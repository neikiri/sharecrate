<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish snapshot of the incoming HTTP request.
 */
final class Request
{
    /** @var array<string, mixed> */
    public array $query = [];

    /** @var array<string, mixed> */
    public array $post = [];

    /** @var array<string, mixed> */
    public array $cookies = [];

    /** @var array<string, mixed> */
    public array $files = [];

    /** @var array<string, mixed> */
    public array $server = [];

    public string $method = 'GET';

    private string $path = '/';

    private static ?self $instance = null;

    public static function capture(): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $request = new self();
        $request->query = $_GET;
        $request->post = $_POST;
        $request->cookies = $_COOKIE;
        $request->files = $_FILES;
        $request->server = $_SERVER;
        $request->method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $request->path = $request->resolvePath();

        self::$instance = $request;

        return $request;
    }

    public static function current(): self
    {
        return self::$instance ?? self::capture();
    }

    private function resolvePath(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = rawurldecode($path);

        $base = Config::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $path = '/' . ltrim($path, '/');
        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** Path segments without empty values. @return string[] */
    public function segments(): array
    {
        return array_values(array_filter(explode('/', $this->path), static fn ($s) => $s !== ''));
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET' || $this->method === 'HEAD';
    }

    public function isHead(): bool
    {
        return $this->method === 'HEAD';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function queryParam(string $key, mixed $default = null): mixed
    {
        $value = $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $value;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key);

        return in_array(is_string($value) ? strtolower($value) : $value, ['1', 'true', 'on', 'yes', true, 1], true);
    }

    /** @return string[] */
    public function arr(string $key): array
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $value));
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;

        if ($value === null && in_array(strtoupper($name), ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
            $value = $this->server[strtoupper($name)] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function userAgent(): ?string
    {
        $agent = $this->header('User-Agent');

        return $agent === null ? null : mb_substr($agent, 0, 255);
    }

    public function referer(): ?string
    {
        $referer = $this->header('Referer');

        return $referer === null ? null : mb_substr($referer, 0, 255);
    }

    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? '') !== '' && strtolower((string) $this->server['HTTPS']) !== 'off') {
            return true;
        }

        if ($this->trustsProxy()) {
            if (strtolower((string) ($this->header('X-Forwarded-Proto') ?? '')) === 'https') {
                return true;
            }
            if ((string) ($this->header('X-Forwarded-Ssl') ?? '') === 'on') {
                return true;
            }
        }

        return (int) ($this->server['SERVER_PORT'] ?? 80) === 443;
    }

    public function host(): string
    {
        $host = (string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost');

        // Strip anything that cannot appear in a hostname.
        return preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $host) ?: 'localhost';
    }

    public function root(): string
    {
        $configured = Config::get('APP_URL');

        if ($configured !== null) {
            return rtrim($configured, '/');
        }

        return ($this->isSecure() ? 'https://' : 'http://') . $this->host() . Config::basePath();
    }

    public function fullUrl(): string
    {
        $query = $this->server['QUERY_STRING'] ?? '';

        return $this->root() . $this->path . ($query !== '' ? '?' . $query : '');
    }

    private function trustsProxy(): bool
    {
        $trusted = Config::list('TRUSTED_PROXIES');

        if ($trusted === []) {
            return false;
        }

        if (in_array('*', $trusted, true)) {
            return true;
        }

        return in_array((string) ($this->server['REMOTE_ADDR'] ?? ''), $trusted, true);
    }

    /**
     * Client IP. Proxy headers are only honoured for trusted proxies.
     */
    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');

        if (!$this->trustsProxy()) {
            return $this->validIp($remote) ?? '0.0.0.0';
        }

        foreach (['CF-Connecting-IP', 'X-Real-IP', 'X-Forwarded-For'] as $header) {
            $value = $this->header($header);

            if ($value === null) {
                continue;
            }

            foreach (explode(',', $value) as $candidate) {
                $ip = $this->validIp(trim($candidate));

                if ($ip !== null) {
                    return $ip;
                }
            }
        }

        return $this->validIp($remote) ?? '0.0.0.0';
    }

    private function validIp(string $ip): ?string
    {
        return filter_var($ip, FILTER_VALIDATE_IP) === false ? null : $ip;
    }

    public function wantsJson(): bool
    {
        if (strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest') {
            return true;
        }

        $accept = (string) $this->header('Accept');

        return str_contains($accept, 'application/json');
    }

    /**
     * Returns the current URL with the given query parameters merged in.
     *
     * @param array<string, string|int|null> $params
     */
    public function withQuery(array $params): string
    {
        $query = array_merge($this->query, $params);
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        $queryString = http_build_query($query);

        return $this->path . ($queryString !== '' ? '?' . $queryString : '');
    }
}
