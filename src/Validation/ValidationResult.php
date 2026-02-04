<?php

declare(strict_types=1);

namespace Fw\Validation;

/**
 * Result of a validation attempt.
 *
 * Used by Validator::tryValidate() for non-throwing validation.
 */
final readonly class ValidationResult
{
    /**
     * @param bool $passed Whether validation passed
     * @param array<string, mixed> $validated The validated data (only if passed)
     * @param array<string, array<string>> $errors Validation errors (only if failed)
     */
    public function __construct(
        public bool $passed,
        public array $validated,
        public array $errors,
    ) {}

    /**
     * Check if validation passed.
     */
    public function passes(): bool
    {
        return $this->passed;
    }

    /**
     * Check if validation failed.
     */
    public function fails(): bool
    {
        return !$this->passed;
    }

    /**
     * Get errors for a specific field.
     *
     * @return array<string>
     */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get the first error for a specific field.
     */
    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get the first error for each field.
     *
     * @return array<string, string>
     */
    public function firstErrors(): array
    {
        $first = [];
        foreach ($this->errors as $field => $messages) {
            if (!empty($messages)) {
                $first[$field] = $messages[0];
            }
        }
        return $first;
    }
}
