<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public static function data(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') return [];
            try {
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                ApiResponse::error('invalid_json', 'The request body is not valid JSON.', 400);
            }
            if (!is_array($data)) ApiResponse::error('invalid_json', 'The JSON body must be an object.', 400);
            return $data;
        }
        return $_POST;
    }

    public static function query(): array { return $_GET; }
}
