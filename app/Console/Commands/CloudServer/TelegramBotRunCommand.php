<?php

namespace Pterodactyl\Console\Commands\CloudServer;

use Illuminate\Console\Command;
use Pterodactyl\Services\CloudServer\TelegramBotService;
use Illuminate\Support\Facades\Log;

/**
 * Cloud Server — Telegram Bot Long-Polling Worker
 *
 * Runs the Telegram bot using getUpdates long polling. Run this as a
 * systemd service (or supervisor) so it stays alive:
 *
 *   php artisan cloudserver:telegram-bot
 *
 * Or with a timeout (default 0 = forever):
 *   php artisan cloudserver:telegram-bot --timeout=3600
 *
 * The worker uses long-polling with a 50-second timeout per request,
 * and gracefully handles SIGTERM/SIGINT to shut down cleanly.
 */
class TelegramBotRunCommand extends Command
{
    protected $signature = 'cloudserver:telegram-bot
                            {--timeout=0 : Max runtime in seconds (0 = forever)}
                            {--poll-timeout=50 : Telegram long-poll timeout per request}';

    protected $description = 'Cloud Server: run the Telegram bot worker (long-polling). Run as a daemon via systemd/supervisor.';

    private bool $shouldStop = false;

    public function handle(TelegramBotService $bot): int
    {
        if (!$bot->isEnabled()) {
            $this->error('Telegram bot is not enabled. Configure bot_token + owner_id + enabled in admin settings first.');
            return self::FAILURE;
        }

        $timeout = (int) $this->option('timeout');
        $pollTimeout = (int) $this->option('poll-timeout');
        $startTime = time();

        // Set up signal handlers for clean shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn() => $this->shouldStop = true);
        }

        $this->info('Telegram bot worker started.');
        $this->info('Bot token: ' . substr($bot->getBotToken(), 0, 8) . '...');
        $this->info('Owner ID: ' . $bot->getOwnerId());
        $this->info('Group ID: ' . ($bot->getGroupId() ?: '(not set)'));
        $this->info('Timeout: ' . ($timeout > 0 ? "{$timeout}s" : 'forever'));
        $this->info('Press Ctrl+C to stop.');
        $this->newLine();

        // Get bot info to verify token
        $me = $bot->api('getMe');
        if (!$me || empty($me['username'])) {
            $this->error('Failed to verify bot token with Telegram API.');
            Log::error('TelegramBot: failed getMe');
            return self::FAILURE;
        }
        $this->info("Bot username: @{$me['username']}");
        $this->info("Bot name: " . ($me['first_name'] ?? ''));
        $this->newLine();

        $offset = 0;
        $iteration = 0;

        while (!$this->shouldStop) {
            $iteration++;
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                $this->info('Timeout reached, exiting.');
                break;
            }

            try {
                $updates = $bot->api('getUpdates', [
                    'offset' => $offset,
                    'timeout' => $pollTimeout,
                    'allowed_updates' => ['message', 'callback_query', 'edited_message'],
                ]);

                if (empty($updates)) {
                    continue;
                }

                foreach ($updates as $update) {
                    $offset = ($update['update_id'] ?? 0) + 1;
                    $bot->handleUpdate($update);
                }
            } catch (\Throwable $e) {
                $this->error('Iteration ' . $iteration . ' error: ' . $e->getMessage());
                Log::error('TelegramBot worker iteration error', ['error' => $e->getMessage()]);
                sleep(2);
            }
        }

        $this->info('Telegram bot worker stopped.');
        return self::SUCCESS;
    }
}
