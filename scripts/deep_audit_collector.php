<?php

/**
 * WTSBill Deep Empirical Audit Collector
 * Analyzes Views, Routes, Buttons, Links, Forms, APIs, Security, and Buffering
 */

$rootDir = dirname(__DIR__);
$viewsDir = $rootDir . '/views';
$backendDir = $rootDir . '/backend';

// 1. Collect all View files
$viewFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $viewFiles[$file->getPathname()] = str_replace($rootDir, '', $file->getPathname());
    }
}

// 2. Scan for Buttons in all Views
$buttonReport = [];
$fakeButtons = [];
foreach ($viewFiles as $absPath => $relPath) {
    $content = file_get_contents($absPath);
    
    // Match <button ...>...</button>
    preg_match_all('/<button([^>]*)>(.*?)<\/button>/is', $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $attrs = $m[1];
        $innerHtml = trim(strip_tags($m[2]));
        $hasOnClick = preg_match('/onclick=["\']([^"\']+)["\']/i', $attrs, $onMatches);
        $type = preg_match('/type=["\']([^"\']+)["\']/i', $attrs, $typeMatches) ? $typeMatches[1] : 'button';
        $handler = $hasOnClick ? $onMatches[1] : ($type === 'submit' ? 'form_submit' : 'none');
        
        // Detect fake / placeholder buttons
        $isFake = false;
        if (preg_match('/(Coming soon|Feature ready|Not implemented|alert\(["\']Coming)/i', $content) &&
            preg_match('/alert\s*\(\s*["\'](Feature|Coming|Under construction)/i', $handler)) {
            $isFake = true;
            $fakeButtons[] = [
                'file' => $relPath,
                'button' => $innerHtml,
                'handler' => $handler
            ];
        }
        
        $buttonReport[] = [
            'file' => $relPath,
            'text' => mb_substr($innerHtml, 0, 40),
            'type' => $type,
            'handler' => $handler,
            'is_fake' => $isFake
        ];
    }
}

// 3. Scan for Links (<a> tags)
$linkReport = [];
$brokenLinks = [];
$hashLinks = [];
foreach ($viewFiles as $absPath => $relPath) {
    $content = file_get_contents($absPath);
    preg_match_all('/<a([^>]*)href=["\']([^"\']*)["\']([^>]*)>(.*?)<\/a>/is', $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $href = trim($m[2]);
        $text = trim(strip_tags($m[4]));
        
        if ($href === '#' || $href === 'javascript:void(0)' || $href === 'javascript:;') {
            $hashLinks[] = [
                'file' => $relPath,
                'text' => mb_substr($text, 0, 30),
                'href' => $href
            ];
        } else {
            $linkReport[] = [
                'file' => $relPath,
                'text' => mb_substr($text, 0, 30),
                'href' => $href
            ];
        }
    }
}

// 4. Scan for Forms
$formReport = [];
foreach ($viewFiles as $absPath => $relPath) {
    $content = file_get_contents($absPath);
    preg_match_all('/<form([^>]*)>(.*?)<\/form>/is', $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $attrs = $m[1];
        $formInner = $m[2];
        
        $action = preg_match('/action=["\']([^"\']*)["\']/i', $attrs, $actMatches) ? $actMatches[1] : '';
        $method = preg_match('/method=["\']([^"\']*)["\']/i', $attrs, $mMatches) ? strtoupper($mMatches[1]) : 'GET';
        $hasCsrf = (strpos($formInner, 'csrf_token') !== false || strpos($formInner, 'csrf_field') !== false);
        
        $formReport[] = [
            'file' => $relPath,
            'action' => $action,
            'method' => $method,
            'has_csrf' => $hasCsrf
        ];
    }
}

// 5. Scan for Buffering / Spinners / Artificial delays / setTimeout
$bufferingReport = [];
foreach ($viewFiles as $absPath => $relPath) {
    $content = file_get_contents($absPath);
    $hasSpinner = preg_match('/(loading-spinner|page-loader|global-spinner|fa-spin|spinner-border)/i', $content);
    $hasSetInterval = preg_match('/setInterval\s*\(/i', $content);
    $hasSetTimeout = preg_match('/setTimeout\s*\(/i', $content);
    $hasLocationReload = preg_match('/location\.reload\s*\(/i', $content);
    
    if ($hasSpinner || $hasSetInterval || $hasSetTimeout || $hasLocationReload) {
        $bufferingReport[] = [
            'file' => $relPath,
            'has_spinner' => (bool)$hasSpinner,
            'has_setInterval' => (bool)$hasSetInterval,
            'has_setTimeout' => (bool)$hasSetTimeout,
            'has_locationReload' => (bool)$hasLocationReload,
        ];
    }
}

// Also check public/assets/js/app.js
$appJsPath = $rootDir . '/public/assets/js/app.js';
$appJsStats = [];
if (file_exists($appJsPath)) {
    $jsContent = file_get_contents($appJsPath);
    $appJsStats = [
        'size_bytes' => strlen($jsContent),
        'set_interval_count' => preg_match_all('/setInterval\s*\(/', $jsContent),
        'set_timeout_count' => preg_match_all('/setTimeout\s*\(/', $jsContent),
        'fetch_calls' => preg_match_all('/fetch\s*\(/', $jsContent),
        'location_reload_count' => preg_match_all('/location\.reload\s*\(/', $jsContent),
        'spinners' => preg_match_all('/(spinner|loader)/i', $jsContent),
    ];
}

echo "=================================================================\n";
echo "              VIEW & SOURCE CODE DEEP SCAN RESULTS               \n";
echo "=================================================================\n";
echo "Total View Files Inspected: " . count($viewFiles) . "\n";
echo "Total Buttons Found: " . count($buttonReport) . "\n";
echo "Fake / Placeholder Buttons: " . count($fakeButtons) . "\n";
echo "Total Links Found: " . count($linkReport) . "\n";
echo "Hash / JS void Links (href='#'): " . count($hashLinks) . "\n";
echo "Total Forms Found: " . count($formReport) . "\n";
echo "Views with Loaders/Timers/Reloads: " . count($bufferingReport) . "\n";
echo "App.js Size: " . ($appJsStats['size_bytes'] ?? 0) . " bytes\n";
echo "App.js Fetch Calls: " . ($appJsStats['fetch_calls'] ?? 0) . "\n";
echo "App.js setTimeout Count: " . ($appJsStats['set_timeout_count'] ?? 0) . "\n";
echo "App.js setInterval Count: " . ($appJsStats['set_interval_count'] ?? 0) . "\n";
echo "App.js location.reload Count: " . ($appJsStats['location_reload_count'] ?? 0) . "\n";

if (!empty($fakeButtons)) {
    echo "\n--- FAKE BUTTONS DETECTED ---\n";
    foreach ($fakeButtons as $fb) {
        echo " - [{$fb['file']}] '{$fb['button']}' -> {$fb['handler']}\n";
    }
}

file_put_contents($rootDir . '/scripts/audit_scan_dump.json', json_encode([
    'total_views' => count($viewFiles),
    'buttons' => $buttonReport,
    'fake_buttons' => $fakeButtons,
    'links' => $linkReport,
    'hash_links' => $hashLinks,
    'forms' => $formReport,
    'buffering' => $bufferingReport,
    'app_js' => $appJsStats
], JSON_PRETTY_PRINT));

echo "\nScan data saved to scripts/audit_scan_dump.json.\n";
