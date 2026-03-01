<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'session.php';
startSecureSession();
applySecurityHeaders(true);
require_once 'mailer.php';
require_once 'email_fallback.php';
require_once 'ws_events.php';
require_once 'workflow.php';
require_once 'retention_cleanup_lib.php';

define('MAX_DAILY_BOOKINGS', 3);
define('QUOTE_VALIDITY_DAYS', 7);
define('DEBUG_BOOKING_LIMIT', true);
define('BOOKING_IDEMPOTENCY_TTL_SECONDS', 86400);
define('MAX_LINE_ITEMS_PER_QUOTE', 20);
define('MAX_QTY_PER_LINE_ITEM', 500);
define('MAX_TOTAL_QTY_PER_QUOTE', 3000);
define('BOOKING_RATE_LIMIT_WINDOW_SECONDS', 600); // 10 mins
define('BOOKING_RATE_LIMIT_MAX_ATTEMPTS', 20); // per IP per window
define('BOOKING_CUSTOMER_NAME_MIN', 3);
define('BOOKING_CUSTOMER_NAME_MAX', 80);
define('BOOKING_ADDRESS_MIN', 8);
define('BOOKING_ADDRESS_MAX', 220);
define('BOOKING_EMAIL_OTP_VERIFIED_TTL_SECONDS', 1800);

if (!function_exists('isPersistentlyVerifiedBookingEmail')) {
    function isPersistentlyVerifiedBookingEmail($email)
    {
        $normalized = strtolower(trim((string)$email));
        if ($normalized === '') return false;
        $key = hash('sha256', $normalized);
        $url = FIREBASE_URL . "system_settings/email_verifications/" . $key . ".json";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $raw = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($raw, true);
        if (!is_array($data)) return false;
        if (empty($data['verified'])) return false;
        return (($data['email'] ?? '') === $normalized);
    }
}

if (!function_exists('hasExistingCustomerEmail')) {
    function hasExistingCustomerEmail($email)
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
}

if (!function_exists('isDisposableEmailDomain')) {
    function isDisposableEmailDomain($email)
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

        $domainKeywords = [
            'tempmail',
            'temp-mail',
            'temporary-mail',
            '10minutemail',
            'mailinator',
            'guerrilla',
            'yopmail',
            'throwaway',
            'trashmail',
            'fakeinbox',
            'burnermail',
            'disposable',
            'maildrop',
            'mailnesia',
            'getnada',
            'sharklasers',
            'dropmail',
            'inboxkitten',
            'spamgourmet',
            'spambox',
            'mail.tm',
            'tmailor'
        ];
        foreach ($domainKeywords as $kw) {
            if ($kw !== '' && strpos($domain, $kw) !== false) {
                return true;
            }
        }

        if (function_exists('dns_get_record')) {
            $mxRecords = @dns_get_record($domain, DNS_MX);
            if (is_array($mxRecords) && !empty($mxRecords)) {
                $mxNeedles = [
                    'mailinator',
                    'guerrillamail',
                    'yopmail',
                    'tempmail',
                    'temp-mail',
                    'maildrop',
                    'mailnesia',
                    'getnada',
                    'dropmail',
                    'inboxkitten',
                    'sharklasers',
                    'spamgourmet',
                    'mail.tm',
                    'tmailor',
                    'moakt'
                ];
                foreach ($mxRecords as $mx) {
                    $target = strtolower(trim((string)($mx['target'] ?? '')));
                    if ($target === '') continue;
                    foreach ($mxNeedles as $needle) {
                        if (strpos($target, $needle) !== false) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
}

if (!function_exists('normalizeForWordFilter')) {
    function normalizeForWordFilter($value)
    {
        $v = strtolower((string)$value);
        $map = [
            '@' => 'a', '0' => 'o', '1' => 'i', '!' => 'i', '|' => 'i',
            '3' => 'e', '5' => 's', '$' => 's', '7' => 't'
        ];
        $v = strtr($v, $map);
        $v = preg_replace('/[^a-z0-9\s]/', ' ', $v);
        $v = preg_replace('/\s+/', ' ', trim($v));
        return $v;
    }
}

if (!function_exists('containsDisallowedWords')) {
    function containsDisallowedWords($value)
    {
        $text = normalizeForWordFilter($value);
        if ($text === '') return false;
        $stems = [
            'puta', 'putangina', 'tangina', 'gago', 'ulol', 'bobo', 'kupal', 'tarantado', 'punyeta', 'hindot', 'pakyu',
            'fuck', 'fck', 'shit', 'bitch', 'asshole', 'bastard', 'dick', 'pussy', 'motherf'
        ];
        foreach ($stems as $stem) {
            if (strpos($text, $stem) !== false) return true;
        }
        return false;
    }
}

if (!function_exists('uuidv4')) {
    function uuidv4()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s%s%s%s%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('isPositiveFinite')) {
    function isPositiveFinite($v)
    {
        return is_numeric($v) && is_finite((float)$v) && (float)$v > 0;
    }
}

if (!function_exists('isLikelyFakePhone')) {
    function isLikelyFakePhone($phone)
    {
        $phone = preg_replace('/\D+/', '', (string)$phone);
        if (!preg_match('/^09\d{9}$/', $phone)) return true;

        $subscriber = substr($phone, 2); // 9 digits
        if (preg_match('/^(\d)\1{8}$/', $subscriber)) return true;
        if ($subscriber === '123456789' || $subscriber === '987654321') return true;

        $pair = substr($subscriber, 0, 2);
        if ($pair !== '') {
            $pairSeq = str_repeat($pair, 5);
            if (strpos($pairSeq, $subscriber) === 0) return true;
        }

        $triple = substr($subscriber, 0, 3);
        if ($triple !== '') {
            $tripleSeq = str_repeat($triple, 3);
            if (strpos($tripleSeq, $subscriber) === 0) return true;
        }

        return false;
    }
}

if (!function_exists('isWithinServiceArea')) {
    function isWithinServiceArea($addressRaw, $cityRaw = '', $provinceRaw = '')
    {
        $normalize = function ($v) {
            $v = strtolower((string)$v);
            $v = preg_replace('/[^a-z0-9\s]/', ' ', $v);
            $v = preg_replace('/\s+/', ' ', trim($v));
            return $v;
        };

        $address = $normalize($addressRaw);
        $city = $normalize($cityRaw);
        $province = $normalize($provinceRaw);
        $haystack = trim($address . ' ' . $city . ' ' . $province);

        $allowedProvinces = [
            'metro manila', 'ncr', 'manila',
            'cavite', 'laguna', 'rizal', 'bulacan', 'batangas'
        ];
        foreach ($allowedProvinces as $kw) {
            if (strpos($province, $kw) !== false || strpos($haystack, $kw) !== false) {
                return true;
            }
        }

        $allowedCities = [
            'caloocan', 'las pinas', 'las pinas city', 'makati', 'malabon', 'mandaluyong',
            'manila', 'marikina', 'muntinlupa', 'navotas', 'paranaque', 'pasay', 'pasig',
            'pateros', 'quezon city', 'san juan', 'taguig', 'valenzuela',
            'antipolo', 'cainta', 'taytay', 'bacoor', 'imus', 'dasmarinas', 'cavite city',
            'santa rosa', 'san pedro', 'binan', 'calamba', 'sta rosa', 'bi nan',
            'meycauayan', 'marilao', 'bocaue', 'san jose del monte'
        ];
        foreach ($allowedCities as $kw) {
            if (strpos($city, $kw) !== false || strpos($haystack, $kw) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('sanitizeBookingForAdminDashboard')) {
    function sanitizeBookingForAdminDashboard(array $booking)
    {
        unset(
            $booking['email'],
            $booking['customer_id'],
            $booking['phone'],
            $booking['address'],
            $booking['city'],
            $booking['province'],
            $booking['zip_code']
        );
        return $booking;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
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
        if ($age < BOOKING_MIN_FORM_FILL_SECONDS) {
            echo json_encode(['status' => 'error', 'message' => 'Submission too fast. Please review and try again.']);
            exit;
        }
        if ($age > BOOKING_MAX_FORM_AGE_SECONDS) {
            echo json_encode(['status' => 'error', 'message' => 'Form expired. Please refresh and submit again.']);
            exit;
        }
    }
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rateKey = hash('sha256', 'booking_submit|' . $clientIp);
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
    if ($windowStart <= 0 || ($nowTs - $windowStart) >= BOOKING_RATE_LIMIT_WINDOW_SECONDS) {
        $windowStart = $nowTs;
        $attempts = 0;
    }
    $attempts++;

    $newRateData = [
        'window_start' => $windowStart,
        'attempts' => $attempts,
        'updated_at' => $nowTs,
        'endpoint' => 'booking_submit'
    ];
    $ch = curl_init($rateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newRateData));
    curl_exec($ch);
    curl_close($ch);

    if ($attempts > BOOKING_RATE_LIMIT_MAX_ATTEMPTS) {
        $retryAfter = max(1, BOOKING_RATE_LIMIT_WINDOW_SECONDS - ($nowTs - $windowStart));
        echo json_encode([
            'status' => 'error',
            'reason' => 'rate_limited',
            'message' => 'Too many booking attempts. Please wait and try again.',
            'retry_after_seconds' => $retryAfter
        ]);
        exit;
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

    $installDate  = $input['date'] ?? '';
    $lineItemsRaw = isset($input['line_items']) && is_array($input['line_items']) ? $input['line_items'] : null;
    if (!$lineItemsRaw || count($lineItemsRaw) === 0) {
        $lineItemsRaw = [[
            'product_key' => $input['product_key'] ?? '',
            'variant_key' => $input['variant_key'] ?? '',
            'w_screen' => $input['w_screen'] ?? false,
            'width' => $input['width'] ?? 0,
            'height' => $input['height'] ?? 0,
            'qty' => $input['qty'] ?? 1
        ]];
    }
    if (count($lineItemsRaw) > MAX_LINE_ITEMS_PER_QUOTE) {
        echo json_encode(['status' => 'error', 'message' => 'Too many line items.']);
        exit;
    }

    $customerNameRaw = trim((string)($input['name'] ?? 'Guest'));
    $customerName = htmlspecialchars($customerNameRaw, ENT_QUOTES, 'UTF-8');
    $customerEmail= filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $customerAddrRaw = trim((string)($input['address'] ?? ''));
    $customerAddr = htmlspecialchars($customerAddrRaw, ENT_QUOTES, 'UTF-8');
    $customerCityRaw = trim((string)($input['city'] ?? ''));
    $customerProvinceRaw = trim((string)($input['province'] ?? ''));
    $customerZipRaw = trim((string)($input['zip_code'] ?? ''));
    $customerCity = htmlspecialchars($customerCityRaw, ENT_QUOTES, 'UTF-8');
    $customerProvince = htmlspecialchars($customerProvinceRaw, ENT_QUOTES, 'UTF-8');
    $customerZip = preg_replace('/\D+/', '', $customerZipRaw);
    $phoneRaw     = $input['phone'] ?? '';
    $consentGiven = filter_var($input['consent'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $customerPhone = preg_replace('/[^0-9]/', '', $phoneRaw);

    $installDateNorm = jthNormalizeIsoDateInput($installDate);
    $installDateTs = $installDateNorm !== '' ? strtotime($installDateNorm) : false;
    if (DEBUG_BOOKING_LIMIT) {
        error_log('[booking_limit] requested_date_raw=' . $installDate . ' normalized=' . $installDateNorm);
    }

    $lineItems = [];
    $totalQtyRequested = 0;
    foreach ($lineItemsRaw as $rawItem) {
        if (!is_array($rawItem)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid line item payload.']);
            exit;
        }
        $productKey = trim((string)($rawItem['product_key'] ?? ''));
        $variantKey = trim((string)($rawItem['variant_key'] ?? ''));
        $withScreen = filter_var($rawItem['w_screen'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $width = floatval($rawItem['width'] ?? 0);
        $height = floatval($rawItem['height'] ?? 0);
        $qty = intval($rawItem['qty'] ?? 1);

        $validProductKey = preg_match('/^[a-zA-Z0-9_-]{2,80}$/', $productKey);
        $validVariantKey = preg_match('/^[a-zA-Z0-9_-]{2,120}$/', $variantKey);
        $validQty = is_int($qty) && $qty >= 1 && $qty <= MAX_QTY_PER_LINE_ITEM;
        $validDims = isPositiveFinite($width) && isPositiveFinite($height) && $width <= 200 && $height <= 200;

        if (!$validProductKey || !$validVariantKey || !$validQty || !$validDims) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid product item details.']);
            exit;
        }

        $lineItems[] = [
            'product_key' => $productKey,
            'variant_key' => $variantKey,
            'w_screen' => $withScreen,
            'width' => $width,
            'height' => $height,
            'qty' => $qty
        ];
        $totalQtyRequested += $qty;
    }
    if ($totalQtyRequested > MAX_TOTAL_QTY_PER_QUOTE) {
        echo json_encode(['status' => 'error', 'message' => 'Total quantity is too high for one quotation.']);
        exit;
    }

    if (!$installDateNorm || empty($customerPhone) || count($lineItems) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Missing details. Phone, Date, and Product are required.']);
        exit;
    }
    if (mb_strlen($customerNameRaw) < BOOKING_CUSTOMER_NAME_MIN || mb_strlen($customerNameRaw) > BOOKING_CUSTOMER_NAME_MAX) {
        echo json_encode(['status' => 'error', 'message' => 'Name must be between 3 and 80 characters.']);
        exit;
    }
    if (containsDisallowedWords($customerNameRaw)) {
        echo json_encode(['status' => 'error', 'message' => 'Inappropriate words are not allowed in name.']);
        exit;
    }
    if (mb_strlen($customerAddrRaw) < BOOKING_ADDRESS_MIN || mb_strlen($customerAddrRaw) > BOOKING_ADDRESS_MAX) {
        echo json_encode(['status' => 'error', 'message' => 'Address must be between 8 and 220 characters.']);
        exit;
    }
    if (!preg_match('/[A-Za-z]/', $customerAddrRaw)) {
        echo json_encode(['status' => 'error', 'message' => 'Address must include valid place text.']);
        exit;
    }
    if (preg_match('/(.)\1{4,}/', $customerAddrRaw)) {
        echo json_encode(['status' => 'error', 'message' => 'Address looks invalid. Please enter a real location.']);
        exit;
    }
    if (containsDisallowedWords($customerAddrRaw)) {
        echo json_encode(['status' => 'error', 'message' => 'Inappropriate words are not allowed in address.']);
        exit;
    }
    if ($customerCityRaw !== '') {
        if (mb_strlen($customerCityRaw) < 2 || mb_strlen($customerCityRaw) > 80) {
            echo json_encode(['status' => 'error', 'message' => 'City must be between 2 and 80 characters.']);
            exit;
        }
        if (containsDisallowedWords($customerCityRaw)) {
            echo json_encode(['status' => 'error', 'message' => 'Inappropriate words are not allowed in city.']);
            exit;
        }
    }
    if ($customerProvinceRaw !== '') {
        if (mb_strlen($customerProvinceRaw) < 2 || mb_strlen($customerProvinceRaw) > 80) {
            echo json_encode(['status' => 'error', 'message' => 'Province must be between 2 and 80 characters.']);
            exit;
        }
        if (containsDisallowedWords($customerProvinceRaw)) {
            echo json_encode(['status' => 'error', 'message' => 'Inappropriate words are not allowed in province.']);
            exit;
        }
    }
    if ($customerZip !== '' && !preg_match('/^\d{4}$/', $customerZip)) {
        echo json_encode(['status' => 'error', 'message' => 'ZIP code must be exactly 4 digits.']);
        exit;
    }
    if (!isWithinServiceArea($customerAddrRaw, $customerCityRaw, $customerProvinceRaw)) {
        echo json_encode([
            'status' => 'error',
            'reason' => 'service_area',
            'message' => 'Service is currently limited to Metro Manila, Cavite, Laguna, Rizal, Bulacan, and Batangas.'
        ]);
        exit;
    }
    if (isLikelyFakePhone($customerPhone)) {
        echo json_encode(['status' => 'error', 'message' => 'Use a valid PH mobile number. Fake or disposable phone patterns are not allowed.']);
        exit;
    }
    if (!$customerEmail) {
        echo json_encode(['status' => 'error', 'message' => 'A valid email address is required.']);
        exit;
    }
    if (isDisposableEmailDomain($customerEmail)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Temporary/disposable email addresses are not allowed. Please use a permanent email.'
        ]);
        exit;
    }
    $otpSession = $_SESSION['booking_email_otp'] ?? null;
    $otpTokenProvided = trim((string)($input['email_otp_token'] ?? ''));
    $otpVerified = (
        is_array($otpSession) &&
        !empty($otpSession['verified']) &&
        ($otpSession['email'] ?? '') === strtolower((string)$customerEmail) &&
        is_string($otpTokenProvided) &&
        $otpTokenProvided !== '' &&
        hash_equals((string)($otpSession['token'] ?? ''), $otpTokenProvided) &&
        (int)($otpSession['verified_at'] ?? 0) > 0 &&
        (time() - (int)$otpSession['verified_at']) <= BOOKING_EMAIL_OTP_VERIFIED_TTL_SECONDS
    );
    if (!$otpVerified && isPersistentlyVerifiedBookingEmail((string)$customerEmail)) {
        $otpVerified = true;
    }
    if (!$otpVerified && hasExistingCustomerEmail((string)$customerEmail)) {
        $otpVerified = true;
    }
    if (!$otpVerified) {
        echo json_encode([
            'status' => 'error',
            'reason' => 'email_otp_required',
            'message' => 'Email verification is required before booking submission.'
        ]);
        exit;
    }
    $todayNorm = date('Y-m-d');
    if ($installDateNorm < $todayNorm) {
        echo json_encode([
            'status' => 'error',
            'reason' => 'past_date',
            'message' => 'Selected date is in the past. Please choose today or a future date.'
        ]);
        exit;
    }
    if (!$consentGiven) {
        echo json_encode(['status' => 'error', 'message' => 'Consent is required before submitting.']);
        exit;
    }

    $providedIdempotency = trim((string)($input['idempotency_key'] ?? ''));
    if ($providedIdempotency === '') {
        $lineItemsFingerprint = hash('sha256', json_encode($lineItems));
        $providedIdempotency = hash('sha256', implode('|', [
            $clientIp,
            $customerPhone,
            $installDateNorm,
            $lineItemsFingerprint,
            date('Y-m-d-H')
        ]));
    }
    $idempotencyHash = hash('sha256', $providedIdempotency);
    $idempotencyUrl = FIREBASE_URL . "system_settings/idempotency_tokens/" . $idempotencyHash . ".json";
    $ch = curl_init($idempotencyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $idempotencyRaw = curl_exec($ch);
    curl_close($ch);
    $existingIdempotency = json_decode($idempotencyRaw, true);
    if (is_array($existingIdempotency)) {
        $createdTs = (int)($existingIdempotency['created_at'] ?? 0);
        if ($createdTs > 0 && (time() - $createdTs) <= BOOKING_IDEMPOTENCY_TTL_SECONDS) {
            echo json_encode([
                'status' => 'error',
                'reason' => 'duplicate_submission',
                'message' => 'This booking was already submitted.',
                'booking_id' => $existingIdempotency['booking_id'] ?? null
            ]);
            exit;
        }
    }

    $normalizedEmail = $customerEmail ? strtolower(trim($customerEmail)) : '';
    $normalizedPhone = preg_replace('/\D+/', '', $phoneRaw);
    if (!preg_match('/^(63|09)/', $normalizedPhone)) {
        $normalizedPhone = '';
    }
    $identity = $normalizedPhone ?: $normalizedEmail ?: 'booking_submit';
    $cooldownId = $clientIp . '|' . $identity;
    $cooldownKey = 'cooldown_' . hash('sha256', $cooldownId);
    $now = time();
    if (!empty($_SESSION[$cooldownKey]) && ($now - $_SESSION[$cooldownKey]) < COOLDOWN_BOOKING_SECONDS) {
        echo json_encode(['status' => 'error', 'message' => 'Please wait before submitting again.']);
        exit;
    }

    $monthDay = date('m-d', (int)$installDateTs);
    $strictHolidays = ['12-25', '01-01', '11-01', '11-02']; 
    if (in_array($monthDay, $strictHolidays)) {
        echo json_encode(['status' => 'error', 'reason' => 'holiday', 'message' => 'Sorry, we are closed on holidays.']);
        exit;
    }

    $ch = curl_init(FIREBASE_URL . "system_settings/calendar/blocked_dates.json"); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $blockedDates = json_decode(curl_exec($ch), true);
    curl_close($ch);

     if (isset($blockedDates[$installDateNorm])) {
        echo json_encode([
            'status' => 'error', 
            'reason' => 'blocked_date',
            'message' => 'Selected date is currently unavailable. Please choose another date.'
        ]);
        exit;
    }

    
    $ch = curl_init(FIREBASE_URL . "bookings.json"); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $allBookings = json_decode($response, true);

    $dailyCount = 0;
    $maxLimit = MAX_DAILY_BOOKINGS;
    $ignoredStatuses = ['cancelled', 'canceled', 'void', 'completed', 'expired'];

    if (DEBUG_BOOKING_LIMIT) {
        error_log('[booking_limit] requested_date_normalized=' . $installDateNorm);
    }

    if ($allBookings && is_array($allBookings)) {
        foreach ($allBookings as $booking) {

            $bDateRaw = $booking['install_date'] ?? '';
            $bDateNorm = '';
            $bDateTs = null;

            if (is_numeric($bDateRaw)) {
                $num = (int)$bDateRaw;
                if ($num > 0) {
                    $bDateTs = ($num > 100000000000) ? (int)floor($num / 1000) : $num;
                }
            } else {
                $bDateTs = strtotime($bDateRaw);
            }

            if ($bDateTs) {
                $bDateNorm = date('Y-m-d', $bDateTs);
            }

            $status = strtolower(trim($booking['status'] ?? ''));

            if (DEBUG_BOOKING_LIMIT) {
                error_log('[booking_limit] booking_date_raw=' . $bDateRaw . ' booking_date_normalized=' . $bDateNorm . ' status=' . $status);
            }

            if (!$bDateNorm) {
                if (DEBUG_BOOKING_LIMIT) {
                    error_log('[booking_limit] decision=skip reason=invalid_date');
                }
                continue; // Ignore missing/invalid dates
            }

            if ($bDateNorm !== $installDateNorm) {
                if (DEBUG_BOOKING_LIMIT) {
                    error_log('[booking_limit] decision=skip reason=date_mismatch');
                }
                continue;
            }

            if (in_array($status, $ignoredStatuses, true)) {
                if (DEBUG_BOOKING_LIMIT) {
                    error_log('[booking_limit] decision=skip reason=ignored_status');
                }
                continue;
            }

            $dailyCount++;
            if (DEBUG_BOOKING_LIMIT) {
                error_log('[booking_limit] decision=count new_daily_count=' . $dailyCount);
            }
        }
    }
    if (DEBUG_BOOKING_LIMIT) {
        error_log('[booking_limit] final_count=' . $dailyCount . ' limit=' . $maxLimit . ' date=' . $installDateNorm);
    }

    if ($dailyCount >= $maxLimit) {
        echo json_encode([
            'status' => 'error', 
            'reason' => 'fully_booked', // Frontend uses this key
            'message' => "This date is fully booked."
        ]);
        exit; // STOP EXECUTION - Do not save to database
    }

    $productCache = [];
    $computedLineItems = [];
    $totalPrice = 0.0;

    foreach ($lineItems as $item) {
        $productKey = $item['product_key'];
        $variantKey = $item['variant_key'];
        $withScreen = $item['w_screen'];
        $width = (float)$item['width'];
        $height = (float)$item['height'];
        $qty = (int)$item['qty'];

        if (!isset($productCache[$productKey])) {
            $ch = curl_init(FIREBASE_URL . "products/$productKey.json");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $productCache[$productKey] = json_decode(curl_exec($ch), true);
            curl_close($ch);
        }

        $productData = $productCache[$productKey];
        if (!is_array($productData) || !isset($productData['variants']) || !is_array($productData['variants']) || !isset($productData['variants'][$variantKey])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid product or variant selected. Please refresh and try again.']);
            exit;
        }

        $variantData = $productData['variants'][$variantKey];
        $isAvailable = !array_key_exists('is_available', $variantData) || $variantData['is_available'] !== false;
        if (!$isAvailable) {
            echo json_encode(['status' => 'error', 'message' => 'Selected variant is currently unavailable. Please choose another option.']);
            exit;
        }

        $unitPrice = null;
        if ($withScreen) {
            if (isset($variantData['price_w_screen']) && is_numeric($variantData['price_w_screen']) && (float)$variantData['price_w_screen'] > 0) {
                $unitPrice = (float)$variantData['price_w_screen'];
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Screen option is not available for the selected variant.']);
                exit;
            }
        } elseif (isset($variantData['price_no_screen']) && is_numeric($variantData['price_no_screen']) && (float)$variantData['price_no_screen'] > 0) {
            $unitPrice = (float)$variantData['price_no_screen'];
        }

        if (!is_numeric($unitPrice) || (float)$unitPrice <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid pricing data for selected variant. Please contact support.']);
            exit;
        }

        $lineLabel = (string)($productData['display_name'] ?? $productKey) . " (" . (string)($variantData['label'] ?? $variantKey) . ")";
        if ($withScreen) {
            $lineLabel .= " [w/ Screen]";
        }

        $areaSQFT = $width * $height;
        $lineTotal = $areaSQFT * $unitPrice * $qty;
        $totalPrice += $lineTotal;

        $computedLineItems[] = [
            'product_key' => $productKey,
            'variant_key' => $variantKey,
            'w' => $width,
            'h' => $height,
            'qty' => $qty,
            'screen' => $withScreen,
            'unit_price' => $unitPrice,
            'line_total' => round($lineTotal, 2),
            'label' => $lineLabel
        ];
    }

    $minimumOrder = 3000;
    if ($totalPrice < $minimumOrder) {
        $totalPrice = $minimumOrder;
    }
    $totalPrice = round($totalPrice, 2);

    $firstLine = $computedLineItems[0] ?? null;
    $prodName = $firstLine ? $firstLine['label'] : 'Custom Product';
    if (count($computedLineItems) > 1) {
        $prodName = count($computedLineItems) . " Items (" . $prodName . " + more)";
    }

    $customerEntry = [
        "name" => $customerName,
        "phone" => $customerPhone,
        "email" => $customerEmail,
        "address" => $customerAddr,
        "city" => $customerCity,
        "province" => $customerProvince,
        "zip_code" => $customerZip,
        "last_active" => date("Y-m-d H:i:s")
    ];

    $ch = curl_init(FIREBASE_URL . "customers/$customerPhone.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customerEntry));
    curl_exec($ch);
    curl_close($ch);

    $newId = "BK-" . strtoupper(substr(str_replace('-', '', uuidv4()), 0, 8));
    
    $entry = [
        "id" => $newId,
        "customer_id" => $customerPhone,
        "customer_name_snapshot" => $customerName,
        "type" => "quotation", 
        "valid_until" => strtotime("+" . QUOTE_VALIDITY_DAYS . " days"),
        "product" => $prodName,
        "address" => $customerAddr,
        "city" => $customerCity,
        "province" => $customerProvince,
        "zip_code" => $customerZip,
        "line_items" => $computedLineItems,
        "raw_specs" => [
            "w" => $firstLine['w'] ?? 0,
            "h" => $firstLine['h'] ?? 0,
            "screen" => $firstLine['screen'] ?? false,
            "unit_price" => $firstLine['unit_price'] ?? 0
        ],
        "price" => $totalPrice,
        "status" => "Pending", // Default status
        "install_date" => $installDateNorm,
        "created_at" => date("Y-m-d H:i:s"),
        "history" => [[
            "action" => "created",
            "status" => "Pending",
            "actor" => "customer",
            "timestamp" => time(),
            "details" => "Web submission by customer"
        ]]
    ];

    $ch = curl_init(FIREBASE_URL . "bookings/$newId.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($entry));
    curl_exec($ch);
    curl_close($ch);

    $idempotencyData = [
        'created_at' => time(),
        'booking_id' => $newId,
        'phone' => $customerPhone
    ];
    $ch = curl_init($idempotencyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($idempotencyData));
    curl_exec($ch);
    curl_close($ch);

    if ($customerEmail) {
        try {
            $mailSent = sendBookingNotification(
                $customerEmail, 
                $customerName, 
                $newId, 
                $totalPrice, 
                $prodName, 
                $installDateNorm, 
                $customerAddr
            );
            if (!$mailSent) {
                $subject = "Booking Received (#{$newId})";
                $body = buildStatusEmailBody('Pending', [
                    'customer_name' => $customerName,
                    'booking_id' => $newId,
                    'product' => $prodName,
                    'price' => $totalPrice,
                    'preferred_date' => $installDateNorm
                ]);
                efbQueueJob($customerEmail, $subject, $body, ['booking_id' => $newId, 'source' => 'booking_create']);
            }
        } catch (Exception $e) {
            $subject = "Booking Received (#{$newId})";
            $body = buildStatusEmailBody('Pending', [
                'customer_name' => $customerName,
                'booking_id' => $newId,
                'product' => $prodName,
                'price' => $totalPrice,
                'preferred_date' => $installDateNorm
            ]);
            efbQueueJob($customerEmail, $subject, $body, ['booking_id' => $newId, 'source' => 'booking_create']);
        }
    }

    pushRealtimeEvent('booking_created', [
        'id' => $newId,
        'status' => 'Pending',
        'install_date' => $installDateNorm
    ]);


    echo json_encode([
        "status" => "success", 
        "booking_id" => $newId, 
        "message" => "Quotation created. Valid for 7 days."
    ]);
    $_SESSION[$cooldownKey] = time();
    exit;
}

if ($method === 'GET') {
    requireAdminSessionOrJsonError();
    requireCsrfOrJsonError();

    try {
        rcRunIfDue('system:auto_admin_fetch');
    } catch (Throwable $e) {
    }

    if (EMAIL_QUEUE_AUTOPROCESS_ON_ADMIN_FETCH) {
        try {
            efbProcessQueue(1);
        } catch (Throwable $e) {
        }
    }

    $ch = curl_init(FIREBASE_URL . "bookings.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $raw = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($raw, true);
    if (is_array($decoded) && isset($decoded['error'])) {
        http_response_code(503);
        echo json_encode([
            "status" => "error",
            "error" => $decoded['error']
        ]);
        exit;
    }

    if ($decoded === null && $raw !== 'null') {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "error" => "Invalid Firebase response"
        ]);
        exit;
    }

    if (is_array($decoded)) {
        $sanitized = [];
        foreach ($decoded as $booking) {
            if (!is_array($booking)) continue;
            $sanitized[] = sanitizeBookingForAdminDashboard($booking);
        }

        echo json_encode([
            "status" => "success",
            "data" => array_values($sanitized),
            "email_health" => efbGetHealthSnapshot()
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "data" => [],
        "email_health" => efbGetHealthSnapshot()
    ]);
}
?>
