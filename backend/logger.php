<?php
require_once 'config.php';

function appLog($level, $message, array $context = [])
{
    $record = [
        'ts' => date('c'),
        'level' => strtoupper((string)$level),
        'message' => (string)$message,
        'context' => $context
    ];
    error_log('[JTH] ' . json_encode($record, JSON_UNESCAPED_SLASHES));
}

