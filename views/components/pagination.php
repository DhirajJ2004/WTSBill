<?php
/**
 * WTSBill ERP - Reusable Pagination Component
 *
 * Parameters:
 * - $currentPage (int): Active page number (1-based)
 * - $totalPages (int): Total number of pages
 * - $totalItems (int|null): Total record count
 * - $perPage (int): Records per page
 * - $baseUrl (string|null): Base URL for page links
 * - $paramName (string): Query param name (default 'page')
 */

$currentPage = max(1, (int)($currentPage ?? 1));
$totalPages = max(1, (int)($totalPages ?? 1));
$totalItems = $totalItems ?? null;
$perPage = (int)($perPage ?? 25);
$baseUrl = $baseUrl ?? strtok($_SERVER["REQUEST_URI"] ?? '', '?');
$paramName = $paramName ?? 'page';

// Build page link preserving existing query parameters
function get_page_url($pageNumber, $url, $param) {
    $params = $_GET;
    $params[$param] = $pageNumber;
    return htmlspecialchars($url . '?' . http_build_query($params));
}

if ($totalPages <= 1 && $totalItems === null) {
    return;
}

$startItem = ($currentPage - 1) * $perPage + 1;
$endItem = $totalItems !== null ? min($totalItems, $currentPage * $perPage) : ($currentPage * $perPage);
?>
<div class="pagination-container">
    <?php if ($totalItems !== null): ?>
        <div class="pagination-info">
            Showing <strong><?= number_format($startItem) ?></strong> to <strong><?= number_format($endItem) ?></strong> of <strong><?= number_format($totalItems) ?></strong> records
        </div>
    <?php else: ?>
        <div class="pagination-info">
            Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <ul class="pagination-list">
            <!-- First & Previous -->
            <li class="pagination-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a href="<?= $currentPage > 1 ? get_page_url(1, $baseUrl, $paramName) : '#' ?>" class="pagination-link" title="First Page" aria-label="First page">
                    <i class="fa-solid fa-angles-left"></i>
                </a>
            </li>
            <li class="pagination-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <a href="<?= $currentPage > 1 ? get_page_url($currentPage - 1, $baseUrl, $paramName) : '#' ?>" class="pagination-link" title="Previous Page" aria-label="Previous page">
                    <i class="fa-solid fa-angle-left"></i>
                </a>
            </li>

            <!-- Page Number Windows -->
            <?php
            $window = 2;
            $startPage = max(1, $currentPage - $window);
            $endPage = min($totalPages, $currentPage + $window);

            if ($startPage > 1): ?>
                <li class="pagination-item"><a href="<?= get_page_url(1, $baseUrl, $paramName) ?>" class="pagination-link">1</a></li>
                <?php if ($startPage > 2): ?>
                    <li class="pagination-ellipsis">&hellip;</li>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                <li class="pagination-item <?= $p === $currentPage ? 'active' : '' ?>">
                    <a href="<?= get_page_url($p, $baseUrl, $paramName) ?>" class="pagination-link" <?= $p === $currentPage ? 'aria-current="page"' : '' ?>>
                        <?= $p ?>
                    </a>
                </li>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <li class="pagination-ellipsis">&hellip;</li>
                <?php endif; ?>
                <li class="pagination-item"><a href="<?= get_page_url($totalPages, $baseUrl, $paramName) ?>" class="pagination-link"><?= $totalPages ?></a></li>
            <?php endif; ?>

            <!-- Next & Last -->
            <li class="pagination-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a href="<?= $currentPage < $totalPages ? get_page_url($currentPage + 1, $baseUrl, $paramName) : '#' ?>" class="pagination-link" title="Next Page" aria-label="Next page">
                    <i class="fa-solid fa-angle-right"></i>
                </a>
            </li>
            <li class="pagination-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                <a href="<?= $currentPage < $totalPages ? get_page_url($totalPages, $baseUrl, $paramName) : '#' ?>" class="pagination-link" title="Last Page" aria-label="Last page">
                    <i class="fa-solid fa-angles-right"></i>
                </a>
            </li>
        </ul>
    <?php endif; ?>
</div>
