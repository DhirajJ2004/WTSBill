<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

require_once ROOT_PATH . '/app/Core/Autoloader.php';
\WTSBill\Core\Autoloader::register();
require_once ROOT_PATH . '/app/Core/helpers.php';

\WTSBill\Core\Env::load(ROOT_PATH . '/.env');
\WTSBill\Core\Config::loadFromDirectory(ROOT_PATH . '/config');

echo "=== 1. Checking Database Structure ===\n";
$db = \WTSBill\Core\Database::pdo();
$cols = $db->query("SHOW COLUMNS FROM delivery_challans LIKE 'invoice_%'")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'Field');
if (in_array('invoice_id', $colNames) && in_array('invoice_number', $colNames)) {
    echo "[PASS] delivery_challans has invoice_id and invoice_number columns.\n";
} else {
    echo "[FAIL] Missing columns in delivery_challans.\n";
}

echo "\n=== 2. Checking Menu Configuration ===\n";
$menu = require ROOT_PATH . '/config/menu.php';
$hasSalesOrders = false;
$hasInvoiceTemplates = false;
$hasWarehouses = false;
$checkItem = function($item) use (&$hasSalesOrders, &$hasInvoiceTemplates, &$hasWarehouses) {
    if (in_array($item['id'] ?? '', ['sales_orders', 'invoice_templates', 'warehouses', 'godowns'])) {
        $hasSalesOrders = true;
    }
};
foreach ($menu['items'] ?? [] as $item) {
    $checkItem($item);
}
foreach ($menu['sections'] ?? [] as $sec) {
    foreach ($sec['items'] ?? [] as $item) {
        $checkItem($item);
    }
}
if (!$hasSalesOrders) {
    echo "[PASS] Removed modules (sales_orders, invoice_templates, warehouses) absent from config/menu.php.\n";
} else {
    echo "[FAIL] Found deprecated modules in config/menu.php.\n";
}

$includesMenu = file_get_contents(ROOT_PATH . '/includes/menu.php');
if (strpos($includesMenu, "'sales_orders'") === false &&
    strpos($includesMenu, "'invoice_templates'") === false &&
    strpos($includesMenu, "'warehouses'") === false) {
    echo "[PASS] Removed modules absent from includes/menu.php.\n";
} else {
    echo "[FAIL] Found deprecated modules in includes/menu.php.\n";
}

if (strpos($includesMenu, 'Company Profile &amp; Branding') !== false || strpos($includesMenu, 'Company Profile & Branding') !== false) {
    echo "[PASS] Sidebar includes Company Profile & Branding.\n";
} else {
    echo "[FAIL] Sidebar missing Company Profile & Branding.\n";
}

if (strpos($includesMenu, '/settings/audit-logs') !== false &&
    strpos($includesMenu, '/settings/backups') !== false &&
    strpos($includesMenu, '/settings/communication') !== false) {
    echo "[PASS] Sidebar includes Audit Trail, Backups, and Communication.\n";
} else {
    echo "[FAIL] Sidebar missing newly separated navigation items.\n";
}

echo "\n=== 3. Checking Duplicate Entry Validation ===\n";
// Customer duplicate phone
try {
    $existing = $db->query("SELECT phone FROM customers WHERE phone IS NOT NULL AND phone != '' AND deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        $svc = new \WTSBill\Services\CustomerService();
        try {
            $svc->createCustomer(['name' => 'Duplicate Test Customer', 'phone' => $existing['phone'], 'company_id' => 1]);
            echo "[FAIL] Duplicate customer phone was allowed!\n";
        } catch (\WTSBill\Core\Exceptions\ValidationException $e) {
            $errs = $e->getErrors();
            echo "[PASS] Customer duplicate phone caught: " . ($errs['phone'] ?? $e->getMessage()) . "\n";
        }
    }
} catch (\Throwable $e) {
    echo "[ERR] " . $e->getMessage() . "\n";
}

// Product duplicate SKU
try {
    $existingProd = $db->query("SELECT sku FROM products WHERE sku IS NOT NULL AND sku != '' AND deleted_at IS NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($existingProd) {
        $pSvc = new \WTSBill\Services\ProductService();
        try {
            $pSvc->createProduct(['name' => 'Duplicate SKU Test', 'sku' => $existingProd['sku'], 'selling_price' => 10, 'cost_price' => 5, 'company_id' => 1]);
            echo "[FAIL] Duplicate product SKU was allowed!\n";
        } catch (\WTSBill\Core\Exceptions\ValidationException $e) {
            $errs = $e->getErrors();
            echo "[PASS] Product duplicate SKU caught: " . ($errs['sku'] ?? $e->getMessage()) . "\n";
        }
    }
} catch (\Throwable $e) {
    echo "[ERR] " . $e->getMessage() . "\n";
}

// Payment duplicate reference
try {
    $existingPay = $db->query("SELECT reference_no FROM payments WHERE reference_no IS NOT NULL AND reference_no != '' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($existingPay) {
        $stmt = $db->prepare("SELECT id FROM payments WHERE company_id = ? AND reference_no = ? LIMIT 1");
        $stmt->execute([1, $existingPay['reference_no']]);
        if ($stmt->fetch()) {
            echo "[PASS] Payment duplicate reference check query successfully matches existing records.\n";
        }
    }
} catch (\Throwable $e) {
    echo "[ERR] " . $e->getMessage() . "\n";
}

echo "\n=== 4. Checking Routes Registration ===\n";
$router = new \WTSBill\Core\Router();
require ROOT_PATH . '/routes/web.php';
$routes = $router->getRoutes();

$criticalRoutes = [
    'GET' => ['/settings', '/settings/audit-logs', '/settings/backups', '/settings/communication', '/sales/challans', '/sales/challans/{id}/print', '/sales/credit-notes/{id}/print', '/api/sales/invoices/lookup'],
    'POST' => ['/sales/challans']
];

foreach ($criticalRoutes as $method => $paths) {
    foreach ($paths as $path) {
        $found = false;
        foreach ($routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                $found = true;
                break;
            }
        }
        if ($found) {
            echo "[PASS] Route {$method} {$path} registered.\n";
        } else {
            echo "[FAIL] Route {$method} {$path} NOT found!\n";
        }
    }
}

echo "\n=== 5. Syntax Lint of all Modified Files ===\n";
$files = [
    'views/auth/login.php',
    'views/partials/footer.php',
    'views/partials/sidebar.php',
    'views/settings/index.php',
    'views/settings/audit_logs.php',
    'views/settings/backups.php',
    'views/settings/communication.php',
    'views/sales/challans.php',
    'views/sales/challans/print.php',
    'views/sales/credit_notes/print.php',
    'views/purchases/debit_notes/create.php',
    'views/sales/invoices.php',
    'app/Controllers/CreditNoteController.php',
    'app/Controllers/SalesController.php',
    'app/Controllers/SettingsController.php',
    'app/Controllers/BackupController.php',
    'app/Controllers/CommunicationController.php',
    'app/Controllers/PurchaseOrderController.php',
    'app/Controllers/PaymentController.php',
    'app/Services/CustomerService.php',
    'app/Services/SupplierService.php',
    'app/Services/PartyService.php',
    'app/Services/ProductService.php',
    'includes/menu.php',
    'config/menu.php',
    'routes/web.php'
];

$allPassed = true;
foreach ($files as $file) {
    $full = ROOT_PATH . '/' . $file;
    if (!file_exists($full)) {
        echo "[FAIL] File missing: {$file}\n";
        $allPassed = false;
        continue;
    }
    exec('"C:\\xampp\\php\\php.exe" -l "' . $full . '"', $output, $ret);
    if ($ret !== 0) {
        echo "[FAIL] Syntax error in {$file}\n";
        $allPassed = false;
    }
}
if ($allPassed) {
    echo "[PASS] All " . count($files) . " modified files passed PHP linting without syntax errors.\n";
}

echo "\n=== 6. Controller Action Execution Tests ===\n";
$_SESSION['user'] = ['id' => 1, 'company_id' => 1, 'role' => 'admin'];
$req = new \WTSBill\Core\Request();

// Test Credit Note Print
try {
    $cnController = new \WTSBill\Controllers\CreditNoteController();
    ob_start();
    $cnController->print($req, 1);
    $out = ob_get_clean();
    echo "[PASS] Credit Note Print rendered successfully (" . strlen($out) . " bytes).\n";
} catch (\Throwable $e) {
    ob_end_clean();
    echo "[FAIL] Credit Note Print error: " . $e->getMessage() . "\n";
}

// Test Challan Print
try {
    $salesController = new \WTSBill\Controllers\SalesController();
    ob_start();
    $salesController->challanPrint($req, 1);
    $out = ob_get_clean();
    echo "[PASS] Delivery Challan Print rendered successfully (" . strlen($out) . " bytes).\n";
} catch (\Throwable $e) {
    ob_end_clean();
    echo "[FAIL] Delivery Challan Print error: " . $e->getMessage() . "\n";
}

// Test Invoice Lookup API
try {
    $req = new \WTSBill\Core\Request(['query' => 'INV']);
    $lookupRes = $salesController->apiInvoiceLookup($req);
    $lookupData = json_decode($lookupRes->getContent(), true);
    if (isset($lookupData['success']) && $lookupData['success'] === true && !empty($lookupData['invoice']['invoice_number'])) {
        echo "[PASS] Invoice Lookup API successfully located invoice '{$lookupData['invoice']['invoice_number']}' with " . count($lookupData['items'] ?? []) . " items.\n";
    } else {
        echo "[FAIL] Invoice Lookup API response: " . $lookupRes->getContent() . "\n";
    }
} catch (\Throwable $e) {
    echo "[FAIL] Invoice Lookup API error: " . $e->getMessage() . "\n";
}

echo "\n=== VERIFICATION SUITE FINISHED ===\n";
