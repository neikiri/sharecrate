<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public const ROLES = ['admin', 'uploader'];

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public static function findByLogin(string $login): ?array
    {
        return Database::one(
            'SELECT * FROM users WHERE username = :login OR email = :login2 LIMIT 1',
            ['login' => $login, 'login2' => $login]
        );
    }

    public static function usernameTaken(string $username, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = :username';
        $params = ['username' => $username];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        return (int) Database::value($sql, $params) > 0;
    }

    public static function emailTaken(string $email, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        return (int) Database::value($sql, $params) > 0;
    }

    /**
     * @param array{username: string, email: string, password: string, role?: string,
     *              display_name?: string|null, locale?: string|null, is_active?: bool,
     *              quota_bytes?: int|null} $data
     */
    public static function create(array $data): int
    {
        return Database::insert('users', [
            'username' => $data['username'],
            'email' => $data['email'],
            'display_name' => $data['display_name'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => in_array($data['role'] ?? 'uploader', self::ROLES, true) ? ($data['role'] ?? 'uploader') : 'uploader',
            'locale' => $data['locale'] ?? null,
            'is_active' => ($data['is_active'] ?? true) ? 1 : 0,
            'quota_bytes' => $data['quota_bytes'] ?? null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        $allowed = ['username', 'email', 'display_name', 'role', 'locale', 'is_active', 'quota_bytes'];
        $payload = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $data[$key];
            }
        }

        if (isset($data['password']) && is_string($data['password']) && $data['password'] !== '') {
            $payload['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if ($payload === []) {
            return;
        }

        $payload['updated_at'] = gmdate('Y-m-d H:i:s');
        Database::update('users', $payload, ['id' => $id]);
    }

    /** Direct column write, used for login bookkeeping. @param array<string, mixed> $data */
    public static function updateRaw(int $id, array $data): void
    {
        Database::update('users', $data, ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('users', ['id' => $id]);
    }

    /**
     * All users with their file statistics.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allWithStats(): array
    {
        return Database::all(
            'SELECT u.*,
                    COALESCE(f.file_count, 0)  AS file_count,
                    COALESCE(f.total_bytes, 0) AS total_bytes,
                    COALESCE(f.downloads, 0)   AS downloads
             FROM users u
             LEFT JOIN (
                 SELECT owner_id,
                        COUNT(*)              AS file_count,
                        SUM(size_bytes)       AS total_bytes,
                        SUM(download_count)   AS downloads
                 FROM files
                 GROUP BY owner_id
             ) f ON f.owner_id = u.id
             ORDER BY u.role ASC, u.username ASC'
        );
    }

    /** @return array<int, array{id: int, username: string}> */
    public static function options(): array
    {
        /** @var array<int, array{id: int, username: string}> $rows */
        $rows = Database::all('SELECT id, username FROM users ORDER BY username ASC');

        return $rows;
    }

    public static function count(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM users');
    }

    public static function adminCount(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
    }

    public static function storageUsed(int $userId): int
    {
        return (int) Database::value(
            'SELECT COALESCE(SUM(size_bytes), 0) FROM files WHERE owner_id = :id',
            ['id' => $userId]
        );
    }

    /** @param array<string, mixed> $user */
    public static function name(array $user): string
    {
        $display = $user['display_name'] ?? null;

        return is_string($display) && trim($display) !== '' ? $display : (string) $user['username'];
    }

    /** Initials for the avatar bubble. @param array<string, mixed> $user */
    public static function initials(array $user): string
    {
        $name = self::name($user);
        $parts = preg_split('/[\s._-]+/', $name) ?: [];
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials .= mb_strtoupper(mb_substr($part, 0, 1));

            if (mb_strlen($initials) >= 2) {
                break;
            }
        }

        return $initials === '' ? '?' : $initials;
    }
}
