<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Geo;
use App\Core\Request;
use App\Support\UserAgent;

/**
 * Download log - who downloaded what and when.
 */
final class Download
{
    /**
     * Writes one log row and bumps the counter on the file.
     */
    public static function record(int $fileId, ?int $bytesSent = null): void
    {
        $request = Request::current();
        $ip = $request->ip();
        $agent = $request->userAgent();
        $parsed = UserAgent::parse($agent);
        $geo = Geo::lookup($ip, $request);

        $mode = (string) (Setting::get('privacy_ip_mode', 'full') ?? 'full');
        $storedIp = match ($mode) {
            'none' => null,
            'anonymised', 'anonymized' => Geo::anonymise($ip),
            default => $ip,
        };

        try {
            Database::insert('downloads', [
                'file_id' => $fileId,
                'ip' => $storedIp,
                'ip_hash' => Geo::hash($ip),
                'country' => $geo['country'],
                'city' => $geo['city'],
                'user_agent' => $agent,
                'browser' => $parsed['browser'],
                'platform' => $parsed['platform'],
                'referer' => $request->referer(),
                'bytes_sent' => $bytesSent,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);

            FileItem::registerDownload($fileId);
        } catch (\Throwable) {
            // A logging failure must not break the download itself.
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function forFile(int $fileId, int $limit = 25): array
    {
        return Database::all(
            'SELECT * FROM downloads WHERE file_id = :file ORDER BY created_at DESC, id DESC LIMIT ' . max(1, min(500, $limit)),
            ['file' => $fileId]
        );
    }

    /**
     * Builds the FROM/WHERE fragment shared by the aggregate queries.
     *
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private static function scope(?int $fileId, ?int $ownerId, ?string $since = null): array
    {
        $from = 'FROM downloads d';
        $conditions = [];
        $params = [];

        if ($ownerId !== null) {
            $from .= ' JOIN files f ON f.id = d.file_id';
            $conditions[] = 'f.owner_id = :owner';
            $params['owner'] = $ownerId;
        }

        if ($fileId !== null) {
            $conditions[] = 'd.file_id = :file';
            $params['file'] = $fileId;
        }

        if ($since !== null) {
            $conditions[] = 'd.created_at >= :since';
            $params['since'] = $since;
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$from, $where, $params];
    }

    public static function total(?int $fileId = null, ?int $ownerId = null): int
    {
        [$from, $where, $params] = self::scope($fileId, $ownerId);

        return (int) Database::value("SELECT COUNT(*) {$from}{$where}", $params);
    }

    public static function uniqueVisitors(?int $fileId = null, ?int $ownerId = null): int
    {
        [$from, $where, $params] = self::scope($fileId, $ownerId);

        return (int) Database::value("SELECT COUNT(DISTINCT d.ip_hash) {$from}{$where}", $params);
    }

    public static function sinceCount(int $days, ?int $fileId = null, ?int $ownerId = null): int
    {
        [$from, $where, $params] = self::scope(
            $fileId,
            $ownerId,
            gmdate('Y-m-d H:i:s', time() - $days * 86400)
        );

        return (int) Database::value("SELECT COUNT(*) {$from}{$where}", $params);
    }

    /**
     * Daily totals for the sparkline / bar chart.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    public static function perDay(int $days = 30, ?int $fileId = null, ?int $ownerId = null): array
    {
        [$from, $where, $params] = self::scope(
            $fileId,
            $ownerId,
            gmdate('Y-m-d 00:00:00', time() - ($days - 1) * 86400)
        );

        $sql = "SELECT DATE(d.created_at) AS day, COUNT(*) AS total {$from}{$where} GROUP BY DATE(d.created_at)";

        $counts = [];

        foreach (Database::all($sql, $params) as $row) {
            $counts[(string) $row['day']] = (int) $row['total'];
        }

        $series = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', time() - $i * 86400);
            $series[] = [
                'date' => $day,
                'label' => gmdate('j.n.', strtotime($day) ?: time()),
                'count' => $counts[$day] ?? 0,
            ];
        }

        return $series;
    }

    /**
     * @return array<int, array{country: ?string, total: int}>
     */
    public static function byCountry(int $limit = 8, ?int $fileId = null, ?int $ownerId = null): array
    {
        [$from, $where, $params] = self::scope($fileId, $ownerId);

        $sql = "SELECT d.country AS country, COUNT(*) AS total {$from}{$where}"
            . ' GROUP BY d.country ORDER BY total DESC LIMIT ' . max(1, min(50, $limit));

        $out = [];

        foreach (Database::all($sql, $params) as $row) {
            $out[] = [
                'country' => is_string($row['country']) && $row['country'] !== '' ? $row['country'] : null,
                'total' => (int) $row['total'],
            ];
        }

        return $out;
    }

    /**
     * Paginated global log with filters.
     *
     * @param array{file?: int|null, country?: string, q?: string, page?: int, per_page?: int, days?: int} $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, per_page: int}
     */
    public static function paginate(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['file'])) {
            $where[] = 'd.file_id = :file';
            $params['file'] = (int) $filters['file'];
        }

        $country = strtoupper(trim((string) ($filters['country'] ?? '')));
        if ($country !== '') {
            $where[] = 'd.country = :country';
            $params['country'] = $country;
        }

        $days = (int) ($filters['days'] ?? 0);
        if ($days > 0) {
            $where[] = 'd.created_at >= :since';
            $params['since'] = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(f.alias LIKE :q OR f.original_name LIKE :q2 OR d.ip LIKE :q3)';
            $like = '%' . $query . '%';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        if (!empty($filters['owner'])) {
            $where[] = 'f.owner_id = :owner';
            $params['owner'] = (int) $filters['owner'];
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::value(
            'SELECT COUNT(*) FROM downloads d JOIN files f ON f.id = d.file_id' . $whereSql,
            $params
        );

        $perPage = max(10, min(200, (int) ($filters['per_page'] ?? 40)));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) ($filters['page'] ?? 1)));
        $offset = ($page - 1) * $perPage;

        $rows = Database::all(
            'SELECT d.*, f.alias, f.original_name, f.extension, f.owner_id
             FROM downloads d
             JOIN files f ON f.id = d.file_id'
            . $whereSql
            . ' ORDER BY d.created_at DESC, d.id DESC'
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

    /**
     * Rows for the CSV export (capped so the export never runs out of memory).
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function forExport(array $filters = [], int $limit = 10000): array
    {
        $result = self::paginate(array_merge($filters, ['page' => 1, 'per_page' => 200]));
        $rows = [];
        $pages = min($result['pages'], (int) ceil($limit / 200));

        for ($page = 1; $page <= $pages; $page++) {
            $chunk = self::paginate(array_merge($filters, ['page' => $page, 'per_page' => 200]));
            $rows = array_merge($rows, $chunk['rows']);

            if (count($rows) >= $limit) {
                break;
            }
        }

        return array_slice($rows, 0, $limit);
    }

    public static function prune(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        try {
            return Database::run(
                'DELETE FROM downloads WHERE created_at < :cutoff',
                ['cutoff' => gmdate('Y-m-d H:i:s', time() - $days * 86400)]
            )->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
