<?php

namespace App\Exports\Services;

use App\Models\ExportJob;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\BankTransaction;
use App\Models\AuditLog;
use App\Exports\Generators\CsvExportGenerator;
use App\Exports\Generators\ExcelExportGenerator;
use App\Audit\Services\AuditTrailService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExportService
{
    /**
     * Export data according to type, format, filters and column selection.
     */
    public static function createExport(
        int $companyId,
        string $dataType,
        string $format = 'CSV',
        array $filters = [],
        ?array $selectedColumns = null,
        ?int $branchId = null,
        ?string $userName = 'System'
    ): array {
        $type = strtoupper($dataType);
        $fmt = strtoupper($format);
        $correlationId = AuditTrailService::generateCorrelationId('EXP');

        $dataset = self::extractDataset($companyId, $type, $filters, $branchId);
        $allHeaders = $dataset['headers'];
        $allRows = $dataset['rows'];

        // Filter columns if specified
        $finalHeaders = $allHeaders;
        $finalRows = $allRows;
        if (!empty($selectedColumns)) {
            $finalHeaders = array_values(array_intersect($allHeaders, $selectedColumns));
            $finalRows = [];
            foreach ($allRows as $r) {
                $filteredRow = [];
                foreach ($finalHeaders as $h) {
                    $filteredRow[$h] = $r[$h] ?? '';
                }
                $finalRows[] = $filteredRow;
            }
        }

        // Generate file content
        $content = match ($fmt) {
            'CSV' => CsvExportGenerator::generate($finalHeaders, $finalRows),
            'XLSX' => ExcelExportGenerator::generate("WTSBill - {$type} Export", $finalHeaders, $finalRows),
            default => throw new InvalidArgumentException("Unsupported export format: {$fmt}"),
        };

        $token = bin2hex(random_bytes(32));
        $fileName = strtolower($type) . '_export_' . date('Ymd_His') . '.' . strtolower($fmt);

        $job = ExportJob::create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'data_type' => $type,
            'export_format' => $fmt,
            'columns_json' => $finalHeaders,
            'filters_json' => $filters,
            'file_name' => $fileName,
            'file_size' => strlen($content),
            'download_token' => $token,
            'download_token_expires_at' => Carbon::now()->addHours(1),
            'status' => 'READY',
            'total_records' => count($finalRows),
            'correlation_id' => $correlationId,
            'created_by' => $userName,
            'completed_at' => Carbon::now(),
        ]);

        AuditTrailService::log(
            $companyId,
            'DATA_EXPORTED',
            'EXPORT_JOB',
            $job->id,
            "Exported {$job->total_records} records of {$type} in {$fmt} format",
            null,
            ['data_type' => $type, 'format' => $fmt, 'records' => count($finalRows)],
            null,
            1,
            $userName,
            $branchId,
            $correlationId
        );

        return [
            'job_id' => $job->id,
            'file_name' => $fileName,
            'format' => $fmt,
            'total_records' => count($finalRows),
            'download_token' => $token,
            'download_expires_at' => $job->download_token_expires_at->toIso8601String(),
            'content' => $content,
        ];
    }

    /**
     * Extract structured rows and headers for the given dataset.
     */
    private static function extractDataset(int $companyId, string $type, array $filters, ?int $branchId): array
    {
        return match ($type) {
            'CUSTOMERS' => self::extractCustomers($companyId, $filters),
            'SUPPLIERS' => self::extractSuppliers($companyId, $filters),
            'PRODUCTS', 'INVENTORY' => self::extractProducts($companyId, $filters),
            'SALES', 'INVOICES' => self::extractInvoices($companyId, $filters, $branchId),
            'PURCHASES' => self::extractPurchases($companyId, $filters, $branchId),
            'PAYMENTS' => self::extractPayments($companyId, $filters, $branchId),
            'EXPENSES' => self::extractExpenses($companyId, $filters, $branchId),
            'LEDGER' => self::extractLedger($companyId, $filters),
            'BANK_TRANSACTIONS' => self::extractBankTransactions($companyId, $filters),
            'AUDIT_LOGS' => self::extractAuditLogs($companyId, $filters),
            default => throw new InvalidArgumentException("Unknown export dataset: {$type}"),
        };
    }

    private static function extractCustomers(int $companyId, array $filters): array
    {
        $q = Customer::where('company_id', $companyId);
        $headers = ['id', 'name', 'phone', 'email', 'gstin', 'pan', 'state', 'current_balance', 'credit_limit', 'is_active'];
        $rows = $q->get()->map(fn($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'email' => $c->email,
            'gstin' => $c->gstin,
            'pan' => $c->pan,
            'state' => $c->state,
            'current_balance' => number_format((float)$c->current_balance, 2, '.', ''),
            'credit_limit' => number_format((float)$c->credit_limit, 2, '.', ''),
            'is_active' => $c->is_active ? 'YES' : 'NO',
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function extractSuppliers(int $companyId, array $filters): array
    {
        $q = Supplier::where('company_id', $companyId);
        $headers = ['id', 'name', 'phone', 'email', 'gstin', 'pan', 'state', 'current_balance', 'bank_name', 'bank_account_number'];
        $rows = $q->get()->map(fn($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'email' => $s->email,
            'gstin' => $s->gstin,
            'pan' => $s->pan,
            'state' => $s->state,
            'current_balance' => number_format((float)$s->current_balance, 2, '.', ''),
            'bank_name' => $s->bank_name,
            'bank_account_number' => $s->bank_account_number,
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function extractProducts(int $companyId, array $filters): array
    {
        $q = Product::with('category')->where('company_id', $companyId);
        $headers = ['id', 'name', 'sku', 'barcode', 'category', 'unit', 'purchase_price', 'selling_price', 'current_stock', 'min_stock_level'];
        $rows = $q->get()->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'category' => $p->category?->name ?? 'General',
            'unit' => $p->unit,
            'purchase_price' => number_format((float)$p->purchase_price, 2, '.', ''),
            'selling_price' => number_format((float)$p->selling_price, 2, '.', ''),
            'current_stock' => $p->current_stock,
            'min_stock_level' => $p->min_stock_level,
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function extractInvoices(int $companyId, array $filters, ?int $branchId): array
    {
        $q = Invoice::where('company_id', $companyId);
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }
        $headers = ['id', 'invoice_number', 'invoice_date', 'customer_name', 'subtotal', 'tax_total', 'grand_total', 'amount_paid', 'amount_due', 'status'];
        $rows = $q->get()->map(fn($i) => [
            'id' => $i->id,
            'invoice_number' => $i->invoice_number,
            'invoice_date' => $i->invoice_date,
            'customer_name' => $i->customer_name,
            'subtotal' => number_format((float)$i->subtotal, 2, '.', ''),
            'tax_total' => number_format((float)$i->tax_total, 2, '.', ''),
            'grand_total' => number_format((float)$i->grand_total, 2, '.', ''),
            'amount_paid' => number_format((float)$i->amount_paid, 2, '.', ''),
            'amount_due' => number_format((float)$i->amount_due, 2, '.', ''),
            'status' => $i->status,
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function extractPurchases(int $companyId, array $filters, ?int $branchId): array
    {
        $q = Purchase::where('company_id', $companyId);
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }
        $headers = ['id', 'invoice_number', 'invoice_date', 'supplier_name', 'taxable_amount', 'tax_amount', 'total_amount', 'paid_amount', 'due_amount', 'status'];
        $rows = $q->get()->map(fn($p) => [
            'id' => $p->id,
            'invoice_number' => $p->purchase_number ?: ($p->invoice_number ?: "PUR-{$p->id}"),
            'invoice_date' => $p->purchase_date ?: $p->created_at?->toDateString(),
            'supplier_name' => $p->supplier?->name ?? 'Supplier',
            'taxable_amount' => number_format((float)($p->taxable_amount ?? $p->total_taxable_amount ?? 0), 2, '.', ''),
            'tax_amount' => number_format((float)($p->tax_amount ?? $p->total_tax_amount ?? 0), 2, '.', ''),
            'total_amount' => number_format((float)($p->total_amount ?? $p->grand_total ?? 0), 2, '.', ''),
            'paid_amount' => number_format((float)($p->paid_amount ?? $p->amount_paid ?? 0), 2, '.', ''),
            'due_amount' => number_format((float)($p->due_amount ?? $p->amount_due ?? 0), 2, '.', ''),
            'status' => $p->status,
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function extractPayments(int $companyId, array $filters, ?int $branchId): array
    {
        $q = Payment::where('company_id', $companyId);
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }
        $headers = ['id', 'payment_number', 'payment_date', 'payment_type', 'party_type', 'amount', 'allocated_amount', 'unallocated_amount', 'payment_mode', 'status'];
        $rows = $q->get()->map(fn($pm) => [
            'id' => $pm->id,
            'payment_number' => $pm->payment_number,
            'payment_date' => $pm->payment_date,
            'payment_type' => $pm->payment_type,
            'party_type' => $pm->party_type,
            'amount' => number_format((float)$pm->amount, 2, '.', ''),
            'allocated_amount' => number_format((float)$pm->allocated_amount, 2, '.', ''),
            'unallocated_amount' => number_format((float)$pm->unallocated_amount, 2, '.', ''),
            'payment_mode' => $pm->payment_mode,
            'status' => $pm->status,
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function extractExpenses(int $companyId, array $filters, ?int $branchId): array
    {
        $q = Expense::where('company_id', $companyId);
        if ($branchId) {
            $q->where('branch_id', $branchId);
        }
        $headers = ['id', 'expense_number', 'expense_date', 'title', 'amount', 'tax_amount', 'payment_mode', 'status'];
        $rows = $q->get()->map(fn($e) => [
            'id' => $e->id,
            'expense_number' => $e->expense_number,
            'expense_date' => $e->expense_date,
            'title' => $e->title,
            'amount' => number_format((float)$e->amount, 2, '.', ''),
            'tax_amount' => number_format((float)$e->tax_amount, 2, '.', ''),
            'payment_mode' => $e->payment_mode,
            'status' => $e->status,
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function extractLedger(int $companyId, array $filters): array
    {
        $q = JournalEntry::where('company_id', $companyId);
        $headers = ['id', 'entry_number', 'entry_date', 'financial_year', 'total_debit', 'total_credit', 'status', 'description'];
        $rows = $q->get()->map(fn($j) => [
            'id' => $j->id,
            'entry_number' => $j->entry_number,
            'entry_date' => $j->entry_date,
            'financial_year' => $j->financial_year,
            'total_debit' => number_format((float)$j->total_debit, 2, '.', ''),
            'total_credit' => number_format((float)$j->total_credit, 2, '.', ''),
            'status' => $j->status,
            'description' => $j->description,
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function extractBankTransactions(int $companyId, array $filters): array
    {
        $q = BankTransaction::where('company_id', $companyId);
        $headers = ['id', 'transaction_date', 'type', 'transaction_type', 'reference_number', 'amount', 'balance_after', 'is_reconciled'];
        $rows = $q->get()->map(fn($b) => [
            'id' => $b->id,
            'transaction_date' => $b->transaction_date,
            'type' => $b->type,
            'transaction_type' => $b->transaction_type,
            'reference_number' => $b->reference_number,
            'amount' => number_format((float)$b->amount, 2, '.', ''),
            'balance_after' => number_format((float)$b->balance_after, 2, '.', ''),
            'is_reconciled' => $b->is_reconciled ? 'YES' : 'NO',
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }

    private static function extractAuditLogs(int $companyId, array $filters): array
    {
        $q = AuditLog::where('company_id', $companyId)->orderBy('id', 'desc')->take(500);
        $headers = ['id', 'created_at', 'user_name', 'action', 'entity_type', 'entity_id', 'description', 'severity', 'status'];
        $rows = $q->get()->map(fn($a) => [
            'id' => $a->id,
            'created_at' => $a->created_at?->toIso8601String(),
            'user_name' => $a->user_name,
            'action' => $a->action,
            'entity_type' => $a->entity_type,
            'entity_id' => $a->entity_id,
            'description' => $a->description,
            'severity' => $a->severity,
            'status' => $a->status,
        ])->toArray();
        return ['headers' => $headers, 'rows' => $rows];
    }
}
