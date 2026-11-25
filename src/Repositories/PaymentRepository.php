<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

final class PaymentRepository
{
    public function __construct(private readonly ?PDO $db = null) {}
    private function pdo(): PDO { return $this->db ?? Database::connection(); }

    public function setStatusByAdmin(int $paymentId, int $adminId, string $status): void
    {
        if (!in_array($status, ['confirmed', 'rejected'], true)) throw new RuntimeException('invalid_payment_status');
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id, status FROM payments WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $paymentId]);
            $payment = $lock->fetch();
            if (!$payment) throw new RuntimeException('payment_not_found');
            if ($payment['status'] !== 'pending') throw new RuntimeException('payment_not_pending');

            $update = $pdo->prepare('UPDATE payments SET status = :status, confirmed_by = :admin_id, confirmed_at = :confirmed_at, updated_at = NOW() WHERE id = :id');
            $update->execute(['status' => $status, 'admin_id' => $adminId, 'confirmed_at' => $status === 'confirmed' ? date('Y-m-d H:i:s') : null, 'id' => $paymentId]);
            $audit = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details, created_at) VALUES (:actor, 'payment.status_changed', 'payment', :entity_id, :details, NOW())");
            $audit->execute(['actor' => $adminId, 'entity_id' => $paymentId, 'details' => json_encode(['status' => $status], JSON_UNESCAPED_UNICODE)]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
}
