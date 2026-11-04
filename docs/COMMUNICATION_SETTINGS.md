# WTSBill ERP — WhatsApp & Email Communication Settings Guide

**Document Version:** 1.0.0  
**Effective Date:** 2026-11-04  
**Author:** Lead Communications & Integrations Architect  
**Applies To:** Document Dispatch (Invoices, Quotations) in WTSBill ERP  

---

## 1. Overview

WTSBill ERP provides an enterprise multi-channel document distribution engine enabling businesses to deliver Tax Invoices and Quotations directly to customers via **WhatsApp** and **Email**.

---

## 2. Default Channel Configuration

Administrators can configure default delivery preferences globally under **Settings > Communication Settings** (`/settings?tab=communication`).

### Configurable Preferences:
- **Default Invoice Delivery Channel:** `Both (WhatsApp + Email)`, `WhatsApp Only`, `Email Only`.
- **Default Quotation Delivery Channel:** `Both (WhatsApp + Email)`, `WhatsApp Only`, `Email Only`.
- Preferences are stored in the `business_communication_settings` database table per tenant.

When opening the document share modal, the modal preselects the configured default channel, while allowing the operator to override the selection for individual transmissions.

---

## 3. WhatsApp Integration Modes

The system supports a dual-tier WhatsApp strategy: an automated Cloud API tier and a universal Click-to-Chat fallback.

### Tier 1: WhatsApp Business Cloud API (Automated)
Used for automated, server-side document delivery directly through Meta's official API.

**Required Configuration in Settings:**
- **Meta Phone Number ID:** Numeric ID from Meta Developer Dashboard (e.g., `105948372619482`).
- **Cloud API Access Token:** System User Permanent Token with `whatsapp_business_messaging` permission.
- **Business Account ID:** (Optional) WhatsApp Business Account ID.

When configured:
1. The server issues a secure HTTPS request to `https://graph.facebook.com/v18.0/{phone_number_id}/messages`.
2. The message is transmitted with customer name, document number, date, amount, balance due, and secure document link.
3. The result is logged in `communication_messages` as `SENT` with provider reference ID.

### Tier 2: Universal Click-to-Chat Fallback (`wa.me`)
If no Cloud API token is configured, the system automatically falls back to standard WhatsApp Click-to-Chat links:
`https://wa.me/{phone_number}?text={encoded_message}`

**Strict Compliance Invariant:**
- A click-to-chat link opens WhatsApp on the operator's phone or desktop with pre-filled text. It **does not** automatically transmit without human confirmation.
- The system **strictly logs this event as `CLICK_TO_CHAT_GENERATED`**, never claiming that the document was delivered.
- The UI displays an explicit guidance note informing the operator:  
  *"WhatsApp Click-to-Chat link prepared. Click 'Open WhatsApp' to send the pre-filled message."*

---

## 4. Phone Number Normalization (E.164 Standard)

Before dispatching to WhatsApp, the recipient's phone number is normalized through `CommunicationService::normalizePhone()`:
- Strips non-digit characters (`+`, `-`, ` `, `(`, `)`).
- Strips leading zeros.
- For 10-digit Indian mobile numbers starting with 6, 7, 8, or 9, prefixes the country code `91`.
- Validates the resulting digit string matches E.164 requirements (10 to 15 digits).
- Invalid numbers are rejected with user-friendly error guidance before sending.

---

## 5. Email Integration & Pure-PHP PDF Engine

### SMTP Configuration:
Configurable in **Settings > Communication Settings**:
- **SMTP Host:** e.g., `smtp.gmail.com` or `mail.company.com`
- **SMTP Port:** `587` (TLS) or `465` (SSL)
- **Encryption:** `TLS` / `SSL`
- **Username & Password:** Encrypted storage in settings
- **From Email & From Name:** e.g., `billing@company.com`, `WTSBill Accounts`

### Native PDF-1.4 Engine (`PdfGenerator`):
- Standalone pure PHP PDF engine (`app/Services/PdfGenerator.php`) generating valid binary `%PDF-1.4` streams with cross-reference tables and `%%EOF` trailers.
- Zero dependencies on external binaries (`wkhtmltopdf`, `dompdf`, etc.).
- The generated PDF is attached directly to outgoing SMTP emails as a base64 MIME attachment named `Invoice_{number}.pdf` or `Quotation_{number}.pdf`.

---

## 6. Document Share Modal & Audit History

### Modal Features (`#document-share-modal`):
1. **Recipient Auto-Fill:** Automatically loads customer email and phone number from the database.
2. **Channel Selection:** Seamless radio/tab toggling between WhatsApp, Email, or Both.
3. **Editable Message:** Professional template pre-populated with document particulars, balance due, and payment links, editable by the user prior to sending.
4. **Communication History:** Real-time table displaying past dispatch attempts, dates, delivery channels, recipients, and statuses (`SENT`, `FAILED`, `CLICK_TO_CHAT_GENERATED`).
