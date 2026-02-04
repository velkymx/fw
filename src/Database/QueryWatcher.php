<?php

declare(strict_types=1);

namespace Fw\Database;

use Fw\Log\Logger;

/**
 * Query Watcher - detects N+1 queries and slow queries.
 *
 * Enable during development to catch performance issues early.
 * Automatically detects when similar queries are executed repeatedly
 * (N+1 pattern) and logs warnings.
 *
 * Usage:
 *     QueryWatcher::enable();
 *     QueryWatcher::setSlowQueryThreshold(100); // 100ms
 *
 *     // At end of request:
 *     QueryWatcher::report();
 */
final class QueryWatcher
{
    /**
     * Whether the watcher is enabled.
     */
    private static bool $enabled = false;

    /**
     * Slow query threshold in milliseconds.
     */
    private static int $slowQueryThreshold = 100;

    /**
     * N+1 detection threshold (number of similar queries).
     */
    private static int $nPlusOneThreshold = 3;

    /**
     * Recorded queries with timing.
     * @var array<int, array{sql: string, time: float, bindings: array, trace: string}>
     */
    private static array $queries = [];

    /**
     * Query pattern counts for N+1 detection.
     * @var array<string, int>
     */
    private static array $patternCounts = [];

    /**
     * Detected N+1 queries.
     * @var array<string, array{count: int, example: string, trace: string}>
     */
    private static array $nPlusOneQueries = [];

    /**
     * Slow queries.
     * @var array<int, array{sql: string, time: float, trace: string}>
     */
    private static array $slowQueries = [];

    /**
     * Enable query watching.
     */
    public static function enable(): void
    {
        self::$enabled = true;
        self::reset();
    }

    /**
     * Disable query watching.
     */
    public static function disable(): void
    {
        self::$enabled = false;
    }

    /**
     * Check if watching is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Set slow query threshold in milliseconds.
     */
    public static function setSlowQueryThreshold(int $ms): void
    {
        self::$slowQueryThreshold = $ms;
    }

    /**
     * Set N+1 detection threshold.
     */
    public static function setNPlusOneThreshold(int $count): void
    {
        self::$nPlusOneThreshold = $count;
    }

    /**
     * Reset all recorded data.
     */
    public static function reset(): void
    {
        self::$queries = [];
        self::$patternCounts = [];
        self::$nPlusOneQueries = [];
        self::$slowQueries = [];
    }

    /**
     * Record a query execution.
     *
     * @param string $sql The SQL query
     * @param array $bindings Query bindings
     * @param float $timeMs Execution time in milliseconds
     */
    public static function recordQuery(string $sql, array $bindings, float $timeMs): void
    {
        if (!self::$enabled) {
            return;
        }

        // Get simplified stack trace
        $trace = self::getSimplifiedTrace();

        // Store query
        self::$queries[] = [
            'sql' => $sql,
            'time' => $timeMs,
            'bindings' => $bindings,
            'trace' => $trace,
        ];

        // Check for slow query
        if ($timeMs >= self::$slowQueryThreshold) {
            self::$slowQueries[] = [
                'sql' => $sql,
                'time' => $timeMs,
                'trace' => $trace,
            ];
        }

        // Detect N+1 pattern
        $pattern = self::normalizeQueryPattern($sql);
        self::$patternCounts[$pattern] = (self::$patternCounts[$pattern] ?? 0) + 1;

        if (self::$patternCounts[$pattern] === self::$nPlusOneThreshold) {
            self::$nPlusOneQueries[$pattern] = [
                'count' => self::$patternCounts[$pattern],
                'example' => $sql,
                'trace' => $trace,
            ];
        } elseif (isset(self::$nPlusOneQueries[$pattern])) {
            self::$nPlusOneQueries[$pattern]['count'] = self::$patternCounts[$pattern];
        }
    }

    /**
     * Normalize a query to a pattern for N+1 detection.
     *
     * Replaces literal values with placeholders to group similar queries.
     */
    private static function normalizeQueryPattern(string $sql): string
    {
        // Replace numeric values
        $pattern = preg_replace('/\b\d+\b/', '?', $sql);

        // Replace quoted strings
        $pattern = preg_replace("/'[^']*'/", '?', $pattern);
        $pattern = preg_replace('/"[^"]*"/', '?', $pattern);

        // Replace IN lists
        $pattern = preg_replace('/IN\s*\([^)]+\)/i', 'IN (?)', $pattern);

        // Normalize whitespace
        $pattern = preg_replace('/\s+/', ' ', trim($pattern));

        return $pattern;
    }

    /**
     * Get a simplified stack trace.
     */
    private static function getSimplifiedTrace(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        $relevant = [];
        foreach ($trace as $frame) {
            // Skip internal framework files
            if (isset($frame['file'])) {
                if (str_contains($frame['file'], '/src/Database/')) {
                    continue;
                }
                if (str_contains($frame['file'], '/src/Model/')) {
                    continue;
                }
            }

            $location = ($frame['file'] ?? 'unknown') . ':' . ($frame['line'] ?? 0);
            $call = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? 'unknown');

            $relevant[] = "{$call} ({$location})";

            if (count($relevant) >= 3) {
                break;
            }
        }

        return implode(' <- ', $relevant);
    }

    /**
     * Get report of all issues found.
     *
     * @return array{
     *     total_queries: int,
     *     total_time: float,
     *     slow_queries: array,
     *     n_plus_one: array
     * }
     */
    public static function getReport(): array
    {
        $totalTime = array_sum(array_column(self::$queries, 'time'));

        return [
            'total_queries' => count(self::$queries),
            'total_time' => round($totalTime, 2),
            'slow_queries' => self::$slowQueries,
            'n_plus_one' => self::$nPlusOneQueries,
        ];
    }

    /**
     * Log the report if there are issues.
     */
    public static function report(?Logger $logger = null): void
    {
        if (!self::$enabled) {
            return;
        }

        $report = self::getReport();

        // Log slow queries
        foreach ($report['slow_queries'] as $query) {
            $message = sprintf(
                'SLOW QUERY (%.2fms): %s [%s]',
                $query['time'],
                $query['sql'],
                $query['trace']
            );

            if ($logger) {
                $logger->warning($message);
            } else {
                error_log($message);
            }
        }

        // Log N+1 queries
        foreach ($report['n_plus_one'] as $pattern => $info) {
            $message = sprintf(
                'N+1 QUERY DETECTED (%d times): %s [%s]',
                $info['count'],
                $info['example'],
                $info['trace']
            );

            if ($logger) {
                $logger->warning($message);
            } else {
                error_log($message);
            }
        }
    }

    /**
     * Get all recorded queries.
     *
     * @return array<int, array{sql: string, time: float, bindings: array, trace: string}>
     */
    public static function getQueries(): array
    {
        return self::$queries;
    }

    /**
     * Check if there are any N+1 issues.
     */
    public static function hasNPlusOneIssues(): bool
    {
        return !empty(self::$nPlusOneQueries);
    }

    /**
     * Check if there are any slow queries.
     */
    public static function hasSlowQueries(): bool
    {
        return !empty(self::$slowQueries);
    }

    /**
     * Get query count.
     */
    public static function getQueryCount(): int
    {
        return count(self::$queries);
    }

    /**
     * Get total query time in milliseconds.
     */
    public static function getTotalTime(): float
    {
        return array_sum(array_column(self::$queries, 'time'));
    }
}
