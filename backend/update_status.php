<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'session.php';
applySecurityHeaders(true);
require_once 'mailer.php'; 
require_once 'email_fallback.php';
require_once 'ws_events.php';
require_once 'workflow.php';

requireAdminSessionOrJsonError();
requireCsrfOrJsonError();

$bookingId = $_POST['id'] ?? null;
$newStatus = $_POST['status'] ?? null;
$changeReason = trim((string)($_POST['reason'] ?? ''));
$reasonCode = trim((string)($_POST['reason_code'] ?? ''));
$newInstallDate = trim((string)($_POST['install_date'] ?? ''));

if (!$bookingId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $bookingId = $input['id'] ?? null;
    $newStatus = $input['status'] ?? null;
    $changeReason = trim((string)($input['reason'] ?? ''));
    $reasonCode = trim((string)($input['reason_code'] ?? ''));
    $newInstallDate = trim((string)($input['install_date'] ?? ''));
}

if (!$bookingId || !$newStatus) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID or Status']);
    exit;
}
$bookingId = trim((string)$bookingId);
$newStatus = trim((string)$newStatus);
if (!preg_match('/^BK-[A-Z0-9]{6,16}$/', strtoupper($bookingId))) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID format']);
    exit;
}
if (mb_strlen($changeReason) > 300) {
    echo json_encode(['status' => 'error', 'message' => 'Reason is too long (max 300 chars).']);
    exit;
}
if ($changeReason !== '' && !preg_match('/^[\p{L}\p{N}\s\.,:;!\?\'"()\-\/#&@]+$/u', $changeReason)) {
    echo json_encode(['status' => 'error', 'message' => 'Reason contains unsupported characters.']);
    exit;
}
$reasonCode = strtolower(str_replace([' ', '-'], '_', $reasonCode));

$allowedStatuses = jthAllowedStatuses();
if (!in_array($newStatus, $allowedStatuses, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status value']);
    exit;
}
if ($newInstallDate !== '') {
    $normalizedDate = jthNormalizeIsoDateInput($newInstallDate);
    if ($normalizedDate === '') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid install_date format. Use YYYY-MM-DD.']);
        exit;
    }
    $newInstallDate = $normalizedDate;
}

$reasonCategories = [
    'Cancelled' => ['customer_request', 'unresponsive_customer', 'duplicate_booking', 'pricing_issue', 'service_area_limit', 'other'],
    'Void' => ['quotation_expired', 'no_response_after_quote', 'duplicate_submission', 'invalid_request', 'other'],
    'Completed' => ['installation_done', 'project_handover_done', 'other']
];
if (($newStatus === 'Cancelled' || $newStatus === 'Void')) {
    if ($reasonCode === '' || !in_array($reasonCode, $reasonCategories[$newStatus], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a valid reason category for Cancelled/Void status.']);
        exit;
    }
}
if ($newStatus === 'Completed' && $reasonCode !== '' && !in_array($reasonCode, $reasonCategories['Completed'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid completion category.']);
    exit;
}
if ($newStatus === 'Completed' && mb_strlen($changeReason) < 5) {
    echo json_encode(['status' => 'error', 'message' => 'Completion note is required (min 5 chars) before marking as Completed.']);
    exit;
}

$ch = curl_init(FIREBASE_URL . "bookings.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$allBookings = json_decode(curl_exec($ch), true);
curl_close($ch);

$firebaseKey = null;
$bookingData = null;

if ($allBookings) {
    foreach ($allBookings as $key => $booking) {
        if (isset($booking['id']) && $booking['id'] === $bookingId) {
            $firebaseKey = $key;
            $bookingData = $booking;
            break;
        }
    }
}

if (!$firebaseKey) {
    echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
    exit;
}

$currentStatus = (string)($bookingData['status'] ?? 'Pending');

if (jthIsTerminalStatus($currentStatus)) {
    echo json_encode([
        'status' => 'error', 
        'message' => "Action Denied: This record is locked ($currentStatus). No further changes allowed."
    ]);
    exit;
}

$validTransitions = jthWorkflowTransitions();

$statusChanged = ($newStatus !== $currentStatus);
if ($statusChanged && isset($validTransitions[$currentStatus])) {
    if (!in_array($newStatus, $validTransitions[$currentStatus])) {
        echo json_encode([
            'status' => 'error', 
            'message' => "Invalid Move: You cannot go from '$currentStatus' to '$newStatus'. Workflow violation."
        ]);
        exit;
    }
}


$updates = ['status' => $newStatus];
$currentInstallDate = trim((string)($bookingData['install_date'] ?? ''));
$dateChanged = false;

if ($newInstallDate !== '' && $newInstallDate !== $currentInstallDate) {
    if ($newInstallDate < date('Y-m-d')) {
        echo json_encode(['status' => 'error', 'message' => 'Install date cannot be in the past.']);
        exit;
    }
    $updates['install_date'] = $newInstallDate;
    $dateChanged = true;
}
$effectiveInstallDateRaw = $updates['install_date'] ?? $currentInstallDate;
$effectiveInstallDate = jthNormalizeDateLoose($effectiveInstallDateRaw);
if ($statusChanged && $effectiveInstallDate !== '' && $effectiveInstallDate < date('Y-m-d')) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Cannot update status for a past schedule date. Please reschedule to today or a future date first.'
    ]);
    exit;
}
if (($newStatus === 'Site Visit' || $newStatus === 'Installation') && jthNormalizeIsoDateInput($effectiveInstallDate) === '') {
    echo json_encode(['status' => 'error', 'message' => "A valid schedule date (YYYY-MM-DD) is required for {$newStatus}."]);
    exit;
}
if (!$statusChanged && !$dateChanged) {
    echo json_encode(['status' => 'error', 'message' => 'No changes detected.']);
    exit;
}

if ($newStatus === 'Confirmed' && ($bookingData['type'] ?? '') === 'quotation') {
    $updates['type'] = 'job';
}

$history = [];
if (isset($bookingData['history']) && is_array($bookingData['history'])) {
    $history = $bookingData['history'];
}

$historyLog = [
    "action" => "status_change",
    "old_status" => $currentStatus,
    "new_status" => $newStatus,
    "actor" => $_SESSION['jth_admin_user'] ?? ADMIN_USER,
    "timestamp" => time(),
    "reason_code" => $reasonCode,
    "reason" => $changeReason,
    "details" => $changeReason !== '' ? ("Admin updated status: " . $changeReason) : "Admin updated status"
];
$history[] = $historyLog; // Append

if ($dateChanged) {
    $history[] = [
        "action" => "schedule_change",
        "old_status" => $currentStatus,
        "new_status" => $newStatus,
        "actor" => $_SESSION['jth_admin_user'] ?? ADMIN_USER,
        "timestamp" => time(),
        "reason_code" => $reasonCode,
        "reason" => $changeReason,
        "details" => "Admin changed schedule from " . ($currentInstallDate ?: 'not set') . " to " . $newInstallDate
    ];
}
$updates['history'] = $history; // Add to update payload

$updateUrl = FIREBASE_URL . "bookings/$firebaseKey.json";
$ch = curl_init($updateUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updates));
$updateHttpHeaders = ['Content-Type: application/json'];
curl_setopt($ch, CURLOPT_HTTPHEADER, $updateHttpHeaders);
$updateErrNo = 0;
$updateHttp = 0;
$result = curl_exec($ch);
$updateErrNo = curl_errno($ch);
$updateHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($updateErrNo !== 0 || $result === false || $result === '') {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'message' => 'Status update failed. Please retry.',
        'reason' => 'firebase_unreachable'
    ]);
    exit;
}

$updateDecoded = json_decode($result, true);
if ($updateHttp >= 400 || (is_array($updateDecoded) && isset($updateDecoded['error']))) {
    $firebaseMessage = is_array($updateDecoded) && isset($updateDecoded['error'])
        ? (is_string($updateDecoded['error']) ? $updateDecoded['error'] : 'Permission denied')
        : ('HTTP ' . $updateHttp);
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Status update rejected by database rules.',
        'reason' => 'firebase_rejected',
        'details' => $firebaseMessage
    ]);
    exit;
}

$recipientEmail = $bookingData['email'] ?? null;
$customerName   = $bookingData['customer_name_snapshot'] ?? $bookingData['customer'] ?? 'Customer';

if (!$recipientEmail && isset($bookingData['customer_id'])) {
    $cId = $bookingData['customer_id'];
    $ch = curl_init(FIREBASE_URL . "customers/$cId/email.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $fetchedEmail = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if ($fetchedEmail) { $recipientEmail = $fetchedEmail; }
}

$emailSent = false;
$scheduleEmailSent = false;
$smsSent = false;
if ($recipientEmail && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    $subject = buildStatusEmailSubject($newStatus, $bookingId);
    $body = buildStatusEmail([
        'status' => $newStatus,
        'customer_name' => $customerName,
        'booking_id' => $bookingId,
        'product' => $bookingData['product'] ?? '—',
        'price' => $bookingData['price'] ?? 0,
        'preferred_date' => $updates['install_date'] ?? ($bookingData['install_date'] ?? ($bookingData['date_created'] ?? '—'))
    ]);
    $emailSent = sendEmail($recipientEmail, $subject, $body);
    if (!$emailSent) {
        efbQueueJob($recipientEmail, $subject, $body, ['booking_id' => $bookingId, 'source' => 'status_update']);
    }

    if ($dateChanged) {
        $scheduleSubject = "Schedule Updated (#$bookingId)";
        $scheduleBody = buildScheduleChangedEmail([
            'customer_name' => $customerName,
            'booking_id' => $bookingId,
            'from_date' => $currentInstallDate ?: 'Not set',
            'to_date' => $newInstallDate
        ]);
        $scheduleEmailSent = sendEmail(
            $recipientEmail,
            $scheduleSubject,
            $scheduleBody
        );
        if (!$scheduleEmailSent) {
            efbQueueJob($recipientEmail, $scheduleSubject, $scheduleBody, ['booking_id' => $bookingId, 'source' => 'status_schedule_change']);
        }
    } elseif ($newStatus === 'Site Visit' && ($updates['install_date'] ?? $currentInstallDate) !== '') {
        $visitSubject = "Site Visit Schedule (#$bookingId)";
        $visitBody = buildSiteVisitReminderEmail([
            'customer_name' => $customerName,
            'booking_id' => $bookingId,
            'install_date' => $updates['install_date'] ?? $currentInstallDate,
            'window_label' => 'Scheduled'
        ]);
        $scheduleEmailSent = sendEmail(
            $recipientEmail,
            $visitSubject,
            $visitBody
        );
        if (!$scheduleEmailSent) {
            efbQueueJob($recipientEmail, $visitSubject, $visitBody, ['booking_id' => $bookingId, 'source' => 'status_site_visit']);
        }
    }
}

if (REMINDER_ENABLED) {
    $phoneForSms = $bookingData['customer_id'] ?? '';
    if ($phoneForSms !== '') {
        if ($dateChanged) {
            $smsSent = sendSmsReminder(
                $phoneForSms,
                "JTH update ($bookingId): schedule changed from " . ($currentInstallDate ?: 'not set') . " to " . $newInstallDate . "."
            );
        } elseif ($newStatus === 'Site Visit' && ($updates['install_date'] ?? $currentInstallDate) !== '') {
            $smsSent = sendSmsReminder(
                $phoneForSms,
                "JTH reminder ($bookingId): your site visit is scheduled on " . ($updates['install_date'] ?? $currentInstallDate) . "."
            );
        }
    }
}

pushRealtimeEvent('status_updated', [
    'id' => $bookingId,
    'old_status' => $currentStatus,
    'new_status' => $newStatus,
    'actor' => $_SESSION['jth_admin_user'] ?? ADMIN_USER
]);

echo json_encode([
    'status' => 'success', 
    'message' => "Updated to $newStatus",
    'history_count' => count($history),
    'email_sent' => $emailSent,
    'schedule_email_sent' => $scheduleEmailSent,
    'sms_sent' => $smsSent,
    'actor' => $_SESSION['jth_admin_user'] ?? ADMIN_USER
]);
?>
