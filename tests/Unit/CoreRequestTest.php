<?php

declare(strict_types=1);

namespace Tests\Unit;

use WTSBill\Core\Request;

/**
 * Unit Test for Core Request component.
 */
class CoreRequestTest
{
    public static function run(callable $assert): void
    {
        // 1. Method and path extraction
        $server = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/customers?sort=name&page=3',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer sample_wtsbill_test_token_xyz',
            'REMOTE_ADDR' => '10.0.0.42',
        ];
        $query = ['sort' => 'name', 'page' => '3'];
        $post = [];
        $rawJson = '{"name": "Tata Steel Ltd", "state_code": "27", "credit_limit": 1000000}';

        $req = new Request($query, $post, $server, $rawJson);

        $assert('REQ-01', 'Method correctly parsed as POST', $req->getMethod() === 'POST');
        $assert('REQ-02', 'isMethod(POST) is true', $req->isMethod('POST'));
        $assert('REQ-03', 'isMethod(GET) is false', !$req->isMethod('GET'));
        $assert('REQ-04', 'Path cleanly extracts without query string', $req->getPath() === '/api/v1/customers');
        $assert('REQ-05', 'Query parameters accessible via query()', $req->query('sort') === 'name');
        $assert('REQ-06', 'Query parameter fallback works', $req->query('missing', 'def') === 'def');

        // 2. JSON Payload parsing
        $assert('REQ-07', 'JSON field parsed into json()', $req->json('name') === 'Tata Steel Ltd');
        $assert('REQ-08', 'JSON numeric field parsed into input()', $req->input('credit_limit') === 1000000);

        // 3. Header & Bearer token extraction
        $assert('REQ-09', 'Bearer token parsed from Authorization header', $req->getBearerToken() === 'sample_wtsbill_test_token_xyz');
        $assert('REQ-10', 'Header name case-insensitive extraction', $req->getHeader('content-type') === 'application/json');

        // 4. Client IP resolution
        $assert('REQ-11', 'Client IP parsed from REMOTE_ADDR', $req->getIp() === '10.0.0.42');

        // 5. expectsJson() detection
        $assert('REQ-12', 'expectsJson() detects /api/ path', $req->expectsJson() === true);
    }
}
