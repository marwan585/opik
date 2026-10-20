<?php

namespace Pterodactyl\Console\Commands\CloudServer;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;

/**
 * Cloud Server — Bundle Cleanup Command
 *
 * Removes ALL old bundle files from public/assets/ except the ones
 * currently referenced by manifest.json. Also clears Laravel's
 * view/config caches so the new bundle is loaded by the browser.
 *
 * Run this after extracting a new panel.tar.gz to ensure no stale
 * bundle files remain on disk that the browser might still have
 * cached.
 *
 * Usage:
 *   php artisan cloudserver:cleanup-bundles
 *   php artisan cloudserver:cleanup-bundles --force   (skip confirmation)
 */
class CleanupBundlesCommand extends Command
{
    protected $signature = 'cloudserver:cleanup-bundles
                            {--force : Skip confirmation prompt}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Cloud Server: remove old JS bundle files from public/assets/ that are no longer referenced by manifest.json. Also clears Laravel caches.';

    public function handle(): int
    {
        $assetsDir = public_path('assets');
        $manifestPath = $assetsDir . '/manifest.json';

        if (!File::exists($manifestPath)) {
            $this->error("Manifest file not found at: $manifestPath");
            $this->warn('Run `yarn build:production` first to generate the manifest.');
            return self::FAILURE;
        }

        $manifest = json_decode(File::get($manifestPath), true);
        if (!is_array($manifest)) {
            $this->error('Failed to parse manifest.json — invalid JSON.');
            return self::FAILURE;
        }

        // Collect all filenames that ARE referenced by the manifest.
        $referencedFiles = ['manifest.json']; // keep manifest itself
        foreach ($manifest as $key => $entry) {
            if (isset($entry['src'])) {
                // entry.src is like '/assets/bundle.ca459b3d.js' — extract just the filename
                $filename = basename($entry['src']);
                $referencedFiles[] = $filename;
            }
            // Also keep the key itself in case it's a filename
            if (str_ends_with($key, '.js') || str_ends_with($key, '.woff2')) {
                $referencedFiles[] = $key;
            }
        }
        // Always keep svgs directory and its contents.
        $referencedFiles = array_unique($referencedFiles);

        // Scan all files in public/assets/
        $allFiles = File::files($assetsDir);
        $toDelete = [];
        foreach ($allFiles as $file) {
            $filename = $file->getFilename();
            // Skip directories (svgs/).
            if ($filename === 'svgs') continue;
            // Skip manifest.json (we already added it to referencedFiles).
            // Skip .map files (source maps — also safe to delete old ones).
            if (str_ends_with($filename, '.map')) {
                $toDelete[] = $file->getPathname();
                continue;
            }
            if (!in_array($filename, $referencedFiles, true)) {
                $toDelete[] = $file->getPathname();
            }
        }

        if (empty($toDelete)) {
            $this->info('No stale bundle files found. public/assets/ is clean.');
            return self::SUCCESS;
        }

        $this->warn(sprintf('Found %d stale bundle file(s) in public/assets/:', count($toDelete)));
        $totalSize = 0;
        foreach ($toDelete as $path) {
            $size = File::size($path);
            $totalSize += $size;
            $this->line(sprintf('  - %s (%s)', basename($path), $this->formatBytes($size)));
        }
        $this->line(sprintf('Total size to reclaim: %s', $this->formatBytes($totalSize)));
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info('Dry run — no files deleted. Re-run without --dry-run to actually delete.');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $confirmed = $this->confirm('Delete these stale bundle files now?', true);
            if (!$confirmed) {
                $this->warn('Cleanup cancelled.');
                return self::SUCCESS;
            }
        }

        $deleted = 0;
        foreach ($toDelete as $path) {
            try {
                File::delete($path);
                $deleted++;
            } catch (\Throwable $e) {
                $this->error("Failed to delete {$path}: " . $e->getMessage());
            }
        }

        $this->info("Deleted $deleted stale bundle file(s).");

        // Also clear Laravel caches so the new manifest is picked up.
        $this->newLine();
        $this->info('Clearing Laravel caches...');
        Artisan::call('view:clear');
        $this->line('  ✓ views cleared');
        Artisan::call('config:clear');
        $this->line('  ✓ config cleared');
        try {
            Artisan::call('cache:clear');
            $this->line('  ✓ application cache cleared');
        } catch (\Throwable $e) {
            $this->warn('  ⚠ cache:clear skipped: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->info('  Bundle cleanup complete!');
        $this->info('═══════════════════════════════════════════════════════════════');
        $this->line('');
        $this->line('Next steps to force browser to load the new bundle:');
        $this->line('  1. Restart queue:  php artisan queue:restart');
        $this->line('  2. Reload php-fpm + nginx:');
        $this->line('     sudo systemctl reload php8.2-fpm nginx');
        $this->line('  3. In your browser, do a HARD refresh (Ctrl+Shift+R or Cmd+Shift+R)');
        $this->line('     OR open DevTools → Network tab → check "Disable cache" → reload.');
        $this->line('  4. If still seeing the old dashboard, clear browser cache');
        $this->line('     (Ctrl+Shift+Delete → "Cached images and files").');

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
