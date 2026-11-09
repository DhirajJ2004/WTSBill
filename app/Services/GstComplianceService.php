<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;

/**
 * GST Compliance & Tax Reporting Service.
 * Manages GSTR-3B monthly summary preparation, GSTR-1 payload structuring,
 * GST Sales Register, and GST Inward Purchase Register.
 */
class GstComplianceService
{
    /**
     * Compute GSTR-3B Monthly Return Summary (Table 3.1 Outward & Table 4 Eligible ITC).
     */
    public function getGstr3bSummary(int $companyId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? (string)$filters['from_date'] : date('Y-m-01');
        $toDate = !empty($filters['to_date']) ? (string)$filters['to_date'] : date('Y-m-d');
        $branchId = (int)($filters['branch_id'] ?? 0);

        $branchSql = "";
        $params = [$companyId, $fromDate, $toDate];
        if ($branchId > 0) {
            $branchSql = " AND (branch_id = ? OR branch_id IS NULL)";
            $params[] = $branchId;
        }

        // Table 3.1(a) Outward Taxable Supplies (From active invoices)
        $outward = Database::selectOne(
            "SELECT 
                COALESCE(COUNT(id), 0) AS invoice_count,
                COALESCE(SUM(sub_total), 0) AS taxable_value,
                COALESCE(SUM(cgst_amount), 0) AS cgst_amount,
                COALESCE(SUM(sgst_amount), 0) AS sgst_amount,
                COALESCE(SUM(igst_amount), 0) AS igst_amount,
                COALESCE(SUM(total_tax), 0) AS total_tax,
                COALESCE(SUM(grand_total), 0) AS total_value
             FROM invoices 
             WHERE company_id = ? 
               AND invoice_date BETWEEN ? AND ? 
               AND deleted_at IS NULL 
               {$branchSql}",
            $params
        );

        $taxableVal = (float)($outward['taxable_value'] ?? 0);
        $outCgst = (float)($outward['cgst_amount'] ?? 0);
        $outSgst = (float)($outward['sgst_amount'] ?? 0);
        $outIgst = (float)($outward['igst_amount'] ?? 0);
        $rawOutTax = (float)($outward['total_tax'] ?? 0);

        // Reconcile any unallocated total_tax (e.g. from imported or test invoices without explicit split) as intra-state standard (50% CGST, 50% SGST)
        $compTax = round($outCgst + $outSgst + $outIgst, 2);
        if ($rawOutTax > $compTax) {
            $unallocated = $rawOutTax - $compTax;
            $half = round($unallocated / 2, 2);
            $outCgst += $half;
            $outSgst += ($unallocated - $half);
        }
        $outTax = round($outCgst + $outSgst + $outIgst, 2);

        // Table 4(A)(5) Eligible ITC from Inward Purchase Bills
        $itc = Database::selectOne(
            "SELECT 
                COALESCE(COUNT(id), 0) AS bill_count,
                COALESCE(SUM(sub_total), 0) AS taxable_value,
                COALESCE(SUM(cgst_amount), 0) AS cgst_amount,
                COALESCE(SUM(sgst_amount), 0) AS sgst_amount,
                COALESCE(SUM(igst_amount), 0) AS igst_amount,
                COALESCE(SUM(total_tax), 0) AS total_tax,
                COALESCE(SUM(grand_total), 0) AS total_value
             FROM purchases 
             WHERE company_id = ? 
               AND purchase_date BETWEEN ? AND ? 
               AND deleted_at IS NULL 
               {$branchSql}",
            $params
        );

        $itcTaxable = (float)($itc['taxable_value'] ?? 0);
        $itcCgst = (float)($itc['cgst_amount'] ?? 0);
        $itcSgst = (float)($itc['sgst_amount'] ?? 0);
        $itcIgst = (float)($itc['igst_amount'] ?? 0);
        $itcTotal = (float)($itc['total_tax'] ?? 0);

        // Table 4(B) ITC Reversals from Debit Notes
        $dnParams = [$companyId, $fromDate, $toDate];
        $dnBranchSql = "";
        if ($branchId > 0) {
            $dnBranchSql = " AND (branch_id = ? OR branch_id IS NULL)";
            $dnParams[] = $branchId;
        }

        $debitNotes = Database::selectOne(
            "SELECT 
                COALESCE(COUNT(id), 0) AS dn_count,
                COALESCE(SUM(cgst_reversal), 0) AS cgst_amount,
                COALESCE(SUM(sgst_reversal), 0) AS sgst_amount,
                COALESCE(SUM(igst_reversal), 0) AS igst_amount,
                COALESCE(SUM(total_tax), 0) AS total_tax
             FROM debit_notes 
             WHERE company_id = ? 
               AND debit_note_date BETWEEN ? AND ? 
               {$dnBranchSql}",
            $dnParams
        );

        $revCgst = (float)($debitNotes['cgst_amount'] ?? 0);
        $revSgst = (float)($debitNotes['sgst_amount'] ?? 0);
        $revIgst = (float)($debitNotes['igst_amount'] ?? 0);
        $revTotal = (float)($debitNotes['total_tax'] ?? 0);

        // Net ITC Available Table 4(C)
        $netItcCgst = round(max(0, $itcCgst - $revCgst), 2);
        $netItcSgst = round(max(0, $itcSgst - $revSgst), 2);
        $netItcIgst = round(max(0, $itcIgst - $revIgst), 2);
        $netItcTotal = round($netItcCgst + $netItcSgst + $netItcIgst, 2);

        // Table 6.1 Tax Liability Offset & Net Cash Payment
        $netPayableCgst = round(max(0, $outCgst - $netItcCgst), 2);
        $netPayableSgst = round(max(0, $outSgst - $netItcSgst), 2);
        $netPayableIgst = round(max(0, $outIgst - $netItcIgst), 2);
        $totalNetPayable = round($netPayableCgst + $netPayableSgst + $netPayableIgst, 2);

        // Excess ITC Carried Forward
        $excessItcCgst = round(max(0, $netItcCgst - $outCgst), 2);
        $excessItcSgst = round(max(0, $netItcSgst - $outSgst), 2);
        $excessItcIgst = round(max(0, $netItcIgst - $outIgst), 2);
        $totalExcessItc = round($excessItcCgst + $excessItcSgst + $excessItcIgst, 2);

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'return_period' => date('mY', strtotime($fromDate)),
            'table_3_1' => [
                'outward_taxable' => [
                    'label' => '3.1(a) Outward Taxable Supplies (other than zero/nil/exempt)',
                    'invoice_count' => (int)$outward['invoice_count'],
                    'taxable_value' => $taxableVal,
                    'cgst' => $outCgst,
                    'sgst' => $outSgst,
                    'igst' => $outIgst,
                    'total_tax' => $outTax,
                    'total_value' => (float)($outward['total_value'] ?? 0),
                ],
                'zero_rated' => [
                    'label' => '3.1(b) Outward Taxable Supplies (zero rated / exports)',
                    'taxable_value' => 0.00,
                    'igst' => 0.00,
                ],
                'nil_exempt' => [
                    'label' => '3.1(c) Other Outward Supplies (nil rated, exempted)',
                    'taxable_value' => 0.00,
                ],
                'rcm_inward' => [
                    'label' => '3.1(d) Inward Supplies Liable to Reverse Charge (RCM)',
                    'taxable_value' => 0.00,
                    'cgst' => 0.00,
                    'sgst' => 0.00,
                    'igst' => 0.00,
                ],
            ],
            'table_4' => [
                'itc_available' => [
                    'label' => '4(A)(5) All Other ITC (Inward Goods & Services)',
                    'bill_count' => (int)$itc['bill_count'],
                    'taxable_value' => $itcTaxable,
                    'cgst' => $itcCgst,
                    'sgst' => $itcSgst,
                    'igst' => $itcIgst,
                    'total_tax' => $itcTotal,
                ],
                'itc_reversed' => [
                    'label' => '4(B) ITC Reversed (Debit Notes & Statutory Reversals)',
                    'dn_count' => (int)$debitNotes['dn_count'],
                    'cgst' => $revCgst,
                    'sgst' => $revSgst,
                    'igst' => $revIgst,
                    'total_tax' => $revTotal,
                ],
                'net_itc' => [
                    'label' => '4(C) Net ITC Available (4(A) - 4(B))',
                    'cgst' => $netItcCgst,
                    'sgst' => $netItcSgst,
                    'igst' => $netItcIgst,
                    'total_tax' => $netItcTotal,
                ],
            ],
            'table_6_1' => [
                'net_tax_payable' => [
                    'cgst' => $netPayableCgst,
                    'sgst' => $netPayableSgst,
                    'igst' => $netPayableIgst,
                    'total' => $totalNetPayable,
                ],
                'excess_itc_cforward' => [
                    'cgst' => $excessItcCgst,
                    'sgst' => $excessItcSgst,
                    'igst' => $excessItcIgst,
                    'total' => $totalExcessItc,
                ],
            ],
        ];
    }

    /**
     * Structure GSTR-3B payload into official GST JSON schema.
     */
    public function exportGstr3bJson(int $companyId, array $filters = []): array
    {
        $summary = $this->getGstr3bSummary($companyId, $filters);
        $company = Database::selectOne("SELECT gstin, name FROM companies WHERE id = ?", [$companyId]);

        return [
            'gstin' => $company['gstin'] ?? '27AADCW7577N1ZE',
            'ret_period' => $summary['return_period'],
            'filing_typ' => 'GSTR3B',
            'sec_sum' => [
                'sec_nm' => '3.1',
                'txval' => $summary['table_3_1']['outward_taxable']['taxable_value'],
                'iamt' => $summary['table_3_1']['outward_taxable']['igst'],
                'camt' => $summary['table_3_1']['outward_taxable']['cgst'],
                'samt' => $summary['table_3_1']['outward_taxable']['sgst'],
                'csamt' => 0.00,
            ],
            'itc_elg' => [
                'itc_avl' => [
                    [
                        'ty' => 'OTH',
                        'iamt' => $summary['table_4']['net_itc']['igst'],
                        'camt' => $summary['table_4']['net_itc']['cgst'],
                        'samt' => $summary['table_4']['net_itc']['sgst'],
                        'csamt' => 0.00,
                    ]
                ],
            ],
            'tax_pmt' => [
                'tx_py' => [
                    'iamt' => $summary['table_6_1']['net_tax_payable']['igst'],
                    'camt' => $summary['table_6_1']['net_tax_payable']['cgst'],
                    'samt' => $summary['table_6_1']['net_tax_payable']['sgst'],
                    'csamt' => 0.00,
                ],
            ],
        ];
    }

    /**
     * Export GSTR-3B statement as CSV.
     */
    public function exportGstr3bCsv(int $companyId, array $filters = []): string
    {
        $sum = $this->getGstr3bSummary($companyId, $filters);
        $lines = [];

        $lines[] = '"FORM GSTR-3B RETURN SUMMARY","Return Period: ' . $sum['return_period'] . '"';
        $lines[] = '"Table Reference","Description","Taxable Value (INR)","IGST (INR)","CGST (INR)","SGST (INR)","Total Tax (INR)"';

        $t3 = $sum['table_3_1']['outward_taxable'];
        $lines[] = sprintf(
            '"3.1(a)","Outward Taxable Supplies",%.2f,%.2f,%.2f,%.2f,%.2f',
            $t3['taxable_value'], $t3['igst'], $t3['cgst'], $t3['sgst'], $t3['total_tax']
        );

        $t4a = $sum['table_4']['itc_available'];
        $lines[] = sprintf(
            '"4(A)(5)","All Other ITC (Purchases)",%.2f,%.2f,%.2f,%.2f,%.2f',
            $t4a['taxable_value'], $t4a['igst'], $t4a['cgst'], $t4a['sgst'], $t4a['total_tax']
        );

        $t4b = $sum['table_4']['itc_reversed'];
        $lines[] = sprintf(
            '"4(B)","ITC Reversals (Debit Notes)",0.00,%.2f,%.2f,%.2f,%.2f',
            $t4b['igst'], $t4b['cgst'], $t4b['sgst'], $t4b['total_tax']
        );

        $t4c = $sum['table_4']['net_itc'];
        $lines[] = sprintf(
            '"4(C)","Net Eligible ITC Available",0.00,%.2f,%.2f,%.2f,%.2f',
            $t4c['igst'], $t4c['cgst'], $t4c['sgst'], $t4c['total_tax']
        );

        $t6 = $sum['table_6_1']['net_tax_payable'];
        $lines[] = sprintf(
            '"6.1","Net Tax Payable via Electronic Cash Ledger",0.00,%.2f,%.2f,%.2f,%.2f',
            $t6['igst'], $t6['cgst'], $t6['sgst'], $t6['total']
        );

        $lines[] = sprintf(
            '"6.2","Excess ITC Carried Forward to Next Month",0.00,%.2f,%.2f,%.2f,%.2f',
            $sum['table_6_1']['excess_itc_cforward']['igst'],
            $sum['table_6_1']['excess_itc_cforward']['cgst'],
            $sum['table_6_1']['excess_itc_cforward']['sgst'],
            $sum['table_6_1']['excess_itc_cforward']['total']
        );

        return implode("\r\n", $lines);
    }

    /**
     * Get Comprehensive GST Sales Register.
     */
    public function getSalesRegister(int $companyId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? (string)$filters['from_date'] : date('Y-m-01');
        $toDate = !empty($filters['to_date']) ? (string)$filters['to_date'] : date('Y-m-d');
        $branchId = (int)($filters['branch_id'] ?? 0);
        $supplyType = strtoupper((string)($filters['supply_type'] ?? 'ALL')); // ALL, B2B, B2C

        $where = "i.company_id = ? AND i.invoice_date BETWEEN ? AND ? AND i.deleted_at IS NULL";
        $params = [$companyId, $fromDate, $toDate];

        if ($branchId > 0) {
            $where .= " AND (i.branch_id = ? OR i.branch_id IS NULL)";
            $params[] = $branchId;
        }

        if ($supplyType === 'B2B') {
            $where .= " AND (c.gstin IS NOT NULL AND LENGTH(c.gstin) = 15)";
        } elseif ($supplyType === 'B2C') {
            $where .= " AND (c.gstin IS NULL OR LENGTH(c.gstin) != 15)";
        }

        $rows = Database::select(
            "SELECT 
                i.id,
                i.invoice_number,
                i.invoice_date,
                i.place_of_supply,
                i.sub_total AS taxable_value,
                i.cgst_amount,
                i.sgst_amount,
                i.igst_amount,
                i.total_tax,
                i.grand_total,
                i.status,
                c.id AS customer_id,
                COALESCE(c.name, i.customer_name, 'Direct Client') AS customer_name,
                COALESCE(c.gstin, i.customer_gstin, '') AS customer_gstin,
                CASE 
                    WHEN c.gstin IS NOT NULL AND LENGTH(c.gstin) = 15 THEN 'B2B'
                    ELSE 'B2C'
                END AS b2b_type,
                b.name AS branch_name
             FROM invoices i
             LEFT JOIN customers c ON i.customer_id = c.id
             LEFT JOIN branches b ON i.branch_id = b.id
             WHERE {$where}
             ORDER BY i.invoice_date DESC, i.id DESC",
            $params
        );

        $totTaxable = 0.0;
        $totCgst = 0.0;
        $totSgst = 0.0;
        $totIgst = 0.0;
        $totTax = 0.0;
        $totGrand = 0.0;
        $b2bCount = 0;
        $b2cCount = 0;

        foreach ($rows as &$r) {
            $totTaxable += (float)$r['taxable_value'];
            $totCgst += (float)$r['cgst_amount'];
            $totSgst += (float)$r['sgst_amount'];
            $totIgst += (float)$r['igst_amount'];
            $totTax += (float)$r['total_tax'];
            $totGrand += (float)$r['grand_total'];

            if ($r['b2b_type'] === 'B2B') {
                $b2bCount++;
            } else {
                $b2cCount++;
            }
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'supply_type' => $supplyType,
            'summary' => [
                'total_invoices' => count($rows),
                'b2b_count' => $b2bCount,
                'b2c_count' => $b2cCount,
                'total_taxable' => round($totTaxable, 2),
                'total_cgst' => round($totCgst, 2),
                'total_sgst' => round($totSgst, 2),
                'total_igst' => round($totIgst, 2),
                'total_tax' => round($totTax, 2),
                'total_grand' => round($totGrand, 2),
            ],
            'invoices' => $rows,
        ];
    }

    /**
     * Export GST Sales Register to CSV.
     */
    public function exportSalesRegisterCsv(int $companyId, array $filters = []): string
    {
        $data = $this->getSalesRegister($companyId, $filters);
        $lines = [];

        $lines[] = '"GST OUTWARD SALES REGISTER","Period: ' . $data['from_date'] . ' to ' . $data['to_date'] . '"';
        $lines[] = '"Invoice No","Date","Customer Name","Customer GSTIN","Type","POS","Taxable (INR)","CGST (INR)","SGST (INR)","IGST (INR)","Total Tax (INR)","Grand Total (INR)"';

        foreach ($data['invoices'] as $inv) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s","%s",%.2f,%.2f,%.2f,%.2f,%.2f,%.2f',
                $inv['invoice_number'],
                $inv['invoice_date'],
                str_replace('"', '""', (string)$inv['customer_name']),
                $inv['customer_gstin'],
                $inv['b2b_type'],
                $inv['place_of_supply'],
                (float)$inv['taxable_value'],
                (float)$inv['cgst_amount'],
                (float)$inv['sgst_amount'],
                (float)$inv['igst_amount'],
                (float)$inv['total_tax'],
                (float)$inv['grand_total']
            );
        }

        $sum = $data['summary'];
        $lines[] = sprintf(
            '"TOTAL","Count: %d","","","","Total",%.2f,%.2f,%.2f,%.2f,%.2f,%.2f',
            $sum['total_invoices'],
            $sum['total_taxable'],
            $sum['total_cgst'],
            $sum['total_sgst'],
            $sum['total_igst'],
            $sum['total_tax'],
            $sum['total_grand']
        );

        return implode("\r\n", $lines);
    }

    /**
     * Get Comprehensive GST Inward Purchase Register.
     */
    public function getPurchaseRegister(int $companyId, array $filters = []): array
    {
        $fromDate = !empty($filters['from_date']) ? (string)$filters['from_date'] : date('Y-m-01');
        $toDate = !empty($filters['to_date']) ? (string)$filters['to_date'] : date('Y-m-d');
        $branchId = (int)($filters['branch_id'] ?? 0);

        $where = "p.company_id = ? AND p.purchase_date BETWEEN ? AND ? AND p.deleted_at IS NULL";
        $params = [$companyId, $fromDate, $toDate];

        if ($branchId > 0) {
            $where .= " AND (p.branch_id = ? OR p.branch_id IS NULL)";
            $params[] = $branchId;
        }

        $rows = Database::select(
            "SELECT 
                p.id,
                p.purchase_number,
                p.vendor_invoice_number,
                p.purchase_date,
                p.place_of_supply,
                p.sub_total AS taxable_value,
                p.cgst_amount,
                p.sgst_amount,
                p.igst_amount,
                p.total_tax,
                p.grand_total,
                p.status,
                s.id AS supplier_id,
                COALESCE(s.name, p.supplier_name, 'Direct Supplier') AS supplier_name,
                COALESCE(s.gstin, p.supplier_gstin, '') AS supplier_gstin,
                b.name AS branch_name
             FROM purchases p
             LEFT JOIN suppliers s ON p.supplier_id = s.id
             LEFT JOIN branches b ON p.branch_id = b.id
             WHERE {$where}
             ORDER BY p.purchase_date DESC, p.id DESC",
            $params
        );

        $totTaxable = 0.0;
        $totCgst = 0.0;
        $totSgst = 0.0;
        $totIgst = 0.0;
        $totTax = 0.0;
        $totGrand = 0.0;

        foreach ($rows as $r) {
            $totTaxable += (float)$r['taxable_value'];
            $totCgst += (float)$r['cgst_amount'];
            $totSgst += (float)$r['sgst_amount'];
            $totIgst += (float)$r['igst_amount'];
            $totTax += (float)$r['total_tax'];
            $totGrand += (float)$r['grand_total'];
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'summary' => [
                'total_bills' => count($rows),
                'total_taxable' => round($totTaxable, 2),
                'total_cgst' => round($totCgst, 2),
                'total_sgst' => round($totSgst, 2),
                'total_igst' => round($totIgst, 2),
                'total_tax' => round($totTax, 2),
                'total_grand' => round($totGrand, 2),
            ],
            'bills' => $rows,
        ];
    }

    /**
     * Export GST Purchase Register to CSV.
     */
    public function exportPurchaseRegisterCsv(int $companyId, array $filters = []): string
    {
        $data = $this->getPurchaseRegister($companyId, $filters);
        $lines = [];

        $lines[] = '"GST INWARD PURCHASE REGISTER","Period: ' . $data['from_date'] . ' to ' . $data['to_date'] . '"';
        $lines[] = '"Bill No","Vendor Inv No","Date","Supplier Name","Supplier GSTIN","POS","Taxable (INR)","CGST (INR)","SGST (INR)","IGST (INR)","Total Tax (INR)","Bill Total (INR)"';

        foreach ($data['bills'] as $b) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s","%s",%.2f,%.2f,%.2f,%.2f,%.2f,%.2f',
                $b['purchase_number'],
                $b['vendor_invoice_number'] ?? '',
                $b['purchase_date'],
                str_replace('"', '""', (string)$b['supplier_name']),
                $b['supplier_gstin'],
                $b['place_of_supply'] ?? '',
                (float)$b['taxable_value'],
                (float)$b['cgst_amount'],
                (float)$b['sgst_amount'],
                (float)$b['igst_amount'],
                (float)$b['total_tax'],
                (float)$b['grand_total']
            );
        }

        $sum = $data['summary'];
        $lines[] = sprintf(
            '"TOTAL","Count: %d","","","","Total",%.2f,%.2f,%.2f,%.2f,%.2f,%.2f',
            $sum['total_bills'],
            $sum['total_taxable'],
            $sum['total_cgst'],
            $sum['total_sgst'],
            $sum['total_igst'],
            $sum['total_tax'],
            $sum['total_grand']
        );

        return implode("\r\n", $lines);
    }
}
