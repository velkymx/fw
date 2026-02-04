<?php

declare(strict_types=1);

namespace Fw\Tests;

use Fw\Core\Application;
use Fw\Database\Connection;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base TestCase for all framework tests.
 *
 * Provides helper methods for setting up the application,
 * database, and making HTTP requests.
 */
abstract class TestCase extends BaseTestCase
{
    protected ?Application $app = null;
    protected ?Connection $db = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear session between tests
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'localhost',
        ];
    }

    protected function tearDown(): void
    {
        $this->app = null;
        $this->db = null;

        parent::tearDown();
    }

    /**
     * Create an application instance for testing.
     */
    protected function createApplication(): Application
    {
        $this->app = new Application(BASE_PATH);
        return $this->app;
    }

    /**
     * Create an in-memory SQLite database for testing.
     */
    protected function createDatabase(): Connection
    {
        Connection::reset();

        $this->db = Connection::getInstance([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        return $this->db;
    }

    /**
     * Run database migrations on the test database.
     */
    protected function runMigrations(): void
    {
        if ($this->db === null) {
            $this->createDatabase();
        }

        // Create users table
        $this->db->query("
            CREATE TABLE users (
                id VARCHAR(36) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) DEFAULT 'user',
                remember_token VARCHAR(255),
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        // Create posts table
        $this->db->query("
            CREATE TABLE posts (
                id VARCHAR(36) PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE NOT NULL,
                content TEXT,
                published_at DATETIME,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }

    /**
     * Simulate a GET request.
     */
    protected function get(string $uri): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = $uri;
        $_GET = [];
        $_POST = [];
    }

    /**
     * Simulate a POST request.
     */
    protected function post(string $uri, array $data = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $uri;
        $_GET = [];
        $_POST = $data;
    }

    /**
     * Set request headers.
     */
    protected function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $_SERVER[$key] = $value;
        }
        return $this;
    }

    /**
     * Assert that a session key has a specific value.
     */
    protected function assertSessionHas(string $key, mixed $value = null): void
    {
        $this->assertArrayHasKey($key, $_SESSION, "Session does not contain key: $key");

        if ($value !== null) {
            $this->assertEquals($value, $_SESSION[$key]);
        }
    }

    /**
     * Assert that a session key does not exist.
     */
    protected function assertSessionMissing(string $key): void
    {
        $this->assertArrayNotHasKey($key, $_SESSION, "Session contains key: $key");
    }
}
