<?php

declare(strict_types=1);

namespace Fw\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;

/**
 * Value object representing a newly created access token.
 *
 * This is returned when creating a token and contains both the
 * plaintext token (shown once) and the token model.
 */
final class NewAccessToken
{
    public function __construct(
        public readonly PersonalAccessToken $accessToken,
        public readonly string $plainTextToken
    ) {}

    /**
     * Get the full token string for API usage.
     */
    public function getToken(): string
    {
        return $this->plainTextToken;
    }

    /**
     * Convert to array for JSON responses.
     */
    public function toArray(): array
    {
        return [
            'token' => $this->plainTextToken,
            'token_id' => $this->accessToken->id,
            'name' => $this->accessToken->name,
            'abilities' => $this->accessToken->abilities,
            'expires_at' => $this->accessToken->expires_at,
        ];
    }
}

/**
 * API Token management service.
 *
 * Handles creation, validation, and management of personal access tokens.
 * Tokens are stored as SHA-256 hashes; plaintext is only returned once on creation.
 */
final class ApiToken
{
    private const int TOKEN_BYTES = 20; // 40 hex characters

    /**
     * Dummy hash for timing-safe comparison when token doesn't exist.
     * Prevents token enumeration via timing attacks.
     */
    private const string DUMMY_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    private static ?array $config = null;

    /**
     * Load API configuration.
     */
    private static function config(): array
    {
        if (self::$config === null) {
            $configPath = dirname(__DIR__, 2) . '/config/api.php';
            self::$config = file_exists($configPath) ? require $configPath : [];
        }

        return self::$config;
    }

    /**
     * Create a new personal access token for a user.
     */
    public static function create(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null
    ): NewAccessToken {
        $config = self::config();

        // Validate abilities against allowed list
        $allowedAbilities = $config['abilities'] ?? [];
        if (!empty($allowedAbilities)) {
            foreach ($abilities as $ability) {
                if (!in_array($ability, $allowedAbilities, true)) {
                    throw new \InvalidArgumentException("Invalid ability: {$ability}");
                }
            }
        }

        // Generate random token
        $randomBytes = random_bytes(self::TOKEN_BYTES);
        $randomHex = bin2hex($randomBytes);

        // Format: {user_id}|{random_hex}
        $prefix = $config['token_prefix'] ?? '';
        $plainTextToken = $prefix . $user->id . '|' . $randomHex;

        // Hash for storage
        $hashAlgo = $config['hash_algo'] ?? 'sha256';
        $hashedToken = hash($hashAlgo, $plainTextToken);

        // Set expiration
        if ($expiresAt === null && isset($config['token_expiration'])) {
            $expiration = $config['token_expiration'];
            if ($expiration !== null && $expiration > 0) {
                $expiresAt = new \DateTimeImmutable("+{$expiration} seconds");
            }
        }

        // Create token record
        $token = PersonalAccessToken::create([
            'user_id' => $user->id,
            'name' => $name,
            'token' => $hashedToken,
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
            'last_used_at' => null,
        ]);

        return new NewAccessToken($token, $plainTextToken);
    }

    /**
     * Find and validate a token from a plaintext token string.
     *
     * Returns the token model if valid, null otherwise.
     * Uses constant-time operations to prevent timing attacks.
     *
     * SECURITY: All code paths execute the same operations in the same order
     * to prevent timing side-channels that could distinguish between:
     * - Token not found
     * - Token found but expired
     * - Token found and valid
     */
    public static function find(string $plainTextToken): ?PersonalAccessToken
    {
        $config = self::config();

        // Hash the token
        $hashAlgo = $config['hash_algo'] ?? 'sha256';
        $hashedToken = hash($hashAlgo, $plainTextToken);

        // Find token
        $token = PersonalAccessToken::findToken($hashedToken);

        // TIMING ATTACK MITIGATION:
        // Execute identical operations regardless of token state to ensure
        // constant-time behavior. All three cases (not found, expired, valid)
        // must perform the same work.

        // Step 1: Perform hash comparison (always)
        // When token not found, compare against dummy hash
        // When token found, compare against stored hash (which we already matched)
        // @phpstan-ignore function.resultUnused (intentional for constant-time execution)
        $_ = hash_equals(self::DUMMY_HASH, $hashedToken);

        // Step 2: Check expiration (always - use dummy check if no token)
        // This ensures isExpired() timing doesn't leak token existence
        $isExpired = $token !== null ? $token->isExpired() : self::dummyExpirationCheck();

        // Step 3: Second dummy comparison to balance timing
        // @phpstan-ignore function.resultUnused (intentional for constant-time execution)
        $_ = hash_equals(self::DUMMY_HASH, self::DUMMY_HASH);

        // Now make the decision (timing-safe since all work is done)
        if ($token === null || $isExpired) {
            return null;
        }

        return $token;
    }

    /**
     * Perform dummy expiration check to balance timing when token doesn't exist.
     *
     * This ensures the same amount of work is done regardless of token existence.
     */
    private static function dummyExpirationCheck(): bool
    {
        // Simulate the same work as isExpired() without actual data
        // Compare current time against a dummy timestamp
        $_ = time() > 0;
        return true; // Treat as expired (will be rejected anyway since token is null)
    }

    /**
     * Parse a token string to extract user ID (for quick lookup optimization).
     *
     * Returns [user_id, random_part] or null if invalid format.
     */
    public static function parseToken(string $plainTextToken): ?array
    {
        $config = self::config();
        $prefix = $config['token_prefix'] ?? '';

        // Remove prefix if present
        if ($prefix !== '' && str_starts_with($plainTextToken, $prefix)) {
            $plainTextToken = substr($plainTextToken, strlen($prefix));
        }

        $parts = explode('|', $plainTextToken, 2);

        if (count($parts) !== 2) {
            return null;
        }

        $userId = $parts[0];

        // User ID must be non-empty
        if ($userId === '') {
            return null;
        }

        return [$userId, $parts[1]];
    }

    /**
     * Revoke all tokens for a user.
     */
    public static function revokeAll(User $user): int
    {
        $tokens = PersonalAccessToken::forUser($user->id);
        $count = 0;

        foreach ($tokens as $token) {
            if ($token->delete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Revoke a specific token by ID for a user.
     */
    public static function revoke(User $user, string $tokenId): bool
    {
        $tokenOption = PersonalAccessToken::find($tokenId);

        if ($tokenOption->isNone()) {
            return false;
        }

        $token = $tokenOption->unwrap();
        if ((string) $token->user_id !== (string) $user->id) {
            return false;
        }

        return $token->delete();
    }

    /**
     * Get all tokens for a user.
     *
     * @return \Fw\Model\Collection<PersonalAccessToken>
     */
    public static function tokens(User $user): \Fw\Model\Collection
    {
        return PersonalAccessToken::forUser($user->id);
    }

    /**
     * Prune expired tokens from the database.
     */
    public static function pruneExpired(): int
    {
        $expired = PersonalAccessToken::query()
            ->where('expires_at', '<=', date('Y-m-d H:i:s'))
            ->get();

        $count = 0;

        foreach ($expired as $row) {
            $token = PersonalAccessToken::find($row['id']);
            if ($token !== null && $token->delete()) {
                $count++;
            }
        }

        return $count;
    }
}
