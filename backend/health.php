<?php
header("Content-Type: application/json");

require_once 'config.php';
applySecurityHeaders(true);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

function checkFirebaseReachable()
{
    $url = rtrim((string)FIREBASE_URL, '/') . '/.json?shallow=true';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 2
    ]);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($errno === 0 && $http >= 200 && $http < 500 && $raw !== false);
}

$checks = [];
$checks['php_runtime'] = [
    'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'value' => PHP_VERSION
];
$checks['app_timezone'] = [
    'ok' => (string)date_default_timezone_get() === (string)APP_TIMEZONE,
    'value' => date_default_timezone_get()
];
$checks['firebase_url_configured'] = [
    'ok' => trim((string)FIREBASE_URL) !== '',
    'value' => trim((string)FIREBASE_URL) !== '' ? 'set' : 'missing'
];
$checks['firebase_reachable'] = [
    'ok' => checkFirebaseReachable(),
    'value' => 'network check'
];
$checks['smtp_configured'] = [
    'ok' => trim((string)SMTP_HOST) !== '' && trim((string)SMTP_USER) !== '' && trim((string)SMTP_PASS) !== '' && (int)SMTP_PORT > 0,
    'value' => 'host/user/pass/port'
];
$checks['recaptcha_configured'] = [
    'ok' => trim((string)RECAPTCHA_SECRET_KEY) !== '',
    'value' => trim((string)RECAPTCHA_SECRET_KEY) !== '' ? 'set' : 'missing'
];

$wsDir = dirname((string)WS_EVENT_LOG);
$adminStoreDir = dirname((string)ADMIN_AUTH_STORE);
$checks['ws_event_log_writable'] = [
    'ok' => is_dir($wsDir) && is_writable($wsDir),
    'value' => $wsDir
];
$checks['admin_auth_store_writable'] = [
    'ok' => is_dir($adminStoreDir) && is_writable($adminStoreDir),
    'value' => $adminStoreDir
];

$criticalFail = !$checks['php_runtime']['ok'] || !$checks['firebase_url_configured']['ok'] || !$checks['firebase_reachable']['ok'];
$anyFail = false;
foreach ($checks as $c) {
    if (empty($c['ok'])) {
        $anyFail = true;
        break;
    }
}

$status = $criticalFail ? 'unhealthy' : ($anyFail ? 'degraded' : 'ok');
if ($criticalFail) {
    http_response_code(503);
}

echo json_encode([
    'status' => $status,
    'timestamp' => date('c'),
    'app_env' => APP_ENV,
    'checks' => $checks
], JSON_UNESCAPED_SLASHES);

