# WTSBill ERP – SMTP Email Delivery & PDF Attachment Engine

## 1. Overview

WTSBill ERP features a dedicated SMTP client implementation and fallback delivery engine designed for reliable transmission of GST Tax Invoices and Quotations with auto-generated PDF attachments.

Key Capabilities:
- Pure PHP socket-based SMTP client supporting **TLS**, **SSL**, and standard authentication (`AUTH LOGIN`).
- Standardized `multipart/mixed` MIME architecture ensuring PDF attachments render cleanly across Outlook, Gmail, Apple Mail, and mobile email apps.
- Automatic customer email resolution with customizable CC recipients.
- Fallback to native PHP `mail()` when local sendmail or relay agent is configured.
- Accurate provider response code inspection (`250 2.0.0 OK`).

---

## 2. SMTP Protocol Architecture

```
WTSBill ERP                                 Mail Server (e.g. Gmail / SendGrid / Custom SMTP)
     |                                              |
     | ------------ fsockopen / stream -----------> |  Connect (Port 587/465/25)
     | <----------- 220 Service Ready ------------- |
     |                                              |
     | ------------ EHLO hostname ----------------> |
     | <----------- 250 Options ------------------- |
     |                                              |
     | ------------ STARTTLS ---------------------> |  (If Port 587 TLS)
     | <----------- 220 Ready for TLS ------------- |
     | [Negotiate Crypto TLS 1.2/1.3]               |
     | ------------ EHLO hostname ----------------> |
     | <----------- 250 Options ------------------- |
     |                                              |
     | ------------ AUTH LOGIN -------------------> |
     | <----------- 334 VXNlcm5hbWU6 -------------- |
     | ------------ [Base64 Username] ------------> |
     | <----------- 334 UGFzc3dvcmQ6 -------------- |
     | ------------ [Base64 Password] ------------> |
     | <----------- 235 Authentication Succeeded -- |
     |                                              |
     | ------------ MAIL FROM: <sender> ----------> |
     | <----------- 250 OK ------------------------ |
     | ------------ RCPT TO: <recipient> ---------> |
     | <----------- 250 OK ------------------------ |
     | ------------ RCPT TO: <cc_recipient> ------> |  (If CC supplied)
     | <----------- 250 OK ------------------------ |
     | ------------ DATA -------------------------> |
     | <----------- 354 Start mail input ---------- |
     | ------------ [MIME Headers + Body + PDF] --> |
     | ------------ CRLF . CRLF ------------------> |
     | <----------- 250 2.0.0 Message accepted ---- |
     | ------------ QUIT -------------------------> |
     | <----------- 221 Bye ----------------------- |
```

---

## 3. Configuration in Settings

Administrators configure email settings in **Settings** > **Communication & Sharing** (`/settings?tab=communication`):

| Setting Key | Description | Example |
| :--- | :--- | :--- |
| `smtp_host` | Outgoing SMTP mail server | `smtp.gmail.com` / `smtp.office365.com` |
| `smtp_port` | Connection port | `587` (TLS), `465` (SSL), `25` |
| `smtp_username` | SMTP account username / email | `billing@wtsindia.co.in` |
| `smtp_password` | App password or SMTP credentials | `••••••••••••••••` |
| `smtp_encryption` | Encryption layer | `tls` or `ssl` |
| `smtp_from_email` | Sender email address | `invoices@wtsindia.co.in` |
| `smtp_from_name` | Sender display name | `WIS Technosavvy Pvt Ltd` |

---

## 4. MIME Multipart Structure

The email generator in `CommunicationService::sendSmtpMail` constructs compliant MIME emails:

```
From: "WIS Technosavvy Pvt Ltd" <invoices@wtsindia.co.in>
To: "Ramesh Hardware Stores" <billing@rameshhardware.com>
Cc: "Accountant" <accounts@rameshhardware.com>
Subject: Invoice INV-2026-11-060023 from WIS Technosavvy Pvt Ltd
MIME-Version: 1.0
Content-Type: multipart/mixed; boundary="==_MULTIPART_BOUNDARY_a1b2c3d4"

--==_MULTIPART_BOUNDARY_a1b2c3d4
Content-Type: text/plain; charset="UTF-8"
Content-Transfer-Encoding: 8bit

Dear Ramesh Hardware Stores,

Please find attached Tax Invoice INV-2026-11-060023 for ₹2,360.00.

Thank you for your business.

--==_MULTIPART_BOUNDARY_a1b2c3d4
Content-Type: application/pdf; name="Invoice_INV-2026-11-060023.pdf"
Content-Transfer-Encoding: base64
Content-Disposition: attachment; filename="Invoice_INV-2026-11-060023.pdf"

JVBERi0xLjQKJeLjz9MKMSAwIG9iago8PAovVHlwZSAvQ2F0YWxvZwov...
--==_MULTIPART_BOUNDARY_a1b2c3d4--
```

---

## 5. Security & Error Handling

- **Address Validation**: All recipient and CC email addresses are strictly validated via `filter_var($email, FILTER_VALIDATE_EMAIL)`.
- **Header Injection Defense**: Carriage returns (`\r`) and line breaks (`\n`) are stripped from subjects and headers to prevent CRLF injection.
- **Provider Status Reporting**: Success is reported only when the SMTP server yields a `250` response code to the end-of-data marker (`\r\n.\r\n`).
- **Audit Persistence**: Every attempt is logged in `communication_messages` with actual error transcripts if the socket fails or server returns a rejection code.

