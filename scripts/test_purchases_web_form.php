<?php
/**
 * Test Web Form POST /purchases handler in views/purchases/purchases.php
 */

require_once __DIR__ . '/../backend/public/index.php';

use App\Models\Company;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Purchase;
use Illuminate\Database\Capsule\Manager as DB;

$company = Company::withoutGlobalScopes()->first();
$companyId = $company->id;
$branchId = 1;

$_SESSION['user_id'] = 1;
$_SESSION['user'] = [
    'id' => 1,
    'name' => 'Web Tester',
    'role' => 'admin',
    'company_id' => $companyId,
    'current_company_id' => $companyId,
    'branch_id' => $branchId,
];
$_SESSION['company_id'] = $companyId;
$_SESSION['branch_id'] = $branchId;
$_SESSION['financial_year'] = '2026-27';

$supplier = Supplier::withoutGlobalScopes()->where('company_id', $companyId)->first();
$warehouse = Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', 1)->first();
$product = Product::withoutGlobalScopes()->where('company_id', $companyId)->first();

echo "Testing Web Form POST /purchases ...\n";

$billNo = 'WEB-BILL-' . rand(100000, 999999);
$qty = 8;
$rate = 320.00;

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/purchases';
$_POST = [
    'action' => 'save_purchase',
    'supplier_id' => $supplier->id,
    'warehouse_id' => $warehouse->id,
    'bill_no' => $billNo,
    'bill_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+15 days')),
    'item_product_id' => [$product->id],
    'item_quantity' => [$qty],
    'item_price' => [$rate],
    'item_gst_rate' => [18],
    'item_discount' => [0],
    'notes' => 'Created via web form test',
];

// Capture output
ob_start();
try {
    include __DIR__ . '/../views/purchases/purchases.php';
} catch (\Throwable $e) {
    // Might exit or redirect
}
$output = ob_get_clean();

// Check if purchase was created in DB
$created = Purchase::withoutGlobalScopes()
    ->where('company_id', $companyId)
    ->where('vendor_invoice_number', $billNo)
    ->first();

if ($created) {
    echo "SUCCESS: Purchase Bill created via web form with ID #{$created->id} (Purchase #{$created->purchase_number})!\n";
    echo "Grand Total: ₹{$created->grand_total}, Status: {$created->status}\n";
    exit(0);
} else {
    echo "FAILED: Purchase was not found in DB.\n";
    exit(1);
}
