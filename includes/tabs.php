<?php
/**
 * WTSBill ERP — Reusable Tab Component
 *
 * Switches tabs using the '?tab=' URL parameter so each tab is bookmarkable,
 * shareable, and fully functional without requiring client-side JavaScript.
 *
 * @param array       $tabs      Array of tab items: [['id' => 'tab_id', 'label' => 'Tab Label', 'icon' => '...svg...', 'badge' => 5], ...]
 * @param string|null $activeTab Currently active tab id. If null, reads from $_GET['tab'] or defaults to first tab.
 * @param string      $baseUrl   Base URL for the links. If empty, uses '?tab=tab_id'.
 * @return string                Rendered HTML for the tabs navigation bar.
 */
function render_tabs(array $tabs, ?string $activeTab = null, string $baseUrl = ''): string
{
    if (empty($tabs)) {
        return '';
    }

    if ($activeTab === null || $activeTab === '') {
        $activeTab = (string)($_GET['tab'] ?? $tabs[0]['id'] ?? '');
    }

    // Ensure valid active tab
    $validIds = array_column($tabs, 'id');
    if (!in_array($activeTab, $validIds, true)) {
        $activeTab = $tabs[0]['id'];
    }

    $html = '<div class="wts-tabs-container" style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid #8EB69B; padding-bottom: 0; flex-wrap: wrap;" role="tablist">';

    foreach ($tabs as $tab) {
        $tabId = htmlspecialchars($tab['id'], ENT_QUOTES, 'UTF-8');
        $tabLabel = htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8');
        $tabIcon = $tab['icon'] ?? '';
        $isActive = ($activeTab === $tab['id']);

        // Determine destination URL
        if (!empty($tab['url'])) {
            $href = htmlspecialchars($tab['url'], ENT_QUOTES, 'UTF-8');
        } elseif ($baseUrl !== '') {
            $separator = (strpos($baseUrl, '?') === false) ? '?' : '&';
            $href = htmlspecialchars($baseUrl . $separator . 'tab=' . urlencode($tab['id']), ENT_QUOTES, 'UTF-8');
        } else {
            // Keep any other current query params, only updating 'tab'
            $queryParams = $_GET;
            $queryParams['tab'] = $tab['id'];
            $href = '?' . htmlspecialchars(http_build_query($queryParams), ENT_QUOTES, 'UTF-8');
        }

        $style = $isActive
            ? 'background: #235347; color: #FFFFFF; font-weight: 600; border-bottom: 3px solid #8EB69B;'
            : 'background: #DAF1DE; color: #163832; font-weight: 500; border-bottom: 3px solid transparent;';

        $html .= '<a href="' . $href . '" class="wts-tab-btn ' . ($isActive ? 'active' : '') . '" role="tab" aria-selected="' . ($isActive ? 'true' : 'false') . '" style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.6rem 1.15rem; border-radius: 8px 8px 0 0; text-decoration: none; font-size: 0.85rem; transition: all 0.2s; ' . $style . '">';
        
        if ($tabIcon !== '') {
            $html .= '<span class="wts-tab-icon" style="display: inline-flex; align-items: center; color: inherit;">' . $tabIcon . '</span>';
        }
        
        $html .= '<span>' . $tabLabel . '</span>';

        if (isset($tab['badge']) && $tab['badge'] !== '') {
            $badgeBg = $isActive ? 'rgba(255,255,255,0.25)' : '#8EB69B';
            $badgeColor = $isActive ? '#FFFFFF' : '#051F20';
            $html .= '<span class="wts-tab-badge" style="background: ' . $badgeBg . '; color: ' . $badgeColor . '; font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.45rem; border-radius: 10px; margin-left: 0.35rem;">' . htmlspecialchars((string)$tab['badge'], ENT_QUOTES, 'UTF-8') . '</span>';
        }

        $html .= '</a>';
    }

    $html .= '</div>';

    return $html;
}

