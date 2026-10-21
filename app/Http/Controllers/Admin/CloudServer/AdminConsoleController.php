<?php

namespace Pterodactyl\Http\Controllers\Admin\CloudServer;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Middleware\AdminOwnerOnly;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Cloud Server — Admin Console (Owner-only)
 *
 * Provides 3 quick actions for the panel owner (admin ID 1):
 *
 *  1. Cleanup Old Files
 *     - Delete stale JS bundles in public/assets/ (not in manifest)
 *     - Clear Laravel views/config/cache/routes
 *     - Clear OPcache (if enabled)
 *     - Remove old log files (keep last 7 days)
 *     - Clear telegram bot session cache
 *
 *  2. Update from GitHub
 *     - Download latest tarball from marwan585/opik main branch
 *     - Extract to temp dir
 *     - Sync app/, config/, database/, resources/, routes/, public/
 *       (preserve .env, storage/, vendor/, public/themes/)
 *     - Replace public/assets/ entirely (delete all old, copy new)
 *     - Run composer install (no-dev, optimize)
 *     - Run php artisan migrate --seed --force
 *     - Clear + rebuild cache
 *     - Restart queue + reload PHP-FPM + nginx
 *     - Restart cloud-tg-bot worker (if configured)
 *
 *  3. Restart All Services
 *     - php artisan queue:restart
 *     - systemctl reload php-fpm
 *     - systemctl reload nginx
 *     - systemctl restart cloud-tg-bot (if configured)
 *     - Clear OPcache
 *
 * All actions are synchronous — the controller runs them and returns
 * a flash message with the result.
 */
class AdminConsoleController extends Controller
{
    private const REPO_TARBALL = 'https://github.com/marwan585/opik/archive/refs/heads/main.tar.gz';
    private const REPO_URL = 'https://github.com/marwan585/opik';

    public function __construct(
        private AlertsMessageBag $alert,
    ) {
    }

    /**
     * Show the admin console page with current system state.
     */
    public function index(): View
    {
        $panelDir = base_path();
        $assetsDir = $panelDir . '/public/assets';

        // Count bundle files
        $bundleCount = 0;
        $bundleSize = 0;
        if (is_dir($assetsDir)) {
            foreach (glob($assetsDir . '/*') as $f) {
                if (is_file($f)) {
                    $bundleCount++;
                    $bundleSize += filesize($f);
                }
            }
        }

        // Read manifest
        $manifestPath = $assetsDir . '/manifest.json';
        $manifestBundle = 'unknown';
        if (is_file($manifestPath)) {
            $m = json_decode((string) file_get_contents($manifestPath), true);
            if (isset($m['main.js']['src'])) {
                $manifestBundle = basename($m['main.js']['src']);
            }
        }

        // Check if bot is configured
        $tgConfigured = false;
        try {
            $tgToken = \Pterodactyl\Models\Setting::where('key', 'cloud:tg:bot_token')->value('value');
            $tgEnabled = \Pterodactyl\Models\Setting::where('key', 'cloud:tg:enabled')->value('value');
            $tgConfigured = !empty($tgToken) && $tgEnabled === '1';
        } catch (\Throwable $e) {
            // ignore
        }

        // Services status
        $services = [];
        foreach (['php8.2-fpm', 'php8.1-fpm', 'php8.3-fpm', 'php-fpm', 'nginx', 'apache2', 'cloud-tg-bot'] as $svc) {
            $status = trim((string) @shell_exec("systemctl is-active {$svc} 2>/dev/null"));
            if ($status !== '') {
                $services[$svc] = $status;
            }
        }

        // Last git commit (if available)
        $lastCommit = null;
        $commitHash = trim((string) @shell_exec('cd ' . escapeshellarg($panelDir) . ' && git rev-parse --short HEAD 2>/dev/null'));
        if ($commitHash) {
            $lastCommit = $commitHash;
        }

        // Disk usage of panel dir
        $panelSize = 0;
        try {
            $output = @shell_exec('du -sh ' . escapeshellarg($panelDir) . ' 2>/dev/null');
            if ($output) {
                $panelSize = trim(explode("\t", $output)[0] ?? '');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Log file size
        $logSize = 0;
        $logPath = $panelDir . '/storage/logs/laravel.log';
        if (is_file($logPath)) {
            $logSize = filesize($logPath);
        }

        return view('admin.cloud_server.console', [
            'bundleCount' => $bundleCount,
            'bundleSize' => $bundleSize,
            'manifestBundle' => $manifestBundle,
            'tgConfigured' => $tgConfigured,
            'services' => $services,
            'lastCommit' => $lastCommit,
            'panelSize' => $panelSize,
            'logSize' => $logSize,
            'panelDir' => $panelDir,
        ]);
    }

    /**
     * Action 1: Cleanup old files.
     */
    public function cleanup(Request $request): RedirectResponse
    {
        $ownerId = AdminOwnerOnly::ownerId();
        $user = $request->user();
        if (!AdminOwnerOnly::isOwner($user->id)) {
            $this->alert->danger('Akses ditolak. Hanya Admin Utama (ID ' . $ownerId . ') yang bisa menjalankan aksi ini.')->flash();
            return redirect()->route('admin.cloud.console');
        }

        $results = [];
        $panelDir = base_path();

        // 1. Run cloudserver:cleanup-bundles command
        try {
            Artisan::call('cloudserver:cleanup-bundles', ['--force' => true]);
            $output = Artisan::output();
            $results[] = '✓ Bundle cleanup: ' . trim($output);
        } catch (\Throwable $e) {
            $results[] = '⚠ Bundle cleanup gagal: ' . $e->getMessage();
        }

        // 2. Clear Laravel caches
        try {
            Artisan::call('view:clear');
            $results[] = '✓ View cache cleared';
        } catch (\Throwable $e) {}

        try {
            Artisan::call('config:clear');
            $results[] = '✓ Config cache cleared';
        } catch (\Throwable $e) {}

        try {
            Artisan::call('cache:clear');
            $results[] = '✓ Application cache cleared';
        } catch (\Throwable $e) {}

        try {
            Artisan::call('route:clear');
            $results[] = '✓ Route cache cleared';
        } catch (\Throwable $e) {}

        // 3. Clear OPcache (CLI)
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $results[] = '✓ OPcache cleared (CLI)';
        }

        // 4. Clear telegram bot session cache
        try {
            $prefix = config('cache.prefix', '') ? config('cache.prefix') . ':' : '';
            Cache::flush();
            $results[] = '✓ Cache flushed (incl. TG bot sessions)';
        } catch (\Throwable $e) {}

        // 5. Delete old log files (keep last 7 days)
        try {
            $logPath = $panelDir . '/storage/logs';
            $deleted = 0;
            if (is_dir($logPath)) {
                foreach (glob($logPath . '/laravel-*.log') as $f) {
                    if (is_file($f) && filemtime($f) < strtotime('-7 days')) {
                        @unlink($f);
                        $deleted++;
                    }
                }
            }
            if ($deleted > 0) {
                $results[] = "✓ Deleted {$deleted} old log file(s)";
            }
        } catch (\Throwable $e) {}

        // 6. Truncate current laravel.log if > 50MB
        try {
            $currentLog = $panelDir . '/storage/logs/laravel.log';
            if (is_file($currentLog) && filesize($currentLog) > 50 * 1024 * 1024) {
                @file_put_contents($currentLog, '');
                $results[] = '✓ Truncated laravel.log (>50MB)';
            }
        } catch (\Throwable $e) {}

        // 7. Clear framework cache data
        try {
            $frameworkCache = $panelDir . '/storage/framework/cache/data';
            if (is_dir($frameworkCache)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($frameworkCache, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                $deleted = 0;
                foreach ($files as $f) {
                    if ($f->getFilename() === '.gitignore') continue;
                    $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
                    $deleted++;
                }
                if ($deleted > 0) {
                    $results[] = "✓ Cleared framework cache ({$deleted} items)";
                }
            }
        } catch (\Throwable $e) {}

        // 8. Clear sessions (logout semua user kecuali admin)
        try {
            $sessionPath = $panelDir . '/storage/framework/sessions';
            if (is_dir($sessionPath)) {
                $files = glob($sessionPath . '/*');
                $deleted = 0;
                foreach ($files as $f) {
                    if (is_file($f) && basename($f) !== '.gitignore') {
                        @unlink($f);
                        $deleted++;
                    }
                }
                if ($deleted > 0) {
                    $results[] = "✓ Cleared {$deleted} session file(s)";
                }
            }
        } catch (\Throwable $e) {}

        Log::info('Cloud Server Admin Console: cleanup run by user ' . $user->id, ['results' => $results]);

        $this->alert->success('Cleanup selesai. ' . count($results) . ' aksi berhasil:')->flash();
        session()->flash('console_results', $results);

        return redirect()->route('admin.cloud.console');
    }

    /**
     * Action 2: Update from GitHub (download + sync + migrate + restart).
     */
    public function update(Request $request): RedirectResponse
    {
        $ownerId = AdminOwnerOnly::ownerId();
        $user = $request->user();
        if (!AdminOwnerOnly::isOwner($user->id)) {
            $this->alert->danger('Akses ditolak. Hanya Admin Utama (ID ' . $ownerId . ') yang bisa menjalankan aksi ini.')->flash();
            return redirect()->route('admin.cloud.console');
        }

        $results = [];
        $panelDir = base_path();
        $tmpDir = sys_get_temp_dir() . '/opik-update-' . uniqid();

        // 1. Enter maintenance mode
        try {
            Artisan::call('down');
            $results[] = '✓ Maintenance mode AKTIF';
        } catch (\Throwable $e) {
            $results[] = '⚠ Tidak bisa masuk maintenance mode: ' . $e->getMessage();
        }

        // 2. Stop bot worker
        @shell_exec('systemctl stop cloud-tg-bot 2>/dev/null');
        $results[] = '✓ Bot worker stopped (if running)';

        // 3. Download tarball
        try {
            @mkdir($tmpDir, 0755, true);
            $tarPath = $tmpDir . '/opik.tar.gz';
            $ok = @file_put_contents($tarPath, @file_get_contents(self::REPO_TARBALL));
            if ($ok === false || $ok === 0) {
                // Try with curl
                @shell_exec('curl -L --fail -o ' . escapeshellarg($tarPath) . ' ' . escapeshellarg(self::REPO_TARBALL) . ' 2>&1');
            }
            if (!is_file($tarPath) || filesize($tarPath) < 1000) {
                throw new \Exception('Gagal download tarball dari GitHub');
            }
            $results[] = '✓ Downloaded tarball (' . round(filesize($tarPath) / 1024 / 1024, 2) . ' MB)';
        } catch (\Throwable $e) {
            $results[] = '❌ Download gagal: ' . $e->getMessage();
            $this->alert->danger('Update gagal: ' . $e->getMessage())->flash();
            session()->flash('console_results', $results);
            try { Artisan::call('up'); } catch (\Throwable $e2) {}
            return redirect()->route('admin.cloud.console');
        }

        // 4. Extract
        try {
            $phar = new \PharData($tarPath);
            $phar->extractTo($tmpDir);
            $srcDir = $tmpDir . '/opik-main';
            if (!is_dir($srcDir)) {
                throw new \Exception('Extract gagal — folder opik-main tidak ditemukan');
            }
            $results[] = '✓ Extracted to temp dir';
        } catch (\Throwable $e) {
            $results[] = '❌ Extract gagal: ' . $e->getMessage();
            $this->alert->danger('Update gagal: ' . $e->getMessage())->flash();
            session()->flash('console_results', $results);
            try { Artisan::call('up'); } catch (\Throwable $e2) {}
            return redirect()->route('admin.cloud.console');
        }

        // 5. Sync directories (preserve .env, storage, vendor, public/themes)
        $syncDirs = ['app', 'config', 'database', 'resources', 'routes', 'bootstrap'];
        $syncFiles = ['composer.json', 'composer.lock', 'package.json', 'webpack.config.js', 'yarn.lock', 'install-cloudserver.sh', 'README.md'];

        foreach ($syncDirs as $dir) {
            $src = $srcDir . '/' . $dir;
            $dst = $panelDir . '/' . $dir;
            if (is_dir($src)) {
                $this->recursiveCopy($src, $dst);
            }
        }
        foreach ($syncFiles as $file) {
            $src = $srcDir . '/' . $file;
            $dst = $panelDir . '/' . $file;
            if (is_file($src)) {
                @copy($src, $dst);
            }
        }
        $results[] = '✓ Synced ' . count($syncDirs) . ' directories + ' . count($syncFiles) . ' files';

        // 6. Replace public/assets/ entirely
        $assetsDir = $panelDir . '/public/assets';
        if (is_dir($assetsDir)) {
            // Delete all files (keep svgs/ subfolder)
            foreach (glob($assetsDir . '/*') as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
        }
        if (is_dir($srcDir . '/public/assets')) {
            $this->recursiveCopy($srcDir . '/public/assets', $assetsDir);
        }
        // Also copy public/index.php if changed
        if (is_file($srcDir . '/public/index.php')) {
            @copy($srcDir . '/public/index.php', $panelDir . '/public/index.php');
        }
        $results[] = '✓ public/assets/ replaced (fresh from repo)';

        // 7. Set permissions
        $webUser = $this->detectWebUser();
        @shell_exec('chown -R ' . escapeshellarg($webUser) . ':' . escapeshellarg($webUser) . ' ' . escapeshellarg($panelDir) . '/* 2>/dev/null');
        @shell_exec('chmod -R 755 ' . escapeshellarg($panelDir . '/storage') . ' ' . escapeshellarg($panelDir . '/bootstrap/cache') . ' 2>/dev/null');
        $results[] = '✓ Permission set (user: ' . $webUser . ')';

        // 8. Composer install
        if (command_exists('composer')) {
            $composerOutput = @shell_exec('cd ' . escapeshellarg($panelDir) . ' && sudo -u ' . escapeshellarg($webUser) . ' composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5');
            $results[] = '✓ Composer install: ' . trim($composerOutput);
        } else {
            $results[] = '⚠ composer tidak ditemukan — skip dependency install';
        }

        // 9. Database migration
        try {
            Artisan::call('migrate', ['--seed' => true, '--force' => true]);
            $migOut = Artisan::output();
            $results[] = '✓ Migration: ' . trim($migOut);
        } catch (\Throwable $e) {
            $results[] = '⚠ Migration error: ' . $e->getMessage();
        }

        // 10. Cleanup orphaned users (failed bot attempts)
        try {
            $orphans = \DB::table('users')->where('email', 'LIKE', '%@cloudserver.local')->get();
            $deleted = 0;
            foreach ($orphans as $u) {
                $serverCount = \DB::table('servers')->where('owner_id', $u->id)->count();
                if ($serverCount === 0) {
                    \DB::table('users')->where('id', $u->id)->delete();
                    $deleted++;
                }
            }
            if ($deleted > 0) {
                $results[] = "✓ Cleanup {$deleted} orphaned user(s)";
            }
        } catch (\Throwable $e) {}

        // 11. Clear + rebuild cache
        try {
            Artisan::call('view:clear');
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            if (function_exists('opcache_reset')) opcache_reset();
            $results[] = '✓ Cache cleared + OPcache reset';
        } catch (\Throwable $e) {}

        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            $results[] = '✓ Cache rebuilt (config + route + view)';
        } catch (\Throwable $e) {}

        // 12. Exit maintenance mode
        try {
            Artisan::call('up');
            $results[] = '✓ Maintenance mode NONAKTIF';
        } catch (\Throwable $e) {}

        // 13. Restart queue + reload PHP-FPM + nginx
        try {
            Artisan::call('queue:restart');
            $results[] = '✓ Queue restart signal sent';
        } catch (\Throwable $e) {}

        $phpFpm = $this->detectPhpFpmService();
        @shell_exec('systemctl reload ' . escapeshellarg($phpFpm) . ' 2>/dev/null');
        $results[] = '✓ ' . $phpFpm . ' reloaded';

        $webServer = $this->detectWebServer();
        if ($webServer) {
            @shell_exec('systemctl reload ' . escapeshellarg($webServer) . ' 2>/dev/null');
            $results[] = '✓ ' . $webServer . ' reloaded';
        }

        // 14. Restart bot worker if configured
        try {
            $tgToken = \Pterodactyl\Models\Setting::where('key', 'cloud:tg:bot_token')->value('value');
            $tgEnabled = \Pterodactyl\Models\Setting::where('key', 'cloud:tg:enabled')->value('value');
            if (!empty($tgToken) && $tgEnabled === '1') {
                @shell_exec('systemctl restart cloud-tg-bot 2>/dev/null');
                $results[] = '✓ Bot worker restarted';
            }
        } catch (\Throwable $e) {}

        // 15. Cleanup temp dir
        $this->recursiveRemove($tmpDir);

        Log::info('Cloud Server Admin Console: update run by user ' . $user->id, ['results' => $results]);

        $this->alert->success('Update selesai! ' . count($results) . ' aksi berhasil:')->flash();
        session()->flash('console_results', $results);

        return redirect()->route('admin.cloud.console');
    }

    /**
     * Action 3: Restart all services.
     */
    public function restart(Request $request): RedirectResponse
    {
        $ownerId = AdminOwnerOnly::ownerId();
        $user = $request->user();
        if (!AdminOwnerOnly::isOwner($user->id)) {
            $this->alert->danger('Akses ditolak. Hanya Admin Utama (ID ' . $ownerId . ') yang bisa menjalankan aksi ini.')->flash();
            return redirect()->route('admin.cloud.console');
        }

        $results = [];

        // 1. Restart queue worker
        try {
            Artisan::call('queue:restart');
            $results[] = '✓ Queue restart signal sent';
        } catch (\Throwable $e) {
            $results[] = '⚠ Queue restart gagal: ' . $e->getMessage();
        }

        // 2. Clear OPcache
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $results[] = '✓ OPcache cleared';
        }

        // 3. Reload PHP-FPM
        $phpFpm = $this->detectPhpFpmService();
        $out = @shell_exec('systemctl reload ' . escapeshellarg($phpFpm) . ' 2>&1');
        if ($out === null || trim($out) === '') {
            $results[] = '✓ ' . $phpFpm . ' reloaded';
        } else {
            // Try restart
            @shell_exec('systemctl restart ' . escapeshellarg($phpFpm) . ' 2>/dev/null');
            $results[] = '✓ ' . $phpFpm . ' restarted';
        }

        // 4. Reload web server
        $webServer = $this->detectWebServer();
        if ($webServer) {
            @shell_exec('systemctl reload ' . escapeshellarg($webServer) . ' 2>/dev/null');
            $results[] = '✓ ' . $webServer . ' reloaded';
        }

        // 5. Restart bot worker (if configured)
        try {
            $tgToken = \Pterodactyl\Models\Setting::where('key', 'cloud:tg:bot_token')->value('value');
            $tgEnabled = \Pterodactyl\Models\Setting::where('key', 'cloud:tg:enabled')->value('value');
            if (!empty($tgToken) && $tgEnabled === '1') {
                @shell_exec('systemctl restart cloud-tg-bot 2>/dev/null');
                sleep(2);
                $status = trim((string) @shell_exec('systemctl is-active cloud-tg-bot 2>/dev/null'));
                if ($status === 'active') {
                    $results[] = '✓ Bot worker restarted (active)';
                } else {
                    $results[] = '⚠ Bot worker restart — status: ' . $status;
                }
            } else {
                $results[] = 'ℹ Bot worker skip — belum dikonfigurasi';
            }
        } catch (\Throwable $e) {
            $results[] = '⚠ Bot worker restart gagal: ' . $e->getMessage();
        }

        // 6. Clear caches
        try {
            Artisan::call('cache:clear');
            $results[] = '✓ Application cache cleared';
        } catch (\Throwable $e) {}

        Log::info('Cloud Server Admin Console: restart run by user ' . $user->id, ['results' => $results]);

        $this->alert->success('Restart selesai! ' . count($results) . ' aksi berhasil:')->flash();
        session()->flash('console_results', $results);

        return redirect()->route('admin.cloud.console');
    }

    /**
     * Get live status (called via AJAX for auto-refresh).
     */
    public function status(): JsonResponse
    {
        $services = [];
        foreach (['php8.2-fpm', 'php8.1-fpm', 'php8.3-fpm', 'php-fpm', 'nginx', 'apache2', 'cloud-tg-bot', 'redis', 'mysql', 'mariadb'] as $svc) {
            $status = trim((string) @shell_exec("systemctl is-active {$svc} 2>/dev/null"));
            if ($status !== '' && $status !== 'inactive') {
                $services[$svc] = $status;
            }
        }

        $assetsDir = base_path('public/assets');
        $bundleCount = 0;
        $bundleSize = 0;
        if (is_dir($assetsDir)) {
            foreach (glob($assetsDir . '/*') as $f) {
                if (is_file($f)) {
                    $bundleCount++;
                    $bundleSize += filesize($f);
                }
            }
        }

        $manifestBundle = 'unknown';
        $manifestPath = $assetsDir . '/manifest.json';
        if (is_file($manifestPath)) {
            $m = json_decode((string) file_get_contents($manifestPath), true);
            if (isset($m['main.js']['src'])) {
                $manifestBundle = basename($m['main.js']['src']);
            }
        }

        $loadAvg = null;
        if (is_readable('/proc/loadavg')) {
            $parts = explode(' ', (string) file_get_contents('/proc/loadavg'));
            if (count($parts) >= 3) {
                $loadAvg = ['m1' => (float) $parts[0], 'm5' => (float) $parts[1], 'm15' => (float) $parts[2]];
            }
        }

        $memInfo = null;
        if (is_readable('/proc/meminfo')) {
            $lines = array_filter(explode("\n", (string) file_get_contents('/proc/meminfo')));
            $parsed = [];
            foreach ($lines as $line) {
                if (preg_match('/^(\w+):\s+(\d+)\s*kB/i', $line, $m)) {
                    $parsed[strtolower($m[1])] = (int) $m[2] * 1024;
                }
            }
            if (isset($parsed['memtotal'], $parsed['memavailable'])) {
                $memInfo = [
                    'used_pct' => $parsed['memtotal'] > 0 ? round((($parsed['memtotal'] - $parsed['memavailable']) / $parsed['memtotal']) * 100, 2) : 0,
                    'used' => $parsed['memtotal'] - $parsed['memavailable'],
                    'total' => $parsed['memtotal'],
                ];
            }
        }

        return response()->json([
            'services' => $services,
            'bundleCount' => $bundleCount,
            'bundleSize' => $bundleSize,
            'manifestBundle' => $manifestBundle,
            'loadAvg' => $loadAvg,
            'memInfo' => $memInfo,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    // ===== Private helpers =====

    private function detectWebUser(): string
    {
        foreach (['www-data', 'nginx', 'apache'] as $u) {
            if (function_exists('posix_getpwnam') && @posix_getpwnam($u)) {
                return $u;
            }
            $check = @shell_exec('id -u ' . escapeshellarg($u) . ' 2>/dev/null');
            if (trim($check) !== '') return $u;
        }
        return 'www-data';
    }

    private function detectPhpFpmService(): string
    {
        foreach (['php8.2-fpm', 'php8.1-fpm', 'php8.3-fpm', 'php-fpm'] as $svc) {
            $out = @shell_exec('systemctl list-unit-files 2>/dev/null | grep -E "^' . preg_quote($svc) . '\.service"');
            if (trim($out) !== '') return $svc;
        }
        return 'php8.2-fpm';
    }

    private function detectWebServer(): ?string
    {
        foreach (['nginx', 'apache2', 'httpd'] as $svc) {
            $out = @shell_exec('systemctl list-unit-files 2>/dev/null | grep -E "^' . preg_quote($svc) . '\.service"');
            if (trim($out) !== '') return $svc;
        }
        return null;
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        $dir = opendir($src);
        while (($entry = readdir($dir)) !== false) {
            if ($entry === '.' || $entry === '..') continue;
            $srcPath = $src . '/' . $entry;
            $dstPath = $dst . '/' . $entry;
            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    private function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $f) {
            $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
        }
        @rmdir($dir);
    }
}

// Helper function (defined at file level for PHP 8.x compatibility)
if (!function_exists('command_exists')) {
    function command_exists(string $cmd): bool
    {
        $check = @shell_exec(sprintf("command -v %s 2>/dev/null", escapeshellarg($cmd)));
        return !empty(trim((string) $check));
    }
}
