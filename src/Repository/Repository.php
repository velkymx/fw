<?php

declare(strict_types=1);

namespace Fw\Repository;

use Fw\Domain\Id;
use Fw\Support\Option;

/**
 * Base Repository interface.
 *
 * Repositories abstract data access, providing a collection-like interface
 * for aggregate roots. This enables:
 * - Swappable implementations (Database, InMemory, Cache)
 * - Testability without database dependencies
 * - Domain logic isolation from persistence concerns
 *
 * @template T The entity type
 * @template TId of Id The entity ID type
 */
interface Repository
{
    /**
     * Find an entity by ID.
     *
     * @param TId $id
     * @return Option<T>
     */
    public function find(Id $id): Option;

    /**
     * Find an entity by ID, throwing if not found.
     *
     * @param TId $id
     * @return T
     * @throws EntityNotFoundException
     */
    public function findOrFail(Id $id): mixed;

    /**
     * Check if an entity exists.
     *
     * @param TId $id
     */
    public function exists(Id $id): bool;

    /**
     * Save an entity (insert or update).
     *
     * @param T $entity
     */
    public function save(mixed $entity): void;

    /**
     * Delete an entity.
     *
     * @param T $entity
     */
    public function delete(mixed $entity): void;

    /**
     * Delete an entity by ID.
     *
     * @param TId $id
     */
    public function deleteById(Id $id): void;
}
