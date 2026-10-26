<?php

declare(strict_types=1);

namespace Tests\Security;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Middleware\CsrfMiddleware;

/**
 * Security Test for Cross-Site Request Forgery (CSRF) Protection.
 */
class CsrfProtectionTest
{
    public static function run(callable $assert): void
    {
        Session::start();
        Session::clear();
        $validToken = Session::csrfToken();

        $router = new Router();
        $csrf = new CsrfMiddleware(['/api/*']);

        $router->post('/submit-form', fn() => Response::success(['saved' => true]), [$csrf]);
        $router->get('/view-form', fn() => Response::html('<form></form>'), [$csrf]);
        $router->post('/api/webhook', fn() => Response::success(['webhook' => true]), [$csrf]);

        // 1. Missing CSRF token rejected (419)
        $noTokenReq = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit-form']);
        $noTokenRes = $router->dispatch($noTokenReq);
        $assert('CSRF-01', 'Missing CSRF token on POST returns HTTP 419', $noTokenRes->getStatusCode() === 419);

        // 2. Invalid CSRF token rejected (419)
        $invalidTokenReq = new Request([], ['_csrf_token' => 'invalid_tampered_token_xyz'], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit-form']);
        $invalidTokenRes = $router->dispatch($invalidTokenReq);
        $assert('CSRF-02', 'Invalid CSRF token on POST returns HTTP 419', $invalidTokenRes->getStatusCode() === 419);

        // 3. Valid CSRF token in POST body accepted (200)
        $validBodyReq = new Request([], ['_csrf_token' => $validToken], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit-form']);
        $validBodyRes = $router->dispatch($validBodyReq);
        $assert('CSRF-03', 'Valid CSRF token in body is accepted (HTTP 200)', $validBodyRes->getStatusCode() === 200);

        // 4. Valid CSRF token in X-CSRF-TOKEN header accepted (200)
        $validHeaderReq = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/submit-form', 'HTTP_X_CSRF_TOKEN' => $validToken]);
        $validHeaderRes = $router->dispatch($validHeaderReq);
        $assert('CSRF-04', 'Valid CSRF token in X-CSRF-TOKEN header is accepted (HTTP 200)', $validHeaderRes->getStatusCode() === 200);

        // 5. Safe GET method passes without token (200)
        $getReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/view-form']);
        $getRes = $router->dispatch($getReq);
        $assert('CSRF-05', 'Safe GET request does not require CSRF token', $getRes->getStatusCode() === 200);

        // 6. Exempt /api/* route passes without token
        $exemptReq = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/webhook']);
        $exemptRes = $router->dispatch($exemptReq);
        $assert('CSRF-06', 'Exempt API route passes POST without CSRF token', $exemptRes->getStatusCode() === 200);
    }
}
