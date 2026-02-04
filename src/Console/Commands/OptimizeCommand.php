<?php

declare(strict_types=1);

namespace Fw\Console\Commands;

use Fw\Console\Command;
use Fw\Core\Config;
use Fw\Core\Router;

/**
 * Optimize the framework for production.
 *
 * This command caches routes, configuration, and clears stale caches
 * for maximum performance in production environments.
 *
 * Run this during deployment:
 *   php fw optimize
 */
class OptimizeCommand extends Command
{
    protected string $name = 'optimize';

    protected string $description = 'Optimize the framework for production (cache routes, config)';

    public function handle(): int
    {
        $this->output->info('Optimizing framework for production...');
        $this->output->newLine();

        $hasErrors = false;

        // 1. Cache configuration
        $this->output->line('Caching configuration...');
        $configCacheFile = BASE_PATH . '/storage/cache/config.php';
        if (file_exists($configCacheFile)) {
            unlink($configCacheFile);
        }

        $config = new Config(BASE_PATH);
        $config->load();

        if ($config->saveCache()) {
            $this->output->success('  Configuration cached');
        } else {
            $this->output->error('  Failed to cache configuration');
            $hasErrors = true;
        }

        // 2. Cache routes
        $this->output->line('Caching routes...');
        $routeCacheFile = BASE_PATH . '/storage/cache/routes.php';
        if (file_exists($routeCacheFile)) {
            unlink($routeCacheFile);
        }

        $router = new Router();
        $router->setCacheFile($routeCacheFile);

        $routesFile = BASE_PATH . '/config/routes.php';
        if (file_exists($routesFile)) {
            $routes = require $routesFile;
            if (is_callable($routes)) {
                $routes($router);
            }

            if ($router->saveCache()) {
                $routeCount = count($router->getRoutes());
                $this->output->success("  Routes cached ({$routeCount} routes)");
            } else {
                $this->output->error('  Failed to cache routes');
                $hasErrors = true;
            }
        } else {
            $this->output->warning('  No routes file found');
        }

        // 3. Clear view cache (stale views)
        $this->output->line('Clearing stale view cache...');
        $viewCachePath = BASE_PATH . '/storage/cache/views';
        if (is_dir($viewCachePath)) {
            $files = glob($viewCachePath . '/*.php');
            if ($files !== false) {
                $count = 0;
                foreach ($files as $file) {
                    if (unlink($file)) {
                        $count++;
                        if (function_exists('opcache_invalidate')) {
                            opcache_invalidate($file, true);
                        }
                    }
                }
                $this->output->success("  Cleared {$count} cached views");
            }
        } else {
            $this->output->line('  No view cache to clear');
        }

        // 4. Clear general cache
        $this->output->line('Clearing general cache...');
        $generalCachePath = BASE_PATH . '/storage/cache';
        $cleared = 0;
        foreach (['*.cache', '*.tmp'] as $pattern) {
            $files = glob($generalCachePath . '/' . $pattern);
            if ($files !== false) {
                foreach ($files as $file) {
                    if (unlink($file)) {
                        $cleared++;
                    }
                }
            }
        }
        $this->output->success("  Cleared {$cleared} cache files");

        $this->output->newLine();

        if ($hasErrors) {
            $this->output->warning('Optimization completed with errors');
            return 1;
        }

        $this->output->success('Optimization complete!');
        $this->output->newLine();
        $this->output->line('For maximum performance, ensure:');
        $this->output->line('  - OPcache is enabled (opcache.enable=1)');
        $this->output->line('  - OPcache file validation is disabled in production');
        $this->output->line('    (opcache.validate_timestamps=0)');

        return 0;
    }
}
