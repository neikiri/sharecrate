<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Formatter;
use App\Support\Icons;
use App\Models\Setting;
use RuntimeException;
use Throwable;

/**
 * Plain PHP templates with a layout. Templates are included inside a View
 * method, so they can use $this-> helpers directly.
 */
final class View
{
    public string $layout = 'layouts/public';

    public string $title = '';

    public string $description = '';

    public string $bodyClass = '';

    public bool $noindex = false;

    public ?string $canonical = null;

    /** @var array<string, string> */
    public array $og = [];

    /** @var array<string, mixed> */
    private array $shared = [];

    /** @param array<string, mixed> $shared */
    public function __construct(array $shared = [])
    {
        $this->shared = $shared;
    }

    /** @param array<string, mixed> $shared */
    public static function make(array $shared = []): self
    {
        return new self($shared);
    }

    public function layout(?string $layout): self
    {
        $this->layout = (string) $layout;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $content = $this->capture($template, $data);

        if ($this->layout !== '') {
            return $this->capture($this->layout, array_merge($data, ['content' => $content]));
        }

        return $content;
    }

    /**
     * Renders and sends the page.
     *
     * @param array<string, mixed> $data
     */
    public function display(string $template, array $data = [], int $status = 200): never
    {
        $html = $this->render($template, $data);
        Session::clearTransient();

        Response::html($html, $status);
    }

    /**
     * Includes a template with $data extracted into its scope.
     *
     * Local variables use a __view prefix so template variables such as
     * $file or $data are never shadowed by this method.
     *
     * @param array<string, mixed> $data
     */
    private function capture(string $template, array $data): string
    {
        $__viewPath = VIEW_PATH . '/' . (preg_replace('#[^a-zA-Z0-9_/\-]#', '', $template) ?? '') . '.php';

        if (!is_file($__viewPath)) {
            throw new RuntimeException("View [{$template}] not found.");
        }

        $__viewData = array_merge($this->shared, $data);

        unset($template, $data);
        extract($__viewData, EXTR_SKIP);
        unset($__viewData);

        ob_start();

        try {
            include $__viewPath;
        } catch (Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    /** Renders a partial straight to the output buffer. @param array<string, mixed> $data */
    public function partial(string $template, array $data = []): void
    {
        echo $this->capture($template, $data);
    }

    /* ----------------------------------------------------------------
     * Template helpers
     * ---------------------------------------------------------------- */

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, string|int> $replace */
    public function t(string $key, array $replace = []): string
    {
        return I18n::t($key, $replace);
    }

    /** @param array<string, string|int> $replace */
    public function te(string $key, array $replace = []): string
    {
        return $this->e(I18n::t($key, $replace));
    }

    /** @param array<string, string|int> $replace */
    public function choice(string $key, int $count, array $replace = []): string
    {
        return I18n::choice($key, $count, $replace);
    }

    public function icon(string $name, string $class = 'size-5'): string
    {
        return Icons::render($name, $class);
    }

    /** @param array<string, string|int|null> $query */
    public function url(string $path = '/', array $query = []): string
    {
        return Url::to($path, $query);
    }

    /** @param array<string, string|int|null> $query */
    public function absolute(string $path = '/', array $query = []): string
    {
        return Url::absolute($path, $query);
    }

    public function bytes(?int $bytes): string
    {
        return Formatter::bytes($bytes);
    }

    public function number(int|float|null $value): string
    {
        return Formatter::number($value);
    }

    public function date(?string $value, bool $withTime = true): string
    {
        return Formatter::date($value, $withTime);
    }

    public function ago(?string $value): string
    {
        return Formatter::ago($value);
    }

    public function csrf(): string
    {
        return Csrf::field();
    }

    public function old(string $key, mixed $default = ''): string
    {
        $value = Session::old($key, $default);

        return is_scalar($value) ? $this->e((string) $value) : '';
    }

    public function error(string $key): ?string
    {
        return Session::errors()[$key] ?? null;
    }

    public function hasError(string $key): bool
    {
        return isset(Session::errors()[$key]);
    }

    /** @return array<int, array{type: string, message: string}> */
    public function flashes(): array
    {
        return Session::flashes();
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        return Auth::user();
    }

    public function setting(string $key, ?string $default = null): ?string
    {
        return Setting::get($key, $default);
    }

    public function siteName(): string
    {
        return (string) Setting::get('site_name', (string) Config::get('APP_NAME', 'ShareCrate'));
    }

    /** Marks the active navigation item. */
    public function isActive(string $prefix, bool $exact = false): bool
    {
        $path = Request::current()->path();

        return $exact ? $path === $prefix : ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/'));
    }

    public function activeClass(string $prefix, string $class = 'nav-link-active', bool $exact = false): string
    {
        return $this->isActive($prefix, $exact) ? ' ' . $class : '';
    }

    public function locale(): string
    {
        return I18n::locale();
    }

    /**
     * Current URL with different query parameters (sorting, paging, filters).
     *
     * @param array<string, string|int|null> $params
     */
    public function queryUrl(array $params): string
    {
        return Url::to(Request::current()->withQuery($params));
    }
}
