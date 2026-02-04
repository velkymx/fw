<?php

declare(strict_types=1);

namespace Fw\Auth;

use Fw\Database\Connection;

final class PasswordReset
{
    private const string TABLE = 'password_resets';
    private const int TOKEN_EXPIRY = 3600; // 1 hour

    /**
     * The database connection (set during bootstrap via setConnection()).
     */
    private static ?Connection $connection = null;

    /**
     * Set the database connection for password resets.
     */
    public static function setConnection(Connection $connection): void
    {
        self::$connection = $connection;
    }

    public static function createToken(string $email): string
    {
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        $db = self::db();

        // Delete any existing tokens for this email
        $db->query(
            "DELETE FROM \"" . self::TABLE . "\" WHERE email = ?",
            [$email]
        );

        // Insert new token
        $db->insert(self::TABLE, [
            'email' => $email,
            'token' => $hashedToken,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    public static function findByToken(string $token): ?array
    {
        $hashedToken = hash('sha256', $token);

        $result = self::db()->selectOne(
            "SELECT * FROM \"" . self::TABLE . "\" WHERE token = ?",
            [$hashedToken]
        );

        if ($result === null) {
            return null;
        }

        // Check if token is expired
        $createdAt = strtotime($result['created_at']);

        if (time() - $createdAt > self::TOKEN_EXPIRY) {
            self::deleteToken($token);
            return null;
        }

        return $result;
    }

    public static function deleteToken(string $token): void
    {
        $hashedToken = hash('sha256', $token);

        self::db()->query(
            "DELETE FROM \"" . self::TABLE . "\" WHERE token = ?",
            [$hashedToken]
        );
    }

    public static function deleteExpired(): int
    {
        $expiry = date('Y-m-d H:i:s', time() - self::TOKEN_EXPIRY);

        return self::db()->query(
            "DELETE FROM \"" . self::TABLE . "\" WHERE created_at < ?",
            [$expiry]
        )->rowCount();
    }

    private static function db(): Connection
    {
        return self::$connection
            ?? throw new \RuntimeException(
                'No database connection set. Call PasswordReset::setConnection() during application bootstrap.'
            );
    }
}
