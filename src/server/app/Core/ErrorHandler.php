<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        error_reporting(E_ALL);
        ini_set('display_errors', Config::debug() ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);

        register_shutdown_function(static function (): void {
            $error = error_get_last();

            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            self::handle(new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        });
    }

    public static function handle(Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status() : 500;

        if ($status >= 500) {
            self::log($e);
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL
                . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
            exit(1);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $request = Request::current();

        if ($request->wantsJson()) {
            Response::json([
                'ok' => false,
                'status' => $status,
                'message' => self::publicMessage($e, $status),
            ], $status);
        }

        try {
            $view = new View();
            $view->layout = 'layouts/minimal';
            $view->noindex = true;
            $view->title = self::titleFor($status);

            $view->display('errors/show', [
                'status' => $status,
                'heading' => self::titleFor($status),
                'message' => self::publicMessage($e, $status),
                'exception' => Config::debug() ? $e : null,
            ], $status);
        } catch (Throwable $fallback) {
            Response::html(self::plainPage($status, self::publicMessage($e, $status), $fallback), $status);
        }
    }

    private static function titleFor(int $status): string
    {
        $key = match ($status) {
            400 => 'errors.400_title',
            403 => 'errors.403_title',
            404 => 'errors.404_title',
            405 => 'errors.405_title',
            410 => 'errors.410_title',
            419 => 'errors.419_title',
            429 => 'errors.429_title',
            503 => 'errors.503_title',
            default => 'errors.500_title',
        };

        return I18n::has($key) ? I18n::t($key) : 'Error ' . $status;
    }

    private static function publicMessage(Throwable $e, int $status): string
    {
        if ($e instanceof HttpException) {
            $key = $e->translationKey();

            if ($key !== null && I18n::has($key)) {
                return I18n::t($key);
            }
        }

        $key = match ($status) {
            400 => 'errors.400_message',
            403 => 'errors.403_message',
            404 => 'errors.404_message',
            405 => 'errors.405_message',
            410 => 'errors.410_message',
            419 => 'errors.419_message',
            429 => 'errors.429_message',
            503 => 'errors.503_message',
            default => 'errors.500_message',
        };

        if (Config::debug() && $status >= 500) {
            return $e->getMessage();
        }

        return I18n::has($key) ? I18n::t($key) : 'Something went wrong.';
    }

    public static function log(Throwable $e): void
    {
        $directory = ROOT_PATH . '/storage/logs';

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            error_log((string) $e);

            return;
        }

        $line = sprintf(
            "[%s] %s: %s in %s:%d%s%s%s",
            gmdate('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL,
            $e->getTraceAsString(),
            PHP_EOL . PHP_EOL
        );

        @file_put_contents($directory . '/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    private static function plainPage(int $status, string $message, ?Throwable $extra = null): string
    {
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $debug = '';

        if ($extra !== null && Config::debug()) {
            $debug = '<pre style="white-space:pre-wrap;font:12px/1.5 monospace;color:#b91c1c">'
                . htmlspecialchars((string) $extra, ENT_QUOTES, 'UTF-8')
                . '</pre>';
        }

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Error ' . $status . '</title></head>'
            . '<body style="font:16px/1.6 system-ui,sans-serif;background:#f8fafc;color:#0f172a;'
            . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0">'
            . '<div style="max-width:32rem;padding:2rem;text-align:center">'
            . '<div style="font-size:3rem;font-weight:700">' . $status . '</div>'
            . '<p>' . $safe . '</p>' . $debug . '</div></body></html>';
    }
}
