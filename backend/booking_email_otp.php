<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'session.php';
startSecureSession();
applySecurityHeaders(true);
require_once 'mailer.php';

define('BOOKING_OTP_TTL_SECONDS', 600); // 10 minutes
define('BOOKING_OTP_VERIFY_MAX_ATTEMPTS', 5);
define('BOOKING_OTP_SEND_COOLDOWN_SECONDS', 45);
define('BOOKING_OTP_RATE_LIMIT_WINDOW_SECONDS', 900); // 15 minutes
define('BOOKING_OTP_RATE_LIMIT_MAX_SENDS', 5);
define('BOOKING_OTP_VERIFIED_TTL_SECONDS', 1800); // 30 minutes

function otpVerificationUrlForEmail($email)
{
    $key = hash('sha256', strtolower(trim((string)$email)));
    return FIREBASE_URL . "system_settings/email_verifications/" . $key . ".json";
}

function otpGetPersistentVerification($email)
{
    $url = otpVerificationUrlForEmail($email);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $raw = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($raw, true);
    if (!is_array($data)) return null;
    if (empty($data['verified'])) return null;
    if (($data['email'] ?? '') !== strtolower(trim((string)$email))) return null;
    return $data;
}

function otpSetPersistentVerification($email, $nowTs)
{
    $url = otpVerificationUrlForEmail($email);
    $payload = [
        'email' => strtolower(trim((string)$email)),
        'verified' => true,
        'verified_at' => (int)$nowTs,
        'updated_at' => (int)$nowTs
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_exec($ch);
    curl_close($ch);
}

function otpEmailExistsInCustomers($email)
{
    $normalized = strtolower(trim((string)$email));
    if ($normalized === '') return false;
    $url = FIREBASE_URL . "customers.json";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $raw = curl_exec($ch);
    curl_close($ch);
    $rows = json_decode($raw, true);
    if (!is_array($rows)) return false;
    foreach ($rows as $customer) {
        if (!is_array($customer)) continue;
        $candidate = strtolower(trim((string)($customer['email'] ?? '')));
        if ($candidate !== '' && hash_equals($candidate, $normalized)) {
            return true;
        }
    }
    return false;
}

function otpIsDisposableEmailDomain($email)
{
    $email = strtolower(trim((string)$email));
    $atPos = strrpos($email, '@');
    if ($atPos === false) return false;
    $domain = substr($email, $atPos + 1);
    if ($domain === '') return false;

    $blocked = [
        'bultoc.com',
        '10minutemail.com',
        '10minutemail.net',
        'guerrillamail.com',
        'guerrillamailblock.com',
        'sharklasers.com',
        'grr.la',
        'mailinator.com',
        'maildrop.cc',
        'tempmail.com',
        'tempmailo.com',
        'temp-mail.org',
        'yopmail.com',
        'dispostable.com',
        'throwawaymail.com',
        'trashmail.com',
        'getnada.com',
        'nada.ltd',
        'mailnesia.com',
        'mintemail.com',
        'moakt.com',
        'mytemp.email',
        'fakemail.net',
        'emailondeck.com',
        'burnermail.io',
        'spamgourmet.com',
        'inboxkitten.com',
        'dropmail.me',
        'tmailor.com',
        'mail.tm',
        'temporary-mail.net',
        'tmpmail.net',
        'tempail.com',
        'fakeinbox.com',
        'spambox.us',
        'spam4.me'
    ];

    if (in_array($domain, $blocked, true)) return true;
    foreach ($blocked as $base) {
        $suffix = '.' . $base;
        if (strlen($domain) > strlen($base) && substr($domain, -strlen($suffix)) === $suffix) {
            return true;
        }
    }

    $keywords = [
        'tempmail', 'temp-mail', 'temporary-mail', '10minutemail', 'mailinator', 'guerrilla', 'yopmail',
        'throwaway', 'trashmail', 'fakeinbox', 'burnermail', 'disposable', 'maildrop', 'mailnesia', 'getnada',
        'sharklasers', 'dropmail', 'inboxkitten', 'spamgourmet', 'spambox', 'mail.tm', 'tmailor'
    ];
    foreach ($keywords as $kw) {
        if ($kw !== '' && strpos($domain, $kw) !== false) {
            return true;
        }
    }
    return false;
}

function verifyRecaptchaOrFail($token)
{
    $recaptchaToken = trim((string)$token);
    if ($recaptchaToken === '') {
        echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA token is required.']);
        exit;
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
        echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA verification failed.']);
        exit;
    }

    $verifyJson = json_decode($verifyRaw, true);
    $success = (bool)($verifyJson['success'] ?? false);
    $score = (float)($verifyJson['score'] ?? 0);
    if (!$success || $score < 0.5) {
        echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA check failed.']);
        exit;
    }
    return true;
}

function otpBuildEmailBody($code)
{
    $safeCode = htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8');
    return "
    <div style='font-family:Arial,sans-serif;background:#f7f7f7;padding:24px'>
      <div style='max-width:520px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px'>
        <h2 style='margin:0 0 12px;color:#111827'>Email Verification Code</h2>
        <p style='margin:0 0 14px;color:#4b5563'>Use this code to verify your email before submitting your booking.</p>
        <div style='font-size:32px;letter-spacing:8px;font-weight:700;color:#111827;margin:8px 0 12px'>{$safeCode}</div>
        <p style='margin:0;color:#6b7280;font-size:13px'>Code expires in 10 minutes. Do not share this code with anyone.</p>
      </div>
    </div>";
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
    exit;
}

$action = strtolower(trim((string)($input['action'] ?? '')));
$emailRaw = trim((string)($input['email'] ?? ''));
$email = strtolower((string)filter_var($emailRaw, FILTER_VALIDATE_EMAIL));
if (!$email) {
    echo json_encode(['status' => 'error', 'message' => 'A valid email is required.']);
    exit;
}
if (otpIsDisposableEmailDomain($email)) {
    echo json_encode(['status' => 'error', 'message' => 'Disposable email is not allowed. Use a real and existing email.']);
    exit;
}

$sessionKey = 'booking_email_otp';
$now = time();

if ($action === 'request') {
    $persistent = otpGetPersistentVerification($email);
    if (is_array($persistent)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Email is already verified.',
            'already_verified' => true,
            'verification_token' => 'persistent'
        ]);
        exit;
    }
    if (otpEmailExistsInCustomers($email)) {
        otpSetPersistentVerification($email, $now);
        echo json_encode([
            'status' => 'success',
            'message' => 'Email is already verified.',
            'already_verified' => true,
            'verification_token' => 'persistent'
        ]);
        exit;
    }

    verifyRecaptchaOrFail($input['recaptcha_token'] ?? '');

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = hash('sha256', 'booking_otp_send|' . $clientIp . '|' . $email);
    $rateUrl = FIREBASE_URL . "system_settings/request_rate_limits/" . $rateKey . ".json";
    $ch = curl_init($rateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $rateRaw = curl_exec($ch);
    curl_close($ch);
    $rateData = json_decode($rateRaw, true);
    if (!is_array($rateData)) $rateData = [];

    $windowStart = (int)($rateData['window_start'] ?? 0);
    $attempts = (int)($rateData['attempts'] ?? 0);
    if ($windowStart <= 0 || ($now - $windowStart) >= BOOKING_OTP_RATE_LIMIT_WINDOW_SECONDS) {
        $windowStart = $now;
        $attempts = 0;
    }
    if ($attempts >= BOOKING_OTP_RATE_LIMIT_MAX_SENDS) {
        $retryAfter = max(1, BOOKING_OTP_RATE_LIMIT_WINDOW_SECONDS - ($now - $windowStart));
        echo json_encode([
            'status' => 'error',
            'reason' => 'rate_limited',
            'message' => 'Too many OTP requests. Please wait before requesting again.',
            'retry_after_seconds' => $retryAfter
        ]);
        exit;
    }

    $existing = $_SESSION[$sessionKey] ?? null;
    if (is_array($existing) && !empty($existing['sent_at']) && ($now - (int)$existing['sent_at']) < BOOKING_OTP_SEND_COOLDOWN_SECONDS && ($existing['email'] ?? '') === $email) {
        $remaining = BOOKING_OTP_SEND_COOLDOWN_SECONDS - ($now - (int)$existing['sent_at']);
        echo json_encode([
            'status' => 'error',
            'reason' => 'cooldown',
            'message' => 'Please wait before requesting another code.',
            'retry_after_seconds' => max(1, (int)$remaining)
        ]);
        exit;
    }

    $otp = (string)random_int(100000, 999999);
    $subject = 'Your JTH booking verification code';
    $body = otpBuildEmailBody($otp);
    $sent = sendEmail($email, $subject, $body);
    if (!$sent) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to send verification code right now. Please try again.']);
        exit;
    }

    $_SESSION[$sessionKey] = [
        'email' => $email,
        'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
        'expires_at' => $now + BOOKING_OTP_TTL_SECONDS,
        'sent_at' => $now,
        'attempts' => 0,
        'verified' => false,
        'verified_at' => 0,
        'token' => ''
    ];

    $attempts++;
    $newRateData = [
        'window_start' => $windowStart,
        'attempts' => $attempts,
        'updated_at' => $now,
        'endpoint' => 'booking_otp_send'
    ];
    $ch = curl_init($rateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newRateData));
    curl_exec($ch);
    curl_close($ch);

    echo json_encode([
        'status' => 'success',
        'message' => 'Verification code sent.',
        'expires_in_seconds' => BOOKING_OTP_TTL_SECONDS,
        'cooldown_seconds' => BOOKING_OTP_SEND_COOLDOWN_SECONDS
    ]);
    exit;
}

if ($action === 'verify') {
    $otpCode = trim((string)($input['otp_code'] ?? ''));
    if (!preg_match('/^\d{6}$/', $otpCode)) {
        echo json_encode(['status' => 'error', 'message' => 'Enter a valid 6-digit code.']);
        exit;
    }

    $otpState = $_SESSION[$sessionKey] ?? null;
    if (!is_array($otpState) || ($otpState['email'] ?? '') !== $email) {
        echo json_encode(['status' => 'error', 'message' => 'No active verification code found for this email.']);
        exit;
    }

    $expiresAt = (int)($otpState['expires_at'] ?? 0);
    if ($expiresAt <= 0 || $now > $expiresAt) {
        unset($_SESSION[$sessionKey]);
        echo json_encode(['status' => 'error', 'message' => 'Verification code expired. Request a new code.']);
        exit;
    }

    $attempts = (int)($otpState['attempts'] ?? 0);
    if ($attempts >= BOOKING_OTP_VERIFY_MAX_ATTEMPTS) {
        unset($_SESSION[$sessionKey]);
        echo json_encode(['status' => 'error', 'message' => 'Too many invalid attempts. Request a new code.']);
        exit;
    }

    $hash = (string)($otpState['otp_hash'] ?? '');
    if ($hash === '' || !password_verify($otpCode, $hash)) {
        $attempts++;
        $otpState['attempts'] = $attempts;
        $_SESSION[$sessionKey] = $otpState;
        $remaining = max(0, BOOKING_OTP_VERIFY_MAX_ATTEMPTS - $attempts);
        echo json_encode([
            'status' => 'error',
            'message' => $remaining > 0 ? "Incorrect code. {$remaining} attempt(s) left." : 'Too many invalid attempts. Request a new code.'
        ]);
        exit;
    }

    $verificationToken = bin2hex(random_bytes(24));
    $otpState['verified'] = true;
    $otpState['verified_at'] = $now;
    $otpState['token'] = $verificationToken;
    $_SESSION[$sessionKey] = $otpState;
    otpSetPersistentVerification($email, $now);

    echo json_encode([
        'status' => 'success',
        'message' => 'Email verified.',
        'verification_token' => $verificationToken,
        'verified_ttl_seconds' => BOOKING_OTP_VERIFIED_TTL_SECONDS
    ]);
    exit;
}

if ($action === 'status') {
    $persistent = otpGetPersistentVerification($email);
    if (is_array($persistent)) {
        echo json_encode([
            'status' => 'success',
            'verified' => true,
            'verification_token' => 'persistent',
            'verified_ttl_seconds' => null,
            'verified_mode' => 'persistent'
        ]);
        exit;
    }
    if (otpEmailExistsInCustomers($email)) {
        otpSetPersistentVerification($email, $now);
        echo json_encode([
            'status' => 'success',
            'verified' => true,
            'verification_token' => 'persistent',
            'verified_ttl_seconds' => null,
            'verified_mode' => 'persistent'
        ]);
        exit;
    }

    $otpState = $_SESSION[$sessionKey] ?? null;
    if (!is_array($otpState) || ($otpState['email'] ?? '') !== $email) {
        echo json_encode(['status' => 'success', 'verified' => false]);
        exit;
    }
    $verified = !empty($otpState['verified']);
    $verifiedAt = (int)($otpState['verified_at'] ?? 0);
    $token = (string)($otpState['token'] ?? '');
    $withinTtl = $verified && $verifiedAt > 0 && ($now - $verifiedAt) <= BOOKING_OTP_VERIFIED_TTL_SECONDS;
    if (!$withinTtl || $token === '') {
        echo json_encode(['status' => 'success', 'verified' => false]);
        exit;
    }
    echo json_encode([
        'status' => 'success',
        'verified' => true,
        'verification_token' => $token,
        'verified_ttl_seconds' => max(0, BOOKING_OTP_VERIFIED_TTL_SECONDS - ($now - $verifiedAt))
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
exit;
?>
