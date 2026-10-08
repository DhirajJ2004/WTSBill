<?php

namespace App\Http\Controllers\Api;

use App\Models\Invoice;
use App\Models\GSTConfiguration;
use App\Models\GSTRate;
use App\Models\HsnSacMaster;
use App\Models\EInvoice;
use App\Models\EWayBill;
use App\Models\GSTFilingPeriod;
use App\Models\GSTDocumentSnapshot;
use App\Http\Middleware\AuthMiddleware;
use App\Services\GSTConfigurationService;
use App\Services\GSTINValidationService;
use App\Services\PlaceOfSupplyService;
use App\Services\TaxCalculationService;
use App\Services\GSTReportingService;
use App\Services\GSTReconciliationService;
use App\Services\GSTLedgerService;
use App\Services\EInvoiceService;
use App\Services\EWayBillService;
use App\Services\AuditLogService;

class GSTController
{
    /**
     * Get GST Configuration for the business
     */
    public function getConfiguration()
    {
        $user = AuthMiddleware::authorize('gst', 'view');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();

        $config = GSTConfigurationService::getOrCreateConfiguration($companyId, $branchId);
        return response_json(['status' => 'success', 'data' => $config]);
    }

    /**
     * Update business GST Configuration
     */
    public function updateConfiguration()
    {
        $user = AuthMiddleware::authorize('gst', 'settings');
        $companyId = $user->current_company_id;
        $branchId = AuthMiddleware::getBranchId();
        $input = get_json_input();

        $config = GSTConfigurationService::getOrCreateConfiguration($companyId, $branchId);

        $config->update([
            'gst_registered' => isset($input['gst_registered']) ? (bool)$input['gst_registered'] : $config->gst_registered,
            'gstin' => isset($input['gstin']) ? strtoupper(trim($input['gstin'])) : $config->gstin,
            'legal_business_name' => $input['legal_business_name'] ?? $config->legal_business_name,
            'trade_name' => $input['trade_name'] ?? $config->trade_name,
            'pan' => $input['pan'] ?? $config->pan,
            'registered_state' => $input['registered_state'] ?? $config->registered_state,
            'state_code' => isset($input['state_code']) ? PlaceOfSupplyService::normalizeStateCode($input['state_code']) : $config->state_code,
            'tax_registration_type' => $input['tax_registration_type'] ?? $config->tax_registration_type,
            'is_composition' => isset($input['is_composition']) ? (bool)$input['is_composition'] : $config->is_composition,
            'default_place_of_supply' => $input['default_place_of_supply'] ?? $config->default_place_of_supply,
            'einvoice_applicable' => isset($input['einvoice_applicable']) ? (bool)$input['einvoice_applicable'] : $config->einvoice_applicable,
            'einvoice_threshold' => isset($input['einvoice_threshold']) ? floatval($input['einvoice_threshold']) : $config->einvoice_threshold,
            'eway_bill_applicable' => isset($input['eway_bill_applicable']) ? (bool)$input['eway_bill_applicable'] : $config->eway_bill_applicable,
            'eway_threshold' => isset($input['eway_threshold']) ? floatval($input['eway_threshold']) : $config->eway_threshold,
            'filing_frequency' => $input['filing_frequency'] ?? $config->filing_frequency,
            'api_environment' => $input['api_environment'] ?? $config->api_environment,
        ]);

        AuditLogService::log(
            $companyId,
            $user->name,
            'GST_CONFIG_UPDATE',
            'GSTConfiguration',
            $config->id,
            "Updated GST Configuration for company #{$companyId}"
        );

        return response_json(['status' => 'success', 'data' => $config]);
    }

    /**
     * Validate GSTIN format & verify state
     */
    public function validateGSTIN()
    {
        $user = AuthMiddleware::authorize('gst', 'view');
        $input = get_json_input();
        $gstin = trim($input['gstin'] ?? '');
        $forceLive = !empty($input['force_live']);

        if (empty($gstin)) {
            return response_json(['status' => 'error', 'message' => 'GSTIN string is required.'], 422);
        }

        $res = GSTINValidationService::validateGSTIN($gstin, $user->current_company_id, $forceLive);
        return response_json(['status' => 'success', 'data' => $res]);
    }

    /**
     * Central State Master
     */
    public function getStateMaster()
    {
        AuthMiddleware::authorize('gst', 'view');
        $map = PlaceOfSupplyService::getStateCodeMap();
        $list = [];
        foreach ($map as $code => $name) {
            $list[] = ['code' => $code, 'name' => $name, 'label' => "{$code} - {$name}"];
        }
        return response_json(['status' => 'success', 'data' => $list]);
    }

    /**
     * Get GST Rates Master
     */
    public function getRates()
    {
        $user = AuthMiddleware::authorize('gst', 'view');
        $rates = GSTConfigurationService::ensureDefaultGSTRates($user->current_company_id);
        return response_json(['status' => 'success', 'data' => $rates]);
    }

    /**
     * Add Custom GST Rate
     */
    public function createRate()
    {
        $user = AuthMiddleware::authorize('gst', 'settings');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $rateVal = floatval($input['rate'] ?? 0);
        $cessVal = floatval($input['cess_rate'] ?? 0);

        $rate = GSTRate::create([
            'company_id' => $companyId,
            'rate' => $rateVal,
            'cess_rate' => $cessVal,
            'description' => $input['description'] ?? "Custom Rate {$rateVal}%",
            'is_active' => true,
            'is_system_default' => false,
        ]);

        AuditLogService::log($companyId, $user->name, 'GST_RATE_CREATE', 'GSTRate', $rate->id, "Created GST Rate {$rateVal}% (Cess {$cessVal}%)");

        return response_json(['status' => 'success', 'data' => $rate], 201);
    }

    /**
     * Update Tax Rate
     */
    public function updateRate($id)
    {
        $user = AuthMiddleware::authorize('gst', 'settings');
        $companyId = $user->current_company_id;
        $rate = GSTRate::where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        })->findOrFail(intval($id));

        $input = get_json_input();
        if (isset($input['description'])) $rate->description = $input['description'];
        if (isset($input['is_active'])) $rate->is_active = (bool)$input['is_active'];
        if (isset($input['effective_from'])) $rate->effective_from = $input['effective_from'];
        if (isset($input['effective_to'])) $rate->effective_to = $input['effective_to'];
        $rate->save();

        AuditLogService::log($companyId, $user->name, 'GST_RATE_UPDATE', 'GSTRate', $rate->id, "Updated GST Rate #{$id}");
        return response_json(['status' => 'success', 'data' => $rate]);
    }

    /**
     * Deactivate or Delete Tax Rate
     */
    public function deleteRate($id)
    {
        $user = AuthMiddleware::authorize('gst', 'settings');
        $companyId = $user->current_company_id;
        $rate = GSTRate::where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        })->findOrFail(intval($id));

        // Strict rule: Do not hard-delete if used in snapshots
        $rate->is_active = false;
        $rate->save();

        AuditLogService::log($companyId, $user->name, 'GST_RATE_DEACTIVATE', 'GSTRate', $rate->id, "Deactivated GST Rate #{$id}");
        return response_json(['status' => 'success', 'message' => 'Tax rate deactivated successfully.']);
    }

    /**
     * Get HSN/SAC Master
     */
    public function getHsnSac()
    {
        $user = AuthMiddleware::authorize('gst', 'view');
        $items = GSTConfigurationService::ensureDefaultHsnSac($user->current_company_id);
        return response_json(['status' => 'success', 'data' => $items]);
    }

    /**
     * Create HSN/SAC Master Item
     */
    public function createHsnSac()
    {
        $user = AuthMiddleware::authorize('gst', 'settings');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $code = trim($input['code'] ?? '');
        if (empty($code)) {
            return response_json(['status' => 'error', 'message' => 'HSN/SAC code is required.'], 422);
        }

        $master = HsnSacMaster::create([
            'company_id' => $companyId,
            'code' => $code,
            'type' => strtoupper($input['type'] ?? 'HSN'),
            'description' => $input['description'] ?? '',
            'default_gst_rate' => floatval($input['default_gst_rate'] ?? 18.0),
            'default_cess_rate' => floatval($input['default_cess_rate'] ?? 0.0),
            'is_active' => true,
        ]);

        AuditLogService::log($companyId, $user->name, 'HSN_SAC_CREATE', 'HsnSacMaster', $master->id, "Created HSN/SAC {$code}");
        return response_json(['status' => 'success', 'data' => $master], 201);
    }

    /**
     * Update HSN/SAC Master Item
     */
    public function updateHsnSac($id)
    {
        $user = AuthMiddleware::authorize('gst', 'settings');
        $companyId = $user->current_company_id;
        $master = HsnSacMaster::where(function ($q) use ($companyId) {
            $q->where('company_id', $companyId)->orWhereNull('company_id');
        })->findOrFail(intval($id));

        $input = get_json_input();
        if (isset($input['description'])) $master->description = $input['description'];
        if (isset($input['default_gst_rate'])) $master->default_gst_rate = floatval($input['default_gst_rate']);
        if (isset($input['default_cess_rate'])) $master->default_cess_rate = floatval($input['default_cess_rate']);
        if (isset($input['is_active'])) $master->is_active = (bool)$input['is_active'];
        $master->save();

        AuditLogService::log($companyId, $user->name, 'HSN_SAC_UPDATE', 'HsnSacMaster', $master->id, "Updated HSN/SAC #{$id}");
        return response_json(['status' => 'success', 'data' => $master]);
    }

    /**
     * Calculate Taxes on arbitrary payload
     */
    public function calculateTax()
    {
        $user = AuthMiddleware::authorize('gst', 'view');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $config = GSTConfigurationService::getOrCreateConfiguration($companyId);
        $sellerState = $input['seller_state_code'] ?? $config->state_code;
        $pos = $input['place_of_supply'] ?? $sellerState;
        $items = $input['items'] ?? [];
        $isRcm = !empty($input['is_reverse_charge']);
        $discount = floatval($input['document_discount'] ?? 0);

        $calc = TaxCalculationService::calculateDocumentTax($items, $sellerState, $pos, $isRcm, false, false, $discount);
        return response_json(['status' => 'success', 'data' => $calc]);
    }

    /**
     * GST Dashboard Overview Summary
     */
    public function getDashboardSummary()
    {
        $user = AuthMiddleware::authorize('gst', 'view');
        $companyId = $user->current_company_id;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;
        $branchId = AuthMiddleware::getBranchId();

        $data = GSTReportingService::getDashboardSummary($companyId, $fromDate, $toDate, $branchId);
        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * GST Transaction Register / Ledger
     */
    public function getRegister()
    {
        $user = AuthMiddleware::authorize('gst', 'view');
        $companyId = $user->current_company_id;
        $filters = [
            'from_date' => $_GET['from_date'] ?? null,
            'to_date' => $_GET['to_date'] ?? null,
            'branch_id' => AuthMiddleware::getBranchId(),
            'document_type' => $_GET['document_type'] ?? 'ALL',
            'gst_category' => $_GET['gst_category'] ?? 'ALL',
            'supply_type' => $_GET['supply_type'] ?? 'ALL',
            'search' => $_GET['search'] ?? null,
        ];

        $data = GSTLedgerService::getRegister($companyId, $filters);
        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * GSTR-1 Data Preparation
     */
    public function getGSTR1()
    {
        $user = AuthMiddleware::authorize('gst', 'reports');
        $companyId = $user->current_company_id;
        $fy = $_GET['financial_year'] ?? AuthMiddleware::getFinancialYear();
        $period = $_GET['period'] ?? 'Current';

        $data = GSTReportingService::getGSTR1Data($companyId, $fy, $period);
        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * GSTR-3B Data Preparation
     */
    public function getGSTR3B()
    {
        $user = AuthMiddleware::authorize('gst', 'reports');
        $companyId = $user->current_company_id;
        $fy = $_GET['financial_year'] ?? AuthMiddleware::getFinancialYear();
        $period = $_GET['period'] ?? 'Current';

        $data = GSTReportingService::getGSTR3BData($companyId, $fy, $period);
        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * HSN/SAC Summary Report
     */
    public function getHsnSummary()
    {
        $user = AuthMiddleware::authorize('gst', 'reports');
        $companyId = $user->current_company_id;
        $gstr1 = GSTReportingService::getGSTR1Data($companyId);
        return response_json(['status' => 'success', 'data' => $gstr1['hsn_summary'] ?? []]);
    }

    /**
     * Rate-wise Tax Analysis
     */
    public function getRateAnalysis()
    {
        $user = AuthMiddleware::authorize('gst', 'reports');
        $companyId = $user->current_company_id;
        $fromDate = $_GET['from_date'] ?? null;
        $toDate = $_GET['to_date'] ?? null;

        $data = GSTReportingService::getTaxRateAnalysis($companyId, $fromDate, $toDate);
        return response_json(['status' => 'success', 'data' => $data]);
    }

    /**
     * State-wise GST Report
     */
    public function getStateWiseSummary()
    {
        $user = AuthMiddleware::authorize('gst', 'reports');
        $companyId = $user->current_company_id;
        $fromDate = $_GET['from_date'] ?? date('Y-01-01');
        $toDate = $_GET['to_date'] ?? date('Y-m-d');

        $snapshots = GSTDocumentSnapshot::where('company_id', $companyId)
            ->where('document_type', 'INVOICE')
            ->whereBetween('document_date', [$fromDate, $toDate])
            ->get();

        $stateSummary = [];
        foreach ($snapshots as $snap) {
            $pos = $snap->place_of_supply;
            $posName = PlaceOfSupplyService::getStateName($pos);
            $key = "{$pos}_{$posName}";

            if (!isset($stateSummary[$key])) {
                $stateSummary[$key] = [
                    'state_code' => $pos,
                    'state_name' => $posName,
                    'invoice_count' => 0,
                    'taxable_amount' => 0.0,
                    'cgst_amount' => 0.0,
                    'sgst_amount' => 0.0,
                    'igst_amount' => 0.0,
                    'cess_amount' => 0.0,
                    'total_tax' => 0.0,
                    'grand_total' => 0.0,
                ];
            }

            $stateSummary[$key]['invoice_count']++;
            $stateSummary[$key]['taxable_amount'] += floatval($snap->taxable_amount);
            $stateSummary[$key]['cgst_amount'] += floatval($snap->cgst_amount);
            $stateSummary[$key]['sgst_amount'] += floatval($snap->sgst_amount);
            $stateSummary[$key]['igst_amount'] += floatval($snap->igst_amount);
            $stateSummary[$key]['cess_amount'] += floatval($snap->cess_amount);
            $stateSummary[$key]['total_tax'] += floatval($snap->total_tax_amount);
            $stateSummary[$key]['grand_total'] += floatval($snap->total_document_value);
        }

        return response_json(['status' => 'success', 'data' => array_values($stateSummary)]);
    }

    /**
     * Get Period Locks
     */
    public function getPeriodLocks()
    {
        $user = AuthMiddleware::authorize('gst', 'view');
        $companyId = $user->current_company_id;
        $periods = GSTFilingPeriod::where('company_id', $companyId)->orderBy('id', 'desc')->get();
        return response_json(['status' => 'success', 'data' => $periods]);
    }

    /**
     * Lock GST Filing Period
     */
    public function lockPeriod()
    {
        $user = AuthMiddleware::authorize('gst', 'settings');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $fy = $input['financial_year'] ?? '2026-27';
        $period = $input['period_name'] ?? 'M' . date('m');
        $returnType = $input['return_type'] ?? 'GSTR1';

        $periodObj = GSTLedgerService::lockPeriod($companyId, $fy, $period, $returnType, $user->name);
        return response_json(['status' => 'success', 'message' => 'GST period locked successfully.', 'data' => $periodObj]);
    }

    /**
     * Reopen GST Filing Period
     */
    public function reopenPeriod($id)
    {
        $user = AuthMiddleware::authorize('gst', 'settings');
        $companyId = $user->current_company_id;
        $input = get_json_input();
        $reason = trim($input['reason'] ?? '');

        try {
            $periodObj = GSTLedgerService::reopenPeriod($companyId, intval($id), $reason, $user->name);
            return response_json(['status' => 'success', 'message' => 'GST period reopened.', 'data' => $periodObj]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * GST Reconciliation List / Run
     */
    public function runReconciliation()
    {
        $user = AuthMiddleware::authorize('gst', 'reconcile');
        $companyId = $user->current_company_id;
        $input = get_json_input();

        $fy = $input['financial_year'] ?? '2026-27';
        $month = $input['period_month'] ?? date('m');
        $portalRecords = $input['portal_records'] ?? [];
        $returnType = $input['return_type'] ?? 'GSTR2B';

        $recon = GSTReconciliationService::createReconciliation($companyId, $fy, $month, $portalRecords, $returnType, $user->name);
        return response_json(['status' => 'success', 'data' => $recon], 201);
    }

    /**
     * Take action on a reconciliation item
     */
    public function actionReconciliationItem($id)
    {
        $user = AuthMiddleware::authorize('gst', 'reconcile');
        $input = get_json_input();
        $action = $input['action'] ?? 'REVIEWED';
        $notes = $input['notes'] ?? null;

        $item = GSTReconciliationService::recordItemAction(intval($id), $action, $notes, $user->name);
        return response_json(['status' => 'success', 'data' => $item]);
    }

    /**
     * Check e-Invoice eligibility
     */
    public function checkEInvoiceEligibility()
    {
        $user = AuthMiddleware::authorize('einvoice', 'view');
        $input = get_json_input();
        $invoiceId = intval($input['invoice_id'] ?? 0);

        $invoice = Invoice::where('company_id', $user->current_company_id)->findOrFail($invoiceId);
        $res = EInvoiceService::checkEligibility($invoice);
        return response_json(['status' => 'success', 'data' => $res]);
    }

    /**
     * Generate e-Invoice IRN
     */
    public function generateEInvoice()
    {
        $user = AuthMiddleware::authorize('einvoice', 'generate');
        $input = get_json_input();
        $invoiceId = intval($input['invoice_id'] ?? 0);

        $invoice = Invoice::where('company_id', $user->current_company_id)->findOrFail($invoiceId);

        try {
            $eInvoice = EInvoiceService::generateEInvoice($invoice, $user->name);
            return response_json([
                'status' => 'success',
                'message' => 'e-Invoice IRN generated successfully.',
                'data' => $eInvoice,
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel e-Invoice IRN
     */
    public function cancelEInvoice($id)
    {
        $user = AuthMiddleware::authorize('einvoice', 'cancel');
        $input = get_json_input();
        $reason = $input['reason'] ?? '2';
        $remarks = $input['remarks'] ?? 'Cancelled by authorized user';

        try {
            $eInvoice = EInvoiceService::cancelEInvoice(intval($id), $reason, $remarks, $user->name);
            return response_json([
                'status' => 'success',
                'message' => 'e-Invoice IRN cancelled successfully.',
                'data' => $eInvoice,
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Check E-Way Bill eligibility
     */
    public function checkEWayBillEligibility()
    {
        $user = AuthMiddleware::authorize('ewaybill', 'view');
        $input = get_json_input();
        $invoiceId = intval($input['invoice_id'] ?? 0);

        $invoice = Invoice::where('company_id', $user->current_company_id)->findOrFail($invoiceId);
        $res = EWayBillService::checkEligibility($invoice);
        return response_json(['status' => 'success', 'data' => $res]);
    }

    /**
     * Generate E-Way Bill
     */
    public function generateEWayBill()
    {
        $user = AuthMiddleware::authorize('ewaybill', 'generate');
        $input = get_json_input();
        $invoiceId = intval($input['invoice_id'] ?? 0);
        $transportData = $input['transport_details'] ?? [];

        $invoice = Invoice::where('company_id', $user->current_company_id)->findOrFail($invoiceId);

        try {
            $ewb = EWayBillService::generateEWayBill($invoice, $transportData, $user->name);
            return response_json([
                'status' => 'success',
                'message' => 'E-Way Bill generated successfully.',
                'data' => $ewb,
            ], 201);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel E-Way Bill
     */
    public function cancelEWayBill($id)
    {
        $user = AuthMiddleware::authorize('ewaybill', 'cancel');
        $input = get_json_input();
        $reason = $input['reason'] ?? '2';
        $remarks = $input['remarks'] ?? 'Cancelled by authorized user';

        try {
            $ewb = EWayBillService::cancelEWayBill(intval($id), $reason, $remarks, $user->name);
            return response_json([
                'status' => 'success',
                'message' => 'E-Way Bill cancelled successfully.',
                'data' => $ewb,
            ]);
        } catch (\Exception $e) {
            return response_json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }
}
