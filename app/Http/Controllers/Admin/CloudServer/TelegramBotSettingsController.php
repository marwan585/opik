<?php

namespace Pterodactyl\Http\Controllers\Admin\CloudServer;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Eloquent\SettingsRepository;
use Pterodactyl\Models\TelegramBotUser;
use Pterodactyl\Models\TelegramPanelCreation;
use Pterodactyl\Services\CloudServer\TelegramBotService;

/**
 * Cloud Server — Telegram Bot Settings Controller
 *
 * Owner-only admin menu for configuring the auto-panel-creation
 * Telegram bot. The bot itself runs as a daemon via:
 *   php artisan cloudserver:telegram-bot
 */
class TelegramBotSettingsController extends Controller
{
    public function __construct(
        private AlertsMessageBag $alert,
        private SettingsRepository $settings,
        private TelegramBotService $bot,
    ) {
    }

    public function index(): View
    {
        $stats = [
            'total_users' => TelegramBotUser::where('is_blocked', false)->count(),
            'total_blocked' => TelegramBotUser::where('is_blocked', true)->count(),
            'total_panels' => TelegramPanelCreation::where('status', 'created')->count(),
            'total_failed' => TelegramPanelCreation::where('status', 'failed')->count(),
        ];

        $recentCreations = TelegramPanelCreation::orderByDesc('id')->limit(10)->get();
        $allowedUsers = TelegramBotUser::orderByDesc('id')->limit(20)->get();

        return view('admin.cloud_server.telegram_bot', [
            'enabled' => (string) $this->settings->get('cloud:tg:enabled', '0') === '1',
            'botToken' => (string) $this->settings->get('cloud:tg:bot_token', ''),
            'ownerId' => (string) $this->settings->get('cloud:tg:owner_id', ''),
            'groupId' => (string) $this->settings->get('cloud:tg:group_id', ''),
            'stats' => $stats,
            'recentCreations' => $recentCreations,
            'allowedUsers' => $allowedUsers,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled'    => ['nullable', 'in:1,0'],
            'bot_token'  => ['nullable', 'string', 'max:500'],
            'owner_id'   => ['nullable', 'string', 'max:50'],
            'group_id'   => ['nullable', 'string', 'max:50'],
        ]);

        $this->settings->set('cloud:tg:enabled', !empty($data['enabled']) ? '1' : '0');
        $this->settings->set('cloud:tg:bot_token', trim((string) ($data['bot_token'] ?? '')));
        $this->settings->set('cloud:tg:owner_id', trim((string) ($data['owner_id'] ?? '')));
        $this->settings->set('cloud:tg:group_id', trim((string) ($data['group_id'] ?? '')));

        $this->alert->success('Pengaturan Telegram Bot berhasil disimpan.')->flash();

        return redirect()->route('admin.cloud.telegram');
    }

    /**
     * Add a user via the admin UI (alternative to using the bot itself).
     */
    public function addUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'telegram_user_id' => ['required', 'integer', 'min:1'],
            'display_name' => ['nullable', 'string', 'max:191'],
        ]);

        $botUser = TelegramBotUser::firstOrCreate(
            ['telegram_user_id' => (int) $data['telegram_user_id']],
            [
                'is_blocked' => false,
                'panels_created' => 0,
                'display_name' => $data['display_name'] ?? null,
            ]
        );

        if ($botUser->is_blocked) {
            $botUser->update(['is_blocked' => false]);
        }

        $this->alert->success("User ID {$data['telegram_user_id']} berhasil ditambahkan.")->flash();
        return redirect()->route('admin.cloud.telegram');
    }

    public function revokeUser(int $id): RedirectResponse
    {
        $u = TelegramBotUser::findOrFail($id);
        $u->update(['is_blocked' => true]);
        $this->alert->success("Akses user {$u->telegram_user_id} dicabut.")->flash();
        return redirect()->route('admin.cloud.telegram');
    }

    public function deleteUser(int $id): RedirectResponse
    {
        $u = TelegramBotUser::findOrFail($id);
        $u->delete();
        $this->alert->success("User {$u->telegram_user_id} dihapus.")->flash();
        return redirect()->route('admin.cloud.telegram');
    }
}
