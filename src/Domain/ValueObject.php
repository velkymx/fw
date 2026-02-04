<?php

declare(strict_types=1);

namespace Fw\Domain;

use Stringable;

/**
 * Base interface for Value Objects.
 *
 * Value Objects are immutable, compared by value (not identity),
 * and encapsulate validation rules for domain primitives.
 *
 * PHP 8.4 property hooks make Value Objects much cleaner by allowing
 * derived properties and validation without explicit getters/setters.
 */
interface ValueObject extends Stringable
{
    /**
     * Check equality with another value object.
     */
    public function equals(self $other): bool;
}
