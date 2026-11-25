<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserRepository
{
    public function __construct(private readonly ?PDO $db = null) {}
    private function pdo(): PDO { return $this->db ?? Database::connection(); }

    public function findPublicById(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT id, full_name, phone, email, role, status, created_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByIdentity(string $identity): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT id, full_name, phone, email, password_hash, role, status FROM users WHERE phone = :phone OR email = :email LIMIT 1');
        $stmt->execute(['phone' => $identity, 'email' => $identity]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createAccount(array $data): int
    {
        $stmt = $this->pdo()->prepare(
            "INSERT INTO users (full_name, phone, email, password_hash, role, status, created_at, updated_at)
             VALUES (:full_name, :phone, :email, :password_hash, :role, 'active', NOW(), NOW())"
        );
        $stmt->execute([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'password_hash' => $data['password_hash'],
            'role' => $data['role'],
        ]);
        return (int) $this->pdo()->lastInsertId();
    }
}
