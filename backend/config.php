<?php

if (!function_exists('loadEnvFile')) {
    function loadEnvFile($path)
    {
        static $loaded = [];
        if (isset($loaded[$path]) || !is_file($path)) {
            return;
        }
        $loaded[$path] = true;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if (($value[0] ?? '') === '"' && (substr($value, -1) === '"')) {
                $value = substr($value, 1, -1);
            } elseif (($value[0] ?? '') === "'" && (substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            if ($key !== '') {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('envValue')) {
    function envValue($key, $default = null)
    {
        $val = getenv($key);
        if ($val === false || $val === null || $val === '') {
            return $default;
        }
        return $val;
    }
}

if (!function_exists('envBool')) {
    function envBool($key, $default = false)
    {
        $raw = envValue($key, null);
        if ($raw === null) {
            return (bool)$default;
        }
        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }
}

loadEnvFile(dirname(__DIR__, 2) . '/.env');
loadEnvFile(__DIR__ . '/.env');

define('APP_ENV', envValue('APP_ENV', 'production'));
define('FIREBASE_URL', envValue('FIREBASE_URL', 'https://jth-glass-and-aluminum-default-rtdb.asia-southeast1.firebasedatabase.app/'));

define('SMTP_HOST', envValue('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_USER', envValue('SMTP_USER', ''));
define('SMTP_PASS', envValue('SMTP_PASS', ''));
define('SMTP_PORT', (int) envValue('SMTP_PORT', '587'));
define('SMTP_SECURE', strtolower((string) envValue('SMTP_SECURE', 'tls')));
define('SMTP_TIMEOUT_SECONDS', (int) envValue('SMTP_TIMEOUT_SECONDS', '12'));
define('SMTP_FROM_EMAIL', envValue('SMTP_FROM_EMAIL', ''));
define('SMTP_FROM_NAME', envValue('SMTP_FROM_NAME', 'JTH Glass & Aluminum Works'));
define('INQUIRY_RECEIVER_EMAIL', envValue('INQUIRY_RECEIVER_EMAIL', SMTP_USER));

define('ADMIN_USER', envValue('ADMIN_USER', 'admin'));
define('ADMIN_PASS', envValue('ADMIN_PASS', ''));
define('ADMIN_PASS_HASH', envValue('ADMIN_PASS_HASH', ''));
define('ADMIN_FORCE_PASSWORD_RESET', filter_var(envValue('ADMIN_FORCE_PASSWORD_RESET', '0'), FILTER_VALIDATE_BOOLEAN));
define('ADMIN_AUTH_STORE', envValue('ADMIN_AUTH_STORE', dirname(__DIR__, 2) . '/storage/admin_auth.json'));

define('COOLDOWN_CONTACT_SECONDS', (int) envValue('COOLDOWN_CONTACT_SECONDS', '60'));
define('COOLDOWN_BOOKING_SECONDS', (int) envValue('COOLDOWN_BOOKING_SECONDS', '180'));
define('APP_TIMEZONE', envValue('APP_TIMEZONE', 'Asia/Manila'));
define('CONTACT_MIN_FORM_FILL_SECONDS', (int) envValue('CONTACT_MIN_FORM_FILL_SECONDS', '3'));
define('CONTACT_MAX_FORM_AGE_SECONDS', (int) envValue('CONTACT_MAX_FORM_AGE_SECONDS', '7200'));
define('BOOKING_MIN_FORM_FILL_SECONDS', (int) envValue('BOOKING_MIN_FORM_FILL_SECONDS', '5'));
define('BOOKING_MAX_FORM_AGE_SECONDS', (int) envValue('BOOKING_MAX_FORM_AGE_SECONDS', '10800'));
define('SECURITY_HEADERS_ENABLED', envBool('SECURITY_HEADERS_ENABLED', true));

define('ADMIN_SESSION_TTL_SECONDS', (int) envValue('ADMIN_SESSION_TTL_SECONDS', '1800')); // 30 mins
define('LOGIN_MAX_ATTEMPTS', (int) envValue('LOGIN_MAX_ATTEMPTS', '5'));
define('LOGIN_LOCKOUT_SECONDS', (int) envValue('LOGIN_LOCKOUT_SECONDS', '900')); // 15 mins
define('ADMIN_LOGIN_IP_WINDOW_SECONDS', (int) envValue('ADMIN_LOGIN_IP_WINDOW_SECONDS', '600')); // 10 mins
define('ADMIN_LOGIN_IP_MAX_ATTEMPTS', (int) envValue('ADMIN_LOGIN_IP_MAX_ATTEMPTS', '30'));
define('ADMIN_LOGIN_CAPTCHA_AFTER_ATTEMPTS', (int) envValue('ADMIN_LOGIN_CAPTCHA_AFTER_ATTEMPTS', '3'));
define('ADMIN_LOGIN_CAPTCHA_REQUIRED_SECONDS', (int) envValue('ADMIN_LOGIN_CAPTCHA_REQUIRED_SECONDS', '1800'));
define('ADMIN_ENFORCE_HTTPS', envBool('ADMIN_ENFORCE_HTTPS', true));

define('RECAPTCHA_SECRET_KEY', envValue('RECAPTCHA_SECRET_KEY', ''));
define('RECAPTCHA_TEST_BYPASS', filter_var(envValue('RECAPTCHA_TEST_BYPASS', '0'), FILTER_VALIDATE_BOOLEAN));
define('RECAPTCHA_TEST_BYPASS_TOKEN', envValue('RECAPTCHA_TEST_BYPASS_TOKEN', 'local-recaptcha-bypass-token'));

define('WS_SERVER_HOST', envValue('WS_SERVER_HOST', '0.0.0.0'));
define('WS_SERVER_PORT', (int) envValue('WS_SERVER_PORT', '8081'));
define('WS_EVENT_LOG', envValue('WS_EVENT_LOG', dirname(__DIR__, 2) . '/storage/ws-events.log'));
define('WS_TOKEN_SECRET', envValue('WS_TOKEN_SECRET', hash('sha256', __FILE__ . '|' . (string)ADMIN_PASS_HASH . '|jth-ws-token')));
define('WS_TOKEN_TTL_SECONDS', (int) envValue('WS_TOKEN_TTL_SECONDS', '3600'));


define('AI_CHAT_ENABLED', filter_var(envValue('AI_CHAT_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN));
define('AI_CHAT_PROVIDER', strtolower((string) envValue('AI_CHAT_PROVIDER', 'openai')));
define('AI_CHAT_API_KEY', envValue('AI_CHAT_API_KEY', ''));
define('AI_CHAT_MODEL', envValue('AI_CHAT_MODEL', 'gpt-4.1-mini'));
define('AI_CHAT_API_URL', envValue('AI_CHAT_API_URL', 'https://api.openai.com/v1/responses'));
define('AI_CHAT_GEMINI_BASE_URL', envValue('AI_CHAT_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'));
define('AI_CHAT_MAX_INPUT_CHARS', (int) envValue('AI_CHAT_MAX_INPUT_CHARS', '500'));
define('AI_CHAT_RATE_LIMIT_PER_MIN', (int) envValue('AI_CHAT_RATE_LIMIT_PER_MIN', '12'));

define('INQUIRY_UPLOAD_MAX_FILES', (int) envValue('INQUIRY_UPLOAD_MAX_FILES', '3'));
define('INQUIRY_UPLOAD_MAX_MB_PER_FILE', (int) envValue('INQUIRY_UPLOAD_MAX_MB_PER_FILE', '5'));
define('INQUIRY_UPLOAD_DIR', envValue('INQUIRY_UPLOAD_DIR', dirname(__DIR__, 2) . '/storage/inquiry_uploads'));

define('REMINDER_ENABLED', envBool('REMINDER_ENABLED', true));
define('REMINDER_CRON_TOKEN', envValue('REMINDER_CRON_TOKEN', ''));
define('SMS_REMINDER_ENABLED', envBool('SMS_REMINDER_ENABLED', false));
define('SMS_REMINDER_WEBHOOK_URL', envValue('SMS_REMINDER_WEBHOOK_URL', ''));
define('SMS_REMINDER_API_KEY', envValue('SMS_REMINDER_API_KEY', ''));

define('RETENTION_ENABLED', envBool('RETENTION_ENABLED', true));
define('RETENTION_CUSTOMER_PII_DAYS', (int) envValue('RETENTION_CUSTOMER_PII_DAYS', '365')); // ~12 months
define('RETENTION_AUTO_INTERVAL_SECONDS', (int) envValue('RETENTION_AUTO_INTERVAL_SECONDS', '86400')); // daily
define('RETENTION_CRON_TOKEN', envValue('RETENTION_CRON_TOKEN', ''));
define('EMAIL_QUEUE_AUTOPROCESS_ON_ADMIN_FETCH', envBool('EMAIL_QUEUE_AUTOPROCESS_ON_ADMIN_FETCH', false));

ini_set('display_errors', 0); // Hide errors from output
ini_set('log_errors', 1);     // Log them to file instead
error_reporting(E_ALL);       // Report everything to log
date_default_timezone_set(APP_TIMEZONE);

if (!function_exists('applySecurityHeaders')) {
    function applySecurityHeaders($apiMode = true)
    {
        if (!SECURITY_HEADERS_ENABLED || headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cross-Origin-Opener-Policy: same-origin');

        if ($apiMode) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        }
    }
}
?>
