<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'session.php';
applySecurityHeaders(true);

requireAdminSessionOrJsonError();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
requireCsrfOrJsonError();

$ch = curl_init(FIREBASE_URL . "bookings.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$allBookings = json_decode(curl_exec($ch), true);
curl_close($ch);

if (!$allBookings) {
    echo json_encode(['status' => 'success', 'message' => 'No bookings to check', 'voided_count' => 0]);
    exit;
}

$now = time();
$voidedCount = 0;
$updatesLog = [];

foreach ($allBookings as $key => $booking) {
    
    if (!isset($booking['status']) || $booking['status'] !== 'Pending') {
        continue;
    }

    if (!isset($booking['valid_until']) || empty($booking['valid_until'])) {
        continue;
    }

    $validUntil = intval($booking['valid_until']);

    if ($now > $validUntil) {
        
        $history = [];
        if (isset($booking['history']) && is_array($booking['history'])) { $history = $booking['history']; }

        $oldStatus = $booking['status'] ?? 'Pending';
        $history[] = [
            "action" => "auto_void",
            "old_status" => $oldStatus,
            "new_status" => "Void",
            "actor" => "system",
            "reason" => "Quotation expired (7 days passed)",
            "timestamp" => time(),
            "details" => "System: Quotation expired (7 days passed)"
        ];

        $updateData = [
            "status" => "Void",
            "history" => $history,
            "updated_at" => date("Y-m-d H:i:s")
        ];

        $url = FIREBASE_URL . "bookings/$key.json";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
        curl_exec($ch);
        curl_close($ch);

        $voidedCount++;
        $updatesLog[] = $booking['id'];
    }
}

echo json_encode([
    'status' => 'success',
    'message' => "Expiry check complete",
    'voided_count' => $voidedCount,
    'voided_ids' => $updatesLog
]);
?>
