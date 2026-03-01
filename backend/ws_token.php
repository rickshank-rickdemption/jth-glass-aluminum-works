<?php
header("Content-Type: application/json");

require_once __DIR__ . '/session.php';
startSecureSession();
applySecurityHeaders(true);
require_once __DIR__ . '/ws_auth.php';

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));
if ($action === '') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input) && !empty($input['action'])) {
        $action = trim((string)$input['action']);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $action === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

if ($action === 'products_public') {
    $ttl = min(3600, max(300, (int)WS_TOKEN_TTL_SECONDS));
    $token = generateWsToken(['scope' => 'products'], $ttl);
    if (!$token) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to issue token.']);
        exit;
    }
    echo json_encode([
        'status' => 'success',
        'token' => $token,
        'expires_in' => $ttl
    ]);
    exit;
}

if ($action === 'admin_dashboard') {
    requireAdminSessionOrJsonError();
    requireCsrfOrJsonError();

    $ttl = min(1800, max(300, (int)WS_TOKEN_TTL_SECONDS));
    $token = generateWsToken([
        'scope' => 'admin',
        'actor' => (string)($_SESSION['jth_admin_user'] ?? ADMIN_USER)
    ], $ttl);
    if (!$token) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to issue token.']);
        exit;
    }
    echo json_encode([
        'status' => 'success',
        'token' => $token,
        'expires_in' => $ttl
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unsupported token action.']);

