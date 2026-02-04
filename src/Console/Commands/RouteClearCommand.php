<?php

declare(strict_types=1);

namespace Fw\Console\Commands;

use Fw\Console\Command;

/**
 * Clear the route cache.
 */
class RouteClearCommand extends Command
{
    protected string $name = 'route:clear';

    protected string $description = 'Remove the route cache file';

    public function handle(): int
    {
        $cacheFile = BASE_PATH . '/storage/cache/routes.php';

        if (!file_exists($cacheFile)) {
            $this->output->line('Route cache file does not exist.');
            return 0;
        }

        if (unlink($cacheFile)) {
            // Invalidate OPcache for this file
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($cacheFile, true);
            }

            $this->output->success('Route cache cleared successfully!');
            return 0;
        }

        $this->output->error('Failed to remove route cache file');
        return 1;
    }
}
