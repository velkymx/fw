<?php

declare(strict_types=1);

namespace Fw\Console;

/**
 * Base class for console commands.
 */
abstract class Command
{
    protected string $name = '';

    protected string $description = '';

    /** @var array<string, array{description: string, required: bool}> */
    protected array $arguments = [];

    /** @var array<string, array{description: string, default: mixed, shortcut?: string}> */
    protected array $options = [];

    protected Input $input;

    protected Output $output;

    /**
     * Execute the command.
     *
     * @return int Exit code (0 = success)
     */
    abstract public function handle(): int;

    /**
     * Configure the command (called before handle).
     */
    public function configure(): void
    {
        // Override in subclass to add arguments/options
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, array{description: string, required: bool}>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, array{description: string, default: mixed, shortcut?: string}>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function setInput(Input $input): void
    {
        $this->input = $input;
    }

    public function setOutput(Output $output): void
    {
        $this->output = $output;
    }

    // Helper methods for subclasses

    protected function argument(string $name): ?string
    {
        return $this->input->argument($name);
    }

    protected function option(string $name): mixed
    {
        return $this->input->option($name);
    }

    protected function hasOption(string $name): bool
    {
        return $this->input->hasOption($name);
    }

    protected function line(string $text): void
    {
        $this->output->line($text);
    }

    protected function info(string $text): void
    {
        $this->output->info($text);
    }

    protected function success(string $text): void
    {
        $this->output->success($text);
    }

    protected function warning(string $text): void
    {
        $this->output->warning($text);
    }

    protected function error(string $text): void
    {
        $this->output->error($text);
    }

    protected function comment(string $text): void
    {
        $this->output->comment($text);
    }

    protected function newLine(int $count = 1): void
    {
        $this->output->newLine($count);
    }

    /**
     * @param array<string> $headers
     * @param array<array<string>> $rows
     */
    protected function table(array $headers, array $rows): void
    {
        $this->output->table($headers, $rows);
    }

    /**
     * Ask a question and return the answer.
     */
    protected function ask(string $question, ?string $default = null): string
    {
        $prompt = $question;
        if ($default !== null) {
            $prompt .= ' [' . $default . ']';
        }
        $prompt .= ': ';

        $this->output->write($this->output->color($prompt, 'green'));

        $answer = trim((string) fgets(STDIN));

        return $answer !== '' ? $answer : ($default ?? '');
    }

    /**
     * Ask for confirmation (y/n).
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        $defaultText = $default ? 'Y/n' : 'y/N';
        $prompt = $question . ' [' . $defaultText . ']: ';

        $this->output->write($this->output->color($prompt, 'green'));

        $answer = strtolower(trim((string) fgets(STDIN)));

        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['y', 'yes'], true);
    }

    /**
     * Present a choice menu.
     *
     * @param array<string> $choices
     */
    protected function choice(string $question, array $choices, ?int $default = null): string
    {
        $this->line($question);
        foreach ($choices as $i => $choice) {
            $marker = $default === $i ? '*' : ' ';
            $this->line("  [$marker] [$i] $choice");
        }

        $defaultText = $default !== null ? (string) $default : '';
        $answer = $this->ask('Enter choice', $defaultText);

        $index = (int) $answer;
        return $choices[$index] ?? $choices[0];
    }

    /**
     * Define an argument.
     */
    protected function addArgument(string $name, string $description, bool $required = true): static
    {
        $this->arguments[$name] = [
            'description' => $description,
            'required' => $required,
        ];
        return $this;
    }

    /**
     * Define an option.
     */
    protected function addOption(
        string $name,
        string $description,
        mixed $default = null,
        ?string $shortcut = null,
    ): static {
        $this->options[$name] = [
            'description' => $description,
            'default' => $default,
        ];
        if ($shortcut !== null) {
            $this->options[$name]['shortcut'] = $shortcut;
        }
        return $this;
    }
}
