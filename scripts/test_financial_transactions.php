<?php

require_once __DIR__ . '/../backend/vendor/autoload.php';

use App\Database\Database;
use Illuminate\Database\Capsule\Manager as DB;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Expense;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\JournalEntry;
use App\Models\AuditLog;
use App\Services\InvoiceService;

Database::init();

$baseUrl = 'http://127.0.0.1:8000/api/v1';

echo "====================================================================\n";
echo "   WTSBill ERP Real Financial Transactions & Persistence Suite     \n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function runHttp(string $method, string $url, array $headers = [], $body = null) {
    $ch = curl_init();
    $curlHeaders = [];
    foreach ($headers as $k => $v) {
        $curlHeaders[] = "{$k}: {$v}";
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    if ($body !== null) {
        $json = is_string($body) ? $body : json_encode($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $curlHeaders[] = 'Content-Type: application/json';
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

    $raw = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $headerStr = substr($raw, 0, $headerSize);
    $bodyStr = substr($raw, $headerSize);
    $data = json_decode($bodyStr, true);

    return [
        'code' => $httpCode,
        'headers' => $headerStr,
        'body' => $data,
        'raw_body' => $bodyStr
    ];
}

function assertCondition($name, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $name\n";
        if ($details) echo "        Detail: $details\n";
    } else {
        $failCount++;
        echo " [FAIL] $name\n";
        if ($details) echo "        Detail: $details\n";
    }
}

// 0. Authenticate User A (Company 1)
$authRes = runHttp('POST', "{$baseUrl}/auth/login", [], [
    'email' => 'anil.d@wtsbill.in',
    'password' => 'password123'
]);

$token = $authRes['body']['access_token'] ?? $authRes['body']['token'] ?? '';
assertCondition("0. Authenticate User A (anil.d@wtsbill.in)", $authRes['code'] === 200 && !empty($token), "HTTP {$authRes['code']}");

$headers = [
    'Authorization' => "Bearer {$token}",
    'X-Company-Id' => '1',
    'Accept' => 'application/json'
];

// ====================================================================
// SECTION 1: CUSTOMER & SUPPLIER REAL PERSISTENCE & VALIDATIONS
// ====================================================================
echo "\n--- SECTION 1: Customer & Supplier Validations & Persistence ---\n";

// 1a. Customer Name Validation
$resBlankName = runHttp('POST', "{$baseUrl}/customers", $headers, ['name' => '']);
assertCondition(
    "1a. Customer blank name rejected with 422",
    $resBlankName['code'] === 422,
    "HTTP {$resBlankName['code']}, Message: " . ($resBlankName['body']['message'] ?? '')
);

// 1b. Customer Phone Validation
$resInvalidPhone = runHttp('POST', "{$baseUrl}/customers", $headers, ['name' => 'Acme Corp', 'phone' => '123']);
assertCondition(
    "1b. Customer invalid phone (<10 digits) rejected with 422",
    $resInvalidPhone['code'] === 422,
    "HTTP {$resInvalidPhone['code']}, Message: " . ($resInvalidPhone['body']['message'] ?? '')
);

// 1c. Customer Email Validation
$resInvalidEmail = runHttp('POST', "{$baseUrl}/customers", $headers, ['name' => 'Acme Corp', 'phone' => '9820012345', 'email' => 'invalid-email-string']);
assertCondition(
    "1c. Customer invalid email format rejected with 422",
    $resInvalidEmail['code'] === 422,
    "HTTP {$resInvalidEmail['code']}, Message: " . ($resInvalidEmail['body']['message'] ?? '')
);

// 1d. Customer GSTIN Validation
$resInvalidGstin = runHttp('POST', "{$baseUrl}/customers", $headers, ['name' => 'Acme Corp', 'phone' => '9820012345', 'gstin' => 'INVALID_GSTIN_123']);
assertCondition(
    "1d. Customer invalid GSTIN format rejected with 422",
    $resInvalidGstin['code'] === 422,
    "HTTP {$resInvalidGstin['code']}, Message: " . ($resInvalidGstin['body']['message'] ?? '')
);

// 1e. Customer PAN Validation
$resInvalidPan = runHttp('POST', "{$baseUrl}/customers", $headers, ['name' => 'Acme Corp', 'phone' => '9820012345', 'pan' => 'BADPAN123']);
assertCondition(
    "1e. Customer invalid PAN format rejected with 422",
    $resInvalidPan['code'] === 422,
    "HTTP {$resInvalidPan['code']}, Message: " . ($resInvalidPan['body']['message'] ?? '')
);

// 1f. Successful Customer Persistence
$uniqueSuffix = substr(uniqid(), -4);
$custName = "Apex MegaRetail " . $uniqueSuffix;
$custGstin = "27AAACA" . rand(1000, 9999) . "A1Z" . rand(1, 9);
$resCreateCust = runHttp('POST', "{$baseUrl}/customers", $headers, [
    'name' => $custName,
    'phone' => '9820199999',
    'email' => "contact.{$uniqueSuffix}@apexretail.com",
    'gstin' => $custGstin,
    'pan' => substr($custGstin, 2, 10),
    'address_line1' => 'Plot 42, MIDC Industrial Area',
    'city' => 'Pune',
    'state' => 'Maharashtra',
    'state_code' => '27'
]);

$createdCustId = $resCreateCust['body']['data']['id'] ?? 0;
$dbCustomer = Customer::withoutGlobalScopes()->find($createdCustId);

assertCondition(
    "1f. Valid Customer persisted into database with company ownership #1",
    $resCreateCust['code'] === 201 && $dbCustomer !== null && (int)$dbCustomer->company_id === 1,
    "HTTP {$resCreateCust['code']}, Customer ID: {$createdCustId}, Name: {$dbCustomer?->name}, PAN: {$dbCustomer?->pan}"
);

// 1g. Cross-Company Party Isolation
$userBAuth = runHttp('POST', "{$baseUrl}/auth/login", [], [
    'email' => 'vikram.m@innovatech.in',
    'password' => 'password123'
]);
$tokenB = $userBAuth['body']['access_token'] ?? $userBAuth['body']['token'] ?? '';
$headersB = [
    'Authorization' => "Bearer {$tokenB}",
    'X-Company-Id' => '2',
    'Accept' => 'application/json'
];

$resCrossCust = runHttp('GET', "{$baseUrl}/customers/{$createdCustId}", $headersB);
assertCondition(
    "1g. Cross-Company customer access is strictly forbidden (HTTP 403)",
    $resCrossCust['code'] === 403,
    "HTTP {$resCrossCust['code']}, Message: " . ($resCrossCust['body']['message'] ?? '')
);

// 1h. Supplier Persistence & Validations
$suppName = "Precision Components " . $uniqueSuffix;
$suppGstin = "27AAACB" . rand(1000, 9999) . "B1Z" . rand(1, 9);
$resCreateSupp = runHttp('POST', "{$baseUrl}/suppliers", $headers, [
    'name' => $suppName,
    'phone' => '9820288888',
    'email' => "sales.{$uniqueSuffix}@precisionparts.in",
    'gstin' => $suppGstin,
    'pan' => substr($suppGstin, 2, 10),
    'address_line1' => 'Sector 7, Bhosari',
    'city' => 'Pune',
    'state' => 'Maharashtra',
    'state_code' => '27'
]);

$createdSuppId = $resCreateSupp['body']['data']['id'] ?? 0;
$dbSupplier = Supplier::withoutGlobalScopes()->find($createdSuppId);

assertCondition(
    "1h. Valid Supplier persisted into database with company ownership #1",
    $resCreateSupp['code'] === 201 && $dbSupplier !== null && (int)$dbSupplier->company_id === 1,
    "HTTP {$resCreateSupp['code']}, Supplier ID: {$createdSuppId}, Name: {$dbSupplier?->name}, GSTIN: {$dbSupplier?->gstin}"
);

// ====================================================================
// SECTION 2: EXPENSE SUBSYSTEM
// ====================================================================
echo "\n--- SECTION 2: Expense Subsystem Real Backend Transactions ---\n";

// 2a. Expense Validation: Amount > 0
$resExpZero = runHttp('POST', "{$baseUrl}/expenses", $headers, ['category' => 'Cloud Infrastructure', 'amount' => 0]);
assertCondition(
    "2a. Expense with zero amount rejected with 422",
    $resExpZero['code'] === 422,
    "HTTP {$resExpZero['code']}, Message: " . ($resExpZero['body']['message'] ?? '')
);

// 2b. Expense Validation: Category required
$resExpBlankCat = runHttp('POST', "{$baseUrl}/expenses", $headers, ['category' => '', 'amount' => 5000]);
assertCondition(
    "2b. Expense with missing category rejected with 422",
    $resExpBlankCat['code'] === 422,
    "HTTP {$resExpBlankCat['code']}, Message: " . ($resExpBlankCat['body']['message'] ?? '')
);

// 2c. Successful Expense with GST, Document Numbering, Bank Deduction, Journal & Audit Log
$bankAcc = BankAccount::where('company_id', 1)->where('is_active', 1)->first();
$initialBankBal = floatval($bankAcc->current_balance);
$expenseAmount = 11800.00;
$expenseTax = 1800.00; // 18% GST on 10,000 net

$resExp = runHttp('POST', "{$baseUrl}/expenses", $headers, [
    'category' => 'Cloud Infrastructure',
    'payee' => 'AWS Cloud Services India',
    'expense_date' => date('Y-m-d'),
    'amount' => $expenseAmount,
    'tax_amount' => $expenseTax,
    'payment_mode' => 'BANK_TRANSFER',
    'bank_account_id' => $bankAcc->id,
    'gstin' => '27AAACA1234A1Z5',
    'is_itc_eligible' => true,
    'reference_no' => 'AWS-INV-9901',
    'description' => 'EC2, RDS and S3 monthly hosting infrastructure charge'
]);

$expId = $resExp['body']['data']['id'] ?? 0;
$dbExpense = Expense::find($expId);

assertCondition(
    "2c. Expense created successfully with HTTP 201 and DocumentNumberService prefix EXP/",
    $resExp['code'] === 201 && $dbExpense !== null && str_starts_with($dbExpense->expense_number, 'EXP/'),
    "HTTP {$resExp['code']}, Expense #: {$dbExpense?->expense_number}, Amount: ₹{$dbExpense?->amount}"
);

// 2d. Cash/Bank ledger update & BankTransaction record
$bankAcc->refresh();
$newBankBal = floatval($bankAcc->current_balance);
$bankTx = BankTransaction::where('company_id', 1)
    ->where('source', 'EXPENSE')
    ->where('source_id', $expId)
    ->first();

assertCondition(
    "2d. Bank account balance reduced by ₹{$expenseAmount} and BankTransaction ledger created",
    $newBankBal === round($initialBankBal - $expenseAmount, 2) && $bankTx !== null && floatval($bankTx->amount) === $expenseAmount,
    "Prev Balance: {$initialBankBal}, New Balance: {$newBankBal}, BankTx Ref: " . ($bankTx?->reference_number ?? 'NONE')
);

// 2e. Double-Entry Accounting Journal for Expense
$expJournal = JournalEntry::where('company_id', 1)
    ->where('reference_type', 'EXPENSE')
    ->where('reference_id', (string)$expId)
    ->with('lines')
    ->first();

$totalDebits = $expJournal ? round(floatval($expJournal->lines->sum('debit')), 2) : 0;
$totalCredits = $expJournal ? round(floatval($expJournal->lines->sum('credit')), 2) : 0;

assertCondition(
    "2e. Double-entry accounting journal posted with balanced debits and credits",
    $expJournal !== null && $totalDebits === $expenseAmount && $totalCredits === $expenseAmount,
    "JV #: {$expJournal?->entry_number}, Debits: ₹{$totalDebits}, Credits: ₹{$totalCredits}"
);

// 2f. Audit Log recorded
$auditExpense = AuditLog::where('company_id', 1)
    ->where('entity_type', 'Expense')
    ->where('entity_id', $expId)
    ->first();

assertCondition(
    "2f. Audit log recorded for Expense creation",
    $auditExpense !== null && str_contains($auditExpense->description, $dbExpense->expense_number),
    "Action: {$auditExpense?->action}, Description: {$auditExpense?->description}"
);

// ====================================================================
// SECTION 3: PAYMENT SUBSYSTEM
// ====================================================================
echo "\n--- SECTION 3: Payment Subsystem Real Backend Transactions ---\n";

// First, create a fresh invoice to receive payment against
$invResult = InvoiceService::createInvoice([
    'company_id' => 1,
    'branch_id' => 1,
    'customer_id' => $createdCustId,
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'items' => [
        [
            'product_id' => 1,
            'quantity' => 2,
            'unit_price' => 1000.00,
            'discount' => 0.00,
            'tax_rate' => 18.0
        ]
    ]
], 1, 1, null);

$invoice = $invResult['data'];
$initialDue = floatval($invoice->amount_due); // ₹2360.00
$initialCustBal = floatval(Customer::withoutGlobalScopes()->find($createdCustId)->current_balance); // ₹2360.00
$bankAcc->refresh();
$prePayBankBal = floatval($bankAcc->current_balance);

// 3a. Prevent Overpayment Validation
$overpayAmount = $initialDue + 500.00;
$resOverpay = runHttp('POST', "{$baseUrl}/payments", $headers, [
    'invoice_id' => $invoice->id,
    'customer_id' => $createdCustId,
    'amount' => $overpayAmount,
    'payment_mode' => 'UPI',
    'payment_type' => 'RECEIPT'
]);

assertCondition(
    "3a. Overpayment rejected with 400 (amount exceeds invoice due)",
    $resOverpay['code'] === 400,
    "HTTP {$resOverpay['code']}, Message: " . ($resOverpay['body']['message'] ?? '')
);

// 3b. Successful Payment Collection & Full Reconciliation
$payAmount = $initialDue; // exact full settlement: ₹2360.00
$utrRef = "UTR" . rand(100000000, 999999999);

$resPay = runHttp('POST', "{$baseUrl}/payments", $headers, [
    'invoice_id' => $invoice->id,
    'customer_id' => $createdCustId,
    'amount' => $payAmount,
    'payment_date' => date('Y-m-d'),
    'payment_mode' => 'BANK_TRANSFER',
    'bank_account_id' => $bankAcc->id,
    'reference_number' => $utrRef,
    'utr' => $utrRef,
    'notes' => 'Settlement against invoice via NEFT',
    'payment_type' => 'RECEIPT',
    'party_type' => 'CUSTOMER'
]);

$paymentId = $resPay['body']['data']['id'] ?? 0;
$dbPayment = Payment::find($paymentId);

assertCondition(
    "3b. Customer receipt recorded successfully with DocumentNumberService format REC/",
    $resPay['code'] === 201 && $dbPayment !== null && str_starts_with($dbPayment->payment_number, 'REC/'),
    "HTTP {$resPay['code']}, Payment #: {$dbPayment?->payment_number}, Amount: ₹{$dbPayment?->amount}, Mode: {$dbPayment?->payment_mode}"
);

// 3c. Invoice Reconciled: amount_paid, amount_due, status
$invoice->refresh();
assertCondition(
    "3c. Invoice amounts reconciled: amount_paid = ₹{$payAmount}, amount_due = 0.00, status = PAID",
    floatval($invoice->amount_paid) === $payAmount && floatval($invoice->amount_due) === 0.00 && $invoice->status === 'PAID',
    "Status: {$invoice->status}, Paid: ₹{$invoice->amount_paid}, Due: ₹{$invoice->amount_due}"
);

// 3d. Customer Balance Deducted
$newCustBal = floatval(Customer::withoutGlobalScopes()->find($createdCustId)->current_balance);
assertCondition(
    "3d. Customer receivable balance decreased by payment amount (₹{$payAmount})",
    $newCustBal === round($initialCustBal - $payAmount, 2),
    "Initial Balance: ₹{$initialCustBal}, New Balance: ₹{$newCustBal}"
);

// 3e. Cash/Bank Ledger Updated
$bankAcc->refresh();
$postPayBankBal = floatval($bankAcc->current_balance);
$bankReceiptTx = BankTransaction::where('company_id', 1)
    ->where('source', 'CUSTOMER_PAYMENT')
    ->where('source_id', $paymentId)
    ->first();

assertCondition(
    "3e. Bank account credited with payment amount and BankTransaction record logged",
    $postPayBankBal === round($prePayBankBal + $payAmount, 2) && $bankReceiptTx !== null && floatval($bankReceiptTx->amount) === $payAmount,
    "Pre-pay Balance: ₹{$prePayBankBal}, Post-pay Balance: ₹{$postPayBankBal}, Tx Ref: " . ($bankReceiptTx?->reference_number ?? 'NONE')
);

// 3f. Double-Entry Accounting Journal for Payment
$payJournal = JournalEntry::where('company_id', 1)
    ->where('reference_type', 'PAYMENT')
    ->where('reference_id', (string)$paymentId)
    ->with('lines')
    ->first();

$payDebits = $payJournal ? round(floatval($payJournal->lines->sum('debit')), 2) : 0;
$payCredits = $payJournal ? round(floatval($payJournal->lines->sum('credit')), 2) : 0;

assertCondition(
    "3f. Payment accounting journal posted with balanced debits and credits",
    $payJournal !== null && $payDebits === $payAmount && $payCredits === $payAmount,
    "JV #: {$payJournal?->entry_number}, Debits: ₹{$payDebits}, Credits: ₹{$payCredits}"
);

// 3g. Audit Log for Payment
$auditPayment = AuditLog::where('company_id', 1)
    ->where('entity_type', 'PAYMENT')
    ->where('entity_id', $paymentId)
    ->first();

assertCondition(
    "3g. Audit log recorded for Customer Receipt",
    $auditPayment !== null && str_contains($auditPayment->description, $dbPayment->payment_number),
    "Action: {$auditPayment?->action}, Description: {$auditPayment?->description}"
);

// 3h. Attempt Payment Against Already Fully Paid Invoice
$resRepeatPay = runHttp('POST', "{$baseUrl}/payments", $headers, [
    'invoice_id' => $invoice->id,
    'customer_id' => $createdCustId,
    'amount' => 500.00,
    'payment_mode' => 'CASH',
    'payment_type' => 'RECEIPT'
]);

assertCondition(
    "3h. Repeat payment on fully settled invoice is rejected with 400",
    $resRepeatPay['code'] === 400,
    "HTTP {$resRepeatPay['code']}, Message: " . ($resRepeatPay['body']['message'] ?? '')
);

echo "\n====================================================================\n";
echo "   FINANCIAL TRANSACTIONS SUMMARY: {$passCount} PASSED, {$failCount} FAILED\n";
echo "====================================================================\n\n";

if ($failCount > 0) {
    exit(1);
}
