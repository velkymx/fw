<?php

declare(strict_types=1);

namespace Database\Seeders;

use Fw\Database\Connection;

/**
 * Main database seeder.
 *
 * This is the entry point for database seeding. Call other seeders
 * from this class using the call() method.
 */
class DatabaseSeeder
{
    public function __construct(
        private Connection $db,
    ) {}

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call other seeders here
        // $this->call(UserSeeder::class);
    }

    /**
     * Run a seeder class.
     *
     * @param class-string $seederClass
     */
    protected function call(string $seederClass): void
    {
        $seeder = new $seederClass($this->db);
        $seeder->run();
    }
}
