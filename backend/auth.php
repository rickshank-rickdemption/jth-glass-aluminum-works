<?php
header("Content-Type: application/json");

require_once 'config.php';
require_once 'session.php';
require_once 'logger.php';
startSecureSession();
applySecurityHeaders(true);
enforceHttpsForAdmin(true);

$action = $_GET['action'] ?? '';

function loadAdminAuthStore()
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

function saveAdminAuthStore(array $payload)
{
    $dir = dirname(ADMIN_AUTH_STORE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return @file_put_contents(ADMIN_AUTH_STORE, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

function currentAdminPasswordHash()
{
    $store = loadAdminAuthStore();
    if (!empty($store['password_hash']) && is_string($store['password_hash'])) {
        return $store['password_hash'];
    }
    return (string) ADMIN_PASS_HASH;
}

function isPasswordResetRequired()
{
    $store = loadAdminAuthStore();
    if (array_key_exists('must_change_password', $store)) {
        return (bool)$store['must_change_password'];
    }
    return (bool) ADMIN_FORCE_PASSWORD_RESET;
}

function isRecoveryConfigured()
{
    $store = loadAdminAuthStore();
    return !empty($store['recovery_enabled']) && !empty($store['totp_secret']) && !empty($store['recovery_code_hash']);
}

function ensureSessionVersion(array &$store)
{
    if (!isset($store['session_version']) || (int)$store['session_version'] <= 0) {
        $store['session_version'] = 1;
    } else {
        $store['session_version'] = (int)$store['session_version'];
    }
}

function incrementSessionVersion(array &$store)
{
    ensureSessionVersion($store);
    $store['session_version'] = (int)$store['session_version'] + 1;
}

function normalizeLoginAlerts(array $alerts)
{
    $normalized = [];
    foreach ($alerts as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $ts = (int)($entry['ts'] ?? 0);
        if ($ts <= 0) {
            $ts = time();
        }
        $normalized[] = [
            'ts' => $ts,
            'event' => (string)($entry['event'] ?? 'unknown'),
            'severity' => (string)($entry['severity'] ?? 'info'),
            'username' => (string)($entry['username'] ?? ''),
            'ip' => (string)($entry['ip'] ?? ''),
            'details' => (string)($entry['details'] ?? '')
        ];
    }
    usort($normalized, function ($a, $b) {
        return (int)($b['ts'] ?? 0) <=> (int)($a['ts'] ?? 0);
    });
    return array_slice($normalized, 0, 120);
}

function appendLoginAlert(array &$store, $event, $severity, $username, $ip, $details = '')
{
    $alerts = isset($store['login_alerts']) && is_array($store['login_alerts']) ? $store['login_alerts'] : [];
    $alerts[] = [
        'ts' => time(),
        'event' => (string)$event,
        'severity' => (string)$severity,
        'username' => (string)$username,
        'ip' => (string)$ip,
        'details' => (string)$details
    ];
    $store['login_alerts'] = normalizeLoginAlerts($alerts);
}

function base32Encode($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    foreach (str_split((string)$data) as $char) {
        $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    for ($i = 0; $i < strlen($binary); $i += 5) {
        $chunk = substr($binary, $i, 5);
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $encoded .= $alphabet[bindec($chunk)];
    }
    return $encoded;
}

function base32Decode($data)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $map = array_flip(str_split($alphabet));
    $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', (string)$data));
    if ($clean === '') {
        return '';
    }
    $binary = '';
    foreach (str_split($clean) as $char) {
        if (!isset($map[$char])) {
            return '';
        }
        $binary .= str_pad(decbin((int)$map[$char]), 5, '0', STR_PAD_LEFT);
    }
    $decoded = '';
    for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
        $decoded .= chr(bindec(substr($binary, $i, 8)));
    }
    return $decoded;
}

function generateTotpSecret($bytes = 20)
{
    return base32Encode(random_bytes(max(10, (int)$bytes)));
}

function getTotpCode($secret, $timeSlice = null)
{
    $secretBin = base32Decode($secret);
    if ($secretBin === '') {
        return null;
    }
    $slice = $timeSlice === null ? floor(time() / 30) : (int)$timeSlice;
    $counter = pack('N*', 0, $slice);
    $hash = hash_hmac('sha1', $counter, $secretBin, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $part = substr($hash, $offset, 4);
    $value = unpack('N', $part)[1] & 0x7FFFFFFF;
    $otp = $value % 1000000;
    return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
}

function verifyTotpCode($secret, $code, $window = 1)
{
    $normalized = preg_replace('/\D+/', '', (string)$code);
    if (!preg_match('/^\d{6}$/', $normalized)) {
        return false;
    }
    $slice = (int)floor(time() / 30);
    for ($i = -abs((int)$window); $i <= abs((int)$window); $i++) {
        $expected = getTotpCode($secret, $slice + $i);
        if ($expected !== null && hash_equals($expected, $normalized)) {
            return true;
        }
    }
    return false;
}

function generateRecoveryCode()
{
    $raw = strtoupper(bin2hex(random_bytes(10)));
    return substr($raw, 0, 5) . '-' . substr($raw, 5, 5) . '-' . substr($raw, 10, 5) . '-' . substr($raw, 15, 5);
}

function buildLoginRateKey($username, $ip)
{
    return hash('sha256', strtolower(trim((string)$username)) . '|' . trim((string)$ip));
}

function buildRecoveryRateKey($ip)
{
    return hash('sha256', 'recovery|' . trim((string)$ip));
}

function buildLoginIpRateKey($ip)
{
    return hash('sha256', 'login-ip|' . trim((string)$ip));
}

function getProgressiveLoginLockSeconds($tier)
{
    $base = max(60, (int)LOGIN_LOCKOUT_SECONDS);
    $t = max(1, min(6, (int)$tier));
    $multiplier = 1 << ($t - 1); // 1x, 2x, 4x, 8x...
    return $base * $multiplier;
}

function loginFailureDelay()
{
    $minMs = 220;
    $maxMs = 760;
    $delayMs = random_int($minMs, $maxMs);
    usleep($delayMs * 1000);
}

function respondLoginFailure($extra = [])
{
    loginFailureDelay();
    $payload = array_merge(['status' => 'error', 'message' => 'Invalid credentials.'], is_array($extra) ? $extra : []);
    echo json_encode($payload);
    exit;
}

function verifyRecaptchaForAdminLogin($token)
{
    $recaptchaToken = trim((string)$token);
    if ($recaptchaToken === '') {
        return false;
    }
    $isRecaptchaBypass = (
        APP_ENV !== 'production' &&
        RECAPTCHA_TEST_BYPASS === true &&
        RECAPTCHA_TEST_BYPASS_TOKEN !== '' &&
        hash_equals(RECAPTCHA_TEST_BYPASS_TOKEN, $recaptchaToken)
    );
    if ($isRecaptchaBypass) {
        return true;
    }
    if ((string)RECAPTCHA_SECRET_KEY === '') {
        return false;
    }

    $verifyPayload = http_build_query([
        'secret' => RECAPTCHA_SECRET_KEY,
        'response' => $recaptchaToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    $verifyCh = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($verifyCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $verifyPayload,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    $verifyRaw = curl_exec($verifyCh);
    $verifyHttp = (int) curl_getinfo($verifyCh, CURLINFO_HTTP_CODE);
    $verifyErrNo = curl_errno($verifyCh);
    curl_close($verifyCh);
    if ($verifyErrNo !== 0 || $verifyHttp !== 200 || !$verifyRaw) {
        return false;
    }
    $verifyJson = json_decode($verifyRaw, true);
    $ok = (bool)($verifyJson['success'] ?? false);
    $score = (float)($verifyJson['score'] ?? 0);
    $action = (string)($verifyJson['action'] ?? '');
    if (!$ok || $score < 0.5) {
        return false;
    }
    if ($action !== '' && $action !== 'admin_login') {
        return false;
    }
    return true;
}

function pruneLoginIpGuards(array $guards)
{
    $now = time();
    $maxAge = max(3600, ADMIN_LOGIN_IP_WINDOW_SECONDS * 4, ADMIN_LOGIN_CAPTCHA_REQUIRED_SECONDS * 2);
    $clean = [];
    foreach ($guards as $key => $entry) {
        if (!is_array($entry)) continue;
        $updatedAt = (int)($entry['updated_at'] ?? 0);
        $captchaUntil = (int)($entry['captcha_until'] ?? 0);
        $windowStart = (int)($entry['window_start'] ?? 0);
        if ($updatedAt <= 0 && $captchaUntil <= 0 && $windowStart <= 0) continue;
        if ($captchaUntil > $now || ($now - max($updatedAt, $captchaUntil, $windowStart)) <= $maxAge) {
            $clean[$key] = $entry;
        }
    }
    return $clean;
}

function buildUpdatedIpGuardAfterFailure(array $guard, $nowTs)
{
    $windowStart = (int)($guard['window_start'] ?? 0);
    $attempts = (int)($guard['attempts'] ?? 0);
    if ($windowStart <= 0 || ($nowTs - $windowStart) >= ADMIN_LOGIN_IP_WINDOW_SECONDS) {
        $windowStart = $nowTs;
        $attempts = 0;
    }
    $attempts++;
    $captchaUntil = (int)($guard['captcha_until'] ?? 0);
    if ($attempts >= ADMIN_LOGIN_CAPTCHA_AFTER_ATTEMPTS) {
        $captchaUntil = max($captchaUntil, $nowTs + ADMIN_LOGIN_CAPTCHA_REQUIRED_SECONDS);
    }
    return [
        'window_start' => $windowStart,
        'attempts' => $attempts,
        'captcha_until' => $captchaUntil,
        'updated_at' => $nowTs
    ];
}


function pruneLoginRateLimits(array $limits)
{
    $now = time();
    $maxAge = max(3600, LOGIN_LOCKOUT_SECONDS * 4);
    $clean = [];
    foreach ($limits as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $updatedAt = (int)($entry['updated_at'] ?? 0);
        $lockUntil = (int)($entry['lock_until'] ?? 0);
        if ($updatedAt <= 0 && $lockUntil <= 0) {
            continue;
        }
        if ($lockUntil > $now || ($now - max($updatedAt, $lockUntil)) <= $maxAge) {
            $clean[$key] = $entry;
        }
    }
    return $clean;
}

function readLoginRateEntry($rateKey)
{
    $store = loadAdminAuthStore();
    $limits = isset($store['login_rate_limits']) && is_array($store['login_rate_limits']) ? $store['login_rate_limits'] : [];
    $limits = pruneLoginRateLimits($limits);
    $entry = isset($limits[$rateKey]) && is_array($limits[$rateKey]) ? $limits[$rateKey] : ['attempts' => 0, 'lock_until' => 0, 'updated_at' => 0];
    return [$store, $limits, $entry];
}

function pruneRecoveryRateLimits(array $limits)
{
    $now = time();
    $maxAge = max(3600, LOGIN_LOCKOUT_SECONDS * 4);
    $clean = [];
    foreach ($limits as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $updatedAt = (int)($entry['updated_at'] ?? 0);
        $lockUntil = (int)($entry['lock_until'] ?? 0);
        if ($updatedAt <= 0 && $lockUntil <= 0) {
            continue;
        }
        if ($lockUntil > $now || ($now - max($updatedAt, $lockUntil)) <= $maxAge) {
            $clean[$key] = $entry;
        }
    }
    return $clean;
}

function readRecoveryRateEntry($rateKey)
{
    $store = loadAdminAuthStore();
    $limits = isset($store['recovery_rate_limits']) && is_array($store['recovery_rate_limits']) ? $store['recovery_rate_limits'] : [];
    $limits = pruneRecoveryRateLimits($limits);
    $entry = isset($limits[$rateKey]) && is_array($limits[$rateKey]) ? $limits[$rateKey] : ['attempts' => 0, 'lock_until' => 0, 'updated_at' => 0];
    return [$store, $limits, $entry];
}

if ($action === 'logout') {
    clearSessionData();
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'logout_all_sessions' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['jth_admin_logged_in']) || $_SESSION['jth_admin_logged_in'] !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    requireCsrfOrJsonError();

    $store = loadAdminAuthStore();
    incrementSessionVersion($store);
    appendLoginAlert(
        $store,
        'logout_all_sessions',
        'warning',
        (string)($_SESSION['jth_admin_user'] ?? ADMIN_USER),
        (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        'All active admin sessions were invalidated.'
    );
    $store['updated_at'] = time();
    if (!saveAdminAuthStore($store)) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to invalidate sessions right now.']);
        exit;
    }

    clearSessionData();
    echo json_encode(['status' => 'success', 'message' => 'All sessions logged out.']);
    exit;
}

if ($action === 'login_alerts' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['jth_admin_logged_in']) || $_SESSION['jth_admin_logged_in'] !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    $limit = (int)($_GET['limit'] ?? 20);
    $limit = max(1, min(100, $limit));
    $store = loadAdminAuthStore();
    $alerts = normalizeLoginAlerts(isset($store['login_alerts']) && is_array($store['login_alerts']) ? $store['login_alerts'] : []);
    echo json_encode([
        'status' => 'success',
        'alerts' => array_slice($alerts, 0, $limit)
    ]);
    exit;
}

if ($action === 'csrf' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['jth_admin_logged_in']) || $_SESSION['jth_admin_logged_in'] !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    echo json_encode(['status' => 'success', 'csrf_token' => getCsrfToken()]);
    exit;
}

if ($action === 'recovery_status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['jth_admin_logged_in']) || $_SESSION['jth_admin_logged_in'] !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    echo json_encode([
        'status' => 'success',
        'configured' => isRecoveryConfigured()
    ]);
    exit;
}

if ($action === 'recovery_setup_begin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['jth_admin_logged_in']) || $_SESSION['jth_admin_logged_in'] !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    requireCsrfOrJsonError();

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = (string)($input['current_password'] ?? '');

    $hash = currentAdminPasswordHash();
    $legacyPlain = (string) ADMIN_PASS;
    $ok = false;
    if ($hash !== '') {
        $ok = password_verify($currentPassword, $hash);
    } elseif ($legacyPlain !== '') {
        $ok = hash_equals($legacyPlain, $currentPassword);
    }
    if (!$ok) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
        exit;
    }

    $secret = generateTotpSecret();
    $recoveryCode = generateRecoveryCode();
    $_SESSION['jth_pending_recovery_setup'] = [
        'secret' => $secret,
        'recovery_code' => $recoveryCode,
        'created_at' => time()
    ];

    $issuer = rawurlencode('JTH Glass Admin');
    $account = rawurlencode((string)($_SESSION['jth_admin_user'] ?? ADMIN_USER));
    $otpauth = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    echo json_encode([
        'status' => 'success',
        'secret' => $secret,
        'otpauth_uri' => $otpauth
    ]);
    exit;
}

if ($action === 'recovery_setup_confirm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['jth_admin_logged_in']) || $_SESSION['jth_admin_logged_in'] !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    requireCsrfOrJsonError();

    $input = json_decode(file_get_contents('php://input'), true);
    $totpCode = (string)($input['totp_code'] ?? '');
    $pending = $_SESSION['jth_pending_recovery_setup'] ?? null;
    if (!is_array($pending) || empty($pending['secret']) || empty($pending['recovery_code'])) {
        echo json_encode(['status' => 'error', 'message' => 'Recovery setup session expired. Please restart setup.']);
        exit;
    }
    if ((time() - (int)($pending['created_at'] ?? 0)) > 900) {
        unset($_SESSION['jth_pending_recovery_setup']);
        echo json_encode(['status' => 'error', 'message' => 'Recovery setup expired. Please restart setup.']);
        exit;
    }
    if (!verifyTotpCode((string)$pending['secret'], $totpCode, 1)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid authenticator code.']);
        exit;
    }

    $store = loadAdminAuthStore();
    ensureSessionVersion($store);
    $store['totp_secret'] = (string)$pending['secret'];
    $store['recovery_code_hash'] = password_hash((string)$pending['recovery_code'], PASSWORD_DEFAULT);
    $store['recovery_enabled'] = true;
    $store['recovery_updated_at'] = time();
    $store['updated_at'] = time();
    if (!saveAdminAuthStore($store)) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to save recovery setup.']);
        exit;
    }

    $recoveryCode = (string)$pending['recovery_code'];
    unset($_SESSION['jth_pending_recovery_setup']);
    echo json_encode([
        'status' => 'success',
        'recovery_code' => $recoveryCode
    ]);
    exit;
}

if ($action === 'recover_reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $recoveryCode = strtoupper(trim((string)($input['recovery_code'] ?? '')));
    $totpCode = trim((string)($input['totp_code'] ?? ''));
    $newPass = (string)($input['new_password'] ?? '');
    $confirmPass = (string)($input['confirm_password'] ?? '');
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rateKey = buildRecoveryRateKey($clientIp);
    [$store, $limits, $entry] = readRecoveryRateEntry($rateKey);
    $lockUntil = (int)($entry['lock_until'] ?? 0);
    if ($lockUntil > time()) {
        $wait = max(1, $lockUntil - time());
        echo json_encode(['status' => 'error', 'message' => "Too many recovery attempts. Try again in {$wait}s."]);
        exit;
    }

    $fail = function($message) use (&$store, &$limits, $rateKey) {
        $fails = (int)(($limits[$rateKey]['attempts'] ?? 0)) + 1;
        $newLockUntil = 0;
        if ($fails >= LOGIN_MAX_ATTEMPTS) {
            $newLockUntil = time() + LOGIN_LOCKOUT_SECONDS;
            $fails = 0;
        }
        $limits[$rateKey] = [
            'attempts' => $fails,
            'lock_until' => $newLockUntil,
            'updated_at' => time()
        ];
        $store['recovery_rate_limits'] = $limits;
        saveAdminAuthStore($store);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    };

    if ($newPass === '' || strlen($newPass) < 12) {
        $fail('New password must be at least 12 characters.');
    }
    if ($newPass !== $confirmPass) {
        $fail('Password confirmation does not match.');
    }
    if ($recoveryCode === '' || $totpCode === '') {
        $fail('Recovery code and authenticator code are required.');
    }
    if (empty($store['recovery_enabled']) || empty($store['totp_secret']) || empty($store['recovery_code_hash'])) {
        $fail('Recovery is not configured. Contact system owner.');
    }
    if (!verifyTotpCode((string)$store['totp_secret'], $totpCode, 1)) {
        $fail('Invalid recovery credentials.');
    }
    if (!password_verify($recoveryCode, (string)$store['recovery_code_hash'])) {
        $fail('Invalid recovery credentials.');
    }

    $store['password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
    $store['must_change_password'] = false;
    incrementSessionVersion($store);
    $store['updated_at'] = time();
    unset($limits[$rateKey]);
    $store['recovery_rate_limits'] = $limits;

    $newRecoveryCode = generateRecoveryCode();
    $store['recovery_code_hash'] = password_hash($newRecoveryCode, PASSWORD_DEFAULT);
    $store['recovery_updated_at'] = time();

    if (!saveAdminAuthStore($store)) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to reset password right now.']);
        exit;
    }

    clearSessionData();
    echo json_encode([
        'status' => 'success',
        'message' => 'Password reset successful.',
        'new_recovery_code' => $newRecoveryCode
    ]);
    exit;
}

if ($action === 'recovery_regenerate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['jth_admin_logged_in']) || $_SESSION['jth_admin_logged_in'] !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    requireCsrfOrJsonError();

    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = (string)($input['current_password'] ?? '');
    $totpCode = trim((string)($input['totp_code'] ?? ''));
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rateKey = buildRecoveryRateKey($clientIp . '|regen');
    [$store, $limits, $entry] = readRecoveryRateEntry($rateKey);
    $lockUntil = (int)($entry['lock_until'] ?? 0);
    if ($lockUntil > time()) {
        $wait = max(1, $lockUntil - time());
        echo json_encode(['status' => 'error', 'message' => "Too many attempts. Try again in {$wait}s."]);
        exit;
    }

    $fail = function($message) use (&$store, &$limits, $rateKey) {
        $fails = (int)(($limits[$rateKey]['attempts'] ?? 0)) + 1;
        $newLockUntil = 0;
        if ($fails >= LOGIN_MAX_ATTEMPTS) {
            $newLockUntil = time() + LOGIN_LOCKOUT_SECONDS;
            $fails = 0;
        }
        $limits[$rateKey] = [
            'attempts' => $fails,
            'lock_until' => $newLockUntil,
            'updated_at' => time()
        ];
        $store['recovery_rate_limits'] = $limits;
        saveAdminAuthStore($store);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    };

    if (empty($store['recovery_enabled']) || empty($store['totp_secret']) || empty($store['recovery_code_hash'])) {
        $fail('Recovery is not configured yet.');
    }
    if ($currentPassword === '' || $totpCode === '') {
        $fail('Current password and authenticator code are required.');
    }

    $hash = currentAdminPasswordHash();
    $legacyPlain = (string) ADMIN_PASS;
    $okPassword = false;
    if ($hash !== '') {
        $okPassword = password_verify($currentPassword, $hash);
    } elseif ($legacyPlain !== '') {
        $okPassword = hash_equals($legacyPlain, $currentPassword);
    }
    if (!$okPassword) {
        $fail('Invalid credentials.');
    }
    if (!verifyTotpCode((string)$store['totp_secret'], $totpCode, 1)) {
        $fail('Invalid credentials.');
    }

    $newRecoveryCode = generateRecoveryCode();
    $store['recovery_code_hash'] = password_hash($newRecoveryCode, PASSWORD_DEFAULT);
    $store['recovery_updated_at'] = time();
    $store['updated_at'] = time();
    unset($limits[$rateKey]);
    $store['recovery_rate_limits'] = $limits;

    if (!saveAdminAuthStore($store)) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to regenerate recovery code.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'recovery_code' => $newRecoveryCode
    ]);
    exit;
}

if ($action === 'reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['jth_admin_logged_in']) || $_SESSION['jth_admin_logged_in'] !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    requireCsrfOrJsonError();

    $input = json_decode(file_get_contents('php://input'), true);
    $oldPass = (string)($input['old_password'] ?? '');
    $newPass = (string)($input['new_password'] ?? '');
    $confirmPass = (string)($input['confirm_password'] ?? '');

    if ($newPass === '' || strlen($newPass) < 12) {
        echo json_encode(['status' => 'error', 'message' => 'New password must be at least 12 characters.']);
        exit;
    }
    if ($newPass !== $confirmPass) {
        echo json_encode(['status' => 'error', 'message' => 'Password confirmation does not match.']);
        exit;
    }

    $hash = currentAdminPasswordHash();
    $legacyPlain = (string) ADMIN_PASS;
    $oldOk = false;
    if ($hash !== '') {
        $oldOk = password_verify($oldPass, $hash);
    } elseif ($legacyPlain !== '') {
        $oldOk = hash_equals($legacyPlain, $oldPass);
    }
    if (!$oldOk) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
        exit;
    }

    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $store = loadAdminAuthStore();
    $store['password_hash'] = $newHash;
    $store['must_change_password'] = false;
    $store['updated_at'] = time();
    if (!isset($store['login_rate_limits']) || !is_array($store['login_rate_limits'])) {
        $store['login_rate_limits'] = [];
    } else {
        $store['login_rate_limits'] = pruneLoginRateLimits($store['login_rate_limits']);
    }
    $saved = saveAdminAuthStore($store);
    if (!$saved) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to save password.']);
        exit;
    }

    $_SESSION['jth_admin_force_password_reset'] = false;
    echo json_encode(['status' => 'success']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        respondLoginFailure();
    }
    $user = trim((string)($input['username'] ?? ''));
    $pass = (string)($input['password'] ?? '');
    $totpCode = trim((string)($input['totp_code'] ?? ''));
    $captchaToken = (string)($input['recaptcha_token'] ?? '');
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = buildLoginRateKey($user, $clientIp);
    [$authStore, $rateLimits, $rateEntry] = readLoginRateEntry($rateKey);
    $nowTs = time();

    $ipRateKey = buildLoginIpRateKey($clientIp);
    $ipGuards = isset($authStore['login_ip_guards']) && is_array($authStore['login_ip_guards']) ? $authStore['login_ip_guards'] : [];
    $ipGuards = pruneLoginIpGuards($ipGuards);
    $ipGuard = isset($ipGuards[$ipRateKey]) && is_array($ipGuards[$ipRateKey]) ? $ipGuards[$ipRateKey] : [
        'window_start' => 0,
        'attempts' => 0,
        'captcha_until' => 0,
        'updated_at' => 0
    ];
    $windowStart = (int)($ipGuard['window_start'] ?? 0);
    $ipAttempts = (int)($ipGuard['attempts'] ?? 0);
    if ($windowStart <= 0 || ($nowTs - $windowStart) >= ADMIN_LOGIN_IP_WINDOW_SECONDS) {
        $windowStart = $nowTs;
        $ipAttempts = 0;
        $ipGuard['window_start'] = $windowStart;
        $ipGuard['attempts'] = 0;
    }
    if ($ipAttempts >= ADMIN_LOGIN_IP_MAX_ATTEMPTS) {
        appendLoginAlert($authStore, 'login_ip_rate_limited', 'warning', $user, $clientIp, 'IP window attempt threshold reached.');
        $ipGuards[$ipRateKey] = buildUpdatedIpGuardAfterFailure($ipGuard, $nowTs);
        $authStore['login_ip_guards'] = $ipGuards;
        saveAdminAuthStore($authStore);
        appLog('warning', 'admin_login_ip_rate_limited', ['ip' => $clientIp, 'username' => $user]);
        respondLoginFailure();
    }

    $captchaUntil = (int)($ipGuard['captcha_until'] ?? 0);
    $captchaRequired = $captchaUntil > $nowTs;
    if ($captchaRequired && !verifyRecaptchaForAdminLogin($captchaToken)) {
        appendLoginAlert($authStore, 'login_captcha_failed', 'warning', $user, $clientIp, 'Captcha check failed while captcha mode is active.');
        $ipGuards[$ipRateKey] = buildUpdatedIpGuardAfterFailure($ipGuard, $nowTs);
        $authStore['login_ip_guards'] = $ipGuards;
        saveAdminAuthStore($authStore);
        appLog('warning', 'admin_login_captcha_failed', ['ip' => $clientIp, 'username' => $user]);
        respondLoginFailure();
    }

    $lockUntil = (int)($rateEntry['lock_until'] ?? 0);
    if ($lockUntil > $nowTs) {
        $wait = max(1, $lockUntil - $nowTs);
        appendLoginAlert($authStore, 'login_locked', 'warning', $user, $clientIp, "Login blocked by lockout ({$wait}s remaining).");
        $ipGuards[$ipRateKey] = buildUpdatedIpGuardAfterFailure($ipGuard, $nowTs);
        $authStore['login_ip_guards'] = $ipGuards;
        saveAdminAuthStore($authStore);
        appLog('warning', 'admin_login_locked', ['username' => $user, 'wait_seconds' => $wait, 'ip' => $clientIp]);
        respondLoginFailure();
    }

    $okUser = hash_equals((string)ADMIN_USER, $user);
    $okPass = false;
    $resolvedHash = currentAdminPasswordHash();
    if ($resolvedHash !== '') {
        $okPass = password_verify($pass, $resolvedHash);
    } elseif (ADMIN_PASS !== '') {
        $okPass = hash_equals((string)ADMIN_PASS, $pass);
    }

    if ($okUser && $okPass) {
        $recoveryConfigured = !empty($authStore['recovery_enabled']) && !empty($authStore['totp_secret']) && !empty($authStore['recovery_code_hash']);
        if (ADMIN_REQUIRE_TOTP_LOGIN && $recoveryConfigured && !verifyTotpCode((string)$authStore['totp_secret'], $totpCode, 1)) {
            $fails = (int)($rateEntry['attempts'] ?? 0) + 1;
            $tier = max(1, (int)($rateEntry['lock_tier'] ?? 1));
            $newLockUntil = 0;
            if ($fails >= LOGIN_MAX_ATTEMPTS) {
                $newLockUntil = $nowTs + getProgressiveLoginLockSeconds($tier);
                $fails = 0;
                $tier = min(6, $tier + 1);
                appLog('warning', 'admin_login_lockout_set', ['username' => $user, 'ip' => $clientIp, 'lock_tier' => $tier, 'lock_until' => $newLockUntil]);
            }
            $rateLimits[$rateKey] = [
                'attempts' => $fails,
                'lock_until' => $newLockUntil,
                'lock_tier' => $tier,
                'updated_at' => time()
            ];
            $authStore['login_rate_limits'] = $rateLimits;
            $ipGuards[$ipRateKey] = buildUpdatedIpGuardAfterFailure($ipGuard, $nowTs);
            $authStore['login_ip_guards'] = $ipGuards;
            appendLoginAlert($authStore, 'login_totp_failed', 'warning', $user, $clientIp, 'Password matched but TOTP verification failed.');
            saveAdminAuthStore($authStore);
            appLog('warning', 'admin_login_totp_failed', ['username' => $user, 'ip' => $clientIp]);
            respondLoginFailure(['reason' => 'TOTP_REQUIRED']);
        }

        session_regenerate_id(true);
        $_SESSION['jth_admin_logged_in'] = true;
        $_SESSION['jth_admin_user'] = $user;
        $_SESSION['jth_admin_last_seen'] = time();
        $_SESSION['jth_admin_force_password_reset'] = isPasswordResetRequired();
        $storeAfterLogin = loadAdminAuthStore();
        ensureSessionVersion($storeAfterLogin);
        $_SESSION['jth_admin_session_version'] = (int)$storeAfterLogin['session_version'];
        getCsrfToken();
        unset($_SESSION['jth_admin_failed_login_count'], $_SESSION['jth_admin_lock_until']);
        unset($rateLimits[$rateKey]);
        $authStore['login_rate_limits'] = $rateLimits;
        unset($ipGuards[$ipRateKey]);
        $authStore['login_ip_guards'] = $ipGuards;
        appendLoginAlert($authStore, 'login_success', 'info', $user, $clientIp, 'Admin login successful.');
        saveAdminAuthStore($authStore);
        if (!empty($_SESSION['jth_admin_force_password_reset'])) {
            appLog('info', 'admin_login_success_reset_required', ['username' => $user, 'ip' => $clientIp]);
            echo json_encode(['status' => 'reset_required']);
        } else {
            appLog('info', 'admin_login_success', ['username' => $user, 'ip' => $clientIp]);
            echo json_encode(['status' => 'success']);
        }
    } else {
        $fails = (int)($rateEntry['attempts'] ?? 0) + 1;
        $tier = max(1, (int)($rateEntry['lock_tier'] ?? 1));
        $newLockUntil = 0;
        if ($fails >= LOGIN_MAX_ATTEMPTS) {
            $newLockUntil = $nowTs + getProgressiveLoginLockSeconds($tier);
            $fails = 0;
            $tier = min(6, $tier + 1);
            appLog('warning', 'admin_login_lockout_set', ['username' => $user, 'ip' => $clientIp, 'lock_tier' => $tier, 'lock_until' => $newLockUntil]);
        }
        $rateLimits[$rateKey] = [
            'attempts' => $fails,
            'lock_until' => $newLockUntil,
            'lock_tier' => $tier,
            'updated_at' => time()
        ];
        $authStore['login_rate_limits'] = $rateLimits;
        $ipGuards[$ipRateKey] = buildUpdatedIpGuardAfterFailure($ipGuard, $nowTs);
        $authStore['login_ip_guards'] = $ipGuards;
        appendLoginAlert($authStore, 'login_failed', 'warning', $user, $clientIp, 'Invalid username or password.');
        saveAdminAuthStore($authStore);
        appLog('warning', 'admin_login_failed', ['username' => $user, 'ip' => $clientIp]);
        respondLoginFailure();
    }
    exit;
}
?>
