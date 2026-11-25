<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiResponse;
use App\Core\Auth;
use App\Core\Database;

final class DashboardController
{
    public function owner(): void
    {
        $user = Auth::requireRole(['OWNER']);
        $pdo = Database::connection();
        $fields = $pdo->prepare('SELECT COUNT(*) FROM fields WHERE owner_id = :owner_id');
        $fields->execute(['owner_id' => $user['id']]);
        $bookings = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN fields f ON f.id = b.field_id WHERE f.owner_id = :owner_id");
        $bookings->execute(['owner_id' => $user['id']]);
        $pending = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN fields f ON f.id = b.field_id WHERE f.owner_id = :owner_id AND b.status = 'pending'");
        $pending->execute(['owner_id' => $user['id']]);
        ApiResponse::success(['fields' => (int) $fields->fetchColumn(), 'bookings' => (int) $bookings->fetchColumn(), 'pending_bookings' => (int) $pending->fetchColumn()]);
    }

    public function admin(): void
    {
        Auth::requireRole(['ADMIN']);
        $pdo = Database::connection();
        $stats = [];
        foreach (['users', 'fields', 'bookings', 'payments'] as $table) {
            $stats[$table] = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }
        $stats['pending_fields'] = (int) $pdo->query("SELECT COUNT(*) FROM fields WHERE status = 'pending'")->fetchColumn();
        $stats['pending_bookings'] = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
        $stats['pending_payments'] = (int) $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
        ApiResponse::success($stats);
    }
}
