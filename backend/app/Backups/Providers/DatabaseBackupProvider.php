<?php

namespace App\Backups\Providers;

use App\Models\Company;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Purchase;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Cheque;
use App\Models\DocumentNumberingConfig;

class DatabaseBackupProvider
{
    /**
     * Create full company dataset dump as an associative array.
     */
    public static function createCompanyDump(int $companyId): array
    {
        $company = Company::findOrFail($companyId);

        return [
            'metadata' => [
                'app_name' => 'WTSBill',
                'app_version' => '1.0.0',
                'schema_version' => '23.0',
                'exported_at' => date('c'),
                'company_id' => $companyId,
                'company_name' => $company->business_name ?: $company->name,
            ],
            'data' => [
                'company' => [$company->toArray()],
                'branches' => Branch::where('company_id', $companyId)->get()->toArray(),
                'categories' => Category::where('company_id', $companyId)->get()->toArray(),
                'products' => Product::where('company_id', $companyId)->get()->toArray(),
                'customers' => Customer::where('company_id', $companyId)->get()->toArray(),
                'suppliers' => Supplier::where('company_id', $companyId)->get()->toArray(),
                'chart_of_accounts' => ChartOfAccount::where('company_id', $companyId)->get()->toArray(),
                'bank_accounts' => BankAccount::where('company_id', $companyId)->get()->toArray(),
                'document_numbering_configs' => DocumentNumberingConfig::where('company_id', $companyId)->get()->toArray(),
                'invoices' => Invoice::where('company_id', $companyId)->get()->toArray(),
                'invoice_items' => InvoiceItem::where('company_id', $companyId)->get()->toArray(),
                'purchases' => Purchase::where('company_id', $companyId)->get()->toArray(),
                'payments' => Payment::where('company_id', $companyId)->get()->toArray(),
                'payment_allocations' => PaymentAllocation::where('company_id', $companyId)->get()->toArray(),
                'journal_entries' => JournalEntry::where('company_id', $companyId)->get()->toArray(),
                'journal_lines' => JournalLine::where('company_id', $companyId)->get()->toArray(),
                'bank_transactions' => BankTransaction::where('company_id', $companyId)->get()->toArray(),
                'cheques' => Cheque::where('company_id', $companyId)->get()->toArray(),
            ],
        ];
    }
}
