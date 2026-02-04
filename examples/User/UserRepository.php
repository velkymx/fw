<?php

declare(strict_types=1);

namespace Examples\User;

use Fw\Database\Connection;
use Fw\Domain\Email;
use Fw\Domain\UserId;
use Fw\Repository\AsyncRepository;
use Fw\Support\Option;

/**
 * User repository with async database operations.
 *
 * Demonstrates:
 * - Extending AsyncRepository base
 * - Typed repository with User entity
 * - Custom query methods
 * - Value Object integration
 *
 * @extends AsyncRepository<User, UserId>
 */
final class UserRepository extends AsyncRepository
{
    protected string $table = 'users';
    protected string $entityClass = User::class;
    protected string $idClass = UserId::class;

    public function __construct(Connection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * Find user by email address.
     *
     * @return Option<User>
     */
    public function findByEmail(Email $email): Option
    {
        return $this->findOneBy(['email' => (string) $email]);
    }

    /**
     * Find user by email string.
     *
     * @return Option<User>
     */
    public function findByEmailString(string $email): Option
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Check if email is already taken.
     */
    public function emailExists(Email $email): bool
    {
        return $this->findByEmail($email)->isSome();
    }

    /**
     * Get users with pagination.
     *
     * @return array<User>
     */
    public function paginate(int $page = 1, int $perPage = 15): array
    {
        $offset = ($page - 1) * $perPage;

        $rows = $this->db
            ->fetchAll(
                "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT ? OFFSET ?",
                [$perPage, $offset]
            )
            ->await();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    /**
     * Search users by name.
     *
     * @return array<User>
     */
    public function searchByName(string $query): array
    {
        $rows = $this->db
            ->fetchAll(
                "SELECT * FROM {$this->table} WHERE name LIKE ? ORDER BY name",
                ["%{$query}%"]
            )
            ->await();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }
}
