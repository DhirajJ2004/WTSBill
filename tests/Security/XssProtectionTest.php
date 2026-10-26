<?php

declare(strict_types=1);

namespace Tests\Security;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Core\View;

/**
 * Cross-Site Scripting (XSS) and Content Injection Regression Test (Chunk 19).
 * Verifies contextual HTML escaping, reflected XSS defenses in 404 handler,
 * security headers, and view rendering safety.
 */
class XssProtectionTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();

        // 1. Output Escaping Helper Function Tests
        $scriptTag = "<script>alert('XSS-ATTACK')</script>";
        $escapedScript = e($scriptTag);
        $assert('XSS-01', "Helper e() escapes script tags into harmless HTML entities", 
            $escapedScript === "&lt;script&gt;alert(&#039;XSS-ATTACK&#039;)&lt;/script&gt;");

        $attrPayload = 'test" onmouseover="alert(1)" class="evil';
        $escapedAttr = e($attrPayload);
        $assert('XSS-02', 'Helper e() escapes double quotes into &quot; to prevent attribute breakout', 
            str_contains($escapedAttr, '&quot;'));

        $singleQuotePayload = "user's input";
        $escapedQuote = e($singleQuotePayload);
        $assert('XSS-03', 'Helper e() escapes single quotes into &#039;', 
            str_contains($escapedQuote, '&#039;'));

        $ampersandPayload = "Tata & Sons & Partners";
        $escapedAmp = e($ampersandPayload);
        $assert('XSS-04', 'Helper e() escapes ampersands into &amp;', 
            str_contains($escapedAmp, '&amp;'));

        // 2. Reflected XSS Neutralization in Router 404 Handler
        $router = new Router();
        $reflectedXssUri = '/<script>alert(document.cookie)<\/script>';
        $xssRequest = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $reflectedXssUri]);
        $xssResponse = $router->dispatch($xssRequest);

        $body404 = $xssResponse->getContent();
        $assert('XSS-05', 'Router 404 handler sanitizes reflected URI to neutralize script execution', 
            !str_contains($body404, '<script>alert') &&
            str_contains($body404, '&lt;script&gt;'));

        // 3. Mandatory Security Headers Verification
        $sampleResponse = Response::html('<div>Content</div>');
        $headers = $sampleResponse->getHeaders();

        $assert('XSS-06', 'Response enforces X-Content-Type-Options: nosniff header', 
            ($headers['X-Content-Type-Options'] ?? '') === 'nosniff');
        $assert('XSS-07', 'Response enforces X-Frame-Options: SAMEORIGIN to prevent clickjacking', 
            ($headers['X-Frame-Options'] ?? '') === 'SAMEORIGIN');
        $assert('XSS-08', 'Response enforces X-XSS-Protection: 1; mode=block header', 
            ($headers['X-XSS-Protection'] ?? '') === '1; mode=block');

        // 4. Stored View Rendering Safety
        $renderedView = View::render('components/test_card', [
            'title' => '<b onfocus=alert(1)>Bold Title</b>'
        ]);
        $assert('XSS-09', 'View component properly escapes interpolated HTML input', 
            str_contains($renderedView, '&lt;b onfocus=alert(1)&gt;'));

        // Clean up
        Session::clear();
    }
}
