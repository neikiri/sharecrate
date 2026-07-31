<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Reads the .env file from the document root and exposes typed getters.
 */
final class Config
{
    /** @var array<string, string> */
    private static array $items = [];

    private static bool $loaded = false;

    private static string $path = '';

    public static function load(string $path): void
    {
        self::$path = $path;
        self::$items = self::defaults();

        if (is_file($path) && is_readable($path)) {
            self::$items = array_merge(self::$items, self::parse((string) file_get_contents($path)));
        }

        self::$loaded = true;
    }

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return [
            'APP_NAME' => 'ShareCrate',
            'APP_URL' => '',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_BASE_PATH' => '',
            'APP_KEY' => '',
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_NAME' => '',
            'DB_USER' => '',
            'DB_PASS' => '',
            'DB_CHARSET' => 'utf8mb4',
            'STORAGE_PATH' => 'storage/uploads',
            'DEFAULT_LOCALE' => 'en',
            'CZECH_COUNTRIES' => 'CZ,SK',
            'GEOIP_PROVIDER' => 'auto',
            'TRUSTED_PROXIES' => '',
            'SESSION_NAME' => 'sharecrate_sid',
            'SESSION_LIFETIME' => '7200',
        ];
    }

    /** @return array<string, string> */
    public static function parse(string $contents): array
    {
        $result = [];
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '' || !preg_match('/^[A-Z0-9_]+$/i', $key)) {
                continue;
            }

            $quoted = false;
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                    $quoted = true;
                }
            }

            // Strip trailing comments from unquoted values.
            if (!$quoted && preg_match('/^(.*?)\s+#.*$/', $value, $m) === 1) {
                $value = rtrim($m[1]);
            }

            $result[strtoupper($key)] = $value;
        }

        return $result;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::$items[strtoupper($key)] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    public static function set(string $key, string $value): void
    {
        self::$items[strtoupper($key)] = $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null ? $default : (int) $value;
    }

    /** @return string[] */
    public static function list(string $key): array
    {
        $value = (string) self::get($key, '');

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($v) => $v !== ''));
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    public static function path(): string
    {
        return self::$path;
    }

    public static function exists(): bool
    {
        return is_file(self::$path);
    }

    /**
     * True once the .env holds everything needed to boot the app.
     */
    public static function isConfigured(): bool
    {
        return self::exists()
            && self::get('DB_NAME') !== null
            && self::get('DB_USER') !== null
            && self::get('APP_KEY') !== null;
    }

    public static function debug(): bool
    {
        return self::bool('APP_DEBUG', false);
    }

    public static function appKey(): string
    {
        return (string) self::get('APP_KEY', 'insecure-fallback-key');
    }

    public static function basePath(): string
    {
        $base = trim((string) self::get('APP_BASE_PATH', ''));

        if ($base === '' || $base === '/') {
            return '';
        }

        return '/' . trim($base, '/');
    }

    /**
     * Renders a complete .env file. Used by the installer.
     *
     * @param array<string, string> $values
     */
    public static function render(array $values): string
    {
        $groups = [
            'Application' => ['APP_NAME', 'APP_URL', 'APP_ENV', 'APP_DEBUG', 'APP_BASE_PATH', 'APP_KEY'],
            'Database' => ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET'],
            'Storage' => ['STORAGE_PATH'],
            'Localisation' => ['DEFAULT_LOCALE', 'CZECH_COUNTRIES', 'GEOIP_PROVIDER'],
            'Networking' => ['TRUSTED_PROXIES'],
            'Session' => ['SESSION_NAME', 'SESSION_LIFETIME'],
        ];

        $merged = array_merge(self::defaults(), $values);
        $out = "# Generated by the ShareCrate installer on " . gmdate('Y-m-d H:i') . " UTC\n";

        foreach ($groups as $label => $keys) {
            $out .= "\n# --- {$label} " . str_repeat('-', max(1, 60 - strlen($label))) . "\n";

            foreach ($keys as $key) {
                $value = (string) ($merged[$key] ?? '');
                $needsQuotes = $value === '' ? false : (bool) preg_match('/[\s#"\']/', $value);
                $escaped = str_replace(['\\', '"'], ['\\\\', '\"'], $value);
                $out .= $key . '=' . ($needsQuotes ? '"' . $escaped . '"' : $value) . "\n";
            }
        }

        return $out;
    }
}
