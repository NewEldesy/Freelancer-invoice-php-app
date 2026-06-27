<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }

    /** @return array<string, mixed>[] */
    public function all(): array
    {
        return $this->db->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at")->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $username, string $email, string $password, string $role = 'user'): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, role)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    }

    public function verify(string $username, string $password): ?array
    {
        $user = $this->findByUsername($username);
        if ($user === null) return null;
        if (!password_verify($password, $user['password_hash'])) return null;
        return $user;
    }
}
