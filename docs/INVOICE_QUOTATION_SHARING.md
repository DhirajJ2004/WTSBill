# WTSBill ERP – Invoice & Quotation Sharing Architecture

## 1. System Overview

The Invoice & Quotation Sharing System provides enterprise-grade document dispatch directly from WTSBill ERP. Users can instantly share GST Tax Invoices and Quotations with customers via **WhatsApp** and **Email** in PDF format, complete with company branding, tax calculations, payment details, dynamic UPI QR codes, and terms & conditions.

The system supports multiple delivery modalities:
- **WhatsApp Web Share API (Method A)**: Direct file attachment on supported mobile devices and modern desktop browsers.
- **WhatsApp Business Cloud API (Method B)**: Server-side document dispatch directly to customer WhatsApp accounts via Meta's Graph API.
- **WhatsApp Click-to-Chat Fallback**: Automated PDF download combined with prefilled WhatsApp messaging when APIs are unconfigured.
- **Direct SMTP Email Delivery**: Native socket client for secure TLS/SSL SMTP transmission with PDF attachments, CC recipients, and real provider status reporting.

---

## 2. Architecture & Components

```
                                  +------------------------------+
                                  |   User Action (UI Buttons)   |
                                  | - Invoice List / Print View  |
                                  | - Quotation List / Print View|
                                  +--------------+---------------+
                                                 |
                                                 v
                                  +------------------------------+
                                  |   Shared Dialog Modal        |
                                  | (views/partials/share_modal) |
                                  +--------------+---------------+
                                                 |
                   +-----------------------------+-----------------------------+
                   |                                                           |
                   v                                                           v
   +-------------------------------+                           +-------------------------------+
   |      WhatsApp Dispatch        |                           |       Email Dispatch          |
   | - Method A: Web Share API     |                           | - Custom SMTP Socket Client   |
   | - Method B: Meta Cloud API    |                           | - Native mail() Fallback      |
   | - Fallback: wa.me + PDF dl    |                           | - Multipart MIME PDF Attach   |
   +---------------+---------------+                           +---------------+---------------+
                   |                                                           |
                   +-----------------------------+-----------------------------+
                                                 |
                                                 v
                                  +------------------------------+
                                  |   PdfGenerator & Branding   |
                                  | - Real Saved Document Data   |
                                  | - Centralized Logo/Sig/Stamp |
                                  | - Standardized PDF Filename  |
                                  +--------------+---------------+
                                                 |
                                                 v
                                  +------------------------------+
                                  | Audit & Communication History|
                                  | (communication_messages)     |
                                  +------------------------------+
```

### Core Files
| File Path | Role |
| :--- | :--- |
| `app/Services/CommunicationService.php` | Delivery engine, SMTP socket client, Meta API client, message formatting, audit logger |
| `app/Controllers/CommunicationController.php` | HTTP endpoints for invoice/quotation sharing, history retrieval, test dispatches |
| `app/Services/PdfGenerator.php` | Native PDF-1.4 binary engine generating branded invoices and quotations |
| `views/partials/share_modal.php` | Universal sharing modal with preview, WhatsApp, Email, and History tabs |
| `views/settings/index.php` | Communication & Sharing configuration panel (SMTP, WhatsApp API, message templates) |
| `tests/Feature/CommunicationSharingTest.php` | Automated test suite covering COMM-01 through COMM-20 |

---

## 3. UI Entry Points & Workflow

### 1. Invoice Listing (`/sales/invoices`)
- Each row in the invoice table features a **Share** action button alongside View, Edit, and Print.
- Clicking **Share** opens the modal preloaded with the invoice number, customer name, phone, email, and grand total.

### 2. Quotation Listing (`/sales/quotations`)
- Quotation table includes a dedicated **Share** button in the actions column.
- Automatically populates quotation ID, validity date, and customer contact parameters.

### 3. Invoice & Quotation Print Previews (`/sales/invoices/{id}/print`, `/sales/quotations/{id}/print`)
- Top action bar includes:
  - **Share (WhatsApp / Email)** button (forest green primary styling)
  - **Download PDF** button for direct authenticated PDF file streaming
  - Preserves existing print, back, and email customer links

---

## 4. WhatsApp Sharing Modalities

### Method A — Direct File Sharing (Web Share API)
- Detects `navigator.canShare && navigator.canShare({ files: [file] })`.
- Fetches the generated PDF blob directly from the secure endpoint `/communication/pdf-stream`.
- Constructs a native `File` object (`Invoice_INV-00125.pdf` or `Quotation_QUO-00125.pdf`).
- Invokes `navigator.share({ files: [file], title, text })` to launch WhatsApp with the PDF pre-attached.

### Method B — WhatsApp Business Cloud API
- Triggered when `whatsapp_enabled` is active and provider credentials exist (`whatsapp_token`, `whatsapp_phone_number_id`).
- Securely sends a document message through Meta's Graph API (`v18.0/{phone-number-id}/messages`).
- Passes a signed HMAC document URL accessible by Meta's webhook scrapers.
- Audit log records provider message ID and actual delivery status.

### Fallback — Download + Click-to-Chat
- If browser lacks Web Share API support and Cloud API is unconfigured:
  1. Triggers automatic download of the PDF document.
  2. Opens WhatsApp Web or native app via `https://wa.me/{phone}?text={encoded_message}`.
  3. Records audit log status strictly as `CLICK_TO_CHAT_GENERATED` (never falsely claiming `DELIVERED`).

---

## 5. Email PDF Delivery

- **Recipient Management**: Automatically pre-fills customer email; allows editing, CC recipient specification, custom subject, and custom message body.
- **Subject Conventions**:
  - `Invoice {invoice_number} from {company_name}`
  - `Quotation {quotation_number} from {company_name}`
- **PDF Attachment**: Automatically generates and attaches `Invoice_{invoice_number}.pdf` or `Quotation_{quotation_number}.pdf` using standard Base64 `multipart/mixed` MIME formatting.
- **Provider Accuracy**: Uses direct SMTP socket connection with TLS/SSL encryption. Parses actual SMTP response codes (`250 2.0.0 OK`). If SMTP fails or is unconfigured, cleanly attempts PHP `mail()` and logs exact error messages.

---

## 6. Security & Audit Logging

- **Anti-Tampering HMAC Tokens**: Public PDF download links use HMAC SHA-256 tokens (`token = hash_hmac('sha256', "invoice:{$id}:{$timestamp}", APP_KEY)`). Expired or mismatched tokens are rejected with HTTP 403.
- **Role-Based Access Control**: Sharing endpoints enforce `Gate::can('invoices.view')` or `Gate::can('quotations.view')`.
- **Sensitive Credential Isolation**: SMTP passwords and Meta access tokens are never transmitted to the browser DOM or JavaScript contexts.
- **Communication Audit Trail**: All transmissions are recorded in `communication_messages` with timestamp, channel, recipient, status, and error details.

