<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database\Database;
use Illuminate\Database\Capsule\Manager as DB;

echo "=========================================" . PHP_EOL;
echo "WTSBill ERP Scheduler Cron Task Started" . PHP_EOL;
echo "Timestamp: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "=========================================" . PHP_EOL;

try {
    Database::init();
    
    // 1. Process Due Recurring Invoices
    $today = date('Y-m-d');
    $dueSubscriptions = DB::table('recurring_invoices')
        ->where('status', 'active')
        ->where('next_issue_date', '<=', $today)
        ->get();
        
    echo "[CRON] Found " . count($dueSubscriptions) . " recurring subscriptions due for billing." . PHP_EOL;
    
    // 2. Process Overdue Invoices
    $overdueInvoices = DB::table('invoices')
        ->whereIn('status', ['pending', 'partially_paid'])
        ->where('due_date', '<', $today)
        ->get();
        
    echo "[CRON] Found " . count($overdueInvoices) . " overdue invoices requiring reminders." . PHP_EOL;
    
    foreach ($overdueInvoices as $inv) {
        DB::table('invoices')->where('id', $inv->id)->update(['status' => 'overdue']);
        echo " - Updated Invoice #" . $inv->invoice_number . " status to OVERDUE." . PHP_EOL;
    }
    
    echo "CRON_EXECUTION_COMPLETED_SUCCESSFULLY" . PHP_EOL;
} catch (\Throwable $e) {
    echo "[CRON ERROR] " . $e->getMessage() . PHP_EOL;
}
