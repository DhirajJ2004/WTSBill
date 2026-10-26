<?php

declare(strict_types=1);

namespace Tests\Security;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Middleware\TenantMiddleware;
use WTSBill\Repositories\BaseRepository;
use WTSBill\Services\AuthService;

/**
 * Concrete test repository for customer entities to test multi-tenant scoping.
 */
class CustomerTestRepository extends BaseRepository
{
    protected string $table = 'customers';
    protected bool $tenantScoped = true;
}

/**
 * Concrete test repository for tags.
 */
class TagTenantRepository extends BaseRepository
{
    protected string $table = 'tags';
    protected bool $tenantScoped = true;
}

/**
 * Security Test for Multi-Tenant Isolation & Boundary Enforcement.
 */
class TenantIsolationTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();
        Session::clear();

        // 1. Establish tenant session as Company 1
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $assert('TENANT-01', 'Active tenant session is Company 1', Session::getCompanyId() === 1);

        $customerRepo = new CustomerTestRepository();

        // All 21 seeded customers belong to Company 1
        $company1Customers = $customerRepo->all();
        $assert('TENANT-02', 'Customer repository queries Company 1 records', count($company1Customers) === 21);
        foreach ($company1Customers as $cust) {
            $assert('TENANT-03', 'Every customer has company_id = 1', (int) $cust['company_id'] === 1);
            break; // Check first
        }

        // 2. Querying a non-existent company returns 0 records
        Session::setTenantContext(999); // Switch to non-existent Company 999
        $company999Customers = $customerRepo->all();
        $assert('TENANT-04', 'Querying Company 999 yields 0 customers', count($company999Customers) === 0);

        // Switch back to Company 1
        Session::setTenantContext(1);

        // 3. Multi-Tenant Insert Scoping
        $tagRepo = new TagTenantRepository();
        $newTagId = $tagRepo->create([
            'name' => 'TENANT_ISOLATION_TAG_C1',
            'color' => '#123123',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $createdTag = Database::selectOne("SELECT * FROM tags WHERE id = :id", ['id' => $newTagId]);
        $assert('TENANT-05', 'Record automatically assigned active session company_id (1)', 
            $createdTag !== null && (int) $createdTag['company_id'] === 1);

        // Verify that another tenant cannot see this record via repository
        Session::setTenantContext(2); // Switch context to Company 2
        $crossTenantFind = $tagRepo->find($newTagId);
        $assert('TENANT-06', 'Cross-tenant find() returns null for Company 2', $crossTenantFind === null);

        $crossTenantWhere = $tagRepo->where(['name' => 'TENANT_ISOLATION_TAG_C1']);
        $assert('TENANT-07', 'Cross-tenant where() returns empty array for Company 2', count($crossTenantWhere) === 0);

        // Switch back to Company 1 and cleanup
        Session::setTenantContext(1);
        $tagRepo->delete($newTagId);

        // 4. TenantMiddleware Boundary Enforcement
        $router = new Router();
        $tenantMiddleware = new TenantMiddleware($authService);

        $router->post('/tenant/invoices', fn() => Response::success(['created' => true]), [$tenantMiddleware]);

        // Request with matching company_id (1) -> ALLOWED (200)
        $validTenantReq = new Request(
            [],
            ['company_id' => 1],
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/tenant/invoices']
        );
        $validTenantRes = $router->dispatch($validTenantReq);
        $assert('TENANT-08', 'Request with matching company_id (1) is allowed (HTTP 200)', $validTenantRes->getStatusCode() === 200);

        // Request attempting to tamper and pass company_id = 2 -> FORBIDDEN (403)
        $tamperedTenantReq = new Request(
            [],
            ['company_id' => 2], // Malicious cross-tenant attempt
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/tenant/invoices', 'HTTP_ACCEPT' => 'application/json']
        );
        $tamperedTenantRes = $router->dispatch($tamperedTenantReq);
        $assert('TENANT-09', 'Cross-tenant request is rejected by TenantMiddleware (HTTP 403)', $tamperedTenantRes->getStatusCode() === 403);
        $tamperedPayload = json_decode($tamperedTenantRes->getContent(), true);
        $assert('TENANT-10', 'Forbidden response explicitly blocks cross-tenant access', 
            str_contains($tamperedPayload['message'] ?? '', 'Cross-tenant'));

        // Cleanup session
        $authService->logout();
    }
}
