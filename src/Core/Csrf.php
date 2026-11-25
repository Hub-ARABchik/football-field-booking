<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $bytes = (int) ($GLOBALS['ffbs_config']['security']['csrf_bytes'] ?? 32);
            $_SESSION['_csrf'] = bin2hex(random_bytes(max(16, min(64, $bytes))));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(): void
    {
        $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $known = (string) ($_SESSION['_csrf'] ?? '');
        if ($known === '' || $provided === '' || !hash_equals($known, $provided)) {
            ApiResponse::error('csrf_failed', 'The security token is missing or expired. Reload the page and try again.', 419);
        }
    }

    public static function rotate(): string
    {
        unset($_SESSION['_csrf']);
        return self::token();
    }
}
