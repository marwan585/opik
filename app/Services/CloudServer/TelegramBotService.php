<?php

namespace Pterodactyl\Services\CloudServer;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Pterodactyl\Models\TelegramBotUser;
use Pterodactyl\Models\TelegramPanelCreation;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Services\Users\UserCreationService;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Users\UserDeletionService;
use Illuminate\Support\Str;

/**
 * Cloud Server — Telegram Bot Service
 *
 * Implements the auto-panel-creation Telegram bot.
 *
 * Two roles:
 *  - Admin (owner_id): full control. See admin menu on /start:
 *    add user access / delete user / delete server / revoke access.
 *  - Allowed user: see "Create Panel" menu on /start (only inside group_id).
 *    Flow: pick RAM (1-7 GB) -> pick egg -> input username -> bot creates
 *    panel user + server, sends credentials to user's private chat.
 *
 * The bot uses Telegram's long-polling getUpdates API.
 */
class TelegramBotService
{
    private const API_BASE = 'https://api.telegram.org/bot';
    private const SESSION_TTL = 1800;

    public function __construct(
        private SettingsRepository $settings,
        private UserCreationService $userCreationService,
        private ServerCreationService $serverCreationService,
        private ServerDeletionService $serverDeletionService,
        private UserDeletionService $userDeletionService,
    ) {
    }

    public function getBotToken(): string
    {
        return (string) $this->settings->get('cloud:tg:bot_token', '');
    }

    public function getOwnerId(): int
    {
        return (int) $this->settings->get('cloud:tg:owner_id', 0);
    }

    public function getGroupId(): string
    {
        return (string) $this->settings->get('cloud:tg:group_id', '');
    }

    public function isEnabled(): bool
    {
        return (string) $this->settings->get('cloud:tg:enabled', '0') === '1'
            && $this->getBotToken() !== ''
            && $this->getOwnerId() > 0;
    }

    public function api(string $method, array $params = []): ?array
    {
        $token = $this->getBotToken();
        if ($token === '') {
            return null;
        }
        try {
            $response = Http::timeout(60)->post(self::API_BASE . $token . '/' . $method, $params);
            if ($response->successful()) {
                $data = $response->json();
                return $data['ok'] ? $data['result'] ?? [] : null;
            }
            Log::error('TelegramBot API error', ['method' => $method, 'status' => $response->status(), 'body' => $response->body()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('TelegramBot API exception', ['method' => $method, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function sendMessage(string|int $chatId, string $text, array $extra = []): ?array
    {
        return $this->api('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $extra));
    }

    public function handleUpdate(array $update): void
    {
        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            }
        } catch (\Throwable $e) {
            Log::error('TelegramBot handleUpdate exception', ['error' => $e->getMessage(), 'update' => $update]);
        }
    }

    private function handleMessage(array $message): void
    {
        $chat = $message['chat'] ?? null;
        $from = $message['from'] ?? null;
        $text = trim($message['text'] ?? '');
        if (!$chat || !$from) return;

        $chatId = $chat['id'];
        $tgUserId = $from['id'];
        $tgUsername = $from['username'] ?? null;

        if ($text === '/start' || $text === '/menu' || str_starts_with($text, '/start ')) {
            $this->sendMainMenu($chatId, $tgUserId, $tgUsername, $chat);
            return;
        }

        if (isset($message['reply_to_message']) && $text !== '') {
            $this->handleForceReply($chatId, $tgUserId, $tgUsername, $text, $message['reply_to_message']);
            return;
        }
    }

    private function sendMainMenu(int|string $chatId, int $tgUserId, ?string $tgUsername, array $chat): void
    {
        $chatType = $chat['type'] ?? '';
        $isGroup = in_array($chatType, ['group', 'supergroup']);
        $configuredGroupId = $this->getGroupId();
        $groupId = $configuredGroupId !== '' ? (int) $configuredGroupId : 0;

        $isOwner = ($tgUserId === $this->getOwnerId());

        if ($isOwner) {
            $this->sendAdminMenu($chatId, $tgUsername);
            return;
        }

        if ($isGroup && $groupId > 0 && $chatId !== $groupId) {
            return;
        }

        $botUser = TelegramBotUser::where('telegram_user_id', $tgUserId)->first();
        if (!$botUser || $botUser->is_blocked) {
            $this->sendMessage($chatId, "🚫 Anda tidak memiliki akses ke bot ini.\n\nHubungi admin untuk mendapatkan akses.");
            return;
        }

        $botUser->update([
            'last_used_at' => now(),
            'telegram_username' => $tgUsername ?? $botUser->telegram_username,
        ]);

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🚀 Buat Panel Baru', 'callback_data' => 'create_panel']],
                [['text' => '📋 List Panel Saya', 'callback_data' => 'my_panels']],
            ],
        ];

        $name = $botUser->display_name ?: ($tgUsername ? '@' . $tgUsername : 'User');
        $this->sendMessage($chatId, "👋 Halo, <b>" . e($name) . "</b>!\n\nPilih menu di bawah untuk membuat panel Pterodactyl baru.", [
            'reply_markup' => json_encode($keyboard),
        ]);
    }

    private function sendAdminMenu(int|string $chatId, ?string $tgUsername): void
    {
        $totalUsers = TelegramBotUser::where('is_blocked', false)->count();
        $totalPanels = TelegramPanelCreation::where('status', 'created')->count();
        $totalServers = Server::count();

        $text = "👑 <b>Admin Panel Control</b>\n\n";
        $text .= "📊 <b>Statistik:</b>\n";
        $text .= "  • User bot aktif: <b>{$totalUsers}</b>\n";
        $text .= "  • Panel dibuat via bot: <b>{$totalPanels}</b>\n";
        $text .= "  • Total server di panel: <b>{$totalServers}</b>\n\n";
        $text .= "Pilih aksi di bawah:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '➕ Tambah Akses User', 'callback_data' => 'admin_add_user'],
                    ['text' => '👥 List User', 'callback_data' => 'admin_list_users'],
                ],
                [
                    ['text' => '🗑 Hapus User', 'callback_data' => 'admin_delete_user'],
                    ['text' => '🚫 Cabut Akses', 'callback_data' => 'admin_revoke'],
                ],
                [
                    ['text' => '🖥 Hapus Server', 'callback_data' => 'admin_list_servers'],
                ],
                [
                    ['text' => '🔄 Refresh', 'callback_data' => 'admin_refresh'],
                ],
            ],
        ];

        $this->sendMessage($chatId, $text, ['reply_markup' => json_encode($keyboard)]);
    }

    private function handleCallbackQuery(array $cb): void
    {
        $cbId = $cb['id'];
        $from = $cb['from'];
        $message = $cb['message'] ?? null;
        $data = $cb['data'] ?? '';

        if (!$message || !$from) {
            $this->api('answerCallbackQuery', ['callback_query_id' => $cbId]);
            return;
        }

        $chatId = $message['chat']['id'];
        $tgUserId = $from['id'];
        $tgUsername = $from['username'] ?? null;
        $isOwner = ($tgUserId === $this->getOwnerId());

        $this->api('answerCallbackQuery', ['callback_query_id' => $cbId]);

        if ($data === 'create_panel') {
            $this->startCreatePanel($chatId, $tgUserId, $tgUsername);
            return;
        }
        if ($data === 'my_panels') {
            $this->listMyPanels($chatId, $tgUserId);
            return;
        }
        if (str_starts_with($data, 'pick_ram:')) {
            $this->showEggPicker($chatId, $tgUserId, $tgUsername, (int) substr($data, 9));
            return;
        }
        if (str_starts_with($data, 'pick_egg:')) {
            $parts = explode(':', $data);
            $this->askUsername($chatId, $tgUserId, $tgUsername, (int) $parts[1], (int) $parts[2]);
            return;
        }
        if (str_starts_with($data, 'cancel')) {
            $this->clearSession($tgUserId);
            $this->api('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message['message_id']]);
            $this->sendMessage($chatId, "❌ Dibatalkan.");
            return;
        }
        if (str_starts_with($data, 'admin_')) {
            if (!$isOwner) {
                $this->sendMessage($chatId, "🚫 Akses ditolak. Menu admin hanya untuk owner.");
                return;
            }
            if ($data === 'admin_refresh') {
                $this->api('deleteMessage', ['chat_id' => $chatId, 'message_id' => $message['message_id']]);
                $this->sendAdminMenu($chatId, $tgUsername);
                return;
            }
            $this->handleAdminCallback($chatId, $data, $tgUserId);
            return;
        }
        if (str_starts_with($data, 'del_user:')) {
            $this->adminDeleteUser($chatId, (int) substr($data, 9));
            return;
        }
        if (str_starts_with($data, 'revoke:')) {
            $this->adminRevokeAccess($chatId, (int) substr($data, 7));
            return;
        }
        if (str_starts_with($data, 'del_server:')) {
            $this->adminDeleteServerConfirm($chatId, (int) substr($data, 11));
            return;
        }
        if (str_starts_with($data, 'del_server_yes:')) {
            $this->adminDeleteServerExecute($chatId, (int) substr($data, 15), $tgUserId);
            return;
        }
    }

    private function startCreatePanel(int|string $chatId, int $tgUserId, ?string $tgUsername): void
    {
        $botUser = TelegramBotUser::where('telegram_user_id', $tgUserId)->first();
        if (!$botUser || $botUser->is_blocked) {
            $this->sendMessage($chatId, "🚫 Anda tidak memiliki akses.");
            return;
        }

        $buttons = [];
        $row = [];
        for ($gb = 1; $gb <= 7; $gb++) {
            $row[] = ['text' => "{$gb} GB", 'callback_data' => "pick_ram:{$gb}"];
            if (count($row) === 4) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) $buttons[] = $row;
        $buttons[] = [['text' => '❌ Batal', 'callback_data' => 'cancel']];

        $this->sendMessage($chatId, "🚀 <b>Buat Panel Baru</b>\n\nPilih kapasitas RAM server:", [
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    private function showEggPicker(int|string $chatId, int $tgUserId, ?string $tgUsername, int $ramGb): void
    {
        $this->setSession($tgUserId, ['step' => 'pick_egg', 'ram_gb' => $ramGb]);

        $eggs = Egg::orderBy('name')->limit(20)->get();
        if ($eggs->isEmpty()) {
            $this->sendMessage($chatId, "❌ Tidak ada egg tersedia di panel. Hubungi admin.");
            return;
        }

        $buttons = [];
        foreach ($eggs as $egg) {
            $name = mb_strlen($egg->name) > 30 ? mb_substr($egg->name, 0, 28) . '…' : $egg->name;
            $buttons[] = [['text' => "📦 " . $name, 'callback_data' => "pick_egg:{$egg->id}:{$ramGb}"]];
        }
        $buttons[] = [['text' => '❌ Batal', 'callback_data' => 'cancel']];

        $this->sendMessage($chatId, "✅ RAM: <b>{$ramGb} GB</b>\n\nPilih egg (jenis server):", [
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    private function askUsername(int|string $chatId, int $tgUserId, ?string $tgUsername, int $eggId, int $ramGb): void
    {
        $egg = Egg::find($eggId);
        if (!$egg) {
            $this->sendMessage($chatId, "❌ Egg tidak ditemukan.");
            return;
        }

        $this->setSession($tgUserId, [
            'step' => 'input_username',
            'ram_gb' => $ramGb,
            'egg_id' => $eggId,
            'egg_name' => $egg->name,
        ]);

        $this->sendMessage($chatId, "✅ RAM: <b>{$ramGb} GB</b>\n📦 Egg: <b>" . e($egg->name) . "</b>\n\nSilakan balas pesan ini dengan <b>username panel</b> yang Anda inginkan (3-20 karakter, huruf/angka/underscore).\n\nContoh: <code>serverku</code>", [
            'reply_markup' => json_encode(['force_reply' => true, 'selective' => true]),
        ]);
    }

    private function handleForceReply(int|string $chatId, int $tgUserId, ?string $tgUsername, string $text, array $replyTo): void
    {
        $session = $this->getSession($tgUserId);
        if (!$session) return;
        $step = $session['step'] ?? '';

        if ($step === 'admin_add_user') {
            $this->clearSession($tgUserId);
            $this->handleAdminForceReply($chatId, $tgUserId, $text);
            return;
        }

        if ($step !== 'input_username') {
            return;
        }

        $username = trim($text);
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $this->sendMessage($chatId, "❌ Username tidak valid. Harus 3-20 karakter, hanya huruf/angka/underscore.\n\nSilakan ulangi /menu untuk memulai ulang.");
            $this->clearSession($tgUserId);
            return;
        }

        if (User::where('username', $username)->exists()) {
            $this->sendMessage($chatId, "❌ Username <code>" . e($username) . "</code> sudah dipakai. Silakan pilih username lain.\n\nKetik /menu untuk memulai ulang.");
            $this->clearSession($tgUserId);
            return;
        }

        $ramGb = (int) $session['ram_gb'];
        $eggId = (int) $session['egg_id'];
        $eggName = $session['egg_name'];

        $this->clearSession($tgUserId);
        $this->createPanelAndServer($chatId, $tgUserId, $tgUsername, $username, $eggId, $eggName, $ramGb);
    }

    private function createPanelAndServer(int|string $chatId, int $tgUserId, ?string $tgUsername, string $username, int $eggId, string $eggName, int $ramGb): void
    {
        $progressMsg = $this->sendMessage($chatId, "⏳ Membuat panel... (1/3) Membuat user");
        $progressMsgId = $progressMsg['message_id'] ?? 0;

        try {
            $password = Str::random(16);
            $email = $username . '@cloudserver.local';
            if (User::where('email', $email)->exists()) {
                $email = $username . '_' . Str::random(4) . '@cloudserver.local';
            }

            $user = $this->userCreationService->handle([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'name_first' => $username,
                'name_last' => '(TG)',
                'root_admin' => false,
                'language' => 'en',
            ]);

            $this->editMessage($chatId, $progressMsgId, "⏳ Membuat panel... (2/3) User dibuat ✓. Membuat server...");

            $node = Node::where('public', true)->first();
            if (!$node) {
                throw new \Exception('Tidak ada node publik yang tersedia.');
            }

            $allocation = Allocation::where('node_id', $node->id)
                ->whereNull('server_id')
                ->orderBy('id')
                ->first();
            if (!$allocation) {
                throw new \Exception('Tidak ada alokasi port yang tersedia di node ini.');
            }

            $memoryMb = $ramGb * 1024;
            $diskMb = $ramGb * 5120;
            $egg = Egg::find($eggId);

            $dockerImage = 'ghcr.io/pterodactyl/yolks:java_17';
            if (is_array($egg->docker_images)) {
                $first = array_key_first($egg->docker_images);
                if ($first) $dockerImage = (string) $first;
            } elseif (is_string($egg->docker_images) && $egg->docker_images !== '') {
                $dockerImage = $egg->docker_images;
            }

            // Build environment from egg variables' default values.
            // Without this, server creation fails because required egg
            // variables (e.g. SERVER_JARFILE, VANILLA_VERSION for Minecraft)
            // have no value and validation throws.
            $eggVariables = \Pterodactyl\Models\EggVariable::where('egg_id', $eggId)->get();
            $environment = [];
            foreach ($eggVariables as $var) {
                $environment[$var->env_variable] = $var->default_value ?? '';
            }

            $server = $this->serverCreationService->handle([
                'name' => $username . '-server',
                'description' => 'Dibuat via Telegram Bot oleh @' . ($tgUsername ?? 'unknown'),
                'owner_id' => $user->id,
                'node_id' => $node->id,
                'allocation_id' => $allocation->id,
                'nest_id' => $egg->nest_id,
                'egg_id' => $eggId,
                'memory' => $memoryMb,
                'swap' => 0,
                'disk' => $diskMb,
                'io' => 500,
                'cpu' => 0,
                'startup' => $egg->startup,
                'image' => $dockerImage,
                'environment' => $environment,
                'skip_scripts' => false,
                'start_on_completion' => false,
            ]);

            $this->editMessage($chatId, $progressMsgId, "⏳ Membuat panel... (3/3) Server dibuat ✓. Mengirim kredensial...");

            TelegramPanelCreation::create([
                'telegram_user_id' => $tgUserId,
                'telegram_username' => $tgUsername,
                'panel_user_id' => $user->id,
                'panel_username' => $username,
                'server_id' => $server->id,
                'server_name' => $server->name,
                'egg_id' => $eggId,
                'egg_name' => $eggName,
                'memory_mb' => $memoryMb,
                'disk_mb' => $diskMb,
                'status' => 'created',
            ]);

            TelegramBotUser::where('telegram_user_id', $tgUserId)->increment('panels_created');

            $panelUrl = config('app.url', 'https://publick.pteroqdactyl.my.id');
            $credText = "🎉 <b>Panel Anda berhasil dibuat!</b>\n\n";
            $credText .= "🌐 <b>URL Panel:</b>\n<code>{$panelUrl}</code>\n\n";
            $credText .= "👤 <b>Username:</b>\n<code>" . e($username) . "</code>\n";
            $credText .= "🔑 <b>Password:</b>\n<code>" . e($password) . "</code>\n\n";
            $credText .= "🖥 <b>Server:</b> " . e($server->name) . "\n";
            $credText .= "📦 <b>Egg:</b> " . e($eggName) . "\n";
            $credText .= "💾 <b>RAM:</b> {$ramGb} GB\n";
            $credText .= "💿 <b>Disk:</b> " . ($diskMb / 1024) . " GB\n\n";
            $credText .= "⚠️ <b>Penting:</b> Simpan kredensial ini di tempat aman. Jangan bagikan ke siapapun.\n\n";
            $credText .= "Silakan login ke panel dengan kredensial di atas.";

            $privateSent = $this->sendMessage($tgUserId, $credText);
            if ($privateSent === null) {
                $this->sendMessage($chatId, "⚠️ Tidak bisa kirim pesan private. Pastikan Anda sudah /start bot ini di private chat.\n\nBerikut kredensial Anda:\n\n" . $credText);
            } else {
                $this->sendMessage($chatId, "✅ Panel berhasil dibuat! Kredensial sudah dikirim ke private chat Anda. Cek pesan dari bot di DM.");
            }

            $adminId = $this->getOwnerId();
            if ($adminId && $adminId !== $tgUserId) {
                $this->sendMessage($adminId, "📋 <b>Panel baru dibuat</b>\n\nUser: @" . e($tgUsername ?? 'unknown') . " ({$tgUserId})\nPanel: " . e($username) . "\nEgg: " . e($eggName) . "\nRAM: {$ramGb} GB");
            }
        } catch (\Throwable $e) {
            Log::error('TelegramBot createPanelAndServer exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            TelegramPanelCreation::create([
                'telegram_user_id' => $tgUserId,
                'telegram_username' => $tgUsername,
                'egg_id' => $eggId,
                'egg_name' => $eggName,
                'memory_mb' => $ramGb * 1024,
                'disk_mb' => $ramGb * 5120,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->editMessage($chatId, $progressMsgId, "❌ <b>Gagal membuat panel</b>\n\nError: <code>" . e($e->getMessage()) . "</code>\n\nSilakan coba lagi atau hubungi admin.");
        }
    }

    private function listMyPanels(int|string $chatId, int $tgUserId): void
    {
        $panels = TelegramPanelCreation::where('telegram_user_id', $tgUserId)
            ->where('status', 'created')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($panels->isEmpty()) {
            $this->sendMessage($chatId, "📋 Anda belum membuat panel apapun.");
            return;
        }

        $text = "📋 <b>Panel Anda</b>\n\n";
        foreach ($panels as $p) {
            $text .= "• <b>" . e($p->panel_username) . "</b> — " . e($p->egg_name) . " (" . ($p->memory_mb / 1024) . " GB RAM)\n";
            $text .= "  Server: " . e($p->server_name) . "\n";
            $text .= "  Dibuat: " . $p->created_at->format('Y-m-d H:i') . "\n\n";
        }

        $this->sendMessage($chatId, $text);
    }

    private function handleAdminCallback(int|string $chatId, string $data, int $adminId): void
    {
        switch ($data) {
            case 'admin_add_user':
                $this->setSession($adminId, ['step' => 'admin_add_user']);
                $this->sendMessage($chatId, "➕ <b>Tambah Akses User</b>\n\nBalas pesan ini dengan <b>Telegram User ID</b> user yang ingin diberi akses.\n\nUntuk mendapatkan user ID, user harus forward pesan ke @userinfobot.", [
                    'reply_markup' => json_encode(['force_reply' => true, 'selective' => true]),
                ]);
                break;
            case 'admin_list_users':
                $this->adminListUsers($chatId);
                break;
            case 'admin_delete_user':
                $this->adminDeleteUserList($chatId);
                break;
            case 'admin_revoke':
                $this->adminRevokeList($chatId);
                break;
            case 'admin_list_servers':
                $this->adminListServers($chatId);
                break;
        }
    }

    public function handleAdminForceReply(int|string $chatId, int $adminId, string $text): void
    {
        $tgId = (int) trim($text);
        if ($tgId <= 0) {
            $this->sendMessage($chatId, "❌ User ID tidak valid. Harus berupa angka.");
            return;
        }

        $botUser = TelegramBotUser::firstOrCreate(
            ['telegram_user_id' => $tgId],
            ['is_blocked' => false, 'panels_created' => 0]
        );
        if ($botUser->is_blocked) {
            $botUser->update(['is_blocked' => false]);
        }

        $this->sendMessage($chatId, "✅ User dengan ID <code>{$tgId}</code> berhasil ditambahkan.\n\nUser sekarang bisa /start bot di group dan membuat panel.");
    }

    private function adminListUsers(int|string $chatId): void
    {
        $users = TelegramBotUser::where('is_blocked', false)->orderByDesc('id')->limit(20)->get();
        if ($users->isEmpty()) {
            $this->sendMessage($chatId, "📋 Belum ada user yang diberi akses.");
            return;
        }

        $text = "👥 <b>User dengan Akses Bot</b>\n\n";
        foreach ($users as $u) {
            $name = $u->display_name ?: ($u->telegram_username ? '@' . $u->telegram_username : '-');
            $text .= "• ID: <code>{$u->telegram_user_id}</code>\n  Nama: " . e($name) . "\n  Panel dibuat: {$u->panels_created}\n\n";
        }

        $this->sendMessage($chatId, $text);
    }

    private function adminDeleteUserList(int|string $chatId): void
    {
        $users = TelegramBotUser::orderByDesc('id')->limit(15)->get();
        if ($users->isEmpty()) {
            $this->sendMessage($chatId, "📋 Belum ada user.");
            return;
        }

        $buttons = [];
        foreach ($users as $u) {
            $name = $u->display_name ?: ($u->telegram_username ? '@' . $u->telegram_username : '#' . $u->telegram_user_id);
            $buttons[] = [['text' => "🗑 " . $name, 'callback_data' => "del_user:{$u->id}"]];
        }
        $buttons[] = [['text' => '❌ Tutup', 'callback_data' => 'cancel']];

        $this->sendMessage($chatId, "🗑 <b>Hapus User</b>\n\nPilih user yang ingin dihapus (menghapus record akses, tidak menghapus panel):", [
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    private function adminDeleteUser(int|string $chatId, int $rowId): void
    {
        $u = TelegramBotUser::find($rowId);
        if (!$u) {
            $this->sendMessage($chatId, "❌ User tidak ditemukan.");
            return;
        }
        $u->delete();
        $this->sendMessage($chatId, "✅ User <code>{$u->telegram_user_id}</code> dihapus dari daftar akses.");
    }

    private function adminRevokeList(int|string $chatId): void
    {
        $users = TelegramBotUser::where('is_blocked', false)->orderByDesc('id')->limit(15)->get();
        if ($users->isEmpty()) {
            $this->sendMessage($chatId, "📋 Tidak ada user untuk dicabut.");
            return;
        }

        $buttons = [];
        foreach ($users as $u) {
            $name = $u->display_name ?: ($u->telegram_username ? '@' . $u->telegram_username : '#' . $u->telegram_user_id);
            $buttons[] = [['text' => "🚫 " . $name, 'callback_data' => "revoke:{$u->id}"]];
        }
        $buttons[] = [['text' => '❌ Tutup', 'callback_data' => 'cancel']];

        $this->sendMessage($chatId, "🚫 <b>Cabut Akses User</b>\n\nPilih user yang ingin dicabut aksesnya:", [
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    private function adminRevokeAccess(int|string $chatId, int $rowId): void
    {
        $u = TelegramBotUser::find($rowId);
        if (!$u) {
            $this->sendMessage($chatId, "❌ User tidak ditemukan.");
            return;
        }
        $u->update(['is_blocked' => true]);
        $this->sendMessage($chatId, "✅ Akses user <code>{$u->telegram_user_id}</code> telah dicabut.");
    }

    private function adminListServers(int|string $chatId): void
    {
        $servers = Server::with('user')->orderByDesc('id')->limit(15)->get();
        if ($servers->isEmpty()) {
            $this->sendMessage($chatId, "🖥 Tidak ada server di panel.");
            return;
        }

        $buttons = [];
        foreach ($servers as $s) {
            $name = mb_strlen($s->name) > 30 ? mb_substr($s->name, 0, 28) . '…' : $s->name;
            $buttons[] = [['text' => "🖥 " . $name, 'callback_data' => "del_server:{$s->id}"]];
        }
        $buttons[] = [['text' => '❌ Tutup', 'callback_data' => 'cancel']];

        $this->sendMessage($chatId, "🖥 <b>Hapus Server</b>\n\nPilih server yang ingin dihapus:", [
            'reply_markup' => json_encode(['inline_keyboard' => $buttons]),
        ]);
    }

    private function adminDeleteServerConfirm(int|string $chatId, int $serverId): void
    {
        $s = Server::with('user')->find($serverId);
        if (!$s) {
            $this->sendMessage($chatId, "❌ Server tidak ditemukan.");
            return;
        }

        $ram = $s->memory / 1024;
        $text = "⚠️ <b>Konfirmasi Hapus Server</b>\n\n";
        $text .= "🖥 <b>Nama:</b> " . e($s->name) . "\n";
        $text .= "👤 <b>Owner:</b> " . e($s->user->username ?? '?') . "\n";
        $text .= "💾 <b>RAM:</b> {$ram} GB\n\n";
        $text .= "Yakin ingin menghapus server ini? Aksi ini tidak bisa dibatalkan.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Ya, hapus', 'callback_data' => "del_server_yes:{$serverId}"],
                    ['text' => '❌ Batal', 'callback_data' => 'cancel'],
                ],
            ],
        ];

        $this->sendMessage($chatId, $text, ['reply_markup' => json_encode($keyboard)]);
    }

    private function adminDeleteServerExecute(int|string $chatId, int $serverId, int $adminId): void
    {
        $s = Server::find($serverId);
        if (!$s) {
            $this->sendMessage($chatId, "❌ Server tidak ditemukan.");
            return;
        }

        try {
            $name = $s->name;
            $this->serverDeletionService->withForce()->handle($s);
            $this->sendMessage($chatId, "✅ Server <b>" . e($name) . "</b> berhasil dihapus.");
        } catch (\Throwable $e) {
            $this->sendMessage($chatId, "❌ Gagal menghapus server: <code>" . e($e->getMessage()) . "</code>");
        }
    }

    private function editMessage(int|string $chatId, int $messageId, string $text): void
    {
        if ($messageId === 0) return;
        $this->api('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    private function setSession(int $tgUserId, array $data): void
    {
        Cache::put('tg:session:' . $tgUserId, $data, now()->addSeconds(self::SESSION_TTL));
    }

    private function getSession(int $tgUserId): ?array
    {
        return Cache::get('tg:session:' . $tgUserId);
    }

    private function clearSession(int $tgUserId): void
    {
        Cache::forget('tg:session:' . $tgUserId);
    }
}
