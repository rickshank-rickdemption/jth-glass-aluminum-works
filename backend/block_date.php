<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'session.php';
require_once 'workflow.php';
applySecurityHeaders(true);
require_once 'ws_events.php';

requireAdminSessionOrJsonError();
requireCsrfOrJsonError();

$date = $_POST['date'] ?? '';
$reason = trim((string)($_POST['reason'] ?? 'Busy'));

if (!$date) {
    echo json_encode(['status' => 'error', 'message' => 'Date is required']);
    exit;
}
if (jthNormalizeIsoDateInput($date) === '') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date format.']);
    exit;
}
$today = date('Y-m-d');
if ($date < $today) {
    echo json_encode(['status' => 'error', 'message' => 'Cannot block a past date.']);
    exit;
}
if ($reason === '') {
    $reason = 'Busy';
}
if (mb_strlen($reason) > 120) {
    echo json_encode(['status' => 'error', 'message' => 'Reason is too long (max 120 chars).']);
    exit;
}
if (!preg_match('/^[\p{L}\p{N}\s\.,:;!\?\'"()\-\/#&@]+$/u', $reason)) {
    echo json_encode(['status' => 'error', 'message' => 'Reason contains unsupported characters.']);
    exit;
}
$reason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

function normalizeDateValue($raw)
{
    if (is_numeric($raw)) {
        $num = (int)$raw;
        if ($num > 0) {
            $ts = $num > 100000000000 ? (int)floor($num / 1000) : $num;
            return date('Y-m-d', $ts);
        }
    }
    $ts = strtotime((string)$raw);
    if ($ts === false) return '';
    return date('Y-m-d', $ts);
}

$bookingsUrl = FIREBASE_URL . "bookings.json";
$ch = curl_init($bookingsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$bookingsRaw = curl_exec($ch);
curl_close($ch);
$allBookings = json_decode($bookingsRaw, true);

$blockedStatuses = ['cancelled', 'canceled', 'void', 'completed', 'expired'];
$conflicts = [];
if (is_array($allBookings)) {
    foreach ($allBookings as $booking) {
        if (!is_array($booking)) continue;
        $bDate = normalizeDateValue($booking['install_date'] ?? '');
        if ($bDate !== $date) continue;
        $status = strtolower(trim((string)($booking['status'] ?? 'pending')));
        if (in_array($status, $blockedStatuses, true)) continue;
        $conflicts[] = (string)($booking['id'] ?? 'N/A');
    }
}

if (count($conflicts) > 0) {
    echo json_encode([
        'status' => 'error',
        'reason' => 'active_bookings_exist',
        'message' => 'Cannot block this date because there are active bookings scheduled.',
        'conflict_count' => count($conflicts),
        'conflict_ids' => array_slice($conflicts, 0, 5)
    ]);
    exit;
}

$url = FIREBASE_URL . "system_settings/calendar/blocked_dates.json";
$data = json_encode([$date => $reason]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
$response = curl_exec($ch);
curl_close($ch);

pushRealtimeEvent('date_blocked', [
    'date' => $date,
    'reason' => $reason
]);

echo json_encode(['status' => 'success', 'message' => "Blocked $date"]);
?>
