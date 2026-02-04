<?php

declare(strict_types=1);

namespace Fw\Validation\Rules;

use Attribute;
use Fw\Validation\Rule;

/**
 * Field must match a regular expression pattern.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Regex implements Rule
{
    public function __construct(
        public readonly string $pattern,
        public readonly string $message = 'The :field format is invalid.',
    ) {}

    public function validate(mixed $value, string $field, array $data = []): ?string
    {
        if ($value === null || $value === '') {
            return null; // Use Required for mandatory fields
        }

        if (!is_string($value) || !preg_match($this->pattern, $value)) {
            return str_replace(':field', $field, $this->message);
        }

        return null;
    }
}
