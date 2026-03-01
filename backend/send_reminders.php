<?php
header("Content-Type: application/json");

require_once 'session.php';
require_once 'workflow.php';
applySecurityHeaders(true);
require_once 'mailer.php';

if (!REMINDER_ENABLED) {
    echo json_encode(['status' => 'disabled', 'message' => 'Reminders are disabled.']);
    exit;
}

if (REMINDER_CRON_TOKEN !== '') {
    $token = (string)($_GET['token'] ?? '');
    if (!hash_equals(REMINDER_CRON_TOKEN, $token)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid token.']);
        exit;
    }
}

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$ch = curl_init(FIREBASE_URL . "bookings.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$raw = curl_exec($ch);
curl_close($ch);

$bookings = json_decode($raw, true);
if (!is_array($bookings)) {
    echo json_encode(['status' => 'ok', 'checked' => 0, 'reminders_sent' => 0]);
    exit;
}

$checked = 0;
$sent = 0;
$smsSent = 0;
$updatedBookings = 0;

foreach ($bookings as $firebaseKey => $booking) {
    $checked++;
    if (!is_array($booking)) {
        continue;
    }

    $status = trim((string)($booking['status'] ?? ''));
    if ($status !== 'Site Visit') {
        continue;
    }

    $installDate = jthNormalizeDateLoose($booking['install_date'] ?? '');
    if ($installDate === '') {
        continue;
    }

    $window = null;
    $reminderKey = null;
    if ($installDate === $tomorrow) {
        $window = '24 hours';
        $reminderKey = 'site_visit_24h_sent_at';
    } elseif ($installDate === $today) {
        $window = 'today';
        $reminderKey = 'site_visit_same_day_sent_at';
    } else {
        continue;
    }

    $reminders = isset($booking['reminders']) && is_array($booking['reminders']) ? $booking['reminders'] : [];
    if (!empty($reminders[$reminderKey])) {
        continue;
    }

    $recipientEmail = $booking['email'] ?? null;
    if (!$recipientEmail && isset($booking['customer_id'])) {
        $ch = curl_init(FIREBASE_URL . "customers/" . $booking['customer_id'] . "/email.json");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $recipientEmail = json_decode(curl_exec($ch), true);
        curl_close($ch);
    }
    $customerName = $booking['customer_name_snapshot'] ?? $booking['customer'] ?? 'Customer';
    $bookingId = $booking['id'] ?? $firebaseKey;

    $emailOk = false;
    if ($recipientEmail && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $emailOk = sendEmail(
            $recipientEmail,
            "Site Visit Reminder (#$bookingId)",
            buildSiteVisitReminderEmail([
                'customer_name' => $customerName,
                'booking_id' => $bookingId,
                'install_date' => $installDate,
                'window_label' => $window
            ])
        );
    }

    $phone = $booking['customer_id'] ?? '';
    $smsOk = false;
    if ($phone !== '') {
        $smsOk = sendSmsReminder(
            $phone,
            "JTH reminder ($bookingId): site visit schedule is $installDate ($window)."
        );
    }

    if ($emailOk || $smsOk) {
        $reminders[$reminderKey] = time();
        $patch = ['reminders' => $reminders];
        $ch = curl_init(FIREBASE_URL . "bookings/$firebaseKey.json");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($patch));
        curl_exec($ch);
        curl_close($ch);
        $updatedBookings++;
    }

    if ($emailOk) {
        $sent++;
    }
    if ($smsOk) {
        $smsSent++;
    }
}

echo json_encode([
    'status' => 'ok',
    'checked' => $checked,
    'reminder_emails_sent' => $sent,
    'reminder_sms_sent' => $smsSent,
    'bookings_updated' => $updatedBookings,
    'date_today' => $today,
    'date_tomorrow' => $tomorrow
]);
