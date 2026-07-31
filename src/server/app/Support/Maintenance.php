<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use App\Core\RateLimiter;
use App\Models\ActivityLog;
use App\Models\Download;
use App\Models\Setting;

/**
 * Housekeeping. Runs occasionally during normal requests so the app stays
 * tidy even without a cron job (bin/cleanup.php does the same on demand).
 */
final class Maintenance
{
    private const PROBABILITY = 40; // roughly every 40th request

    private const MIN_INTERVAL = 3600;

    public static function maybeRun(): void
    {
        try {
            if (random_int(1, self::PROBABILITY) !== 1) {
                return;
            }

            $last = (int) (Setting::get('last_maintenance_at', '0') ?? '0');

            if ($last > time() - self::MIN_INTERVAL) {
                return;
            }

            self::run();
        } catch (\Throwable) {
            // Maintenance is opportunistic.
        }
    }

    /**
     * @return array<string, int>
     */
    public static function run(): array
    {
        $report = [
            'rate_limits' => 0,
            'remember_tokens' => 0,
            'geo_cache' => 0,
            'downloads' => 0,
            'activity' => 0,
            'thumbnails' => 0,
        ];

        try {
            $report['rate_limits'] = RateLimiter::purgeExpired();
        } catch (\Throwable) {
        }

        try {
            $report['remember_tokens'] = Database::run(
                'DELETE FROM remember_tokens WHERE expires_at < :now',
                ['now' => gmdate('Y-m-d H:i:s')]
            )->rowCount();
        } catch (\Throwable) {
        }

        try {
            $report['geo_cache'] = Database::run(
                'DELETE FROM geo_cache WHERE created_at < :cutoff',
                ['cutoff' => gmdate('Y-m-d H:i:s', time() - 60 * 86400)]
            )->rowCount();
        } catch (\Throwable) {
        }

        $retention = Setting::int('log_retention_days', 365);

        if ($retention > 0) {
            $report['downloads'] = Download::prune($retention);
            $report['activity'] = ActivityLog::prune($retention);
        }

        try {
            $report['thumbnails'] = Thumbnailer::pruneOrphans();
        } catch (\Throwable) {
        }

        try {
            Setting::set('last_maintenance_at', (string) time());
        } catch (\Throwable) {
        }

        return $report;
    }
}
