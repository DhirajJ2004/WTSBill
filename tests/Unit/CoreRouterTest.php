<?php

declare(strict_types=1);

namespace Tests\Unit;

use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;

/**
 * Unit Test for Core Router component.
 */
class CoreRouterTest
{
    public static function run(callable $assert): void
    {
        $router = new Router();

        // 1. Exact route match
        $router->get('/test-get', function (Request $r) {
            return Response::success(['matched' => 'get']);
        });

        $reqGet = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test-get']);
        $resGet = $router->dispatch($reqGet);
        $assert('ROUTER-01', 'Exact GET route matched', $resGet->getStatusCode() === 200);
        $dataGet = json_decode($resGet->getContent(), true);
        $assert('ROUTER-02', 'Exact GET payload matched', $dataGet['data']['matched'] === 'get');

        // 2. Parameterized route extraction
        $router->get('/users/{id}/profile', function (Request $r, $id) {
            return Response::success(['user_id' => (int) $id]);
        });

        $reqParam = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/789/profile']);
        $resParam = $router->dispatch($reqParam);
        $dataParam = json_decode($resParam->getContent(), true);
        $assert('ROUTER-03', 'Route parameter extracted cleanly', $dataParam['data']['user_id'] === 789);

        // 3. HTTP method mismatch returns 404
        $reqWrongMethod = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/test-get']);
        $resWrongMethod = $router->dispatch($reqWrongMethod);
        $assert('ROUTER-04', 'Mismatched HTTP method returns 404', $resWrongMethod->getStatusCode() === 404);

        // 4. Route groups with prefix
        $router->group(['prefix' => '/api/v1'], function (Router $r) {
            $r->post('/invoices', function (Request $req) {
                return Response::success(['created' => true], 'Created', 201);
            });
        });

        $reqGroup = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/v1/invoices']);
        $resGroup = $router->dispatch($reqGroup);
        $assert('ROUTER-05', 'Route group prefix applied correctly', $resGroup->getStatusCode() === 201);

        // 5. Middleware execution and pipeline short-circuit
        $middlewareExecuted = false;
        $router->get('/protected', function () {
            return Response::success(['protected' => true]);
        }, [
            function (Request $r) use (&$middlewareExecuted) {
                $middlewareExecuted = true;
                // Allow through
                return null;
            }
        ]);

        $reqMiddleware = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/protected']);
        $router->dispatch($reqMiddleware);
        $assert('ROUTER-06', 'Route middleware invoked in pipeline', $middlewareExecuted === true);

        // 6. Middleware blocking with response
        $router->get('/blocked', function () {
            return Response::success(['should_not_reach' => true]);
        }, [
            function (Request $r) {
                return Response::error('Access Denied', 403);
            }
        ]);

        $reqBlocked = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/blocked']);
        $resBlocked = $router->dispatch($reqBlocked);
        $assert('ROUTER-07', 'Middleware blocks and returns 403 response', $resBlocked->getStatusCode() === 403);

        // 7. 404 JSON for API request vs HTML for Web request
        $reqApi404 = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/unknown', 'HTTP_ACCEPT' => 'application/json']);
        $resApi404 = $router->dispatch($reqApi404);
        $json404 = json_decode($resApi404->getContent(), true);
        $assert('ROUTER-08', '404 for API returns JSON envelope', $resApi404->getStatusCode() === 404 && isset($json404['success']));

        $reqWeb404 = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/unknown-page', 'HTTP_ACCEPT' => 'text/html']);
        $resWeb404 = $router->dispatch($reqWeb404);
        $assert('ROUTER-09', '404 for Web returns HTML', $resWeb404->getStatusCode() === 404 && str_contains($resWeb404->getContent(), '<h1>404'));
    }
}
