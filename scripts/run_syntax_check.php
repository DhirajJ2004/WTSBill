<?php
// scripts/run_syntax_check.php

$dirIterator = new RecursiveDirectoryIterator(__DIR__ . '/..', RecursiveDirectoryIterator::SKIP_DOTS);
$iterator = new RecursiveIteratorIterator($dirIterator);

$phpFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getRealPath();
        if (strpos($path, 'vendor') === false && strpos($path, '.git') === false) {
            $phpFiles[] = $path;
        }
    }
}

echo "Found " . count($phpFiles) . " PHP files to check...\n";

require_once __DIR__ . '/../backend/vendor/autoload.php';
$phpBinary = \App\Database\Database::getPhpExecutable();
echo "Using PHP Executable: {$phpBinary}\n";

$errors = [];
$passed = 0;

foreach ($phpFiles as $file) {
    $cmd = escapeshellcmd($phpBinary) . ' -l ' . escapeshellarg($file) . ' 2>&1';
    $output = [];
    $returnVar = 0;
    exec($cmd, $output, $returnVar);

    if ($returnVar !== 0) {
        $errors[] = [
            'file' => $file,
            'output' => implode("\n", $output)
        ];
    } else {
        $passed++;
    }
}

echo "Syntax Check Summary:\n";
echo "Passed: $passed\n";
echo "Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nERRORS DETECTED:\n";
    foreach ($errors as $err) {
        echo "File: " . $err['file'] . "\n";
        echo $err['output'] . "\n\n";
    }
    exit(1);
} else {
    echo "\n[PASS] All PHP files passed syntax linting!\n";
    exit(0);
}
