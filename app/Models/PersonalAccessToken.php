<?php

declare(strict_types=1);

namespace App\Models;

use Fw\Model\Model;
use Fw\Model\BelongsTo;
use Fw\Model\Collection;
use Fw\Domain\UserId;

class PersonalAccessToken extends Model
{
    protected static ?string $table = 'personal_access_tokens';

    protected static bool $incrementing = false;

    protected static string $keyType = 'string';

    protected static array $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'expires_at',
        'last_used_at',
    ];

    protected static array $casts = [
        'user_id' => UserId::class,
        'abilities' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function can(string $ability): bool
    {
        $abilities = $this->getAttribute('abilities') ?? [];
        if (empty($abilities)) {
            return false;
        }

        // Wildcard grants all
        if (in_array('*', $abilities, true)) {
            return true;
        }

        // Exact match
        if (in_array($ability, $abilities, true)) {
            return true;
        }

        // Check for parent ability (e.g., 'posts' grants 'posts:read')
        if (str_contains($ability, ':')) {
            [$parent, $action] = explode(':', $ability, 2);

            // Parent ability grants all children (e.g., 'posts' grants 'posts:read')
            if (in_array($parent, $abilities, true)) {
                return true;
            }

            // Action ability grants that action on all resources (e.g., 'read' grants 'posts:read')
            if (in_array($action, $abilities, true)) {
                return true;
            }
        }

        return false;
    }

    public function cannot(string $ability): bool
    {
        return !$this->can($ability);
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->getAttribute('expires_at');
        return $expiresAt !== null && $expiresAt->getTimestamp() < time();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    public function revoke(): bool
    {
        return $this->delete();
    }

    public function touchLastUsed(): void
    {
        $this->setAttribute('last_used_at', new \DateTimeImmutable());
        $this->save();
    }

    public static function findToken(string $hashedToken): ?self
    {
        return static::where('token', $hashedToken)->first()->unwrapOr(null);
    }

    public static function forUser(string|UserId $userId): Collection
    {
        return static::where('user_id', (string) $userId)->get();
    }
}
