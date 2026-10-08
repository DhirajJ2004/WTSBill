<?php
// Single Print Test Subprocess Runner

if (empty($argv[1])) {
    exit(1);
}

$data = json_decode(base64_decode($argv[1]), true);
if (!$data || empty($data['file'])) {
    exit(1);
}

require_once __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../views/db_helper.php';

$_GET = $data['get'] ?? [];
$_SESSION = $data['session'] ?? [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $data['file'];
$_SERVER['SCRIPT_NAME'] = '/backend/public/index.php';

$caughtException = null;
register_shutdown_function(function() use (&$caughtException) {
    $code = http_response_code();
    $statusCode = ($code === false || $code === 0) ? 200 : $code;
    $meta = [
        'statusCode' => $statusCode,
        'exception' => $caughtException ? $caughtException->getMessage() : null,
    ];
    echo "\n===TEST_RUNNER_RESULT===" . json_encode($meta);
});

try {
    include $data['file'];
} catch (\Throwable $e) {
    $caughtException = $e;
    http_response_code(500);
}
