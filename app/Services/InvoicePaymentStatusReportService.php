<?php

declare(strict_types=1);

namespace WTSBill\Services;

use WTSBill\Core\Database;
use ZipArchive;

/**
 * Service for Invoice Payment Status Report & Genuine Excel (.xlsx) Export.
 *
 * Provides comprehensive reporting and analysis for:
 * - Unpaid invoices
 * - Fully paid invoices
 * - Partially paid invoices with remaining balances
 * - Accurate multi-partial payment allocation aggregation
 * - Reconciled summary KPIs and genuine Office Open XML (.xlsx) workbook generation.
 */
class InvoicePaymentStatusReportService
{
    /**
     * Retrieve complete report payload including summary cards, filtered records, totals, and pagination.
     *
     * @param int $companyId
     * @param array $filters
     * @return array
     */
    public function getReport(int $companyId, array $filters = []): array
    {
        // 1. Fetch all eligible finalized sales invoices for the company
        $invoices = $this->fetchBaseInvoices($companyId, $filters);

        // 2. Compute Summary Cards from company-wide active invoices
        $summary = $this->calculateSummaryCards($invoices);

        // 3. Apply memory filters (e.g. payment_status, overdue, text search)
        $filtered = $this->applyFilters($invoices, $filters);

        // 4. Compute filtered totals
        $filteredTotals = $this->calculateFilteredTotals($filtered);

        // 5. Apply sorting
        $sortField = strtolower(trim((string)($filters['sort'] ?? 'invoice_date')));
        $sortDir = strtolower(trim((string)($filters['dir'] ?? 'desc'))) === 'asc' ? 'asc' : 'desc';
        $sorted = $this->sortInvoices($filtered, $sortField, $sortDir);

        // 6. Paginate records
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(5, min(200, (int)($filters['per_page'] ?? 25)));
        $totalRecords = count($sorted);
        $totalPages = max(1, (int)ceil($totalRecords / $perPage));
        $offset = ($page - 1) * $perPage;
        $paginatedRecords = array_slice($sorted, $offset, $perPage);

        return [
            'summary' => $summary,
            'records' => $paginatedRecords,
            'all_filtered_records' => $filtered,
            'filtered_totals' => $filteredTotals,
            'pagination' => [
                'total' => $totalRecords,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'from' => $totalRecords > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $totalRecords),
            ],
            'filters' => $filters,
            'sort' => [
                'field' => $sortField,
                'direction' => $sortDir,
            ],
        ];
    }

    /**
     * Fetch base invoices joined with customer details and payment allocation totals.
     *
     * @param int $companyId
     * @param array $filters
     * @return array
     */
    private function fetchBaseInvoices(int $companyId, array $filters = []): array
    {
        $where = [
            "i.company_id = ?",
            "i.deleted_at IS NULL",
            "i.status NOT IN ('CANCELLED', 'VOID')",
        ];
        $params = [$companyId];

        // SQL-level filters where applicable
        if (!empty($filters['branch_id'])) {
            $where[] = "i.branch_id = ?";
            $params[] = (int)$filters['branch_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = "i.invoice_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = "i.invoice_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['due_date_from'])) {
            $where[] = "i.due_date >= ?";
            $params[] = $filters['due_date_from'];
        }

        if (!empty($filters['due_date_to'])) {
            $where[] = "i.due_date <= ?";
            $params[] = $filters['due_date_to'];
        }

        $sql = "SELECT i.id,
                       i.invoice_number,
                       i.invoice_date,
                       i.due_date,
                       i.grand_total,
                       i.amount_paid AS recorded_paid,
                       i.amount_due AS recorded_due,
                       i.status AS invoice_status,
                       i.branch_id,
                       b.name AS branch_name,
                       COALESCE(c.name, i.customer_name, 'Direct Client') AS customer_name,
                       COALESCE(NULLIF(c.phone, ''), NULLIF(pt.phone, ''), NULLIF(c.whatsapp_number, ''), '') AS customer_mobile,
                       COALESCE(alloc.total_allocated, 0.00) AS allocated_paid
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                LEFT JOIN parties pt ON c.party_id = pt.id OR pt.customer_id = c.id
                LEFT JOIN branches b ON i.branch_id = b.id
                LEFT JOIN (
                    SELECT pa.document_id,
                           COALESCE(SUM(pa.allocated_amount), 0) AS total_allocated
                    FROM payment_allocations pa
                    JOIN payments py ON pa.payment_id = py.id
                    WHERE pa.document_type = 'INVOICE'
                      AND pa.company_id = ?
                      AND py.deleted_at IS NULL
                      AND py.status != 'CANCELLED'
                    GROUP BY pa.document_id
                ) alloc ON alloc.document_id = i.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.invoice_date DESC, i.id DESC";

        $fullParams = array_merge([$companyId], $params);
        $rows = Database::select($sql, $fullParams);

        $today = date('Y-m-d');
        $processed = [];

        foreach ($rows as $row) {
            $invoiceAmount = round((float)$row['grand_total'], 2);
            $allocated = round((float)($row['allocated_paid'] ?? 0), 2);
            $recorded = round((float)($row['recorded_paid'] ?? 0), 2);

            // Paid amount derives from valid allocations, falling back to recorded paid amount
            $paidAmount = ($allocated > 0.001) ? $allocated : $recorded;
            $paidAmount = min($invoiceAmount, max(0.00, round($paidAmount, 2)));

            // Remaining balance = Invoice Amount - Paid Amount
            $remainingBalance = max(0.00, round($invoiceAmount - $paidAmount, 2));

            // Status determination
            if ($remainingBalance <= 0.001) {
                $statusKey = 'PAID';
                $statusLabel = 'Paid';
                $remainingBalance = 0.00;
            } elseif ($paidAmount > 0.001) {
                $statusKey = 'PARTIALLY_PAID';
                $statusLabel = 'Partially Paid';
            } else {
                $statusKey = 'UNPAID';
                $statusLabel = 'Unpaid';
                $paidAmount = 0.00;
                $remainingBalance = $invoiceAmount;
            }

            $dueDate = !empty($row['due_date']) ? (string)$row['due_date'] : null;
            $isOverdue = (!empty($dueDate) && $dueDate < $today && $remainingBalance > 0.001);

            $processed[] = [
                'id' => (int)$row['id'],
                'invoice_number' => (string)$row['invoice_number'],
                'invoice_date' => (string)$row['invoice_date'],
                'due_date' => $dueDate,
                'customer_name' => (string)$row['customer_name'],
                'mobile_number' => (string)$row['customer_mobile'],
                'invoice_amount' => $invoiceAmount,
                'paid_amount' => $paidAmount,
                'remaining_balance' => $remainingBalance,
                'payment_status' => $statusKey,
                'payment_status_label' => $statusLabel,
                'is_overdue' => $isOverdue,
                'branch_id' => (int)$row['branch_id'],
                'branch_name' => (string)($row['branch_name'] ?? 'Main Branch'),
            ];
        }

        return $processed;
    }

    /**
     * Calculate Summary Cards:
     * A. Pending Invoices (Unpaid)
     * B. Paid Invoices
     * C. Partially Paid Invoices
     * D. Total Outstanding
     *
     * @param array $invoices
     * @return array
     */
    private function calculateSummaryCards(array $invoices): array
    {
        $unpaidCount = 0;
        $unpaidInvoiceAmount = 0.00;
        $unpaidOutstanding = 0.00;

        $paidCount = 0;
        $paidInvoiceAmount = 0.00;
        $paidAmountReceived = 0.00;

        $partialCount = 0;
        $partialInvoiceAmount = 0.00;
        $partialAmountReceived = 0.00;
        $partialRemainingBalance = 0.00;

        foreach ($invoices as $inv) {
            $invAmt = $inv['invoice_amount'];
            $paidAmt = $inv['paid_amount'];
            $remBal = $inv['remaining_balance'];

            switch ($inv['payment_status']) {
                case 'UNPAID':
                    $unpaidCount++;
                    $unpaidInvoiceAmount += $invAmt;
                    $unpaidOutstanding += $remBal;
                    break;

                case 'PAID':
                    $paidCount++;
                    $paidInvoiceAmount += $invAmt;
                    $paidAmountReceived += $paidAmt;
                    break;

                case 'PARTIALLY_PAID':
                    $partialCount++;
                    $partialInvoiceAmount += $invAmt;
                    $partialAmountReceived += $paidAmt;
                    $partialRemainingBalance += $remBal;
                    break;
            }
        }

        $totalOutstanding = round($unpaidOutstanding + $partialRemainingBalance, 2);

        return [
            'pending' => [
                'count' => $unpaidCount,
                'total_invoice_amount' => round($unpaidInvoiceAmount, 2),
                'total_outstanding_amount' => round($unpaidOutstanding, 2),
            ],
            'paid' => [
                'count' => $paidCount,
                'total_invoice_amount' => round($paidInvoiceAmount, 2),
                'total_amount_received' => round($paidAmountReceived, 2),
            ],
            'partially_paid' => [
                'count' => $partialCount,
                'total_invoice_amount' => round($partialInvoiceAmount, 2),
                'total_amount_received' => round($partialAmountReceived, 2),
                'total_remaining_balance' => round($partialRemainingBalance, 2),
            ],
            'total_outstanding' => $totalOutstanding,
        ];
    }

    /**
     * Apply in-memory filters for fields like search, customer name, status, mobile, overdue.
     *
     * @param array $invoices
     * @param array $filters
     * @return array
     */
    private function applyFilters(array $invoices, array $filters): array
    {
        $statusFilter = strtoupper(trim((string)($filters['payment_status'] ?? 'ALL')));
        if ($statusFilter === 'PARTIAL') {
            $statusFilter = 'PARTIALLY_PAID';
        }

        $invNoFilter = strtolower(trim((string)($filters['invoice_number'] ?? '')));
        $custNameFilter = strtolower(trim((string)($filters['customer_name'] ?? '')));
        $mobileFilter = strtolower(trim((string)($filters['mobile'] ?? '')));
        $searchFilter = strtolower(trim((string)($filters['search'] ?? '')));
        $overdueFilter = strtolower(trim((string)($filters['overdue'] ?? 'all')));

        return array_values(array_filter($invoices, function ($inv) use (
            $statusFilter,
            $invNoFilter,
            $custNameFilter,
            $mobileFilter,
            $searchFilter,
            $overdueFilter
        ) {
            // Payment Status filter
            if ($statusFilter !== 'ALL' && $inv['payment_status'] !== $statusFilter) {
                return false;
            }

            // Invoice Number filter
            if ($invNoFilter !== '' && !str_contains(strtolower($inv['invoice_number']), $invNoFilter)) {
                return false;
            }

            // Customer Name filter
            if ($custNameFilter !== '' && !str_contains(strtolower($inv['customer_name']), $custNameFilter)) {
                return false;
            }

            // Mobile Number filter
            if ($mobileFilter !== '' && !str_contains(strtolower($inv['mobile_number']), $mobileFilter)) {
                return false;
            }

            // Overdue filter
            if ($overdueFilter === 'overdue' && !$inv['is_overdue']) {
                return false;
            }
            if ($overdueFilter === 'not_overdue' && $inv['is_overdue']) {
                return false;
            }

            // Global Search filter
            if ($searchFilter !== '') {
                $haystack = strtolower(
                    $inv['invoice_number'] . ' ' .
                    $inv['customer_name'] . ' ' .
                    $inv['mobile_number'] . ' ' .
                    $inv['branch_name']
                );
                if (!str_contains($haystack, $searchFilter)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Calculate summary totals for currently filtered records.
     *
     * @param array $records
     * @return array
     */
    private function calculateFilteredTotals(array $records): array
    {
        $totalInvoiceAmount = 0.00;
        $totalPaidAmount = 0.00;
        $totalRemainingBalance = 0.00;

        foreach ($records as $r) {
            $totalInvoiceAmount += $r['invoice_amount'];
            $totalPaidAmount += $r['paid_amount'];
            $totalRemainingBalance += $r['remaining_balance'];
        }

        return [
            'count' => count($records),
            'total_invoice_amount' => round($totalInvoiceAmount, 2),
            'total_paid_amount' => round($totalPaidAmount, 2),
            'total_remaining_balance' => round($totalRemainingBalance, 2),
        ];
    }

    /**
     * Sort invoices by designated field and direction.
     *
     * @param array $records
     * @param string $field
     * @param string $dir
     * @return array
     */
    private function sortInvoices(array $records, string $field, string $dir): array
    {
        usort($records, function ($a, $b) use ($field, $dir) {
            $valA = $a[$field] ?? null;
            $valB = $b[$field] ?? null;

            if (is_numeric($valA) && is_numeric($valB)) {
                $cmp = $valA <=> $valB;
            } else {
                $cmp = strcasecmp((string)$valA, (string)$valB);
            }

            return $dir === 'asc' ? $cmp : -$cmp;
        });

        return $records;
    }

    /**
     * Generate genuine Office Open XML (.xlsx) workbook for invoice payment status.
     *
     * Exact Required Columns:
     * 1. Invoice Number
     * 2. Customer Name
     * 3. Invoice Amount
     * 4. Mobile Number
     * 5. Due Date
     * 6. Invoice Date
     * 7. Paid Amount
     * 8. Remaining Balance
     * 9. Payment Status
     *
     * @param int $companyId
     * @param array $filters
     * @return string Binary string of the generated .xlsx archive
     */
    public function generateExcelWorkbook(int $companyId, array $filters = []): string
    {
        $report = $this->getReport($companyId, $filters);
        $summary = $report['summary'];
        $records = $report['all_filtered_records'];
        $totals = $report['filtered_totals'];

        $tempFile = tempnam(sys_get_temp_dir(), 'wtsbill_xlsx_') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to initialize Excel archive on server.');
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Payment Status Report" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 4. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 5. xl/styles.xml
        // Style Index Map:
        // 0: Normal text
        // 1: Header Green (#235347, White Bold, Centered)
        // 2: Currency Numeric Right-Aligned (#,##0.00)
        // 3: Date Centered (yyyy-mm-dd)
        // 4: Regular bordered cell
        // 5: Title Bold 14pt Forest Green (#0B2B26)
        // 6: Subtitle / KPI label 9pt Gray
        // 7: KPI value bold 11pt
        // 8: Totals Row Bold (#DAF1DE background, Dark Green text)
        // 9: Totals Currency Bold (#DAF1DE background, Dark Green text)
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="2">
    <numFmt numFmtId="164" formatCode="#,##0.00"/>
    <numFmt numFmtId="165" formatCode="yyyy-mm-dd"/>
  </numFmts>
  <fonts count="6">
    <font><sz val="10"/><color theme="1"/><name val="Calibri"/></font>
    <font><b/><sz val="10.5"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
    <font><b/><sz val="14"/><color rgb="FF0B2B26"/><name val="Calibri"/></font>
    <font><sz val="9"/><color rgb="FF64748B"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FF0B2B26"/><name val="Calibri"/></font>
    <font><b/><sz val="10.5"/><color rgb="FF051F20"/><name val="Calibri"/></font>
  </fonts>
  <fills count="5">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF235347"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFDAF1DE"/></patternFill></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/></patternFill></fill>
  </fills>
  <borders count="3">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFCBD5E1"/></left>
      <right style="thin"><color rgb="FFCBD5E1"/></right>
      <top style="thin"><color rgb="FFCBD5E1"/></top>
      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
    </border>
    <border>
      <left style="thin"><color rgb="FF8EB69B"/></left>
      <right style="thin"><color rgb="FF8EB69B"/></right>
      <top style="medium"><color rgb="FF235347"/></top>
      <bottom style="double"><color rgb="FF235347"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="10">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
    <xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="center"/></xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
    <xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>
    <xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="164" fontId="4" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1"><alignment horizontal="right"/></xf>
    <xf numFmtId="0" fontId="5" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>
    <xf numFmtId="164" fontId="5" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1"><alignment horizontal="right"/></xf>
  </cellXfs>
</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. Build xl/worksheets/sheet1.xml
        $sheetXml = $this->buildWorksheetXml($summary, $records, $totals);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();

        $binary = (string)file_get_contents($tempFile);
        @unlink($tempFile);

        return $binary;
    }

    /**
     * Build sheet1.xml content with summary KPI cards, headers, and rows.
     *
     * @param array $summary
     * @param array $records
     * @param array $totals
     * @return string
     */
    private function buildWorksheetXml(array $summary, array $records, array $totals): string
    {
        $today = date('Y-m-d H:i:s');
        $rowsXml = [];

        // Row 1: Title
        $rowsXml[] = '<row r="1" ht="28" customHeight="1">';
        $rowsXml[] = '<c r="A1" t="inlineStr" s="5"><is><t>' . $this->sanitizeText('WTSBill ERP — Invoice Payment Status Report') . '</t></is></c>';
        $rowsXml[] = '</row>';

        // Row 2: Subtitle with Generated Timestamp
        $rowsXml[] = '<row r="2" ht="18" customHeight="1">';
        $rowsXml[] = '<c r="A2" t="inlineStr"><is><t>' . $this->sanitizeText("Generated: {$today} | Currency: INR (₹)") . '</t></is></c>';
        $rowsXml[] = '</row>';

        // Row 3: Blank separator
        $rowsXml[] = '<row r="3" ht="10" customHeight="1"></row>';

        // Row 4: Summary KPIs Header
        $rowsXml[] = '<row r="4" ht="20" customHeight="1">';
        $rowsXml[] = '<c r="A4" t="inlineStr" s="6"><is><t>PENDING INVOICES</t></is></c>';
        $rowsXml[] = '<c r="B4" t="inlineStr" s="6"><is><t>Original: ₹' . number_format($summary['pending']['total_invoice_amount'], 2) . '</t></is></c>';
        $rowsXml[] = '<c r="C4" t="inlineStr" s="6"><is><t>PAID INVOICES</t></is></c>';
        $rowsXml[] = '<c r="D4" t="inlineStr" s="6"><is><t>Received: ₹' . number_format($summary['paid']['total_amount_received'], 2) . '</t></is></c>';
        $rowsXml[] = '<c r="E4" t="inlineStr" s="6"><is><t>PARTIALLY PAID</t></is></c>';
        $rowsXml[] = '<c r="F4" t="inlineStr" s="6"><is><t>Remaining: ₹' . number_format($summary['partially_paid']['total_remaining_balance'], 2) . '</t></is></c>';
        $rowsXml[] = '<c r="G4" t="inlineStr" s="6"><is><t>TOTAL OUTSTANDING</t></is></c>';
        $rowsXml[] = '<c r="H4" t="n" s="7"><v>' . $summary['total_outstanding'] . '</v></c>';
        $rowsXml[] = '</row>';

        // Row 5: Summary KPI Values Breakdown
        $rowsXml[] = '<row r="5" ht="20" customHeight="1">';
        $rowsXml[] = '<c r="A5" t="inlineStr" s="6"><is><t>' . $summary['pending']['count'] . ' Unpaid Bills</t></is></c>';
        $rowsXml[] = '<c r="B5" t="n" s="7"><v>' . $summary['pending']['total_outstanding_amount'] . '</v></c>';
        $rowsXml[] = '<c r="C5" t="inlineStr" s="6"><is><t>' . $summary['paid']['count'] . ' Full Receipts</t></is></c>';
        $rowsXml[] = '<c r="D5" t="n" s="7"><v>' . $summary['paid']['total_invoice_amount'] . '</v></c>';
        $rowsXml[] = '<c r="E5" t="inlineStr" s="6"><is><t>' . $summary['partially_paid']['count'] . ' Installments</t></is></c>';
        $rowsXml[] = '<c r="F5" t="n" s="7"><v>' . $summary['partially_paid']['total_remaining_balance'] . '</v></c>';
        $rowsXml[] = '<c r="G5" t="inlineStr" s="6"><is><t>Net Due</t></is></c>';
        $rowsXml[] = '<c r="H5" t="n" s="7"><v>' . $summary['total_outstanding'] . '</v></c>';
        $rowsXml[] = '</row>';

        // Row 6: Blank separator
        $rowsXml[] = '<row r="6" ht="12" customHeight="1"></row>';

        // Row 7: Main Data Table Headers (Exact 9 Columns Requested)
        $rowsXml[] = '<row r="7" ht="26" customHeight="1">';
        $rowsXml[] = '<c r="A7" t="inlineStr" s="1"><is><t>Invoice Number</t></is></c>';
        $rowsXml[] = '<c r="B7" t="inlineStr" s="1"><is><t>Customer Name</t></is></c>';
        $rowsXml[] = '<c r="C7" t="inlineStr" s="1"><is><t>Invoice Amount</t></is></c>';
        $rowsXml[] = '<c r="D7" t="inlineStr" s="1"><is><t>Mobile Number</t></is></c>';
        $rowsXml[] = '<c r="E7" t="inlineStr" s="1"><is><t>Due Date</t></is></c>';
        $rowsXml[] = '<c r="F7" t="inlineStr" s="1"><is><t>Invoice Date</t></is></c>';
        $rowsXml[] = '<c r="G7" t="inlineStr" s="1"><is><t>Paid Amount</t></is></c>';
        $rowsXml[] = '<c r="H7" t="inlineStr" s="1"><is><t>Remaining Balance</t></is></c>';
        $rowsXml[] = '<c r="I7" t="inlineStr" s="1"><is><t>Payment Status</t></is></c>';
        $rowsXml[] = '</row>';

        // Data Rows starting at Row 8
        $rowNum = 8;
        foreach ($records as $r) {
            $rowsXml[] = '<row r="' . $rowNum . '" ht="20" customHeight="1">';
            // 1. Invoice Number
            $rowsXml[] = '<c r="A' . $rowNum . '" t="inlineStr" s="4"><is><t>' . $this->sanitizeText($r['invoice_number']) . '</t></is></c>';
            // 2. Customer Name
            $rowsXml[] = '<c r="B' . $rowNum . '" t="inlineStr" s="4"><is><t>' . $this->sanitizeText($r['customer_name']) . '</t></is></c>';
            // 3. Invoice Amount
            $rowsXml[] = '<c r="C' . $rowNum . '" t="n" s="2"><v>' . $r['invoice_amount'] . '</v></c>';
            // 4. Mobile Number
            $rowsXml[] = '<c r="D' . $rowNum . '" t="inlineStr" s="4"><is><t>' . $this->sanitizeText($r['mobile_number']) . '</t></is></c>';
            // 5. Due Date
            $rowsXml[] = '<c r="E' . $rowNum . '" t="inlineStr" s="3"><is><t>' . $this->sanitizeText($r['due_date'] ?? 'N/A') . '</t></is></c>';
            // 6. Invoice Date
            $rowsXml[] = '<c r="F' . $rowNum . '" t="inlineStr" s="3"><is><t>' . $this->sanitizeText($r['invoice_date']) . '</t></is></c>';
            // 7. Paid Amount
            $rowsXml[] = '<c r="G' . $rowNum . '" t="n" s="2"><v>' . $r['paid_amount'] . '</v></c>';
            // 8. Remaining Balance
            $rowsXml[] = '<c r="H' . $rowNum . '" t="n" s="2"><v>' . $r['remaining_balance'] . '</v></c>';
            // 9. Payment Status
            $statusWithOverdue = $r['payment_status_label'];
            if ($r['is_overdue']) {
                $statusWithOverdue .= ' (OVERDUE)';
            }
            $rowsXml[] = '<c r="I' . $rowNum . '" t="inlineStr" s="4"><is><t>' . $this->sanitizeText($statusWithOverdue) . '</t></is></c>';
            $rowsXml[] = '</row>';
            $rowNum++;
        }

        // Summary Totals Footer Row
        $rowsXml[] = '<row r="' . $rowNum . '" ht="24" customHeight="1">';
        $rowsXml[] = '<c r="A' . $rowNum . '" t="inlineStr" s="8"><is><t>TOTALS (' . $totals['count'] . ' Invoices)</t></is></c>';
        $rowsXml[] = '<c r="B' . $rowNum . '" t="inlineStr" s="8"><is><t></t></is></c>';
        $rowsXml[] = '<c r="C' . $rowNum . '" t="n" s="9"><v>' . $totals['total_invoice_amount'] . '</v></c>';
        $rowsXml[] = '<c r="D' . $rowNum . '" t="inlineStr" s="8"><is><t></t></is></c>';
        $rowsXml[] = '<c r="E' . $rowNum . '" t="inlineStr" s="8"><is><t></t></is></c>';
        $rowsXml[] = '<c r="F' . $rowNum . '" t="inlineStr" s="8"><is><t></t></is></c>';
        $rowsXml[] = '<c r="G' . $rowNum . '" t="n" s="9"><v>' . $totals['total_paid_amount'] . '</v></c>';
        $rowsXml[] = '<c r="H' . $rowNum . '" t="n" s="9"><v>' . $totals['total_remaining_balance'] . '</v></c>';
        $rowsXml[] = '<c r="I' . $rowNum . '" t="inlineStr" s="8"><is><t></t></is></c>';
        $rowsXml[] = '</row>';

        $rowsJoined = implode('', $rowsXml);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetViews>
    <sheetView tabSelected="1" workbookViewId="0">
      <pane ySplit="7" topLeftCell="A8" activePane="bottomLeft" state="frozen"/>
    </sheetView>
  </sheetViews>
  <cols>
    <col min="1" max="1" width="20" customWidth="1"/>
    <col min="2" max="2" width="30" customWidth="1"/>
    <col min="3" max="3" width="18" customWidth="1"/>
    <col min="4" max="4" width="18" customWidth="1"/>
    <col min="5" max="5" width="15" customWidth="1"/>
    <col min="6" max="6" width="15" customWidth="1"/>
    <col min="7" max="7" width="18" customWidth="1"/>
    <col min="8" max="8" width="20" customWidth="1"/>
    <col min="9" max="9" width="18" customWidth="1"/>
  </cols>
  <sheetData>
    ' . $rowsJoined . '
  </sheetData>
</worksheet>';
    }

    /**
     * Prevent spreadsheet formula injection and escape XML special characters.
     *
     * @param string|null $text
     * @return string
     */
    private function sanitizeText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $trimmed = trim($text);

        // Anti-formula injection: prepend apostrophe if string begins with =, +, -, @
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            $trimmed = "'" . $trimmed;
        }

        return htmlspecialchars($trimmed, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

