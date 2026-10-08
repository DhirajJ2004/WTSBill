<?php

namespace App\DocumentEngine\Models;

use App\DocumentEngine\Formatters\NumberToWordsFormatter;
use App\DocumentEngine\Formatters\IndianCurrencyFormatter;

class DocumentModel
{
    public string $documentType;
    public string $title;
    public string $documentNumber;
    public string $documentDate;
    public ?string $dueDate = null;
    public ?string $referenceNumber = null;
    public ?string $placeOfSupply = null;

    // Company & Branch
    public array $company = [];
    public ?array $branch = null;

    // Party (Customer or Supplier)
    public ?array $party = null;

    // Items & Tables
    public array $items = [];
    public array $taxBreakdown = [];
    public array $totals = [];

    // Payment Tracking
    public array $paymentInfo = [
        'status' => 'UNPAID', // PAID, PARTIALLY_PAID, UNPAID
        'amount_paid' => 0.00,
        'amount_due' => 0.00,
        'payment_mode' => null,
    ];

    // Bank Details
    public ?array $bankDetails = null;

    // Statements / Books Table
    public ?array $statementData = null;

    // Reconciliation Specifics
    public ?array $reconciliationData = null;

    // Notes, Terms & Signatory
    public ?string $notes = null;
    public ?string $terms = null;
    public array $signatory = [
        'title' => 'For',
        'authorized_label' => 'Authorized Signatory',
        'signature_url' => null,
        'stamp_url' => null,
    ];

    // Metadata
    public array $metadata = [];

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $val) {
            if (property_exists($this, $key)) {
                $this->{$key} = $val;
            }
        }
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Compute totals and formatted amount in words
     */
    public function calculateTotalsAndWords(): void
    {
        $grandTotal = floatval($this->totals['grand_total'] ?? 0.0);
        $this->totals['formatted_grand_total'] = IndianCurrencyFormatter::format($grandTotal);
        $this->totals['amount_in_words'] = NumberToWordsFormatter::convert($grandTotal, 'Rupees');

        if (isset($this->totals['taxable_amount'])) {
            $this->totals['formatted_taxable_amount'] = IndianCurrencyFormatter::format(floatval($this->totals['taxable_amount']));
        }
    }
}
