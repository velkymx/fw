<?php

declare(strict_types=1);

namespace Fw\Console\Commands;

use Fw\Console\Command;
use Fw\Core\Config;

/**
 * Clear the configuration cache.
 */
class ConfigClearCommand extends Command
{
    protected string $name = 'config:clear';

    protected string $description = 'Remove the configuration cache file';

    public function handle(): int
    {
        $config = new Config(BASE_PATH);

        if (!$config->isCached()) {
            $this->output->line('Configuration cache does not exist.');
            return 0;
        }

        if ($config->clearCache()) {
            $this->output->success('Configuration cache cleared successfully!');
            return 0;
        }

        $this->output->error('Failed to remove configuration cache file');
        return 1;
    }
}
