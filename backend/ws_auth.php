<?php
require_once __DIR__ . '/config.php';

if (!function_exists('wsBase64UrlEncode')) {
    function wsBase64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode((string)$data), '+/', '-_'), '=');
    }
}

if (!function_exists('wsBase64UrlDecode')) {
    function wsBase64UrlDecode($data)
    {
        $data = strtr((string)$data, '-_', '+/');
        $padLen = strlen($data) % 4;
        if ($padLen > 0) {
            $data .= str_repeat('=', 4 - $padLen);
        }
        $decoded = base64_decode($data, true);
        return $decoded === false ? null : $decoded;
    }
}

if (!function_exists('generateWsToken')) {
    function generateWsToken(array $claims, $ttlSeconds = null)
    {
        $now = time();
        $ttl = (int)($ttlSeconds ?? WS_TOKEN_TTL_SECONDS);
        if ($ttl <= 0) {
            $ttl = WS_TOKEN_TTL_SECONDS > 0 ? WS_TOKEN_TTL_SECONDS : 3600;
        }

        $payload = $claims;
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttl;

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            return null;
        }

        $payloadPart = wsBase64UrlEncode($payloadJson);
        $sig = hash_hmac('sha256', $payloadPart, (string)WS_TOKEN_SECRET, true);
        $sigPart = wsBase64UrlEncode($sig);
        return $payloadPart . '.' . $sigPart;
    }
}

if (!function_exists('verifyWsToken')) {
    function verifyWsToken($token, &$claimsOut = null)
    {
        $claimsOut = null;
        $parts = explode('.', (string)$token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return false;
        }

        [$payloadPart, $sigPart] = $parts;
        $expectedSig = hash_hmac('sha256', $payloadPart, (string)WS_TOKEN_SECRET, true);
        $providedSig = wsBase64UrlDecode($sigPart);
        if ($providedSig === null || !hash_equals($expectedSig, $providedSig)) {
            return false;
        }

        $payloadJson = wsBase64UrlDecode($payloadPart);
        if ($payloadJson === null) {
            return false;
        }
        $claims = json_decode($payloadJson, true);
        if (!is_array($claims)) {
            return false;
        }

        $now = time();
        $exp = (int)($claims['exp'] ?? 0);
        $iat = (int)($claims['iat'] ?? 0);
        if ($exp <= $now || $iat <= 0 || $iat > ($now + 60)) {
            return false;
        }

        $scope = (string)($claims['scope'] ?? '');
        if (!in_array($scope, ['admin', 'products', 'track'], true)) {
            return false;
        }
        if ($scope === 'track') {
            $bookingId = strtoupper(trim((string)($claims['booking_id'] ?? '')));
            if (!preg_match('/^BK-[A-Z0-9]{6,16}$/', $bookingId)) {
                return false;
            }
            $claims['booking_id'] = $bookingId;
        }

        $claimsOut = $claims;
        return true;
    }
}

