<?php

declare(strict_types=1);

namespace Examples\User;

use DateTimeImmutable;
use Fw\Domain\Email;
use Fw\Domain\UserId;
use Fw\Model\Collection;
use Fw\Model\HasMany;
use Fw\Model\Model;

/**
 * User Model with Typed Active Record pattern.
 *
 * This demonstrates the single, unified approach to models:
 * - Type-safe properties with auto-casting via property hooks
 * - Static query methods (find, where, all, etc.)
 * - Relationships (hasMany, belongsTo)
 * - Async-ready (works with Fibers)
 * - Driver-agnostic (SQLite, MySQL, PostgreSQL)
 *
 * @example
 *     // Finding
 *     $user = User::find($id);              // Option<User>
 *     $user = User::findOrFail($id);        // User (throws if not found)
 *     $users = User::where('active', true)->get();
 *
 *     // Creating
 *     $user = User::create([
 *         'email' => 'john@example.com',
 *         'name' => 'John Doe',
 *     ]);
 *
 *     // Updating
 *     $user->name = 'Jane Doe';
 *     $user->save();
 *
 *     // Relationships
 *     $posts = $user->posts;
 *     $users = User::with('posts')->get();
 */
class UserModel extends Model
{
    /**
     * The table name.
     */
    protected static ?string $table = 'users';

    /**
     * The primary key type.
     */
    protected static string $keyType = 'string';

    /**
     * Disable auto-incrementing (using UUIDs).
     */
    protected static bool $incrementing = false;

    /**
     * Mass-assignable attributes.
     */
    protected static array $fillable = [
        'id',
        'email',
        'name',
        'password',
        'is_active',
    ];

    /**
     * Cast rules for attributes.
     * Note: These can also be auto-detected from typed properties.
     */
    protected static array $casts = [
        'id' => UserId::class,
        'email' => Email::class,
        'is_active' => 'bool',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========================================
    // TYPED PROPERTIES WITH PROPERTY HOOKS
    // ========================================

    /**
     * User ID with auto-casting.
     */
    public UserId $id {
        set(string|UserId $value) => UserId::wrap($value);
    }

    /**
     * Email with auto-casting.
     */
    public Email $email {
        set(string|Email $value) => Email::wrap($value);
    }

    /**
     * User's name.
     */
    public string $name;

    /**
     * Password hash.
     */
    public private(set) string $password;

    /**
     * Whether user is active.
     */
    public bool $isActive = true;

    /**
     * Created timestamp.
     */
    public private(set) ?DateTimeImmutable $createdAt = null;

    /**
     * Updated timestamp.
     */
    public private(set) ?DateTimeImmutable $updatedAt = null;

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Get user's posts.
     *
     * @return HasMany<UserModel, PostModel>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(PostModel::class);
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    /**
     * Scope: Only active users.
     */
    public static function active(): \Fw\Model\ModelQueryBuilder
    {
        return static::where('is_active', true);
    }

    /**
     * Scope: Users by email domain.
     */
    public static function fromDomain(string $domain): \Fw\Model\ModelQueryBuilder
    {
        return static::where('email', 'LIKE', "%@{$domain}");
    }

    // ========================================
    // CUSTOM METHODS
    // ========================================

    /**
     * Set the user's password (hashes it).
     */
    public function setPassword(string $plainPassword): static
    {
        $this->setAttribute('password', password_hash($plainPassword, PASSWORD_DEFAULT));
        return $this;
    }

    /**
     * Verify password.
     */
    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->password ?? '');
    }

    /**
     * Activate the user.
     */
    public function activate(): static
    {
        $this->isActive = true;
        return $this;
    }

    /**
     * Deactivate the user.
     */
    public function deactivate(): static
    {
        $this->isActive = false;
        return $this;
    }

    /**
     * Get user's full email display.
     */
    public function emailDisplay(): string
    {
        return "{$this->name} <{$this->email}>";
    }

    // ========================================
    // FACTORY METHODS
    // ========================================

    /**
     * Create a new user with a generated ID.
     *
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): static
    {
        $attributes['id'] ??= UserId::generate();

        $model = new static($attributes);

        if (isset($attributes['password'])) {
            $model->setPassword($attributes['password']);
        }

        return $model;
    }

    /**
     * Register a new user.
     */
    public static function register(string $email, string $name, string $password): static
    {
        return static::make([
            'email' => $email,
            'name' => $name,
            'password' => $password,
            'is_active' => true,
        ]);
    }
}

/**
 * Example Post model for relationship demo.
 */
class PostModel extends Model
{
    protected static ?string $table = 'posts';
    protected static string $keyType = 'string';
    protected static bool $incrementing = false;

    protected static array $fillable = [
        'id',
        'user_id',
        'title',
        'body',
    ];

    public function user(): \Fw\Model\BelongsTo
    {
        return $this->belongsTo(UserModel::class);
    }
}
