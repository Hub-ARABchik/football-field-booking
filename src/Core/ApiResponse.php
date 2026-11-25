<?php

declare(strict_types=1);

namespace App\Core;

final class ApiResponse
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function success(mixed $data = null, int $status = 200, array $meta = []): never
    {
        self::json(['ok' => true, 'data' => $data, 'meta' => (object) $meta], $status);
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): never
    {
        $payload = ['ok' => false, 'error' => $code, 'message' => $message];
        if ($details !== []) $payload['details'] = $details;
        self::json($payload, $status);
    }
}
