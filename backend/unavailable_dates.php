<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

require_once 'session.php';
require_once 'workflow.php';
applySecurityHeaders(true);

$strictHolidays = ['12-25', '01-01', '11-01', '11-02'];
$holidayDates = [];
$currentYear = (int)date('Y');
$years = [$currentYear, $currentYear + 1];
foreach ($years as $year) {
    foreach ($strictHolidays as $md) {
        [$m, $d] = explode('-', $md);
        $holidayDates[] = sprintf('%04d-%02d-%02d', (int)$year, (int)$m, (int)$d);
    }
}

$blockedDates = [];
$ch = curl_init(FIREBASE_URL . "system_settings/calendar/blocked_dates.json");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$raw = curl_exec($ch);
curl_close($ch);
$blockedRaw = json_decode($raw, true);
if (is_array($blockedRaw)) {
    foreach ($blockedRaw as $date => $reason) {
        $norm = jthNormalizeIsoDateInput($date);
        if ($norm === '') continue;
        $blockedDates[] = $norm;
    }
}

echo json_encode([
    'status' => 'success',
    'blocked_dates' => $blockedDates,
    'holiday_dates' => $holidayDates
]);
exit;
?>
