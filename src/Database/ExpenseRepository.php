<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class ExpenseRepository
{
    private PDO $db;

    public const CATEGORIES = [
        'materiaux'    => 'Matériaux',
        'main_oeuvre'  => "Main d'œuvre",
        'transport'    => 'Transport',
        'autre'        => 'Autre',
    ];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM expenses")->fetchColumn();
    }

    /** @return array<string, mixed>[] */
    public function all(): array
    {
        return $this->db->query("
            SELECT e.*, i.number AS invoice_number, i.client_name
            FROM expenses e
            LEFT JOIN invoices i ON i.id = e.invoice_id
            ORDER BY e.date DESC, e.created_at DESC
        ")->fetchAll();
    }

    /** @return array<string, mixed>[] */
    public function allForInvoice(int $invoiceId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, i.number AS invoice_number, i.client_name
            FROM expenses e
            LEFT JOIN invoices i ON i.id = e.invoice_id
            WHERE e.invoice_id = ?
            ORDER BY e.date DESC, e.created_at DESC
        ");
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    public function totalForInvoice(int $invoiceId): int
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{total_depenses:int, ca_encaisse:int, ca_engage:int, benefice_net:int, by_category:array<string,int>} */
    public function globalStats(): array
    {
        $total = (int) $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) FROM expenses"
        )->fetchColumn();

        $row = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN status = 'payée'                THEN total_net ELSE 0 END), 0) AS ca_encaisse,
                COALESCE(SUM(CASE WHEN status IN ('envoyée','payée')   THEN total_net ELSE 0 END), 0) AS ca_engage
            FROM invoices
        ")->fetch();

        $caEncaisse = (int) $row['ca_encaisse'];
        $caEngage   = (int) $row['ca_engage'];

        $byCategory = [];
        $rows = $this->db->query(
            "SELECT category, COALESCE(SUM(amount), 0) AS s FROM expenses GROUP BY category"
        )->fetchAll();
        foreach ($rows as $r) {
            $byCategory[$r['category']] = (int) $r['s'];
        }

        return [
            'total_depenses' => $total,
            'ca_encaisse'    => $caEncaisse,
            'ca_engage'      => $caEngage,
            'benefice_net'   => $caEngage - $total,
            'by_category'    => $byCategory,
        ];
    }

    /**
     * Monthly expense breakdown for a given year.
     * Returns 12 rows filled with zeros for empty months.
     *
     * @return array<int, array{month:int, total:int}>
     */
    public function statsByMonth(int $year): array
    {
        $stmt = $this->db->prepare("
            SELECT
                CAST(strftime('%m', date) AS INTEGER) AS month,
                COALESCE(SUM(amount), 0)              AS total
            FROM expenses
            WHERE strftime('%Y', date) = :year
              AND date IS NOT NULL AND date != ''
            GROUP BY month
            ORDER BY month
        ");
        $stmt->execute([':year' => (string) $year]);
        $rows = $stmt->fetchAll();

        $byMonth = [];
        foreach ($rows as $r) {
            $byMonth[(int) $r['month']] = (int) $r['total'];
        }

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $result[$m] = ['month' => $m, 'total' => $byMonth[$m] ?? 0];
        }
        return $result;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO expenses (invoice_id, category, description, amount, date)
            VALUES (:invoice_id, :category, :description, :amount, :date)
        ");
        $stmt->execute([
            ':invoice_id'  => $data['invoice_id'] ? (int) $data['invoice_id'] : null,
            ':category'    => $data['category']    ?? 'autre',
            ':description' => trim($data['description'] ?? ''),
            ':amount'      => (int) ($data['amount'] ?? 0),
            ':date'        => $data['date']        ?? date('Y-m-d'),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM expenses WHERE id = ?")->execute([$id]);
    }
}
