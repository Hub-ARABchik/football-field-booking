<?php

declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

$environment = strtolower((string) $env('APP_ENV', 'production'));
if (!in_array($environment, ['production', 'development', 'testing'], true)) {
    $environment = 'production';
}

$basePath = trim((string) $env('APP_BASE_PATH', ''));
$basePath = $basePath === '' ? '' : '/' . trim($basePath, '/');

return [
    'app' => [
        'name' => 'Football Field Booking System',
        'env' => $environment,
        'debug' => filter_var($env('APP_DEBUG', '0'), FILTER_VALIDATE_BOOLEAN),
        'base_path' => $basePath,
        'timezone' => 'Asia/Dushanbe',
        'currency' => 'сом.',
    ],

    'db' => [
        'driver' => 'mysql',
        'host' => (string) $env('DB_HOST', 'sql300.infinityfree.com'),
        'port' => (int) $env('DB_PORT', 3306),
        'database' => (string) $env('DB_NAME', 'football-booking'),
        'user' => (string) $env('DB_USER', 'if0_43450886'),
        'pass' => (string) $env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'session_name' => 'FFBSSESSID',
        'session_lifetime' => 0,
        'session_secure' => filter_var($env('SESSION_SECURE', '1'), FILTER_VALIDATE_BOOLEAN),
        'session_httponly' => true,
        'session_samesite' => 'Lax',
        'csrf_bytes' => 32,
    ],

    'booking' => [
        'cancel_hours' => 3,
        'max_advance_days' => 70,
    ],

    'uploads' => [
        'directory' => dirname(__DIR__) . '/uploads',
        'max_file_size' => 5 * 1024 * 1024,
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
    ],
];
