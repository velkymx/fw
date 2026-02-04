<?php

declare(strict_types=1);

namespace Fw\Console\Commands;

use Fw\Console\Command;
use Fw\Core\Config;

/**
 * Cache configuration for faster bootstrapping.
 *
 * This merges all configuration files into a single cached PHP file
 * that can be loaded via OPcache, bypassing individual file reads.
 */
class ConfigCacheCommand extends Command
{
    protected string $name = 'config:cache';

    protected string $description = 'Create a configuration cache file for faster bootstrapping';

    public function handle(): int
    {
        $cacheFile = BASE_PATH . '/storage/cache/config.php';

        // Clear existing cache first
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($cacheFile, true);
            }
        }

        // Create a fresh config and load all files
        $config = new Config(BASE_PATH);
        $config->load();

        // Save to cache
        if ($config->saveCache()) {
            $this->output->success('Configuration cached successfully!');
            $this->output->line("  Cache file: {$cacheFile}");
            return 0;
        }

        $this->output->error('Failed to write configuration cache file');
        return 1;
    }
}
