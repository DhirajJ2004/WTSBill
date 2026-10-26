<?php
/**
 * WTSBill ERP — System & Organization Settings View
 */
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Enterprise System Settings</h1>
        <p class="page-subtitle">Configure organization profile, Indian GSTIN master, branch settings, and defaults</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Tenant Organization Profile</h3>
        </div>
        <div class="card-body">
            <div style="margin-bottom: 1rem;">
                <label style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Company Legal Name</label>
                <div style="font-size: 1.1rem; font-weight: 600; color: var(--text-primary);">Wis Technosavvy Pvt Ltd</div>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">GSTIN</label>
                <div><code>27AADCW7577N1ZE</code> (State: Maharashtra - 27)</div>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Financial Year</label>
                <div>01 April &mdash; 31 March (FY 2026-27)</div>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;">Default Currency & Timezone</label>
                <div>INR (₹) &bull; Asia/Kolkata (+05:30)</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Active Branches</h3>
        </div>
        <div class="card-body">
            <div style="padding: 0.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 0.5rem; margin-bottom: 0.75rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong>Main Branch - Mumbai</strong>
                    <span class="badge badge-success">MAIN</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Code: BOM-01 &bull; Bandra Kurla Complex, Mumbai</div>
            </div>
            <div style="padding: 0.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 0.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong>Pune Regional Branch</strong>
                    <span class="badge badge-info">REGIONAL</span>
                </div>
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">Code: PUN-02 &bull; Shivaji Nagar, Pune</div>
            </div>
        </div>
    </div>
</div>
