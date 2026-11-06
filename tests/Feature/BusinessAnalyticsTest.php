<?php

declare(strict_types=1);

namespace Tests\Feature;

use WTSBill\Core\Database;
use WTSBill\Core\Request;
use WTSBill\Core\Router;
use WTSBill\Core\Session;
use WTSBill\Services\AnalyticsService;
use WTSBill\Services\AuthService;

/**
 * Feature Test Suite for Phase 9: Business Analytics & Executive BI Dashboard.
 * Verifies Executive KPIs, Gross and Net margin calculations, 6-month time-series trends,
 * top selling products, top customer accounts, and CSV stream exports.
 */
class BusinessAnalyticsTest
{
    public static function run(callable $assert): void
    {
        $authService = new AuthService();
        Session::start();

        // Load web routes
        $router = new Router();
        require ROOT_PATH . '/routes/web.php';

        // 1. BI-01: Guest redirect
        Session::destroy();
        Session::start();
        $guestReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/analytics/bi']);
        $guestRes = $router->dispatch($guestReq);
        $assert('BI-01', 'Unauthenticated request to /analytics/bi redirects to /login (HTTP 302)',
            $guestRes->getStatusCode() === 302 && $guestRes->getHeader('Location') === '/login'
        );

        // Authenticate as Admin
        $authService->authenticate('anil.d@wtsbill.in', 'password123');
        $companyId = (int)(Session::getCompanyId() ?? 1);

        $analyticsService = new AnalyticsService();

        // 2. BI-02: Executive BI HTML View
        $biReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/analytics/bi']);
        $biRes = $router->dispatch($biReq);
        $biBody = $biRes->getBody();
        $assert('BI-02', 'GET /analytics/bi returns HTTP 200 with KPI cards and executive visual trends',
            $biRes->getStatusCode() === 200 &&
            str_contains($biBody, 'Executive BI &amp; Analytics') &&
            str_contains($biBody, 'Total Invoiced Revenue') &&
            str_contains($biBody, 'Gross Profit Margin')
        );

        // 3. BI-03: Profitability Calculation Integrity
        $dash = $analyticsService->getExecutiveDashboard($companyId);
        $m = $dash['metrics'] ?? [];
        $taxable = (float)($m['taxable_revenue'] ?? 0);
        $cogs = (float)($m['cogs_amount'] ?? 0);
        $gp = (float)($m['gross_profit'] ?? 0);
        $exp = (float)($m['operating_expenses'] ?? 0);
        $np = (float)($m['net_profit'] ?? 0);

        $gpExpected = round($taxable - $cogs, 2);
        $npExpected = round($gp - $exp, 2);
        $assert('BI-03', 'Gross and Net Profit equations hold: Taxable - COGS == GP, and GP - Expenses == NP',
            abs($gp - $gpExpected) < 0.01 && abs($np - $npExpected) < 0.01
        );

        // 4. BI-04: Margin Percentage Equations
        $gpMargin = (float)($m['gross_margin_pct'] ?? 0);
        $npMargin = (float)($m['net_margin_pct'] ?? 0);
        $expectedGpMargin = $taxable > 0 ? round(($gp / $taxable) * 100, 2) : 0.0;
        $expectedNpMargin = $taxable > 0 ? round(($np / $taxable) * 100, 2) : 0.0;
        $assert('BI-04', 'Gross and Net margin percentages are mathematically consistent with taxable sales base',
            abs($gpMargin - $expectedGpMargin) < 0.05 && abs($npMargin - $expectedNpMargin) < 0.05
        );

        // 5. BI-05: Executive BI JSON API
        $apiReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/analytics/bi']);
        $apiRes = $router->dispatch($apiReq);
        $apiJson = json_decode($apiRes->getBody(), true);
        $assert('BI-05', 'GET /api/analytics/bi returns structured JSON with success flag and executive data blocks',
            $apiRes->getStatusCode() === 200 &&
            isset($apiJson['success']) &&
            $apiJson['success'] === true &&
            isset($apiJson['data']['metrics']['total_revenue']) &&
            isset($apiJson['data']['trends'])
        );

        // 6. BI-06: Executive BI CSV Export
        $exportReq = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/analytics/bi/export']);
        $exportRes = $router->dispatch($exportReq);
        $csvContent = $exportRes->getBody();
        $assert('BI-06', 'GET /analytics/bi/export returns text/csv stream with executive KPIs and rankings',
            $exportRes->getStatusCode() === 200 &&
            str_contains($exportRes->getHeader('Content-Type') ?? '', 'text/csv') &&
            str_contains($csvContent, 'EXECUTIVE BI ANALYTICS SUMMARY') &&
            str_contains($csvContent, 'Total Invoiced Revenue') &&
            str_contains($csvContent, 'TOP 5 SELLING PRODUCTS')
        );

        // 7. BI-07: Top Selling Products Hierarchy
        $topProducts = $dash['top_products'] ?? [];
        $isSortedDesc = true;
        for ($i = 0; $i < count($topProducts) - 1; $i++) {
            if ((float)$topProducts[$i]['gross_sales'] < (float)$topProducts[$i + 1]['gross_sales']) {
                $isSortedDesc = false;
                break;
            }
        }
        $assert('BI-07', 'Top selling products list is ranked by gross sales turnover in descending order',
            is_array($topProducts) && $isSortedDesc
        );

        // 8. BI-08: Top Customer Accounts Volume
        $topCust = $dash['top_customers'] ?? [];
        $assert('BI-08', 'Top customer accounts analysis structures customer name, invoice counts, and billed turnover',
            is_array($topCust) && (empty($topCust) || isset($topCust[0]['total_billed']))
        );

        // 9. BI-09: 6-Month Consecutive Time Series
        $trends = $dash['trends'] ?? [];
        $assert('BI-09', 'Monthly time-series provides exactly 6 consecutive data points with revenue and expense',
            count($trends) === 6 && isset($trends[0]['revenue']) && isset($trends[0]['expense'])
        );

        // 10. BI-10: Branch Performance Benchmarks
        $branchPerf = $dash['branches'] ?? [];
        $assert('BI-10', 'Branch performance benchmarks active branches with invoice volume and sales attribution',
            is_array($branchPerf) && !empty($branchPerf) && isset($branchPerf[0]['total_sales'])
        );
    }
}

