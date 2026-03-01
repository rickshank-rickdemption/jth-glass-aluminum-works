<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'session.php';
require_once 'workflow.php';
applySecurityHeaders(true);
require_once 'ws_events.php';

requireAdminSessionOrJsonError();
requireCsrfOrJsonError();

$date = trim((string)($_POST['date'] ?? ''));
if ($date === '' || jthNormalizeIsoDateInput($date) === '') {
    echo json_encode(['status' => 'error', 'message' => 'Valid date is required.']);
    exit;
}

$date = jthNormalizeIsoDateInput($date);
$blocked = null;
$ch = curl_init(FIREBASE_URL . "system_settings/calendar/blocked_dates.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$blocked = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!is_array($blocked) || !array_key_exists($date, $blocked)) {
    echo json_encode(['status' => 'error', 'message' => 'Date is not currently blocked.']);
    exit;
}

$ch = curl_init(FIREBASE_URL . "system_settings/calendar/blocked_dates/" . rawurlencode($date) . ".json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_exec($ch);
curl_close($ch);

pushRealtimeEvent('date_unblocked', ['date' => $date]);

echo json_encode(['status' => 'success', 'message' => "Unblocked $date"]);
?>

