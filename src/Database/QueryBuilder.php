<?php

declare(strict_types=1);

namespace Fw\Database;

/**
 * Fluent query builder for database operations.
 *
 * This builder is mutable for performance - chain methods modify and return
 * the same instance. Use clone() explicitly if you need to branch queries.
 */
final class QueryBuilder
{
    /**
     * Allowed SQL comparison operators.
     * Prevents SQL injection via operator parameter.
     */
    private const array ALLOWED_OPERATORS = [
        '=', '!=', '<>', '<', '>', '<=', '>=',
        'LIKE', 'NOT LIKE', 'ILIKE', 'NOT ILIKE',
        'REGEXP', 'NOT REGEXP', 'RLIKE',
        '&', '|', '^', '<<', '>>',
    ];

    private Connection $connection;
    private string $table = '';
    private array $columns = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private array $joins = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private bool $distinct = false;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create an explicit clone for branching queries.
     *
     * Use when you need to create multiple queries from a base:
     *   $base = $db->table('users')->where('active', true);
     *   $admins = $base->clone()->where('role', 'admin')->get();
     *   $members = $base->clone()->where('role', 'member')->get();
     */
    public function clone(): self
    {
        return clone $this;
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(string|array $columns = ['*']): self
    {
        $cols = is_array($columns) ? $columns : func_get_args();

        // Validate each column to prevent SQL injection
        foreach ($cols as $column) {
            $this->validateIdentifier($column, 'column');
        }

        $this->columns = $cols;
        return $this;
    }

    /**
     * Maximum allowed length for identifiers (prevents ReDoS attacks).
     * MySQL limit is 64, PostgreSQL is 63, but we allow more for expressions.
     */
    private const int MAX_IDENTIFIER_LENGTH = 255;

    /**
     * Validate that an identifier (column, table, etc.) is safe.
     *
     * Allows: alphanumeric, underscore, dot (for table.column), and * for wildcard
     * Also allows common aggregate expressions like COUNT(*), SUM(column), etc.
     *
     * @throws \InvalidArgumentException If identifier contains unsafe characters
     */
    private function validateIdentifier(string $identifier, string $type = 'identifier'): void
    {
        // Length check BEFORE any regex to prevent ReDoS attacks
        if (strlen($identifier) > self::MAX_IDENTIFIER_LENGTH) {
            throw new \InvalidArgumentException(
                "Invalid {$type}: exceeds maximum length of " . self::MAX_IDENTIFIER_LENGTH . " characters."
            );
        }

        // Check for null bytes early (security)
        if (str_contains($identifier, "\0")) {
            throw new \InvalidArgumentException(
                "Invalid {$type}: contains null bytes."
            );
        }

        // Allow * wildcard
        if ($identifier === '*') {
            return;
        }

        // Allow aggregate functions: COUNT(*), SUM(col), AVG(col), COALESCE(a, b, c), etc.
        if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX|COALESCE|IFNULL|NULLIF)\s*\((.+)\)$/i', $identifier, $matches)) {
            $funcName = strtoupper($matches[1]);
            $inner = trim($matches[2]);

            // Handle functions that can take multiple arguments
            if (in_array($funcName, ['COALESCE', 'IFNULL', 'NULLIF'], true)) {
                // Split by comma, but be careful about nested functions
                $args = $this->splitFunctionArgs($inner);
                foreach ($args as $arg) {
                    $arg = trim($arg);
                    // Allow literals (numbers, quoted strings, NULL)
                    if ($this->isLiteral($arg)) {
                        continue;
                    }
                    $this->validateIdentifier($arg, $type);
                }
            } else {
                // Single-argument aggregate functions
                if ($inner !== '*') {
                    $this->validateIdentifier($inner, $type);
                }
            }
            return;
        }

        // Allow aliased columns: column AS alias, column alias
        if (preg_match('/^(.+?)\s+(?:AS\s+)?([a-zA-Z_][a-zA-Z0-9_]*)$/i', $identifier, $matches)) {
            $this->validateIdentifier(trim($matches[1]), $type);
            return;
        }

        // Basic identifier: alphanumeric, underscore, dot (for table.column)
        // Must start with letter or underscore
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $identifier)) {
            throw new \InvalidArgumentException(
                "Invalid {$type} name: '{$identifier}'. " .
                "Must contain only alphanumeric characters, underscores, and dots."
            );
        }
    }

    /**
     * Split function arguments by comma, respecting nested parentheses.
     *
     * @return array<string>
     */
    private function splitFunctionArgs(string $args): array
    {
        $result = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($args); $i++) {
            $char = $args[$i];

            if ($char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $result[] = $current;
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $result[] = $current;
        }

        return $result;
    }

    /**
     * Check if a value is a SQL literal (number, string, NULL).
     */
    private function isLiteral(string $value): bool
    {
        // NULL
        if (strtoupper($value) === 'NULL') {
            return true;
        }

        // Numbers (integer or decimal)
        if (preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return true;
        }

        // Single-quoted strings (simple check - doesn't handle escapes perfectly)
        if (preg_match("/^'[^']*'$/", $value)) {
            return true;
        }

        return false;
    }

    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->validateOperator($operator);

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'boolean' => 'AND',
        ];
        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->validateOperator($operator);

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'boolean' => 'OR',
        ];
        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'count' => count($values),
            'boolean' => 'AND',
        ];
        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'notNull',
            'column' => $column,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'boolean' => 'AND',
        ];
        $this->bindings[] = $min;
        $this->bindings[] = $max;

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->validateOperator($operator);

        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->validateOperator($operator);

        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->validateOperator($operator);

        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * Validate that an operator is in the allowed list.
     *
     * @throws \InvalidArgumentException If operator is not allowed
     */
    private function validateOperator(string $operator): void
    {
        $normalized = strtoupper(trim($operator));

        if (!in_array($normalized, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException(
                "Invalid SQL operator: {$operator}. Allowed operators: " . implode(', ', self::ALLOWED_OPERATORS)
            );
        }
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->validateIdentifier($column, 'column');

        $dir = strtoupper($direction);
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException(
                "Invalid ORDER BY direction: '{$direction}'. Must be ASC or DESC."
            );
        }

        $this->orderBy[] = [$column, $dir];
        return $this;
    }

    public function groupBy(string|array $columns): self
    {
        $cols = is_array($columns) ? $columns : func_get_args();

        foreach ($cols as $column) {
            $this->validateIdentifier($column, 'column');
        }

        $this->groupBy = $cols;
        return $this;
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('LIMIT must be non-negative');
        }
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('OFFSET must be non-negative');
        }
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        [$sql, $bindings] = $this->toSql();
        return $this->connection->select($sql, $bindings);
    }

    public function first(): ?array
    {
        // Clone to avoid mutating the original query
        $result = $this->clone()->limit(1)->get();
        return $result[0] ?? null;
    }

    public function find(int|string $id, string $column = 'id'): ?array
    {
        return $this->clone()->where($column, $id)->first();
    }

    public function count(): int
    {
        $query = $this->clone();
        $query->columns = ['COUNT(*) as aggregate'];

        $result = $query->first();
        return (int) ($result['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column): float
    {
        $query = $this->clone();
        $query->columns = ['SUM(' . $this->quoteIdentifier($column) . ') as aggregate'];

        $result = $query->first();
        return (float) ($result['aggregate'] ?? 0);
    }

    public function avg(string $column): float
    {
        $query = $this->clone();
        $query->columns = ['AVG(' . $this->quoteIdentifier($column) . ') as aggregate'];

        $result = $query->first();
        return (float) ($result['aggregate'] ?? 0);
    }

    public function max(string $column): mixed
    {
        $query = $this->clone();
        $query->columns = ['MAX(' . $this->quoteIdentifier($column) . ') as aggregate'];

        $result = $query->first();
        return $result['aggregate'] ?? null;
    }

    public function min(string $column): mixed
    {
        $query = $this->clone();
        $query->columns = ['MIN(' . $this->quoteIdentifier($column) . ') as aggregate'];

        $result = $query->first();
        return $result['aggregate'] ?? null;
    }

    public function insert(array $data): int
    {
        return $this->connection->insert($this->table, $data);
    }

    public function update(array $data): int
    {
        $set = [];
        $params = [];

        foreach ($data as $column => $value) {
            $set[] = $this->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->quoteIdentifier($this->table),
            implode(', ', $set),
            $this->compileWheres()
        );

        return $this->connection->query($sql, array_merge($params, $this->bindings))->rowCount();
    }

    public function delete(): int
    {
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->quoteIdentifier($this->table),
            $this->compileWheres()
        );

        return $this->connection->query($sql, $this->bindings)->rowCount();
    }

    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $total = $this->count();
        $items = $this->clone()->limit($perPage)->offset($offset)->get();

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Compile query to SQL with parameterized bindings.
     *
     * @return array{0: string, 1: array} [sql, bindings]
     */
    public function toSql(): array
    {
        $bindings = $this->bindings;

        $sql = $this->distinct ? 'SELECT DISTINCT ' : 'SELECT ';
        $sql .= implode(', ', $this->columns);
        $sql .= ' FROM ' . $this->quoteIdentifier($this->table);

        foreach ($this->joins as $join) {
            $sql .= sprintf(
                ' %s JOIN %s ON %s %s %s',
                $join['type'],
                $this->quoteIdentifier($join['table']),
                $this->quoteIdentifier($join['first']),
                $join['operator'],
                $this->quoteIdentifier($join['second'])
            );
        }

        $sql .= $this->compileWheres();

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', array_map(fn($c) => $this->quoteIdentifier($c), $this->groupBy));
        }

        if (!empty($this->orderBy)) {
            $orders = array_map(fn($o) => $this->quoteIdentifier($o[0]) . ' ' . $o[1], $this->orderBy);
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        // LIMIT/OFFSET are parameterized to prevent SQL injection
        // Even though they're typed as int, parameterization is defense-in-depth
        if ($this->limit !== null) {
            $sql .= ' LIMIT ?';
            $bindings[] = $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ?';
            $bindings[] = $this->offset;
        }

        return [$sql, $bindings];
    }

    private function compileWheres(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $parts = [];

        foreach ($this->wheres as $i => $where) {
            $clause = match ($where['type']) {
                'basic' => $this->quoteIdentifier($where['column']) . ' ' . $where['operator'] . ' ?',
                'in' => $this->quoteIdentifier($where['column']) . ' IN (' . implode(', ', array_fill(0, $where['count'], '?')) . ')',
                'null' => $this->quoteIdentifier($where['column']) . ' IS NULL',
                'notNull' => $this->quoteIdentifier($where['column']) . ' IS NOT NULL',
                'between' => $this->quoteIdentifier($where['column']) . ' BETWEEN ? AND ?',
                default => '',
            };

            if ($i === 0) {
                $parts[] = $clause;
            } else {
                $parts[] = $where['boolean'] . ' ' . $clause;
            }
        }

        return ' WHERE ' . implode(' ', $parts);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (str_contains($identifier, '.')) {
            return implode('.', array_map(
                fn($p) => $this->connection->quoteIdentifier($p),
                explode('.', $identifier)
            ));
        }
        return $this->connection->quoteIdentifier($identifier);
    }
}
