<?php
header("Content-Type: application/json");
require_once 'session.php';
require_once 'workflow.php';
applySecurityHeaders(true);

requireAdminSessionOrJsonError();

$events = [];

$ch = curl_init(FIREBASE_URL . "bookings.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$bookings = json_decode(curl_exec($ch), true);
curl_close($ch);

if ($bookings) {
    foreach ($bookings as $key => $b) {
        
        $status = $b['status'] ?? 'Pending';
        if ($status === 'Void' || $status === 'Cancelled') {
            continue; 
        }

        $color = '#3b82f6'; // Default: Pending (Blue)
        
        switch ($status) {
            case 'Site Visit':   $color = '#06b6d4'; break; // Cyan
            case 'Confirmed':    $color = '#10b981'; break; // Green
            case 'Fabrication':  $color = '#f59e0b'; break; // Orange
            case 'Installation': $color = '#8b5cf6'; break; // Purple
            case 'Completed':    $color = '#374151'; break; // Dark Grey
        }

        $eventDate = jthNormalizeDateLoose($b['install_date'] ?? '');
        
        if ($eventDate !== '') {
            $customerName = $b['customer_name_snapshot'] ?? $b['customer'] ?? 'Guest';
            $productName  = $b['product'] ?? 'Service';

            $events[] = [
                'id' => $b['id'] ?? $key,
                'title' => "[$status] $customerName", 
                'start' => $eventDate,
                'allDay' => true, // Bookings are usually day-based
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'type' => 'booking',
                    'status' => $status,
                    'details' => $productName
                ]
            ];
        }
    }
}

$ch = curl_init(FIREBASE_URL . "system_settings/calendar/blocked_dates.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$blocked = json_decode(curl_exec($ch), true);
curl_close($ch);

if ($blocked) {
    foreach ($blocked as $date => $reason) {
        $events[] = [
            'id' => 'block-' . $date,
            'title' => "BLOCKED: $reason",
            'start' => $date,
            'allDay' => true,
            'display' => 'background', // Renders as a shaded full day
            'backgroundColor' => '#ef4444', // Red
            'extendedProps' => [
                'type' => 'blocked',
                'reason' => $reason
            ]
        ];
    }
}

echo json_encode($events);
?>
