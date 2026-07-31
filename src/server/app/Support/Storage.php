<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use RuntimeException;

/**
 * Everything that touches the upload directory.
 *
 * The directory is the FTP drop target as well, so paths coming from disk are
 * treated as untrusted input and validated before use.
 */
final class Storage
{
    /** Files that are still being uploaded by an FTP client. */
    private const PARTIAL_SUFFIXES = ['.filepart', '.part', '.partial', '.crdownload', '.tmp', '.temp', '.!ut', '.st'];

    /** Ignored while scanning. */
    private const IGNORED_NAMES = ['.', '..', '.ftpquota', '.htaccess', '.gitkeep', 'Thumbs.db', '.DS_Store', 'desktop.ini'];

    /** A file must be untouched for this long before it is considered complete. */
    private const SETTLE_SECONDS = 5;

    public static function root(): string
    {
        $configured = (string) Config::get('STORAGE_PATH', 'storage/uploads');

        $isAbsolute = str_starts_with($configured, '/')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $configured) === 1;

        $path = $isAbsolute ? $configured : ROOT_PATH . '/' . ltrim($configured, '/');

        return rtrim(str_replace('\\', '/', $path), '/');
    }

    public static function ensure(): bool
    {
        $root = self::root();

        if (!is_dir($root)) {
            @mkdir($root, 0775, true);
        }

        return is_dir($root) && is_writable($root);
    }

    public static function writable(): bool
    {
        return is_dir(self::root()) && is_writable(self::root());
    }

    /**
     * Normalises a relative path and rejects traversal attempts.
     */
    public static function normalise(string $relative): string
    {
        $relative = str_replace('\\', '/', trim($relative));
        $relative = ltrim($relative, '/');
        $segments = [];

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new RuntimeException('Path traversal detected.');
            }

            if (preg_match('/[\x00-\x1F]/', $segment) === 1) {
                throw new RuntimeException('Invalid characters in path.');
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            throw new RuntimeException('Empty storage path.');
        }

        return implode('/', $segments);
    }

    /** Absolute path for a stored file. */
    public static function path(string $relative): string
    {
        return self::root() . '/' . self::normalise($relative);
    }

    public static function exists(string $relative): bool
    {
        try {
            return is_file(self::path($relative));
        } catch (\Throwable) {
            return false;
        }
    }

    public static function size(string $relative): ?int
    {
        try {
            $path = self::path($relative);

            if (!is_file($path)) {
                return null;
            }

            $size = @filesize($path);

            return $size === false ? null : $size;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function delete(string $relative): bool
    {
        try {
            $path = self::path($relative);

            if (!is_file($path)) {
                return false;
            }

            return @unlink($path);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Recursively lists relative paths of complete files in the storage root.
     *
     * @return array<int, array{path: string, name: string, size: int, modified: int}>
     */
    public static function scan(int $limit = 2000): array
    {
        $root = self::root();

        if (!is_dir($root)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (count($found) >= $limit) {
                break;
            }

            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }

            $name = $item->getFilename();

            if (self::isIgnored($name)) {
                continue;
            }

            $absolute = str_replace('\\', '/', $item->getPathname());
            $relative = ltrim(substr($absolute, strlen($root)), '/');

            if ($relative === '' || str_starts_with($relative, 'cache/')) {
                continue;
            }

            $modified = (int) $item->getMTime();

            if ($modified > time() - self::SETTLE_SECONDS) {
                // Probably still uploading.
                continue;
            }

            $found[] = [
                'path' => $relative,
                'name' => $name,
                'size' => (int) $item->getSize(),
                'modified' => $modified,
            ];
        }

        usort($found, static fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $found;
    }

    public static function isIgnored(string $name): bool
    {
        if (in_array($name, self::IGNORED_NAMES, true) || str_starts_with($name, '.')) {
            return true;
        }

        $lower = strtolower($name);

        foreach (self::PARTIAL_SUFFIXES as $suffix) {
            if (str_ends_with($lower, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitises a file name for on-disk storage while keeping it readable.
     */
    public static function safeName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
        $name = preg_replace('/[\/:*?"<>|]+/', '-', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name, " .-");

        if ($name === '') {
            $name = 'file-' . date('Ymd-His');
        }

        // Windows/Apache friendly length cap while keeping the extension.
        if (mb_strlen($name) > 180) {
            $extension = FileTypes::extension($name);
            $stem = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 170);
            $name = $stem . ($extension !== '' ? '.' . $extension : '');
        }

        return $name;
    }

    /**
     * Returns a relative path that does not exist yet.
     */
    public static function uniquePath(string $filename, string $directory = ''): string
    {
        $name = self::safeName($filename);
        $directory = trim(str_replace('\\', '/', $directory), '/');
        $extension = FileTypes::extension($name);
        $stem = $extension !== '' ? (string) mb_substr($name, 0, mb_strlen($name) - mb_strlen($extension) - 1) : $name;
        $suffix = $extension !== '' ? '.' . $extension : '';

        $candidate = ($directory !== '' ? $directory . '/' : '') . $stem . $suffix;
        $counter = 1;

        while (self::exists($candidate)) {
            $candidate = ($directory !== '' ? $directory . '/' : '') . $stem . '-' . $counter . $suffix;
            $counter++;

            if ($counter > 9999) {
                $candidate = ($directory !== '' ? $directory . '/' : '') . $stem . '-' . bin2hex(random_bytes(4)) . $suffix;

                break;
            }
        }

        return $candidate;
    }

    /**
     * Moves an uploaded file into storage and returns its relative path.
     *
     * @param array<string, mixed> $uploadedFile entry from $_FILES
     */
    public static function storeUpload(array $uploadedFile, string $directory = ''): string
    {
        if (!self::ensure()) {
            throw new RuntimeException('The storage directory is not writable.');
        }

        $tmp = (string) ($uploadedFile['tmp_name'] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }

        $relative = self::uniquePath((string) ($uploadedFile['name'] ?? 'file'), $directory);
        $target = self::path($relative);
        $targetDir = dirname($target);

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        if (!@move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Could not move the uploaded file into storage.');
        }

        @chmod($target, 0644);

        return $relative;
    }

    /** Cache directory for generated thumbnails. */
    public static function cachePath(string $sub = ''): string
    {
        $path = self::root() . '/cache' . ($sub !== '' ? '/' . trim($sub, '/') : '');

        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        return $path;
    }

    public static function totalUsed(): int
    {
        $total = 0;

        foreach (self::scan(100000) as $file) {
            $total += $file['size'];
        }

        return $total;
    }

    public static function freeSpace(): ?int
    {
        $free = @disk_free_space(self::root());

        return is_float($free) ? (int) $free : null;
    }

    /**
     * Largest file the current PHP configuration accepts through the web form.
     */
    public static function maxUploadBytes(): int
    {
        $candidates = [
            self::iniBytes((string) ini_get('upload_max_filesize')),
            self::iniBytes((string) ini_get('post_max_size')),
        ];

        $configured = (int) (\App\Models\Setting::get('max_upload_mb', '0') ?? '0');

        if ($configured > 0) {
            $candidates[] = $configured * 1024 * 1024;
        }

        $candidates = array_filter($candidates, static fn ($v) => $v > 0);

        return $candidates === [] ? 0 : (int) min($candidates);
    }

    public static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1' || $value === '0') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Human readable summary of the PHP upload limits (shown in the UI).
     *
     * @return array{upload_max: int, post_max: int, effective: int, execution_time: int}
     */
    public static function limits(): array
    {
        return [
            'upload_max' => self::iniBytes((string) ini_get('upload_max_filesize')),
            'post_max' => self::iniBytes((string) ini_get('post_max_size')),
            'effective' => self::maxUploadBytes(),
            'execution_time' => (int) ini_get('max_execution_time'),
        ];
    }
}
