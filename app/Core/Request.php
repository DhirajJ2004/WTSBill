<?php

declare(strict_types=1);

namespace WTSBill\Core;

/**
 * Unified HTTP Request abstraction capturing GET, POST, JSON payloads, headers, and client context.
 */
class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $queryParams;
    private array $postParams;
    private array $jsonParams;
    private array $serverParams;
    private array $headers;

    public function __construct(
        array $query = [],
        array $post = [],
        array $server = [],
        ?string $rawBody = null
    ) {
        $this->queryParams = $query;
        $this->postParams = $post;
        $this->serverParams = $server;
        $this->method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        
        $rawUri = $server['REQUEST_URI'] ?? '/';
        $this->uri = $rawUri;
        $this->path = $this->parsePath($rawUri, $server);
        
        $this->headers = $this->extractHeaders($server);
        $this->jsonParams = $this->parseJsonBody($rawBody);
    }

    /**
     * Capture the current incoming global HTTP request.
     */
    public static function capture(): self
    {
        $rawBody = file_get_contents('php://input');
        return new self($_GET, $_POST, $_SERVER, $rawBody ?: null);
    }

    private function parsePath(string $uri, array $server = []): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Detect and strip subfolder deployment under web server (e.g. /WTSBill or /WTSBill/public)
        $scriptName = $server['SCRIPT_NAME'] ?? '';
        if ($scriptName !== '') {
            $baseDir = str_replace('\\', '/', dirname($scriptName));
            if ($baseDir !== '/' && $baseDir !== '.' && $baseDir !== '') {
                if (str_starts_with($path, $baseDir)) {
                    $path = substr($path, strlen($baseDir));
                } elseif (str_ends_with($baseDir, '/public')) {
                    $parentDir = substr($baseDir, 0, -7);
                    if ($parentDir !== '' && str_starts_with($path, $parentDir)) {
                        $path = substr($path, strlen($parentDir));
                    }
                }
            }
        }

        if (str_starts_with($path, '/public')) {
            $path = substr($path, 7);
        }

        $path = '/' . trim($path, '/');
        return $path === '' ? '/' : $path;
    }

    private function parseJsonBody(?string $rawBody): array
    {
        if ($rawBody === null || trim($rawBody) === '') {
            return [];
        }

        $contentType = $this->getHeader('content-type') ?? '';
        if (str_contains(strtolower($contentType), 'application/json')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function extractHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'AUTHORIZATION'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function all(): array
    {
        return $this->input();
    }

    /**
     * Get input from JSON payload, POST form, or query param in that order.
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($this->queryParams, $this->postParams, $this->jsonParams);
        if ($key === null) {
            return $all;
        }
        return $all[$key] ?? $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function get(?string $key = null, mixed $default = null): mixed
    {
        return $this->query($key, $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->postParams;
        }
        return $this->postParams[$key] ?? $default;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->jsonParams;
        }
        return $this->jsonParams[$key] ?? $default;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $normalized = strtolower($name);
        return $this->headers[$normalized] ?? $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->getHeader($name, $default);
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('authorization');
        if ($auth && preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    public function getIp(): string
    {
        return $this->serverParams['HTTP_X_FORWARDED_FOR'] 
            ?? $this->serverParams['REMOTE_ADDR'] 
            ?? '127.0.0.1';
    }

    public function expectsJson(): bool
    {
        $accept = $this->getHeader('accept') ?? '';
        return str_contains($accept, 'application/json') || str_starts_with($this->path, '/api/');
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Request', false)) {
    class_alias(Request::class, 'App\Core\Request');
}
