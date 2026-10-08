<?php

namespace App\DocumentEngine\Services;

use App\Models\Company;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\DeliveryChallan;
use App\Models\CreditNote;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\DebitNote;
use App\Models\Payment;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\DocumentAuditLog;
use App\DocumentEngine\Models\DocumentModel;
use App\DocumentEngine\Types\DocumentType;
use App\DocumentEngine\Templates\DocumentTemplateRegistry;
use App\DocumentEngine\Renderers\HtmlDocumentRenderer;
use App\DocumentEngine\Renderers\PdfDocumentRenderer;
use App\DocumentEngine\Renderers\ThermalDocumentRenderer;
use App\Services\LedgerService;
use App\Services\BankingReportService;
use App\Services\AccountingReportService;
use App\Services\GstReportService;
use Exception;

class DocumentService
{
    /**
     * Master generation flow
     */
    public static function generateDocument(
        string $documentType,
        string $sourceId,
        int $companyId,
        ?int $branchId = null,
        ?int $templateId = null,
        string $format = 'PDF',
        string $userName = 'System',
        bool $freezeSnapshot = true
    ): array {
        $docType = strtoupper($documentType);

        // 1. Resolve Template
        $template = null;
        if ($templateId) {
            $template = DocumentTemplate::find($templateId);
        }
        if (!$template) {
            $template = DocumentTemplate::where('company_id', $companyId)
                ->where('document_type', $docType)
                ->where('is_default', true)
                ->first();
        }
        if (!$template) {
            // Seed defaults and retry
            DocumentTemplateRegistry::seedDefaultTemplates($companyId, $branchId);
            $template = DocumentTemplate::where('company_id', $companyId)
                ->where('document_type', $docType)
                ->first();
        }

        // 2. Check for pre-existing frozen snapshot for finalized transactions
        $existing = GeneratedDocument::where('company_id', $companyId)
            ->where('document_type', $docType)
            ->where('source_id', (string)$sourceId)
            ->where('is_frozen', true)
            ->first();

        if ($existing && !empty($existing->snapshot_json)) {
            $docModel = DocumentModel::fromArray($existing->snapshot_json);
        } else {
            // Build live DocumentModel from source data
            $docModel = static::buildDocumentModel($docType, $sourceId, $companyId, $branchId);
        }

        // 3. Render
        $isThermal = ($template && strtoupper($template->paper_size) === 'THERMAL_80MM');
        if ($isThermal) {
            $html = ThermalDocumentRenderer::render($docModel, $template);
            $pdfRes = PdfDocumentRenderer::renderToPdf($docModel, $template);
        } else {
            $html = HtmlDocumentRenderer::render($docModel, $template);
            $pdfRes = PdfDocumentRenderer::renderToPdf($docModel, $template);
        }

        // 4. Generate Professional File Name: e.g. INV-2026-27-0001.pdf
        $cleanDocNum = preg_replace('/[^A-Za-z0-9_-]/', '-', $docModel->documentNumber);
        $fileName = "{$cleanDocNum}.pdf";

        // 5. Store / Update Generated Document Record
        $generatedDoc = GeneratedDocument::updateOrCreate(
            [
                'company_id' => $companyId,
                'document_type' => $docType,
                'source_type' => $docType,
                'source_id' => (string)$sourceId,
            ],
            [
                'branch_id' => $branchId,
                'document_number' => $docModel->documentNumber,
                'document_date' => $docModel->documentDate,
                'template_id' => $template?->id,
                'version' => $template?->version ?? 1,
                'file_name' => $fileName,
                'mime_type' => 'application/pdf',
                'file_size' => $pdfRes['file_size'],
                'checksum_hash' => $pdfRes['checksum_hash'],
                'snapshot_json' => $docModel->toArray(),
                'is_frozen' => $freezeSnapshot,
                'generated_by' => $userName,
            ]
        );

        // 6. Audit Log
        DocumentAuditLog::create([
            'company_id' => $companyId,
            'generated_document_id' => $generatedDoc->id,
            'action' => 'GENERATED',
            'user_name' => $userName,
            'metadata_json' => [
                'document_type' => $docType,
                'source_id' => $sourceId,
                'format' => $format,
                'checksum' => $pdfRes['checksum_hash'],
            ]
        ]);

        return [
            'status' => 'success',
            'generated_document_id' => $generatedDoc->id,
            'document_number' => $docModel->documentNumber,
            'file_name' => $fileName,
            'html' => $html,
            'pdf_content' => $pdfRes['pdf_content'],
            'checksum_hash' => $pdfRes['checksum_hash'],
            'model' => $docModel->toArray(),
        ];
    }

    /**
     * Read-Only Document Preview
     */
    public static function previewDocument(
        string $documentType,
        string $sourceId,
        int $companyId,
        ?int $branchId = null,
        ?int $templateId = null
    ): array {
        $docType = strtoupper($documentType);

        $template = $templateId ? DocumentTemplate::find($templateId) : null;
        if (!$template) {
            $template = DocumentTemplate::where('company_id', $companyId)
                ->where('document_type', $docType)
                ->where('is_default', true)
                ->first();
        }

        $docModel = static::buildDocumentModel($docType, $sourceId, $companyId, $branchId);
        $html = HtmlDocumentRenderer::render($docModel, $template);

        return [
            'status' => 'success',
            'document_number' => $docModel->documentNumber,
            'title' => $docModel->title,
            'html' => $html,
            'model' => $docModel->toArray(),
        ];
    }

    /**
     * Build intermediate presentation DocumentModel from source data
     */
    public static function buildDocumentModel(
        string $documentType,
        string $sourceId,
        int $companyId,
        ?int $branchId = null
    ): DocumentModel {
        $company = Company::findOrFail($companyId);
        $branch = $branchId ? Branch::find($branchId) : null;

        $docType = strtoupper($documentType);
        $meta = DocumentType::getMeta($docType);

        $doc = new DocumentModel();
        $doc->documentType = $docType;
        $doc->title = $meta['title'] ?? 'DOCUMENT';

        // Set Company Profile
        $doc->company = [
            'name' => $company->name,
            'legal_name' => $company->legal_name ?: $company->name,
            'gstin' => $company->gstin,
            'pan' => $company->pan,
            'email' => $company->email,
            'phone' => $company->phone,
            'address_line1' => $company->address_line1,
            'address_line2' => $company->address_line2,
            'city' => $company->city,
            'state' => $company->state,
            'state_code' => $company->state_code ?: '27',
            'pincode' => $company->pincode,
            'logo_url' => $company->logo_url,
        ];

        // Set Branch Profile if applicable
        if ($branch) {
            $doc->branch = [
                'name' => $branch->name,
                'gstin' => $branch->gstin ?: $company->gstin,
                'address_line1' => $branch->address,
                'city' => $branch->city,
                'state' => $branch->state,
                'state_code' => $branch->state_code,
                'phone' => $branch->phone,
            ];
        }

        // Set Bank Details for payments
        $primaryBank = BankAccount::where('company_id', $companyId)->where('is_active', true)->first();
        if ($primaryBank) {
            $doc->bankDetails = [
                'bank_name' => $primaryBank->bank_name,
                'account_name' => $primaryBank->account_name ?: $company->name,
                'account_number' => $primaryBank->account_number,
                'masked_account_number' => $primaryBank->masked_account_number,
                'ifsc_code' => $primaryBank->ifsc_code,
                'branch_name' => $primaryBank->branch_name,
                'upi_id' => $primaryBank->upi_id,
            ];
        }

        // Populate by Document Type
        switch ($docType) {
            case DocumentType::SALES_INVOICE:
                static::populateSalesInvoice($doc, $sourceId, $companyId);
                break;

            case DocumentType::QUOTATION:
                static::populateQuotation($doc, $sourceId, $companyId);
                break;

            case DocumentType::SALES_ORDER:
                static::populateSalesOrder($doc, $sourceId, $companyId);
                break;

            case DocumentType::DELIVERY_CHALLAN:
                static::populateDeliveryChallan($doc, $sourceId, $companyId);
                break;

            case DocumentType::CREDIT_NOTE:
                static::populateCreditNote($doc, $sourceId, $companyId);
                break;

            case DocumentType::PURCHASE_INVOICE:
                static::populatePurchaseInvoice($doc, $sourceId, $companyId);
                break;

            case DocumentType::PURCHASE_ORDER:
                static::populatePurchaseOrder($doc, $sourceId, $companyId);
                break;

            case DocumentType::DEBIT_NOTE:
                static::populateDebitNote($doc, $sourceId, $companyId);
                break;

            case DocumentType::PAYMENT_RECEIPT:
                static::populatePaymentReceipt($doc, $sourceId, $companyId);
                break;

            case DocumentType::CUSTOMER_STATEMENT:
                static::populateCustomerStatement($doc, $sourceId, $companyId);
                break;

            case DocumentType::SUPPLIER_STATEMENT:
                static::populateSupplierStatement($doc, $sourceId, $companyId);
                break;

            case DocumentType::CASH_BOOK:
                static::populateCashBook($doc, $companyId);
                break;

            case DocumentType::BANK_BOOK:
                static::populateBankBook($doc, $sourceId, $companyId);
                break;

            case DocumentType::CHEQUE_REGISTER:
                static::populateChequeRegister($doc, $companyId);
                break;

            case DocumentType::BANK_RECONCILIATION:
                static::populateBankReconciliation($doc, $sourceId, $companyId);
                break;

            default:
                $doc->documentNumber = "DOC-{$sourceId}";
                $doc->documentDate = date('Y-m-d');
                break;
        }

        $doc->calculateTotalsAndWords();
        return $doc;
    }

    private static function formatDate($date): ?string
    {
        if (empty($date)) return null;
        if (is_object($date) && method_exists($date, 'format')) {
            return $date->format('Y-m-d');
        }
        return (string)$date;
    }

    private static function populateSalesInvoice(DocumentModel $doc, string $id, int $companyId): void
    {
        $inv = Invoice::with(['items', 'customer'])->where('company_id', $companyId)->findOrFail($id);

        $doc->documentNumber = $inv->invoice_number;
        $doc->documentDate = static::formatDate($inv->invoice_date) ?: date('Y-m-d');
        $doc->dueDate = static::formatDate($inv->due_date);
        $doc->referenceNumber = $inv->reference_no;
        $doc->placeOfSupply = $inv->place_of_supply;
        $doc->notes = $inv->notes;
        $doc->terms = $inv->terms;

        if ($inv->customer) {
            $doc->party = [
                'party_type' => 'CUSTOMER',
                'name' => $inv->customer->name,
                'gstin' => $inv->customer->gstin,
                'pan' => $inv->customer->pan,
                'billing_address' => $inv->billing_address ?: $inv->customer->address_line1,
                'shipping_address' => $inv->shipping_address ?: $inv->customer->address_line1,
                'state' => $inv->customer->state,
                'state_code' => $inv->customer->state_code,
                'phone' => $inv->customer->phone,
                'email' => $inv->customer->email,
            ];
        }

        $items = [];
        foreach ($inv->items as $idx => $it) {
            $items[] = [
                'sr_no' => $idx + 1,
                'name' => $it->item_name ?: 'Product Item',
                'description' => $it->description,
                'hsn_sac' => $it->hsn_sac,
                'quantity' => $it->quantity,
                'unit' => $it->unit ?: 'Pcs',
                'unit_price' => floatval($it->unit_price),
                'discount_amount' => floatval($it->discount_amount),
                'taxable_amount' => floatval($it->taxable_value),
                'gst_rate' => floatval($it->gst_rate),
                'cgst_amount' => floatval($it->cgst_amount),
                'sgst_amount' => floatval($it->sgst_amount),
                'igst_amount' => floatval($it->igst_amount),
                'cess_amount' => floatval($it->cess_amount ?? 0),
                'total_amount' => floatval($it->total_amount),
            ];
        }
        $doc->items = $items;

        $doc->totals = [
            'sub_total' => floatval($inv->sub_total),
            'discount_amount' => floatval($inv->discount_amount),
            'taxable_amount' => floatval($inv->taxable_value),
            'cgst_amount' => floatval($inv->cgst_amount),
            'sgst_amount' => floatval($inv->sgst_amount),
            'igst_amount' => floatval($inv->igst_amount),
            'cess_amount' => floatval($inv->cess_amount ?? 0),
            'round_off' => floatval($inv->round_off ?? 0),
            'grand_total' => floatval($inv->grand_total),
        ];

        $doc->paymentInfo = [
            'status' => $inv->status, // DRAFT, POSTED, PAID, PARTIALLY_PAID, CANCELLED
            'amount_paid' => floatval($inv->amount_paid),
            'amount_due' => floatval($inv->amount_due),
        ];

        // Tax Breakdown
        $doc->taxBreakdown = [
            [
                'hsn_sac' => 'HSN Summary',
                'taxable_amount' => floatval($inv->taxable_value),
                'cgst_amount' => floatval($inv->cgst_amount),
                'sgst_amount' => floatval($inv->sgst_amount),
                'igst_amount' => floatval($inv->igst_amount),
                'total_tax' => floatval($inv->total_tax),
            ]
        ];
    }

    private static function populateQuotation(DocumentModel $doc, string $id, int $companyId): void
    {
        $q = Quotation::with(['items', 'customer'])->where('company_id', $companyId)->findOrFail($id);

        $doc->documentNumber = $q->quotation_number;
        $doc->documentDate = static::formatDate($q->quotation_date) ?: date('Y-m-d');
        $doc->dueDate = static::formatDate($q->valid_until);
        $doc->referenceNumber = $q->reference_no;
        $doc->notes = $q->notes;
        $doc->terms = $q->terms;
        $doc->metadata['watermark'] = 'QUOTATION - NOT AN INVOICE';

        if ($q->customer) {
            $doc->party = [
                'party_type' => 'CUSTOMER',
                'name' => $q->customer->name,
                'gstin' => $q->customer->gstin,
                'billing_address' => $q->billing_address,
                'shipping_address' => $q->shipping_address,
                'state' => $q->customer->state,
                'phone' => $q->customer->phone,
            ];
        }

        $items = [];
        foreach ($q->items as $idx => $it) {
            $items[] = [
                'sr_no' => $idx + 1,
                'name' => $it->item_name ?: 'Product Item',
                'hsn_sac' => $it->hsn_sac,
                'quantity' => $it->quantity,
                'unit' => $it->unit ?: 'Pcs',
                'unit_price' => floatval($it->unit_price),
                'discount_amount' => floatval($it->discount_amount),
                'taxable_amount' => floatval($it->taxable_value),
                'total_amount' => floatval($it->total_amount),
            ];
        }
        $doc->items = $items;

        $doc->totals = [
            'taxable_amount' => floatval($q->taxable_value),
            'cgst_amount' => floatval($q->cgst_amount),
            'sgst_amount' => floatval($q->sgst_amount),
            'igst_amount' => floatval($q->igst_amount),
            'grand_total' => floatval($q->grand_total),
        ];
    }

    private static function populateSalesOrder(DocumentModel $doc, string $id, int $companyId): void
    {
        $so = SalesOrder::with(['items', 'customer'])->where('company_id', $companyId)->findOrFail($id);
        $doc->documentNumber = $so->order_number;
        $doc->documentDate = static::formatDate($so->order_date) ?: date('Y-m-d');
        $doc->referenceNumber = $so->reference_no;
        $doc->notes = $so->notes;

        if ($so->customer) {
            $doc->party = [
                'party_type' => 'CUSTOMER',
                'name' => $so->customer->name,
                'gstin' => $so->customer->gstin,
                'billing_address' => $so->billing_address,
                'shipping_address' => $so->shipping_address,
            ];
        }

        $items = [];
        foreach ($so->items as $idx => $it) {
            $items[] = [
                'sr_no' => $idx + 1,
                'name' => $it->item_name ?: 'Product Item',
                'hsn_sac' => $it->hsn_sac,
                'quantity' => $it->quantity,
                'unit' => $it->unit ?: 'Pcs',
                'unit_price' => floatval($it->unit_price),
                'taxable_amount' => floatval($it->taxable_value),
                'total_amount' => floatval($it->total_amount),
            ];
        }
        $doc->items = $items;
        $doc->totals = [
            'taxable_amount' => floatval($so->taxable_value),
            'grand_total' => floatval($so->grand_total),
        ];
    }

    private static function populateDeliveryChallan(DocumentModel $doc, string $id, int $companyId): void
    {
        $dc = DeliveryChallan::with(['items', 'customer'])->where('company_id', $companyId)->findOrFail($id);
        $doc->documentNumber = $dc->challan_number;
        $doc->documentDate = static::formatDate($dc->challan_date) ?: date('Y-m-d');
        $doc->referenceNumber = $dc->reference_no;
        $doc->notes = $dc->notes;

        if ($dc->customer) {
            $doc->party = [
                'party_type' => 'CUSTOMER',
                'name' => $dc->customer->name,
                'gstin' => $dc->customer->gstin,
                'billing_address' => $dc->shipping_address,
                'shipping_address' => $dc->shipping_address,
            ];
        }

        $items = [];
        foreach ($dc->items as $idx => $it) {
            $items[] = [
                'sr_no' => $idx + 1,
                'name' => $it->item_name ?: 'Product Item',
                'quantity' => $it->quantity,
                'unit' => $it->unit ?: 'Pcs',
                'unit_price' => 0.0,
                'total_amount' => 0.0,
            ];
        }
        $doc->items = $items;
        $doc->totals = ['grand_total' => 0.0];
    }

    private static function populateCreditNote(DocumentModel $doc, string $id, int $companyId): void
    {
        $cn = CreditNote::with(['items', 'customer'])->where('company_id', $companyId)->findOrFail($id);
        $doc->documentNumber = $cn->credit_note_number;
        $doc->documentDate = static::formatDate($cn->credit_note_date) ?: date('Y-m-d');
        $doc->referenceNumber = "Orig Inv: {$cn->invoice_number}";
        $doc->notes = "Reason: {$cn->reason}";

        if ($cn->customer) {
            $doc->party = [
                'party_type' => 'CUSTOMER',
                'name' => $cn->customer->name,
                'gstin' => $cn->customer->gstin,
                'billing_address' => $cn->customer->address_line1,
            ];
        }

        $items = [];
        foreach ($cn->items as $idx => $it) {
            $items[] = [
                'sr_no' => $idx + 1,
                'name' => $it->item_name ?: 'Returned Item',
                'quantity' => $it->quantity,
                'unit_price' => floatval($it->unit_price),
                'taxable_amount' => floatval($it->taxable_value),
                'total_amount' => floatval($it->total_amount),
            ];
        }
        $doc->items = $items;
        $doc->totals = [
            'taxable_amount' => floatval($cn->taxable_value),
            'cgst_amount' => floatval($cn->cgst_amount),
            'sgst_amount' => floatval($cn->sgst_amount),
            'igst_amount' => floatval($cn->igst_amount),
            'grand_total' => floatval($cn->grand_total),
        ];
    }

    private static function populatePurchaseInvoice(DocumentModel $doc, string $id, int $companyId): void
    {
        $pur = Purchase::with(['items', 'supplier'])->where('company_id', $companyId)->findOrFail($id);
        $doc->documentNumber = $pur->purchase_number;
        $doc->documentDate = static::formatDate($pur->purchase_date) ?: date('Y-m-d');
        $doc->dueDate = static::formatDate($pur->due_date);
        $doc->referenceNumber = $pur->vendor_invoice_number;
        $doc->notes = $pur->notes;

        if ($pur->supplier) {
            $doc->party = [
                'party_type' => 'SUPPLIER',
                'name' => $pur->supplier->name,
                'gstin' => $pur->supplier->gstin,
                'pan' => $pur->supplier->pan,
                'billing_address' => $pur->supplier->address_line1,
                'state' => $pur->supplier->state,
                'phone' => $pur->supplier->phone,
            ];
        }

        $items = [];
        foreach ($pur->items as $idx => $it) {
            $items[] = [
                'sr_no' => $idx + 1,
                'name' => $it->item_name ?: 'Item',
                'hsn_sac' => $it->hsn_sac,
                'quantity' => $it->quantity,
                'unit' => $it->unit ?: 'Pcs',
                'unit_price' => floatval($it->unit_price),
                'taxable_amount' => floatval($it->taxable_value),
                'total_amount' => floatval($it->total_amount),
            ];
        }
        $doc->items = $items;
        $doc->totals = [
            'taxable_amount' => floatval($pur->taxable_value),
            'cgst_amount' => floatval($pur->cgst_amount),
            'sgst_amount' => floatval($pur->sgst_amount),
            'igst_amount' => floatval($pur->igst_amount),
            'grand_total' => floatval($pur->grand_total),
        ];
    }

    private static function populatePurchaseOrder(DocumentModel $doc, string $id, int $companyId): void
    {
        $po = PurchaseOrder::with(['items', 'supplier'])->where('company_id', $companyId)->findOrFail($id);
        $doc->documentNumber = $po->po_number;
        $doc->documentDate = static::formatDate($po->po_date) ?: date('Y-m-d');
        $doc->notes = $po->notes;

        if ($po->supplier) {
            $doc->party = [
                'party_type' => 'SUPPLIER',
                'name' => $po->supplier->name,
                'gstin' => $po->supplier->gstin,
                'billing_address' => $po->supplier->address_line1,
            ];
        }

        $items = [];
        foreach ($po->items as $idx => $it) {
            $items[] = [
                'sr_no' => $idx + 1,
                'name' => $it->item_name ?: 'Item',
                'quantity' => $it->quantity,
                'unit_price' => floatval($it->unit_price),
                'taxable_amount' => floatval($it->taxable_value),
                'total_amount' => floatval($it->total_amount),
            ];
        }
        $doc->items = $items;
        $doc->totals = ['taxable_amount' => floatval($po->taxable_value), 'grand_total' => floatval($po->grand_total)];
    }

    private static function populateDebitNote(DocumentModel $doc, string $id, int $companyId): void
    {
        $dn = DebitNote::with(['items', 'supplier'])->where('company_id', $companyId)->findOrFail($id);
        $doc->documentNumber = $dn->debit_note_number;
        $doc->documentDate = static::formatDate($dn->debit_note_date) ?: date('Y-m-d');
        $doc->referenceNumber = "Vendor Inv: {$dn->vendor_invoice_number}";
        $doc->notes = "Reason: {$dn->reason}";

        if ($dn->supplier) {
            $doc->party = [
                'party_type' => 'SUPPLIER',
                'name' => $dn->supplier->name,
                'gstin' => $dn->supplier->gstin,
                'billing_address' => $dn->supplier->address_line1,
            ];
        }

        $items = [];
        foreach ($dn->items as $idx => $it) {
            $items[] = [
                'sr_no' => $idx + 1,
                'name' => $it->item_name ?: 'Returned Item',
                'quantity' => $it->quantity,
                'unit_price' => floatval($it->unit_price),
                'taxable_amount' => floatval($it->taxable_value),
                'total_amount' => floatval($it->total_amount),
            ];
        }
        $doc->items = $items;
        $doc->totals = [
            'taxable_amount' => floatval($dn->taxable_value),
            'cgst_amount' => floatval($dn->cgst_amount),
            'sgst_amount' => floatval($dn->sgst_amount),
            'igst_amount' => floatval($dn->igst_amount),
            'grand_total' => floatval($dn->grand_total),
        ];
    }

    private static function populatePaymentReceipt(DocumentModel $doc, string $id, int $companyId): void
    {
        $pay = Payment::with(['customer', 'allocations'])->where('company_id', $companyId)->findOrFail($id);
        $doc->documentNumber = $pay->payment_number;
        $doc->documentDate = static::formatDate($pay->payment_date) ?: date('Y-m-d');
        $doc->referenceNumber = $pay->reference_number;
        $doc->notes = "Payment Mode: {$pay->payment_mode} | Allocated: ₹{$pay->allocated_amount}";

        if ($pay->customer) {
            $doc->party = [
                'party_type' => 'CUSTOMER',
                'name' => $pay->customer->name,
                'gstin' => $pay->customer->gstin,
                'billing_address' => $pay->customer->address_line1,
            ];
        }

        $items = [];
        foreach ($pay->allocations as $idx => $al) {
            $invNum = $al->invoice?->invoice_number ?: ($al->document_id ? "Invoice #{$al->document_id}" : 'Settlement');
            $amt = floatval($al->allocated_amount ?: ($al->amount ?? 0));
            $items[] = [
                'sr_no' => $idx + 1,
                'name' => "Settled: {$invNum}",
                'quantity' => 1,
                'unit_price' => $amt,
                'taxable_amount' => $amt,
                'total_amount' => $amt,
            ];
        }
        $doc->items = $items;
        $doc->totals = [
            'taxable_amount' => floatval($pay->amount),
            'grand_total' => floatval($pay->amount),
        ];
    }

    private static function populateCustomerStatement(DocumentModel $doc, string $customerId, int $companyId): void
    {
        $cust = Customer::where('company_id', $companyId)->findOrFail($customerId);
        $doc->documentNumber = "STMT-CUST-{$cust->id}-" . date('Ymd');
        $doc->documentDate = date('Y-m-d');

        $doc->party = [
            'party_type' => 'CUSTOMER',
            'name' => $cust->name,
            'gstin' => $cust->gstin,
            'billing_address' => $cust->address_line1,
            'state' => $cust->state,
            'phone' => $cust->phone,
        ];

        $account = \App\Models\ChartOfAccount::where('company_id', $companyId)
            ->where('code', '1200') // Accounts Receivable
            ->first();

        $ledgerData = $account ? LedgerService::getAccountLedger($companyId, $account->id) : ['transactions' => [], 'opening_balance' => 0, 'closing_balance' => $cust->current_balance];

        $doc->statementData = [
            'period' => 'Current Financial Year',
            'opening_balance' => floatval($ledgerData['opening_balance'] ?? 0),
            'closing_balance' => floatval($ledgerData['closing_balance'] ?? $cust->current_balance),
            'entries' => $ledgerData['transactions'] ?? [],
        ];

        $doc->totals = [
            'grand_total' => floatval($ledgerData['closing_balance'] ?? $cust->current_balance),
        ];
    }

    private static function populateSupplierStatement(DocumentModel $doc, string $supplierId, int $companyId): void
    {
        $sup = Supplier::where('company_id', $companyId)->findOrFail($supplierId);
        $doc->documentNumber = "STMT-SUP-{$sup->id}-" . date('Ymd');
        $doc->documentDate = date('Y-m-d');

        $doc->party = [
            'party_type' => 'SUPPLIER',
            'name' => $sup->name,
            'gstin' => $sup->gstin,
            'billing_address' => $sup->address_line1,
            'phone' => $sup->phone,
        ];

        $account = \App\Models\ChartOfAccount::where('company_id', $companyId)
            ->where('code', '2000') // Accounts Payable
            ->first();

        $ledgerData = $account ? LedgerService::getAccountLedger($companyId, $account->id) : ['transactions' => [], 'opening_balance' => 0, 'closing_balance' => $sup->current_balance];

        $doc->statementData = [
            'period' => 'Current Financial Year',
            'opening_balance' => floatval($ledgerData['opening_balance'] ?? 0),
            'closing_balance' => floatval($ledgerData['closing_balance'] ?? $sup->current_balance),
            'entries' => $ledgerData['transactions'] ?? [],
        ];

        $doc->totals = [
            'grand_total' => floatval($ledgerData['closing_balance'] ?? $sup->current_balance),
        ];
    }

    private static function populateCashBook(DocumentModel $doc, int $companyId): void
    {
        $cashBook = BankingReportService::getCashBook($companyId);
        $doc->documentNumber = "CASH-BOOK-" . date('Ymd');
        $doc->documentDate = date('Y-m-d');

        $doc->statementData = [
            'period' => 'Financial Year 2026-27',
            'opening_balance' => floatval($cashBook['opening_balance'] ?? 0),
            'closing_balance' => floatval($cashBook['closing_balance'] ?? 0),
            'entries' => $cashBook['transactions'] ?? [],
        ];
        $doc->totals = ['grand_total' => floatval($cashBook['closing_balance'] ?? 0)];
    }

    private static function populateBankBook(DocumentModel $doc, string $bankAccountId, int $companyId): void
    {
        $bankBook = BankingReportService::getBankBook($companyId, (int)$bankAccountId);
        $doc->documentNumber = "BANK-BOOK-{$bankAccountId}-" . date('Ymd');
        $doc->documentDate = date('Y-m-d');

        $doc->statementData = [
            'period' => 'Financial Year 2026-27',
            'opening_balance' => floatval($bankBook['opening_balance'] ?? 0),
            'closing_balance' => floatval($bankBook['closing_balance'] ?? 0),
            'entries' => $bankBook['transactions'] ?? [],
        ];
        $doc->totals = ['grand_total' => floatval($bankBook['closing_balance'] ?? 0)];
    }

    private static function populateChequeRegister(DocumentModel $doc, int $companyId): void
    {
        $reg = BankingReportService::getChequeRegister($companyId);
        $doc->documentNumber = "CHEQUE-REG-" . date('Ymd');
        $doc->documentDate = date('Y-m-d');

        $entries = [];
        foreach (array_merge($reg['received_cheques'] ?? [], $reg['issued_cheques'] ?? []) as $ch) {
            $entries[] = [
                'date' => $ch['cheque_date'],
                'journal_number' => "CHQ #{$ch['cheque_number']}",
                'description' => "Party: {$ch['party_name']} | Status: {$ch['status']}",
                'debit' => $ch['type'] === 'RECEIVED' ? $ch['amount'] : 0,
                'credit' => $ch['type'] === 'ISSUED' ? $ch['amount'] : 0,
                'balance' => $ch['amount'],
            ];
        }

        $doc->statementData = [
            'period' => 'Cheque Register',
            'opening_balance' => 0,
            'closing_balance' => 0,
            'entries' => $entries,
        ];
        $doc->totals = ['grand_total' => 0];
    }

    private static function populateBankReconciliation(DocumentModel $doc, string $recId, int $companyId): void
    {
        $rec = BankReconciliation::with('bankAccount')->where('company_id', $companyId)->findOrFail($recId);
        $doc->documentNumber = "BRS-{$rec->id}-" . date('Ymd');
        $doc->documentDate = date('Y-m-d');

        $doc->reconciliationData = [
            'bank_account_name' => $rec->bankAccount?->account_name ?: 'Bank Account',
            'statement_period' => "{$rec->statement_start_date} to {$rec->statement_end_date}",
            'statement_closing_balance' => floatval($rec->statement_closing_balance),
            'uncleared_deposits' => floatval($rec->uncleared_deposits),
            'unpresented_cheques' => floatval($rec->unpresented_cheques),
            'reconciled_balance' => floatval($rec->reconciled_balance),
            'book_closing_balance' => floatval($rec->book_closing_balance),
            'difference_amount' => floatval($rec->difference_amount),
            'status' => $rec->status,
        ];
        $doc->totals = ['grand_total' => floatval($rec->reconciled_balance)];
    }
}
