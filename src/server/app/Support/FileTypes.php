<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Maps extensions to a category, an icon, a colour and a MIME type.
 * The category drives the icon tiles and the preview logic.
 */
final class FileTypes
{
    /** @var array<string, string[]> */
    private const CATEGORIES = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg', 'ico', 'tif', 'tiff', 'heic'],
        'video' => ['mp4', 'mkv', 'webm', 'mov', 'avi', 'wmv', 'flv', 'm4v', 'mpg', 'mpeg', 'ts'],
        'audio' => ['mp3', 'wav', 'flac', 'ogg', 'oga', 'm4a', 'aac', 'opus', 'wma', 'aiff'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'tgz', 'zst', 'iso', 'cab'],
        'document' => ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'md', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp', 'epub'],
        'code' => ['php', 'js', 'mjs', 'ts', 'tsx', 'jsx', 'json', 'xml', 'yml', 'yaml', 'html', 'htm', 'css', 'scss', 'sql', 'sh', 'bat', 'ps1', 'py', 'rb', 'go', 'rs', 'java', 'c', 'cpp', 'h', 'cs', 'toml', 'ini', 'env', 'log'],
        'font' => ['ttf', 'otf', 'woff', 'woff2', 'eot'],
        'app' => ['exe', 'msi', 'apk', 'dmg', 'deb', 'rpm', 'appimage', 'jar', 'bin'],
        'design' => ['psd', 'ai', 'xd', 'fig', 'sketch', 'blend', 'obj', 'fbx', 'stl', '3ds'],
    ];

    /** @var array<string, string> */
    private const MIME = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'bmp' => 'image/bmp', 'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'heic' => 'image/heic',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mkv' => 'video/x-matroska',
        'mov' => 'video/quicktime', 'avi' => 'video/x-msvideo', 'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac', 'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg', 'opus' => 'audio/ogg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'md' => 'text/markdown', 'csv' => 'text/csv',
        'json' => 'application/json', 'xml' => 'application/xml', 'html' => 'text/html', 'htm' => 'text/html',
        'css' => 'text/css', 'js' => 'text/javascript', 'mjs' => 'text/javascript',
        'zip' => 'application/zip', 'rar' => 'application/vnd.rar', '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar', 'gz' => 'application/gzip', 'tgz' => 'application/gzip',
        'bz2' => 'application/x-bzip2', 'xz' => 'application/x-xz', 'iso' => 'application/x-iso9660-image',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'epub' => 'application/epub+zip', 'rtf' => 'application/rtf',
        'exe' => 'application/vnd.microsoft.portable-executable', 'msi' => 'application/x-msdownload',
        'apk' => 'application/vnd.android.package-archive', 'dmg' => 'application/x-apple-diskimage',
        'deb' => 'application/vnd.debian.binary-package', 'rpm' => 'application/x-rpm',
        'jar' => 'application/java-archive',
        'ttf' => 'font/ttf', 'otf' => 'font/otf', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
        'sql' => 'application/sql', 'yml' => 'text/yaml', 'yaml' => 'text/yaml', 'log' => 'text/plain',
    ];

    /** @return array<string, string[]> */
    public static function extensionsByCategory(): array
    {
        return self::CATEGORIES;
    }

    /** @return string[] */
    public static function categories(): array
    {
        return [...array_keys(self::CATEGORIES), 'other'];
    }

    public static function extension(string $filename): string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,16}$/', $extension) === 1 ? $extension : '';
    }

    public static function category(string $extension): string
    {
        $extension = strtolower(ltrim($extension, '.'));

        foreach (self::CATEGORIES as $category => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $category;
            }
        }

        return 'other';
    }

    public static function categoryOf(string $filename): string
    {
        return self::category(self::extension($filename));
    }

    public static function mime(string $extension, ?string $fallback = null): string
    {
        $extension = strtolower(ltrim($extension, '.'));

        return self::MIME[$extension] ?? $fallback ?? 'application/octet-stream';
    }

    /**
     * Detects the MIME type from the file itself, falling back to the extension.
     */
    public static function detectMime(string $absolutePath, string $extension): string
    {
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);

            if ($finfo !== false) {
                $detected = @finfo_file($finfo, $absolutePath);
                finfo_close($finfo);

                if (is_string($detected) && $detected !== '' && $detected !== 'application/octet-stream') {
                    // Trust the extension for text-ish files, finfo is often too generic.
                    if (!str_starts_with($detected, 'text/') || !isset(self::MIME[$extension])) {
                        return $detected;
                    }
                }
            }
        }

        return self::mime($extension);
    }

    /** Tailwind classes for the coloured file tile. */
    public static function tileClasses(string $category): string
    {
        return match ($category) {
            'image' => 'bg-violet-50 text-violet-600 ring-1 ring-violet-100',
            'video' => 'bg-rose-50 text-rose-600 ring-1 ring-rose-100',
            'audio' => 'bg-amber-50 text-amber-600 ring-1 ring-amber-100',
            'archive' => 'bg-orange-50 text-orange-600 ring-1 ring-orange-100',
            'document' => 'bg-sky-50 text-sky-600 ring-1 ring-sky-100',
            'code' => 'bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100',
            'font' => 'bg-fuchsia-50 text-fuchsia-600 ring-1 ring-fuchsia-100',
            'app' => 'bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100',
            'design' => 'bg-teal-50 text-teal-600 ring-1 ring-teal-100',
            default => 'bg-slate-100 text-slate-500 ring-1 ring-slate-200',
        };
    }

    public static function icon(string $category): string
    {
        return match ($category) {
            'image' => 'image',
            'video' => 'video',
            'audio' => 'music',
            'archive' => 'archive',
            'document' => 'file-text',
            'code' => 'code',
            'font' => 'type',
            'app' => 'app-window',
            'design' => 'palette',
            default => 'file',
        };
    }

    public static function label(string $category): string
    {
        return \App\Core\I18n::t('filetype.' . $category);
    }

    /* ----------------------------------------------------------------
     * Preview capabilities
     * ---------------------------------------------------------------- */

    public static function isImage(string $extension): bool
    {
        return self::category($extension) === 'image';
    }

    public static function isThumbnailable(string $extension): bool
    {
        return in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    public static function isInlinePreviewable(string $extension): bool
    {
        return in_array(strtolower($extension), [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico',
            'pdf',
            'mp4', 'webm', 'm4v', 'mov',
            'mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'flac',
            'txt', 'md', 'csv', 'json', 'log',
        ], true);
    }

    public static function previewKind(string $extension): string
    {
        $extension = strtolower($extension);

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico'], true)) {
            return 'image';
        }

        if ($extension === 'pdf') {
            return 'pdf';
        }

        if (in_array($extension, ['mp4', 'webm', 'm4v', 'mov'], true)) {
            return 'video';
        }

        if (in_array($extension, ['mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'flac'], true)) {
            return 'audio';
        }

        if (in_array($extension, ['txt', 'md', 'csv', 'json', 'log'], true)) {
            return 'text';
        }

        return 'none';
    }

    /**
     * Extensions that must never be served inline - they would be a stored XSS
     * vector even from the same origin.
     */
    public static function forceAttachment(string $extension): bool
    {
        return in_array(strtolower($extension), ['html', 'htm', 'svg', 'xml', 'xhtml', 'js', 'mjs', 'php', 'phtml'], true);
    }
}
