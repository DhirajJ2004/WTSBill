<?php
$title = 'Invoice Templates & Print Settings';
?>

<div class="content-wrapper" style="padding: 1.5rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #163832; margin-bottom: 0.25rem;">
                <a href="/settings" style="color: #235347; text-decoration: none;">Settings</a>
                <span>/</span>
                <span style="color: #051F20; font-weight: 600;">Invoice Templates</span>
            </div>
            <h1 style="font-size: 1.5rem; font-weight: 700; color: #051F20; margin: 0;">Invoice Templates & Styling</h1>
            <p style="font-size: 0.85rem; color: #163832; margin: 0.25rem 0 0 0;">Select your printable document format, company branding colors, QR code display, and terms.</p>
        </div>
        <div>
            <a href="/sales/invoices" class="btn btn-secondary" style="background: #DAF1DE; border: 1.5px solid #8EB69B; color: #163832; padding: 0.55rem 1rem; border-radius: 8px; font-weight: 600; text-decoration: none; font-size: 0.85rem;">
                View Sample Invoice &rarr;
            </a>
        </div>
    </div>

    <!-- Template Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Template 1: Standard GST Tax Invoice (Active Default) -->
        <div class="card" style="background: #FFFFFF; border: 2px solid #235347; border-radius: 12px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(35,83,71,0.08); position: relative;">
            <div style="position: absolute; top: 1rem; right: 1rem;">
                <span class="badge" style="background: #235347; color: #FFFFFF; font-size: 0.72rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 20px;">ACTIVE DEFAULT</span>
            </div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #051F20; margin: 0 0 0.5rem 0;">WIS Official A4 Tax Invoice</h3>
            <p style="font-size: 0.8rem; color: #163832; margin: 0 0 1rem 0;">Official clean layout matching WISDOM TECH SERVICES reference format with dynamic UPI QR code, bank details, and digital signature.</p>

            <div style="background: #F4FAF6; border: 1px solid #8EB69B; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; font-size: 0.78rem; line-height: 1.45; color: #051F20;">
                <div>&bull; <strong>Orientation:</strong> A4 Portrait (210mm &times; 297mm)</div>
                <div>&bull; <strong>Header:</strong> Dual-column seller & buyer branding</div>
                <div>&bull; <strong>Line Items:</strong> Clean floating rows without vertical grid lines</div>
                <div>&bull; <strong>Compliance:</strong> Dynamic CGST/SGST/IGST tax summary & statutory amount in words</div>
                <div>&bull; <strong>QR Code:</strong> Built-in UPI instant payment QR</div>
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <a href="/sales/invoices/1" target="_blank" class="btn btn-secondary" style="flex: 1; text-align: center; background: #DAF1DE; border: 1.5px solid #8EB69B; color: #235347; font-weight: 600; font-size: 0.8rem; padding: 0.5rem; border-radius: 6px; text-decoration: none;">Live Preview</a>
                <button type="button" class="btn btn-primary" disabled style="flex: 1; background: #235347; color: #FFFFFF; font-weight: 600; font-size: 0.8rem; padding: 0.5rem; border: none; border-radius: 6px; opacity: 0.9;">Selected</button>
            </div>
        </div>

        <!-- Template 2: Modern Compact Invoice -->
        <div class="card" style="background: #FFFFFF; border: 1.5px solid #8EB69B; border-radius: 12px; padding: 1.25rem; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #051F20; margin: 0 0 0.5rem 0;">Compact B2B Invoice</h3>
            <p style="font-size: 0.8rem; color: #163832; margin: 0 0 1rem 0;">Streamlined layout optimized for high-volume transactions, multi-page billing runs, and export freight.</p>

            <div style="background: #F4FAF6; border: 1px solid #8EB69B; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; font-size: 0.78rem; line-height: 1.45; color: #051F20;">
                <div>&bull; <strong>Orientation:</strong> A4 Portrait</div>
                <div>&bull; <strong>Density:</strong> High-density rows (up to 25 items per page)</div>
                <div>&bull; <strong>HSN Summary:</strong> Consolidated table by HSN/SAC code</div>
            </div>

            <button type="button" class="btn btn-secondary" onclick="if(window.Toast && window.Toast.info){window.Toast.info('Standard WIS Tax Invoice is currently the active system default.');}" style="width: 100%; text-align: center; background: #FFFFFF; border: 1.5px solid #8EB69B; color: #163832; font-weight: 600; font-size: 0.8rem; padding: 0.5rem; border-radius: 6px; cursor: pointer;">Set as Default</button>
        </div>
    </div>
</div>
