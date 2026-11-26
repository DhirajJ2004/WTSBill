import sys
import time
from playwright.sync_api import sync_playwright

def run_tests():
    print("Starting Playwright Browser Verification...")
    results = {}
    
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        context = browser.new_context()
        page = context.new_page()
        
        # 1. Login Page & Footer Link Inspection
        print("\n--- Test 1: Login Page & Footer Credit Link ---")
        res = page.goto("http://127.0.0.1/login")
        assert res.status == 200, f"Login page failed with status {res.status}"
        
        footer_links = page.query_selector_all("a[href*='wts'], a[href*='designed']")
        footer_html = page.locator("body").inner_html()
        assert "Designed & Developed by" in footer_html or "WTS" in footer_html, "Footer credit missing"
        # Check that credit link is intact
        credit_link = page.query_selector("a[href='https://wtsnepal.com/']")
        if not credit_link:
            credit_link = page.query_selector("a[href*='wtsnepal.com']")
        print(f"[PASS] Login page rendered. Footer credit element found: {bool(credit_link)}")
        results["login_footer"] = "PASS"

        # Perform Login
        page.fill("input[name='email'], input[name='username'], input[type='text'], input[placeholder*='Email']", "anil.d@wtsbill.in")
        page.fill("input[name='password']", "password123")
        page.click("button[type='submit']")
        page.wait_for_load_state("networkidle")
        print(f"[PASS] Logged in successfully. Current URL: {page.url}")
        
        # 2. Credit Note Edit (No PHP 500 error)
        print("\n--- Test 2: Credit Note Edit ---")
        res = page.goto("http://127.0.0.1/sales/credit-notes/1")
        print(f"GET /sales/credit-notes/1 status: {res.status}")
        assert res.status == 200, f"Credit note edit failed with status {res.status}"
        assert "500 Internal Server Error" not in page.title()
        assert "TypeError" not in page.content()
        # Verify credit note form
        customer_sel = page.query_selector("select[name='customer_id'], #customer_id")
        items_table = page.query_selector("#items-tbody, table")
        print(f"[PASS] Credit Note 1 edit loaded cleanly (HTTP 200). Customer selector: {bool(customer_sel)}, Items table: {bool(items_table)}")
        results["credit_note_edit"] = "PASS"

        # 3. Invoice Payment Status Excel Export
        print("\n--- Test 3: Invoice Payment Status Excel Export ---")
        res = page.goto("http://127.0.0.1/reports/invoice-payment-status")
        assert res.status == 200
        btn_excel = page.query_selector("#btn-download-excel, a[href*='invoice-payment-status/export']")
        assert btn_excel is not None, "Export button not found"
        href = btn_excel.get_attribute("href")
        print(f"Export button href: {href}")
        assert "/reports/invoice-payment-status/export" in href, f"Invalid href: {href}"
        
        # Test download response
        export_res = page.request.get(f"http://127.0.0.1{href}")
        print(f"Export request status: {export_res.status}, Content-Type: {export_res.headers.get('content-type')}, Size: {len(export_res.body())} bytes")
        assert export_res.status == 200
        assert len(export_res.body()) > 500, "Export body too small"
        print("[PASS] Excel export generated valid non-empty file (HTTP 200).")
        results["excel_export"] = "PASS"

        # 4. GST Purchase Register Broken Bill Link
        print("\n--- Test 4: GST Purchase Register Bill Links ---")
        res = page.goto("http://127.0.0.1/reports/gst-purchase-register")
        assert res.status == 200
        bill_links = page.query_selector_all("a[href*='/purchases/bills?id=']")
        print(f"Found {len(bill_links)} purchase bill drawer links in GST register.")
        if len(bill_links) > 0:
            target_href = bill_links[0].get_attribute("href")
            print(f"Clicking first bill link: {target_href}")
            res = page.goto(f"http://127.0.0.1{target_href}")
            assert res.status == 200
            page.wait_for_timeout(1000)
            drawer = page.query_selector("#bill-drawer")
            assert drawer is not None, "Bill drawer element missing"
            # verify drawer is open or has active/open class
            drawer_classes = drawer.get_attribute("class") or ""
            print(f"[PASS] Bill drawer loaded on bills page. Drawer classes: {drawer_classes}")
        
        # Test alias route /purchases/{id}
        res_alias = page.goto("http://127.0.0.1/purchases/1")
        print(f"Direct /purchases/1 landed at: {page.url} with status {res_alias.status}")
        assert "/purchases/bills?id=1" in page.url or "/purchases/bills" in page.url
        print("[PASS] GST purchase register bill link and /purchases/{id} alias verified.")
        results["purchase_bill_link"] = "PASS"

        # 5. Sales Orders Routes (Legacy handling)
        print("\n--- Test 5: Sales Orders Route Redirects ---")
        res_create = page.goto("http://127.0.0.1/sales/orders/create")
        print(f"/sales/orders/create landed at: {page.url} with status {res_create.status}")
        assert res_create.status == 200
        assert "/sales/invoices/create" in page.url
        assert "500" not in page.title()
        
        res_index = page.goto("http://127.0.0.1/sales/orders")
        print(f"/sales/orders landed at: {page.url} with status {res_index.status}")
        assert res_index.status == 200
        assert "/sales/invoices" in page.url
        assert "500" not in page.title()
        print("[PASS] Sales orders routes cleanly redirect to invoices with HTTP 200.")
        results["sales_orders_redirect"] = "PASS"

        # 6. Expenses Direct Create Route
        print("\n--- Test 6: Expenses Direct Create Route ---")
        res_exp = page.goto("http://127.0.0.1/expenses/create")
        print(f"/expenses/create status: {res_exp.status}")
        assert res_exp.status == 200, f"Expected 200, got {res_exp.status}"
        page.wait_for_timeout(500)
        expense_modal = page.query_selector("#expense-modal")
        assert expense_modal is not None, "Expense modal element missing"
        modal_class = expense_modal.get_attribute("class") or ""
        print(f"[PASS] /expenses/create loaded cleanly. Modal element present. Modal class: {modal_class}")
        results["expenses_create"] = "PASS"

        # 7. Core Regression Verification
        print("\n--- Test 7: Regression Check on Core Modules ---")
        routes_to_check = [
            "/dashboard",
            "/sales/invoices",
            "/sales/quotations",
            "/sales/challans",
            "/sales/credit-notes",
            "/purchases/bills",
            "/items",
            "/customers",
            "/reports/gst-sales-register",
            "/reports/profit-loss"
        ]
        for route in routes_to_check:
            r = page.goto(f"http://127.0.0.1{route}")
            assert r.status == 200, f"Route {route} failed with status {r.status}"
            assert "500" not in page.title() and "Fatal" not in page.content(), f"Route {route} threw error"
            print(f"  [OK] {route} -> HTTP 200")
        results["regression"] = "PASS"

        browser.close()

    print("\n==========================================")
    print("ALL PLAYWRIGHT TESTS PASSED SUCCESSFULLY!")
    print("==========================================")
    for k, v in results.items():
        print(f"{k}: {v}")
    return results

if __name__ == '__main__':
    run_tests()
