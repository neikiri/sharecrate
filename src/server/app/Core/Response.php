<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sends the response and terminates the request.
 */
final class Response
{
    private static bool $securityHeadersSent = false;

    /** @param array<string, string> $headers */
    public static function html(string $body, int $status = 200, array $headers = []): never
    {
        self::securityHeaders();
        self::status($status);
        header('Content-Type: text/html; charset=UTF-8');
        // Pages are user specific (locale, session, unlocked files) - never cache.
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        self::extraHeaders($headers);

        echo $body;
        self::finish();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public static function json(array $data, int $status = 200, array $headers = []): never
    {
        self::securityHeaders();
        self::status($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        self::extraHeaders($headers);

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::finish();
    }

    public static function text(string $body, int $status = 200): never
    {
        self::securityHeaders();
        self::status($status);
        header('Content-Type: text/plain; charset=UTF-8');

        echo $body;
        self::finish();
    }

    public static function redirect(string $url, int $status = 302): never
    {
        self::status($status);
        header('Location: ' . $url);
        header('Content-Length: 0');
        self::finish();
    }

    public static function back(string $fallback = '/'): never
    {
        $request = Request::current();
        $referer = $request->referer();
        $target = $fallback;

        if ($referer !== null && str_starts_with($referer, $request->root())) {
            $target = $referer;
        } else {
            $target = Url::to($fallback);
        }

        self::redirect($target);
    }

    public static function noContent(int $status = 204): never
    {
        self::status($status);
        self::finish();
    }

    /** @param array<string, string> $headers */
    public static function csv(string $body, string $filename, array $headers = []): never
    {
        self::securityHeaders();
        self::status(200);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($body));
        self::extraHeaders($headers);

        echo "\xEF\xBB\xBF" . $body; // BOM keeps Excel happy with UTF-8
        self::finish();
    }

    public static function status(int $status): void
    {
        if (!headers_sent()) {
            http_response_code($status);
        }
    }

    /** @param array<string, string> $headers */
    private static function extraHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * Baseline security headers for HTML responses.
     */
    public static function securityHeaders(): void
    {
        if (self::$securityHeadersSent || headers_sent()) {
            return;
        }

        self::$securityHeadersSent = true;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
        header(
            "Content-Security-Policy: default-src 'self'; "
            . "base-uri 'self'; "
            . "img-src 'self' data: blob:; "
            . "media-src 'self' blob:; "
            . "object-src 'none'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'unsafe-eval'; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "form-action 'self'; "
            . "frame-ancestors 'self'"
        );
    }

    public static function noStore(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }

    private static function finish(): never
    {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        exit;
    }
}
