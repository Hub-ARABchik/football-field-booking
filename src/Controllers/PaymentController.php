<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApiResponse;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Repositories\PaymentRepository;
use RuntimeException;

final class PaymentController
{
    public function setStatus(int $id): void
    {
        $admin = Auth::requireRole(['ADMIN']);
        Csrf::verify();
        $data = Request::data();
        $status = (string) ($data['status'] ?? '');
        if (!in_array($status, ['confirmed', 'rejected'], true)) {
            ApiResponse::error('validation_failed', 'Payment status must be confirmed or rejected.', 422);
        }
        try {
            (new PaymentRepository())->setStatusByAdmin($id, (int) $admin['id'], $status);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'payment_not_found') ApiResponse::error('payment_not_found', 'The payment was not found.', 404);
            if ($exception->getMessage() === 'payment_not_pending') ApiResponse::error('payment_not_pending', 'Only pending payments can be reviewed.', 409);
            throw $exception;
        }
        ApiResponse::success(['id' => $id, 'status' => $status]);
    }
}
