<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hardened session wrapper plus flash messages and old input.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        if (headers_sent()) {
            return;
        }

        $lifetime = max(300, Config::int('SESSION_LIFETIME', 7200));

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '5');

        session_name((string) Config::get('SESSION_NAME', 'sharecrate_sid'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => (Config::basePath() === '' ? '/' : Config::basePath() . '/'),
            'domain' => '',
            'secure' => Request::current()->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        self::$started = true;

        $now = time();
        $last = (int) ($_SESSION['_last_activity'] ?? $now);

        if ($now - $last > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
        }

        $_SESSION['_last_activity'] = $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_destroy();
        self::$started = false;
    }

    /* ----------------------------------------------------------------
     * Flash messages
     * ---------------------------------------------------------------- */

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type: string, message: string}> */
    public static function flashes(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($messages) ? $messages : [];
    }

    /* ----------------------------------------------------------------
     * Old input + validation errors
     * ---------------------------------------------------------------- */

    /** @param array<string, mixed> $input */
    public static function flashInput(array $input): void
    {
        self::start();
        unset($input['password'], $input['password_confirmation'], $input['_token']);
        $_SESSION['_old'] = $input;
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        $old = $_SESSION['_old'] ?? [];

        return is_array($old) ? ($old[$key] ?? $default) : $default;
    }

    /** @param array<string, string> $errors */
    public static function flashErrors(array $errors): void
    {
        self::start();
        $_SESSION['_errors'] = $errors;
    }

    /** @return array<string, string> */
    public static function errors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];

        return is_array($errors) ? $errors : [];
    }

    /**
     * Clears one-request-only data. Called at the end of a rendered page.
     */
    public static function clearTransient(): void
    {
        unset($_SESSION['_old'], $_SESSION['_errors']);
    }
}
