<?php
/**
 * WTSBill ERP - Reusable PDF Print Controls & Toolbar
 * Provides real-time zoom/scale adjustment, margin presets, paper size selection,
 * element visibility toggles, and 1-page fit optimization for PDF printing.
 */
$docType = $docType ?? 'TAX INVOICE';
$docNumber = $docNumber ?? ($invNumber ?? 'DOC-001');
$currentCopy = $_GET['copy'] ?? 'original';
?>
<!-- PDF Print Controls & Live Adjustment Toolbar -->
<div class="print-controls-bar no-print">
    <div class="print-toolbar-container">
        <!-- Left: Document Identity & Copy Selector -->
        <div class="print-toolbar-group">
            <a href="javascript:window.history.back()" class="btn-toolbar-icon" title="Go Back">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </a>
            <div class="toolbar-doc-info">
                <span class="toolbar-doc-badge"><?= htmlspecialchars($docType) ?></span>
                <span class="toolbar-doc-num"><?= htmlspecialchars($docNumber) ?></span>
            </div>
            <select id="copyTypeSelector" class="toolbar-select" onchange="updateCopyType(this.value)">
                <option value="original" <?= $currentCopy === 'original' ? 'selected' : '' ?>>Original for Recipient</option>
                <option value="duplicate" <?= $currentCopy === 'duplicate' ? 'selected' : '' ?>>Duplicate for Supplier/Transporter</option>
                <option value="triplicate" <?= $currentCopy === 'triplicate' ? 'selected' : '' ?>>Triplicate for Consignee</option>
            </select>
        </div>

        <!-- Center: Interactive Scale / Zoom & Margin Adjusters -->
        <div class="print-toolbar-group print-adjuster-group">
            <!-- Scale Zoom Controls -->
            <div class="adjuster-item" title="Scale document to fit PDF page perfectly">
                <span class="adjuster-label">PDF Scale:</span>
                <button type="button" class="btn-zoom-step" onclick="adjustZoom(-0.02)">−</button>
                <input type="range" id="zoomSlider" min="0.75" max="1.20" step="0.01" value="0.96" oninput="setPdfZoom(this.value)">
                <button type="button" class="btn-zoom-step" onclick="adjustZoom(0.02)">+</button>
                <span id="zoomValueBadge" class="zoom-badge">96%</span>
            </div>

            <!-- Quick Fit Presets -->
            <div class="adjuster-presets">
                <button type="button" class="btn-preset-pill active" onclick="fitToSinglePage()" title="Automatically fit on 1 single PDF page">Fit 1-Page</button>
                <button type="button" class="btn-preset-pill" onclick="setPdfZoom(0.92)">92%</button>
                <button type="button" class="btn-preset-pill" onclick="setPdfZoom(0.96)">96%</button>
                <button type="button" class="btn-preset-pill" onclick="setPdfZoom(1.00)">100%</button>
            </div>

            <!-- Margins Selection -->
            <div class="adjuster-item">
                <span class="adjuster-label">Margin:</span>
                <select id="marginSelector" class="toolbar-select" onchange="setPdfMargin(this.value)">
                    <option value="compact">Compact (4mm)</option>
                    <option value="standard" selected>Standard (8mm)</option>
                    <option value="spacious">Spacious (12mm)</option>
                </select>
            </div>
        </div>

        <!-- Right: Element Toggles & Print PDF Action -->
        <div class="print-toolbar-group">
            <!-- Show/Hide Options Dropdown -->
            <div class="dropdown-wrapper">
                <button type="button" class="btn-toolbar-btn btn-secondary-toolbar" onclick="togglePrintOptionsDropdown(event)">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1Z"/></svg>
                    <span>PDF Options</span>
                </button>
                <div id="printOptionsDropdown" class="toolbar-dropdown-menu">
                    <div class="dropdown-header">Toggle Invoice Sections</div>
                    <label class="dropdown-item-toggle">
                        <input type="checkbox" id="toggleBankQr" checked onchange="toggleSection('bankQrSection', this.checked)">
                        <span>Bank Details &amp; UPI QR</span>
                    </label>
                    <label class="dropdown-item-toggle">
                        <input type="checkbox" id="toggleTrans" checked onchange="toggleSection('transSection', this.checked)">
                        <span>Last Transaction Balances</span>
                    </label>
                    <label class="dropdown-item-toggle">
                        <input type="checkbox" id="toggleStamp" checked onchange="toggleSection('stampSection', this.checked)">
                        <span>Company Seal &amp; Signature</span>
                    </label>
                    <label class="dropdown-item-toggle">
                        <input type="checkbox" id="toggleTerms" checked onchange="toggleSection('termsSection', this.checked)">
                        <span>Terms &amp; Warranty Conditions</span>
                    </label>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="btn-dropdown-reset" onclick="resetPrintSettings()">Reset All Adjustments</button>
                </div>
            </div>

            <!-- Print / Download PDF Trigger -->
            <button type="button" class="btn-toolbar-btn btn-primary-print" onclick="window.print()">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
                <span>Print / Save as PDF</span>
            </button>
        </div>
    </div>
</div>

<style>
/* --------------------------------------------------------------------------
   PDF Print Controls & Executive Adjustable UI Bar
   -------------------------------------------------------------------------- */
:root {
    --pdf-zoom: 0.94;
    --pdf-page-margin: 6mm;
    --pdf-page-width: 210mm;
}

@page {
    size: A4 portrait;
    margin: 6mm;
}

.print-controls-bar {
    position: sticky;
    top: 0;
    z-index: 99999;
    background: #0f172a;
    border-bottom: 1px solid #334155;
    padding: 10px 18px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.25);
    margin-bottom: 24px;
}

.print-toolbar-container {
    max-width: 1280px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.print-toolbar-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-toolbar-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    background: #1e293b;
    border: 1px solid #475569;
    border-radius: 6px;
    color: #cbd5e1;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-toolbar-icon:hover {
    background: #334155;
    color: #ffffff;
}

.toolbar-doc-info {
    display: flex;
    flex-direction: column;
    margin-right: 4px;
}
.toolbar-doc-badge {
    font-size: 10px;
    font-weight: 800;
    color: #38bdf8;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}
.toolbar-doc-num {
    font-size: 13px;
    font-weight: 700;
    color: #ffffff;
}

.toolbar-select {
    background: #1e293b;
    color: #f8fafc;
    border: 1px solid #475569;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    outline: none;
    cursor: pointer;
}
.toolbar-select:hover {
    border-color: #94a3b8;
}

/* Adjuster Group */
.print-adjuster-group {
    background: #1e293b;
    padding: 6px 14px;
    border-radius: 8px;
    border: 1px solid #334155;
    gap: 16px;
}

.adjuster-item {
    display: flex;
    align-items: center;
    gap: 8px;
}
.adjuster-label {
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
}

.btn-zoom-step {
    width: 24px;
    height: 24px;
    background: #334155;
    color: #ffffff;
    border: none;
    border-radius: 4px;
    font-weight: bold;
    font-size: 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-zoom-step:hover {
    background: #475569;
}

#zoomSlider {
    width: 90px;
    height: 4px;
    accent-color: #38bdf8;
    cursor: pointer;
}

.zoom-badge {
    background: #0284c7;
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 4px;
    min-width: 44px;
    text-align: center;
}

.adjuster-presets {
    display: flex;
    gap: 4px;
}
.btn-preset-pill {
    background: #334155;
    color: #cbd5e1;
    border: none;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-preset-pill:hover, .btn-preset-pill.active {
    background: #0284c7;
    color: #ffffff;
}

/* Action Buttons */
.btn-toolbar-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.btn-secondary-toolbar {
    background: #1e293b;
    color: #e2e8f0;
    border: 1px solid #475569;
}
.btn-secondary-toolbar:hover {
    background: #334155;
    color: #ffffff;
}
.btn-primary-print {
    background: #0284c7;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(2, 132, 199, 0.4);
}
.btn-primary-print:hover {
    background: #0369a1;
}

/* Dropdown Menu */
.dropdown-wrapper {
    position: relative;
}
.toolbar-dropdown-menu {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    width: 250px;
    background: #1e293b;
    border: 1px solid #475569;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    padding: 10px;
    display: none;
    z-index: 100000;
}
.toolbar-dropdown-menu.show {
    display: block;
}
.dropdown-header {
    font-size: 11px;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    padding: 4px 6px 8px 6px;
    border-bottom: 1px solid #334155;
    margin-bottom: 6px;
}
.dropdown-item-toggle {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    color: #f1f5f9;
    font-size: 12px;
    cursor: pointer;
    border-radius: 4px;
}
.dropdown-item-toggle:hover {
    background: #334155;
}
.dropdown-item-toggle input {
    accent-color: #38bdf8;
    cursor: pointer;
}
.dropdown-divider {
    height: 1px;
    background: #334155;
    margin: 8px 0;
}
.btn-dropdown-reset {
    width: 100%;
    background: none;
    border: none;
    color: #f87171;
    font-size: 11px;
    font-weight: 700;
    padding: 6px;
    text-align: center;
    cursor: pointer;
}
.btn-dropdown-reset:hover {
    text-decoration: underline;
}

/* Sheet Zoom Wrapper */
.pdf-sheet-wrapper {
    transform-origin: top center;
    zoom: var(--pdf-zoom, 0.94);
    transition: zoom 0.15s ease, transform 0.15s ease;
    margin: 0 auto;
}

@media print {
    .print-controls-bar, .no-print {
        display: none !important;
    }
    html, body {
        background: #ffffff !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }
    .pdf-sheet-wrapper {
        zoom: var(--pdf-zoom, 0.94) !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 auto !important;
        padding: 0 !important;
    }
    .invoice-sheet {
        width: 100% !important;
        max-width: 100% !important;
        min-height: auto !important;
        height: auto !important;
        border: 1.5px solid #000000 !important;
        box-sizing: border-box !important;
        margin: 0 auto !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>

<script>
(function() {
    // Load persisted zoom & margin settings if present
    const savedZoom = localStorage.getItem('wts_print_zoom');
    if (savedZoom) {
        setPdfZoom(savedZoom, false);
    }
    const savedMargin = localStorage.getItem('wts_print_margin');
    if (savedMargin) {
        setPdfMargin(savedMargin, false);
    }
})();

function setPdfZoom(val, save = true) {
    val = parseFloat(val);
    if (isNaN(val)) val = 0.94;
    val = Math.max(0.70, Math.min(1.25, val));
    
    document.documentElement.style.setProperty('--pdf-zoom', val);
    
    const slider = document.getElementById('zoomSlider');
    if (slider) slider.value = val.toFixed(2);
    
    const badge = document.getElementById('zoomValueBadge');
    if (badge) badge.innerText = Math.round(val * 100) + '%';
    
    // Update active pill
    document.querySelectorAll('.btn-preset-pill').forEach(btn => {
        const text = btn.innerText.replace('%', '').trim();
        if (text === Math.round(val * 100).toString()) {
            btn.classList.add('active');
        } else if (text !== 'Fit 1-Page') {
            btn.classList.remove('active');
        }
    });

    if (save) {
        localStorage.setItem('wts_print_zoom', val.toFixed(2));
    }
}

function adjustZoom(delta) {
    const current = parseFloat(document.getElementById('zoomSlider').value || 0.94);
    setPdfZoom(current + delta);
}

function fitToSinglePage() {
    // 0.93 - 0.94 guarantees 100% border preservation on standard A4 print/PDF exports
    setPdfZoom(0.93);
    document.querySelectorAll('.btn-preset-pill').forEach(b => b.classList.remove('active'));
    const fitBtn = document.querySelector('.btn-preset-pill');
    if (fitBtn) fitBtn.classList.add('active');
}

function setPdfMargin(preset, save = true) {
    let marginVal = '6mm';
    if (preset === 'compact') marginVal = '3mm';
    else if (preset === 'spacious') marginVal = '10mm';
    
    document.documentElement.style.setProperty('--pdf-page-margin', marginVal);
    
    // Inject dynamic style tag for browser print engine to respect page margins
    let styleTag = document.getElementById('dynamicPageMarginTag');
    if (!styleTag) {
        styleTag = document.createElement('style');
        styleTag.id = 'dynamicPageMarginTag';
        document.head.appendChild(styleTag);
    }
    styleTag.innerHTML = `@page { size: A4 portrait; margin: ${marginVal} !important; }`;

    const select = document.getElementById('marginSelector');
    if (select) select.value = preset;
    
    if (save) {
        localStorage.setItem('wts_print_margin', preset);
    }
}

function updateCopyType(val) {
    const copyMap = {
        'original': 'Original for Receipient (Page 1/1)',
        'duplicate': 'Duplicate for Supplier/Transporter (Page 1/1)',
        'triplicate': 'Triplicate for Consignee (Page 1/1)'
    };
    const label = document.getElementById('copyTypeLabel');
    if (label && copyMap[val]) {
        label.innerText = copyMap[val];
    }
}

function toggleSection(sectionClassOrId, isVisible) {
    const targets = document.querySelectorAll('.' + sectionClassOrId + ', #' + sectionClassOrId);
    targets.forEach(el => {
        el.style.display = isVisible ? '' : 'none';
    });
}

function togglePrintOptionsDropdown(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('printOptionsDropdown');
    if (dropdown) dropdown.classList.toggle('show');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown-wrapper')) {
        const dropdown = document.getElementById('printOptionsDropdown');
        if (dropdown) dropdown.classList.remove('show');
    }
});

function resetPrintSettings() {
    setPdfZoom(0.96);
    setPdfMargin('standard');
    ['toggleBankQr', 'toggleTrans', 'toggleStamp', 'toggleTerms'].forEach(id => {
        const cb = document.getElementById(id);
        if (cb) {
            cb.checked = true;
            cb.dispatchEvent(new Event('change'));
        }
    });
}
</script>
