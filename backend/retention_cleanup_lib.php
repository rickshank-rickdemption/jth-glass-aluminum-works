<?php

if (!function_exists('rcFirebaseRequest')) {
    function rcFirebaseRequest($method, $path, $payload = null)
    {
        $url = rtrim(FIREBASE_URL, '/') . '/' . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $raw = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $raw === '' || $raw === 'null') {
            return ['http' => $http, 'data' => null];
        }
        $decoded = json_decode($raw, true);
        return ['http' => $http, 'data' => $decoded];
    }
}

if (!function_exists('rcParseTimestamp')) {
    function rcParseTimestamp($value)
    {
        if (is_numeric($value)) {
            $n = (int)$value;
            if ($n <= 0) return 0;
            if ($n > 100000000000) return (int)floor($n / 1000); // ms -> s
            return $n;
        }
        $str = trim((string)$value);
        if ($str === '') return 0;
        $ts = strtotime($str);
        return $ts === false ? 0 : (int)$ts;
    }
}

if (!function_exists('rcGetCustomerLastActivityTs')) {
    function rcGetCustomerLastActivityTs($customer, $bookingIndex, $customerId)
    {
        $maxTs = 0;
        if (is_array($customer)) {
            $maxTs = max($maxTs, rcParseTimestamp($customer['last_active'] ?? 0));
            $maxTs = max($maxTs, rcParseTimestamp($customer['created_at'] ?? 0));
            $maxTs = max($maxTs, rcParseTimestamp($customer['updated_at'] ?? 0));
        }
        if (isset($bookingIndex[$customerId]) && is_array($bookingIndex[$customerId])) {
            $maxTs = max($maxTs, (int)($bookingIndex[$customerId]['latest_ts'] ?? 0));
        }
        return $maxTs;
    }
}

if (!function_exists('rcBuildBookingIndex')) {
    function rcBuildBookingIndex($bookings)
    {
        $index = [];
        if (!is_array($bookings)) return $index;
        foreach ($bookings as $bookingKey => $booking) {
            if (!is_array($booking)) continue;
            $customerId = trim((string)($booking['customer_id'] ?? ''));
            if ($customerId === '') continue;
            $ts = 0;
            $ts = max($ts, rcParseTimestamp($booking['created_at'] ?? 0));
            $ts = max($ts, rcParseTimestamp($booking['updated_at'] ?? 0));
            $ts = max($ts, rcParseTimestamp($booking['install_date'] ?? 0));
            if (isset($booking['history']) && is_array($booking['history'])) {
                foreach ($booking['history'] as $h) {
                    if (!is_array($h)) continue;
                    $ts = max($ts, rcParseTimestamp($h['timestamp'] ?? 0));
                }
            }
            if (!isset($index[$customerId])) {
                $index[$customerId] = ['latest_ts' => $ts, 'booking_keys' => []];
            } else {
                $index[$customerId]['latest_ts'] = max((int)$index[$customerId]['latest_ts'], $ts);
            }
            $index[$customerId]['booking_keys'][] = (string)$bookingKey;
        }
        return $index;
    }
}

if (!function_exists('rcAppendBookingHistory')) {
    function rcAppendBookingHistory($bookingKey, $booking, $customerId, $actor)
    {
        if (!is_array($booking)) return;
        $history = isset($booking['history']) && is_array($booking['history']) ? $booking['history'] : [];
        $history[] = [
            'action' => 'retention_purge',
            'actor' => $actor,
            'timestamp' => time(),
            'details' => "Customer repository record purged after retention period for customer_id={$customerId}"
        ];
        rcFirebaseRequest('PATCH', "bookings/" . rawurlencode((string)$bookingKey) . ".json", [
            'history' => $history
        ]);
    }
}

if (!function_exists('rcPurgeCustomerRecord')) {
    function rcPurgeCustomerRecord($customerId, $customer, $ttlDays, $lastTs)
    {
        $customerId = trim((string)$customerId);
        if ($customerId === '') {
            return ['ok' => false, 'error' => 'invalid_customer_id'];
        }

        $name = 'Purged Customer';
        if (is_array($customer)) {
            $existingName = trim((string)($customer['name'] ?? ''));
            if ($existingName !== '') $name = $existingName;
        }

        $payload = [
            'name' => $name,
            'email' => '',
            'phone' => '',
            'address' => '',
            'city' => '',
            'province' => '',
            'zip' => '',
            'consent' => false,
            'pii_purged' => true,
            'pii_purged_at' => time(),
            'retention_ttl_days' => max(1, (int)$ttlDays),
            'retention_last_activity_ts' => (int)$lastTs
        ];

        $patchRes = rcFirebaseRequest('PATCH', "customers/" . rawurlencode($customerId) . ".json", $payload);
        if (($patchRes['http'] ?? 0) < 200 || ($patchRes['http'] ?? 0) >= 300) {
            return ['ok' => false, 'error' => 'firebase_patch_failed', 'http' => (int)($patchRes['http'] ?? 0), 'data' => $patchRes['data'] ?? null];
        }

        return ['ok' => true];
    }
}

if (!function_exists('rcRunCleanup')) {
    function rcRunCleanup($actor = 'system', $dryRun = false)
    {
        if (!RETENTION_ENABLED) {
            return [
                'status' => 'success',
                'enabled' => false,
                'message' => 'Retention is disabled.',
                'cutoff_ts' => 0,
                'purged_customers' => 0,
                'scanned_customers' => 0
            ];
        }

        $ttlDays = max(1, (int)RETENTION_CUSTOMER_PII_DAYS);
        $cutoffTs = time() - ($ttlDays * 86400);

        $customersRes = rcFirebaseRequest('GET', 'customers.json');
        $bookingsRes = rcFirebaseRequest('GET', 'bookings.json');
        $customers = is_array($customersRes['data']) ? $customersRes['data'] : [];
        $bookings = is_array($bookingsRes['data']) ? $bookingsRes['data'] : [];
        $bookingIndex = rcBuildBookingIndex($bookings);

        $purged = 0;
        $scanned = 0;
        $entries = [];
        $purgedIds = [];
        $failed = [];

        foreach ($customers as $customerId => $customer) {
            $scanned++;
            $customerId = trim((string)$customerId);
            if ($customerId === '') continue;

            $lastTs = rcGetCustomerLastActivityTs($customer, $bookingIndex, $customerId);
            if ($lastTs <= 0 || $lastTs > $cutoffTs) {
                continue;
            }

            $entries[] = [
                'customer_id' => $customerId,
                'last_activity_ts' => $lastTs,
                'last_activity' => date('Y-m-d H:i:s', $lastTs)
            ];

            if ($dryRun) {
                $purged++;
                continue;
            }

            $purgeRes = rcPurgeCustomerRecord($customerId, $customer, $ttlDays, $lastTs);
            if (empty($purgeRes['ok'])) {
                $failed[] = [
                    'customer_id' => $customerId,
                    'reason' => (string)($purgeRes['error'] ?? 'unknown'),
                    'http' => (int)($purgeRes['http'] ?? 0)
                ];
                continue;
            }
            if (isset($bookingIndex[$customerId]) && is_array($bookingIndex[$customerId]['booking_keys'] ?? null)) {
                foreach ($bookingIndex[$customerId]['booking_keys'] as $bkKey) {
                    $booking = $bookings[$bkKey] ?? null;
                    rcAppendBookingHistory($bkKey, $booking, $customerId, $actor);
                }
            }

            $auditKey = 'ret_' . str_replace('.', '', uniqid('', true));
            rcFirebaseRequest('PUT', "system_settings/retention_audit/" . rawurlencode($auditKey) . ".json", [
                'action' => 'customer_pii_purged',
                'customer_id' => $customerId,
                'actor' => $actor,
                'timestamp' => time(),
                'last_activity_ts' => $lastTs,
                'details' => "Purged customer repository record after {$ttlDays} days retention window."
            ]);
            $purgedIds[] = $customerId;
            $purged++;
        }

        rcFirebaseRequest('PATCH', 'system_settings/retention_state.json', [
            'enabled' => RETENTION_ENABLED,
            'last_run_at' => time(),
            'last_run_by' => $actor,
            'ttl_days' => $ttlDays,
            'cutoff_ts' => $cutoffTs,
            'scanned_customers' => $scanned,
            'purged_customers' => $purged,
            'purged_customer_ids' => array_values($purgedIds),
            'failed_customers' => count($failed)
        ]);

        return [
            'status' => 'success',
            'enabled' => true,
            'ttl_days' => $ttlDays,
            'cutoff_ts' => $cutoffTs,
            'cutoff_date' => date('Y-m-d', $cutoffTs),
            'scanned_customers' => $scanned,
            'purged_customers' => $purged,
            'failed_customers' => count($failed),
            'failed_items' => $failed,
            'purged_customer_ids' => array_values($purgedIds),
            'items' => $entries
        ];
    }
}

if (!function_exists('rcRunIfDue')) {
    function rcRunIfDue($actor = 'system:auto')
    {
        if (!RETENTION_ENABLED) return null;
        $stateRes = rcFirebaseRequest('GET', 'system_settings/retention_state.json');
        $state = is_array($stateRes['data']) ? $stateRes['data'] : [];
        $lastRun = (int)($state['last_run_at'] ?? 0);
        $interval = max(3600, (int)RETENTION_AUTO_INTERVAL_SECONDS);
        if ($lastRun > 0 && (time() - $lastRun) < $interval) {
            return null;
        }
        return rcRunCleanup($actor, false);
    }
}
?>
