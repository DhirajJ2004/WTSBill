<?php
if (!function_exists('enum_exists')) {
    function enum_exists(string $enum, bool $autoload = true): bool
    {
        return false;
    }
}

if (!function_exists('url')) {
    function url($path = '')
    {
        $path = '/' . ltrim($path, '/');
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $subDir = rtrim(dirname($scriptName), '/\\');
        if (strpos($subDir, '/backend/public') !== false) {
            $base = str_replace('/backend/public', '', $subDir);
        } elseif ($subDir !== '/' && $subDir !== '\\' && $subDir !== '.') {
            $base = $subDir;
        } else {
            $base = '';
        }
        $base = rtrim(str_replace('\\', '/', $base), '/');
        if (empty($base) && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/WTSBill') === 0) {
            $base = '/WTSBill';
        }
        return $base . $path;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * CSRF Protection Helpers for state-changing web requests
 */
if (!function_exists('get_csrf_token')) {
    function get_csrf_token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return get_csrf_token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token = null): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $expected = $_SESSION['csrf_token'] ?? '';
        if (empty($expected)) {
            return false;
        }
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        if (empty($token)) {
            return false;
        }
        return hash_equals($expected, $token);
    }
}

if (!function_exists('validate_csrf_or_abort')) {
    function validate_csrf_or_abort(): void
    {
        if (in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            if (!verify_csrf_token()) {
                http_response_code(403);
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body style="font-family: sans-serif; text-align: center; padding: 50px;"><h2 style="color: #dc2626;">403 Forbidden</h2><p>Invalid or missing CSRF token. Please refresh the page and try again.</p><p><a href="javascript:history.back()">Go Back</a></p></body></html>';
                exit();
            }
        }
    }
}

/**
 * XSS Safe Output Escaping Helper
 */
if (!function_exists('e')) {
    function e($val): string
    {
        return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

require_once __DIR__ . '/../backend/vendor/autoload.php';
use Illuminate\Database\Capsule\Manager as DB;
use App\Database\Database;

try {
    Database::init();
} catch (\Throwable $e) {
    error_log("[WTSBill db_helper DB Warning] " . $e->getMessage());
}

use App\Auth\WorkspaceContext;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AuthorizationException;

/**
 * Multi-Company & Workspace Session Management
 */
/**
 * Multi-Company & Workspace Session Management
 */
function get_all_companies()
{
    $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        if (isset($GLOBALS['_wts_all_companies_cache'][$userId])) {
            return $GLOBALS['_wts_all_companies_cache'][$userId];
        }
        $authCompanies = WorkspaceContext::getAuthorizedCompanies($userId);
        return $GLOBALS['_wts_all_companies_cache'][$userId] = ($authCompanies ?: []);
    }

    return [];
}

function get_company_by_id($id)
{
    $id = (int)$id;
    $companies = get_all_companies();
    foreach ($companies as $c) {
        if ((int)$c['id'] === $id) {
            return $c;
        }
    }
    return null;
}

function get_current_company()
{
    $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    $activeId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : null;
    $cacheKey = "{$userId}_{$activeId}";
    if (isset($GLOBALS['_wts_cur_company_cache'][$cacheKey])) {
        return $GLOBALS['_wts_cur_company_cache'][$cacheKey];
    }

    $companies = get_all_companies();
    if (empty($companies)) {
        return null;
    }

    if ($activeId === null && isset($_COOKIE['wts_company_id'])) {
        $activeId = (int)$_COOKIE['wts_company_id'];
    }

    if ($activeId !== null && $userId > 0) {
        // Enforce membership verification if logged in
        if (WorkspaceContext::verifyCompanyMembership($userId, $activeId)) {
            foreach ($companies as $company) {
                if ((int)$company['id'] === $activeId) {
                    return $GLOBALS['_wts_cur_company_cache'][$cacheKey] = $company;
                }
            }
        }
    }

    // Default to the first authorized company
    $first = $companies[0];
    $_SESSION['company_id'] = (int)$first['id'];
    $_SESSION['company_name'] = $first['name'];
    return $GLOBALS['_wts_cur_company_cache'][$cacheKey] = $first;
}

function get_current_company_id(): int
{
    $comp = get_current_company();
    return (int)($comp['id'] ?? 0);
}

function ensure_company_access(?int $requestedCompanyId = null): int
{
    $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    $currentCompId = get_current_company_id();

    if ($requestedCompanyId !== null && $requestedCompanyId > 0) {
        if ($userId > 0 && !WorkspaceContext::verifyCompanyMembership($userId, $requestedCompanyId)) {
            http_response_code(403);
            throw new AuthorizationException("Forbidden: User is not authorized to access Company #{$requestedCompanyId}.", 403);
        }
        return $requestedCompanyId;
    }

    return $currentCompId;
}

function get_current_branches(): array
{
    $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    $companyId = get_current_company_id();
    if ($userId > 0 && $companyId > 0) {
        $cacheKey = "{$userId}_{$companyId}";
        if (isset($GLOBALS['_wts_cur_branches_cache'][$cacheKey])) {
            return $GLOBALS['_wts_cur_branches_cache'][$cacheKey];
        }
        return $GLOBALS['_wts_cur_branches_cache'][$cacheKey] = WorkspaceContext::getAuthorizedBranches($userId, $companyId);
    }
    return [];
}

function get_current_branch(): ?array
{
    $branches = get_current_branches();
    if (empty($branches)) return null;

    $activeBranchId = isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : null;
    if ($activeBranchId !== null) {
        foreach ($branches as $b) {
            if ((int)$b['id'] === $activeBranchId) {
                return $b;
            }
        }
    }

    // Default to first authorized branch
    $first = $branches[0];
    $_SESSION['branch_id'] = (int)$first['id'];
    return $first;
}

function get_current_branch_id(): int
{
    $b = get_current_branch();
    return (int)($b['id'] ?? 0);
}

function get_current_financial_year()
{
    $selectedYear = $_SESSION['financial_year'] ?? null;
    if ($selectedYear) {
        return $selectedYear;
    }

    $companyId = get_current_company_id();
    if (isset($GLOBALS['_wts_cur_fy_cache'][$companyId])) {
        return $GLOBALS['_wts_cur_fy_cache'][$companyId];
    }

    try {
        $fy = DB::table('accounting_periods')
            ->where('company_id', $companyId)
            ->where('is_closed', 0)
            ->orderBy('start_date')
            ->value('financial_year');
        if ($fy) return $GLOBALS['_wts_cur_fy_cache'][$companyId] = $fy;
    } catch (\Throwable $e) {
    }

    $month = (int)date('m');
    $year = (int)date('Y');
    $fyStart = $month >= 4 ? $year : $year - 1;
    $fyEnd = ($fyStart + 1) % 100;
    return $GLOBALS['_wts_cur_fy_cache'][$companyId] = sprintf('%04d-%02d', $fyStart, $fyEnd);
}

function get_financial_years()
{
    $companyId = get_current_company_id();
    if (isset($GLOBALS['_wts_fys_cache'][$companyId])) {
        return $GLOBALS['_wts_fys_cache'][$companyId];
    }

    try {
        $periods = DB::table('accounting_periods')
            ->where('company_id', $companyId)
            ->orderBy('start_date')
            ->get();
        $years = [];
        foreach ($periods as $period) {
            $year = $period->financial_year;
            if (!isset($years[$year])) {
                $closed = (bool) $period->is_closed;
                $years[$year] = [
                    'code' => $year,
                    'title' => $year,
                    'dates' => date('d-M-Y', strtotime($period->start_date)) . ' to ' . date('d-M-Y', strtotime($period->end_date)),
                    'status' => $closed ? 'Closed' : 'Current',
                    'badge' => $closed ? 'CLOSED' : 'CURRENT'
                ];
            }
        }
        if (!empty($years)) {
            return $GLOBALS['_wts_fys_cache'][$companyId] = array_values($years);
        }
    } catch (\Throwable $e) {
    }

    $curFy = get_current_financial_year();
    return $GLOBALS['_wts_fys_cache'][$companyId] = [
        [
            'code' => $curFy,
            'title' => 'FY ' . $curFy,
            'dates' => '01-Apr to 31-Mar',
            'status' => 'Current',
            'badge' => 'CURRENT'
        ]
    ];
}

function get_current_user_info()
{
    return $_SESSION['user'] ?? [];
}

function set_active_workspace($companyId, $financialYear, $userRole = null, $branchId = null)
{
    $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    $companyId = (int)$companyId;

    if ($userId <= 0) {
        throw new AuthorizationException("Unauthorized: Authentication required to select workspace.", 401);
    }

    if ($companyId <= 0 || !WorkspaceContext::verifyCompanyMembership($userId, $companyId)) {
        throw new AuthorizationException("Forbidden: User is not authorized to access Company #{$companyId}.", 403);
    }

    $branches = WorkspaceContext::getAuthorizedBranches($userId, $companyId);
    if ($branchId !== null && (int)$branchId > 0) {
        if (!WorkspaceContext::verifyBranchAccess($userId, $companyId, (int)$branchId)) {
            throw new AuthorizationException("Forbidden: User does not have access to Branch #{$branchId}.", 403);
        }
        $branchId = (int)$branchId;
    } else {
        $branchId = !empty($branches) ? (int)$branches[0]['id'] : null;
    }

    $userRole = WorkspaceContext::getUserRoleForCompany($userId, $companyId) ?: ($userRole ?? 'ADMIN');
    $perms = WorkspaceContext::getUserPermissionsForCompany($userId, $companyId);

    $company = DB::table('companies')->where('id', $companyId)->first();
    if (!$company) {
        throw new AuthorizationException("Forbidden: Company not found.", 403);
    }
    if ($company->status !== 'ACTIVE') {
        throw new AuthorizationException("Forbidden: Company is not active.", 403);
    }

    $_SESSION['company_id'] = $companyId;
    $_SESSION['company_name'] = $company->name;
    $_SESSION['company_gstin'] = $company->gstin ?? '';
    $_SESSION['branch_id'] = $branchId;
    $_SESSION['financial_year'] = $financialYear;
    $_SESSION['user_role'] = $userRole;
    $_SESSION['permissions'] = $perms;

    // Set cookie if headers not yet sent
    if (!headers_sent()) {
        setcookie('wts_company_id', (string) $companyId, time() + 86400 * 30, '/');
        setcookie('wts_branch_id', (string) $branchId, time() + 86400 * 30, '/');
        setcookie('wts_financial_year', (string) $financialYear, time() + 86400 * 30, '/');
    }
}

function create_new_company($data)
{
    $userId = (int)($_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? 0);
    $existingUser = $userId > 0 ? \App\Models\User::find($userId) : null;
    if (!$existingUser && !empty($_SESSION['user']['email'])) {
        $existingUser = \App\Models\User::where('email', $_SESSION['user']['email'])->first();
    }

    $result = \App\Services\CompanyProvisioningService::provisionCompany($data, $existingUser);
    if (!$result['success']) {
        throw new \RuntimeException($result['message'] ?? 'Failed to provision company.');
    }

    return $result['company'];
}

function get_db_metrics()
{
    $companyId = get_current_company_id();
    if (isset($GLOBALS['_wts_metrics_cache'][$companyId])) {
        return $GLOBALS['_wts_metrics_cache'][$companyId];
    }

    try {
        // Consolidated Aggregated Queries for high performance
        $salesAgg = DB::table('invoices')
            ->where('company_id', $companyId)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN grand_total ELSE 0 END), 0) as total_sales,
                COALESCE(SUM(CASE WHEN status IN ('pending', 'partially_paid', 'overdue', 'UNPAID') THEN grand_total ELSE 0 END), 0) as receivables,
                COUNT(*) as invoice_count
            ")
            ->first();

        $purchaseAgg = DB::table('purchases')
            ->where('company_id', $companyId)
            ->selectRaw("
                COALESCE(SUM(grand_total), 0) as total_purchases,
                COALESCE(SUM(CASE WHEN status IN ('pending', 'partially_paid', 'UNPAID') THEN grand_total ELSE 0 END), 0) as payables
            ")
            ->first();

        $productAgg = DB::table('products')
            ->where('company_id', $companyId)
            ->selectRaw("
                COALESCE(SUM(current_stock * purchase_price), 0) as inventory_value,
                COALESCE(SUM(CASE WHEN current_stock <= min_stock_alert THEN 1 ELSE 0 END), 0) as low_stock_count
            ")
            ->first();

        $totalExpenses = (float) DB::table('expenses')->where('company_id', $companyId)->sum('amount');
        $paidCollections = (float) DB::table('payments')->where('company_id', $companyId)->sum('amount');
        $pendingQuotesCount = (int) DB::table('quotations')->where('company_id', $companyId)->where('status', 'sent')->count();
        $activeSubscriptions = (int) DB::table('recurring_invoices')->where('company_id', $companyId)->where('status', 'active')->count();

        $totalSales = (float) ($salesAgg->total_sales ?? 0);
        $receivables = (float) ($salesAgg->receivables ?? 0);
        $totalInvoicesCount = (int) ($salesAgg->invoice_count ?? 0);

        $totalPurchases = (float) ($purchaseAgg->total_purchases ?? 0);
        $payables = (float) ($purchaseAgg->payables ?? 0);

        $inventoryValue = (float) ($productAgg->inventory_value ?? 0);
        $lowStockCount = (int) ($productAgg->low_stock_count ?? 0);

        $netProfit = $totalSales - $totalExpenses;

        $res = [
            'total_sales' => $totalSales,
            'total_purchases' => $totalPurchases,
            'paid_collections' => $paidCollections,
            'receivables' => $receivables,
            'payables' => $payables,
            'expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'invoice_count' => $totalInvoicesCount,
            'quote_count' => $pendingQuotesCount,
            'active_subscriptions' => $activeSubscriptions,
            'low_stock_count' => $lowStockCount,
            'inventory_value' => $inventoryValue
        ];

        return $GLOBALS['_wts_metrics_cache'][$companyId] = $res;
    } catch (\Throwable $e) {
        return [
            'total_sales' => 0,
            'total_purchases' => 0,
            'paid_collections' => 0,
            'receivables' => 0,
            'payables' => 0,
            'expenses' => 0,
            'net_profit' => 0,
            'invoice_count' => 0,
            'quote_count' => 0,
            'active_subscriptions' => 0,
            'low_stock_count' => 0,
            'inventory_value' => 0
        ];
    }
}

function get_customers()
{
    try {
        $customers = DB::table('customers')->where('company_id', get_current_company_id())->orderBy('name', 'asc')->get();
        $list = json_decode(json_encode($customers), true);
        foreach ($list as &$c) {
            $c['balance'] = (float) ($c['current_balance'] ?? $c['opening_balance'] ?? 0);
            $c['category'] = strtolower($c['customer_type'] ?? '');
        }
        unset($c);
        return $list;
    } catch (\Throwable $e) {
        return [];
    }
}

function get_suppliers()
{
    try {
        $suppliers = DB::table('suppliers')->where('company_id', get_current_company_id())->orderBy('name', 'asc')->get();
        $list = json_decode(json_encode($suppliers), true);
        foreach ($list as &$s) {
            $s['balance'] = (float) ($s['current_balance'] ?? $s['opening_balance'] ?? 0);
        }
        unset($s);
        return $list;
    } catch (\Throwable $e) {
        return [];
    }
}

function get_products()
{
    try {
        $products = DB::table('products')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.company_id', get_current_company_id())
            ->select('products.*', 'categories.name as category_name')
            ->orderBy('products.name', 'asc')
            ->get();
        $list = json_decode(json_encode($products), true);
        foreach ($list as &$item) {
            $item['price'] = (float) ($item['selling_price'] ?? $item['sales_price'] ?? $item['purchase_price'] ?? 0);
            $item['type'] = strtolower($item['product_type'] ?? '');
        }
        unset($item);
        return $list;
    } catch (\Throwable $e) {
        return [];
    }
}

function get_invoices()
{
    try {
        $invoices = DB::table('invoices')
            ->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->where('invoices.company_id', get_current_company_id())
            ->select('invoices.*', 'customers.name as customer_name', 'customers.gstin as customer_gstin')
            ->orderBy('invoices.id', 'desc')
            ->get();
        $list = json_decode(json_encode($invoices), true);
        foreach ($list as &$inv) {
            $inv['tax_total'] = (float) ($inv['total_tax'] ?? $inv['tax_total'] ?? 0);
            $inv['subtotal'] = (float) ($inv['sub_total'] ?? $inv['subtotal'] ?? 0);
        }
        unset($inv);
        return $list;
    } catch (\Throwable $e) {
        return [];
    }
}

function get_quotations()
{
    try {
        $quotes = DB::table('quotations')
            ->leftJoin('customers', 'quotations.customer_id', '=', 'customers.id')
            ->where('quotations.company_id', get_current_company_id())
            ->select('quotations.*', 'customers.name as customer_name')
            ->orderBy('quotations.id', 'desc')
            ->get();
        return json_decode(json_encode($quotes), true);
    } catch (\Throwable $e) {
        return [];
    }
}

function get_payments()
{
    try {
        $payments = DB::table('payments')
            ->leftJoin('customers', function ($join) {
                $join->on('payments.party_id', '=', 'customers.id')
                     ->where('payments.party_type', '=', 'CUSTOMER');
            })
            ->leftJoin('payment_allocations', 'payments.id', '=', 'payment_allocations.payment_id')
            ->leftJoin('invoices', 'payment_allocations.invoice_id', '=', 'invoices.id')
            ->where('payments.company_id', get_current_company_id())
            ->select('payments.*', 'customers.name as customer_name', 'invoices.invoice_number')
            ->orderBy('payments.id', 'desc')
            ->get();
        return json_decode(json_encode($payments), true);
    } catch (\Throwable $e) {
        return [];
    }
}

function get_expenses()
{
    try {
        $expenses = DB::table('expenses')->where('company_id', get_current_company_id())->orderBy('id', 'desc')->get();
        $list = json_decode(json_encode($expenses), true);
        foreach ($list as &$expense) {
            $expense['vendor'] = $expense['payee'] ?? $expense['vendor'] ?? '';
            $expense['status'] = $expense['status'] ?? '';
        }
        return $list;
    } catch (\Throwable $e) {
        return [];
    }
}

function get_users()
{
    try {
        $companyId = get_current_company_id();
        $users = DB::table('users')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->where('user_roles.company_id', $companyId)
            ->select('users.*')
            ->distinct()
            ->get();
        $list = json_decode(json_encode($users), true);
        foreach ($list as &$user) {
            $user['last_login'] = $user['last_login_at'] ?? $user['last_login'] ?? null;
            $user['status'] = $user['status'] ?? (($user['is_active'] ?? false) ? 'active' : 'inactive');
        }
        return $list;
    } catch (\Throwable $e) {
        return [];
    }
}

function get_audit_logs()
{
    try {
        $logs = DB::table('audit_logs')->where('company_id', get_current_company_id())->orderBy('id', 'desc')->get();
        $list = json_decode(json_encode($logs), true);
        foreach ($list as &$log) {
            $log['timestamp'] = $log['created_at'] ?? '';
            $log['module'] = $log['entity_type'] ?? '';
            $log['details'] = $log['description'] ?? '';
        }
        return $list;
    } catch (\Throwable $e) {
        return [];
    }
}

if (!function_exists('getIndianWords')) {
    function getIndianWords(float $number): string
    {
        return \App\Services\NumberToWordsService::toIndianWords($number);
    }
}

if (!function_exists('component')) {
    function component(string $__component_name, array $__component_data = []): string {
        $__component_file = __DIR__ . '/components/' . str_replace('.', '/', $__component_name) . '.php';
        if (!file_exists($__component_file)) {
            $__component_file = __DIR__ . '/components/' . $__component_name . '.php';
        }
        if (file_exists($__component_file)) {
            extract($__component_data);
            ob_start();
            include $__component_file;
            return ob_get_clean();
        }
        return "<!-- Component [{$__component_name}] not found -->";
    }
}

if (!function_exists('render_component')) {
    function render_component(string $name, array $data = []): void {
        echo component($name, $data);
    }
}

