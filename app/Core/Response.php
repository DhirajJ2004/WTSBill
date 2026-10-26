<?php

declare(strict_types=1);

namespace WTSBill\Core;

/**
 * Unified HTTP Response abstraction handling JSON, Views, Redirects, and Security Headers.
 */
class Response
{
    private int $statusCode;
    private array $headers;
    private string $content;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        
        // Default security headers
        $this->headers['X-Content-Type-Options'] ??= 'nosniff';
        $this->headers['X-Frame-Options'] ??= 'SAMEORIGIN';
        $this->headers['X-XSS-Protection'] ??= '1; mode=block';
    }

    /**
     * Create a JSON response.
     */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new self($encoded !== false ? $encoded : '{}', $statusCode, $headers);
    }

    /**
     * Standard success JSON envelope.
     */
    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): self
    {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Standard error JSON envelope with safe message.
     */
    public static function error(string $message, int $statusCode = 400, array $errors = []): self
    {
        $payload = [
            'success' => false,
            'message' => self::sanitizeErrorMessage($message),
            'errors' => $errors,
        ];
        return self::json($payload, $statusCode);
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    /**
     * Create a redirect back to referer or fallback URL.
     */
    public static function redirectBack(string $fallback = '/', int $statusCode = 302): self
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($referer === $currentUri || empty($referer)) {
            $referer = $fallback;
        }
        return self::redirect($referer, $statusCode);
    }

    /**
     * Create an HTML response.
     */
    public static function html(string $html, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'text/html; charset=utf-8';
        return new self($html, $statusCode, $headers);
    }

    /**
     * Set a custom response header.
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Add cache control headers for cacheable responses.
     */
    public function withCache(int $maxAge = 3600, bool $isPublic = false): self
    {
        $visibility = $isPublic ? 'public' : 'private';
        $this->headers['Cache-Control'] = "{$visibility}, max-age={$maxAge}";
        return $this;
    }

    /**
     * Add a Server-Timing entry for performance observability.
     */
    public function withServerTiming(string $metric, float $durationMs, ?string $description = null): self
    {
        $entry = sprintf('%s;dur=%.2f', $metric, $durationMs);
        if ($description !== null) {
            $entry .= sprintf(';desc="%s"', addslashes($description));
        }

        if (isset($this->headers['Server-Timing'])) {
            $this->headers['Server-Timing'] .= ', ' . $entry;
        } else {
            $this->headers['Server-Timing'] = $entry;
        }

        return $this;
    }

    /**
     * Send headers and body to client with automatic Server-Timing and conditional 304 check.
     */
    public function send(): void
    {
        // 1. Calculate and append Server-Timing headers if benchmark start is defined
        if (defined('WTSBILL_START')) {
            $totalElapsedMs = (microtime(true) - WTSBILL_START) * 1000.0;
            $this->withServerTiming('app', $totalElapsedMs, 'Application Gateway');

            if (class_exists(Database::class, false)) {
                $metrics = Database::getQueryMetrics();
                if ($metrics['count'] > 0) {
                    $this->withServerTiming('db', (float) $metrics['time_ms'], "{$metrics['count']} SQL Queries");
                }
            }
        }

        // 2. Generate ETag for 200 OK responses with content and handle conditional 304
        if ($this->statusCode === 200 && $this->content !== '') {
            $etag = '"' . md5($this->content) . '"';
            $this->headers['ETag'] ??= $etag;

            $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
            if ($clientEtag !== null && (trim($clientEtag) === $etag || trim($clientEtag) === '*')) {
                if (!headers_sent()) {
                    http_response_code(304);
                    foreach ($this->headers as $name => $value) {
                        header("{$name}: {$value}");
                    }
                }
                return;
            }
        }

        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getBody(): string
    {
        return $this->content;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return (string) $v;
            }
        }
        return null;
    }


    /**
     * Redact SQL syntax and raw database exception traces from error messages.
     */
    private static function sanitizeErrorMessage(string $message): string
    {
        if (preg_match('/(SQLSTATE|SELECT|INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TABLE)/i', $message)) {
            return 'A database error occurred while processing your request. Please contact support.';
        }
        return $message;
    }
}

// Backward compatibility alias
if (!class_exists('App\Core\Response', false)) {
    class_alias(Response::class, 'App\Core\Response');
}
