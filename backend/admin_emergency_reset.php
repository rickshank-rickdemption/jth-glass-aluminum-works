<?php

require_once __DIR__ . '/config.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run in CLI.\n");
    exit(1);
}

function parseArgs(array $argv)
{
    $out = [
        'password' => '',
        'generate' => false,
        'help' => false
    ];
    foreach ($argv as $arg) {
        if (strpos($arg, '--password=') === 0) {
            $out['password'] = (string)substr($arg, strlen('--password='));
            continue;
        }
        if ($arg === '--generate') {
            $out['generate'] = true;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            $out['help'] = true;
            continue;
        }
    }
    return $out;
}

function randomPassword($len = 20)
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*_-';
    $max = strlen($alphabet) - 1;
    $pwd = '';
    for ($i = 0; $i < $len; $i++) {
        $pwd .= $alphabet[random_int(0, $max)];
    }
    return $pwd;
}

function loadStore($path)
{
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function saveStore($path, array $data)
{
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return false;
    }
    return @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

$args = parseArgs(array_slice($argv, 1));
if ($args['help']) {
    echo "Emergency admin reset (CLI only)\n";
    echo "Usage:\n";
    echo "  php backend/admin_emergency_reset.php --password=\"NewStrongPass123!\"\n";
    echo "  php backend/admin_emergency_reset.php --generate\n";
    exit(0);
}

$password = trim((string)$args['password']);
if ($args['generate']) {
    $password = randomPassword(22);
}

if ($password === '') {
    fwrite(STDERR, "Error: Provide --password=\"...\" or use --generate\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Error: Password must be at least 12 characters.\n");
    exit(1);
}

$store = loadStore(ADMIN_AUTH_STORE);
$sessionVersion = (int)($store['session_version'] ?? 0);
if ($sessionVersion <= 0) {
    $sessionVersion = 1;
}

$store['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
$store['must_change_password'] = true;
$store['updated_at'] = time();
$store['session_version'] = $sessionVersion + 1;

$store['recovery_enabled'] = false;
unset($store['totp_secret'], $store['recovery_code_hash'], $store['recovery_updated_at']);

if (!saveStore(ADMIN_AUTH_STORE, $store)) {
    fwrite(STDERR, "Error: Unable to write " . ADMIN_AUTH_STORE . "\n");
    exit(1);
}

echo "Emergency reset complete.\n";
echo "Temporary password: {$password}\n";
echo "Next steps:\n";
echo "1) Login at admin-login.html\n";
echo "2) Change password immediately\n";
echo "3) Reconfigure recovery setup\n";
echo "4) Regenerate and store a new recovery code securely\n";

