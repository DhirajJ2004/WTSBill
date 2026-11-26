<?php

use WTSBill\Core\Config;
use WTSBill\Core\Database;
use WTSBill\Core\Session;

$appName = Config::get('app.name', 'WTSBill ERP');
$year = date('Y');

$companyId = (int)(Session::getCompanyId() ?? (Session::get('current_company_id') ?? 1));
$company = null;
try {
    $company = Database::selectOne("SELECT name, legal_name, website FROM companies WHERE id = ? LIMIT 1", [$companyId]);
} catch (\Throwable $e) {}

$companyName = $company['legal_name'] ?? ($company['name'] ?? 'Wis Technosavvy Pvt Ltd');
$wtsUrl = !empty($company['website']) ? $company['website'] : '/dashboard';
?>
<footer class="erp-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1.5rem; font-size: 0.8rem; border-top: 1px solid var(--border-color); background: var(--bg-surface); color: var(--text-muted); flex-wrap: wrap; gap: 0.75rem;">
    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
        <span>&copy; <?= e($year) ?> <strong><?= e($appName) ?></strong></span>
        <span>&bull;</span>
        <span>Designed &amp; Developed by <a href="<?= e($wtsUrl) ?>" <?= !empty($company['website']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?> style="color: var(--color-primary, #355872); font-weight: 600; text-decoration: none;" title="<?= e($companyName) ?>">WTS</a></span>
        <span>&bull;</span>
        <span>All Rights Reserved.</span>
    </div>
    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
        <span>Environment: <strong style="color: var(--text-primary);"><?= e(Config::get('app.env', 'production')) ?></strong></span>
        <span>&bull;</span>
        <span>Native PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span>
    </div>
</footer>
