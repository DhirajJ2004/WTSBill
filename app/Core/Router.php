<?php

declare(strict_types=1);

namespace WTSBill\Core;

/**
 * Lightweight, high-performance clean URL router supporting middleware and route parameters.
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];
    private string $currentPrefix = '';
    private array $currentGroupMiddlewares = [];

    public function get(string $path, callable|array|string $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array|string $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array|string $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array|string $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function group(array $attributes, callable $callback): void
    {
        $previousPrefix = $this->currentPrefix;
        $previousMiddlewares = $this->currentGroupMiddlewares;

        if (isset($attributes['prefix'])) {
            $this->currentPrefix .= '/' . trim($attributes['prefix'], '/');
        }

        if (isset($attributes['middleware'])) {
            $middlewares = is_array($attributes['middleware']) ? $attributes['middleware'] : [$attributes['middleware']];
            $this->currentGroupMiddlewares = array_merge($this->currentGroupMiddlewares, $middlewares);
        }

        $callback($this);

        $this->currentPrefix = $previousPrefix;
        $this->currentGroupMiddlewares = $previousMiddlewares;
    }

    private function addRoute(string $method, string $path, callable|array|string $handler, array $middlewares = []): void
    {
        $fullPath = $this->currentPrefix . '/' . trim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');
        if ($fullPath === '') {
            $fullPath = '/';
        }

        $allMiddlewares = array_merge($this->currentGroupMiddlewares, $middlewares);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'pattern' => $this->compilePattern($fullPath),
            'handler' => $handler,
            'middlewares' => $allMiddlewares,
        ];
    }

    private function compilePattern(string $path): string
    {
        // Convert {param} to (?P<param>[^/]+)
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Dispatch the incoming request against registered routes.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                // Extract named parameters
                $params = array_filter($matches, fn($key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);

                // Run middleware pipeline
                foreach ($route['middlewares'] as $middleware) {
                    if (is_callable($middleware)) {
                        $middlewareResponse = $middleware($request);
                        if ($middlewareResponse instanceof Response) {
                            return $middlewareResponse;
                        }
                    } elseif (is_object($middleware) && method_exists($middleware, 'handle')) {
                        $middlewareResponse = $middleware->handle($request);
                        if ($middlewareResponse instanceof Response) {
                            return $middlewareResponse;
                        }
                    } elseif (is_string($middleware) && class_exists($middleware)) {
                        $instance = new $middleware();
                        if (method_exists($instance, 'handle')) {
                            $middlewareResponse = $instance->handle($request);
                            if ($middlewareResponse instanceof Response) {
                                return $middlewareResponse;
                            }
                        }
                    }
                }

                // Invoke route handler
                return $this->invokeHandler($route['handler'], $request, $params);
            }
        }

        if ($request->expectsJson()) {
            return Response::error("Endpoint '{$path}' not found", 404);
        }

        $safePath = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>404 Not Found &mdash; WTSBill ERP</title>'
            . '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}'
            . '.card{background:#1e293b;padding:2.5rem;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,0.5);text-align:center;max-width:480px;border:1px solid #334155;}'
            . 'h1{color:#ef4444;margin-bottom:0.5rem;font-size:1.75rem;}p{color:#94a3b8;line-height:1.6;font-size:0.95rem;word-break:break-all;}'
            . 'code{background:#0f172a;padding:0.2rem 0.4rem;border-radius:0.25rem;color:#38bdf8;font-size:0.9em;}'
            . 'a{display:inline-block;margin-top:1.25rem;background:#0284c7;color:#fff;text-decoration:none;font-weight:600;padding:0.6rem 1.25rem;border-radius:0.5rem;}</style></head><body>'
            . '<div class="card"><h1>404 Not Found</h1><p>The requested URL <code>' . $safePath . '</code> was not found on this server.</p>'
            . '<a href="/">&larr; Return to Dashboard</a></div></body></html>';

        return Response::html($html, 404);
    }

    private function invokeHandler(callable|array|string $handler, Request $request, array $params): Response
    {
        if (is_callable($handler)) {
            $res = call_user_func($handler, $request, ...array_values($params));
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = is_object($class) ? $class : new $class();
            $res = call_user_func([$instance, $method], $request, ...array_values($params));
        } elseif (is_string($handler) && file_exists($handler)) {
            // Render PHP view script
            ob_start();
            extract($params, EXTR_SKIP);
            require $handler;
            $output = ob_get_clean();
            return Response::html($output ?: '');
        } else {
            throw new \RuntimeException('Invalid route handler specified.');
        }

        if ($res instanceof Response) {
            return $res;
        }

        if (is_array($res)) {
            return Response::json($res);
        }

        return Response::html((string) $res);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Router', false)) {
    class_alias(Router::class, 'App\Core\Router');
}
