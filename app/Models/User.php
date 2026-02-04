<?php

declare(strict_types=1);

namespace App\Models;

use Fw\Model\Model;
use Fw\Model\ModelQueryBuilder;
use Fw\Model\HasMany;
use Fw\Support\Option;
use Fw\Domain\Email;

/**
 * User model with authentication support.
 *
 * Demonstrates:
 * - Relationship definitions (HasMany)
 * - Custom mutators (setPassword)
 * - Query scopes (active, verified)
 * - Type casting with value objects (Email)
 * - Option return type for findByEmail
 */
class User extends Model
{
    protected static ?string $table = 'users';

    /**
     * Mass assignable fields.
     * Note: password is NOT fillable to prevent mass assignment vulnerabilities.
     * Use setPassword() method instead.
     */
    protected static array $fillable = ['email', 'name'];

    /**
     * Hidden fields - excluded from toArray() and JSON serialization.
     */
    protected static array $hidden = ['password', 'remember_token'];

    /**
     * Type casts for automatic conversion.
     */
    protected static array $casts = [
        'email' => Email::class,
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =========================================================================
    // Relationships
    // =========================================================================

    /**
     * Get all posts authored by this user.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    // =========================================================================
    // Authentication Methods
    // =========================================================================

    /**
     * Securely set the user's password.
     *
     * Uses PASSWORD_DEFAULT which automatically uses the strongest
     * available algorithm (currently bcrypt, will upgrade to Argon2id
     * when PHP defaults change).
     */
    public function setPassword(string $plainPassword): static
    {
        $this->setAttribute('password', password_hash($plainPassword, PASSWORD_DEFAULT));
        return $this;
    }

    /**
     * Verify a plain password against the stored hash.
     *
     * Uses timing-safe comparison internally via password_verify().
     */
    public function verifyPassword(string $plainPassword): bool
    {
        $hash = $this->getAttribute('password');
        if ($hash === null || $hash === '') {
            return false;
        }
        return password_verify($plainPassword, $hash);
    }

    // =========================================================================
    // Query Methods
    // =========================================================================

    /**
     * Find a user by email address (case-insensitive).
     *
     * @return Option<static> Some(User) if found, None if not
     */
    public static function findByEmail(string $email): Option
    {
        return static::where('email', strtolower($email))->first();
    }

    // =========================================================================
    // Query Scopes
    // =========================================================================

    /**
     * Scope: Only active users.
     */
    public static function active(): ModelQueryBuilder
    {
        return static::where('is_active', '=', true);
    }

    /**
     * Scope: Only verified users.
     */
    public static function verified(): ModelQueryBuilder
    {
        return static::whereNotNull('email_verified_at');
    }

    /**
     * Scope: Recently registered users.
     */
    public static function recent(int $days = 7): ModelQueryBuilder
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return static::where('created_at', '>=', $since);
    }
}
