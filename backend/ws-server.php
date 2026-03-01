<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ws_auth.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

if (!is_file(WS_EVENT_LOG)) {
    @touch(WS_EVENT_LOG);
}

$address = sprintf('tcp://%s:%d', WS_SERVER_HOST, WS_SERVER_PORT);
$server = @stream_socket_server($address, $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "WebSocket server start failed: {$errstr} ({$errno})\n");
    exit(1);
}

stream_set_blocking($server, false);
echo "WebSocket server listening on {$address}\n";

$clients = [];
$eventPos = max(0, (int)@filesize(WS_EVENT_LOG));

while (true) {
    $read = [$server];
    foreach ($clients as $client) {
        $read[] = $client['socket'];
    }

    $write = null;
    $except = null;
    $changed = @stream_select($read, $write, $except, 1);

    if ($changed !== false && $changed > 0) {
        foreach ($read as $sock) {
            if ($sock === $server) {
                $conn = @stream_socket_accept($server, 0);
                if ($conn) {
                    stream_set_blocking($conn, false);
                    $id = (int)$conn;
                    $clients[$id] = [
                        'socket' => $conn,
                        'handshake' => false,
                        'claims' => null
                    ];
                }
                continue;
            }

            $id = (int)$sock;
            $data = @fread($sock, 8192);
            if ($data === '' || $data === false) {
                @fclose($sock);
                unset($clients[$id]);
                continue;
            }

            if (empty($clients[$id]['handshake'])) {
                $key = extractWebSocketKey($data);
                $token = extractWebSocketToken($data);
                $claims = null;
                if ($key === null || !verifyWsToken($token, $claims)) {
                    @fclose($sock);
                    unset($clients[$id]);
                    continue;
                }
                $accept = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
                $response =
                    "HTTP/1.1 101 Switching Protocols\r\n" .
                    "Upgrade: websocket\r\n" .
                    "Connection: Upgrade\r\n" .
                    "Sec-WebSocket-Accept: {$accept}\r\n\r\n";
                @fwrite($sock, $response);
                $clients[$id]['handshake'] = true;
                $clients[$id]['claims'] = $claims;
                continue;
            }

            $opcode = getWebSocketOpcode($data);
            if ($opcode === 0x8) { // close
                @fclose($sock);
                unset($clients[$id]);
            } elseif ($opcode === 0x9) { // ping
                @fwrite($sock, encodeWebSocketFrame('', 0xA)); // pong
            }
        }
    }

    clearstatcache(true, WS_EVENT_LOG);
    $size = (int)@filesize(WS_EVENT_LOG);
    if ($size < $eventPos) {
        $eventPos = 0;
    }

    if ($size > $eventPos) {
        $fh = @fopen(WS_EVENT_LOG, 'rb');
        if ($fh) {
            @fseek($fh, $eventPos);
            while (($line = fgets($fh)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $frame = encodeWebSocketFrame($line, 0x1);
                $event = json_decode($line, true);
                if (!is_array($event)) {
                    continue;
                }
                foreach ($clients as $clientId => $client) {
                    if (empty($client['handshake'])) {
                        continue;
                    }
                    if (!canClientReceiveEvent($client, $event)) {
                        continue;
                    }
                    $ok = @fwrite($client['socket'], $frame);
                    if ($ok === false) {
                        @fclose($client['socket']);
                        unset($clients[$clientId]);
                    }
                }
            }
            $eventPos = (int)@ftell($fh);
            @fclose($fh);
        }
    }
}

function extractWebSocketKey($request)
{
    if (!preg_match('/Sec-WebSocket-Key:\s*(.+)\r\n/i', $request, $m)) {
        return null;
    }
    return trim($m[1]);
}

function extractWebSocketToken($request)
{
    if (!preg_match('/^GET\s+(\S+)\s+HTTP\/1\.1/m', $request, $m)) {
        return null;
    }
    $path = (string)$m[1];
    $query = parse_url($path, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return null;
    }
    parse_str($query, $params);
    if (!is_array($params) || empty($params['token'])) {
        return null;
    }
    return (string)$params['token'];
}

function canClientReceiveEvent($client, $event)
{
    $claims = isset($client['claims']) && is_array($client['claims']) ? $client['claims'] : [];
    $scope = (string)($claims['scope'] ?? '');
    $type = (string)($event['type'] ?? '');
    $payload = isset($event['payload']) && is_array($event['payload']) ? $event['payload'] : [];

    if ($scope === 'admin') {
        return true;
    }
    if ($scope === 'products') {
        return $type === 'product_price_updated';
    }
    if ($scope === 'track') {
        if ($type !== 'status_updated') {
            return false;
        }
        $targetId = strtoupper(trim((string)($claims['booking_id'] ?? '')));
        $eventId = strtoupper(trim((string)($payload['id'] ?? '')));
        return $targetId !== '' && $eventId !== '' && hash_equals($targetId, $eventId);
    }

    return false;
}

function getWebSocketOpcode($frame)
{
    if ($frame === '' || strlen($frame) < 2) {
        return null;
    }
    return ord($frame[0]) & 0x0F;
}

function encodeWebSocketFrame($payload, $opcode = 0x1)
{
    $len = strlen($payload);
    $head = chr(0x80 | ($opcode & 0x0F));

    if ($len <= 125) {
        return $head . chr($len) . $payload;
    }
    if ($len <= 65535) {
        return $head . chr(126) . pack('n', $len) . $payload;
    }
    return $head . chr(127) . pack('N2', 0, $len) . $payload;
}
