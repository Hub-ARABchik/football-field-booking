<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    public static function user(): ?array
    {
        $id = filter_var($_SESSION['uid'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) return null;
        $user = (new UserRepository())->findPublicById((int) $id);
        if (!$user || $user['status'] !== 'active') {
            unset($_SESSION['uid']);
            return null;
        }
        return $user;
    }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) ApiResponse::error('unauthenticated', 'Sign in to continue.', 401);
        return $user;
    }

    public static function requireRole(array $roles): array
    {
        $user = self::requireUser();
        if (!in_array($user['role'], $roles, true)) {
            ApiResponse::error('forbidden', 'You do not have permission to perform this action.', 403);
        }
        return $user;
    }
}
