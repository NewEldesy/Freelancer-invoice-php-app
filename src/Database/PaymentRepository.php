<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class PaymentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function allForInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM payments WHERE invoice_id = ? ORDER BY paid_at ASC, id ASC"
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    public function totalForInvoice(int $invoiceId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ?"
        );
        $stmt->execute([$invoiceId]);
        return (int) $stmt->fetchColumn();
    }

    public function add(int $invoiceId, int $amount, string $paidAt, string $note): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO payments (invoice_id, amount, paid_at, note) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$invoiceId, $amount, $paidAt, $note]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM payments WHERE id = ?")->execute([$id]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
