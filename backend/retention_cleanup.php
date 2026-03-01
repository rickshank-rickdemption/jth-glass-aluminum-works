<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'session.php';
startSecureSession();
applySecurityHeaders(true);
require_once 'retention_cleanup_lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function respond($payload)
{
    echo json_encode($payload);
    exit;
}

if ($method === 'GET') {
    $token = trim((string)($_GET['token'] ?? ''));
    if (RETENTION_CRON_TOKEN !== '' && $token !== '' && hash_equals(RETENTION_CRON_TOKEN, $token)) {
        $result = rcRunCleanup('system:cron', false);
        respond($result);
    }

    requireAdminSessionOrJsonError();
    requireCsrfOrJsonError();
    $state = rcFirebaseRequest('GET', 'system_settings/retention_state.json');
    respond([
        'status' => 'success',
        'enabled' => RETENTION_ENABLED,
        'state' => $state['data'] ?? null
    ]);
}

if ($method === 'POST') {
    requireAdminSessionOrJsonError();
    requireCsrfOrJsonError();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];

    $dryRun = !empty($input['dry_run']);
    $result = rcRunCleanup('admin:manual', $dryRun);
    respond($result);
}

respond(['status' => 'error', 'message' => 'Method not allowed.']);
?>
