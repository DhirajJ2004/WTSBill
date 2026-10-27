<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voucher <?= e($payment['payment_number']) ?> &mdash; WTSBill ERP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #ffffff;
            color: #111827;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            border-bottom: 2px solid #111827;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
        }
        .company-name { font-size: 1.5rem; font-weight: bold; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
        .meta-table td { padding: 0.5rem; border: 1px solid #e5e7eb; }
        .amount-box {
            background: #f3f4f6;
            border: 2px dashed #9ca3af;
            padding: 1.25rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 2rem;
        }
        .signature-line {
            display: flex;
            justify-content: space-between;
            margin-top: 4rem;
            padding-top: 1rem;
        }
        .sig-block { border-top: 1px solid #111; width: 200px; text-align: center; font-size: 0.85rem; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
        <button type="button" onclick="window.print()" style="padding: 0.5rem 1rem; background: #0284c7; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Print Voucher</button>
        <button type="button" onclick="window.close()" style="padding: 0.5rem 1rem; background: #64748b; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Close</button>
    </div>

    <div class="header">
        <div>
            <div class="company-name"><?= e($company['name'] ?? 'Wis Technosavvy Pvt Ltd') ?></div>
            <div style="font-size: 0.85rem; color: #4b5563; margin-top: 0.25rem;">
                GSTIN: <?= e($company['gstin'] ?? '27AADCW7577N1ZE') ?> &bull; PAN: <?= e($company['pan'] ?? 'AADCW7577N') ?><br>
                <?= e($company['address_line1'] ?? 'Mumbai, Maharashtra') ?>
            </div>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; text-transform: uppercase; color: #0284c7;">
                <?= $payment['payment_type'] === 'RECEIPT' ? 'OFFICIAL MONEY RECEIPT' : 'PAYMENT VOUCHER' ?>
            </h2>
            <div style="font-size: 1.1rem; font-weight: bold; margin-top: 0.25rem;"><?= e($payment['payment_number']) ?></div>
            <div style="font-size: 0.85rem; color: #4b5563;">Date: <?= e(format_date($payment['payment_date'])) ?></div>
        </div>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 25%; font-weight: bold; background: #f9fafb;">Received From / Paid To</td>
            <td style="width: 75%; font-size: 1.1rem; font-weight: bold;"><?= e($partyName) ?></td>
        </tr>
        <tr>
            <td style="font-weight: bold; background: #f9fafb;">Payment Mode</td>
            <td><?= e($payment['payment_mode']) ?></td>
        </tr>
        <?php if (!empty($payment['reference_no'])): ?>
            <tr>
                <td style="font-weight: bold; background: #f9fafb;">Reference / UTR #</td>
                <td><code><?= e($payment['reference_no']) ?></code></td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($payment['notes'])): ?>
            <tr>
                <td style="font-weight: bold; background: #f9fafb;">Remarks / Particulars</td>
                <td><?= e($payment['notes']) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="amount-box">
        AMOUNT: <?= format_currency((float)$payment['amount']) ?>
        <div style="font-size: 0.9rem; font-weight: normal; color: #4b5563; margin-top: 0.25rem;">
            <?= e(amount_to_words_inr((float)$payment['amount'])) ?>
        </div>
    </div>

    <div class="signature-line">
        <div class="sig-block">Prepared By: <?= e($payment['created_by'] ?? 'System') ?></div>
        <div class="sig-block">Authorized Signatory</div>
    </div>
</body>
</html>
