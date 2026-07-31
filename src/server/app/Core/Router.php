<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Minimal pattern router. Handlers are either closures or
 * "Namespace\Controller@method" strings resolved under App\Controllers.
 */
final class Router
{
    /** @var array<int, array{methods: string[], regex: string, handler: mixed}> */
    private array $routes = [];

    /** @var Closure|null */
    private $fallback = null;

    public function get(string $pattern, mixed $handler): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): void
    {
        $this->add(['POST'], $pattern, $handler);
    }

    public function any(string $pattern, mixed $handler): void
    {
        $this->add(['GET', 'HEAD', 'POST'], $pattern, $handler);
    }

    public function fallback(Closure $handler): void
    {
        $this->fallback = $handler;
    }

    /** @param string[] $methods */
    private function add(array $methods, string $pattern, mixed $handler): void
    {
        $this->routes[] = [
            'methods' => $methods,
            'regex' => $this->compile($pattern),
            'handler' => $handler,
        ];
    }

    /**
     * Turns "/f/{alias:[a-z]{2,10}}" into a named-group regex.
     *
     * The placeholder is parsed with brace counting, so constraints may
     * contain their own quantifiers such as {2} or {0,159}.
     */
    private function compile(string $pattern): string
    {
        $regex = '';
        $length = strlen($pattern);
        $index = 0;

        while ($index < $length) {
            if ($pattern[$index] !== '{') {
                $regex .= preg_quote($pattern[$index], '#');
                $index++;

                continue;
            }

            $depth = 1;
            $cursor = $index + 1;

            while ($cursor < $length) {
                if ($pattern[$cursor] === '{') {
                    $depth++;
                } elseif ($pattern[$cursor] === '}') {
                    $depth--;

                    if ($depth === 0) {
                        break;
                    }
                }

                $cursor++;
            }

            if ($depth !== 0) {
                throw new \RuntimeException("Unbalanced braces in route pattern [{$pattern}].");
            }

            $token = substr($pattern, $index + 1, $cursor - $index - 1);
            [$name, $constraint] = str_contains($token, ':')
                ? explode(':', $token, 2)
                : [$token, '[^/]+'];

            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) !== 1) {
                throw new \RuntimeException("Invalid placeholder [{$name}] in route [{$pattern}].");
            }

            $regex .= '(?P<' . $name . '>' . $constraint . ')';
            $index = $cursor + 1;
        }

        return '#^' . $regex . '$#u';
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();
        $method = $request->method();
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            if (!in_array($method, $route['methods'], true)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            $this->call($route['handler'], $params, $request);

            return;
        }

        if ($this->fallback !== null) {
            ($this->fallback)($request);
        }

        if ($pathMatched) {
            throw new HttpException(405, 'Method Not Allowed');
        }

        throw HttpException::notFound();
    }

    /** @param array<string, string> $params */
    private function call(mixed $handler, array $params, Request $request): void
    {
        if ($handler instanceof Closure) {
            $handler($request, $params);

            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $fqcn = str_starts_with($class, '\\') ? ltrim($class, '\\') : 'App\\Controllers\\' . $class;

            if (!class_exists($fqcn)) {
                throw new \RuntimeException("Controller {$fqcn} not found.");
            }

            $controller = new $fqcn();

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Method {$fqcn}::{$method}() not found.");
            }

            $controller->{$method}($request, $params);

            return;
        }

        throw new \RuntimeException('Unsupported route handler.');
    }
}
