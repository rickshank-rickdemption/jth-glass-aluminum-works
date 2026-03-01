<?php
header("Content-Type: application/json");

require_once 'session.php';
startSecureSession();
applySecurityHeaders(true);
require_once 'ws_auth.php';

if (!defined('TRACK_RATE_LIMIT_WINDOW_SECONDS')) {
    define('TRACK_RATE_LIMIT_WINDOW_SECONDS', 600); // 10 mins
}
if (!defined('TRACK_RATE_LIMIT_MAX_ATTEMPTS')) {
    define('TRACK_RATE_LIMIT_MAX_ATTEMPTS', 30);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload.']);
    exit;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = hash('sha256', 'track_booking|' . $clientIp);
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
if ($windowStart <= 0 || ($nowTs - $windowStart) >= TRACK_RATE_LIMIT_WINDOW_SECONDS) {
    $windowStart = $nowTs;
    $attempts = 0;
}
$attempts++;

$newRateData = [
    'window_start' => $windowStart,
    'attempts' => $attempts,
    'updated_at' => $nowTs,
    'endpoint' => 'track_booking'
];
$ch = curl_init($rateUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($newRateData));
curl_exec($ch);
curl_close($ch);

if ($attempts > TRACK_RATE_LIMIT_MAX_ATTEMPTS) {
    $retryAfter = max(1, TRACK_RATE_LIMIT_WINDOW_SECONDS - ($nowTs - $windowStart));
    http_response_code(429);
    echo json_encode([
        'status' => 'error',
        'reason' => 'rate_limited',
        'message' => 'Too many tracking attempts. Please wait and try again.',
        'retry_after_seconds' => $retryAfter
    ]);
    exit;
}

$bookingId = strtoupper(trim((string)($input['booking_id'] ?? '')));
$email = strtolower(trim((string)($input['email'] ?? '')));
$phone = preg_replace('/\D+/', '', (string)($input['phone'] ?? ''));

if (!preg_match('/^BK-[A-Z0-9]{6,16}$/', $bookingId)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid booking reference format.']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
    exit;
}
if ($phone !== '' && !preg_match('/^\d{11,13}$/', $phone)) {
    echo json_encode(['status' => 'error', 'message' => 'Phone must be 11 to 13 digits.']);
    exit;
}

$ch = curl_init(FIREBASE_URL . "bookings/" . rawurlencode($bookingId) . ".json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$bookingRaw = curl_exec($ch);
curl_close($ch);
$booking = json_decode($bookingRaw, true);

if (!is_array($booking) || isset($booking['error']) || empty($booking['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No booking found for provided details.']);
    exit;
}

$bookingPhone = preg_replace('/\D+/', '', (string)($booking['customer_id'] ?? ($booking['phone'] ?? '')));
$bookingEmail = strtolower(trim((string)($booking['email'] ?? '')));

if ($bookingEmail === '' && $bookingPhone !== '') {
    $ch = curl_init(FIREBASE_URL . "customers/" . rawurlencode($bookingPhone) . ".json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $custRaw = curl_exec($ch);
    curl_close($ch);
    $cust = json_decode($custRaw, true);
    if (is_array($cust) && !empty($cust['email'])) {
        $bookingEmail = strtolower(trim((string)$cust['email']));
    }
}

$contactMatched = ($email === '' && $phone === '');
if ($email !== '' && $bookingEmail !== '' && hash_equals($bookingEmail, $email)) {
    $contactMatched = true;
}
if ($phone !== '' && $bookingPhone !== '' && hash_equals($bookingPhone, $phone)) {
    $contactMatched = true;
}

if (!$contactMatched) {
    echo json_encode(['status' => 'error', 'message' => 'No booking found for provided details.']);
    exit;
}

$history = [];
if (isset($booking['history']) && is_array($booking['history'])) {
    $history = $booking['history'];
}
usort($history, function ($a, $b) {
    $ta = (int)($a['timestamp'] ?? 0);
    $tb = (int)($b['timestamp'] ?? 0);
    return $ta <=> $tb;
});

$timeline = [];
foreach ($history as $h) {
    if (!is_array($h)) continue;
    $timeline[] = [
        'action' => (string)($h['action'] ?? 'update'),
        'from' => (string)($h['old_status'] ?? ($h['from'] ?? '')),
        'to' => (string)($h['new_status'] ?? ($h['to'] ?? ($h['status'] ?? ''))),
        'reason_code' => (string)($h['reason_code'] ?? ''),
        'reason' => (string)($h['reason'] ?? ($h['details'] ?? '')),
        'timestamp' => (int)($h['timestamp'] ?? 0)
    ];
}

echo json_encode([
    'status' => 'success',
    'ws_token' => generateWsToken([
        'scope' => 'track',
        'booking_id' => $bookingId
    ], min(43200, max(900, (int)WS_TOKEN_TTL_SECONDS))),
    'booking' => [
        'id' => (string)$booking['id'],
        'customer' => (string)($booking['customer_name_snapshot'] ?? 'Customer'),
        'product' => (string)($booking['product'] ?? '—'),
        'amount' => (float)($booking['price'] ?? 0),
        'status' => (string)($booking['status'] ?? 'Pending'),
        'install_date' => (string)($booking['install_date'] ?? '—'),
        'created_at' => (string)($booking['created_at'] ?? ''),
        'timeline' => $timeline
    ]
]);
