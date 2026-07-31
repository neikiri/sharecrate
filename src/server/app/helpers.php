<?php

/**
 * Global helper functions.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Models\Setting;
use App\Support\Formatter;
use App\Support\Icons;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('t')) {
    /** @param array<string, string|int> $replace */
    function t(string $key, array $replace = []): string
    {
        return I18n::t($key, $replace);
    }
}

if (!function_exists('tc')) {
    /** @param array<string, string|int> $replace */
    function tc(string $key, int $count, array $replace = []): string
    {
        return I18n::choice($key, $count, $replace);
    }
}

if (!function_exists('url')) {
    /** @param array<string, string|int|null> $query */
    function url(string $path = '/', array $query = []): string
    {
        return Url::to($path, $query);
    }
}

if (!function_exists('absolute_url')) {
    /** @param array<string, string|int|null> $query */
    function absolute_url(string $path = '/', array $query = []): string
    {
        return Url::absolute($path, $query);
    }
}

if (!function_exists('icon')) {
    function icon(string $name, string $class = 'size-5'): string
    {
        return Icons::render($name, $class);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        $value = Session::old($key, $default);

        return is_scalar($value) ? e((string) $value) : '';
    }
}

if (!function_exists('setting')) {
    function setting(string $key, ?string $default = null): ?string
    {
        return Setting::get($key, $default);
    }
}

if (!function_exists('current_user')) {
    /** @return array<string, mixed>|null */
    function current_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        return Request::current();
    }
}

if (!function_exists('redirect')) {
    /** @param array<string, string|int|null> $query */
    function redirect(string $path = '/', array $query = []): never
    {
        Response::redirect(Url::to($path, $query));
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('abort')) {
    function abort(int $status, ?string $translationKey = null): never
    {
        throw new HttpException($status, 'Aborted', $translationKey);
    }
}

if (!function_exists('bytes_human')) {
    function bytes_human(?int $bytes): string
    {
        return Formatter::bytes($bytes);
    }
}

if (!function_exists('fdate')) {
    function fdate(?string $utc, bool $withTime = true): string
    {
        return Formatter::date($utc, $withTime);
    }
}
