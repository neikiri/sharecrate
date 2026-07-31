<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Database backed fixed window throttle for logins and file passwords.
 */
final class RateLimiter
{
    public static function key(string $action, string ...$parts): string
    {
        return substr($action . ':' . sha1(implode('|', $parts) . Config::appKey()), 0, 160);
    }

    public static function tooManyAttempts(string $bucket, int $maxAttempts): bool
    {
        return self::attempts($bucket) >= $maxAttempts;
    }

    public static function attempts(string $bucket): int
    {
        try {
            $row = Database::one(
                'SELECT attempts, reset_at FROM rate_limits WHERE bucket = :bucket LIMIT 1',
                ['bucket' => $bucket]
            );
        } catch (\Throwable) {
            return 0;
        }

        if ($row === null) {
            return 0;
        }

        if (strtotime((string) $row['reset_at']) < time()) {
            return 0;
        }

        return (int) $row['attempts'];
    }

    /**
     * Registers one attempt and returns the new counter value.
     */
    public static function hit(string $bucket, int $decaySeconds = 900): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $reset = gmdate('Y-m-d H:i:s', time() + $decaySeconds);

        try {
            Database::run(
                'INSERT INTO rate_limits (bucket, attempts, reset_at)
                 VALUES (:bucket, 1, :reset)
                 ON DUPLICATE KEY UPDATE
                    attempts = IF(reset_at < :now, 1, attempts + 1),
                    reset_at = IF(reset_at < :now2, :reset2, reset_at)',
                [
                    'bucket' => $bucket,
                    'reset' => $reset,
                    'reset2' => $reset,
                    'now' => $now,
                    'now2' => $now,
                ]
            );

            return self::attempts($bucket);
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function clear(string $bucket): void
    {
        try {
            Database::delete('rate_limits', ['bucket' => $bucket]);
        } catch (\Throwable) {
            // Throttling is best effort - never break the request over it.
        }
    }

    /** Seconds until the window resets. */
    public static function availableIn(string $bucket): int
    {
        try {
            $resetAt = Database::value(
                'SELECT reset_at FROM rate_limits WHERE bucket = :bucket LIMIT 1',
                ['bucket' => $bucket]
            );
        } catch (\Throwable) {
            return 0;
        }

        if (!is_string($resetAt)) {
            return 0;
        }

        return max(0, strtotime($resetAt) - time());
    }

    public static function purgeExpired(): int
    {
        try {
            return Database::run(
                'DELETE FROM rate_limits WHERE reset_at < :now',
                ['now' => gmdate('Y-m-d H:i:s')]
            )->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
