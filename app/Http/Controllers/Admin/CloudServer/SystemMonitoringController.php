<?php

namespace Pterodactyl\Http\Controllers\Admin\CloudServer;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Node;

/**
 * Cloud Server — System Monitoring Controller
 *
 * Shows live server-side metrics: CPU load, memory usage, disk space,
 * PHP runtime info, Laravel version, database stats, and panel
 * counters. The page auto-refreshes every 5 seconds via JavaScript.
 */
class SystemMonitoringController extends Controller
{
    public function index(): View
    {
        return view('admin.cloud_server.monitoring', $this->collectMetrics());
    }

    /**
     * JSON endpoint that returns the same metrics for the
     * auto-refresh polling.
     */
    public function metrics(): JsonResponse
    {
        return response()->json($this->collectMetrics());
    }

    private function collectMetrics(): array
    {
        // CPU load averages (Linux /proc/loadavg, falls back to null on Windows).
        $loadAvg = null;
        if (is_readable('/proc/loadavg')) {
            $parts = explode(' ', (string) file_get_contents('/proc/loadavg'));
            if (count($parts) >= 3) {
                $loadAvg = [
                    'm1'  => (float) $parts[0],
                    'm5'  => (float) $parts[1],
                    'm15' => (float) $parts[2],
                ];
            }
        }

        // CPU count.
        $cpuCount = (int) (env('CPU_COUNT', false) ?: (is_file('/proc/cpuinfo')
            ? count(array_filter(explode("\n", (string) file_get_contents('/proc/cpuinfo')), fn ($l) => str_starts_with($l, 'processor')))
            : 1));

        // Memory info (Linux /proc/meminfo).
        $memInfo = null;
        if (is_readable('/proc/meminfo')) {
            $lines = array_filter(explode("\n", (string) file_get_contents('/proc/meminfo')));
            $parsed = [];
            foreach ($lines as $line) {
                if (preg_match('/^(\w+):\s+(\d+)\s*kB/i', $line, $m)) {
                    $parsed[strtolower($m[1])] = (int) $m[2] * 1024; // kB → bytes
                }
            }
            if (isset($parsed['memtotal'], $parsed['memavailable'])) {
                $memInfo = [
                    'total' => $parsed['memtotal'],
                    'available' => $parsed['memavailable'],
                    'used' => $parsed['memtotal'] - $parsed['memavailable'],
                    'used_pct' => $parsed['memtotal'] > 0
                        ? round((($parsed['memtotal'] - $parsed['memavailable']) / $parsed['memtotal']) * 100, 2)
                        : 0,
                ];
            }
        }

        // Disk usage for the panel directory.
        $disk = null;
        try {
            $path = base_path();
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            if ($free !== false && $total !== false && $total > 0) {
                $disk = [
                    'total' => $total,
                    'free' => $free,
                    'used' => $total - $free,
                    'used_pct' => round((($total - $free) / $total) * 100, 2),
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Uptime.
        $uptime = null;
        if (is_readable('/proc/uptime')) {
            $parts = explode(' ', (string) file_get_contents('/proc/uptime'));
            $uptime = (float) $parts[0] ?? null;
        }

        // Panel counters.
        $panelStats = [
            'servers_total'     => Server::count(),
            'servers_active'     => Server::whereNull('status')->count(),
            'servers_suspended'  => Server::where('status', Server::STATUS_SUSPENDED)->count(),
            'users_total'        => User::count(),
            'users_admins'       => User::where('root_admin', 1)->count(),
            'nodes_total'        => Node::count(),
        ];

        // PHP runtime info.
        $phpInfo = [
            'version'      => PHP_VERSION,
            'sapi'         => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'max_exec'     => ini_get('max_execution_time'),
            'opcache'      => function_exists('opcache_get_status') ? (bool) ini_get('opcache.enable') : false,
            'extensions'   => implode(', ', array_slice(get_loaded_extensions(), 0, 30)),
            'ext_count'     => count(get_loaded_extensions()),
        ];

        // Laravel / app info.
        $appInfo = [
            'laravel'      => app()->version(),
            'panel'        => config('app.version'),
            'fork'         => config('app.fork-version'),
            'environment'  => app()->environment(),
            'debug'        => config('app.debug'),
            'queue_driver' => config('queue.default'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'db_driver'    => config('database.default'),
        ];

        // Database size (best effort — works for MySQL).
        $dbSize = null;
        try {
            $dbName = config('database.connections.mysql.database');
            if ($dbName) {
                $row = DB::table('information_schema.tables')
                    ->where('table_schema', $dbName)
                    ->selectRaw('SUM(data_length + index_length) AS size')
                    ->first();
                $dbSize = $row?->size ? (int) $row->size : null;
            }
        } catch (\Throwable $e) {
            // ignore — some setups block information_schema
        }

        return [
            'loadAvg'    => $loadAvg,
            'cpuCount'   => $cpuCount,
            'memInfo'    => $memInfo,
            'disk'       => $disk,
            'uptime'     => $uptime,
            'panelStats' => $panelStats,
            'phpInfo'    => $phpInfo,
            'appInfo'    => $appInfo,
            'dbSize'     => $dbSize,
            'collectedAt' => now()->toDateTimeString(),
        ];
    }
}
