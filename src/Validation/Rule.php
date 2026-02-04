<?php

declare(strict_types=1);

namespace Fw\Validation;

/**
 * Base interface for validation rule attributes.
 */
interface Rule
{
    /**
     * Validate a value.
     *
     * @param mixed $value The value to validate
     * @param string $field The field name (for error messages)
     * @param array<string, mixed> $data All input data (for cross-field validation)
     * @return string|null Error message if validation fails, null if passes
     */
    public function validate(mixed $value, string $field, array $data = []): ?string;
}
