<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Session based authentication with optional long lived "remember me" cookie.
 */
final class Auth
{
    public const REMEMBER_COOKIE = 'dl_remember';

    private const REMEMBER_DAYS = 30;

    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    private static bool $resolved = false;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        Session::start();

        $id = Session::get('_user_id');

        if (is_numeric($id)) {
            $user = User::find((int) $id);

            if ($user !== null && (int) $user['is_active'] === 1) {
                self::$user = $user;

                return self::$user;
            }

            Session::forget('_user_id');
        }

        self::$user = self::fromRememberCookie();

        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function isAdmin(): bool
    {
        $user = self::user();

        return $user !== null && $user['role'] === 'admin';
    }

    public static function attempt(string $login, string $password, bool $remember = false): bool
    {
        $user = User::findByLogin($login);

        if ($user === null) {
            // Constant-ish time behaviour for unknown accounts.
            password_verify($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30M1RwEFxK1P.');

            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        if ((int) $user['is_active'] !== 1) {
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            User::updateRaw((int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        self::login($user, $remember);

        return true;
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user, bool $remember = false): void
    {
        Session::start();
        Session::regenerate();
        Csrf::rotate();

        Session::put('_user_id', (int) $user['id']);
        self::$user = $user;
        self::$resolved = true;

        $request = Request::current();
        User::updateRaw((int) $user['id'], [
            'last_login_at' => gmdate('Y-m-d H:i:s'),
            'last_login_ip' => $request->ip(),
        ]);

        if ($remember) {
            self::issueRememberToken((int) $user['id']);
        }

        ActivityLog::record('user.login', (string) $user['username'], null, (int) $user['id']);
    }

    public static function logout(): void
    {
        $userId = self::id();
        self::clearRememberCookie($userId);

        if ($userId !== null) {
            ActivityLog::record('user.logout', null, null, $userId);
        }

        self::$user = null;
        self::$resolved = true;
        Session::destroy();
    }

    /**
     * Aborts with a redirect to the login screen when nobody is signed in.
     *
     * @return array<string, mixed>
     */
    public static function requireUser(): array
    {
        $user = self::user();

        if ($user !== null) {
            return $user;
        }

        $request = Request::current();

        if ($request->wantsJson()) {
            Response::json(['ok' => false, 'message' => I18n::t('auth.required')], 401);
        }

        Session::put('_intended', $request->fullUrl());
        Session::flash('info', I18n::t('auth.required'));

        Response::redirect(Url::to('/admin/login'));
    }

    /** @return array<string, mixed> */
    public static function requireAdmin(): array
    {
        $user = self::requireUser();

        if ($user['role'] !== 'admin') {
            throw HttpException::forbidden('errors.admin_only');
        }

        return $user;
    }

    public static function intended(string $default = '/admin'): string
    {
        $intended = Session::pull('_intended');

        if (is_string($intended) && $intended !== '') {
            $root = Request::current()->root();

            if (str_starts_with($intended, $root)) {
                return $intended;
            }
        }

        return Url::to($default);
    }

    /* ----------------------------------------------------------------
     * Remember me
     * ---------------------------------------------------------------- */

    private static function issueRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + self::REMEMBER_DAYS * 86400;

        try {
            Database::insert('remember_tokens', [
                'user_id' => $userId,
                'selector' => $selector,
                'validator_hash' => hash('sha256', $validator),
                'user_agent' => Request::current()->userAgent(),
                'expires_at' => gmdate('Y-m-d H:i:s', $expires),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            return;
        }

        if (headers_sent()) {
            return;
        }

        setcookie(self::REMEMBER_COOKIE, $selector . ':' . $validator, [
            'expires' => $expires,
            'path' => Config::basePath() === '' ? '/' : Config::basePath() . '/',
            'secure' => Request::current()->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** @return array<string, mixed>|null */
    private static function fromRememberCookie(): ?array
    {
        $cookie = Request::current()->cookies[self::REMEMBER_COOKIE] ?? null;

        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $cookie, 2);

        if (strlen($selector) !== 32 || strlen($validator) !== 64) {
            return null;
        }

        try {
            $row = Database::one(
                'SELECT * FROM remember_tokens WHERE selector = :selector LIMIT 1',
                ['selector' => $selector]
            );
        } catch (\Throwable) {
            return null;
        }

        if ($row === null) {
            self::clearRememberCookie();

            return null;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            Database::delete('remember_tokens', ['id' => (int) $row['id']]);
            self::clearRememberCookie();

            return null;
        }

        if (!hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
            // Possible theft - drop every token for this user.
            Database::delete('remember_tokens', ['user_id' => (int) $row['user_id']]);
            self::clearRememberCookie();

            return null;
        }

        $user = User::find((int) $row['user_id']);

        if ($user === null || (int) $user['is_active'] !== 1) {
            return null;
        }

        Session::start();
        Session::put('_user_id', (int) $user['id']);

        return $user;
    }

    private static function clearRememberCookie(?int $userId = null): void
    {
        $cookie = Request::current()->cookies[self::REMEMBER_COOKIE] ?? null;

        if (is_string($cookie) && str_contains($cookie, ':')) {
            [$selector] = explode(':', $cookie, 2);

            try {
                Database::delete('remember_tokens', ['selector' => $selector]);
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($userId !== null) {
            try {
                Database::run(
                    'DELETE FROM remember_tokens WHERE user_id = :user AND expires_at < :now',
                    ['user' => $userId, 'now' => gmdate('Y-m-d H:i:s')]
                );
            } catch (\Throwable) {
                // ignore
            }
        }

        if (!headers_sent()) {
            setcookie(self::REMEMBER_COOKIE, '', [
                'expires' => time() - 3600,
                'path' => Config::basePath() === '' ? '/' : Config::basePath() . '/',
                'secure' => Request::current()->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}
