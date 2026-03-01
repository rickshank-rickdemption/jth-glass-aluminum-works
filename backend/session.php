<?php
require_once 'config.php';

function startSecureSession()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $secureCookie = $isHttps || (APP_ENV === 'production');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

function isHttpsRequest()
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $proto === 'https';
}

function enforceHttpsForAdmin($apiMode = false, $redirectTo = '')
{
    if (!ADMIN_ENFORCE_HTTPS || APP_ENV !== 'production') {
        return;
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    $isLocalHost = (
        $host === 'localhost' ||
        $host === '127.0.0.1' ||
        $host === '::1' ||
        preg_match('/^192\.168\./', $host) ||
        preg_match('/^10\./', $host) ||
        preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host)
    );
    if ($isLocalHost) {
        return;
    }
    if (isHttpsRequest()) {
        return;
    }
    if ($apiMode) {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'HTTPS is required.']);
        exit;
    }
    $target = trim((string)$redirectTo);
    if ($target === '') {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if ($host !== '') {
            $target = 'https://' . $host . $uri;
        }
    }
    if ($target !== '') {
        header('Location: ' . $target, true, 302);
    } else {
        http_response_code(403);
        echo 'HTTPS is required.';
    }
    exit;
}

function clearSessionData()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)($params['secure'] ?? true),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => 'Strict'
        ]);
    }
    session_destroy();
}

function loadAdminAuthStoreData()
{
    if (!is_file(ADMIN_AUTH_STORE)) {
        return [];
    }
    $raw = @file_get_contents(ADMIN_AUTH_STORE);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function getStoredAdminSessionVersion()
{
    $store = loadAdminAuthStoreData();
    return (int)($store['session_version'] ?? 0);
}

function isAdminRecoveryConfigured()
{
    $store = loadAdminAuthStoreData();
    return !empty($store['recovery_enabled']) && !empty($store['totp_secret']) && !empty($store['recovery_code_hash']);
}

function requireAdminSessionOrJsonError()
{
    startSecureSession();
    enforceHttpsForAdmin(true);

    $isLoggedIn = !empty($_SESSION['jth_admin_logged_in']) && $_SESSION['jth_admin_logged_in'] === true;
    $lastSeen = (int)($_SESSION['jth_admin_last_seen'] ?? 0);
    $expired = $lastSeen > 0 && (time() - $lastSeen) > ADMIN_SESSION_TTL_SECONDS;

    if (!$isLoggedIn || $expired) {
        if ($expired) {
            clearSessionData();
        }
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized Access']);
        exit;
    }

    if (!empty($_SESSION['jth_admin_force_password_reset'])) {
        echo json_encode(['status' => 'error', 'message' => 'Password reset required.', 'code' => 'PASSWORD_RESET_REQUIRED']);
        exit;
    }

    $sessionVersion = (int)($_SESSION['jth_admin_session_version'] ?? 0);
    $storedVersion = getStoredAdminSessionVersion();
    if ($storedVersion > 0 && $sessionVersion !== $storedVersion) {
        clearSessionData();
        echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
        exit;
    }

    $_SESSION['jth_admin_last_seen'] = time();
}

function requireAdminSessionOrRedirect($redirectTo)
{
    startSecureSession();
    enforceHttpsForAdmin(false);

    $isLoggedIn = !empty($_SESSION['jth_admin_logged_in']) && $_SESSION['jth_admin_logged_in'] === true;
    $lastSeen = (int)($_SESSION['jth_admin_last_seen'] ?? 0);
    $expired = $lastSeen > 0 && (time() - $lastSeen) > ADMIN_SESSION_TTL_SECONDS;

    if (!$isLoggedIn || $expired) {
        if ($expired) {
            clearSessionData();
        }
        header("Location: {$redirectTo}");
        exit;
    }

    if (!empty($_SESSION['jth_admin_force_password_reset']) && basename($_SERVER['PHP_SELF'] ?? '') !== 'admin-password-reset.html') {
        header("Location: admin-password-reset.html");
        exit;
    }

    $sessionVersion = (int)($_SESSION['jth_admin_session_version'] ?? 0);
    $storedVersion = getStoredAdminSessionVersion();
    if ($storedVersion > 0 && $sessionVersion !== $storedVersion) {
        clearSessionData();
        header("Location: {$redirectTo}");
        exit;
    }

    $_SESSION['jth_admin_last_seen'] = time();
}

function getCsrfToken()
{
    startSecureSession();
    if (empty($_SESSION['jth_admin_csrf_token'])) {
        $_SESSION['jth_admin_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['jth_admin_csrf_token'];
}

function requireCsrfOrJsonError()
{
    startSecureSession();
    $expected = (string)($_SESSION['jth_admin_csrf_token'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.', 'code' => 'CSRF_INVALID']);
        exit;
    }
}
