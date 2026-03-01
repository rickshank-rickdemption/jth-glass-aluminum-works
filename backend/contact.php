<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'session.php';
startSecureSession();
applySecurityHeaders(true);
require_once 'mailer.php';

if (!defined('CONTACT_RATE_LIMIT_WINDOW_SECONDS')) {
    define('CONTACT_RATE_LIMIT_WINDOW_SECONDS', 600); // 10 mins
}
if (!defined('CONTACT_RATE_LIMIT_MAX_ATTEMPTS')) {
    define('CONTACT_RATE_LIMIT_MAX_ATTEMPTS', 12);
}

function contactIsDisposableEmailDomain($email)
{
    $email = strtolower(trim((string)$email));
    $atPos = strrpos($email, '@');
    if ($atPos === false) return false;
    $domain = substr($email, $atPos + 1);
    if ($domain === '') return false;

    $blocked = [
        'bultoc.com', '10minutemail.com', '10minutemail.net', 'guerrillamail.com',
        'guerrillamailblock.com', 'sharklasers.com', 'grr.la', 'mailinator.com',
        'maildrop.cc', 'tempmail.com', 'tempmailo.com', 'temp-mail.org', 'yopmail.com',
        'dispostable.com', 'throwawaymail.com', 'trashmail.com', 'getnada.com', 'nada.ltd',
        'mailnesia.com', 'mintemail.com', 'moakt.com', 'mytemp.email', 'fakemail.net',
        'emailondeck.com', 'burnermail.io', 'spamgourmet.com', 'inboxkitten.com',
        'dropmail.me', 'tmailor.com', 'mail.tm', 'temporary-mail.net', 'tmpmail.net',
        'tempail.com', 'fakeinbox.com', 'spambox.us', 'spam4.me'
    ];
    if (in_array($domain, $blocked, true)) return true;
    foreach ($blocked as $base) {
        $suffix = '.' . $base;
        if (strlen($domain) > strlen($base) && substr($domain, -strlen($suffix)) === $suffix) {
            return true;
        }
    }
    $keywords = [
        'tempmail', 'temp-mail', 'temporary-mail', '10minutemail', 'mailinator',
        'guerrilla', 'yopmail', 'throwaway', 'trashmail', 'fakeinbox', 'burnermail',
        'disposable', 'maildrop', 'mailnesia', 'getnada', 'sharklasers', 'dropmail',
        'inboxkitten', 'spamgourmet', 'spambox', 'mail.tm', 'tmailor'
    ];
    foreach ($keywords as $kw) {
        if ($kw !== '' && strpos($domain, $kw) !== false) {
            return true;
        }
    }
    return false;
}

function contactIsLikelyFakePhone($phone)
{
    $phone = preg_replace('/\D+/', '', (string)$phone);
    if ($phone === '') return false; // optional field
    if (!preg_match('/^09\d{9}$/', $phone)) return true;

    $subscriber = substr($phone, 2);
    if (preg_match('/^(\d)\1{8}$/', $subscriber)) return true;
    if ($subscriber === '123456789' || $subscriber === '987654321') return true;

    $pair = substr($subscriber, 0, 2);
    if ($pair !== '' && strpos(str_repeat($pair, 5), $subscriber) === 0) return true;

    $triple = substr($subscriber, 0, 3);
    if ($triple !== '' && strpos(str_repeat($triple, 3), $subscriber) === 0) return true;

    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

function getRequestPayload()
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($contentType, 'multipart/form-data') !== false) {
        return is_array($_POST) ? $_POST : [];
    }

    $raw = file_get_contents('php://input');
    $parsed = json_decode($raw, true);
    return is_array($parsed) ? $parsed : null;
}

function normalizeUploadedFiles($filesField)
{
    $normalized = [];
    if (!is_array($filesField) || !isset($filesField['name'])) {
        return $normalized;
    }

    if (is_array($filesField['name'])) {
        $count = count($filesField['name']);
        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $filesField['name'][$i] ?? '',
                'type' => $filesField['type'][$i] ?? '',
                'tmp_name' => $filesField['tmp_name'][$i] ?? '',
                'error' => $filesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $filesField['size'][$i] ?? 0
            ];
        }
        return $normalized;
    }

    $normalized[] = [
        'name' => $filesField['name'] ?? '',
        'type' => $filesField['type'] ?? '',
        'tmp_name' => $filesField['tmp_name'] ?? '',
        'error' => $filesField['error'] ?? UPLOAD_ERR_NO_FILE,
        'size' => $filesField['size'] ?? 0
    ];
    return $normalized;
}

function validateAndStoreInquiryFiles($files)
{
    $result = [
        'stored' => [],
        'attachments' => []
    ];
    if (!is_array($files) || count($files) === 0) {
        return $result;
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'];
    $allowedMime = [
        'image/jpeg', 'image/png', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    $maxBytes = INQUIRY_UPLOAD_MAX_MB_PER_FILE * 1024 * 1024;

    if (count($files) > INQUIRY_UPLOAD_MAX_FILES) {
        throw new RuntimeException('Too many files. Maximum is ' . INQUIRY_UPLOAD_MAX_FILES . '.');
    }

    $baseDir = rtrim(INQUIRY_UPLOAD_DIR, '/');
    $targetDir = $baseDir . '/' . date('Y') . '/' . date('m');
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Upload directory is not writable.');
    }

    foreach ($files as $file) {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed. Please try again.');
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        $origName = (string)($file['name'] ?? 'attachment');
        $size = (int)($file['size'] ?? 0);
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid uploaded file.');
        }
        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException('Each file must be up to ' . INQUIRY_UPLOAD_MAX_MB_PER_FILE . 'MB.');
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new RuntimeException('Unsupported file type. Allowed: JPG, PNG, WEBP, PDF, DOC, DOCX.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$finfo->file($tmpPath));
        if (!in_array($mime, $allowedMime, true)) {
            throw new RuntimeException('Unsupported MIME type: ' . $mime);
        }

        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
        $safeBase = trim((string)$safeBase, '._-');
        if ($safeBase === '') {
            $safeBase = 'file';
        }
        $storedName = $safeBase . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath = $targetDir . '/' . $storedName;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new RuntimeException('Could not save uploaded file.');
        }

        $result['stored'][] = [
            'name' => $origName,
            'path' => $destPath,
            'size' => $size,
            'mime' => $mime
        ];
        $result['attachments'][] = [
            'path' => $destPath,
            'name' => $origName
        ];
    }

    return $result;
}

$input = getRequestPayload();
if (!is_array($input)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request payload.']);
    exit;
}

$honeypot = trim((string)($input['website'] ?? $input['company'] ?? ''));
if ($honeypot !== '') {
    echo json_encode(['status' => 'error', 'message' => 'Submission rejected.']);
    exit;
}
$formStartedAt = (int)($input['form_started_at'] ?? 0);
if ($formStartedAt > 0) {
    $age = time() - $formStartedAt;
    if ($age < CONTACT_MIN_FORM_FILL_SECONDS) {
        echo json_encode(['status' => 'error', 'message' => 'Submission too fast. Please try again.']);
        exit;
    }
    if ($age > CONTACT_MAX_FORM_AGE_SECONDS) {
        echo json_encode(['status' => 'error', 'message' => 'Form expired. Please refresh and submit again.']);
        exit;
    }
}

$recaptchaToken = trim((string)($input['recaptcha_token'] ?? ''));
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

$recaptchaSuccess = false;
$recaptchaScore = 0.0;
if ($isRecaptchaBypass) {
    $recaptchaSuccess = true;
    $recaptchaScore = 0.9;
} else {
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
    $recaptchaSuccess = (bool)($verifyJson['success'] ?? false);
    $recaptchaScore = (float)($verifyJson['score'] ?? 0);
}

if (!$recaptchaSuccess || $recaptchaScore < 0.5) {
    echo json_encode([
        'status' => 'error',
        'message' => 'reCAPTCHA check failed.',
        'recaptcha' => [
            'success' => $recaptchaSuccess,
            'score' => $recaptchaScore
        ]
    ]);
    exit;
}

$fname = htmlspecialchars($input['fname'] ?? '');
$lname = htmlspecialchars($input['lname'] ?? '');
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
$phone = preg_replace('/\D+/', '', (string)($input['phone'] ?? ''));
$messageRaw = $input['message'] ?? '';
$messageSafe = nl2br(htmlspecialchars($messageRaw));
$consentGiven = filter_var($input['consent'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!$fname || !$email || !$messageRaw) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
    exit;
}
if (mb_strlen($fname) < 2 || mb_strlen($fname) > 60) {
    echo json_encode(['status' => 'error', 'message' => 'First name must be 2-60 characters.']);
    exit;
}
if ($lname !== '' && mb_strlen($lname) > 60) {
    echo json_encode(['status' => 'error', 'message' => 'Last name is too long.']);
    exit;
}
if ($email && contactIsDisposableEmailDomain((string)$email)) {
    echo json_encode(['status' => 'error', 'message' => 'Disposable email is not allowed. Please use a real email address.']);
    exit;
}
if ($phone !== '' && !preg_match('/^09\d{9}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Phone must be a valid PH mobile number (11 digits starting with 09).']);
    exit;
}
if (contactIsLikelyFakePhone($phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Phone number looks invalid. Please use a real mobile number.']);
    exit;
}
if (mb_strlen(trim((string)$messageRaw)) < 3 || mb_strlen((string)$messageRaw) > 2000) {
    echo json_encode(['status' => 'error', 'message' => 'Message must be between 3 and 2000 characters.']);
    exit;
}
if (!$consentGiven) {
    echo json_encode(['status' => 'error', 'message' => 'Consent is required before submitting.']);
    exit;
}

$uploadedFiles = normalizeUploadedFiles($_FILES['attachments'] ?? null);
try {
    $uploadResult = validateAndStoreInquiryFiles($uploadedFiles);
} catch (RuntimeException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$normalizedEmail = $email ? strtolower(trim($email)) : 'unknown';
$rateKey = hash('sha256', 'contact_inquiry|' . $clientIp . '|' . $normalizedEmail);
$rateUrl = FIREBASE_URL . "system_settings/request_rate_limits/" . $rateKey . ".json";
$nowTs = time();

$ch = curl_init($rateUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$rateRaw = curl_exec($ch);
curl_close($ch);
$rateData = json_decode($rateRaw, true);
if (!is_array($rateData)) {
    $rateData = [];
}

$windowStart = (int)($rateData['window_start'] ?? 0);
$attempts = (int)($rateData['attempts'] ?? 0);
if ($windowStart <= 0 || ($nowTs - $windowStart) >= CONTACT_RATE_LIMIT_WINDOW_SECONDS) {
    $windowStart = $nowTs;
    $attempts = 0;
}
$attempts++;

$newRateData = [
    'window_start' => $windowStart,
    'attempts' => $attempts,
    'updated_at' => $nowTs,
    'endpoint' => 'contact_inquiry'
];
$ch = curl_init($rateUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newRateData));
curl_exec($ch);
curl_close($ch);

if ($attempts > CONTACT_RATE_LIMIT_MAX_ATTEMPTS) {
    $retryAfter = max(1, CONTACT_RATE_LIMIT_WINDOW_SECONDS - ($nowTs - $windowStart));
    echo json_encode([
        'status' => 'error',
        'reason' => 'rate_limited',
        'message' => 'Too many inquiry attempts. Please wait and try again.',
        'retry_after_seconds' => $retryAfter
    ]);
    exit;
}

$cooldownId = $normalizedEmail ? ($clientIp . '|' . $normalizedEmail) : ($clientIp . '|inquiry_submit');
$cooldownKey = 'cooldown_' . hash('sha256', $cooldownId);
$now = time();
if (!empty($_SESSION[$cooldownKey]) && ($now - $_SESSION[$cooldownKey]) < COOLDOWN_CONTACT_SECONDS) {
    echo json_encode(['status' => 'error', 'message' => 'Please wait before submitting again.']);
    exit;
}

$adminEmail = defined('SMTP_USER') ? SMTP_USER : 'admin@example.com';
$subject = "Inquiry: $fname $lname";
$currentDate = date("F j, Y, g:i a") . " PHT";
$phoneDisplay = $phone !== '' ? $phone : 'N/A';
$attachmentNames = array_map(
    static function ($f) {
        return htmlspecialchars((string)($f['name'] ?? 'Attachment'), ENT_QUOTES, 'UTF-8');
    },
    $uploadResult['stored'] ?? []
);
$attachmentsHtml = '<div style="font-size:13px;color:#71717A;">None</div>';
if (!empty($attachmentNames)) {
    $attachmentsHtml = '<ul style="margin:0;padding-left:18px;color:#27272A;font-size:13px;line-height:1.6;">';
    foreach ($attachmentNames as $safeName) {
        $attachmentsHtml .= '<li>' . $safeName . '</li>';
    }
    $attachmentsHtml .= '</ul>';
}

$body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:24px;background:#F4F4F5;font-family:Inter,Segoe UI,Arial,sans-serif;color:#18181B;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table width="620" border="0" cellspacing="0" cellpadding="0" style="max-width:620px;background:#FFFFFF;border:1px solid #E4E4E7;border-radius:12px;overflow:hidden;box-shadow:0 3px 12px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="padding:20px 24px;background:#FAFAFA;border-bottom:1px solid #E4E4E7;color:#18181B;">
                            <div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#71717A;font-weight:700;">JTH Glass & Aluminum Works</div>
                            <div style="font-size:20px;font-weight:700;margin-top:8px;">New Inquiry Received</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 24px 10px 24px;font-size:14px;line-height:1.65;color:#3F3F46;">
                            A customer submitted a new inquiry from the website contact form.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 24px 18px 24px;">
                            <table width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #E4E4E7;border-radius:10px;overflow:hidden;">
                                <tr><td style="padding:12px 14px;background:#FAFAFA;font-size:11px;color:#71717A;text-transform:uppercase;letter-spacing:.08em;font-weight:600;" colspan="2">Inquiry Details</td></tr>
                                <tr><td style="padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;">Name</td><td align="right" style="padding:10px 14px;font-size:13px;font-weight:600;color:#27272A;border-top:1px solid #E4E4E7;">$fname $lname</td></tr>
                                <tr><td style="padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;">Phone</td><td align="right" style="padding:10px 14px;font-size:13px;font-weight:600;color:#27272A;border-top:1px solid #E4E4E7;">$phoneDisplay</td></tr>
                                <tr><td style="padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;">Email</td><td align="right" style="padding:10px 14px;font-size:13px;font-weight:600;color:#27272A;border-top:1px solid #E4E4E7;"><a href="mailto:$email" style="color:#18181B;text-decoration:none;">$email</a></td></tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 20px 24px;">
                            <div style="font-size:11px;color:#71717A;text-transform:uppercase;letter-spacing:.08em;font-weight:600;margin-bottom:8px;">Message</div>
                            <div style="background:#FAFAFA;border:1px solid #E4E4E7;border-radius:10px;padding:14px;font-size:14px;line-height:1.65;color:#18181B;">$messageSafe</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 24px 20px 24px;">
                            <div style="font-size:11px;color:#71717A;text-transform:uppercase;letter-spacing:.08em;font-weight:600;margin-bottom:8px;">Attachments</div>
                            <div style="background:#FAFAFA;border:1px solid #E4E4E7;border-radius:10px;padding:14px;">$attachmentsHtml</div>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:0 24px 24px 24px;">
                            <a href="mailto:$email?subject=Re: Inquiry via JTH Glass Website" style="display:inline-block;background:#18181B;color:#FAFAFA;text-decoration:none;padding:10px 16px;border-radius:8px;font-size:12px;font-weight:600;letter-spacing:.01em;">Reply via Email</a>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:14px 20px;background:#FAFAFA;border-top:1px solid #E4E4E7;">
                            <div style="font-size:11px;color:#71717A;">Received on $currentDate</div>
                            <div style="font-size:11px;color:#A1A1AA;margin-top:4px;">JTH Glass & Aluminum Works</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

$sent = sendEmail($adminEmail, $subject, $body, $uploadResult['attachments'] ?? []);

if ($sent) {
    $_SESSION[$cooldownKey] = time();
    echo json_encode([
        'status' => 'success',
        'message' => 'Message sent successfully.',
        'uploaded_files' => count($uploadResult['stored'] ?? [])
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Could not send message. Please try again later.']);
}
?>
