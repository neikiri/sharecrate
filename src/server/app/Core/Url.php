<?php

declare(strict_types=1);

namespace App\Core;

/**
 * URL builder that keeps APP_BASE_PATH in one place.
 */
final class Url
{
    /** @var array<string, string>|null */
    private static ?array $manifest = null;

    /**
     * Absolute path inside the site, e.g. Url::to('/admin/files') => "/admin/files".
     *
     * @param array<string, string|int|null> $query
     */
    public static function to(string $path = '/', array $query = []): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $path = '/' . ltrim($path, '/');
        $url = Config::basePath() . $path;
        $url = $url === '' ? '/' : $url;

        if ($query !== []) {
            $filtered = array_filter($query, static fn ($v) => $v !== null && $v !== '');

            if ($filtered !== []) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($filtered);
            }
        }

        return $url;
    }

    /**
     * Fully qualified URL (used for share links, canonical tags, e-mails).
     *
     * @param array<string, string|int|null> $query
     */
    public static function absolute(string $path = '/', array $query = []): string
    {
        $configured = Config::get('APP_URL');
        $root = $configured !== null
            ? rtrim($configured, '/')
            : Request::current()->root();

        // APP_URL already includes the base path.
        $relative = self::to($path, $query);
        $base = Config::basePath();

        if ($base !== '' && str_starts_with($relative, $base) && str_ends_with($root, $base)) {
            $relative = substr($relative, strlen($base));
        }

        return $root . ($relative === '/' ? '' : $relative);
    }

    /** Short public link for a file alias. */
    public static function file(string $alias, bool $absolute = true): string
    {
        $path = '/' . $alias;

        return $absolute ? self::absolute($path) : self::to($path);
    }

    public static function download(string $alias): string
    {
        return self::to('/d/' . $alias);
    }

    public static function preview(string $alias): string
    {
        return self::to('/p/' . $alias);
    }

    public static function thumbnail(string $alias): string
    {
        return self::to('/t/' . $alias);
    }

    /**
     * Resolves a built asset through assets/manifest.json.
     */
    public static function asset(string $name): string
    {
        $manifest = self::manifest();
        $file = $manifest[$name] ?? null;

        if ($file === null) {
            return self::to('/assets/' . ltrim($name, '/'));
        }

        return self::to('/' . ltrim($file, '/'));
    }

    /** @return array<string, string> */
    public static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        self::$manifest = [];
        $path = ROOT_PATH . '/assets/manifest.json';

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (is_array($decoded)) {
                /** @var array<string, string> $decoded */
                self::$manifest = $decoded;
            }
        }

        return self::$manifest;
    }

    /** All stylesheet URLs produced by the build. @return string[] */
    public static function styles(): array
    {
        $manifest = self::manifest();
        $files = $manifest['css'] ?? '';
        $list = is_string($files) ? array_filter(explode(',', $files)) : [];

        return array_map(static fn ($f) => self::to('/' . ltrim(trim($f), '/')), $list);
    }

    /** Main JS bundle URL. */
    public static function script(): ?string
    {
        $manifest = self::manifest();
        $file = $manifest['js'] ?? null;

        return is_string($file) && $file !== '' ? self::to('/' . ltrim($file, '/')) : null;
    }
}
