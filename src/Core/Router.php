<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:array}> */
    private array $routes = [];

    public function get(string $path, array $handler): void { $this->add('GET', $path, $handler); }
    public function post(string $path, array $handler): void { $this->add('POST', $path, $handler); }
    public function put(string $path, array $handler): void { $this->add('PUT', $path, $handler); }
    public function patch(string $path, array $handler): void { $this->add('PATCH', $path, $handler); }
    public function delete(string $path, array $handler): void { $this->add('DELETE', $path, $handler); }

    private function add(string $method, string $path, array $handler): void
    {
        if (count($handler) !== 2 || !is_string($handler[0]) || !is_string($handler[1])) {
            throw new \InvalidArgumentException('Route handler must be [Controller::class, method].');
        }
        $quoted = preg_quote($path, '#');
        $pattern = preg_replace('/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/', '(?P<$1>[0-9]+)', $quoted);
        $this->routes[] = [
            'method' => $method,
            'pattern' => '#^' . $pattern . '/?$#D',
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri, string $basePath = ''): void
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? rawurldecode($path) : '/';
        $basePath = $basePath === '' ? '' : '/' . trim($basePath, '/');
        if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $path, $matches)) {
                continue;
            }
            $allowed[] = $route['method'];
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $parameters = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $parameters[] = (int) $value;
                }
            }
            [$class, $action] = $route['handler'];
            if (!class_exists($class) || !method_exists($class, $action)) {
                ApiResponse::error('route_handler_unavailable', 'The requested operation is unavailable.', 500);
            }
            (new $class())->{$action}(...$parameters);
            return;
        }

        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_values(array_unique($allowed))));
            ApiResponse::error('method_not_allowed', 'HTTP method is not allowed for this route.', 405);
        }
        ApiResponse::error('not_found', 'The requested API route was not found.', 404);
    }
}
