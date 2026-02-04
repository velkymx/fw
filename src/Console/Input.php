<?php

declare(strict_types=1);

namespace Fw\Console;

/**
 * Parse and access CLI arguments and options.
 */
final class Input
{
    /** @var array<string, mixed> */
    private array $arguments = [];

    /** @var array<string, mixed> */
    private array $options = [];

    private string $commandName = '';

    /**
     * @param array<string> $argv
     * @param array<string, array{description: string, required: bool}> $argDefinitions
     * @param array<string, array{description: string, default: mixed, shortcut?: string}> $optDefinitions
     */
    public function __construct(
        array $argv,
        array $argDefinitions = [],
        array $optDefinitions = [],
    ) {
        $this->parse($argv, $argDefinitions, $optDefinitions);
    }

    /**
     * Get an argument value.
     */
    public function argument(string $name): ?string
    {
        return $this->arguments[$name] ?? null;
    }

    /**
     * Get all arguments.
     *
     * @return array<string, mixed>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get an option value.
     */
    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Check if an option was provided.
     */
    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options) && $this->options[$name] !== null;
    }

    /**
     * Get all options.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Get the command name (first argument after script name).
     */
    public function getCommandName(): string
    {
        return $this->commandName;
    }

    /**
     * Parse argv into arguments and options.
     *
     * @param array<string> $argv
     * @param array<string, array{description: string, required: bool}> $argDefinitions
     * @param array<string, array{description: string, default: mixed, shortcut?: string}> $optDefinitions
     */
    private function parse(array $argv, array $argDefinitions, array $optDefinitions): void
    {
        // Remove script name
        array_shift($argv);

        // First non-option argument is the command name
        if (! empty($argv) && ! str_starts_with($argv[0], '-')) {
            $this->commandName = array_shift($argv);
        }

        // Build shortcut map
        $shortcuts = [];
        foreach ($optDefinitions as $name => $def) {
            if (isset($def['shortcut'])) {
                $shortcuts[$def['shortcut']] = $name;
            }
            // Set defaults
            $this->options[$name] = $def['default'] ?? null;
        }

        // Parse remaining arguments
        $argNames = array_keys($argDefinitions);
        $argIndex = 0;

        while (! empty($argv)) {
            $token = array_shift($argv);

            // Long option: --option or --option=value
            if (str_starts_with($token, '--')) {
                $this->parseLongOption($token, $argv);
                continue;
            }

            // Short option: -o or -o value
            if (str_starts_with($token, '-')) {
                $this->parseShortOption($token, $argv, $shortcuts);
                continue;
            }

            // Positional argument
            if (isset($argNames[$argIndex])) {
                $this->arguments[$argNames[$argIndex]] = $token;
                $argIndex++;
            }
        }
    }

    /**
     * Parse a long option (--option or --option=value).
     *
     * @param array<string> $remaining
     */
    private function parseLongOption(string $token, array &$remaining): void
    {
        $option = substr($token, 2);

        if (str_contains($option, '=')) {
            [$name, $value] = explode('=', $option, 2);
            $this->options[$name] = $value;
        } else {
            // Check if next token is a value (not an option)
            if (! empty($remaining) && ! str_starts_with($remaining[0], '-')) {
                $this->options[$option] = array_shift($remaining);
            } else {
                $this->options[$option] = true;
            }
        }
    }

    /**
     * Parse a short option (-o or -o value).
     *
     * @param array<string> $remaining
     * @param array<string, string> $shortcuts
     */
    private function parseShortOption(string $token, array &$remaining, array $shortcuts): void
    {
        $chars = substr($token, 1);

        // Handle bundled options: -abc
        if (strlen($chars) > 1) {
            foreach (str_split($chars) as $char) {
                $name = $shortcuts[$char] ?? $char;
                $this->options[$name] = true;
            }
            return;
        }

        $char = $chars;
        $name = $shortcuts[$char] ?? $char;

        // Check if next token is a value
        if (! empty($remaining) && ! str_starts_with($remaining[0], '-')) {
            $this->options[$name] = array_shift($remaining);
        } else {
            $this->options[$name] = true;
        }
    }
}
