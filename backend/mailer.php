<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';

function sendEmail($to, $subject, $bodyHTML, $attachments = []) {
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 0; 
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $secureMode = strtolower(trim((string)SMTP_SECURE));
        if ($secureMode === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secureMode === 'none' || $secureMode === '') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = max(5, (int)SMTP_TIMEOUT_SECONDS);
        $mail->SMTPKeepAlive = false;

        $mail->setFrom(SMTP_USER, 'JTH Glass & Aluminum Works');
        $mail->addAddress($to); 

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHTML;
        $mail->AltBody = strip_tags(str_replace("<br>", "\n", $bodyHTML));

        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (!is_array($attachment)) {
                    continue;
                }
                $path = $attachment['path'] ?? '';
                $name = $attachment['name'] ?? '';
                if (!is_string($path) || $path === '' || !is_file($path)) {
                    continue;
                }
                if (is_string($name) && $name !== '') {
                    $mail->addAttachment($path, $name);
                } else {
                    $mail->addAttachment($path);
                }
            }
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        appLog('error', 'mailer_send_failed', ['to' => $to, 'subject' => $subject, 'error' => $mail->ErrorInfo]);
        return false;
    }
}

function sendSmsReminder($toPhone, $message) {
    if (!SMS_REMINDER_ENABLED || SMS_REMINDER_WEBHOOK_URL === '') {
        return false;
    }

    $phone = preg_replace('/\D+/', '', (string)$toPhone);
    if ($phone === '' || trim((string)$message) === '') {
        return false;
    }

    $payload = [
        'to' => $phone,
        'message' => trim((string)$message),
        'source' => 'jth_glass_system'
    ];

    $headers = ['Content-Type: application/json'];
    if (SMS_REMINDER_API_KEY !== '') {
        $headers[] = 'Authorization: Bearer ' . SMS_REMINDER_API_KEY;
    }

    $ch = curl_init(SMS_REMINDER_WEBHOOK_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    $raw = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    curl_close($ch);

    if ($errno !== 0 || $http < 200 || $http >= 300) {
        appLog('error', 'sms_reminder_failed', [
            'to' => $phone,
            'http_code' => $http,
            'curl_errno' => $errno,
            'response' => is_string($raw) ? substr($raw, 0, 300) : ''
        ]);
        return false;
    }
    return true;
}

function getStatusEmailMeta($status) {
    $status = trim((string)$status);
    $map = [
        'Pending' => [
            'headline' => 'Quotation Created',
            'message' => 'Your quotation has been created and is pending admin review.',
            'badge' => 'Pending',
            'badge_bg' => '#FEF3C7',
            'badge_text' => '#92400E'
        ],
        'Site Visit' => [
            'headline' => 'Your Site Visit is Scheduled',
            'message' => 'Our team is ready for the site visit on your preferred date.',
            'badge' => 'Site Visit',
            'badge_bg' => '#E0E7FF',
            'badge_text' => '#3730A3'
        ],
        'Confirmed' => [
            'headline' => 'Booking Confirmed',
            'message' => 'Your booking has been confirmed. We will proceed with preparation.',
            'badge' => 'Confirmed',
            'badge_bg' => '#DCFCE7',
            'badge_text' => '#166534'
        ],
        'Fabrication' => [
            'headline' => 'Fabrication Started',
            'message' => 'Your order is now in fabrication.',
            'badge' => 'Fabrication',
            'badge_bg' => '#FFEDD5',
            'badge_text' => '#9A3412'
        ],
        'Installation' => [
            'headline' => 'Installation Scheduled',
            'message' => 'Your installation is scheduled. We will be in touch with final details.',
            'badge' => 'Installation',
            'badge_bg' => '#EDE9FE',
            'badge_text' => '#5B21B6'
        ],
        'Completed' => [
            'headline' => 'Project Completed',
            'message' => 'Your project has been marked as completed.',
            'badge' => 'Completed',
            'badge_bg' => '#ECFDF3',
            'badge_text' => '#047857'
        ],
        'Cancelled' => [
            'headline' => 'Booking Cancelled',
            'message' => 'Your booking has been cancelled. If this is unexpected, please contact support.',
            'badge' => 'Cancelled',
            'badge_bg' => '#FEE2E2',
            'badge_text' => '#991B1B',
            'cta_label' => 'Contact Support',
            'cta_url' => 'mailto:' . SMTP_USER
        ],
        'Void' => [
            'headline' => 'Booking Voided',
            'message' => 'Your booking has been voided. If you need assistance, contact support.',
            'badge' => 'Void',
            'badge_bg' => '#FEE2E2',
            'badge_text' => '#991B1B',
            'cta_label' => 'Contact Support',
            'cta_url' => 'mailto:' . SMTP_USER
        ],
        'Expired' => [
            'headline' => 'Quotation Expired',
            'message' => 'Your quotation has expired. You can request a new quotation anytime.',
            'badge' => 'Expired',
            'badge_bg' => '#FEE2E2',
            'badge_text' => '#991B1B',
            'cta_label' => 'Contact Support',
            'cta_url' => 'mailto:' . SMTP_USER
        ]
    ];
    return $map[$status] ?? [
        'headline' => 'Status Update',
        'message' => 'Your booking status has been updated.',
        'badge' => $status ?: 'Updated',
        'badge_bg' => '#E4E4E7',
        'badge_text' => '#18181B'
    ];
}

function buildStatusEmail($data) {
    $meta = getStatusEmailMeta($data['status'] ?? 'Pending');
    $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost:8000';

    $customer = $data['customer_name'] ?? 'Customer';
    $bookingId = $data['booking_id'] ?? '—';
    $product = $data['product'] ?? '—';
    $price = isset($data['price']) ? number_format((float)$data['price'], 2) : '—';
    $preferredDate = $data['preferred_date'] ?? '—';
    $ctaLabel = $meta['cta_label'] ?? '';
    $ctaUrl = $meta['cta_url'] ?? '';

    $ctaHtml = '';
    if ($ctaLabel && $ctaUrl) {
        $ctaHtml = "<tr><td align='center' style='padding:0 24px 24px 24px;'>
            <a href='$ctaUrl' style='display:inline-block;background:#18181B;color:#FAFAFA;text-decoration:none;padding:10px 16px;border-radius:8px;font-size:12px;font-weight:600;letter-spacing:0.01em;'>$ctaLabel</a>
        </td></tr>";
    }

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    </head>
    <body style='margin:0;padding:24px;background:#F4F4F5;font-family:Inter,Segoe UI,Arial,sans-serif;color:#18181B;'>
        <table width='100%' border='0' cellspacing='0' cellpadding='0'>
            <tr>
                <td align='center'>
                    <table width='620' border='0' cellspacing='0' cellpadding='0' style='max-width:620px;background:#FFFFFF;border:1px solid #E4E4E7;border-radius:12px;overflow:hidden;box-shadow:0 3px 12px rgba(0,0,0,0.06);'>
                        <tr>
                            <td style='padding:20px 24px;background:#FAFAFA;border-bottom:1px solid #E4E4E7;color:#111827;'>
                                <div style='font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#71717A;font-weight:700;'>JTH Glass & Aluminum Works</div>
                                <div style='font-size:20px;font-weight:700;margin-top:8px;color:#18181B;'>".$meta['headline']."</div>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding:20px 24px 10px 24px;'>
                                <div style='font-size:14px;line-height:1.65;color:#3F3F46;'>".$meta['message']."</div>
                                <div style='margin-top:14px;display:inline-block;background:".$meta['badge_bg'].";color:".$meta['badge_text'].";padding:6px 11px;border-radius:999px;font-size:11px;font-weight:600;'>".$meta['badge']."</div>
                            </td>
                        </tr>
                        <tr>
                            <td style='padding:10px 24px 20px 24px;'>
                                <table width='100%' cellspacing='0' cellpadding='0' style='border:1px solid #E4E4E7;border-radius:10px;overflow:hidden;'>
                                    <tr><td style='padding:12px 14px;background:#FAFAFA;font-size:11px;color:#71717A;text-transform:uppercase;letter-spacing:.08em;font-weight:600;' colspan='2'>Booking Details</td></tr>
                                    <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Reference ID</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:700;color:#18181B;border-top:1px solid #E4E4E7;'>$bookingId</td></tr>
                                    <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Customer</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:600;color:#27272A;border-top:1px solid #E4E4E7;'>$customer</td></tr>
                                    <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Product</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:600;color:#27272A;border-top:1px solid #E4E4E7;'>$product</td></tr>
                                    <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Estimated Price</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:700;color:#18181B;border-top:1px solid #E4E4E7;'>₱$price</td></tr>
                                    <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Preferred Date</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:600;color:#27272A;border-top:1px solid #E4E4E7;'>$preferredDate</td></tr>
                                </table>
                            </td>
                        </tr>
                        $ctaHtml
                        <tr>
                            <td align='center' style='padding:15px 20px;background:#FAFAFA;border-top:1px solid #E4E4E7;'>
                                <div style='font-size:11px;color:#71717A;'>Need help? Contact <strong style='color:#27272A;'>".SMTP_USER."</strong></div>
                                <div style='font-size:11px;margin-top:6px;'><a href='$baseUrl' style='color:#3F3F46;text-decoration:none;'>Visit Website</a></div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";
}

function buildStatusEmailSubject($status, $refId) {
    $label = trim((string)$status);
    if ($label === '') $label = 'Update';
    return "Booking Status: $label (#$refId)";
}

function buildSiteVisitReminderEmail($data) {
    $customer = htmlspecialchars((string)($data['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $bookingId = htmlspecialchars((string)($data['booking_id'] ?? '—'), ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars((string)($data['install_date'] ?? '—'), ENT_QUOTES, 'UTF-8');
    $window = htmlspecialchars((string)($data['window_label'] ?? 'Upcoming schedule'), ENT_QUOTES, 'UTF-8');

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
        <meta charset='UTF-8'>
    </head>
    <body style='margin:0;padding:24px;background:#F4F4F5;font-family:Inter,Segoe UI,Arial,sans-serif;color:#18181B;'>
        <table width='100%' border='0' cellspacing='0' cellpadding='0'>
            <tr><td align='center'>
                <table width='620' style='max-width:620px;background:#FFFFFF;border:1px solid #E4E4E7;border-radius:12px;overflow:hidden;'>
                    <tr><td style='padding:20px 24px;background:#FAFAFA;border-bottom:1px solid #E4E4E7;'>
                        <div style='font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#71717A;font-weight:700;'>JTH Glass & Aluminum Works</div>
                        <div style='font-size:20px;font-weight:700;margin-top:8px;'>Site Visit Reminder</div>
                    </td></tr>
                    <tr><td style='padding:20px 24px 10px;font-size:14px;line-height:1.65;color:#3F3F46;'>
                        Hello <strong style='color:#18181B;'>$customer</strong>, this is a reminder for your site visit schedule.
                    </td></tr>
                    <tr><td style='padding:10px 24px 20px;'>
                        <table width='100%' cellspacing='0' cellpadding='0' style='border:1px solid #E4E4E7;border-radius:10px;overflow:hidden;'>
                            <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Reference ID</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:700;color:#18181B;border-top:1px solid #E4E4E7;'>$bookingId</td></tr>
                            <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Schedule</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:700;color:#18181B;border-top:1px solid #E4E4E7;'>$date</td></tr>
                            <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Window</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:600;color:#27272A;border-top:1px solid #E4E4E7;'>$window</td></tr>
                        </table>
                    </td></tr>
                    <tr><td align='center' style='padding:14px 20px;background:#FAFAFA;border-top:1px solid #E4E4E7;font-size:11px;color:#71717A;'>
                        If you need to reschedule, reply to this email at ".SMTP_USER."
                    </td></tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>";
}

function buildScheduleChangedEmail($data) {
    $customer = htmlspecialchars((string)($data['customer_name'] ?? 'Customer'), ENT_QUOTES, 'UTF-8');
    $bookingId = htmlspecialchars((string)($data['booking_id'] ?? '—'), ENT_QUOTES, 'UTF-8');
    $fromDate = htmlspecialchars((string)($data['from_date'] ?? 'Not set'), ENT_QUOTES, 'UTF-8');
    $toDate = htmlspecialchars((string)($data['to_date'] ?? 'Not set'), ENT_QUOTES, 'UTF-8');

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap' rel='stylesheet'>
        <meta charset='UTF-8'>
    </head>
    <body style='margin:0;padding:24px;background:#F4F4F5;font-family:Inter,Segoe UI,Arial,sans-serif;color:#18181B;'>
        <table width='100%' border='0' cellspacing='0' cellpadding='0'>
            <tr><td align='center'>
                <table width='620' style='max-width:620px;background:#FFFFFF;border:1px solid #E4E4E7;border-radius:12px;overflow:hidden;'>
                    <tr><td style='padding:20px 24px;background:#FAFAFA;border-bottom:1px solid #E4E4E7;'>
                        <div style='font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#71717A;font-weight:700;'>JTH Glass & Aluminum Works</div>
                        <div style='font-size:20px;font-weight:700;margin-top:8px;'>Schedule Updated</div>
                    </td></tr>
                    <tr><td style='padding:20px 24px;font-size:14px;line-height:1.65;color:#3F3F46;'>
                        Hello <strong style='color:#18181B;'>$customer</strong>, your schedule has been updated.
                    </td></tr>
                    <tr><td style='padding:0 24px 20px;'>
                        <table width='100%' cellspacing='0' cellpadding='0' style='border:1px solid #E4E4E7;border-radius:10px;overflow:hidden;'>
                            <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Reference ID</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:700;color:#18181B;border-top:1px solid #E4E4E7;'>$bookingId</td></tr>
                            <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>Previous Date</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:600;color:#27272A;border-top:1px solid #E4E4E7;'>$fromDate</td></tr>
                            <tr><td style='padding:10px 14px;font-size:13px;color:#71717A;border-top:1px solid #E4E4E7;'>New Date</td><td align='right' style='padding:10px 14px;font-size:13px;font-weight:700;color:#18181B;border-top:1px solid #E4E4E7;'>$toDate</td></tr>
                        </table>
                    </td></tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>";
}

function sendBookingNotification($customerEmail, $customerName, $refID, $total, $productDesc, $installDate, $address) {
    $subject = buildStatusEmailSubject('Pending', $refID);
    $bodyContent = buildStatusEmail([
        'status' => 'Pending',
        'customer_name' => $customerName,
        'booking_id' => $refID,
        'product' => $productDesc,
        'price' => $total,
        'preferred_date' => $installDate
    ]);
    return sendEmail($customerEmail, $subject, $bodyContent);
}
?>
