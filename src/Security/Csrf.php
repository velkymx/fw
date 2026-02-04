<?php

declare(strict_types=1);

namespace Fw\Security;

use Closure;

final class Csrf
{
    private const string SESSION_KEY = '_csrf_token';

    /**
     * Number of random bytes for token generation.
     * Results in 64-character hex string (256 bits of entropy).
     */
    private const int TOKEN_BYTES = 32;

    /**
     * The form field name for CSRF tokens.
     * Use this constant when manually checking for tokens.
     */
    public const string FIELD_NAME = '_csrf_token';

    /**
     * The header name for CSRF tokens (lowercase).
     */
    public const string HEADER_NAME = 'x-csrf-token';

    /**
     * Callable that initializes the session when needed.
     * @var Closure(): void
     */
    private Closure $sessionInitializer;

    /**
     * @param Closure(): void $sessionInitializer Callable that starts the session
     */
    public function __construct(Closure $sessionInitializer)
    {
        $this->sessionInitializer = $sessionInitializer;
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (headers_sent($file, $line)) {
                // Log the failure for debugging - CSRF will fail silently
                error_log(
                    "CSRF Warning: Cannot initialize session - headers already sent " .
                    "(output started at {$file}:{$line}). CSRF validation will fail."
                );
                return;
            }
            ($this->sessionInitializer)();
        }
    }

    public function getToken(): string
    {
        $this->ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = $this->generateToken();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public function regenerateToken(): string
    {
        // Ensure session is started before accessing $_SESSION
        $this->ensureSession();

        $_SESSION[self::SESSION_KEY] = $this->generateToken();
        return $_SESSION[self::SESSION_KEY];
    }

    public function validate(?string $token): bool
    {
        $this->ensureSession();

        if ($token === null || !isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Validate CSRF token from request data.
     *
     * Checks POST field first, then headers (case-insensitive).
     *
     * @param array<string, mixed> $post POST data
     * @param array<string, string> $headers Headers array (any case format accepted)
     */
    public function validateRequest(array $post, array $headers = []): bool
    {
        // Check POST field first
        if (isset($post[self::FIELD_NAME])) {
            return $this->validate($post[self::FIELD_NAME]);
        }

        // Normalize headers to lowercase for case-insensitive lookup
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower($name)] = $value;
        }

        // Check header (stored as lowercase 'x-csrf-token')
        $token = $normalizedHeaders[self::HEADER_NAME] ?? null;

        return $this->validate($token);
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    public function formField(): string
    {
        $token = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::FIELD_NAME . '" value="' . $token . '">';
    }

    public function metaTag(): string
    {
        $token = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');
        return '<meta name="csrf-token" content="' . $token . '">';
    }
}
