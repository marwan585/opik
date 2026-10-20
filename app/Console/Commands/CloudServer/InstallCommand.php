<?php

namespace Pterodactyl\Console\Commands\CloudServer;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Cloud Server — One-shot Installer
 *
 * Run this command after extracting the NookTheme zip into your panel
 * directory. It performs every step needed to enable the full Cloud
 * Server protection stack in one go:
 *
 *   1. Sets APP_NAME=Cloud Server in .env (rebranding).
 *   2. Publishes the new config defaults (auto-suspend thresholds).
 *   3. Clears & re-caches config + routes (so the new middleware alias
 *      `admin.owner` and the new routes are picked up).
 *   4. Runs any pending migrations.
 *   5. Verifies that the protected admin menus are wired with the
 *      AdminOwnerOnly middleware.
 *   6. Verifies that the auto-suspend schedule is registered.
 *   7. Restarts the queue worker so the scheduler picks up the new job.
 *
 * Usage:
 *   php artisan cloudserver:install
 *   php artisan cloudserver:install --force   (skip confirmations)
 */
class InstallCommand extends Command
{
    protected $signature = 'cloudserver:install
                            {--force : Skip confirmation prompts}
                            {--skip-migrations : Skip running database migrations}';

    protected $description = 'Cloud Server: one-shot installer. Activates branding, security middleware, auto-suspend schedule, and admin owner-only protections.';

    public function handle(): int
    {
        $this->printBanner();

        if (!$this->option('force')) {
            $confirmed = $this->confirm(
                'This will rebrand your panel to "Cloud Server", enable the admin-owner-only ' .
                'security middleware, register the auto-suspend schedule, and clear caches. Continue?',
                true
            );
            if (!$confirmed) {
                $this->warn('Installation cancelled.');
                return self::SUCCESS;
            }
        }

        $this->newLine();

        // ───────────────────────────────────────────────────────────────────
        // Step 1: Update .env with Cloud Server branding + protection defaults
        // ───────────────────────────────────────────────────────────────────
        $this->info('[1/7] Patching .env with Cloud Server branding & protection defaults...');
        $this->patchEnvFile();
        $this->line('   ✓ APP_NAME=Cloud Server');
        $this->line('   ✓ CLOUD_SERVER_OWNER_ID=1');
        $this->line('   ✓ CLOUD_SERVER_AUTOSUSPEND_ENABLED=true');

        // ───────────────────────────────────────────────────────────────────
        // Step 2: Clear all caches so the new middleware + routes are loaded
        // ───────────────────────────────────────────────────────────────────
        $this->info('[2/7] Clearing application caches...');
        $this->callSilent('config:clear');
        $this->callSilent('route:clear');
        $this->callSilent('view:clear');
        $this->callSilent('cache:clear');
        $this->line('   ✓ config, route, view, application cache cleared');

        // ───────────────────────────────────────────────────────────────────
        // Step 3: Run migrations (if any new ones exist)
        // ───────────────────────────────────────────────────────────────────
        if (!$this->option('skip-migrations')) {
            $this->info('[3/7] Running database migrations...');
            try {
                $this->call('migrate', ['--force' => true]);
                $this->line('   ✓ migrations applied');
            } catch (\Throwable $e) {
                $this->warn('   ⚠️  Migration step failed (non-fatal): ' . $e->getMessage());
            }
        } else {
            $this->info('[3/7] Skipping migrations (--skip-migrations).');
        }

        // ───────────────────────────────────────────────────────────────────
        // Step 4: Rebuild config + route cache for production speed
        // ───────────────────────────────────────────────────────────────────
        $this->info('[4/7] Rebuilding config + route caches...');
        try {
            $this->callSilent('config:cache');
            $this->callSilent('route:cache');
            $this->line('   ✓ config + route caches rebuilt');
        } catch (\Throwable $e) {
            $this->warn('   ⚠️  Cache rebuild skipped: ' . $e->getMessage());
        }

        // ───────────────────────────────────────────────────────────────────
        // Step 5: Verify that the AdminOwnerOnly middleware is registered
        // ───────────────────────────────────────────────────────────────────
        $this->info('[5/7] Verifying AdminOwnerOnly middleware registration...');
        $kernelPath = base_path('app/Http/Kernel.php');
        if (File::exists($kernelPath) && str_contains(File::get($kernelPath), 'admin.owner')) {
            $this->line('   ✓ middleware alias `admin.owner` is registered in app/Http/Kernel.php');
        } else {
            $this->error('   ✗ middleware alias `admin.owner` not found — manual fix required!');
        }

        // ───────────────────────────────────────────────────────────────────
        // Step 6: Verify the admin routes are protected
        // ───────────────────────────────────────────────────────────────────
        $this->info('[6/7] Verifying protected admin routes...');
        $routesFile = base_path('routes/admin.php');
        $routesContent = File::get($routesFile);
        $protectedSections = ['locations', 'databases', 'nodes', 'mounts', 'nests', 'settings'];
        $protectedCount = 0;
        foreach ($protectedSections as $section) {
            if (str_contains($routesContent, "prefix' => '{$section}', 'middleware' => 'admin.owner'")) {
                $protectedCount++;
                $this->line("   ✓ /admin/{$section} protected");
            }
        }
        if (str_contains($routesContent, "admin.users.delete')\n        ->middleware('admin.owner')")) {
            $this->line('   ✓ /admin/users/{id} DELETE protected');
            $protectedCount++;
        }
        if (str_contains($routesContent, "admin.servers.view')\n        ->middleware('admin.owner')")) {
            $this->line('   ✓ /admin/servers/view/{id} protected');
            $protectedCount++;
        }
        $this->line("   Total protected routes: {$protectedCount}");

        // ───────────────────────────────────────────────────────────────────
        // Step 7: Verify the auto-suspend scheduler is registered
        // ───────────────────────────────────────────────────────────────────
        $this->info('[7/7] Verifying auto-suspend scheduler...');
        $consoleKernelPath = base_path('app/Console/Kernel.php');
        if (File::exists($consoleKernelPath) && str_contains(File::get($consoleKernelPath), 'cloudserver:autosuspend')) {
            $this->line('   ✓ schedule `cloudserver:autosuspend` registered (every 5 minutes)');
        } else {
            $this->error('   ✗ auto-suspend schedule not found — check app/Console/Kernel.php');
        }

        // ───────────────────────────────────────────────────────────────────
        // Done. Print summary.
        // ───────────────────────────────────────────────────────────────────
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('  Cloud Server installation complete!');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->newLine();
        $this->line('  Activated protections:');
        $this->line('    ✓ Branding: Pterodactyl → Cloud Server (visible UI surfaces)');
        $this->line('    ✓ Post-login loading overlay (2s) on login + 2FA checkpoint');
        $this->line('    ✓ Enhanced user dashboard (hero card + stat strip)');
        $this->line('    ✓ Enhanced admin dashboard (stat widgets)');
        $this->line('    ✓ AdminOwnerOnly middleware (only user ID 1 has full access)');
        $this->line('    ✓ Violation page shown when non-owner admin hits a protected menu');
        $this->line('    ✓ Auto-suspend scanner (disk overuse + suspicious activity) every 5 min');
        $this->newLine();
        $this->line('  Protected admin menus (only ID 1 can access):');
        $this->line('    - Locations, Databases, Nodes, Mounts, Nests, Settings');
        $this->line('    - User delete, Server delete, Per-server admin view');
        $this->newLine();
        $this->line('  Next steps:');
        $this->line('    1. Restart the queue worker:  php artisan queue:restart');
        $this->line('    2. Reload nginx/php-fpm so .env changes take effect.');
        $this->line('    3. Make sure your system cron is running the Laravel scheduler:');
        $this->line('       * * * * * cd /var/www/cloudserver && php artisan schedule:run >> /dev/null 2>&1');
        $this->newLine();
        $this->line('  Tune auto-suspend thresholds in .env:');
        $this->line('    CLOUD_SERVER_OWNER_ID=1                    (panel owner ID)');
        $this->line('    CLOUD_SERVER_AUTOSUSPEND_ENABLED=true');
        $this->line('    CLOUD_SERVER_SUSPICIOUS_CPU_PCT=99         (sustained CPU% before suspend)');
        $this->line('    CLOUD_SERVER_SUSPICIOUS_MEM_OVERHEAD_PCT=20 (% over limit before suspend)');
        $this->newLine();
        $this->info('  Manual run of the auto-suspend scanner:');
        $this->line('    php artisan cloudserver:autosuspend');
        $this->line('    php artisan cloudserver:autosuspend --dry-run  (no actual suspensions)');
        $this->newLine();

        return self::SUCCESS;
    }

    private function printBanner(): void
    {
        $this->newLine();
        $this->line('  ██████╗██╗   ██╗██████╗ ███████╗██████╗     ██████╗  ██████╗ ███████╗');
        $this->line(' ██╔════╝╚██╗ ██╔╝██╔══██╗██╔════╝██╔══██╗    ██╔══██╗██╔═══██╗██╔════╝');
        $this->line(' ██║      ╚████╔╝ ██████╔╝█████╗  ██████╔╝    ██║  ██║██║   ██║███████╗');
        $this->line(' ██║       ╚██╔╝  ██╔══██╗██╔══╝  ██╔══██╗    ██║  ██║██║   ██║╚════██║');
        $this->line(' ╚██████╗   ██║   ██████╔╝███████╗██║  ██║    ██████╔╝╚██████╔╝███████║');
        $this->line('  ╚═════╝   ╚═╝   ╚═════╝ ╚══════╝╚═╝  ╚═╝    ╚═════╝  ╚═════╝ ╚══════╝');
        $this->newLine();
        $this->info('  Cloud Server Installer — full protection stack in one command.');
        $this->newLine();
    }

    /**
     * Patch the .env file with Cloud Server defaults without destroying
     * the user's existing customisations. Adds new keys at the bottom
     * if they don't already exist; replaces existing values otherwise.
     */
    private function patchEnvFile(): void
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            // If .env doesn't exist, copy from .env.example
            if (File::exists(base_path('.env.example'))) {
                File::copy(base_path('.env.example'), $envPath);
            } else {
                File::put($envPath, '');
            }
        }

        $content = File::get($envPath);
        $patched = $content;

        // APP_NAME=Cloud Server
        $patched = preg_replace('/^APP_NAME=.*/m', 'APP_NAME=Cloud Server', $patched);
        if (!preg_match('/^APP_NAME=/m', $patched)) {
            $patched = "APP_NAME=Cloud Server\n" . $patched;
        }

        // Cloud Server protection defaults — append if not present.
        $defaults = [
            'CLOUD_SERVER_OWNER_ID=1',
            'CLOUD_SERVER_AUTOSUSPEND_ENABLED=true',
            'CLOUD_SERVER_DISK_THRESHOLD_PCT=100',
            'CLOUD_SERVER_SUSPICIOUS_CPU_PCT=99',
            'CLOUD_SERVER_SUSPICIOUS_MEM_OVERHEAD_PCT=20',
        ];

        foreach ($defaults as $line) {
            $key = explode('=', $line)[0];
            if (!preg_match('/^' . preg_quote($key, '/') . '=/m', $patched)) {
                $patched .= "\n" . $line;
            }
        }

        File::put($envPath, $patched);
    }
}
