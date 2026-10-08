<?php
$files = [
    'backend/app/Http/Controllers/Api/BankAccountController.php',
    'backend/app/Http/Controllers/Api/BankReconciliationController.php',
    'backend/app/Http/Controllers/Api/BranchController.php',
    'backend/app/Http/Controllers/Api/ChequeController.php',
    'backend/app/Http/Controllers/Api/ExpenseController.php',
    'backend/app/Http/Controllers/Api/GatewayWebhookController.php',
    'backend/app/Http/Controllers/Api/NotificationController.php',
    'backend/app/Http/Controllers/Api/NotificationSettingController.php',
    'backend/app/Http/Controllers/Api/PaymentController.php',
    'backend/app/Http/Controllers/Api/PaymentLinkController.php',
    'backend/app/Http/Controllers/Api/PriceListController.php',
    'backend/app/Http/Controllers/Api/PurchaseController.php',
    'backend/app/Http/Controllers/Api/RecurringController.php',
    'backend/app/Http/Controllers/Api/ReportEngineController.php',
    'backend/app/Http/Controllers/Api/ReminderController.php',
    'backend/app/Http/Controllers/Api/StockTransferController.php',
    'backend/app/Http/Controllers/Api/WarehouseController.php',
    'backend/app/SystemAdmin/Controllers/SystemAdminController.php',
    'backend/app/Migrations/Controllers/MigrationController.php',
    'backend/app/Imports/Controllers/ImportController.php',
    'backend/app/Exports/Controllers/ExportController.php',
    'backend/app/Backups/Controllers/BackupController.php',
    'backend/app/Audit/Controllers/AuditController.php',
    'backend/app/PaymentGateways/Webhooks/GatewayWebhookService.php',
    'backend/app/Cheques/Services/ChequeService.php',
    'backend/app/Banking/Services/PaymentEngine.php'
];

foreach ($files as $relPath) {
    $fullPath = __DIR__ . '/../' . $relPath;
    if (!file_exists($fullPath)) continue;
    $content = file_get_contents($fullPath);
    $orig = $content;
    
    $content = str_replace('AuthMiddleware::getTenantId() ?: 1', 'AuthMiddleware::getTenantId()', $content);
    $content = str_replace("AuthMiddleware::getTenantId() ?: intval(\$_GET['company_id'] ?? 1)", 'AuthMiddleware::getTenantId()', $content);
    $content = str_replace('(int)$request->attributes->get(\'company_id\', 1)', '\App\Http\Middleware\AuthMiddleware::getTenantId()', $content);
    $content = str_replace('$request->attributes->get(\'company_id\', 1)', '\App\Http\Middleware\AuthMiddleware::getTenantId()', $content);

    if (strpos($relPath, 'PaymentEngine.php') !== false) {
        $content = str_replace("'created_by' => 1,", "'created_by' => \\App\\Http\\Middleware\\AuthMiddleware::getUser()?->id ?? \$payment->created_by,", $content);
    }
    if (strpos($relPath, 'ChequeService.php') !== false) {
        $content = str_replace("'created_by' => 1,", "'created_by' => \\App\\Http\\Middleware\\AuthMiddleware::getUser()?->id ?? \$cheque->created_by,", $content);
    }
    if (strpos($relPath, 'GatewayWebhookService.php') !== false) {
        $content = str_replace("'created_by' => 1,", "'created_by' => \$invoice?->created_by ?? \\App\\Http\\Middleware\\AuthMiddleware::getUser()?->id,", $content);
    }

    if ($content !== $orig) {
        file_put_contents($fullPath, $content);
        echo "Updated: $relPath\n";
    }
}
echo "Replacement finished.\n";
