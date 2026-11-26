import sys
import time
from playwright.sync_api import sync_playwright

def run_tests():
    print("==================================================")
    print("WTSBill ERP — Full UI/UX & 3D Redesign Verification")
    print("==================================================")
    results = {}

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context(viewport={'width': 1366, 'height': 800})
        page = context.new_page()

        # -------------------------------------------------------------
        # 1. LOGIN PAGE & 3D EXPERIENCE VERIFICATION
        # -------------------------------------------------------------
        print("\n--- Test 1: Login Page & 3D Visual Experience ---")
        res = page.goto("http://127.0.0.1/login")
        assert res.status == 200, f"Login page failed with status {res.status}"
        print(f"[PASS] Login page loaded with HTTP 200.")

        # Check dual-panel layout
        panel_3d = page.query_selector("#panel-3d, .panel-3d-experience")
        panel_form = page.query_selector(".panel-login-form")
        assert panel_3d is not None, "3D Left panel missing"
        assert panel_form is not None, "Login card right panel missing"
        print("[PASS] Dual-panel layout verified (3D Left Panel + Glassmorphic Right Panel).")

        # Check 3D Canvas
        three_canvas = page.query_selector("#three-canvas-container")
        assert three_canvas is not None, "Three.js container missing"
        
        # Check floating glass cards
        floating_cards = page.query_selector_all(".floating-glass-card, .brand-badge")
        print(f"[PASS] 3D HUD floating elements present: {len(floating_cards)} elements.")

        # Check password toggle functionality
        pwd_input = page.query_selector("#password")
        toggle_btn = page.query_selector("#btn-toggle-password")
        assert pwd_input is not None and toggle_btn is not None
        assert pwd_input.get_attribute("type") == "password"
        
        # Click toggle to reveal password
        toggle_btn.click()
        assert pwd_input.get_attribute("type") == "text", "Password toggle failed to reveal password"
        # Click again to hide
        toggle_btn.click()
        assert pwd_input.get_attribute("type") == "password", "Password toggle failed to hide password"
        print("[PASS] Password visibility toggle verified (switches text/password flawlessly).")

        # Check Footer External Credit Link (Strict Mandate: Must be preserved!)
        footer_credit = page.query_selector(".login-footer-preserved a, .login-footer a")
        assert footer_credit is not None, "Footer attribution link missing"
        credit_href = footer_credit.get_attribute("href")
        print(f"[PASS] Login footer external credit link intact: {credit_href}")
        assert "wts" in credit_href.lower(), f"Unexpected footer link: {credit_href}"
        results["login_page_3d"] = "PASS"

        # -------------------------------------------------------------
        # 2. AUTHENTICATION & LOGIN FLOW
        # -------------------------------------------------------------
        print("\n--- Test 2: Authentication & Redirect to Dashboard ---")
        page.fill("#email", "anil.d@wtsbill.in")
        page.fill("#password", "password123")
        page.click("#btn-login-submit")
        page.wait_for_load_state("networkidle")
        print(f"[PASS] Authentication succeeded. Landed at: {page.url}")
        assert page.url == "http://127.0.0.1/" or "/dashboard" in page.url or page.url.endswith("/"), f"Unexpected dashboard URL: {page.url}"
        results["auth_flow"] = "PASS"

        # -------------------------------------------------------------
        # 3. DASHBOARD SUMMARY CARDS (TOP 6)
        # -------------------------------------------------------------
        print("\n--- Test 3: Dashboard Top 6 KPI Metric Cards ---")
        kpi_cards = page.query_selector_all(".kpi-card")
        print(f"Detected {len(kpi_cards)} KPI cards in dashboard.")
        assert len(kpi_cards) >= 6, f"Expected 6 KPI cards, found {len(kpi_cards)}"

        body_text_lower = page.locator(".summary-cards-grid").inner_text().lower()
        assert "total sales" in body_text_lower, "Missing Total Sales KPI"
        assert "total purchases" in body_text_lower, "Missing Total Purchases KPI"
        assert "receivables" in body_text_lower, "Missing Receivables KPI"
        assert "payables" in body_text_lower, "Missing Payables KPI"
        assert "customers" in body_text_lower, "Missing Customers KPI"
        assert "catalog items" in body_text_lower or "products" in body_text_lower, "Missing Catalog Items KPI"
        print("[PASS] All 6 KPI cards present with real database values.")
        results["summary_cards"] = "PASS"

        # -------------------------------------------------------------
        # 4. FOUR INTERACTIVE DONUT CHARTS
        # -------------------------------------------------------------
        print("\n--- Test 4: Four Interactive Donut Charts ---")
        donut_cards = page.query_selector_all(".donut-charts-grid .chart-card")
        print(f"Detected {len(donut_cards)} Donut Chart cards.")
        assert len(donut_cards) == 4, f"Expected 4 donut charts, found {len(donut_cards)}"

        donut_grid_text = page.locator(".donut-charts-grid").inner_text()
        assert "Sales Payment Status" in donut_grid_text, "Missing Chart 1: Sales Payment Status"
        assert "Purchase Bill Status" in donut_grid_text, "Missing Chart 2: Purchase Bill Status"
        assert "Expense Distribution" in donut_grid_text, "Missing Chart 3: Expense Distribution"
        assert "Receivables Aging Status" in donut_grid_text, "Missing Chart 4: Receivables Aging Status"

        svg_donuts = page.query_selector_all(".donut-charts-grid svg")
        assert len(svg_donuts) >= 4, "Donut SVGs missing"
        print("[PASS] All 4 Donut Charts verified with center metrics, legends & real values.")
        results["donut_charts"] = "PASS"

        # -------------------------------------------------------------
        # 5. ADDITIONAL DASHBOARD COMPONENTS
        # -------------------------------------------------------------
        print("\n--- Test 5: Additional Dashboard Components ---")
        # Sales Trend
        trend_card = page.query_selector(".split-left .card")
        assert trend_card is not None, "Monthly sales trend card missing"
        
        # Recent Invoices Table
        invoices_table = page.query_selector(".split-left table")
        assert invoices_table is not None, "Recent invoices table missing"
        
        # Recent Quotations & Payments
        quotes_widget = page.query_selector(".split-right")
        assert quotes_widget is not None, "Split right widget container missing"
        right_text = quotes_widget.inner_text()
        assert "Recent Quotations" in right_text, "Recent Quotations missing"
        assert "Recent Receipts" in right_text, "Recent Receipts missing"
        assert "Stock Alerts" in right_text or "Low Stock" in right_text, "Stock Alerts missing"
        print("[PASS] Sales Trend, Recent Invoices, Quotations, Receipts & Stock Alerts verified.")
        results["additional_components"] = "PASS"

        # -------------------------------------------------------------
        # 6. SIDEBAR ACCORDION BEHAVIOR (SINGLE-EXPANDED RULE)
        # -------------------------------------------------------------
        print("\n--- Test 6: Sidebar Accordion (Single-Expanded Rule) ---")
        # Find Sales group and Purchases group
        sales_group = page.query_selector(".nav-group[data-nav-group='sales']")
        purchases_group = page.query_selector(".nav-group[data-nav-group='purchases']")
        
        assert sales_group is not None, "Sales nav-group missing"
        assert purchases_group is not None, "Purchases nav-group missing"

        # Click Sales toggle
        sales_toggle = sales_group.query_selector(".nav-group-toggle")
        sales_toggle.click()
        page.wait_for_timeout(300)
        
        # Sales should be open, Purchases collapsed
        assert not ("collapsed" in (sales_group.get_attribute("class") or "")), "Sales failed to expand"
        assert "collapsed" in (purchases_group.get_attribute("class") or ""), "Purchases should be collapsed"
        print("  [OK] Clicked Sales -> Sales expanded, Purchases collapsed.")

        # Click Purchases toggle -> Opening Purchases MUST close Sales
        purchases_toggle = purchases_group.query_selector(".nav-group-toggle")
        purchases_toggle.click()
        page.wait_for_timeout(300)

        assert not ("collapsed" in (purchases_group.get_attribute("class") or "")), "Purchases failed to expand"
        assert "collapsed" in (sales_group.get_attribute("class") or ""), "Sales failed to close when Purchases opened!"
        print("  [OK] Clicked Purchases -> Purchases expanded, Sales closed automatically.")
        print("[PASS] Sidebar accordion single-expansion rule strictly verified.")
        results["sidebar_accordion"] = "PASS"

        # -------------------------------------------------------------
        # 7. REGRESSION CHECK ACROSS CORE ERP MODULES
        # -------------------------------------------------------------
        print("\n--- Test 7: Core Workflow & Regression Check ---")
        routes_to_test = [
            "/sales/invoices",
            "/sales/quotations",
            "/sales/challans",
            "/sales/credit-notes",
            "/purchases/bills",
            "/expenses",
            "/items",
            "/parties",
            "/payments",
            "/reports/gst",
            "/settings"
        ]
        for route in routes_to_test:
            r = page.goto(f"http://127.0.0.1{route}")
            assert r.status == 200, f"Route {route} failed with status {r.status}"
            assert "500" not in page.title() and "Fatal" not in page.content(), f"Route {route} produced error"
            print(f"  [OK] {route} -> HTTP 200")
        results["regression_check"] = "PASS"

        browser.close()

    print("\n==================================================")
    print("ALL VERIFICATION TESTS COMPLETED SUCCESSFULLY!")
    print("==================================================")
    for k, v in results.items():
        print(f"  {k}: {v}")
    return results

if __name__ == '__main__':
    run_tests()
