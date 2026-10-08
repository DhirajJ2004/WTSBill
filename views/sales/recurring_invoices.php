<?php
$pageTitle = 'Recurring Billing & Subscriptions - WTSBill ERP';
$pageHeader = 'AMC & Cloud Subscription Automation';
$currentRoute = 'recurring-invoices';

require_once __DIR__ . '/../db_helper.php';

use App\Models\RecurringInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\DocumentNumberService;
use App\Services\AccountingEventService;
use App\Services\AuditLogService;
use Illuminate\Database\Capsule\Manager as DB;

$companyId = get_current_company_id();
$branchId = get_current_branch_id() ?: 1;
$financialYear = get_current_financial_year();

$submitError = null;
$submitSuccess = null;

// -------------------------------------------------------------
// Handle POST actions for Recurring Billing
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isJson = (strpos($_SERVER['HTTP_CONTENT_TYPE'] ?? $_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);
    $input = $isJson ? get_json_input() : $_POST;

    if (empty($input) && php_sapi_name() === 'cli') {
        parse_str(@file_get_contents('php://stdin'), $input);
    }

    $action = $input['action'] ?? ($input['form_action'] ?? 'create_plan');

    if (!$isJson && isset($input['csrf_token']) && !verify_csrf_token()) {
        $submitError = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $userName = $_SESSION['user']['name'] ?? 'Admin';

            if ($action === 'create_plan') {
                $customerId = intval($input['customer_id'] ?? 0);
                $customer = Customer::where('company_id', $companyId)->find($customerId);
                if (!$customer) {
                    throw new \InvalidArgumentException('Please select a valid customer.');
                }

                $templateName = trim($input['template_name'] ?? '');
                if (empty($templateName)) {
                    $templateName = 'Recurring Plan - ' . $customer->name;
                }

                $frequency = strtolower(trim($input['frequency'] ?? 'monthly'));
                $amount = round(floatval($input['grand_total'] ?? ($input['amount'] ?? 0)), 2);
                if ($amount <= 0) {
                    throw new \InvalidArgumentException('Subscription amount must be greater than zero.');
                }

                $startDate = $input['start_date'] ?? date('Y-m-d');
                $nextDate = $startDate;

                $plan = RecurringInvoice::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'customer_id' => $customerId,
                    'template_name' => $templateName,
                    'frequency' => $frequency,
                    'start_date' => $startDate,
                    'next_invoice_date' => $nextDate,
                    'status' => 'active',
                    'sub_total' => round($amount / 1.18, 2),
                    'total_tax' => round($amount - ($amount / 1.18), 2),
                    'grand_total' => $amount,
                    'notes' => trim($input['notes'] ?? ''),
                ]);

                AuditLogService::log(
                    $companyId,
                    $userName,
                    'RECURRING_PLAN_CREATE',
                    'RecurringInvoice',
                    $plan->id,
                    "Created Recurring Subscription Plan #{$plan->id} ({$templateName}) for {$customer->name}"
                );

                if ($isJson) {
                    response_json(['status' => 'success', 'message' => 'Subscription plan created successfully', 'data' => $plan], 201);
                } else {
                    $submitSuccess = "Subscription Plan #{$plan->id} ({$templateName}) created successfully!";
                }
            } elseif ($action === 'run_plan') {
                $planId = intval($input['plan_id'] ?? 0);
                $plan = RecurringInvoice::where('company_id', $companyId)->with('customer')->findOrFail($planId);

                $generatedInv = DB::transaction(function () use ($plan, $companyId, $branchId, $financialYear, $userName) {
                    $invNum = DocumentNumberService::generateNextNumber($companyId, $branchId, $financialYear, 'INVOICE');
                    $grandTotal = floatval($plan->grand_total);
                    $subTotal = floatval($plan->sub_total ?: round($grandTotal / 1.18, 2));
                    $taxTotal = round($grandTotal - $subTotal, 2);
                    $cgst = round($taxTotal / 2, 2);
                    $sgst = round($taxTotal - $cgst, 2);

                    $inv = Invoice::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'customer_id' => $plan->customer_id,
                        'invoice_number' => $invNum,
                        'invoice_date' => date('Y-m-d'),
                        'due_date' => date('Y-m-d', strtotime('+15 days')),
                        'subtotal' => $subTotal,
                        'taxable_amount' => $subTotal,
                        'total_tax' => $taxTotal,
                        'cgst_amount' => $cgst,
                        'sgst_amount' => $sgst,
                        'grand_total' => $grandTotal,
                        'amount_due' => $grandTotal,
                        'status' => 'pending',
                        'financial_year' => $financialYear,
                        'notes' => "Auto-generated from Recurring Plan #{$plan->id} ({$plan->template_name})",
                    ]);

                    // Update plan last generated date & advance next invoice date
                    $nextInterval = '+1 month';
                    if ($plan->frequency === 'quarterly') $nextInterval = '+3 months';
                    elseif ($plan->frequency === 'yearly') $nextInterval = '+1 year';
                    elseif ($plan->frequency === 'weekly') $nextInterval = '+1 week';

                    $plan->last_generated_date = date('Y-m-d');
                    $plan->next_invoice_date = date('Y-m-d', strtotime($nextInterval));
                    $plan->save();

                    // Update customer balance
                    $cust = $plan->customer;
                    if ($cust) {
                        $cust->current_balance = round($cust->current_balance + $grandTotal, 2);
                        $cust->save();
                    }

                    // Post Accounting Journal
                    AccountingEventService::recordSaleAccounting($inv, $userName);

                    AuditLogService::log(
                        $companyId,
                        $userName,
                        'RECURRING_EXECUTE',
                        'Invoice',
                        $inv->id,
                        "Generated Invoice #{$invNum} (₹" . number_format($grandTotal, 2) . ") from Recurring Plan #{$plan->id}"
                    );

                    return $inv;
                });

                if ($isJson) {
                    response_json(['status' => 'success', 'message' => "Invoice #{$generatedInv->invoice_number} generated successfully", 'data' => $generatedInv]);
                } else {
                    $submitSuccess = "Invoice #" . htmlspecialchars($generatedInv->invoice_number) . " generated successfully from subscription plan!";
                }
            } elseif ($action === 'trigger_scheduler') {
                // Find all due active recurring invoices and run them
                $duePlans = RecurringInvoice::where('company_id', $companyId)
                    ->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('next_invoice_date')
                          ->orWhere('next_invoice_date', '<=', date('Y-m-d'));
                    })
                    ->get();

                $runCount = 0;
                foreach ($duePlans as $plan) {
                    $invNum = DocumentNumberService::generateNextNumber($companyId, $branchId, $financialYear, 'INVOICE');
                    $grandTotal = floatval($plan->grand_total);
                    $subTotal = floatval($plan->sub_total ?: round($grandTotal / 1.18, 2));
                    $taxTotal = round($grandTotal - $subTotal, 2);

                    $inv = Invoice::create([
                        'company_id' => $companyId,
                        'branch_id' => $branchId,
                        'customer_id' => $plan->customer_id,
                        'invoice_number' => $invNum,
                        'invoice_date' => date('Y-m-d'),
                        'due_date' => date('Y-m-d', strtotime('+15 days')),
                        'subtotal' => $subTotal,
                        'taxable_amount' => $subTotal,
                        'total_tax' => $taxTotal,
                        'grand_total' => $grandTotal,
                        'amount_due' => $grandTotal,
                        'status' => 'pending',
                        'financial_year' => $financialYear,
                        'notes' => "Auto-scheduled run for Plan #{$plan->id}",
                    ]);

                    $plan->last_generated_date = date('Y-m-d');
                    $plan->next_invoice_date = date('Y-m-d', strtotime('+1 month'));
                    $plan->save();

                    AccountingEventService::recordSaleAccounting($inv, $userName);
                    $runCount++;
                }

                if ($isJson) {
                    response_json(['status' => 'success', 'message' => "Scheduler completed: {$runCount} due invoices generated."]);
                } else {
                    $submitSuccess = "Scheduler executed successfully! Generated {$runCount} due recurring invoices.";
                }
            }
        } catch (\Throwable $e) {
            if ($isJson) {
                response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
            } else {
                $submitError = $e->getMessage();
            }
        }
    }
}

$customers = get_customers();
$plans = RecurringInvoice::where('company_id', $companyId)->with('customer')->orderBy('id', 'desc')->get();

include __DIR__ . '/../layout/header.php';
include __DIR__ . '/../layout/sidebar.php';
?>

<div class="main-wrapper">
    <?php include __DIR__ . '/../layout/navbar.php'; ?>

    <main class="content-area">
        <?php if ($submitSuccess): ?>
            <div class="alert alert-success" style="padding: 14px 18px; margin-bottom: 20px; background: #ecfdf5; border: 1px solid #10b981; border-radius: 8px; color: #065f46; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <i class="fa-solid fa-circle-check text-success" style="font-size: 18px;"></i>
                <span><?= $submitSuccess ?></span>
            </div>
        <?php endif; ?>

        <?php if ($submitError): ?>
            <div class="alert alert-danger" style="padding: 14px 18px; margin-bottom: 20px; background: #fef2f2; border: 1px solid #ef4444; border-radius: 8px; color: #991b1b; display: flex; align-items: center; gap: 10px; font-weight: 600;">
                <i class="fa-solid fa-triangle-exclamation text-danger" style="font-size: 18px;"></i>
                <span><?= htmlspecialchars($submitError) ?></span>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-title"><i class="fa-solid fa-rotate text-primary"></i> Recurring Invoices & AMC Subscriptions</div>
                <div style="display: flex; gap: 12px;">
                    <form method="POST" action="<?= url('/views/sales/recurring_invoices.php') ?>" style="display: inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="trigger_scheduler">
                        <button type="submit" class="btn btn-outline"><i class="fa-solid fa-play"></i> Run Scheduler Now</button>
                    </form>
                    <button class="btn btn-primary" onclick="openModal('addSubscriptionModal')"><i class="fa-solid fa-plus"></i> + Create Subscription Plan</button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Plan ID</th>
                            <th>Customer / Client Name</th>
                            <th>Service Description</th>
                            <th>Billing Frequency</th>
                            <th>Amount (₹)</th>
                            <th>Next Run Date</th>
                            <th>Last Run Date</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($plans->isEmpty()): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 30px; color: #64748b;">
                                    No recurring subscriptions configured yet. Click <strong>+ Create Subscription Plan</strong> to setup automatic billing schedules.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($plans as $p): ?>
                                <tr>
                                    <td><strong>SUB-<?= htmlspecialchars($p->id) ?></strong></td>
                                    <td><strong><?= htmlspecialchars($p->customer?->name ?: 'Customer #' . $p->customer_id) ?></strong></td>
                                    <td><?= htmlspecialchars($p->template_name) ?></td>
                                    <td><span class="badge badge-info"><?= strtoupper(htmlspecialchars($p->frequency)) ?></span></td>
                                    <td><strong>₹<?= number_format($p->grand_total, 2) ?></strong></td>
                                    <td><?= htmlspecialchars($p->next_invoice_date ?: 'Pending') ?></td>
                                    <td><?= htmlspecialchars($p->last_generated_date ?: 'Never') ?></td>
                                    <td><span class="badge <?= $p->status === 'active' ? 'badge-success' : 'badge-secondary' ?>"><?= strtoupper(htmlspecialchars($p->status)) ?></span></td>
                                    <td class="text-right">
                                        <form method="POST" action="<?= url('/views/sales/recurring_invoices.php') ?>" style="display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="run_plan">
                                            <input type="hidden" name="plan_id" value="<?= $p->id ?>">
                                            <button type="submit" class="btn btn-outline btn-sm"><i class="fa-solid fa-bolt"></i> Generate Now</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Add Subscription Modal -->
<div class="modal-overlay" id="addSubscriptionModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-calendar-plus text-primary"></i> Setup Recurring AMC / Subscription Schedule</div>
            <button class="modal-close" onclick="closeModal('addSubscriptionModal')">&times;</button>
        </div>
        <form method="POST" action="<?= url('/views/sales/recurring_invoices.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_plan">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Client / Customer Name *</label>
                    <select name="customer_id" class="form-control" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['email'] ?? 'No email') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Service Description / Plan Name *</label>
                    <input type="text" name="template_name" class="form-control" placeholder="e.g. Monthly Dedicated Server Hosting AMC" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Billing Frequency *</label>
                        <select name="frequency" class="form-control" required>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly (3 months)</option>
                            <option value="bi_annually">Bi-Annually (6 months)</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Billing Amount (incl tax) (₹) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" class="form-control" placeholder="17700.00" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Billing Date *</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes &amp; Terms</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Due within 15 days of invoice date">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSubscriptionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
