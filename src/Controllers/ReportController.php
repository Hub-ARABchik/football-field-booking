<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiResponse;
use App\Core\Auth;
use App\Core\Database;

final class ReportController
{
    public function bookings(): void
    {
        Auth::requireRole(['ADMIN']);
        $sql = "SELECT DATE_FORMAT(b.booking_date, '%Y-%m') AS month,
                       COUNT(*) AS bookings,
                       SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
                       SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                       SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                       SUM(CASE WHEN p.status = 'confirmed' THEN p.amount ELSE 0 END) AS paid_amount
                FROM bookings b
                LEFT JOIN payments p ON p.booking_id = b.id
                GROUP BY DATE_FORMAT(b.booking_date, '%Y-%m')
                ORDER BY month DESC LIMIT 24";
        ApiResponse::success(Database::connection()->query($sql)->fetchAll());
    }
}
