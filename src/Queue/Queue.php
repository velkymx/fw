<?php

declare(strict_types=1);

namespace Fw\Queue;

use Fw\Database\Connection;

final class Queue
{
    private static ?Queue $instance = null;

    /**
     * Configuration for queue (set during bootstrap).
     * @var array{driver?: string, table?: string, path?: string}
     */
    private static array $config = [];

    /**
     * Database connection (set during bootstrap, optional).
     */
    private static ?Connection $connection = null;

    private DriverInterface $driver;
    private string $defaultQueue = 'default';

    private function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Configure the queue system during application bootstrap.
     *
     * @param array{driver?: string, table?: string, path?: string} $config
     */
    public static function configure(array $config, ?Connection $connection = null): void
    {
        self::$config = $config;
        self::$connection = $connection;
        self::$instance = null; // Reset instance when reconfigured
    }

    public static function getInstance(?DriverInterface $driver = null): self
    {
        if (self::$instance === null) {
            if ($driver === null) {
                $driver = self::createDefaultDriver();
            }
            self::$instance = new self($driver);
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private static function createDefaultDriver(): DriverInterface
    {
        $config = self::$config;
        $driverType = $config['driver'] ?? 'file';

        return match ($driverType) {
            'sync' => new SyncDriver(),
            'database' => new DatabaseDriver(
                self::$connection ?? throw new \RuntimeException(
                    'Database connection required for database queue driver. Call Queue::configure() with a connection.'
                ),
                $config['table'] ?? 'jobs'
            ),
            'file' => new FileDriver($config['path'] ?? BASE_PATH . '/storage/queue'),
            default => throw new \InvalidArgumentException("Unknown queue driver: $driverType"),
        };
    }

    public function setDefaultQueue(string $queue): self
    {
        $this->defaultQueue = $queue;
        return $this;
    }

    public function dispatch(JobInterface $job): string
    {
        return $this->driver->push($job);
    }

    public function dispatchAfter(int $delay, JobInterface $job): string
    {
        return $this->driver->later($delay, $job);
    }

    public function pop(?string $queue = null): ?array
    {
        return $this->driver->pop($queue ?? $this->defaultQueue);
    }

    public function delete(string $jobId): bool
    {
        return $this->driver->delete($jobId);
    }

    public function release(string $jobId, int $delay = 0): bool
    {
        return $this->driver->release($jobId, $delay);
    }

    public function size(?string $queue = null): int
    {
        return $this->driver->size($queue ?? $this->defaultQueue);
    }

    public function clear(?string $queue = null): int
    {
        return $this->driver->clear($queue ?? $this->defaultQueue);
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }
}
