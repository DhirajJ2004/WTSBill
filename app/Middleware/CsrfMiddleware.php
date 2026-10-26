<?php

declare(strict_types=1);

namespace WTSBill\Middleware;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;

/**
 * Cross-Site Request Forgery (CSRF) Protection Middleware.
 */
class CsrfMiddleware
{
    private array $except = [
        '/api/*', // Stateless API endpoints with Bearer tokens are exempt from cookie CSRF
    ];

    public function __construct(array $except = [])
    {
        if (!empty($except)) {
            $this->except = array_merge($this->except, $except);
        }
    }

    public function handle(Request $request): ?Response
    {
        $method = $request->getMethod();

        // Safe HTTP methods do not require CSRF token
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        // Check if route is in the exempt list
        if ($this->isExempt($request->getPath())) {
            return null;
        }

        // Extract CSRF token from POST body, JSON body, or HTTP headers
        $token = $request->input('_csrf_token')
            ?? $request->input('_token')
            ?? $request->getHeader('x-csrf-token')
            ?? $request->getHeader('x-xsrf-token');

        if (!Session::verifyCsrfToken($token)) {
            if ($request->expectsJson()) {
                return Response::error('CSRF token mismatch. Please reload the page.', 419);
            }

            return Response::html(
                '<!DOCTYPE html><html><head><title>419 Page Expired</title>' .
                '<style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}' .
                '.card{background:#1e293b;padding:2.5rem;border-radius:1rem;text-align:center;max-width:440px;border:1px solid #f59e0b;}' .
                'h1{color:#fbbf24;font-size:1.75rem;margin-bottom:0.5rem;}p{color:#94a3b8;font-size:0.95rem;line-height:1.5;}' .
                'a{color:#38bdf8;text-decoration:none;font-weight:500;}</style></head><body>' .
                '<div class="card"><h1>419 Page Expired</h1>' .
                '<p>Your security session or form submission has expired. Please refresh the page and try again.</p>' .
                '<p><a href="javascript:location.reload()">&circlearrowright; Refresh Page</a></p></div></body></html>',
                419
            );
        }

        return null;
    }

    private function isExempt(string $path): bool
    {
        foreach ($this->except as $pattern) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            if (preg_match($regex, $path)) {
                return true;
            }
        }
        return false;
    }

    public function __invoke(Request $request): ?Response
    {
        return $this->handle($request);
    }
}

// Backward compatibility alias
if (!class_exists('App\Middleware\CsrfMiddleware', false)) {
    class_alias(CsrfMiddleware::class, 'App\Middleware\CsrfMiddleware');
}
