<?php
header("Content-Type: application/json");

require_once __DIR__ . '/config.php';
applySecurityHeaders(true);

function fetchJsonNode($path)
{
    $url = rtrim(FIREBASE_URL, '/') . '/' . ltrim((string)$path, '/');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $raw = curl_exec($ch);
    $err = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err !== 0 || $raw === false || $http >= 400) {
        return [null, false];
    }
    return [json_decode($raw, true), true];
}

[$versionNode, $okVersion] = fetchJsonNode('system_settings/products_version.json');
if ($okVersion && is_array($versionNode)) {
    echo json_encode([
        'status' => 'success',
        'updated_at' => (int)($versionNode['updated_at'] ?? 0),
        'actor' => (string)($versionNode['actor'] ?? '')
    ]);
    exit;
}

// Fallback for stricter rules: derive a simple version from product variant updated_at values.
[$productsNode, $okProducts] = fetchJsonNode('products.json');
if (!$okProducts || !is_array($productsNode)) {
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to fetch products version.'
    ]);
    exit;
}

$maxUpdatedAt = 0;
foreach ($productsNode as $product) {
    if (!is_array($product)) {
        continue;
    }
    $variants = $product['variants'] ?? null;
    if (!is_array($variants)) {
        continue;
    }
    foreach ($variants as $variant) {
        if (!is_array($variant)) {
            continue;
        }
        $ts = (int)($variant['updated_at'] ?? 0);
        if ($ts > $maxUpdatedAt) {
            $maxUpdatedAt = $ts;
        }
    }
}

echo json_encode([
    'status' => 'success',
    'updated_at' => $maxUpdatedAt,
    'actor' => ''
]);
