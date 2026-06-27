<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class OpportunityRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM opportunities")->fetchColumn();
    }

    /** @return array<string, mixed>[] */
    public function all(): array
    {
        return $this->db
            ->query("SELECT * FROM opportunities ORDER BY created_at DESC")
            ->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM opportunities WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{total:int, prospect:int, devis_envoye:int, negociation:int, gagne:int, perdu:int, pipeline_value:int, won_value:int, conversion_rate:float} */
    public function stats(): array
    {
        $row = $this->db->query("
            SELECT
                COUNT(*)                                              AS total,
                SUM(o.status = 'prospect')                            AS prospect,
                SUM(o.status = 'devis_envoye')                       AS devis_envoye,
                SUM(o.status = 'negociation')                        AS negociation,
                SUM(o.status = 'gagne')                              AS gagne,
                SUM(o.status = 'perdu')                              AS perdu,
                COALESCE(SUM(CASE WHEN o.status NOT IN ('perdu')
                    THEN COALESCE(i.total_net, o.estimated_amount)
                    ELSE 0 END), 0)                                   AS pipeline_value,
                COALESCE(SUM(CASE WHEN o.status = 'gagne'
                    THEN COALESCE(i.total_net, o.estimated_amount)
                    ELSE 0 END), 0)                                   AS won_value
            FROM opportunities o
            LEFT JOIN invoices i ON i.id = o.invoice_id
        ")->fetch();

        $closed = (int)$row['gagne'] + (int)$row['perdu'];
        $rate   = $closed > 0 ? round((int)$row['gagne'] / $closed * 100, 1) : 0.0;

        return [
            'total'           => (int) $row['total'],
            'prospect'        => (int) $row['prospect'],
            'devis_envoye'    => (int) $row['devis_envoye'],
            'negociation'     => (int) $row['negociation'],
            'gagne'           => (int) $row['gagne'],
            'perdu'           => (int) $row['perdu'],
            'pipeline_value'  => (int) $row['pipeline_value'],
            'won_value'       => (int) $row['won_value'],
            'conversion_rate' => $rate,
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO opportunities
                (title, client_name, client_address, client_contact, description,
                 estimated_amount, status, source, notes, expected_close)
            VALUES
                (:title, :client_name, :client_address, :client_contact, :description,
                 :estimated_amount, :status, :source, :notes, :expected_close)
        ");
        $stmt->execute($this->fields($data));
        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE opportunities SET
                title = :title, client_name = :client_name, client_address = :client_address,
                client_contact = :client_contact, description = :description,
                estimated_amount = :estimated_amount, status = :status,
                source = :source, notes = :notes, expected_close = :expected_close,
                updated_at = datetime('now','localtime')
            WHERE id = :id
        ");
        $stmt->execute(array_merge($this->fields($data), [':id' => $id]));
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare(
            "UPDATE opportunities SET status = ?, updated_at = datetime('now','localtime') WHERE id = ?"
        );
        $stmt->execute([$status, $id]);
    }

    public function linkInvoice(int $id, int $invoiceId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE opportunities SET invoice_id = ?, status = 'gagne', updated_at = datetime('now','localtime') WHERE id = ?"
        );
        $stmt->execute([$invoiceId, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM opportunities WHERE id = ?")->execute([$id]);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed> */
    private function fields(array $data): array
    {
        return [
            ':title'            => trim($data['title']            ?? ''),
            ':client_name'      => trim($data['client_name']      ?? ''),
            ':client_address'   => trim($data['client_address']   ?? ''),
            ':client_contact'   => trim($data['client_contact']   ?? ''),
            ':description'      => trim($data['description']      ?? ''),
            ':estimated_amount' => (int) ($data['estimated_amount'] ?? 0),
            ':status'           => $data['status']                ?? 'prospect',
            ':source'           => trim($data['source']           ?? ''),
            ':notes'            => trim($data['notes']            ?? ''),
            ':expected_close'   => $data['expected_close']        ?? '',
        ];
    }
}
