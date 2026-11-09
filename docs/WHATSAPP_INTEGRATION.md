# WTSBill ERP – WhatsApp Document Dispatch Integration Guide

## 1. Overview

WTSBill ERP provides two complementary methods for sharing Tax Invoices and Quotations with customers via WhatsApp:

1. **Method A — Direct File Sharing (Web Share API)**:
   Modern mobile devices (Android / iOS) and desktop browsers with Web Share File support allow seamless passing of generated PDF files directly to native WhatsApp apps without intermediate cloud storage.

2. **Method B — Meta WhatsApp Business Cloud API**:
   Enterprise automated document delivery straight from the WTSBill ERP server using Meta's official Graph API (`v18.0`).

3. **Fallback — Download + Click-to-Chat (`wa.me`)**:
   Standardized fallback when native file sharing is unavailable and Cloud API credentials are not configured.

---

## 2. Meta WhatsApp Business Cloud API Configuration

### Step-by-Step Meta Setup
1. Register on [Meta for Developers](https://developers.facebook.com/) and create a Business App.
2. Add **WhatsApp** product to the app.
3. Obtain:
   - **Phone Number ID**: Dedicated sender number ID provided in Meta WhatsApp dashboard.
   - **WhatsApp Business Account (WABA) ID**.
   - **System User Permanent Access Token**: Generated under Meta Business Manager > System Users with `whatsapp_business_messaging` permission.
4. Navigate to WTSBill ERP: **Settings** > **Communication & Sharing** (`/settings?tab=communication`).
5. Configure the WhatsApp Cloud API fields:
   - **Enable WhatsApp Sharing**: Toggle to ON.
   - **Phone Number ID**: e.g., `105938472910485`
   - **Access Token**: `EAAx...`
   - **Default Message Template**: Customizable text containing `{document_number}`, `{customer_name}`, `{company_name}`, `{total}`, and `{balance}` variables.
6. Click **Save Communication Settings**.
7. Use the **Test WhatsApp Connection** button to verify delivery to an authorized test phone number.

---

## 3. Web Share API (Method A) Architecture

On supported mobile and desktop browsers:
```javascript
// Step 1: Detect capability
if (navigator.canShare && navigator.canShare({ files: [new File([], 'test.pdf')] })) {
    // Step 2: Fetch PDF binary from secure authenticated stream
    const res = await fetch('/communication/pdf-stream?type=' + docType + '&id=' + docId);
    const blob = await res.blob();
    const pdfFile = new File([blob], filename, { type: 'application/pdf' });
    
    // Step 3: Trigger native share dialog
    await navigator.share({
        files: [pdfFile],
        title: filename,
        text: prefilledMessageText
    });
}
```

### Supported Environments
- Android Chrome (v75+)
- iOS Safari (iOS 15+)
- macOS Safari & Chrome (macOS 12+)
- Windows 11 Edge & Chrome (desktop share UI)

---

## 4. Click-to-Chat (`wa.me`) Fallback

When Web Share API is not supported or declined:
1. The client script initiates a direct browser download of `Invoice_{number}.pdf` or `Quotation_{number}.pdf`.
2. Automatically generates an international E.164 phone number link:
   `https://wa.me/919876543210?text=Dear%20Customer...`
3. Opens the chat with customer so the user can easily attach the newly downloaded PDF into the conversation.
4. **Audit Compliance**: System records status as `CLICK_TO_CHAT_GENERATED`, ensuring false "delivered" claims are never recorded.

---

## 5. Normalized Phone Handling

Phone numbers are sanitized to remove non-digit characters:
- `+91 98765 43210` -> `919876543210`
- `9876543210` (10-digit Indian mobile) -> auto-prefixed with `91` -> `919876543210`
- International numbers with country codes are preserved after stripping spaces and hyphens.

---

## 6. Audit Trail & Verification

All sharing events record into the `communication_messages` table:
- `entity_type`: `invoice` or `quotation`
- `entity_id`: Document ID
- `channel`: `whatsapp`
- `recipient`: E.164 recipient mobile number
- `status`: `SENT`, `DELIVERED`, `FAILED`, or `CLICK_TO_CHAT_GENERATED`
- `provider_ref_id`: Meta Graph API `wamid`
- `error_message`: Full error text if API call fails

