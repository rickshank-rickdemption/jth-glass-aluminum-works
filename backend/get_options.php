<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require_once 'config.php';
applySecurityHeaders(true);

function curl_get_json($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $raw === null) {
        return [null, $err ?: 'Request failed'];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [null, 'Invalid JSON response'];
    }

    return [$decoded, null];
}

function fs_value($field) {
    if (!is_array($field)) {
        return null;
    }
    if (isset($field['stringValue'])) {
        return $field['stringValue'];
    }
    if (isset($field['integerValue'])) {
        return (float)$field['integerValue'];
    }
    if (isset($field['doubleValue'])) {
        return (float)$field['doubleValue'];
    }
    if (isset($field['booleanValue'])) {
        return (bool)$field['booleanValue'];
    }
    return null;
}

function make_key($text) {
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    $text = trim($text, '_');
    return $text !== '' ? $text : 'item';
}

list($rtdb, $rtdbErr) = curl_get_json(FIREBASE_URL . "products.json");
if (is_array($rtdb) && !isset($rtdb['error']) && !empty($rtdb)) {
    echo json_encode($rtdb, JSON_UNESCAPED_UNICODE);
    exit;
}

$projectId = "jth-glass-and-aluminum";
$fsUrl = "https://firestore.googleapis.com/v1/projects/" . $projectId . "/databases/(default)/documents/prices";
list($fsData, $fsErr) = curl_get_json($fsUrl);

if (is_array($fsData) && isset($fsData['documents']) && is_array($fsData['documents']) && !empty($fsData['documents'])) {
    $products = [];

    foreach ($fsData['documents'] as $doc) {
        if (!isset($doc['fields']) || !is_array($doc['fields'])) {
            continue;
        }

        $fields = $doc['fields'];
        $product = trim((string)fs_value($fields['product'] ?? null));
        $glass = trim((string)fs_value($fields['glass'] ?? null));
        $finish = trim((string)fs_value($fields['finish'] ?? null));
        $price = (float)fs_value($fields['price'] ?? null);

        if ($product === '' || $price <= 0) {
            continue;
        }

        $catKey = make_key($product);
        if (!isset($products[$catKey])) {
            $products[$catKey] = [
                "display_name" => $product,
                "variants" => []
            ];
        }

        $variantLabel = trim($glass . ($finish !== '' ? " - " . $finish : ""));
        if ($variantLabel === '') {
            $variantLabel = "Standard";
        }

        $varKeyBase = make_key($variantLabel);
        $varKey = $varKeyBase;
        $counter = 1;
        while (isset($products[$catKey]["variants"][$varKey])) {
            $counter++;
            $varKey = $varKeyBase . "_" . $counter;
        }

        $products[$catKey]["variants"][$varKey] = [
            "label" => $variantLabel,
            "price_no_screen" => $price,
            "price_w_screen" => 0,
            "is_available" => true
        ];
    }

    if (!empty($products)) {
        echo json_encode($products, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

echo json_encode([
    "error" => "No product data available.",
    "debug" => [
        "rtdb" => $rtdbErr ?: ($rtdb['error'] ?? "RTDB products unavailable"),
        "firestore" => $fsErr ?: ($fsData['error']['message'] ?? "Firestore prices unavailable")
    ]
], JSON_UNESCAPED_UNICODE);
?>
