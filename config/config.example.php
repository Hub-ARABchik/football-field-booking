<?php

declare(strict_types=1);

/**
 * FFBS configuration template.
 *
 * Copy this file to config/config.php on the hosting account and replace the
 * YOUR_INFINITYFREE_* values with the credentials shown in the InfinityFree
 * Control Panel. Never commit config/config.php.
 */

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
        // Production must keep debug disabled. Enable only on a local/dev copy.
        'debug' => filter_var($env('APP_DEBUG', '0'), FILTER_VALIDATE_BOOLEAN),
        // Empty base path means the site is installed at the domain document root.
        // Example for a subfolder: /ffbs
        'base_path' => $basePath,
        'timezone' => 'Asia/Dushanbe',
        'currency' => 'сом.',
    ],

    'db' => [
        'driver' => 'mysql',
        // Copy the exact MySQL Hostname from InfinityFree Control Panel.
        'host' => (string) $env('DB_HOST', 'YOUR_INFINITYFREE_MYSQL_HOST'),
        'port' => (int) $env('DB_PORT', 3306),
        // Use the complete database name and username, including any hosting prefix.
        'database' => (string) $env('DB_NAME', 'YOUR_INFINITYFREE_DATABASE_NAME'),
        'user' => (string) $env('DB_USER', 'YOUR_INFINITYFREE_DATABASE_USERNAME'),
        'pass' => (string) $env('DB_PASS', 'YOUR_INFINITYFREE_DATABASE_PASSWORD'),
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'session_name' => 'FFBSSESSID',
        'session_lifetime' => 0,
        // Keep true on the live HTTPS site. Set SESSION_SECURE=0 only for local HTTP.
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
        // The runtime bootstrap resolves this relative to the project root.
        'directory' => dirname(__DIR__) . '/uploads',
        'max_file_size' => 5 * 1024 * 1024,
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
        ],
    ],
];
