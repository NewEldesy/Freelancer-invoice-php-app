<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class ProjectRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** @return array<string, mixed>[] */
    public function all(): array
    {
        return $this->db->query("
            SELECT p.*, i.number AS invoice_number, i.client_name, i.total_net
            FROM projects p
            JOIN invoices i ON i.id = p.invoice_id
            ORDER BY p.created_at DESC
        ")->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, i.number AS invoice_number, i.client_name, i.total_net
            FROM projects p
            JOIN invoices i ON i.id = p.invoice_id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByInvoice(int $invoiceId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE invoice_id = ? LIMIT 1");
        $stmt->execute([$invoiceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{total:int, non_commence:int, en_cours:int, livre:int, valide:int} */
    public function stats(): array
    {
        $row = $this->db->query("
            SELECT
                COUNT(*)                            AS total,
                SUM(status = 'non_commence')        AS non_commence,
                SUM(status = 'en_cours')            AS en_cours,
                SUM(status = 'livre')               AS livre,
                SUM(status = 'valide')              AS valide
            FROM projects
        ")->fetch();

        return [
            'total'        => (int) $row['total'],
            'non_commence' => (int) $row['non_commence'],
            'en_cours'     => (int) $row['en_cours'],
            'livre'        => (int) $row['livre'],
            'valide'       => (int) $row['valide'],
        ];
    }

    public function create(int $invoiceId, string $title, string $startDate = ''): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO projects (invoice_id, title, status, start_date)
            VALUES (?, ?, 'en_cours', ?)
        ");
        $stmt->execute([$invoiceId, $title, $startDate ?: date('Y-m-d')]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE projects SET
                title = :title, status = :status,
                start_date = :start_date, end_date = :end_date, notes = :notes,
                updated_at = datetime('now','localtime')
            WHERE id = :id
        ");
        $stmt->execute([
            ':title'      => trim($data['title']      ?? ''),
            ':status'     => $data['status']          ?? 'non_commence',
            ':start_date' => $data['start_date']      ?? '',
            ':end_date'   => $data['end_date']        ?? '',
            ':notes'      => trim($data['notes']      ?? ''),
            ':id'         => $id,
        ]);
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare(
            "UPDATE projects SET status = ?, updated_at = datetime('now','localtime') WHERE id = ?"
        );
        $stmt->execute([$status, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);
    }
}
