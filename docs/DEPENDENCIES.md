# WTSBill ERP — Dependencies & External Integrations

## 1. Backend PHP Dependencies (Composer)

| Package | Version | Purpose |
| :--- | :--- | :--- |
| `php` | `>=8.0` | Target PHP Runtime |
| `illuminate/database` | `^10.0` / `^9.0` | Eloquent ORM & Query Builder |
| `illuminate/events` | `^10.0` / `^9.0` | Event Dispatcher for Model Hooks |
| `illuminate/container`| `^10.0` / `^9.0` | Dependency Injection Container |

## 2. Core PHP Runtime Extensions Required

* `pdo` & `pdo_mysql` — Database connectivity
* `bcmath` — Arbitrary precision mathematics for financial rounding
* `mbstring` — Multibyte string processing
* `openssl` — CSRF & token cryptography
* `json` — REST API payload encoding/decoding
* `session` — Session state management
* `curl` — External API and payment gateway communications

## 3. Frontend Static Assets

* **Vanilla JavaScript:** `public/assets/js/app.js` (23.7 KB)
* **Responsive CSS:** `public/assets/css/app.css` (44.6 KB)
* **Typography:** Inter Font (deferred load)
* **Icons:** Font Awesome 6.4.0 (CDN / Local fallbacks)
