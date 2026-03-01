<?php

require_once 'config.php';

function pushRealtimeEvent($type, array $payload = [])
{
    if (!defined('WS_EVENT_LOG') || !WS_EVENT_LOG || !is_string($type) || $type === '') {
        return false;
    }

    $event = [
        'type' => $type,
        'timestamp' => time(),
        'payload' => $payload
    ];

    $line = json_encode($event, JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return false;
    }

    return @file_put_contents(WS_EVENT_LOG, $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
}

