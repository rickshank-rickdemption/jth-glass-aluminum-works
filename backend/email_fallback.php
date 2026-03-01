<?php

if (!function_exists('efbFirebaseRequest')) {
    function efbFirebaseRequest($method, $path, $payload = null)
    {
        $url = FIREBASE_URL . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $raw = curl_exec($ch);
        curl_close($ch);
        if ($raw === false || $raw === '' || $raw === 'null') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('efbQueueJob')) {
    function efbQueueJob($to, $subject, $bodyHtml, $meta = [])
    {
        $jobId = 'EQ-' . strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
        $now = time();
        $job = [
            'id' => $jobId,
            'to' => (string)$to,
            'subject' => (string)$subject,
            'body_html' => (string)$bodyHtml,
            'attempts' => 0,
            'status' => 'queued',
            'next_attempt_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
            'last_error' => '',
            'meta' => is_array($meta) ? $meta : []
        ];
        efbFirebaseRequest('PUT', "system_settings/email_queue/" . rawurlencode($jobId) . ".json", $job);
        efbFirebaseRequest('PATCH', "system_settings/email_health.json", [
            'degraded' => true,
            'last_failure_at' => $now,
            'last_error' => 'queued_due_to_send_failure',
            'updated_at' => $now
        ]);
        return $jobId;
    }
}

if (!function_exists('efbComputeBackoffSeconds')) {
    function efbComputeBackoffSeconds($attempts)
    {
        $attempts = max(1, (int)$attempts);
        $delay = (int)pow(2, min($attempts, 6)) * 60; // 2m,4m,8m... up to 64m
        return min($delay, 3600);
    }
}

if (!function_exists('efbProcessQueue')) {
    function efbProcessQueue($limit = 5)
    {
        if (!function_exists('sendEmail')) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        $now = time();
        $queue = efbFirebaseRequest('GET', "system_settings/email_queue.json");
        if (!is_array($queue) || empty($queue)) {
            efbFirebaseRequest('PATCH', "system_settings/email_health.json", [
                'degraded' => false,
                'updated_at' => $now,
                'last_processed_at' => $now
            ]);
            return ['processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        $jobs = [];
        foreach ($queue as $id => $job) {
            if (!is_array($job)) continue;
            $status = (string)($job['status'] ?? 'queued');
            $nextAt = (int)($job['next_attempt_at'] ?? 0);
            if (!in_array($status, ['queued', 'retrying'], true)) continue;
            if ($nextAt > $now) continue;
            $job['_id'] = $id;
            $jobs[] = $job;
        }

        usort($jobs, function ($a, $b) {
            $at = (int)($a['created_at'] ?? 0);
            $bt = (int)($b['created_at'] ?? 0);
            return $at <=> $bt;
        });

        $processed = 0;
        $sent = 0;
        $failed = 0;
        foreach ($jobs as $job) {
            if ($processed >= $limit) break;
            $processed++;
            $id = (string)$job['_id'];
            $to = (string)($job['to'] ?? '');
            $subject = (string)($job['subject'] ?? 'JTH Notification');
            $bodyHtml = (string)($job['body_html'] ?? '');
            $attempts = (int)($job['attempts'] ?? 0);

            $ok = false;
            try {
                $ok = sendEmail($to, $subject, $bodyHtml);
            } catch (Throwable $e) {
                $ok = false;
            }

            if ($ok) {
                efbFirebaseRequest('DELETE', "system_settings/email_queue/" . rawurlencode($id) . ".json");
                $sent++;
                continue;
            }

            $failed++;
            $attempts++;
            efbFirebaseRequest('PATCH', "system_settings/email_queue/" . rawurlencode($id) . ".json", [
                'attempts' => $attempts,
                'status' => 'retrying',
                'next_attempt_at' => $now + efbComputeBackoffSeconds($attempts),
                'updated_at' => $now,
                'last_error' => 'send_failed'
            ]);
        }

        $remainingQueue = efbFirebaseRequest('GET', "system_settings/email_queue.json");
        $queuedCount = is_array($remainingQueue) ? count($remainingQueue) : 0;
        efbFirebaseRequest('PATCH', "system_settings/email_health.json", [
            'degraded' => $queuedCount > 0,
            'queue_count' => $queuedCount,
            'last_processed_at' => $now,
            'last_failure_at' => $failed > 0 ? $now : null,
            'updated_at' => $now
        ]);

        return ['processed' => $processed, 'sent' => $sent, 'failed' => $failed];
    }
}

if (!function_exists('efbGetHealthSnapshot')) {
    function efbGetHealthSnapshot()
    {
        $health = efbFirebaseRequest('GET', "system_settings/email_health.json");
        $queue = efbFirebaseRequest('GET', "system_settings/email_queue.json");
        $queueCount = is_array($queue) ? count($queue) : 0;
        $now = time();
        return [
            'degraded' => (bool)($health['degraded'] ?? false) || $queueCount > 0,
            'queue_count' => $queueCount,
            'last_failure_at' => (int)($health['last_failure_at'] ?? 0),
            'last_processed_at' => (int)($health['last_processed_at'] ?? 0),
            'updated_at' => (int)($health['updated_at'] ?? $now)
        ];
    }
}

