<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\HttpException;
use App\Models\Download;

/**
 * Streams files from the protected storage directory.
 *
 * Supports HTTP range requests so downloads can be resumed and video/audio
 * previews can seek.
 */
final class Downloader
{
    private const CHUNK = 262144; // 256 kB

    /**
     * @param array<string, mixed> $file
     */
    public static function send(array $file, bool $inline = false, bool $countDownload = true): never
    {
        $relative = (string) $file['path'];

        if (!Storage::exists($relative)) {
            throw HttpException::gone('errors.file_missing');
        }

        $path = Storage::path($relative);
        $size = (int) filesize($path);
        $extension = (string) ($file['extension'] ?? FileTypes::extension((string) $file['original_name']));
        $mime = is_string($file['mime_type'] ?? null) && $file['mime_type'] !== ''
            ? (string) $file['mime_type']
            : FileTypes::mime($extension);

        // Never render untrusted markup in the browser.
        if (FileTypes::forceAttachment($extension)) {
            $inline = false;
            $mime = 'application/octet-stream';
        }

        $filename = (string) $file['original_name'];
        $modified = (int) filemtime($path);
        $etag = '"' . md5($relative . '|' . $size . '|' . $modified) . '"';

        [$start, $end, $isPartial] = self::resolveRange($size);

        if (!headers_sent()) {
            header_remove('X-Powered-By');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Accept-Ranges: bytes');
            header('Content-Type: ' . $mime);
            header('Content-Disposition: ' . self::disposition($inline, $filename));
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
            header('ETag: ' . $etag);
            header('Cache-Control: private, max-age=0, must-revalidate');
        }

        // Conditional requests: previews and thumbnails benefit a lot from this.
        if (!$isPartial && self::notModified($etag, $modified)) {
            http_response_code(304);
            exit;
        }

        $length = $end - $start + 1;

        if ($isPartial) {
            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        } else {
            http_response_code(200);
        }

        header('Content-Length: ' . $length);

        // HEAD only asks for metadata - it is not a download.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            exit;
        }

        // Resumed transfers (Range starting mid-file) are not counted again.
        if ($countDownload && $start === 0) {
            Download::record((int) $file['id'], $length);
        }

        self::stream($path, $start, $length);

        exit;
    }

    /**
     * @return array{0: int, 1: int, 2: bool} start, end, isPartial
     */
    private static function resolveRange(int $size): array
    {
        $header = $_SERVER['HTTP_RANGE'] ?? '';

        if (!is_string($header) || $header === '' || $size === 0) {
            return [0, max(0, $size - 1), false];
        }

        if (preg_match('/^bytes=(\d*)-(\d*)$/i', trim($header), $m) !== 1) {
            self::rangeNotSatisfiable($size);
        }

        $startRaw = $m[1];
        $endRaw = $m[2];

        if ($startRaw === '' && $endRaw === '') {
            self::rangeNotSatisfiable($size);
        }

        if ($startRaw === '') {
            // Suffix range: last N bytes
            $length = (int) $endRaw;

            if ($length <= 0) {
                self::rangeNotSatisfiable($size);
            }

            $start = max(0, $size - $length);
            $end = $size - 1;
        } else {
            $start = (int) $startRaw;
            $end = $endRaw === '' ? $size - 1 : (int) $endRaw;
        }

        if ($start > $end || $start >= $size) {
            self::rangeNotSatisfiable($size);
        }

        $end = min($end, $size - 1);

        return [$start, $end, true];
    }

    private static function rangeNotSatisfiable(int $size): never
    {
        if (!headers_sent()) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
        }

        exit;
    }

    private static function notModified(string $etag, int $modified): bool
    {
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

        if (is_string($ifNoneMatch) && $ifNoneMatch !== '') {
            foreach (explode(',', $ifNoneMatch) as $candidate) {
                if (trim($candidate) === $etag) {
                    return true;
                }
            }
        }

        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

        if (is_string($ifModifiedSince) && $ifModifiedSince !== '') {
            $timestamp = strtotime($ifModifiedSince);

            if ($timestamp !== false && $timestamp >= $modified) {
                return true;
            }
        }

        return false;
    }

    private static function disposition(bool $inline, string $filename): string
    {
        $type = $inline ? 'inline' : 'attachment';
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $filename) ?? 'download';
        $ascii = str_replace(['"', '\\'], '', $ascii);

        if (trim($ascii) === '') {
            $ascii = 'download';
        }

        return sprintf(
            '%s; filename="%s"; filename*=UTF-8\'\'%s',
            $type,
            $ascii,
            rawurlencode($filename)
        );
    }

    private static function stream(string $path, int $start, int $length): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        @set_time_limit(0);
        ignore_user_abort(false);

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        if ($start > 0) {
            fseek($handle, $start);
        }

        $remaining = $length;

        while ($remaining > 0 && !feof($handle)) {
            if (connection_aborted()) {
                break;
            }

            $chunk = (int) min(self::CHUNK, $remaining);
            $buffer = fread($handle, $chunk);

            if ($buffer === false || $buffer === '') {
                break;
            }

            echo $buffer;
            $remaining -= strlen($buffer);

            if (ob_get_level() > 0) {
                @ob_flush();
            }

            flush();
        }

        fclose($handle);
    }

    /**
     * Serves a generated thumbnail with long lived caching.
     */
    public static function sendThumbnail(string $absolutePath, string $mime): never
    {
        $size = (int) filesize($absolutePath);
        $modified = (int) filemtime($absolutePath);
        $etag = '"' . md5($absolutePath . $size . $modified) . '"';

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . $size);
            header('Cache-Control: public, max-age=604800, immutable');
            header('ETag: ' . $etag);
            header('X-Content-Type-Options: nosniff');
        }

        if (self::notModified($etag, $modified)) {
            http_response_code(304);
            exit;
        }

        readfile($absolutePath);

        exit;
    }
}
