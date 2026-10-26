<?php
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/app/Core/Autoloader.php';
\WTSBill\Core\Autoloader::register();
require_once ROOT_PATH . '/app/Core/helpers.php';
\WTSBill\Core\Env::load(ROOT_PATH . '/.env');
\WTSBill\Core\Config::loadFromDirectory(ROOT_PATH . '/config');

\WTSBill\Core\Session::start();
\WTSBill\Core\Session::set('auth_user', ['id' => 1, 'role' => 'ADMIN']);
\WTSBill\Core\Session::set('company_id', 1);

try {
    $invCtrl = new \WTSBill\Controllers\InventoryController();
    $companyId = 1; $productId = 1; $warehouseId = 1; $type = 'ADD'; $qty = 15.0; $notes = 'Cycle count test';
    \WTSBill\Core\Database::insert('stock_movements', [
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'product_id' => $productId,
        'type' => 'ADJUSTMENT',
        'movement_type' => 'ADJUSTMENT',
        'direction' => ($type === 'SUBTRACT') ? 'OUT' : 'IN',
        'quantity' => $qty,
        'balance_after' => 100,
        'notes' => $notes ?: 'Physical stock audit adjustment',
        'movement_date' => date('Y-m-d'),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    echo "stock_movements insert passed\n";
} catch (\Throwable $e) {
    echo "INSERT ERROR: " . $e->getMessage() . "\n";
    if (method_exists($e, 'getSql')) {
        echo "SQL: " . $e->getSql() . "\n";
        echo "Bindings: " . json_encode($e->getBindings()) . "\n";
    }
}
