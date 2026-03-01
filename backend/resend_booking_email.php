<?php
header("Content-Type: application/json");

require_once 'session.php';
applySecurityHeaders(true);
require_once 'mailer.php';
require_once 'email_fallback.php';

requireAdminSessionOrJsonError();
requireCsrfOrJsonError();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$bookingId = trim((string)($_POST['id'] ?? ($input['id'] ?? '')));
if (!preg_match('/^BK-[A-Z0-9]{6,16}$/', strtoupper($bookingId))) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid booking ID format.']);
    exit;
}

$booking = efbFirebaseRequest('GET', "bookings/" . rawurlencode($bookingId) . ".json");
if (!is_array($booking) || empty($booking['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Booking not found.']);
    exit;
}

$recipientEmail = $booking['email'] ?? '';
if ((!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) && !empty($booking['customer_id'])) {
    $customer = efbFirebaseRequest('GET', "customers/" . rawurlencode((string)$booking['customer_id']) . ".json");
    if (is_array($customer) && !empty($customer['email'])) {
        $recipientEmail = (string)$customer['email'];
    }
}

if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'No valid customer email found for this booking.']);
    exit;
}

$status = (string)($booking['status'] ?? 'Pending');
$customerName = (string)($booking['customer_name_snapshot'] ?? $booking['customer'] ?? 'Customer');
$subject = buildStatusEmailSubject($status, $bookingId);
$body = buildStatusEmailBody($status, [
    'customer_name' => $customerName,
    'booking_id' => $bookingId,
    'product' => $booking['product'] ?? '—',
    'price' => $booking['price'] ?? 0,
    'preferred_date' => $booking['install_date'] ?? ($booking['created_at'] ?? '—')
]);

$sent = false;
try {
    $sent = sendEmail($recipientEmail, $subject, $body);
} catch (Throwable $e) {
    $sent = false;
}

if ($sent) {
    echo json_encode(['status' => 'success', 'message' => 'Email resent successfully.']);
    exit;
}

$jobId = efbQueueJob($recipientEmail, $subject, $body, [
    'booking_id' => $bookingId,
    'source' => 'manual_resend'
]);
echo json_encode([
    'status' => 'success',
    'queued' => true,
    'queue_id' => $jobId,
    'message' => 'SMTP unavailable. Email queued for retry.'
]);
?>

