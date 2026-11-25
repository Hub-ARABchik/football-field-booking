<?php

declare(strict_types=1);

use App\Core\Database;

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'configuration_missing', 'message' => 'FFBS is not configured. Copy config/config.example.php to config/config.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configFile;
if (!is_array($config) || !isset($config['app'], $config['db'])) {
    throw new RuntimeException('Invalid FFBS configuration structure.');
}
$dbConfig = $config['db'];
foreach (['host', 'database', 'user', 'pass'] as $key) {
    $value = (string) ($dbConfig[$key] ?? '');
    if ($value === '' || str_contains($value, 'YOUR_INFINITYFREE_')) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'database_not_configured', 'message' => 'Complete the private MySQL settings in config/config.php.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
$GLOBALS['ffbs_config'] = $config;
date_default_timezone_set((string) ($config['app']['timezone'] ?? 'Asia/Dushanbe'));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

$security = $config['security'] ?? [];
session_name((string) ($security['session_name'] ?? 'FFBSSESSID'));
$cookiePath = trim((string) ($config['app']['base_path'] ?? ''));
$cookiePath = $cookiePath === '' ? '/' : '/' . trim($cookiePath, '/');
session_set_cookie_params([
    'lifetime' => (int) ($security['session_lifetime'] ?? 0),
    'path' => $cookiePath,
    'secure' => (bool) ($security['session_secure'] ?? true),
    'httponly' => (bool) ($security['session_httponly'] ?? true),
    'samesite' => (string) ($security['session_samesite'] ?? 'Lax'),
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

Database::configure($config['db']);
