<?php

declare(strict_types=1);

namespace Fw\Console\Commands;

use Fw\Console\Command;

/**
 * Analyze pending migrations for destructive operations.
 *
 * This command helps prevent accidental data loss by warning about:
 * - DROP TABLE / DROP COLUMN
 * - TRUNCATE
 * - DELETE without WHERE
 * - ALTER TABLE that removes columns
 *
 * Run before migrate to catch potential issues.
 */
class MigrateCheckCommand extends Command
{
    protected string $name = 'migrate:check';

    protected string $description = 'Analyze pending migrations for destructive operations';

    /**
     * Patterns that indicate destructive operations.
     * @var array<string, string>
     */
    private array $destructivePatterns = [
        'DROP TABLE' => 'Will permanently delete table and all data',
        'DROP COLUMN' => 'Will permanently delete column and all data in it',
        'TRUNCATE' => 'Will delete all rows from table',
        'DELETE FROM' => 'May delete data (check for WHERE clause)',
        'dropColumn' => 'Will permanently delete column and all data in it',
        'dropTable' => 'Will permanently delete table and all data',
        'drop(' => 'Will permanently delete table and all data',
        'dropIfExists' => 'Will permanently delete table if it exists',
        'dropForeign' => 'Will remove foreign key constraint',
        'dropIndex' => 'Will remove index (may impact performance)',
        'dropPrimary' => 'Will remove primary key',
        'dropUnique' => 'Will remove unique constraint',
        'renameColumn' => 'Will rename column (may break dependent code)',
        'renameTable' => 'Will rename table (may break dependent code)',
        'RENAME TABLE' => 'Will rename table (may break dependent code)',
        'MODIFY COLUMN' => 'May change column type (potential data loss)',
        'ALTER COLUMN' => 'May change column type (potential data loss)',
    ];

    /**
     * Patterns that indicate safe operations.
     * @var array<string>
     */
    private array $safePatterns = [
        'CREATE TABLE',
        'ADD COLUMN',
        'CREATE INDEX',
        'addColumn',
        'create(',
        'table(',
        'index(',
    ];

    public function handle(): int
    {
        $migrationsPath = BASE_PATH . '/database/migrations';

        if (!is_dir($migrationsPath)) {
            $this->output->error('Migrations directory not found');
            return 1;
        }

        // Get pending migrations
        $migrations = $this->getPendingMigrations($migrationsPath);

        if (empty($migrations)) {
            $this->output->success('No pending migrations to check');
            return 0;
        }

        $this->output->info("Analyzing " . count($migrations) . " pending migration(s)...");
        $this->output->newLine();

        $hasDestructive = false;
        $warnings = [];

        foreach ($migrations as $file) {
            $content = file_get_contents($file);
            $filename = basename($file);

            $fileWarnings = $this->analyzeContent($content);

            if (!empty($fileWarnings)) {
                $hasDestructive = true;
                $warnings[$filename] = $fileWarnings;
            }
        }

        if ($hasDestructive) {
            $this->output->warning('DESTRUCTIVE OPERATIONS DETECTED:');
            $this->output->newLine();

            foreach ($warnings as $filename => $fileWarnings) {
                $this->output->line("  {$filename}:");
                foreach ($fileWarnings as $warning) {
                    $this->output->line("    - {$warning['pattern']}: {$warning['description']}");
                    if (isset($warning['line'])) {
                        $this->output->line("      Line {$warning['line']}: {$warning['context']}");
                    }
                }
                $this->output->newLine();
            }

            $this->output->warning('Review these changes carefully before running migrate.');
            $this->output->line('Run "php fw migrate --force" to proceed anyway.');
            return 1;
        }

        $this->output->success('No destructive operations detected in pending migrations');
        return 0;
    }

    /**
     * Get list of pending migration files.
     *
     * @return array<string>
     */
    private function getPendingMigrations(string $path): array
    {
        // For simplicity, return all migration files
        // In a full implementation, this would check against the migrations table
        $files = glob($path . '/*.php');
        return $files ?: [];
    }

    /**
     * Analyze migration content for destructive patterns.
     *
     * @return array<array{pattern: string, description: string, line?: int, context?: string}>
     */
    private function analyzeContent(string $content): array
    {
        $warnings = [];
        $lines = explode("\n", $content);

        foreach ($this->destructivePatterns as $pattern => $description) {
            foreach ($lines as $lineNum => $line) {
                // Skip comments
                $trimmedLine = trim($line);
                if (str_starts_with($trimmedLine, '//') || str_starts_with($trimmedLine, '*')) {
                    continue;
                }

                if (stripos($line, $pattern) !== false) {
                    // Check if it's in the down() method (rollback - more acceptable)
                    $isInDown = $this->isInDownMethod($content, $lineNum);

                    $warnings[] = [
                        'pattern' => $pattern . ($isInDown ? ' (in down())' : ''),
                        'description' => $description . ($isInDown ? ' - rollback only' : ''),
                        'line' => $lineNum + 1,
                        'context' => trim(substr($line, 0, 80)),
                    ];
                }
            }
        }

        // Check for DELETE without WHERE
        if (preg_match('/DELETE\s+FROM\s+\w+\s*(?:;|$)/i', $content)) {
            $warnings[] = [
                'pattern' => 'DELETE without WHERE',
                'description' => 'Will delete ALL rows from table',
            ];
        }

        return $warnings;
    }

    /**
     * Check if a line is within the down() method.
     */
    private function isInDownMethod(string $content, int $targetLine): bool
    {
        $lines = explode("\n", $content);
        $inDown = false;
        $braceCount = 0;

        for ($i = 0; $i <= $targetLine && $i < count($lines); $i++) {
            $line = $lines[$i];

            // Check for down method start
            if (preg_match('/function\s+down\s*\(/', $line)) {
                $inDown = true;
                $braceCount = 0;
            }

            // Check for up method (exits down context)
            if (preg_match('/function\s+up\s*\(/', $line)) {
                $inDown = false;
            }

            // Track braces to know when we exit the method
            if ($inDown) {
                $braceCount += substr_count($line, '{');
                $braceCount -= substr_count($line, '}');

                if ($braceCount < 0) {
                    $inDown = false;
                }
            }
        }

        return $inDown;
    }
}
