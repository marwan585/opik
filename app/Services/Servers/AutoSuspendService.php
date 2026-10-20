<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\ActivityLog;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Illuminate\Support\Facades\Log;

/**
 * Cloud Server — Auto-Suspend Service
 *
 * Suspends a server when:
 *  1. Disk usage exceeds the configured limit (auto-suspend on disk overuse).
 *  2. Suspicious activity is detected (high CPU sustained, repeated failed
 *     starts, abnormal memory consumption, etc.).
 *
 * The thresholds can be tuned via env vars (CLOUD_SERVER_DISK_THRESHOLD_PCT,
 * CLOUD_SERVER_SUSPICIOUS_CPU_PCT, etc.) so different deployments can adjust
 * sensitivity. Defaults are conservative.
 */
class AutoSuspendService
{
    public const REASON_DISK_OVERUSE = 'disk_overuse';
    public const REASON_SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    public function __construct(
        private SuspensionService $suspensionService,
        private DaemonServerRepository $daemonServerRepository
    ) {
    }

    /**
     * Suspend a server for the given reason. The activity is recorded
     * in the activity log so the panel owner can audit it later.
     */
    public function suspend(Server $server, string $reason, array $details = []): void
    {
        if ($server->isSuspended()) {
            return;
        }

        try {
            $this->suspensionService->toggle($server, SuspensionService::ACTION_SUSPEND);
        } catch (\Throwable $e) {
            Log::error('Cloud Server AutoSuspend: failed to suspend server', [
                'server_id' => $server->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        // Write a record to the activity log for audit purposes.
        try {
            ActivityLog::create([
                'event' => 'server.suspended.auto',
                'ip' => '127.0.0.1',
                'properties' => json_encode(array_merge([
                    'server_id' => $server->id,
                    'server_uuid' => $server->uuid,
                    'reason' => $reason,
                ], $details)),
            ]);
        } catch (\Throwable $e) {
            // Activity log table may not exist on some installs — ignore.
        }

        Log::warning('Cloud Server AutoSuspend: server suspended', [
            'server_id' => $server->id,
            'reason' => $reason,
            'details' => $details,
        ]);
    }

    /**
     * Unsuspend a previously auto-suspended server.
     */
    public function unsuspend(Server $server): void
    {
        if (!$server->isSuspended()) {
            return;
        }

        try {
            $this->suspensionService->toggle($server, SuspensionService::ACTION_UNSUSPEND);
            Log::info('Cloud Server AutoSuspend: server unsuspended', ['server_id' => $server->id]);
        } catch (\Throwable $e) {
            Log::error('Cloud Server AutoSuspend: failed to unsuspend', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch the live disk usage (in bytes) for a server from Wings.
     * Returns null if the daemon is unreachable.
     */
    public function getDiskUsage(Server $server): ?array
    {
        try {
            $stats = $this->daemonServerRepository->setServer($server)->getServerResourceUsage();
            return [
                'disk_bytes' => (int) ($stats['disk_bytes'] ?? 0),
                'disk_limit_bytes' => $server->disk * 1024 * 1024, // MiB → bytes
                'memory_bytes' => (int) ($stats['memory_bytes'] ?? 0),
                'memory_limit_bytes' => $server->memory * 1024 * 1024,
                'cpu_percent' => (float) ($stats['cpu_absolute'] ?? 0),
                'state' => $stats['state'] ?? 'unknown',
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Heuristic that decides whether the server's current state is
     * "suspicious" enough to warrant an automatic suspension.
     */
    public function isSuspicious(Server $server, ?array $usage = null): ?array
    {
        if ($usage === null) {
            $usage = $this->getDiskUsage($server);
            if ($usage === null) {
                return null;
            }
        }

        $cpuThreshold = (float) env('CLOUD_SERVER_SUSPICIOUS_CPU_PCT', 99.0);
        $memOverhead = (float) env('CLOUD_SERVER_SUSPICIOUS_MEM_OVERHEAD_PCT', 20.0);

        // CPU sustained at threshold for the duration of the check window.
        if ($usage['cpu_percent'] >= $cpuThreshold) {
            return [
                'flag' => 'high_cpu',
                'cpu_percent' => $usage['cpu_percent'],
                'threshold' => $cpuThreshold,
            ];
        }

        // Memory consumption > limit + overhead percent.
        if ($usage['memory_limit_bytes'] > 0) {
            $memOverheadBytes = $usage['memory_limit_bytes'] * (1 + $memOverhead / 100);
            if ($usage['memory_bytes'] > $memOverheadBytes) {
                return [
                    'flag' => 'memory_overrun',
                    'memory_bytes' => $usage['memory_bytes'],
                    'memory_limit_bytes' => $usage['memory_limit_bytes'],
                    'threshold_pct' => $memOverhead,
                ];
            }
        }

        // Disk usage exceeds limit.
        if ($usage['disk_limit_bytes'] > 0 && $usage['disk_bytes'] > $usage['disk_limit_bytes']) {
            return [
                'flag' => 'disk_overuse',
                'disk_bytes' => $usage['disk_bytes'],
                'disk_limit_bytes' => $usage['disk_limit_bytes'],
            ];
        }

        return null;
    }
}
