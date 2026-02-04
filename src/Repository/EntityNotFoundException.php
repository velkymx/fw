<?php

declare(strict_types=1);

namespace Fw\Repository;

use Fw\Domain\Id;
use RuntimeException;

/**
 * Exception thrown when an entity cannot be found.
 */
final class EntityNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $entityClass,
        public readonly Id $id
    ) {
        parent::__construct(
            sprintf('%s with ID %s not found', $entityClass, $id)
        );
    }

    /**
     * Create exception for a specific entity type.
     */
    public static function for(string $entityClass, Id $id): self
    {
        return new self($entityClass, $id);
    }
}
