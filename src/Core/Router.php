<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal route registry and dispatcher.
 *
 * The router supports static and parameterized paths without external
 * dependencies. This keeps request flow easy to debug for small projects.
 */
final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->match(['GET'], $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->match(['POST'], $path, $handler);
    }

    public function match(array $methods, string $path, array $handler): void
    {
        $pattern = preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . rtrim($pattern ?? '', '/') . '$#';

        $this->routes[] = [
            'methods' => $methods,
            'pattern' => $path === '/' ? '#^/$#' : $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        // Dispatch is first-match-wins, which is why route order in App matters.
        foreach ($this->routes as $route) {
            if (!in_array($request->method(), $route['methods'], true)) {
                continue;
            }

            if (!preg_match($route['pattern'], $request->path(), $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $method] = $route['handler'];
            $controller = new $class();

            return $controller->$method($request, $params);
        }

        throw new HttpException('Page not found', 404);
    }
}
