# WTSBill ERP — Static Asset Usage Audit

**Generated Date:** October 8, 2026

## 1. Asset Inventory & Verification

| Asset Path | Type | Size | References Found | Status |
| :--- | :--- | :--- | :--- | :--- |
| `public/assets/css/app.css` | CSS | 44,659 B | `header.php`, `login.php`, test suites | **ACTIVE & IN USE** |
| `public/assets/js/app.js` | JS | 23,736 B | `footer.php`, `header.php`, test suites | **ACTIVE & IN USE** |
| `public/assets/img/logo.png` | Image | 12,480 B | `header.php`, `login.php`, `navbar.php` | **ACTIVE & IN USE** |
| `public/assets/images/logo.png`| Image | 12,480 B | Canonical Image Path | **ACTIVE & IN USE** |

## 2. Result
Zero orphan or unused static assets detected. All static assets are actively referenced in header, navbar, login, or printable invoice views.
