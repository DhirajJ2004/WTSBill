<?php
/**
 * WTSBill ERP — Create Customer Master View
 */
$errors = \WTSBill\Core\Session::getFlash('validation_errors') ?? [];
$errorMsg = \WTSBill\Core\Session::getFlash('error');
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Register New Customer</h1>
        <p class="page-subtitle">Add debtor profile, GSTIN validation, credit limit, and billing/shipping address</p>
    </div>
    <div>
        <a href="/customers" class="btn btn-secondary">&larr; Back to Customers</a>
    </div>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">Customer Onboarding Form</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; border-radius: var(--radius); background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: #fca5a5;">
                <?= e($errorMsg) ?>
                <?php if (!empty($errors)): ?>
                    <ul style="margin: 0.5rem 0 0 1.25rem;">
                        <?php foreach ($errors as $field => $msg): ?>
                            <li><?= e($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/customers/create" id="customer-create-form">
            <input type="hidden" name="_token" value="<?= e(\WTSBill\Core\Session::getCsrfToken()) ?>">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="create-cust-name">Trade / Customer Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="create-cust-name" name="name" class="form-control" placeholder="e.g. Ramesh Hardware Stores" required autofocus>
                </div>

                <div class="form-group">
                    <label for="create-cust-company">Company Legal Name</label>
                    <input type="text" id="create-cust-company" name="company_name" class="form-control" placeholder="e.g. Ramesh Enterprises Pvt Ltd">
                </div>

                <div class="form-group">
                    <label for="create-cust-gstin">GSTIN (15 Digits)</label>
                    <input type="text" id="create-cust-gstin" name="gstin" class="form-control" placeholder="27AAAAA0000A1Z5" data-uppercase maxlength="15">
                </div>

                <div class="form-group">
                    <label for="create-cust-pan">PAN (10 Digits)</label>
                    <input type="text" id="create-cust-pan" name="pan" class="form-control" placeholder="AAAAA0000A" data-uppercase maxlength="10">
                </div>

                <div class="form-group">
                    <label for="create-cust-phone">Contact Phone</label>
                    <input type="tel" id="create-cust-phone" name="phone" class="form-control" placeholder="+91 9876543210">
                </div>

                <div class="form-group">
                    <label for="create-cust-email">Email Address</label>
                    <input type="email" id="create-cust-email" name="email" class="form-control" placeholder="billing@customer.com">
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="create-cust-address">Billing Address</label>
                    <textarea id="create-cust-address" name="address_line1" class="form-control" rows="2" placeholder="Street, Building, Area"></textarea>
                </div>

                <div class="form-group">
                    <label for="create-cust-city">City</label>
                    <input type="text" id="create-cust-city" name="city" class="form-control" placeholder="Mumbai">
                </div>

                <div class="form-group">
                    <label for="create-cust-state">State</label>
                    <input type="text" id="create-cust-state" name="state" class="form-control" placeholder="Maharashtra">
                </div>

                <div class="form-group">
                    <label for="create-cust-pincode">PIN Code</label>
                    <input type="text" id="create-cust-pincode" name="pincode" class="form-control" placeholder="400001">
                </div>

                <div class="form-group">
                    <label for="create-cust-credit-limit">Credit Limit (₹)</label>
                    <input type="number" id="create-cust-credit-limit" name="credit_limit" class="form-control" placeholder="50000" value="50000" step="0.01">
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <a href="/customers" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.5rem; font-size: 0.95rem;">Save Customer Record &rarr;</button>
            </div>
        </form>
    </div>
</div>
