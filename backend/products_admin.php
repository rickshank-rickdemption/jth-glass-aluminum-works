<?php
header("Content-Type: application/json");

require_once 'session.php';
applySecurityHeaders(true);
require_once 'logger.php';
require_once 'ws_events.php';
require_once 'product_catalog_seed.php';

requireAdminSessionOrJsonError();

function curlJsonGet($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $raw = curl_exec($ch);
    $err = curl_errno($ch);
    curl_close($ch);
    if ($err !== 0 || $raw === false) {
        return [null, 'fetch_failed'];
    }
    $decoded = json_decode($raw, true);
    return [is_array($decoded) ? $decoded : [], null];
}

function curlJsonPatch($url, array $payload)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $raw = curl_exec($ch);
    $err = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err !== 0 || $http < 200 || $http >= 300 || $raw === false) {
        return [false, 'update_failed'];
    }
    return [true, null];
}

function curlJsonPut($url, array $payload)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $raw = curl_exec($ch);
    $err = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err !== 0 || $http < 200 || $http >= 300 || $raw === false) {
        return [false, 'put_failed'];
    }
    return [true, null];
}

function curlJsonDelete($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $raw = curl_exec($ch);
    $err = curl_errno($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err !== 0 || $http < 200 || $http >= 300 || $raw === false) {
        return [false, 'delete_failed'];
    }
    return [true, null];
}

function makeKey($text)
{
    $text = strtolower(trim((string)$text));
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    $text = trim($text, '_');
    return $text !== '' ? $text : '';
}

$action = trim((string)($_GET['action'] ?? 'list'));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
const PRODUCT_TYPES = ['windows', 'doors', 'sliding', 'partitions', 'railings', 'accessories', 'others'];

function upsertSeedCatalog(array $seedCatalog, array &$existingProducts)
{
    $created = 0;
    $updated = 0;

    foreach ($seedCatalog as $productKey => $productNode) {
        if (!is_array($productNode) || !is_array($productNode['variants'] ?? null)) {
            continue;
        }
        $existingNode = isset($existingProducts[$productKey]) && is_array($existingProducts[$productKey]) ? $existingProducts[$productKey] : [];
        $existingVariants = isset($existingNode['variants']) && is_array($existingNode['variants']) ? $existingNode['variants'] : [];

        foreach ($productNode['variants'] as $variantKey => $variantPayload) {
            if (isset($existingVariants[$variantKey])) {
                $updated++;
            } else {
                $created++;
            }
            $existingVariants[$variantKey] = $variantPayload;
        }

        $payload = [
            'display_name' => (string)($productNode['display_name'] ?? ($existingNode['display_name'] ?? $productKey)),
            'variants' => $existingVariants
        ];
        [$ok, $err] = curlJsonPut(FIREBASE_URL . "products/$productKey.json", $payload);
        if (!$ok) {
            return [false, $created, $updated];
        }

        $existingProducts[$productKey] = $payload;
    }

    return [true, $created, $updated];
}

if ($method === 'GET' && $action === 'list') {
    [$products, $err] = curlJsonGet(FIREBASE_URL . "products.json");
    if ($err !== null) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Unable to fetch products.']);
        exit;
    }

    $rows = [];
    foreach ($products as $productKey => $product) {
        if (!is_array($product)) {
            continue;
        }
        $productName = trim((string)($product['display_name'] ?? $productKey));
        $variants = $product['variants'] ?? null;
        if (!is_array($variants)) {
            continue;
        }

        foreach ($variants as $variantKey => $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $rows[] = [
                'product_key' => (string)$productKey,
                'product_name' => $productName,
                'variant_key' => (string)$variantKey,
                'variant_label' => trim((string)($variant['label'] ?? $variantKey)),
                'price_no_screen' => (float)($variant['price_no_screen'] ?? 0),
                'product_type' => trim((string)($variant['product_type'] ?? '')),
                'is_available' => ($variant['is_available'] ?? true) === true,
                'updated_at' => (int)($variant['updated_at'] ?? 0),
                'updated_by' => trim((string)($variant['updated_by'] ?? ''))
            ];
        }
    }

    usort($rows, static function ($a, $b) {
        $left = strtolower($a['product_name'] . ' ' . $a['variant_label']);
        $right = strtolower($b['product_name'] . ' ' . $b['variant_label']);
        return $left <=> $right;
    });

    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit;
}

if ($method === 'POST' && $action === 'update') {
    requireCsrfOrJsonError();

    $productKey = trim((string)($_POST['product_key'] ?? ''));
    $variantKey = trim((string)($_POST['variant_key'] ?? ''));
    $priceNoScreen = $_POST['price_no_screen'] ?? null;
    $productType = strtolower(trim((string)($_POST['product_type'] ?? 'others')));
    $isAvailableRaw = $_POST['is_available'] ?? '1';

    if (!preg_match('/^[a-zA-Z0-9_-]{2,80}$/', $productKey)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product key.']);
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_-]{2,120}$/', $variantKey)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid variant key.']);
        exit;
    }

    if (!is_numeric($priceNoScreen)) {
        echo json_encode(['status' => 'error', 'message' => 'Base price must be a numeric value.']);
        exit;
    }
    $priceNoScreen = (float)$priceNoScreen;
    if ($priceNoScreen <= 0 || $priceNoScreen > 1000000) {
        echo json_encode(['status' => 'error', 'message' => 'Base price must be between 0.01 and 1,000,000.']);
        exit;
    }
    if (!in_array($productType, PRODUCT_TYPES, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product type.']);
        exit;
    }

    $isAvailable = filter_var($isAvailableRaw, FILTER_VALIDATE_BOOLEAN);
    $adminUser = $_SESSION['jth_admin_user'] ?? ADMIN_USER;
    $nowTs = time();

    $variantPath = FIREBASE_URL . "products/$productKey/variants/$variantKey.json";
    $patch = [
        'price_no_screen' => round($priceNoScreen, 2),
        'product_type' => $productType,
        'is_available' => $isAvailable,
        'updated_at' => $nowTs,
        'updated_by' => $adminUser
    ];

    [$ok, $err] = curlJsonPatch($variantPath, $patch);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update product price.']);
        exit;
    }

    $auditKey = uniqid('pa_', true);
    $auditPath = FIREBASE_URL . "system_settings/product_price_audit/$auditKey.json";
    $auditPayload = [
        'product_key' => $productKey,
        'variant_key' => $variantKey,
        'price_no_screen' => round($priceNoScreen, 2),
        'product_type' => $productType,
        'is_available' => $isAvailable,
        'actor' => $adminUser,
        'timestamp' => $nowTs
    ];
    curlJsonPut($auditPath, $auditPayload);

    appLog('info', 'product_price_updated', [
        'product_key' => $productKey,
        'variant_key' => $variantKey,
        'actor' => $adminUser
    ]);

    pushRealtimeEvent('product_price_updated', [
        'product_key' => $productKey,
        'variant_key' => $variantKey,
        'actor' => $adminUser,
        'product_type' => $productType
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Product price updated successfully.',
        'updated_at' => $nowTs,
        'updated_by' => $adminUser
    ]);
    exit;
}

if ($method === 'POST' && $action === 'create') {
    requireCsrfOrJsonError();

    $productName = trim((string)($_POST['product_name'] ?? ''));
    $variantLabel = trim((string)($_POST['variant_label'] ?? ''));
    $priceNoScreen = $_POST['price_no_screen'] ?? null;
    $productType = strtolower(trim((string)($_POST['product_type'] ?? 'others')));
    $isAvailableRaw = $_POST['is_available'] ?? '1';

    if (mb_strlen($productName) < 2 || mb_strlen($productName) > 120) {
        echo json_encode(['status' => 'error', 'message' => 'Product name must be 2 to 120 characters.']);
        exit;
    }
    if (mb_strlen($variantLabel) < 2 || mb_strlen($variantLabel) > 160) {
        echo json_encode(['status' => 'error', 'message' => 'Variant label must be 2 to 160 characters.']);
        exit;
    }
    if (!is_numeric($priceNoScreen)) {
        echo json_encode(['status' => 'error', 'message' => 'Base price must be a numeric value.']);
        exit;
    }
    $priceNoScreen = (float)$priceNoScreen;
    if ($priceNoScreen <= 0 || $priceNoScreen > 1000000) {
        echo json_encode(['status' => 'error', 'message' => 'Base price must be between 0.01 and 1,000,000.']);
        exit;
    }
    if (!in_array($productType, PRODUCT_TYPES, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product type.']);
        exit;
    }

    $productKey = makeKey($productName);
    $variantKeyBase = makeKey($variantLabel);
    if ($productKey === '' || $variantKeyBase === '') {
        echo json_encode(['status' => 'error', 'message' => 'Unable to generate valid product/variant key.']);
        exit;
    }

    [$allProducts, $fetchErr] = curlJsonGet(FIREBASE_URL . "products.json");
    if ($fetchErr !== null) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Unable to fetch existing products.']);
        exit;
    }
    if (!is_array($allProducts)) {
        $allProducts = [];
    }

    $existingProduct = $allProducts[$productKey] ?? null;
    $existingVariants = (is_array($existingProduct) && is_array($existingProduct['variants'] ?? null))
        ? $existingProduct['variants']
        : [];

    $variantKey = $variantKeyBase;
    $suffix = 1;
    while (array_key_exists($variantKey, $existingVariants)) {
        $suffix++;
        $variantKey = $variantKeyBase . '_' . $suffix;
    }

    $isAvailable = filter_var($isAvailableRaw, FILTER_VALIDATE_BOOLEAN);
    $adminUser = $_SESSION['jth_admin_user'] ?? ADMIN_USER;
    $nowTs = time();

    $productPatch = [
        'display_name' => $productName
    ];
    [$okProduct, $errProduct] = curlJsonPatch(FIREBASE_URL . "products/$productKey.json", $productPatch);
    if (!$okProduct) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to prepare product node.']);
        exit;
    }

    $variantPayload = [
        'label' => $variantLabel,
        'price_no_screen' => round($priceNoScreen, 2),
        'price_w_screen' => 0,
        'product_type' => $productType,
        'is_available' => $isAvailable,
        'updated_at' => $nowTs,
        'updated_by' => $adminUser
    ];
    [$okVariant, $errVariant] = curlJsonPut(FIREBASE_URL . "products/$productKey/variants/$variantKey.json", $variantPayload);
    if (!$okVariant) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create product variant.']);
        exit;
    }

    $auditKey = uniqid('pc_', true);
    $auditPath = FIREBASE_URL . "system_settings/product_price_audit/$auditKey.json";
    $auditPayload = [
        'product_key' => $productKey,
        'variant_key' => $variantKey,
        'product_name' => $productName,
        'variant_label' => $variantLabel,
        'price_no_screen' => round($priceNoScreen, 2),
        'product_type' => $productType,
        'is_available' => $isAvailable,
        'action' => 'create',
        'actor' => $adminUser,
        'timestamp' => $nowTs
    ];
    curlJsonPut($auditPath, $auditPayload);

    appLog('info', 'product_variant_created', [
        'product_key' => $productKey,
        'variant_key' => $variantKey,
        'actor' => $adminUser
    ]);

    pushRealtimeEvent('product_price_updated', [
        'product_key' => $productKey,
        'variant_key' => $variantKey,
        'actor' => $adminUser,
        'product_type' => $productType,
        'change_type' => 'create'
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Product variant created successfully.',
        'product_key' => $productKey,
        'variant_key' => $variantKey
    ]);
    exit;
}

if ($method === 'POST' && $action === 'seed_import_table_a') {
    requireCsrfOrJsonError();

    $productType = strtolower(trim((string)($_POST['product_type'] ?? 'windows')));
    if (!in_array($productType, PRODUCT_TYPES, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product type.']);
        exit;
    }

    $adminUser = $_SESSION['jth_admin_user'] ?? ADMIN_USER;
    [$existingProducts, $fetchErr] = curlJsonGet(FIREBASE_URL . "products.json");
    if ($fetchErr !== null) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Unable to fetch existing products.']);
        exit;
    }
    if (!is_array($existingProducts)) {
        $existingProducts = [];
    }

    $seedCatalog = buildSeedProductsCatalogTableA($productType, $adminUser);
    $created = 0;
    $updated = 0;

    foreach ($seedCatalog as $productKey => $productNode) {
        if (!is_array($productNode) || !is_array($productNode['variants'] ?? null)) {
            continue;
        }
        $existingNode = isset($existingProducts[$productKey]) && is_array($existingProducts[$productKey]) ? $existingProducts[$productKey] : [];
        $existingVariants = isset($existingNode['variants']) && is_array($existingNode['variants']) ? $existingNode['variants'] : [];

        foreach ($productNode['variants'] as $variantKey => $variantPayload) {
            if (isset($existingVariants[$variantKey])) {
                $updated++;
            } else {
                $created++;
            }
            $existingVariants[$variantKey] = $variantPayload;
        }

        $payload = [
            'display_name' => (string)($productNode['display_name'] ?? ($existingNode['display_name'] ?? $productKey)),
            'variants' => $existingVariants
        ];
        [$ok, $err] = curlJsonPut(FIREBASE_URL . "products/$productKey.json", $payload);
        if (!$ok) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to import seed rows.']);
            exit;
        }
    }

    $auditKey = uniqid('seed_', true);
    curlJsonPut(FIREBASE_URL . "system_settings/product_price_audit/$auditKey.json", [
        'action' => 'seed_import_table_a',
        'created' => $created,
        'updated' => $updated,
        'actor' => $adminUser,
        'product_type' => $productType,
        'timestamp' => time()
    ]);

    pushRealtimeEvent('product_price_updated', [
        'change_type' => 'seed_import_table_a',
        'actor' => $adminUser
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Seed catalog imported.',
        'created' => $created,
        'updated' => $updated,
        'total_rows' => $created + $updated
    ]);
    exit;
}

if ($method === 'POST' && $action === 'seed_import_all_known') {
    requireCsrfOrJsonError();

    $adminUser = $_SESSION['jth_admin_user'] ?? ADMIN_USER;
    [$existingProducts, $fetchErr] = curlJsonGet(FIREBASE_URL . "products.json");
    if ($fetchErr !== null) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Unable to fetch existing products.']);
        exit;
    }
    if (!is_array($existingProducts)) {
        $existingProducts = [];
    }

    $combined = [];
    $catalogs = [
        buildSeedProductsCatalogTableA('windows', $adminUser),
        buildSeedProductsCatalogTableB('windows', $adminUser),
        buildSeedProductsCatalogTableCNoScreen('windows', $adminUser),
        buildSeedAccessoriesCatalogTableD($adminUser),
        buildSeedProductsCatalogTableENoScreen('windows', $adminUser)
    ];

    foreach ($catalogs as $cat) {
        if (!is_array($cat)) continue;
        foreach ($cat as $productKey => $productNode) {
            if (!isset($combined[$productKey])) {
                $combined[$productKey] = [
                    'display_name' => (string)($productNode['display_name'] ?? $productKey),
                    'variants' => []
                ];
            }
            if (isset($productNode['variants']) && is_array($productNode['variants'])) {
                foreach ($productNode['variants'] as $variantKey => $variantPayload) {
                    $combined[$productKey]['variants'][$variantKey] = $variantPayload;
                }
            }
        }
    }

    [$ok, $created, $updated] = upsertSeedCatalog($combined, $existingProducts);
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to import all seed tables.']);
        exit;
    }

    $auditKey = uniqid('seed_all_', true);
    curlJsonPut(FIREBASE_URL . "system_settings/product_price_audit/$auditKey.json", [
        'action' => 'seed_import_all_known',
        'created' => $created,
        'updated' => $updated,
        'actor' => $adminUser,
        'timestamp' => time()
    ]);

    pushRealtimeEvent('product_price_updated', [
        'change_type' => 'seed_import_all_known',
        'actor' => $adminUser
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'All known seed tables imported.',
        'created' => $created,
        'updated' => $updated,
        'total_rows' => $created + $updated
    ]);
    exit;
}

if ($method === 'POST' && $action === 'bulk_set_type') {
    requireCsrfOrJsonError();

    $targetType = strtolower(trim((string)($_POST['target_type'] ?? '')));
    $variantKeysJson = (string)($_POST['variant_keys_json'] ?? '');

    if ($targetType === '' || !in_array($targetType, PRODUCT_TYPES, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid target product type.']);
        exit;
    }

    $variantKeys = [];
    if ($variantKeysJson !== '') {
        $decoded = json_decode($variantKeysJson, true);
        if (is_array($decoded)) {
            $variantKeys = $decoded;
        }
    }

    if (!is_array($variantKeys) || count($variantKeys) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No variants selected for bulk update.']);
        exit;
    }
    if (count($variantKeys) > 3000) {
        echo json_encode(['status' => 'error', 'message' => 'Selection too large. Please reduce batch size.']);
        exit;
    }

    $adminUser = $_SESSION['jth_admin_user'] ?? ADMIN_USER;
    $nowTs = time();
    $updated = 0;
    $skipped = 0;
    $seen = [];

    foreach ($variantKeys as $rawKey) {
        $itemKey = trim((string)$rawKey);
        if ($itemKey === '' || isset($seen[$itemKey])) {
            continue;
        }
        $seen[$itemKey] = true;

        [$productKey, $variantKey] = explode('__', $itemKey, 2) + [null, null];
        $productKey = trim((string)$productKey);
        $variantKey = trim((string)$variantKey);

        if (!preg_match('/^[a-zA-Z0-9_-]{2,80}$/', $productKey) || !preg_match('/^[a-zA-Z0-9_-]{2,120}$/', $variantKey)) {
            $skipped++;
            continue;
        }

        $variantUrl = FIREBASE_URL . "products/$productKey/variants/$variantKey.json";
        [$node, $fetchErr] = curlJsonGet($variantUrl);
        if ($fetchErr !== null || !is_array($node) || empty($node['label'])) {
            $skipped++;
            continue;
        }

        [$ok, $err] = curlJsonPatch($variantUrl, [
            'product_type' => $targetType,
            'updated_at' => $nowTs,
            'updated_by' => $adminUser
        ]);
        if ($ok) {
            $updated++;
        } else {
            $skipped++;
        }
    }

    $auditKey = uniqid('bulk_type_', true);
    curlJsonPut(FIREBASE_URL . "system_settings/product_price_audit/$auditKey.json", [
        'action' => 'bulk_set_type',
        'target_type' => $targetType,
        'updated' => $updated,
        'skipped' => $skipped,
        'actor' => $adminUser,
        'timestamp' => $nowTs
    ]);

    pushRealtimeEvent('product_price_updated', [
        'change_type' => 'bulk_set_type',
        'target_type' => $targetType,
        'updated' => $updated,
        'actor' => $adminUser
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Bulk type update complete.',
        'updated' => $updated,
        'skipped' => $skipped
    ]);
    exit;
}

if ($method === 'POST' && $action === 'delete') {
    requireCsrfOrJsonError();

    $productKey = trim((string)($_POST['product_key'] ?? ''));
    $variantKey = trim((string)($_POST['variant_key'] ?? ''));
    $productName = trim((string)($_POST['product_name'] ?? ''));
    $variantLabel = trim((string)($_POST['variant_label'] ?? ''));

    if (!preg_match('/^[a-zA-Z0-9_-]{2,80}$/', $productKey)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product key.']);
        exit;
    }
    if (!preg_match('/^[a-zA-Z0-9_-]{2,120}$/', $variantKey)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid variant key.']);
        exit;
    }

    $adminUser = $_SESSION['jth_admin_user'] ?? ADMIN_USER;
    $nowTs = time();

    [$productNode, $fetchErr] = curlJsonGet(FIREBASE_URL . "products/$productKey.json");
    if ($fetchErr !== null || !is_array($productNode)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
        exit;
    }
    $variants = $productNode['variants'] ?? null;
    if (!is_array($variants) || !array_key_exists($variantKey, $variants)) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Variant not found.']);
        exit;
    }

    $variantPath = FIREBASE_URL . "products/$productKey/variants/$variantKey.json";
    [$okDelete, $deleteErr] = curlJsonDelete($variantPath);
    if (!$okDelete) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete variant.']);
        exit;
    }

    $remainingCount = count($variants) - 1;
    if ($remainingCount <= 0) {
        curlJsonDelete(FIREBASE_URL . "products/$productKey.json");
    }

    $auditKey = uniqid('pd_', true);
    $auditPath = FIREBASE_URL . "system_settings/product_price_audit/$auditKey.json";
    $auditPayload = [
        'product_key' => $productKey,
        'variant_key' => $variantKey,
        'product_name' => $productName,
        'variant_label' => $variantLabel,
        'action' => 'delete',
        'actor' => $adminUser,
        'timestamp' => $nowTs
    ];
    curlJsonPut($auditPath, $auditPayload);

    appLog('warning', 'product_variant_deleted', [
        'product_key' => $productKey,
        'variant_key' => $variantKey,
        'actor' => $adminUser
    ]);

    pushRealtimeEvent('product_price_updated', [
        'product_key' => $productKey,
        'variant_key' => $variantKey,
        'actor' => $adminUser,
        'change_type' => 'delete'
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Product variant deleted successfully.',
        'updated_at' => $nowTs,
        'updated_by' => $adminUser
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Unsupported request.']);
