<?php

declare(strict_types=1);

namespace Fw\Database;

use PDO;
use PDOStatement;
use Fw\Database\QueryWatcher;

final class Connection
{
    private static ?Connection $instance = null;

    /**
     * Configuration for creating new connections.
     * @var array<string, mixed>
     */
    private static array $config = [];

    private PDO $pdo;
    private int $transactionLevel = 0;
    private array $queryLog = [];
    private bool $logging = false;
    public private(set) string $driver;

    /**
     * Connection ID for tracking in concurrent environments.
     */
    public private(set) string $connectionId;

    public function __construct(array $config)
    {
        $this->driver = $config['driver'] ?? 'mysql';
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? ($this->driver === 'pgsql' ? 5432 : 3306);
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = match ($this->driver) {
            'mysql' => "mysql:host=$host;port=$port;dbname=$database;charset=$charset",
            'pgsql' => "pgsql:host=$host;port=$port;dbname=$database",
            'sqlite' => "sqlite:$database",
            default => throw new \InvalidArgumentException("Unsupported driver: {$this->driver}"),
        };

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        // Persistent connections - reuses connections across requests in PHP-FPM
        // Disable if you have transaction/state issues, or use external pooler instead
        if ($config['persistent'] ?? false) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        if ($this->driver === 'mysql') {
            // PHP 8.5+ uses Pdo\Mysql::ATTR_INIT_COMMAND
            $initCommandAttr = defined('Pdo\Mysql::ATTR_INIT_COMMAND')
                ? \Pdo\Mysql::ATTR_INIT_COMMAND
                : PDO::MYSQL_ATTR_INIT_COMMAND;
            $options[$initCommandAttr] = "SET NAMES $charset COLLATE {$charset}_unicode_ci";
        }

        $this->pdo = match ($this->driver) {
            'sqlite' => new PDO($dsn, options: $options),
            default => new PDO($dsn, $username, $password, $options),
        };

        if ($this->driver === 'sqlite') {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
        }

        $this->logging = $config['logging'] ?? false;
        $this->connectionId = bin2hex(random_bytes(8));
    }

    /**
     * Get the singleton connection instance.
     *
     * @deprecated Use dependency injection instead. In worker mode,
     *             call createFresh() for each request to avoid state pollution.
     *
     * @throws \LogicException If config is passed when instance already exists with different config
     */
    public static function getInstance(?array $config = null): self
    {
        // If config is provided
        if ($config !== null) {
            // If instance already exists, verify config matches
            if (self::$instance !== null && self::$config !== $config) {
                throw new \LogicException(
                    'Connection::getInstance() called with different config than existing instance. ' .
                    'Use Connection::reset() first, or use Connection::configure() before first getInstance() call, ' .
                    'or use Connection::createFresh() for a new connection.'
                );
            }
            self::$config = $config;
        }

        // Ensure config is set before creating instance
        if (self::$instance === null && self::$config === []) {
            throw new \LogicException(
                'Connection::getInstance() called without configuration. ' .
                'Call Connection::configure() first or pass config to getInstance().'
            );
        }

        return self::$instance ??= new self(self::$config);
    }

    /**
     * Create a fresh connection (for worker mode / concurrent requests).
     *
     * Use this instead of getInstance() when you need isolated
     * transaction state per request.
     */
    public static function createFresh(?array $config = null): self
    {
        return new self($config ?? self::$config);
    }

    /**
     * Configure the default connection settings.
     *
     * Call this during bootstrap to set config used by getInstance().
     */
    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Reset the singleton instance.
     *
     * Call between requests in worker mode to prevent state pollution.
     */
    public static function reset(): void
    {
        if (self::$instance !== null) {
            // Rollback any pending transactions
            while (self::$instance->transactionLevel > 0) {
                self::$instance->rollBack();
            }
            // Clear query log
            self::$instance->queryLog = [];
        }
        self::$instance = null;
    }

    /**
     * Reset per-request state without closing connection.
     *
     * Use this in worker mode to reuse the PDO connection
     * but clear request-specific state.
     */
    public function resetRequestState(): void
    {
        // Rollback any uncommitted transactions
        while ($this->transactionLevel > 0) {
            $this->rollBack();
        }

        // Clear query log
        $this->queryLog = [];
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $start = microtime(true);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $elapsed = microtime(true) - $start;
        $elapsedMs = $elapsed * 1000;

        // Record for query watcher (N+1 and slow query detection)
        QueryWatcher::recordQuery($sql, $params, $elapsedMs);

        if ($this->logging) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'time' => $elapsed,
            ];
        }

        return $stmt;
    }

    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', array_map(fn($c) => $this->quoteIdentifier($c), $columns)),
            implode(', ', $placeholders)
        );

        $this->query($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        $set = [];
        $params = [];

        foreach ($data as $column => $value) {
            $set[] = $this->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $whereParts = [];
        foreach ($where as $column => $value) {
            $whereParts[] = $this->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(', ', $set),
            implode(' AND ', $whereParts)
        );

        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $whereParts = [];
        $params = [];

        foreach ($where as $column => $value) {
            $whereParts[] = $this->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $this->quoteIdentifier($table),
            implode(' AND ', $whereParts)
        );

        return $this->query($sql, $params)->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        } else {
            // Use parameterized savepoint name to prevent any injection possibility
            $savepointName = $this->getSavepointName($this->transactionLevel);
            $this->pdo->exec("SAVEPOINT {$savepointName}");
        }

        $this->transactionLevel++;
    }

    public function commit(): void
    {
        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            $this->pdo->commit();
        } else {
            $savepointName = $this->getSavepointName($this->transactionLevel);
            $this->pdo->exec("RELEASE SAVEPOINT {$savepointName}");
        }
    }

    public function rollBack(): void
    {
        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            $this->pdo->rollBack();
        } else {
            $savepointName = $this->getSavepointName($this->transactionLevel);
            $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepointName}");
        }
    }

    /**
     * Generate a safe savepoint name.
     *
     * Savepoint names must be valid identifiers. We use a restricted format
     * with only alphanumeric characters to prevent any SQL injection.
     */
    private function getSavepointName(int $level): string
    {
        // connectionId is already safe (hex from random_bytes), but we validate anyway
        $safeConnectionId = preg_replace('/[^a-f0-9]/i', '', $this->connectionId);
        $safeLevel = max(0, $level);

        return "sp_{$safeConnectionId}_{$safeLevel}";
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function quoteIdentifier(string $identifier): string
    {
        return match ($this->driver) {
            'mysql' => '`' . str_replace('`', '``', $identifier) . '`',
            'sqlite', 'pgsql' => '"' . str_replace('"', '""', $identifier) . '"',
            default => $identifier,
        };
    }

    public function table(string $name): QueryBuilder
    {
        return (new QueryBuilder($this))->table($name);
    }

    public function enableLogging(): void
    {
        $this->logging = true;
    }

    public function disableLogging(): void
    {
        $this->logging = false;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }
}
