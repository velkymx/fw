<?php

declare(strict_types=1);

namespace Fw\Console\Commands;

use Fw\Console\Command;

/**
 * Clear all optimization caches.
 *
 * Use this during development when you need fresh config/routes.
 */
class OptimizeClearCommand extends Command
{
    protected string $name = 'optimize:clear';

    protected string $description = 'Clear all optimization caches (routes, config, views)';

    public function handle(): int
    {
        $this->output->info('Clearing optimization caches...');
        $this->output->newLine();

        $cachePath = BASE_PATH . '/storage/cache';

        // 1. Clear config cache
        $configCache = $cachePath . '/config.php';
        if (file_exists($configCache)) {
            unlink($configCache);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($configCache, true);
            }
            $this->output->success('  Configuration cache cleared');
        } else {
            $this->output->line('  No configuration cache');
        }

        // 2. Clear route cache
        $routeCache = $cachePath . '/routes.php';
        if (file_exists($routeCache)) {
            unlink($routeCache);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($routeCache, true);
            }
            $this->output->success('  Route cache cleared');
        } else {
            $this->output->line('  No route cache');
        }

        // 3. Clear view cache
        $viewCachePath = $cachePath . '/views';
        if (is_dir($viewCachePath)) {
            $files = glob($viewCachePath . '/*.php');
            if ($files !== false && count($files) > 0) {
                foreach ($files as $file) {
                    unlink($file);
                    if (function_exists('opcache_invalidate')) {
                        opcache_invalidate($file, true);
                    }
                }
                $this->output->success('  View cache cleared (' . count($files) . ' files)');
            } else {
                $this->output->line('  No view cache');
            }
        }

        $this->output->newLine();
        $this->output->success('All optimization caches cleared!');

        return 0;
    }
}
