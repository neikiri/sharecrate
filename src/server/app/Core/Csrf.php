<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();
        $token = Session::get(self::KEY);

        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::put(self::KEY, $token);
        }

        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">';
    }

    public static function verify(?string $candidate): bool
    {
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        $token = Session::get(self::KEY);

        return is_string($token) && hash_equals($token, $candidate);
    }

    /**
     * Aborts the request when the token is missing or wrong.
     */
    public static function check(Request $request): void
    {
        if (!$request->isPost()) {
            return;
        }

        if (self::verify(is_string($request->raw('_token')) ? (string) $request->raw('_token') : null)) {
            return;
        }

        if ($request->wantsJson()) {
            Response::json([
                'ok' => false,
                'message' => I18n::t('errors.csrf'),
            ], 419);
        }

        throw new HttpException(419, 'CSRF token mismatch', 'errors.csrf');
    }

    public static function rotate(): void
    {
        Session::forget(self::KEY);
        self::token();
    }
}
