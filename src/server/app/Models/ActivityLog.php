<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

/**
 * Lightweight audit trail powering the "recent activity" panel.
 */
final class ActivityLog
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public static function record(string $action, ?string $subject = null, ?array $meta = null, ?int $userId = null): void
    {
        try {
            $ip = null;

            if (PHP_SAPI !== 'cli') {
                $mode = (string) (Setting::get('privacy_ip_mode', 'full') ?? 'full');
                $raw = Request::current()->ip();
                $ip = match ($mode) {
                    'none' => null,
                    'anonymised', 'anonymized' => \App\Core\Geo::anonymise($raw),
                    default => $raw,
                };
            }

            Database::insert('activity_log', [
                'user_id' => $userId ?? Auth::id(),
                'action' => mb_substr($action, 0, 60),
                'subject' => $subject === null ? null : mb_substr($subject, 0, 190),
                'meta' => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'ip' => $ip,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Never let logging break a request.
        }
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 12): array
    {
        try {
            return Database::all(
                'SELECT a.*, u.username, u.display_name
                 FROM activity_log a
                 LEFT JOIN users u ON u.id = a.user_id
                 ORDER BY a.created_at DESC, a.id DESC
                 LIMIT ' . max(1, min(100, $limit))
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function prune(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        try {
            return Database::run(
                'DELETE FROM activity_log WHERE created_at < :cutoff',
                ['cutoff' => gmdate('Y-m-d H:i:s', time() - $days * 86400)]
            )->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
