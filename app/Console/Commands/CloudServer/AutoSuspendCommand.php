<?php

namespace Pterodactyl\Console\Commands\CloudServer;

use Illuminate\Console\Command;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\AutoSuspendService;

/**
 * Cloud Server — Auto-Suspend Scanner
 *
 * Runs periodically (every 5 minutes via Laravel scheduler) and inspects
 * every active server. Suspends any server that:
 *   - Is currently using more disk than its configured limit.
 *   - Shows suspicious activity (sustained high CPU, memory overrun).
 *
 * Configurable via env:
 *   CLOUD_SERVER_AUTOSUSPEND_ENABLED=true|false   (default true)
 *   CLOUD_SERVER_DISK_THRESHOLD_PCT=100           (% of limit before suspending)
 *   CLOUD_SERVER_SUSPICIOUS_CPU_PCT=99            (sustained CPU%)
 *   CLOUD_SERVER_SUSPICIOUS_MEM_OVERHEAD_PCT=20   (% over limit before suspending)
 *
 * Signature: php artisan cloudserver:autosuspend
 */
class AutoSuspendCommand extends Command
{
    protected $signature = 'cloudserver:autosuspend
                            {--dry-run : Show what would happen without actually suspending}
                            {--reason= : Force a specific reason (disk_overuse|suspicious_activity)}';

    protected $description = 'Cloud Server: scan all servers and auto-suspend any that exceed disk limits or show suspicious activity.';

    public function handle(AutoSuspendService $service): int
    {
        if (!env('CLOUD_SERVER_AUTOSUSPEND_ENABLED', true)) {
            $this->info('Cloud Server: auto-suspend is disabled via CLOUD_SERVER_AUTOSUSPEND_ENABLED=false');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $forcedReason = $this->option('reason');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode — no servers will actually be suspended.');
        }

        // Only check active (non-suspended, non-installing) servers.
        $servers = Server::whereNull('status')
            ->orWhere('status', '!=', Server::STATUS_SUSPENDED)
            ->get();

        $this->info(sprintf('Cloud Server: scanning %d servers...', $servers->count()));

        $suspended = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($servers as $server) {
            try {
                if ($server->isSuspended()) {
                    $skipped++;
                    continue;
                }

                $usage = $service->getDiskUsage($server);
                if ($usage === null) {
                    $this->line(sprintf(
                        '  ⚠️  Server #%d (%s): daemon unreachable, skipping.',
                        $server->id,
                        $server->name
                    ));
                    $skipped++;
                    continue;
                }

                $suspicious = $service->isSuspicious($server, $usage);

                if ($suspicious === null && !$forcedReason) {
                    continue;
                }

                $reason = $forcedReason ?: ($suspicious['flag'] === 'disk_overuse'
                    ? AutoSuspendService::REASON_DISK_OVERUSE
                    : AutoSuspendService::REASON_SUSPICIOUS_ACTIVITY);

                $details = $suspicious ?? ['forced' => true];

                $this->warn(sprintf(
                    '  🚫 Server #%d (%s): SUSPENDING — reason=%s details=%s',
                    $server->id,
                    $server->name,
                    $reason,
                    json_encode($details)
                ));

                if (!$dryRun) {
                    $service->suspend($server, $reason, $details);
                }
                $suspended++;
            } catch (\Throwable $e) {
                $this->error(sprintf(
                    '  ❌ Server #%d (%s): error — %s',
                    $server->id,
                    $server->name,
                    $e->getMessage()
                ));
                $errors++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Cloud Server: scan complete. Suspended: %d, Skipped: %d, Errors: %d%s',
            $suspended,
            $skipped,
            $errors,
            $dryRun ? ' (dry-run, no actual suspensions)' : ''
        ));

        return self::SUCCESS;
    }
}
