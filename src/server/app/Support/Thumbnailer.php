<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generates cached thumbnails for image files using GD.
 * Falls back gracefully when the extension is missing.
 */
final class Thumbnailer
{
    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatetruecolor');
    }

    /**
     * @param array<string, mixed> $file
     * @return array{path: string, mime: string}|null
     */
    public static function get(array $file, int $box = 640): ?array
    {
        $extension = strtolower((string) ($file['extension'] ?? ''));

        if (!self::available() || !FileTypes::isThumbnailable($extension)) {
            return null;
        }

        $relative = (string) $file['path'];

        if (!Storage::exists($relative)) {
            return null;
        }

        $source = Storage::path($relative);
        $box = max(80, min(1600, $box));
        $useWebp = function_exists('imagewebp');
        $target = Storage::cachePath('thumbs') . '/' . (int) $file['id'] . '-' . $box . ($useWebp ? '.webp' : '.jpg');
        $mime = $useWebp ? 'image/webp' : 'image/jpeg';

        if (is_file($target) && filemtime($target) >= (int) filemtime($source)) {
            return ['path' => $target, 'mime' => $mime];
        }

        // Guard against decompression bombs.
        $info = @getimagesize($source);

        if ($info === false || $info[0] * $info[1] > 60000000) {
            return null;
        }

        $image = self::load($source, $extension);

        if ($image === null) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1.0, $box / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        // White background so transparent PNGs do not turn black.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $newWidth, $newHeight, $white);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        $ok = $useWebp
            ? @imagewebp($canvas, $target, 82)
            : @imagejpeg($canvas, $target, 85);

        imagedestroy($canvas);

        if (!$ok || !is_file($target)) {
            return null;
        }

        return ['path' => $target, 'mime' => $mime];
    }

    private static function load(string $path, string $extension): ?\GdImage
    {
        $image = match ($extension) {
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            'gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : false,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : false,
            default => false,
        };

        return $image instanceof \GdImage ? $image : null;
    }

    /** Removes cached thumbnails for one file. */
    public static function forget(int $fileId): void
    {
        $directory = Storage::cachePath('thumbs');
        $matches = glob($directory . '/' . $fileId . '-*') ?: [];

        foreach ($matches as $match) {
            @unlink($match);
        }
    }

    /** Deletes thumbnails without a matching file row. */
    public static function pruneOrphans(): int
    {
        $directory = Storage::cachePath('thumbs');
        $files = glob($directory . '/*') ?: [];
        $removed = 0;

        foreach ($files as $path) {
            $name = basename($path);
            $id = (int) explode('-', $name)[0];

            if ($id <= 0) {
                continue;
            }

            $exists = \App\Core\Database::value('SELECT 1 FROM files WHERE id = :id LIMIT 1', ['id' => $id]);

            if ($exists === null && @unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }
}
