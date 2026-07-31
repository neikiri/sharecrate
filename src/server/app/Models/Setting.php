<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Key/value settings with an in-request cache and sane defaults.
 */
final class Setting
{
    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return [
            'site_name' => 'ShareCrate',
            'site_tagline' => '',
            'contact_email' => '',
            'timezone' => 'Europe/Prague',
            'privacy_ip_mode' => 'full',
            'log_retention_days' => '365',
            'alias_style' => 'slug',
            'alias_random_len' => '6',
            'allow_uploader_delete' => '1',
            'max_upload_mb' => '0',
            'default_expiry_days' => '0',
            'show_file_owner' => '0',
        ];
    }

    /** @return array<string, string|null> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = self::defaults();

        try {
            foreach (Database::all('SELECT setting_key, setting_value FROM settings') as $row) {
                $key = (string) $row['setting_key'];
                $value = $row['setting_value'];
                self::$cache[$key] = $value === null ? null : (string) $value;
            }
        } catch (\Throwable) {
            // Table not created yet (installer) - defaults are good enough.
        }

        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::all()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default ?? self::defaults()[$key] ?? null;
        }

        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null ? $default : in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null || !is_numeric($value) ? $default : (int) $value;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (:key, :value, :now)
             ON DUPLICATE KEY UPDATE setting_value = :value2, updated_at = :now2',
            [
                'key' => $key,
                'value' => $value,
                'value2' => $value,
                'now' => gmdate('Y-m-d H:i:s'),
                'now2' => gmdate('Y-m-d H:i:s'),
            ]
        );

        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    /** @param array<string, string|null> $values */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
