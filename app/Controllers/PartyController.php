<?php

namespace App\Controllers;

use App\Services\PartyService;
use App\Repositories\PartyRepository;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\AuthorizationException;

class PartyController
{
    /**
     * Display Parties index page (Customers / Suppliers).
     */
    public function index(): void
    {
        $user = AuthMiddleware::handle();
        $tenant = TenantMiddleware::handle();
        $companyId = $tenant['company_id'];

        $tab = $_GET['tab'] ?? ($_GET['type'] === 'supplier' ? 'suppliers' : 'customers');
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? 'all';
        $taxType = $_GET['tax_type'] ?? 'all';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;

        $filters = [
            'search' => $search,
            'status' => $status,
            'tax_type' => $taxType,
        ];

        if ($tab === 'suppliers') {
            $result = PartyRepository::getSuppliers($companyId, $filters, $page, $perPage);
        } else {
            $result = PartyRepository::getCustomers($companyId, $filters, $page, $perPage);
        }

        if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json' || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            response_json([
                'status' => 'success',
                'data' => $result['data'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'last_page' => $result['last_page'],
                ]
            ]);
        }

        // Pass variables to view
        $parties = $result['data'];
        $pagination = $result;
        $activeTab = $tab;
        
        require __DIR__ . '/../../views/parties/index.php';
    }

    /**
     * Show Party Details with Transaction History.
     */
    public function show(int $id, string $type = 'customer'): void
    {
        $user = AuthMiddleware::handle();
        $tenant = TenantMiddleware::handle();
        $companyId = $tenant['company_id'];

        try {
            $details = PartyService::getPartyDetails($id, $type, $companyId);
            if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json' || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                response_json(['status' => 'success', 'data' => $details]);
            }
            return;
        } catch (AuthorizationException $e) {
            if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json' || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                response_json(['status' => 'error', 'message' => $e->getMessage()], $e->statusCode);
            }
            http_response_code($e->statusCode);
            echo "<!DOCTYPE html><html><body><h2>{$e->statusCode} Forbidden</h2><p>" . htmlspecialchars($e->getMessage()) . "</p></body></html>";
            exit();
        }
    }

    /**
     * Handle Store / Create Party.
     */
    public function store(): void
    {
        $isJson = (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
        $user = AuthMiddleware::handle($isJson);
        $tenant = TenantMiddleware::handle($isJson);
        $companyId = $tenant['company_id'];
        $branchId = $tenant['branch_id'];

        if (!$isJson) {
            CsrfMiddleware::handle();
        }

        $input = $isJson ? get_json_input() : $_POST;
        $type = strtolower(trim($input['type'] ?? ($input['party_role'] ?? 'customer')));
        $userName = $user->name ?? 'Admin';

        if ($type === 'supplier') {
            $result = PartyService::createSupplier($input, $companyId, $branchId, $userName);
        } else {
            $result = PartyService::createCustomer($input, $companyId, $branchId, $userName);
        }

        if ($isJson) {
            if ($result['success']) {
                response_json(['status' => 'success', 'data' => $result['customer'] ?? $result['supplier']], 201);
            } else {
                response_json(['status' => 'error', 'message' => $result['message'], 'errors' => $result['errors'] ?? []], 422);
            }
        }

        if ($result['success']) {
            $_SESSION['flash_success'] = $result['message'];
        } else {
            $_SESSION['flash_error'] = $result['message'];
        }

        header('Location: ' . (function_exists('url') ? url('/parties?tab=' . ($type === 'supplier' ? 'suppliers' : 'customers')) : '/parties'));
        exit();
    }

    /**
     * Handle Update Party.
     */
    public function update(int $id): void
    {
        $isJson = (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
        $user = AuthMiddleware::handle($isJson);
        $tenant = TenantMiddleware::handle($isJson);
        $companyId = $tenant['company_id'];

        if (!$isJson) {
            CsrfMiddleware::handle();
        }

        $input = $isJson ? get_json_input() : $_POST;
        $type = strtolower(trim($input['type'] ?? ($input['party_role'] ?? 'customer')));
        $userName = $user->name ?? 'Admin';

        try {
            if ($type === 'supplier') {
                $result = PartyService::updateSupplier($id, $input, $companyId, $userName);
            } else {
                $result = PartyService::updateCustomer($id, $input, $companyId, $userName);
            }

            if ($isJson) {
                if ($result['success']) {
                    response_json(['status' => 'success', 'data' => $result['customer'] ?? $result['supplier']]);
                } else {
                    response_json(['status' => 'error', 'message' => $result['message'], 'errors' => $result['errors'] ?? []], 422);
                }
            }

            if ($result['success']) {
                $_SESSION['flash_success'] = $result['message'];
            } else {
                $_SESSION['flash_error'] = $result['message'];
            }

            header('Location: ' . (function_exists('url') ? url('/parties?tab=' . ($type === 'supplier' ? 'suppliers' : 'customers')) : '/parties'));
            exit();
        } catch (AuthorizationException $e) {
            if ($isJson) {
                response_json(['status' => 'error', 'message' => $e->getMessage()], $e->statusCode);
            }
            http_response_code($e->statusCode);
            echo "<!DOCTYPE html><html><body><h2>{$e->statusCode} Forbidden</h2><p>" . htmlspecialchars($e->getMessage()) . "</p></body></html>";
            exit();
        }
    }

    /**
     * Handle Delete Party.
     */
    public function destroy(int $id): void
    {
        $isJson = (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
        $user = AuthMiddleware::handle($isJson);
        $tenant = TenantMiddleware::handle($isJson);
        $companyId = $tenant['company_id'];

        if (!$isJson) {
            CsrfMiddleware::handle();
        }

        $input = $isJson ? get_json_input() : $_POST;
        $type = strtolower(trim($input['type'] ?? ($_GET['type'] ?? 'customer')));
        $userName = $user->name ?? 'Admin';

        try {
            if ($type === 'supplier') {
                $result = PartyService::deleteSupplier($id, $companyId, $userName);
            } else {
                $result = PartyService::deleteCustomer($id, $companyId, $userName);
            }

            if ($isJson) {
                if ($result['success']) {
                    response_json(['status' => 'success', 'message' => $result['message']]);
                } else {
                    response_json(['status' => 'error', 'message' => $result['message']], 400);
                }
            }

            if ($result['success']) {
                $_SESSION['flash_success'] = $result['message'];
            } else {
                $_SESSION['flash_error'] = $result['message'];
            }

            header('Location: ' . (function_exists('url') ? url('/parties?tab=' . ($type === 'supplier' ? 'suppliers' : 'customers')) : '/parties'));
            exit();
        } catch (AuthorizationException $e) {
            if ($isJson) {
                response_json(['status' => 'error', 'message' => $e->getMessage()], $e->statusCode);
            }
            http_response_code($e->statusCode);
            echo "<!DOCTYPE html><html><body><h2>{$e->statusCode} Forbidden</h2><p>" . htmlspecialchars($e->getMessage()) . "</p></body></html>";
            exit();
        }
    }
}
