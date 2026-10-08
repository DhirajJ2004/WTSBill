<?php
$pageTitle = 'Products & Inventory Center - WTS LEDGER PRO';
$currentRoute = 'inventory';

require_once __DIR__ . '/../db_helper.php';
use Illuminate\Database\Capsule\Manager as DB;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\InventoryService;

$companyId = get_current_company_id();
$branchId = get_current_branch_id() ?: 1;

$statusMessage = null;
$statusType = 'success';
$errorMessage = null;

// ====================================================================
// HANDLE INVENTORY POST ACTIONS
// ====================================================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (isset($_POST['csrf_token']) && !verify_csrf_token()) {
        $errorMessage = 'Invalid or expired security token. Please try again.';
        $statusType = 'danger';
    } else {
        $action = $_POST['action'] ?? 'add_product';

        try {
            // --------------------------------------------------------
            // 1. ADD / CREATE PRODUCT
            // --------------------------------------------------------
            if ($action === 'add_product' || $action === 'save_product') {
                $name = trim($_POST['name'] ?? '');
                $sku = trim($_POST['sku'] ?? '');
                $categoryName = trim($_POST['category'] ?? 'General');
                $sellingPrice = floatval($_POST['price'] ?? ($_POST['sales_price'] ?? 0));
                $purchasePrice = floatval($_POST['purchase_price'] ?? 0);
                $taxRate = floatval($_POST['tax_rate'] ?? 18);
                $unit = trim($_POST['unit'] ?? 'Pcs') ?: 'Pcs';
                $hsnSac = trim($_POST['hsn_sac'] ?? '');
                $minStock = floatval($_POST['min_stock_alert'] ?? 10);
                $openingStock = floatval($_POST['opening_stock'] ?? 0);
                $warehouseId = intval($_POST['warehouse_id'] ?? 0);

                if (empty($name)) {
                    throw new Exception("Product name is required.");
                }
                if (empty($sku)) {
                    throw new Exception("Product SKU code is required.");
                }
                if ($sellingPrice < 0) {
                    throw new Exception("Selling price cannot be negative.");
                }
                if ($purchasePrice < 0) {
                    throw new Exception("Purchase price cannot be negative.");
                }

                // Check SKU uniqueness in the active company
                $existingSku = Product::where('company_id', $companyId)->where('sku', $sku)->first();
                if ($existingSku) {
                    throw new Exception("Product with SKU '{$sku}' already exists in this company.");
                }

                // Resolve or create category
                $categoryId = null;
                if (!empty($categoryName)) {
                    $cat = DB::table('categories')->where('company_id', $companyId)->where('name', $categoryName)->first();
                    if ($cat) {
                        $categoryId = $cat->id;
                    } else {
                        $categoryId = DB::table('categories')->insertGetId([
                            'company_id' => $companyId,
                            'name' => $categoryName,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }

                DB::beginTransaction();
                $newProduct = Product::create([
                    'company_id' => $companyId,
                    'category_id' => $categoryId,
                    'name' => $name,
                    'sku' => $sku,
                    'hsn_sac' => $hsnSac,
                    'unit' => $unit,
                    'sales_price' => $sellingPrice,
                    'purchase_price' => $purchasePrice,
                    'tax_rate' => $taxRate,
                    'gst_rate' => $taxRate,
                    'current_stock' => 0,
                    'opening_stock' => $openingStock,
                    'min_stock_alert' => $minStock,
                    'track_inventory' => 1,
                    'is_active' => 1,
                ]);

                // If opening stock > 0, record opening stock movement in selected warehouse
                if ($openingStock > 0) {
                    $targetWarehouse = $warehouseId > 0 
                        ? Warehouse::where('company_id', $companyId)->find($warehouseId)
                        : Warehouse::where('company_id', $companyId)->first();
                    
                    if (!$targetWarehouse) {
                        $targetWarehouse = Warehouse::create([
                            'company_id' => $companyId,
                            'branch_id' => $branchId,
                            'name' => 'Main Warehouse',
                            'code' => 'WH01',
                            'is_primary' => 1,
                            'is_active' => 1
                        ]);
                    }

                    InventoryService::recordStockMovement(
                        companyId: $companyId,
                        warehouseId: $targetWarehouse->id,
                        productId: $newProduct->id,
                        movementType: 'OPENING_STOCK',
                        quantity: $openingStock,
                        direction: 'IN',
                        unitCost: $purchasePrice,
                        branchId: $branchId,
                        refType: 'OPENING_BALANCE',
                        refNumber: 'OPN-' . $sku,
                        movementDate: date('Y-m-d'),
                        createdBy: $_SESSION['user']['name'] ?? 'Admin',
                        notes: 'Initial opening stock entry upon product creation'
                    );
                }

                DB::commit();
                $statusMessage = "Product '{$name}' (SKU: {$sku}) saved successfully.";
            }

            // --------------------------------------------------------
            // 2. EDIT / UPDATE PRODUCT
            // --------------------------------------------------------
            elseif ($action === 'edit_product' || $action === 'update_product') {
                $productId = intval($_POST['product_id'] ?? 0);
                $product = Product::where('company_id', $companyId)->findOrFail($productId);

                $name = trim($_POST['name'] ?? '');
                $sku = trim($_POST['sku'] ?? '');
                $categoryName = trim($_POST['category'] ?? '');
                $sellingPrice = floatval($_POST['price'] ?? ($_POST['sales_price'] ?? 0));
                $purchasePrice = floatval($_POST['purchase_price'] ?? 0);
                $taxRate = floatval($_POST['tax_rate'] ?? 18);
                $unit = trim($_POST['unit'] ?? 'Pcs') ?: 'Pcs';
                $hsnSac = trim($_POST['hsn_sac'] ?? '');
                $minStock = floatval($_POST['min_stock_alert'] ?? 10);

                if (empty($name) || empty($sku)) {
                    throw new Exception("Product name and SKU are required.");
                }

                $existingSku = Product::where('company_id', $companyId)
                    ->where('sku', $sku)
                    ->where('id', '!=', $productId)
                    ->first();
                if ($existingSku) {
                    throw new Exception("Another product with SKU '{$sku}' already exists.");
                }

                $categoryId = $product->category_id;
                if (!empty($categoryName)) {
                    $cat = DB::table('categories')->where('company_id', $companyId)->where('name', $categoryName)->first();
                    $categoryId = $cat ? $cat->id : DB::table('categories')->insertGetId([
                        'company_id' => $companyId,
                        'name' => $categoryName,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }

                $product->update([
                    'name' => $name,
                    'sku' => $sku,
                    'category_id' => $categoryId,
                    'hsn_sac' => $hsnSac,
                    'unit' => $unit,
                    'sales_price' => $sellingPrice,
                    'purchase_price' => $purchasePrice,
                    'tax_rate' => $taxRate,
                    'gst_rate' => $taxRate,
                    'min_stock_alert' => $minStock,
                ]);

                $statusMessage = "Product '{$name}' updated successfully.";
            }

            // --------------------------------------------------------
            // 3. DELETE / DEACTIVATE PRODUCT
            // --------------------------------------------------------
            elseif ($action === 'delete_product') {
                $productId = intval($_POST['product_id'] ?? 0);
                $product = Product::where('company_id', $companyId)->findOrFail($productId);
                $product->delete();
                $statusMessage = "Product '{$product->name}' removed from active inventory.";
            }

            // --------------------------------------------------------
            // 4. ADD / CREATE WAREHOUSE
            // --------------------------------------------------------
            elseif ($action === 'add_warehouse' || $action === 'save_warehouse') {
                $name = trim($_POST['warehouse_name'] ?? ($_POST['name'] ?? ''));
                $code = trim($_POST['warehouse_code'] ?? ($_POST['code'] ?? ''));
                $city = trim($_POST['city'] ?? '');
                $isPrimary = !empty($_POST['is_primary']) ? 1 : 0;

                if (empty($name)) {
                    throw new Exception("Warehouse name is required.");
                }
                if (empty($code)) {
                    $whCount = Warehouse::where('company_id', $companyId)->count() + 1;
                    $code = 'WH-' . str_pad((string)$whCount, 2, '0', STR_PAD_LEFT);
                }

                if ($isPrimary) {
                    Warehouse::where('company_id', $companyId)->update(['is_primary' => 0]);
                }

                Warehouse::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'name' => $name,
                    'code' => $code,
                    'city' => $city,
                    'is_primary' => $isPrimary,
                    'is_active' => 1,
                ]);

                $statusMessage = "Warehouse '{$name}' created successfully.";
            }

            // --------------------------------------------------------
            // 5. STOCK ADJUSTMENT WORKFLOW
            // --------------------------------------------------------
            elseif ($action === 'stock_adjustment') {
                $warehouseId = intval($_POST['adj_warehouse_id'] ?? 0);
                $productId = intval($_POST['adj_product_id'] ?? 0);
                $qty = floatval($_POST['adj_quantity'] ?? 0);
                $adjType = strtoupper($_POST['adj_type'] ?? 'INCREASE'); // INCREASE / DECREASE
                $reason = trim($_POST['adj_reason'] ?? 'Physical stock count correction');
                $notes = trim($_POST['adj_notes'] ?? '');

                if ($warehouseId <= 0 || $productId <= 0) {
                    throw new Exception("Please select both a valid warehouse and product.");
                }
                if ($qty <= 0) {
                    throw new Exception("Adjustment quantity must be greater than zero.");
                }

                $wh = Warehouse::where('company_id', $companyId)->findOrFail($warehouseId);
                $prod = Product::where('company_id', $companyId)->findOrFail($productId);

                $items = [
                    [
                        'product_id' => $productId,
                        'quantity' => $qty,
                        'type' => $adjType,
                        'unit_cost' => floatval($prod->purchase_price ?: 0)
                    ]
                ];

                $adjustment = InventoryService::createAdjustment(
                    companyId: $companyId,
                    warehouseId: $warehouseId,
                    items: $items,
                    reason: $reason,
                    notes: $notes,
                    createdByName: $_SESSION['user']['name'] ?? 'Admin',
                    branchId: $branchId
                );

                $statusMessage = "Stock adjustment #{$adjustment->adjustment_number} posted successfully.";
            }

            // --------------------------------------------------------
            // 6. WAREHOUSE TRANSFER WORKFLOW
            // --------------------------------------------------------
            elseif ($action === 'warehouse_transfer') {
                $fromWhId = intval($_POST['from_warehouse_id'] ?? 0);
                $toWhId = intval($_POST['to_warehouse_id'] ?? 0);
                $productId = intval($_POST['trf_product_id'] ?? 0);
                $qty = floatval($_POST['trf_quantity'] ?? 0);
                $notes = trim($_POST['trf_notes'] ?? '');

                if ($fromWhId <= 0 || $toWhId <= 0) {
                    throw new Exception("Please select valid source and destination warehouses.");
                }
                if ($fromWhId === $toWhId) {
                    throw new Exception("Source and destination warehouses cannot be the same.");
                }
                if ($productId <= 0) {
                    throw new Exception("Please select a valid product.");
                }
                if ($qty <= 0) {
                    throw new Exception("Transfer quantity must be greater than zero.");
                }

                $fromWh = Warehouse::where('company_id', $companyId)->findOrFail($fromWhId);
                $toWh = Warehouse::where('company_id', $companyId)->findOrFail($toWhId);
                $prod = Product::where('company_id', $companyId)->findOrFail($productId);

                // Verify source stock balance
                $sourceBal = StockBalance::where('company_id', $companyId)
                    ->where('warehouse_id', $fromWhId)
                    ->where('product_id', $productId)
                    ->first();
                $avail = $sourceBal ? floatval($sourceBal->available_quantity ?: $sourceBal->quantity) : 0;
                if ($avail < $qty) {
                    throw new Exception("Insufficient stock for '{$prod->name}' in {$fromWh->name}. Available: {$avail}, Requested: {$qty}.");
                }

                $items = [
                    [
                        'product_id' => $productId,
                        'quantity' => $qty
                    ]
                ];

                $transfer = InventoryService::createTransfer(
                    companyId: $companyId,
                    fromWarehouseId: $fromWhId,
                    toWarehouseId: $toWhId,
                    items: $items,
                    notes: $notes,
                    branchId: $branchId
                );

                // Complete receive at destination warehouse
                InventoryService::receiveTransfer($transfer->id);

                $statusMessage = "Transfer #{$transfer->transfer_number} processed: {$qty} {$prod->unit} moved from {$fromWh->name} to {$toWh->name}.";
            }
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $errorMessage = $e->getMessage();
            $statusType = 'danger';
        }
    }
}

// ====================================================================
// LOAD REAL DATABASE INVENTORY DATA
// ====================================================================
$dbProducts = get_products();
$warehouses = Warehouse::where('company_id', $companyId)->orderByDesc('is_primary')->get();

$stockMovements = DB::table('stock_movements')
    ->leftJoin('products', 'stock_movements.product_id', '=', 'products.id')
    ->leftJoin('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
    ->where('stock_movements.company_id', $companyId)
    ->select('stock_movements.*', 'products.name as product_name', 'products.sku as product_sku', 'products.unit as product_unit', 'warehouses.name as warehouse_name')
    ->orderByDesc('stock_movements.id')
    ->limit(50)
    ->get();

$stockAdjustments = DB::table('stock_adjustments')
    ->leftJoin('warehouses', 'stock_adjustments.warehouse_id', '=', 'warehouses.id')
    ->where('stock_adjustments.company_id', $companyId)
    ->select('stock_adjustments.*', 'warehouses.name as warehouse_name')
    ->orderByDesc('stock_adjustments.id')
    ->limit(50)
    ->get();

$stockTransfers = DB::table('stock_transfers')
    ->leftJoin('warehouses as from_wh', 'stock_transfers.from_warehouse_id', '=', 'from_wh.id')
    ->leftJoin('warehouses as to_wh', 'stock_transfers.to_warehouse_id', '=', 'to_wh.id')
    ->where('stock_transfers.company_id', $companyId)
    ->select('stock_transfers.*', 'from_wh.name as from_warehouse_name', 'to_wh.name as to_warehouse_name')
    ->orderByDesc('stock_transfers.id')
    ->limit(50)
    ->get();

$lowStockCount = count(array_filter($dbProducts, static fn($product) => (float)($product['current_stock'] ?? 0) > 0 && (float)($product['current_stock'] ?? 0) <= (float)($product['min_stock_alert'] ?? 0)));
$outOfStockCount = count(array_filter($dbProducts, static fn($product) => (float)($product['current_stock'] ?? 0) <= 0));
$inventoryCostValue = array_sum(array_map(static fn($product) => (float)($product['current_stock'] ?? 0) * (float)($product['purchase_price'] ?? 0), $dbProducts));
$inventorySalesValue = array_sum(array_map(static fn($product) => (float)($product['current_stock'] ?? 0) * (float)($product['sales_price'] ?? 0), $dbProducts));
$projectedProfit = max(0, $inventorySalesValue - $inventoryCostValue);

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <!-- Status Feedback Alerts -->
        <?php if (!empty($statusMessage)): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #047857; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                <div><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($statusMessage) ?></div>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none; border:none; cursor:pointer; color:#047857;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #b91c1c; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                <div><i class="fa-solid fa-triangle-exclamation"></i> <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?></div>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none; border:none; cursor:pointer; color:#b91c1c;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        <?php endif; ?>

        <!-- Products Hub Header -->
        <div class="workspace-header-row">
            <div class="workspace-header-left">
                <h1 class="workspace-title">Products &amp; Inventory Center</h1>
                <p class="workspace-subtitle">Manage catalog, warehouses, real-time stock movements, and valuation.</p>
            </div>
            <div class="workspace-header-right">
                <button class="header-filter-btn" type="button" onclick="openModal('adjustmentModal')">
                    <i class="fa-solid fa-sliders filter-icon"></i>
                    <span>Stock Adjustment</span>
                </button>
                <button class="header-filter-btn" type="button" onclick="openModal('transferModal')">
                    <i class="fa-solid fa-arrow-right-arrow-left filter-icon"></i>
                    <span>Transfer Stock</span>
                </button>
                <button class="header-filter-btn" type="button" onclick="openModal('addWarehouseModal')">
                    <i class="fa-solid fa-warehouse filter-icon"></i>
                    <span>Add Warehouse</span>
                </button>
                <button class="btn-create-dark" type="button" onclick="openModal('addProductModal')">
                    <i class="fa-solid fa-plus"></i>
                    <span>Add Product</span>
                </button>
            </div>
        </div>

        <!-- 4 Stat Cards Row -->
        <div class="inventory-stats-row">
            <div class="stat-card">
                <div class="stat-card-label">TOTAL PRODUCTS</div>
                <div class="stat-card-value"><?= count($dbProducts) ?> items</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">LOW STOCK ITEMS</div>
                <div class="stat-card-value text-amber"><?= $lowStockCount ?> products</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">OUT OF STOCK</div>
                <div class="stat-card-value text-coral"><?= $outOfStockCount ?> products</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">INVENTORY VALUE (AT COST)</div>
                <div class="stat-card-value">₹<?= number_format($inventoryCostValue, 2) ?></div>
            </div>
        </div>

        <!-- Inventory Navigation Tabs -->
        <div class="subnav-tabs-wrapper">
            <div class="subnav-tabs" id="inventoryTabsNav">
                <a href="javascript:void(0)" class="subnav-tab active" data-tab="stock" onclick="switchTab('inventoryTabsNav', 'tab-stock-reg', event)">Stock Register</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="warehouses" onclick="switchTab('inventoryTabsNav', 'tab-warehouse-reg', event)">Warehouse Registry (<?= $warehouses->count() ?>)</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="movements" onclick="switchTab('inventoryTabsNav', 'tab-stock-movements', event)">Stock Movements</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="adjustments" onclick="switchTab('inventoryTabsNav', 'tab-adjustments-log', event)">Adjustments Log</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="transfers" onclick="switchTab('inventoryTabsNav', 'tab-warehouse-transfers', event)">Warehouse Transfers</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="reorder" onclick="switchTab('inventoryTabsNav', 'tab-reorder-replenishments', event)">Low Stock / Reorder (<?= $lowStockCount ?>)</a>
                <a href="javascript:void(0)" class="subnav-tab" data-tab="valuation" onclick="switchTab('inventoryTabsNav', 'tab-inventory-val', event)">Inventory Valuation</a>
            </div>
        </div>

        <div class="inventory-tabs-container">
            <!-- ==========================================
                 TAB 1: STOCK REGISTER (DEFAULT ACTIVE)
                 ========================================== -->
            <div id="tab-stock-reg" class="subnav-pane active">
                <div class="white-card filter-toolbar-card">
                    <div class="filter-toolbar-row">
                        <div class="search-input-box" style="flex: 1; max-width: 460px;">
                            <i class="fa-solid fa-magnifying-glass search-box-icon"></i>
                            <input type="text" id="inventorySearchInput" class="search-box-field" placeholder="Search product name, SKU or barcode..." onkeyup="filterInventoryTable()">
                        </div>

                        <div class="filter-select-group">
                            <select id="stockStatusSelect" class="toolbar-select" onchange="filterInventoryTable()">
                                <option value="">All Stocks</option>
                                <option value="In Stock">In Stock</option>
                                <option value="Low Stock">Low Stock</option>
                                <option value="Out of Stock">Out of Stock</option>
                            </select>

                            <button class="toolbar-filter-btn" type="button" onclick="exportTableToCSV('inventoryProductsTable', 'Inventory_Catalog.csv')">
                                <i class="fa-solid fa-download"></i>
                                <span>Export CSV</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="white-card table-card">
                    <div class="table-responsive">
                        <table class="wts-table" id="inventoryProductsTable">
                            <thead>
                                <tr>
                                    <th>PRODUCT</th>
                                    <th>SKU</th>
                                    <th>CATEGORY</th>
                                    <th>SELLING PRICE</th>
                                    <th>PURCHASE PRICE</th>
                                    <th>STOCK</th>
                                    <th>STATUS</th>
                                    <th>GST RATE</th>
                                    <th class="text-center">ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dbProducts as $p):
                                    $stock = (float)($p['current_stock'] ?? 0);
                                    $minimum = (float)($p['min_stock_alert'] ?? 0);
                                    $status = $stock <= 0 ? 'Out of Stock' : ($stock <= $minimum ? 'Low Stock' : 'In Stock');
                                    $statusClass = $stock <= 0 || $stock <= $minimum ? 'badge-status-pending' : 'badge-status-paid';
                                    $category = $p['category_name'] ?? 'General';
                                ?>
                                    <tr data-status="<?= htmlspecialchars($status) ?>" data-cat="<?= htmlspecialchars($category) ?>">
                                        <td>
                                            <strong class="product-name-link"><?= htmlspecialchars($p['name']) ?></strong>
                                            <?php if (!empty($p['hsn_sac'])): ?>
                                                <div style="font-size: 11px; color: #64748b;">HSN: <?= htmlspecialchars($p['hsn_sac']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="font-mono text-muted"><?= htmlspecialchars($p['sku'] ?? '') ?></span></td>
                                        <td><?= htmlspecialchars($category) ?></td>
                                        <td><strong>₹<?= number_format((float)($p['sales_price'] ?? 0), 2) ?></strong></td>
                                        <td>₹<?= number_format((float)($p['purchase_price'] ?? 0), 2) ?></td>
                                        <td><strong><?= number_format($stock, 2) ?></strong> <?= htmlspecialchars($p['unit'] ?? 'Pcs') ?></td>
                                        <td>
                                            <span class="badge-status <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars((string)($p['tax_rate'] ?? '18')) ?>%</td>
                                        <td class="text-center">
                                            <div class="table-action-icons">
                                                <button class="table-icon-btn" title="Edit Product" onclick='openEditProductModal(<?= json_encode($p) ?>)'><i class="fa-regular fa-pen-to-square"></i></button>
                                                <button class="table-icon-btn" title="Quick Adjust Stock" onclick="openQuickAdjustModal(<?= $p['id'] ?>)"><i class="fa-solid fa-sliders"></i></button>
                                                <form method="POST" action="<?= url('/inventory') ?>" style="display:inline;" onsubmit="return confirm('Deactivate product <?= htmlspecialchars($p['name']) ?>?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_product">
                                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                                    <button type="submit" class="table-icon-btn" title="Delete Product" style="color:#ef4444;"><i class="fa-regular fa-trash-can"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$dbProducts): ?>
                                    <tr><td colspan="9" class="text-center">No products found in the database. Use "+ Add Product" above to create your first item.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 TAB 2: WAREHOUSE REGISTRY
                 ========================================== -->
            <div id="tab-warehouse-reg" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a;">Warehouse Locations &amp; Capacity Registry</h3>
                        <button class="btn-create-dark" onclick="openModal('addWarehouseModal')"><i class="fa-solid fa-plus"></i> Add Warehouse</button>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>WAREHOUSE NAME</th>
                                    <th>CODE</th>
                                    <th>LOCATION / CITY</th>
                                    <th>PRIMARY WAREHOUSE</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($warehouses as $wh): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($wh->name) ?></strong></td>
                                        <td><span class="font-mono"><?= htmlspecialchars($wh->code ?: 'WH-' . $wh->id) ?></span></td>
                                        <td><?= htmlspecialchars($wh->city ?: 'Main Location') ?></td>
                                        <td>
                                            <?php if ($wh->is_primary): ?>
                                                <span class="badge-status badge-status-paid"><i class="fa-solid fa-star"></i> PRIMARY</span>
                                            <?php else: ?>
                                                <span class="text-muted">Secondary</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge-status badge-status-paid">ACTIVE</span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($warehouses->isEmpty()): ?>
                                    <tr><td colspan="5" class="text-center">No warehouses configured.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 TAB 3: STOCK MOVEMENTS
                 ========================================== -->
            <div id="tab-stock-movements" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a;">Real-Time Stock Movement Ledger</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>DATE</th>
                                    <th>ITEM NAME</th>
                                    <th>SKU</th>
                                    <th>WAREHOUSE</th>
                                    <th>TYPE</th>
                                    <th>QUANTITY</th>
                                    <th>BALANCE AFTER</th>
                                    <th>REFERENCE</th>
                                    <th>OPERATOR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stockMovements as $mv): 
                                    $isOut = strtoupper($mv->direction ?? '') === 'OUT';
                                    $sign = $isOut ? '-' : '+';
                                    $qtyClass = $isOut ? 'text-coral' : 'text-green';
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mv->movement_date ?? date('Y-m-d', strtotime($mv->created_at))) ?></td>
                                        <td><strong><?= htmlspecialchars($mv->product_name ?? 'Product') ?></strong></td>
                                        <td><span class="font-mono text-muted"><?= htmlspecialchars($mv->product_sku ?? '') ?></span></td>
                                        <td><?= htmlspecialchars($mv->warehouse_name ?? 'Warehouse') ?></td>
                                        <td>
                                            <span class="badge-status <?= $isOut ? 'badge-status-overdue' : 'badge-status-paid' ?>">
                                                <?= htmlspecialchars($mv->movement_type ?: $mv->type) ?> (<?= htmlspecialchars($mv->direction) ?>)
                                            </span>
                                        </td>
                                        <td><strong class="<?= $qtyClass ?>"><?= $sign . number_format((float)$mv->quantity, 2) ?> <?= htmlspecialchars($mv->product_unit ?? '') ?></strong></td>
                                        <td><?= number_format((float)$mv->balance_after, 2) ?></td>
                                        <td><span class="font-mono"><?= htmlspecialchars($mv->reference_number ?: ($mv->reference_type ?? '-')) ?></span></td>
                                        <td><?= htmlspecialchars($mv->created_by ?? 'System') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($stockMovements->isEmpty()): ?>
                                    <tr><td colspan="9" class="text-center">No stock movements recorded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 TAB 4: ADJUSTMENTS LOG
                 ========================================== -->
            <div id="tab-adjustments-log" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a;">Stock Adjustments &amp; Reconciliation Log</h3>
                        <button class="btn-create-dark" onclick="openModal('adjustmentModal')"><i class="fa-solid fa-plus"></i> New Adjustment</button>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>ADJ NUMBER</th>
                                    <th>DATE</th>
                                    <th>WAREHOUSE</th>
                                    <th>REASON</th>
                                    <th>OPERATOR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stockAdjustments as $adj): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($adj->adjustment_number) ?></strong></td>
                                        <td><?= htmlspecialchars($adj->adjustment_date) ?></td>
                                        <td><?= htmlspecialchars($adj->warehouse_name ?? 'Primary Warehouse') ?></td>
                                        <td><?= htmlspecialchars($adj->reason) ?></td>
                                        <td><?= htmlspecialchars($adj->created_by ?? 'Admin') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($stockAdjustments->isEmpty()): ?>
                                    <tr><td colspan="5" class="text-center">No physical count adjustments recorded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 TAB 5: WAREHOUSE TRANSFERS
                 ========================================== -->
            <div id="tab-warehouse-transfers" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a;">Inter-Warehouse Transfer Log</h3>
                        <button class="btn-create-dark" onclick="openModal('transferModal')"><i class="fa-solid fa-plus"></i> New Transfer</button>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>TRANSFER #</th>
                                    <th>DATE</th>
                                    <th>SOURCE WAREHOUSE</th>
                                    <th>DESTINATION WAREHOUSE</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stockTransfers as $trf): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($trf->transfer_number) ?></strong></td>
                                        <td><?= htmlspecialchars($trf->transfer_date) ?></td>
                                        <td><?= htmlspecialchars($trf->from_warehouse_name ?? 'Source') ?></td>
                                        <td><?= htmlspecialchars($trf->to_warehouse_name ?? 'Destination') ?></td>
                                        <td><span class="badge-status badge-status-paid"><?= strtoupper(htmlspecialchars($trf->status)) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($stockTransfers->isEmpty()): ?>
                                    <tr><td colspan="5" class="text-center">No warehouse transfers recorded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 TAB 6: REORDER REPLENISHMENTS
                 ========================================== -->
            <div id="tab-reorder-replenishments" class="subnav-pane" style="display: none;">
                <div class="white-card table-card">
                    <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="font-size: 15px; font-weight: 800; color: #0f172a;">Low Stock Items &amp; Reorder Alerts</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="wts-table">
                            <thead>
                                <tr>
                                    <th>PRODUCT NAME</th>
                                    <th>SKU</th>
                                    <th>CURRENT STOCK</th>
                                    <th>SAFETY REORDER LEVEL</th>
                                    <th>DEFICIT / SHORTAGE</th>
                                    <th class="text-center">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $lowItems = array_filter($dbProducts, static fn($p) => (float)($p['current_stock'] ?? 0) <= (float)($p['min_stock_alert'] ?? 0));
                                foreach ($lowItems as $lp): 
                                    $cStock = (float)($lp['current_stock'] ?? 0);
                                    $mStock = (float)($lp['min_stock_alert'] ?? 10);
                                    $deficit = max(0, $mStock - $cStock);
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($lp['name']) ?></strong></td>
                                        <td><span class="font-mono text-muted"><?= htmlspecialchars($lp['sku']) ?></span></td>
                                        <td><span class="text-coral font-bold"><?= number_format($cStock, 2) ?> <?= htmlspecialchars($lp['unit'] ?? '') ?></span></td>
                                        <td><?= number_format($mStock, 2) ?> <?= htmlspecialchars($lp['unit'] ?? '') ?></td>
                                        <td><strong class="text-coral">-<?= number_format($deficit, 2) ?></strong></td>
                                        <td class="text-center">
                                            <button class="btn btn-outline btn-sm" onclick="openQuickAdjustModal(<?= $lp['id'] ?>)">Restock via Adjustment</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($lowItems)): ?>
                                    <tr><td colspan="6" class="text-center" style="padding: 24px; color:#10b981;"><i class="fa-solid fa-circle-check"></i> All catalog items are adequately stocked above safety threshold.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ==========================================
                 TAB 7: INVENTORY VALUATION
                 ========================================== -->
            <div id="tab-inventory-val" class="subnav-pane" style="display: none;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="white-card">
                        <h3 class="card-head-title">Inventory Valuation Summary (FIFO / Standard Cost)</h3>
                        <p class="card-head-sub">Authoritative valuation based on active stock balance ledger</p>
                        <div style="margin-top: 16px;">
                            <div class="health-item-row">
                                <div>
                                    <div class="health-item-label">TOTAL STOCK VALUE (AT PURCHASE COST)</div>
                                    <div class="health-item-value">₹<?= number_format($inventoryCostValue, 2) ?></div>
                                </div>
                                <div class="health-badge badge-neutral">Cost Basis</div>
                            </div>
                            <div class="health-item-row">
                                <div>
                                    <div class="health-item-label">ESTIMATED MARKET SALES VALUE</div>
                                    <div class="health-item-value text-green">₹<?= number_format($inventorySalesValue, 2) ?></div>
                                </div>
                                <div class="health-badge badge-green">Selling Price</div>
                            </div>
                            <div class="health-item-row">
                                <div>
                                    <div class="health-item-label">PROJECTED GROSS PROFIT SPREAD</div>
                                    <div class="health-item-value text-blue">₹<?= number_format($projectedProfit, 2) ?></div>
                                </div>
                                <div class="health-badge badge-blue"><?= $inventoryCostValue > 0 ? round(($projectedProfit / $inventoryCostValue) * 100, 1) : 0 ?>% Margin</div>
                            </div>
                        </div>
                    </div>

                    <div class="white-card">
                        <h3 class="card-head-title">Warehouse Distribution</h3>
                        <p class="card-head-sub">Registered stock warehouses</p>
                        <div style="padding: 16px 0;">
                            <?php foreach ($warehouses as $wh): ?>
                                <div class="health-item-row">
                                    <div>
                                        <div class="health-item-label"><?= htmlspecialchars($wh->name) ?></div>
                                        <div class="health-item-value font-mono"><?= htmlspecialchars($wh->code ?: 'WH-' . $wh->id) ?></div>
                                    </div>
                                    <div class="health-badge <?= $wh->is_primary ? 'badge-blue' : 'badge-neutral' ?>"><?= $wh->is_primary ? 'Primary Hub' : 'Branch Hub' ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Product Modal -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal-content" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-box text-purple"></i> Add Product Item</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('addProductModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= url('/inventory') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_product">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group">
                    <label class="form-label">Product / Component Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Copper Wire 2.5sqmm Red">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">SKU Code *</label>
                        <input type="text" name="sku" class="form-control" required placeholder="e.g. EL-CWR-25R">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" placeholder="e.g. Hardware, Electrical">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Selling Price (₹) *</label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control" required placeholder="1850.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Purchase Price (₹)</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" placeholder="1350.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">GST Rate (%)</label>
                        <select name="tax_rate" class="form-control">
                            <option value="18">18%</option>
                            <option value="12">12%</option>
                            <option value="5">5%</option>
                            <option value="28">28%</option>
                            <option value="0">0%</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">HSN/SAC Code</label>
                        <input type="text" name="hsn_sac" class="form-control" placeholder="84818030">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit of Measure</label>
                        <input type="text" name="unit" class="form-control" value="Pcs">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Stock Alert</label>
                        <input type="number" step="1" name="min_stock_alert" class="form-control" value="10">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div class="form-group">
                        <label class="form-label">Opening Stock (Initial)</label>
                        <input type="number" step="1" min="0" name="opening_stock" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial Warehouse</label>
                        <select name="warehouse_id" class="form-control">
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh->id ?>"><?= htmlspecialchars($wh->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('addProductModal')">Cancel</button>
                <button type="submit" class="btn-create-dark">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal-overlay" id="editProductModal">
    <div class="modal-content" style="max-width: 580px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pen-to-square text-blue"></i> Edit Product Item</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('editProductModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= url('/inventory') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_product">
            <input type="hidden" name="product_id" id="editProductId">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" id="editProductName" class="form-control" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">SKU Code *</label>
                        <input type="text" name="sku" id="editProductSku" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" id="editProductCategory" class="form-control">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Selling Price (₹) *</label>
                        <input type="number" step="0.01" min="0" name="price" id="editProductPrice" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Purchase Price (₹)</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" id="editProductPurchasePrice" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">GST Rate (%)</label>
                        <select name="tax_rate" id="editProductTaxRate" class="form-control">
                            <option value="18">18%</option>
                            <option value="12">12%</option>
                            <option value="5">5%</option>
                            <option value="28">28%</option>
                            <option value="0">0%</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">HSN/SAC Code</label>
                        <input type="text" name="hsn_sac" id="editProductHsn" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unit</label>
                        <input type="text" name="unit" id="editProductUnit" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Min Stock Alert</label>
                        <input type="number" step="1" name="min_stock_alert" id="editProductMinStock" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('editProductModal')">Cancel</button>
                <button type="submit" class="btn-create-dark">Update Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Warehouse Modal -->
<div class="modal-overlay" id="addWarehouseModal">
    <div class="modal-content" style="max-width: 480px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-warehouse text-blue"></i> Add Warehouse Location</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('addWarehouseModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= url('/inventory') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_warehouse">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group">
                    <label class="form-label">Warehouse Name *</label>
                    <input type="text" name="warehouse_name" class="form-control" required placeholder="e.g. Pune Logistics Hub">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Warehouse Code</label>
                        <input type="text" name="warehouse_code" class="form-control" placeholder="e.g. WH-PUN-01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City / Location</label>
                        <input type="text" name="city" class="form-control" placeholder="e.g. Pune">
                    </div>
                </div>
                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_primary" value="1">
                        <span>Set as primary dispatch warehouse</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer" style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('addWarehouseModal')">Cancel</button>
                <button type="submit" class="btn-create-dark">Save Warehouse</button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal-overlay" id="adjustmentModal">
    <div class="modal-content" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-sliders text-amber"></i> Stock Physical Count Adjustment</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('adjustmentModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= url('/inventory') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="stock_adjustment">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <div class="form-group">
                    <label class="form-label">Target Warehouse *</label>
                    <select name="adj_warehouse_id" id="adjWarehouseSelect" class="form-control" required>
                        <?php foreach ($warehouses as $wh): ?>
                            <option value="<?= $wh->id ?>"><?= htmlspecialchars($wh->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Product *</label>
                    <select name="adj_product_id" id="adjProductSelect" class="form-control" required>
                        <option value="">-- Choose Product --</option>
                        <?php foreach ($dbProducts as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku']) ?> | Stock: <?= $p['current_stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Adjustment Type *</label>
                        <select name="adj_type" class="form-control" required>
                            <option value="INCREASE">INCREASE (+ Add Stock)</option>
                            <option value="DECREASE">DECREASE (- Reduce Stock)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quantity *</label>
                        <input type="number" step="0.01" min="0.01" name="adj_quantity" class="form-control" required placeholder="e.g. 5">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason for Adjustment *</label>
                    <select name="adj_reason" class="form-control">
                        <option value="Physical count discrepancy">Physical count discrepancy</option>
                        <option value="Handling damage in transit">Handling damage in transit</option>
                        <option value="Expired / shelf-life write-off">Expired / shelf-life write-off</option>
                        <option value="Found excess stock">Found excess stock</option>
                        <option value="Sample distribution / demo">Sample distribution / demo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Auditor / Manager Notes</label>
                    <textarea name="adj_notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('adjustmentModal')">Cancel</button>
                <button type="submit" class="btn-create-dark">Post Adjustment</button>
            </div>
        </form>
    </div>
</div>

<!-- Warehouse Transfer Modal -->
<div class="modal-overlay" id="transferModal">
    <div class="modal-content" style="max-width: 540px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-arrow-right-arrow-left text-blue"></i> Inter-Warehouse Stock Transfer</h3>
            <button class="btn btn-outline btn-sm" onclick="closeModal('transferModal')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= url('/inventory') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="warehouse_transfer">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Source Warehouse *</label>
                        <select name="from_warehouse_id" class="form-control" required>
                            <option value="">-- From --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh->id ?>"><?= htmlspecialchars($wh->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Destination Warehouse *</label>
                        <select name="to_warehouse_id" class="form-control" required>
                            <option value="">-- To --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh->id ?>"><?= htmlspecialchars($wh->name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Select Product *</label>
                    <select name="trf_product_id" class="form-control" required>
                        <option value="">-- Choose Product --</option>
                        <?php foreach ($dbProducts as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (SKU: <?= htmlspecialchars($p['sku']) ?> | Total Stock: <?= $p['current_stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Transfer Quantity *</label>
                    <input type="number" step="0.01" min="0.01" name="trf_quantity" class="form-control" required placeholder="Quantity to move">
                </div>
                <div class="form-group">
                    <label class="form-label">Transfer Notes</label>
                    <textarea name="trf_notes" class="form-control" rows="2" placeholder="Vehicle no, challan ref, driver name..."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="margin-top: 16px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('transferModal')">Cancel</button>
                <button type="submit" class="btn-create-dark">Transfer Stock</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterInventoryTable() {
    let q = (document.getElementById('inventorySearchInput').value || '').toLowerCase().trim();
    let st = (document.getElementById('stockStatusSelect').value || '').toLowerCase().trim();

    let rows = document.querySelectorAll('#inventoryProductsTable tbody tr');
    rows.forEach(r => {
        let text = r.innerText.toLowerCase();
        let rSt = (r.getAttribute('data-status') || '').toLowerCase();

        let matchQ = !q || text.includes(q);
        let matchSt = !st || rSt.includes(st);

        if (matchQ && matchSt) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });
}

function openEditProductModal(p) {
    document.getElementById('editProductId').value = p.id || '';
    document.getElementById('editProductName').value = p.name || '';
    document.getElementById('editProductSku').value = p.sku || '';
    document.getElementById('editProductCategory').value = p.category_name || '';
    document.getElementById('editProductPrice').value = p.sales_price || p.price || '';
    document.getElementById('editProductPurchasePrice').value = p.purchase_price || '';
    document.getElementById('editProductTaxRate').value = p.tax_rate || 18;
    document.getElementById('editProductHsn').value = p.hsn_sac || '';
    document.getElementById('editProductUnit').value = p.unit || 'Pcs';
    document.getElementById('editProductMinStock').value = p.min_stock_alert || 10;
    openModal('editProductModal');
}

function openQuickAdjustModal(prodId) {
    let select = document.getElementById('adjProductSelect');
    if (select && prodId) {
        select.value = prodId;
    }
    openModal('adjustmentModal');
}
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
