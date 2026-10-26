<?php

declare(strict_types=1);

namespace Tests\Unit;

use WTSBill\Core\Response;

/**
 * Unit Test for Core Response component.
 */
class CoreResponseTest
{
    public static function run(callable $assert): void
    {
        // 1. Success response
        $resSuccess = Response::success(['invoice_id' => 101, 'number' => 'INV-001'], 'Invoice created', 201);
        $assert('RESP-01', 'Success response status is 201', $resSuccess->getStatusCode() === 201);
        
        $payload = json_decode($resSuccess->getContent(), true);
        $assert('RESP-02', 'Success envelope has success=true', $payload['success'] === true);
        $assert('RESP-03', 'Success envelope message is preserved', $payload['message'] === 'Invoice created');
        $assert('RESP-04', 'Success envelope data is intact', $payload['data']['invoice_id'] === 101);

        // 2. Security headers
        $headers = $resSuccess->getHeaders();
        $assert('RESP-05', 'X-Content-Type-Options is nosniff', ($headers['X-Content-Type-Options'] ?? null) === 'nosniff');
        $assert('RESP-06', 'X-Frame-Options is SAMEORIGIN', ($headers['X-Frame-Options'] ?? null) === 'SAMEORIGIN');
        $assert('RESP-07', 'X-XSS-Protection is 1; mode=block', ($headers['X-XSS-Protection'] ?? null) === '1; mode=block');

        // 3. Error response with SQL redaction
        $rawSqlError = 'SQLSTATE[42S22]: Column not found: 1054 Unknown column in where clause SELECT * FROM secret_table';
        $resError = Response::error($rawSqlError, 500, ['field' => 'invalid']);
        $errPayload = json_decode($resError->getContent(), true);

        $assert('RESP-08', 'Error response status is 500', $resError->getStatusCode() === 500);
        $assert('RESP-09', 'Error envelope has success=false', $errPayload['success'] === false);
        $assert('RESP-10', 'SQL error string is redacted from message', !str_contains($errPayload['message'], 'SQLSTATE'));
        $assert('RESP-11', 'SQL error table name is redacted', !str_contains($errPayload['message'], 'secret_table'));

        // 4. HTML response
        $htmlRes = Response::html('<h1>Hello WTSBill</h1>', 200);
        $assert('RESP-12', 'HTML response has Content-Type text/html', str_contains($htmlRes->getHeaders()['Content-Type'], 'text/html'));
        $assert('RESP-13', 'HTML response content matches', $htmlRes->getContent() === '<h1>Hello WTSBill</h1>');

        // 5. Redirect response
        $redirectRes = Response::redirect('/login', 302);
        $assert('RESP-14', 'Redirect response status is 302', $redirectRes->getStatusCode() === 302);
        $assert('RESP-15', 'Redirect response Location header set', ($redirectRes->getHeaders()['Location'] ?? null) === '/login');
    }
}
