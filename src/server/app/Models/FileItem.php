<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\FileTypes;
use App\Support\Storage;

/**
 * A shared file. Rows are created either by a web upload or by importing
 * files that arrived over FTP.
 */
final class FileItem
{
    public const SORTABLE = [
        'created_at' => 'f.created_at',
        'name' => 'f.original_name',
        'size' => 'f.size_bytes',
        'downloads' => 'f.download_count',
        'last_download' => 'f.last_download_at',
        'alias' => 'f.alias',
    ];

    private const SELECT = 'f.*, u.username AS owner_username, u.display_name AS owner_display_name';

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT ' . self::SELECT . ' FROM files f LEFT JOIN users u ON u.id = f.owner_id WHERE f.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findByAlias(string $alias): ?array
    {
        return Database::one(
            'SELECT ' . self::SELECT . ' FROM files f LEFT JOIN users u ON u.id = f.owner_id WHERE f.alias = :alias LIMIT 1',
            ['alias' => $alias]
        );
    }

    /** @return array<string, mixed>|null */
    public static function findByPath(string $relativePath): ?array
    {
        return Database::one(
            'SELECT * FROM files WHERE path_hash = :hash LIMIT 1',
            ['hash' => self::pathHash($relativePath)]
        );
    }

    public static function pathHash(string $relativePath): string
    {
        return hash('sha256', str_replace('\\', '/', trim($relativePath, '/')));
    }

    /** @return array<string, bool> map of path_hash => true */
    public static function knownPathHashes(): array
    {
        $map = [];

        foreach (Database::all('SELECT path_hash FROM files') as $row) {
            $map[(string) $row['path_hash']] = true;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $path = str_replace('\\', '/', (string) $data['path']);
        $originalName = (string) ($data['original_name'] ?? basename($path));
        $extension = FileTypes::extension($originalName);

        $password = $data['password'] ?? null;
        $passwordHash = is_string($password) && $password !== ''
            ? password_hash($password, PASSWORD_DEFAULT)
            : ($data['password_hash'] ?? null);

        return Database::insert('files', [
            'alias' => (string) $data['alias'],
            'title' => self::nullable($data['title'] ?? null, 190),
            'description' => self::nullable($data['description'] ?? null, 5000),
            'original_name' => mb_substr($originalName, 0, 255),
            'path' => mb_substr($path, 0, 512),
            'path_hash' => self::pathHash($path),
            'extension' => $extension !== '' ? $extension : null,
            'mime_type' => self::nullable($data['mime_type'] ?? null, 160),
            'size_bytes' => (int) ($data['size_bytes'] ?? 0),
            'checksum' => $data['checksum'] ?? null,
            'password_hash' => $passwordHash,
            'owner_id' => $data['owner_id'] ?? null,
            'source' => in_array($data['source'] ?? 'web', ['web', 'ftp', 'cli'], true) ? $data['source'] : 'web',
            'status' => ($data['status'] ?? 'active') === 'disabled' ? 'disabled' : 'active',
            'allow_preview' => !empty($data['allow_preview']) ? 1 : 0,
            'expires_at' => $data['expires_at'] ?? null,
            'max_downloads' => isset($data['max_downloads']) && (int) $data['max_downloads'] > 0
                ? (int) $data['max_downloads']
                : null,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $data */
    public static function update(int $id, array $data): void
    {
        $allowed = [
            'alias', 'title', 'description', 'status', 'allow_preview',
            'expires_at', 'max_downloads', 'password_hash', 'owner_id',
            'size_bytes', 'mime_type', 'checksum', 'original_name',
        ];

        $payload = [];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $data[$key];
            }
        }

        if ($payload === []) {
            return;
        }

        $payload['updated_at'] = gmdate('Y-m-d H:i:s');
        Database::update('files', $payload, ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('files', ['id' => $id]);
    }

    public static function setPassword(int $id, ?string $plain): void
    {
        Database::update('files', [
            'password_hash' => $plain === null || $plain === '' ? null : password_hash($plain, PASSWORD_DEFAULT),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public static function registerDownload(int $id): void
    {
        Database::run(
            'UPDATE files SET download_count = download_count + 1, last_download_at = :now WHERE id = :id',
            ['now' => gmdate('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /* ----------------------------------------------------------------
     * State helpers
     * ---------------------------------------------------------------- */

    /** @param array<string, mixed> $file */
    public static function hasPassword(array $file): bool
    {
        return is_string($file['password_hash'] ?? null) && $file['password_hash'] !== '';
    }

    /** @param array<string, mixed> $file */
    public static function isExpired(array $file): bool
    {
        $expires = $file['expires_at'] ?? null;

        if (!is_string($expires) || $expires === '') {
            return false;
        }

        return strtotime($expires . ' UTC') < time();
    }

    /** @param array<string, mixed> $file */
    public static function limitReached(array $file): bool
    {
        $max = $file['max_downloads'] ?? null;

        if ($max === null || (int) $max <= 0) {
            return false;
        }

        return (int) $file['download_count'] >= (int) $max;
    }

    /** @param array<string, mixed> $file */
    public static function isAvailable(array $file): bool
    {
        return ($file['status'] ?? 'active') === 'active'
            && !self::isExpired($file)
            && !self::limitReached($file)
            && Storage::exists((string) $file['path']);
    }

    /**
     * One of: active, disabled, expired, limit, missing
     *
     * @param array<string, mixed> $file
     */
    public static function state(array $file): string
    {
        if (($file['status'] ?? 'active') !== 'active') {
            return 'disabled';
        }

        if (self::isExpired($file)) {
            return 'expired';
        }

        if (self::limitReached($file)) {
            return 'limit';
        }

        if (!Storage::exists((string) $file['path'])) {
            return 'missing';
        }

        return 'active';
    }

    /** @param array<string, mixed> $file */
    public static function displayName(array $file): string
    {
        $title = $file['title'] ?? null;

        return is_string($title) && trim($title) !== '' ? $title : (string) $file['original_name'];
    }

    /** @param array<string, mixed> $file */
    public static function category(array $file): string
    {
        return FileTypes::category((string) ($file['extension'] ?? ''));
    }

    /* ----------------------------------------------------------------
     * Listing
     * ---------------------------------------------------------------- */

    /**
     * @param array{q?: string, owner?: int|null, state?: string, category?: string,
     *              sort?: string, dir?: string, page?: int, per_page?: int} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int}
     */
    public static function paginate(array $filters = []): array
    {
        $where = [];
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(f.alias LIKE :q OR f.original_name LIKE :q2 OR f.title LIKE :q3 OR f.description LIKE :q4)';
            $like = '%' . $query . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        if (isset($filters['owner']) && $filters['owner'] !== null && $filters['owner'] !== '') {
            $where[] = 'f.owner_id = :owner';
            $params['owner'] = (int) $filters['owner'];
        }

        $state = (string) ($filters['state'] ?? '');
        $now = gmdate('Y-m-d H:i:s');

        if ($state === 'active') {
            $where[] = "f.status = 'active' AND (f.expires_at IS NULL OR f.expires_at > :now)"
                . ' AND (f.max_downloads IS NULL OR f.download_count < f.max_downloads)';
            $params['now'] = $now;
        } elseif ($state === 'disabled') {
            $where[] = "f.status = 'disabled'";
        } elseif ($state === 'expired') {
            $where[] = 'f.expires_at IS NOT NULL AND f.expires_at <= :now';
            $params['now'] = $now;
        } elseif ($state === 'protected') {
            $where[] = 'f.password_hash IS NOT NULL';
        } elseif ($state === 'limit') {
            $where[] = 'f.max_downloads IS NOT NULL AND f.download_count >= f.max_downloads';
        }

        $category = (string) ($filters['category'] ?? '');
        if ($category !== '') {
            $extensions = self::extensionsForCategory($category);

            if ($extensions !== []) {
                $placeholders = [];

                foreach ($extensions as $index => $extension) {
                    $key = 'ext' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $extension;
                }

                $where[] = 'f.extension IN (' . implode(', ', $placeholders) . ')';
            } elseif ($category === 'other') {
                $where[] = 'f.extension IS NULL';
            }
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::value(
            'SELECT COUNT(*) FROM files f' . $whereSql,
            $params
        );

        $perPage = max(5, min(100, (int) ($filters['per_page'] ?? 20)));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) ($filters['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;

        $sortKey = (string) ($filters['sort'] ?? 'created_at');
        $column = self::SORTABLE[$sortKey] ?? self::SORTABLE['created_at'];
        $direction = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        $rows = Database::all(
            'SELECT ' . self::SELECT . ' FROM files f
             LEFT JOIN users u ON u.id = f.owner_id'
            . $whereSql
            . " ORDER BY {$column} {$direction}, f.id DESC"
            . " LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /** @return string[] */
    private static function extensionsForCategory(string $category): array
    {
        return FileTypes::extensionsByCategory()[$category] ?? [];
    }

    /* ----------------------------------------------------------------
     * Statistics
     * ---------------------------------------------------------------- */

    /**
     * @return array{files: int, active: int, protected: int, bytes: int, downloads: int}
     */
    public static function stats(?int $ownerId = null): array
    {
        $where = $ownerId === null ? '' : ' WHERE owner_id = :owner';
        $params = $ownerId === null ? [] : ['owner' => $ownerId];

        $row = Database::one(
            'SELECT
                COUNT(*)                                   AS files,
                COALESCE(SUM(size_bytes), 0)               AS bytes,
                COALESCE(SUM(download_count), 0)           AS downloads,
                COALESCE(SUM(password_hash IS NOT NULL), 0) AS protected_files,
                COALESCE(SUM(status = \'active\'), 0)       AS active
             FROM files' . $where,
            $params
        ) ?? [];

        return [
            'files' => (int) ($row['files'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'protected' => (int) ($row['protected_files'] ?? 0),
            'bytes' => (int) ($row['bytes'] ?? 0),
            'downloads' => (int) ($row['downloads'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 6, ?int $ownerId = null): array
    {
        $where = $ownerId === null ? '' : ' WHERE f.owner_id = :owner';
        $params = $ownerId === null ? [] : ['owner' => $ownerId];

        return Database::all(
            'SELECT ' . self::SELECT . ' FROM files f
             LEFT JOIN users u ON u.id = f.owner_id'
            . $where
            . ' ORDER BY f.created_at DESC, f.id DESC LIMIT ' . max(1, min(50, $limit)),
            $params
        );
    }

    /** @return array<int, array<string, mixed>> */
    public static function mostDownloaded(int $limit = 6, ?int $ownerId = null): array
    {
        $where = ' WHERE f.download_count > 0';
        $params = [];

        if ($ownerId !== null) {
            $where .= ' AND f.owner_id = :owner';
            $params['owner'] = $ownerId;
        }

        return Database::all(
            'SELECT ' . self::SELECT . ' FROM files f
             LEFT JOIN users u ON u.id = f.owner_id'
            . $where
            . ' ORDER BY f.download_count DESC LIMIT ' . max(1, min(50, $limit)),
            $params
        );
    }

    /** Files whose payload disappeared from disk. @return array<int, array<string, mixed>> */
    public static function missingOnDisk(int $limit = 500): array
    {
        $missing = [];

        foreach (Database::all('SELECT * FROM files ORDER BY created_at DESC LIMIT ' . $limit) as $file) {
            if (!Storage::exists((string) $file['path'])) {
                $missing[] = $file;
            }
        }

        return $missing;
    }

    private static function nullable(mixed $value, int $max): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
