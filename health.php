<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$checks = [];

$requiredFiles = [
    DATA_FILE,
    USERS_FILE,
    MESSAGES_FILE,
    ACTIVITY_LOG_FILE
];

foreach ($requiredFiles as $file) {
    $exists = file_exists($file);
    $writable = $exists ? is_writable($file) : is_writable('.');

    $checks[$file] = [
        'exists' => $exists,
        'writable' => $writable
    ];
}

$uploadsWritable = is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR);
$checks['uploads'] = [
    'exists' => is_dir(UPLOAD_DIR),
    'writable' => $uploadsWritable
];

$allOk = true;
$hasWarning = false;

foreach ($checks as $name => $state) {
    if (empty($state['writable'])) {
        $allOk = false;
    }
    if ($name !== 'uploads' && empty($state['exists'])) {
        $hasWarning = true;
    }
}

$status = $allOk ? ($hasWarning ? 'warn' : 'ok') : 'error';
$httpCode = $allOk ? 200 : 503;

http_response_code($httpCode);

echo json_encode([
    'status' => $status,
    'time' => date('c'),
    'php_version' => PHP_VERSION,
    'checks' => $checks
], JSON_UNESCAPED_UNICODE);
