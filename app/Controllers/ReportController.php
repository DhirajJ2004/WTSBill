<?php

declare(strict_types=1);

namespace WTSBill\Controllers;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Response;
use WTSBill\Core\Session;
use WTSBill\Core\View;
use WTSBill\Services\BalanceSheetService;

/**
 * Report Controller.
 * Handles GSTR-1 Tax Compliance returns, JSON payload export for GST portal,
 * and accountant-grade Balance Sheet statements, APIs, and exports.
 */
class ReportController
{
    private BalanceSheetService $balanceSheetService;

    public function __construct(?BalanceSheetService $balanceSheetService = null)
    {
        $this->balanceSheetService = $balanceSheetService ?? new BalanceSheetService();
    }

    /**
     * GSTR-1 summary view.
     */
    public function gstIndex(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;

        $invoices = Database::select(
            "SELECT i.*, COALESCE(c.name, i.customer_name, 'Direct Client') as customer_name, c.gstin as customer_gstin 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.company_id = ? 
             ORDER BY i.id DESC",
            [$companyId]
        );

        $html = View::renderWithLayout('reports/gst', [
            'title' => 'GSTR-1 Compliance',
            'currentUri' => '/reports/gst',
            'invoices' => $invoices,
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Export GSTR-1 data as JSON or CSV/Excel file stream.
     */
    public function gstExport(Request $req): Response
    {
        $companyId = Session::getCompanyId() ?? 1;
        $format = strtolower(trim((string)($req->query('format') ?? 'json')));

        $invoices = Database::select(
            "SELECT i.invoice_number, i.invoice_date, COALESCE(c.name, i.customer_name) as customer_name,
                    COALESCE(c.gstin, i.customer_gstin, 'URP') as customer_gstin,
                    i.place_of_supply, i.sub_total as taxable_value, i.cgst_amount, i.sgst_amount, i.igst_amount, i.grand_total 
             FROM invoices i 
             LEFT JOIN customers c ON i.customer_id = c.id 
             WHERE i.company_id = ? 
             ORDER BY i.id ASC",
            [$companyId]
        );

        if ($format === 'json') {
            $gstPayload = [
                'gstin' => '27AADCW7577N1ZE',
                'fp' => date('mY'),
                'cur_gt' => array_sum(array_column($invoices, 'grand_total')),
                'b2b' => array_map(function($inv) {
                    return [
                        'ctin' => $inv['customer_gstin'],
                        'inv' => [
                            [
                                'inum' => $inv['invoice_number'],
                                'idt' => date('d-m-Y', strtotime($inv['invoice_date'])),
                                'val' => (float)$inv['grand_total'],
                                'pos' => $inv['place_of_supply'] ?: '27',
                                'rchrg' => 'N',
                                'inv_typ' => 'R',
                                'itms' => [
                                    [
                                        'num' => 1,
                                        'itm_det' => [
                                            'txval' => (float)$inv['taxable_value'],
                                            'rt' => 18.0,
                                            'iamt' => (float)$inv['igst_amount'],
                                            'camt' => (float)$inv['cgst_amount'],
                                            'samt' => (float)$inv['sgst_amount'],
                                            'csamt' => 0.0,
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ];
                }, $invoices),
            ];

            $jsonContent = json_encode($gstPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $resp = Response::json($gstPayload);
            return $resp->withHeader('Content-Disposition', 'attachment; filename="GSTR1_' . date('Ymd_His') . '.json"')
                        ->withHeader('Content-Type', 'application/json');
        }

        // CSV / Excel export
        $lines = [];
        $lines[] = 'Invoice Number,Invoice Date,Customer Name,Customer GSTIN,Place of Supply,Taxable Value,CGST,SGST,IGST,Grand Total';
        foreach ($invoices as $inv) {
            $lines[] = sprintf(
                '"%s","%s","%s","%s","%s",%.2f,%.2f,%.2f,%.2f,%.2f',
                $inv['invoice_number'],
                $inv['invoice_date'],
                str_replace('"', '""', (string)$inv['customer_name']),
                $inv['customer_gstin'],
                $inv['place_of_supply'],
                (float)$inv['taxable_value'],
                (float)$inv['cgst_amount'],
                (float)$inv['sgst_amount'],
                (float)$inv['igst_amount'],
                (float)$inv['grand_total']
            );
        }

        $csvContent = implode("\r\n", $lines);
        $resp = new Response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="GSTR1_Export_' . date('Ymd_His') . '.csv"',
        ]);

        return $resp;
    }

    /**
     * Professional Balance Sheet statement page view.
     * Route: GET /reports/balance-sheet
     */
    public function balanceSheet(Request $req): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 0);
        $asOf = (string)($req->query('as_of') ?? date('Y-m-d'));
        $compare = (string)($req->query('compare') ?? 'none');

        $statement = $this->balanceSheetService->getStatement($companyId, $branchId, $asOf, $compare);

        $html = View::renderWithLayout('reports/balance-sheet', [
            'title' => 'Balance Sheet',
            'currentUri' => '/reports/balance-sheet',
            'statement' => $statement,
            'asOf' => $statement['as_of'],
            'compare' => $statement['compare'],
        ], 'layouts/app');

        return Response::html($html);
    }

    /**
     * Balance Sheet live JSON API endpoint.
     * Route: GET /api/reports/balance-sheet
     */
    public function balanceSheetApi(Request $req): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 0);
        $asOf = (string)($req->query('as_of') ?? date('Y-m-d'));
        $compare = (string)($req->query('compare') ?? 'none');

        $statement = $this->balanceSheetService->getStatement($companyId, $branchId, $asOf, $compare);

        return Response::json($statement);
    }

    /**
     * Export Balance Sheet as CSV, Excel (.xlsx/.xls), or Printable PDF.
     * Route: GET /reports/balance-sheet/export
     */
    public function balanceSheetExport(Request $req): Response
    {
        $companyId = (int)(Session::getCompanyId() ?? 1);
        $branchId = (int)(Session::getBranchId() ?? 0);
        $asOf = (string)($req->query('as_of') ?? date('Y-m-d'));
        $compare = (string)($req->query('compare') ?? 'none');
        $format = strtolower(trim((string)($req->query('format') ?? 'csv')));

        $statement = $this->balanceSheetService->getStatement($companyId, $branchId, $asOf, $compare);

        if ($format === 'csv') {
            return $this->exportBalanceSheetCsv($statement);
        }

        if ($format === 'xlsx' || $format === 'excel' || $format === 'xls') {
            return $this->exportBalanceSheetExcel($statement);
        }

        if ($format === 'pdf') {
            return $this->exportBalanceSheetPdf($statement);
        }

        return $this->exportBalanceSheetCsv($statement);
    }

    /**
     * Generate structured CSV export for Balance Sheet.
     */
    private function exportBalanceSheetCsv(array $data): Response
    {
        $hasCompare = ($data['compare'] !== 'none');
        $lines = [];

        // Meta Header Block
        $lines[] = '"WTSBill ERP — Financial Balance Sheet Statement"';
        $lines[] = '"Company: ' . str_replace('"', '""', $data['company']['name']) . '","GSTIN: ' . $data['company']['gstin'] . '","Financial Year: ' . $data['financial_year'] . '"';
        $lines[] = '"Operating Branch: ' . str_replace('"', '""', $data['branch']['name']) . '","As of Date: ' . $data['as_of_formatted'] . '","Comparison: ' . strtoupper($data['compare']) . ($data['compare_formatted'] ? ' (' . $data['compare_formatted'] . ')' : '') . '"';
        $lines[] = '"Generated on: ' . $data['generated_at'] . '","Status: ' . ($data['balanced'] ? 'BALANCED' : 'UNBALANCED (Diff: ' . $data['difference_formatted'] . ')') . '"';
        $lines[] = '""';

        // Column Titles
        if ($hasCompare) {
            $lines[] = '"Account Group / Ledger Name","Code","Current Amount (' . $data['as_of_formatted'] . ')","Previous Amount (' . $data['compare_formatted'] . ')","Change (₹)","Change (%)"';
        } else {
            $lines[] = '"Account Group / Ledger Name","Code","Amount (INR)"';
        }

        // Section 1: ASSETS
        $lines[] = '"--- TOTAL ASSETS ---","","' . ($hasCompare ? '","","","' : '') . '"';
        foreach ($data['assets']['groups'] as $grp) {
            $lines[] = '"[ ' . str_replace('"', '""', $grp['title']) . ' ]","","' . ($hasCompare ? '","","","' : '') . '"';
            foreach ($grp['accounts'] as $acc) {
                if ($hasCompare) {
                    $lines[] = sprintf(
                        '"  %s","%s",%.2f,%.2f,%.2f,%.1f%%',
                        str_replace('"', '""', $acc['name']),
                        $acc['code'],
                        (float)$acc['amount'],
                        (float)$acc['previous_amount'],
                        (float)$acc['change_amount'],
                        (float)$acc['change_percent']
                    );
                } else {
                    $lines[] = sprintf(
                        '"  %s","%s",%.2f',
                        str_replace('"', '""', $acc['name']),
                        $acc['code'],
                        (float)$acc['amount']
                    );
                }
            }
            if ($hasCompare) {
                $lines[] = sprintf(
                    '"Subtotal %s","",%.2f,%.2f,%.2f,%.1f%%',
                    str_replace('"', '""', $grp['title']),
                    (float)$grp['subtotal'],
                    (float)$grp['previous_subtotal'],
                    (float)$grp['change_amount'],
                    (float)$grp['change_percent']
                );
            } else {
                $lines[] = sprintf('"Subtotal %s","",%.2f', str_replace('"', '""', $grp['title']), (float)$grp['subtotal']);
            }
            $lines[] = '""';
        }

        if ($hasCompare) {
            $lines[] = sprintf('"TOTAL ASSETS","",%.2f,%.2f,%.2f,%.1f%%', (float)$data['assets']['total'], (float)$data['assets']['previous_total'], (float)$data['assets']['change_amount'], (float)$data['assets']['change_percent']);
        } else {
            $lines[] = sprintf('"TOTAL ASSETS","",%.2f', (float)$data['assets']['total']);
        }
        $lines[] = '""';

        // Section 2: LIABILITIES & EQUITY
        $lines[] = '"--- LIABILITIES & EQUITY ---","","' . ($hasCompare ? '","","","' : '') . '"';
        foreach ($data['liabilities_equity']['groups'] as $grp) {
            $lines[] = '"[ ' . str_replace('"', '""', $grp['title']) . ' ]","","' . ($hasCompare ? '","","","' : '') . '"';
            foreach ($grp['accounts'] as $acc) {
                if ($hasCompare) {
                    $lines[] = sprintf(
                        '"  %s","%s",%.2f,%.2f,%.2f,%.1f%%',
                        str_replace('"', '""', $acc['name']),
                        $acc['code'],
                        (float)$acc['amount'],
                        (float)$acc['previous_amount'],
                        (float)$acc['change_amount'],
                        (float)$acc['change_percent']
                    );
                } else {
                    $lines[] = sprintf(
                        '"  %s","%s",%.2f',
                        str_replace('"', '""', $acc['name']),
                        $acc['code'],
                        (float)$acc['amount']
                    );
                }
            }
            if ($hasCompare) {
                $lines[] = sprintf(
                    '"Subtotal %s","",%.2f,%.2f,%.2f,%.1f%%',
                    str_replace('"', '""', $grp['title']),
                    (float)$grp['subtotal'],
                    (float)$grp['previous_subtotal'],
                    (float)$grp['change_amount'],
                    (float)$grp['change_percent']
                );
            } else {
                $lines[] = sprintf('"Subtotal %s","",%.2f', str_replace('"', '""', $grp['title']), (float)$grp['subtotal']);
            }
            $lines[] = '""';
        }

        if ($hasCompare) {
            $lines[] = sprintf('"TOTAL LIABILITIES & EQUITY","",%.2f,%.2f,%.2f,%.1f%%', (float)$data['liabilities_equity']['total'], (float)$data['liabilities_equity']['previous_total'], (float)$data['liabilities_equity']['change_amount'], (float)$data['liabilities_equity']['change_percent']);
        } else {
            $lines[] = sprintf('"TOTAL LIABILITIES & EQUITY","",%.2f', (float)$data['liabilities_equity']['total']);
        }

        $lines[] = '""';
        $lines[] = sprintf('"Accounting Balance Check: %s","Difference: %.2f"', $data['balanced'] ? 'BALANCED' : 'OUT OF BALANCE', (float)$data['difference']);

        $csv = "\xEF\xBB\xBF" . implode("\r\n", $lines);
        $filename = 'Balance_Sheet_' . $data['as_of'] . '_' . date('His') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Generate Excel XML spreadsheet export with formatting, bold headers, and numbers.
     */
    private function exportBalanceSheetExcel(array $data): Response
    {
        $hasCompare = ($data['compare'] !== 'none');
        $filename = 'Balance_Sheet_' . $data['as_of'] . '_' . date('His') . '.xls';

        ob_start();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
        ?>
        <Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
                  xmlns:o="urn:schemas-microsoft-com:office:office"
                  xmlns:x="urn:schemas-microsoft-com:office:excel"
                  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
                  xmlns:html="http://www.w3.org/TR/REC-html40">
            <Styles>
                <Style ss:ID="Default" ss:Name="Normal">
                    <Alignment ss:Vertical="Center"/>
                    <Font ss:FontName="Calibri" ss:Size="11" ss:Color="#000000"/>
                </Style>
                <Style ss:ID="Title">
                    <Font ss:FontName="Calibri" ss:Size="16" ss:Bold="1" ss:Color="#1e3a8a"/>
                </Style>
                <Style ss:ID="Subtitle">
                    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#475569"/>
                </Style>
                <Style ss:ID="SectionHeader">
                    <Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1" ss:Color="#ffffff"/>
                    <Interior ss:Color="#1e293b" ss:Pattern="Solid"/>
                </Style>
                <Style ss:ID="ColHeader">
                    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#ffffff"/>
                    <Interior ss:Color="#334155" ss:Pattern="Solid"/>
                    <Alignment ss:Horizontal="Center"/>
                </Style>
                <Style ss:ID="GroupHeader">
                    <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0f172a"/>
                    <Interior ss:Color="#f1f5f9" ss:Pattern="Solid"/>
                </Style>
                <Style ss:ID="TextLeft">
                    <Alignment ss:Horizontal="Left"/>
                </Style>
                <Style ss:ID="NumRight">
                    <Alignment ss:Horizontal="Right"/>
                    <NumberFormat ss:Format="#,##0.00"/>
                </Style>
                <Style ss:ID="SubtotalRow">
                    <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#0f172a"/>
                    <Interior ss:Color="#f8fafc" ss:Pattern="Solid"/>
                    <Borders>
                        <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                        <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
                    </Borders>
                </Style>
                <Style ss:ID="TotalRow">
                    <Font ss:FontName="Calibri" ss:Size="12" ss:Bold="1" ss:Color="#1e3a8a"/>
                    <Interior ss:Color="#e2e8f0" ss:Pattern="Solid"/>
                    <Borders>
                        <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
                        <Border ss:Position="Bottom" ss:LineStyle="Double" ss:Weight="3"/>
                    </Borders>
                </Style>
            </Styles>
            <Worksheet ss:Name="Balance Sheet">
                <Table>
                    <Column ss:Width="260"/>
                    <Column ss:Width="80"/>
                    <Column ss:Width="130"/>
                    <?php if ($hasCompare): ?>
                        <Column ss:Width="130"/>
                        <Column ss:Width="110"/>
                        <Column ss:Width="90"/>
                    <?php endif; ?>

                    <Row ss:Height="26">
                        <Cell ss:StyleID="Title"><Data ss:Type="String"><?= htmlspecialchars($data['company']['name']) ?> — Balance Sheet</Data></Cell>
                    </Row>
                    <Row>
                        <Cell ss:StyleID="Subtitle"><Data ss:Type="String">GSTIN: <?= htmlspecialchars($data['company']['gstin']) ?> | <?= htmlspecialchars($data['financial_year']) ?> | As of <?= htmlspecialchars($data['as_of_formatted']) ?></Data></Cell>
                    </Row>
                    <Row>
                        <Cell ss:StyleID="Subtitle"><Data ss:Type="String">Status: <?= $data['balanced'] ? 'BALANCED (Assets = Liabilities + Equity)' : 'OUT OF BALANCE: Diff ' . $data['difference_formatted'] ?></Data></Cell>
                    </Row>
                    <Row></Row>

                    <!-- Header Row -->
                    <Row ss:Height="22">
                        <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Account Name</Data></Cell>
                        <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Code</Data></Cell>
                        <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Current (INR)</Data></Cell>
                        <?php if ($hasCompare): ?>
                            <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Previous (INR)</Data></Cell>
                            <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Change (₹)</Data></Cell>
                            <Cell ss:StyleID="ColHeader"><Data ss:Type="String">Change (%)</Data></Cell>
                        <?php endif; ?>
                    </Row>

                    <!-- ASSETS -->
                    <Row ss:Height="20">
                        <Cell ss:StyleID="SectionHeader"><Data ss:Type="String">TOTAL ASSETS</Data></Cell>
                        <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                        <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                        <?php if ($hasCompare): ?>
                            <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                            <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                            <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                        <?php endif; ?>
                    </Row>

                    <?php foreach ($data['assets']['groups'] as $grp): ?>
                        <Row>
                            <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"><?= htmlspecialchars($grp['title']) ?></Data></Cell>
                            <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                            <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                            <?php if ($hasCompare): ?>
                                <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                                <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                                <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                            <?php endif; ?>
                        </Row>
                        <?php foreach ($grp['accounts'] as $acc): ?>
                            <Row>
                                <Cell ss:StyleID="TextLeft"><Data ss:Type="String">   <?= htmlspecialchars($acc['name']) ?></Data></Cell>
                                <Cell ss:StyleID="TextLeft"><Data ss:Type="String"><?= htmlspecialchars($acc['code']) ?></Data></Cell>
                                <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$acc['amount'] ?></Data></Cell>
                                <?php if ($hasCompare): ?>
                                    <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$acc['previous_amount'] ?></Data></Cell>
                                    <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$acc['change_amount'] ?></Data></Cell>
                                    <Cell ss:StyleID="TextLeft"><Data ss:Type="String"><?= sprintf('%.1f%%', (float)$acc['change_percent']) ?></Data></Cell>
                                <?php endif; ?>
                            </Row>
                        <?php endforeach; ?>
                        <Row>
                            <Cell ss:StyleID="SubtotalRow"><Data ss:Type="String">Subtotal <?= htmlspecialchars($grp['title']) ?></Data></Cell>
                            <Cell ss:StyleID="SubtotalRow"><Data ss:Type="String"></Data></Cell>
                            <Cell ss:StyleID="SubtotalRow"><Data ss:Type="Number"><?= (float)$grp['subtotal'] ?></Data></Cell>
                            <?php if ($hasCompare): ?>
                                <Cell ss:StyleID="SubtotalRow"><Data ss:Type="Number"><?= (float)$grp['previous_subtotal'] ?></Data></Cell>
                                <Cell ss:StyleID="SubtotalRow"><Data ss:Type="Number"><?= (float)$grp['change_amount'] ?></Data></Cell>
                                <Cell ss:StyleID="SubtotalRow"><Data ss:Type="String"><?= sprintf('%.1f%%', (float)$grp['change_percent']) ?></Data></Cell>
                            <?php endif; ?>
                        </Row>
                    <?php endforeach; ?>

                    <Row ss:Height="22">
                        <Cell ss:StyleID="TotalRow"><Data ss:Type="String">TOTAL ASSETS</Data></Cell>
                        <Cell ss:StyleID="TotalRow"><Data ss:Type="String"></Data></Cell>
                        <Cell ss:StyleID="TotalRow"><Data ss:Type="Number"><?= (float)$data['assets']['total'] ?></Data></Cell>
                        <?php if ($hasCompare): ?>
                            <Cell ss:StyleID="TotalRow"><Data ss:Type="Number"><?= (float)$data['assets']['previous_total'] ?></Data></Cell>
                            <Cell ss:StyleID="TotalRow"><Data ss:Type="Number"><?= (float)$data['assets']['change_amount'] ?></Data></Cell>
                            <Cell ss:StyleID="TotalRow"><Data ss:Type="String"><?= sprintf('%.1f%%', (float)$data['assets']['change_percent']) ?></Data></Cell>
                        <?php endif; ?>
                    </Row>
                    <Row></Row>

                    <!-- LIABILITIES & EQUITY -->
                    <Row ss:Height="20">
                        <Cell ss:StyleID="SectionHeader"><Data ss:Type="String">LIABILITIES &amp; EQUITY</Data></Cell>
                        <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                        <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                        <?php if ($hasCompare): ?>
                            <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                            <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                            <Cell ss:StyleID="SectionHeader"><Data ss:Type="String"></Data></Cell>
                        <?php endif; ?>
                    </Row>

                    <?php foreach ($data['liabilities_equity']['groups'] as $grp): ?>
                        <Row>
                            <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"><?= htmlspecialchars($grp['title']) ?></Data></Cell>
                            <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                            <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                            <?php if ($hasCompare): ?>
                                <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                                <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                                <Cell ss:StyleID="GroupHeader"><Data ss:Type="String"></Data></Cell>
                            <?php endif; ?>
                        </Row>
                        <?php foreach ($grp['accounts'] as $acc): ?>
                            <Row>
                                <Cell ss:StyleID="TextLeft"><Data ss:Type="String">   <?= htmlspecialchars($acc['name']) ?></Data></Cell>
                                <Cell ss:StyleID="TextLeft"><Data ss:Type="String"><?= htmlspecialchars($acc['code']) ?></Data></Cell>
                                <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$acc['amount'] ?></Data></Cell>
                                <?php if ($hasCompare): ?>
                                    <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$acc['previous_amount'] ?></Data></Cell>
                                    <Cell ss:StyleID="NumRight"><Data ss:Type="Number"><?= (float)$acc['change_amount'] ?></Data></Cell>
                                    <Cell ss:StyleID="TextLeft"><Data ss:Type="String"><?= sprintf('%.1f%%', (float)$acc['change_percent']) ?></Data></Cell>
                                <?php endif; ?>
                            </Row>
                        <?php endforeach; ?>
                        <Row>
                            <Cell ss:StyleID="SubtotalRow"><Data ss:Type="String">Subtotal <?= htmlspecialchars($grp['title']) ?></Data></Cell>
                            <Cell ss:StyleID="SubtotalRow"><Data ss:Type="String"></Data></Cell>
                            <Cell ss:StyleID="SubtotalRow"><Data ss:Type="Number"><?= (float)$grp['subtotal'] ?></Data></Cell>
                            <?php if ($hasCompare): ?>
                                <Cell ss:StyleID="SubtotalRow"><Data ss:Type="Number"><?= (float)$grp['previous_subtotal'] ?></Data></Cell>
                                <Cell ss:StyleID="SubtotalRow"><Data ss:Type="Number"><?= (float)$grp['change_amount'] ?></Data></Cell>
                                <Cell ss:StyleID="SubtotalRow"><Data ss:Type="String"><?= sprintf('%.1f%%', (float)$grp['change_percent']) ?></Data></Cell>
                            <?php endif; ?>
                        </Row>
                    <?php endforeach; ?>

                    <Row ss:Height="22">
                        <Cell ss:StyleID="TotalRow"><Data ss:Type="String">TOTAL LIABILITIES &amp; EQUITY</Data></Cell>
                        <Cell ss:StyleID="TotalRow"><Data ss:Type="String"></Data></Cell>
                        <Cell ss:StyleID="TotalRow"><Data ss:Type="Number"><?= (float)$data['liabilities_equity']['total'] ?></Data></Cell>
                        <?php if ($hasCompare): ?>
                            <Cell ss:StyleID="TotalRow"><Data ss:Type="Number"><?= (float)$data['liabilities_equity']['previous_total'] ?></Data></Cell>
                            <Cell ss:StyleID="TotalRow"><Data ss:Type="Number"><?= (float)$data['liabilities_equity']['change_amount'] ?></Data></Cell>
                            <Cell ss:StyleID="TotalRow"><Data ss:Type="String"><?= sprintf('%.1f%%', (float)$data['liabilities_equity']['change_percent']) ?></Data></Cell>
                        <?php endif; ?>
                    </Row>
                </Table>
            </Worksheet>
        </Workbook>
        <?php
        $xml = ob_get_clean();

        return new Response($xml, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Render clean, high-contrast, accountant-grade printable PDF view.
     */
    private function exportBalanceSheetPdf(array $data): Response
    {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Balance Sheet — <?= htmlspecialchars($data['company']['name']) ?> (<?= htmlspecialchars($data['as_of_formatted']) ?>)</title>
            <style>
                @page { size: A4 portrait; margin: 15mm 12mm 15mm 12mm; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #0f172a; background: #ffffff; margin: 0; padding: 20px; font-size: 11pt; line-height: 1.4; }
                .report-header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
                .company-name { font-size: 18pt; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; }
                .report-meta { font-size: 9.5pt; color: #475569; margin-top: 4px; }
                .report-title { font-size: 15pt; font-weight: 700; color: #1e3a8a; margin: 10px 0 4px 0; letter-spacing: 1px; }
                .report-asof { font-size: 10.5pt; font-weight: 600; color: #0f172a; }
                .badge-status { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 8.5pt; font-weight: 700; margin-top: 6px; }
                .badge-balanced { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
                .badge-diff { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
                .statement-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; page-break-inside: avoid; }
                .statement-table th { background: #f1f5f9; color: #0f172a; font-size: 9pt; font-weight: 700; text-transform: uppercase; border-top: 1px solid #cbd5e1; border-bottom: 2px solid #0f172a; padding: 6px 10px; }
                .statement-table td { padding: 6px 10px; border-bottom: 1px solid #e2e8f0; font-size: 10pt; }
                .section-banner { background: #0f172a; color: #ffffff; font-weight: 700; font-size: 10.5pt; padding: 6px 10px; border-top: 1px solid #0f172a; }
                .group-row { background: #f8fafc; font-weight: 600; color: #334155; font-size: 9.5pt; text-transform: uppercase; }
                .amount-col { text-align: right; font-variant-numeric: tabular-nums; font-family: 'Courier New', Courier, monospace; }
                .subtotal-row td { font-weight: 700; border-top: 1px solid #94a3b8; border-bottom: 1px solid #94a3b8; background: #f8fafc; }
                .total-row td { font-weight: 700; font-size: 11.5pt; border-top: 2px solid #0f172a; border-bottom: 3px double #0f172a; background: #f1f5f9; color: #0f172a; }
                .footer-meta { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 8.5pt; color: #64748b; display: flex; justify-content: space-between; }
                .print-actions { margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 10px; }
                .print-btn { background: #2563eb; color: #ffffff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; }
                @media print { .print-actions { display: none !important; } }
            </style>
        </head>
        <body onload="window.print()">
            <div class="print-actions">
                <button class="print-btn" onclick="window.print()">Print Statement</button>
            </div>

            <div class="report-header">
                <div class="company-name"><?= htmlspecialchars($data['company']['name']) ?></div>
                <div class="report-meta">GSTIN: <?= htmlspecialchars($data['company']['gstin']) ?> &bull; <?= htmlspecialchars($data['financial_year']) ?> &bull; Branch: <?= htmlspecialchars($data['branch']['name']) ?></div>
                <div class="report-title">BALANCE SHEET STATEMENT</div>
                <div class="report-asof">As of <?= htmlspecialchars($data['as_of_formatted']) ?></div>
                <?php if ($data['balanced']): ?>
                    <span class="badge-status badge-balanced">&check; BALANCED &bull; Assets = Liabilities + Equity</span>
                <?php else: ?>
                    <span class="badge-status badge-diff">&times; UNBALANCED &bull; Difference: <?= htmlspecialchars($data['difference_formatted']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Two-Column Statement Layout for Print -->
            <table style="width: 100%; border-collapse: separate; border-spacing: 15px 0;">
                <tr style="vertical-align: top;">
                    <!-- Left: Assets -->
                    <td style="width: 50%;">
                        <table class="statement-table">
                            <thead>
                                <tr>
                                    <th colspan="2" class="section-banner">TOTAL ASSETS</th>
                                </tr>
                                <tr>
                                    <th>Particulars</th>
                                    <th style="text-align: right;">Amount (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['assets']['groups'] as $grp): ?>
                                    <tr class="group-row">
                                        <td colspan="2"><?= htmlspecialchars($grp['title']) ?></td>
                                    </tr>
                                    <?php foreach ($grp['accounts'] as $acc): ?>
                                        <tr>
                                            <td style="padding-left: 20px;"><?= htmlspecialchars($acc['name']) ?></td>
                                            <td class="amount-col"><?= htmlspecialchars($acc['amount_formatted']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="subtotal-row">
                                        <td>Subtotal <?= htmlspecialchars($grp['title']) ?></td>
                                        <td class="amount-col"><?= $this->balanceSheetService->formatCurrency((float)$grp['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td>TOTAL ASSETS</td>
                                    <td class="amount-col"><?= htmlspecialchars($data['assets']['total_formatted']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </td>

                    <!-- Right: Liabilities & Equity -->
                    <td style="width: 50%;">
                        <table class="statement-table">
                            <thead>
                                <tr>
                                    <th colspan="2" class="section-banner">LIABILITIES &amp; EQUITY</th>
                                </tr>
                                <tr>
                                    <th>Particulars</th>
                                    <th style="text-align: right;">Amount (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['liabilities_equity']['groups'] as $grp): ?>
                                    <tr class="group-row">
                                        <td colspan="2"><?= htmlspecialchars($grp['title']) ?></td>
                                    </tr>
                                    <?php foreach ($grp['accounts'] as $acc): ?>
                                        <tr>
                                            <td style="padding-left: 20px;"><?= htmlspecialchars($acc['name']) ?></td>
                                            <td class="amount-col"><?= htmlspecialchars($acc['amount_formatted']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="subtotal-row">
                                        <td>Subtotal <?= htmlspecialchars($grp['title']) ?></td>
                                        <td class="amount-col"><?= $this->balanceSheetService->formatCurrency((float)$grp['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td>TOTAL LIABILITIES &amp; EQUITY</td>
                                    <td class="amount-col"><?= htmlspecialchars($data['liabilities_equity']['total_formatted']) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>

            <div class="footer-meta">
                <div>Generated by WTSBill ERP &bull; Verified Double-Entry Ledger</div>
                <div>Statement Timestamp: <?= htmlspecialchars($data['generated_at']) ?></div>
            </div>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
