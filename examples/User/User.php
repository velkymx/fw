<?php

declare(strict_types=1);

namespace Examples\User;

use Fw\Domain\Email;
use Fw\Domain\UserId;
use JsonSerializable;

/**
 * User entity with strongly-typed Value Objects.
 *
 * Demonstrates:
 * - Typed ID (UserId)
 * - Email value object
 * - Immutable properties
 * - Entity serialization
 */
final readonly class User implements JsonSerializable
{
    public function __construct(
        public UserId $id,
        public Email $email,
        public string $name,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /**
     * Create from array (hydration).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: UserId::fromTrusted($data['id']),
            email: Email::from($data['email']),
            name: $data['name'],
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }

    /**
     * Convert to array (dehydration).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value,
            'email' => (string) $this->email,
            'name' => $this->name,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Create a new user with updated name.
     */
    public function withName(string $name): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            name: $name,
            createdAt: $this->createdAt,
            updatedAt: date('Y-m-d H:i:s'),
        );
    }

    /**
     * Create a new user with updated email.
     */
    public function withEmail(Email $email): self
    {
        return new self(
            id: $this->id,
            email: $email,
            name: $this->name,
            createdAt: $this->createdAt,
            updatedAt: date('Y-m-d H:i:s'),
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
