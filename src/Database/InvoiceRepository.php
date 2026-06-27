<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class InvoiceRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** @return array<string, mixed>[] */
    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
    }

    public function all(): array
    {
        return $this->db
            ->query("SELECT * FROM invoices ORDER BY created_at DESC")
            ->fetchAll();
    }

    public function allByStatus(string $status): array
    {
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>[] */
    public function linesOf(int $invoiceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM invoice_lines WHERE invoice_id = ? ORDER BY sort_order"
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    /** @return array{total:int, brouillon:int, envoyee:int, payee:int, annulee:int, total_net:int} */
    public function stats(): array
    {
        $row = $this->db->query("
            SELECT
                COUNT(*)                                      AS total,
                SUM(status = 'brouillon')                     AS brouillon,
                SUM(status = 'envoyée')                       AS envoyee,
                SUM(status = 'payée')                         AS payee,
                SUM(status = 'annulée')                       AS annulee,
                COALESCE(SUM(CASE WHEN status IN ('envoyée','payée') THEN total_net ELSE 0 END), 0) AS ca_engage,
                COALESCE(SUM(CASE WHEN status = 'payée'              THEN total_net ELSE 0 END), 0) AS ca_encaisse
            FROM invoices
        ")->fetch();

        return [
            'total'     => (int) $row['total'],
            'brouillon' => (int) $row['brouillon'],
            'envoyee'   => (int) $row['envoyee'],
            'payee'     => (int) $row['payee'],
            'annulee'   => (int) $row['annulee'],
            'ca_engage'   => (int) $row['ca_engage'],
            'ca_encaisse' => (int) $row['ca_encaisse'],
        ];
    }

    /**
     * Monthly breakdown for a given year.
     * Returns 12 rows (one per month), filled with zeros for months with no data.
     *
     * @return array<int, array{month:int, ca_engage:int, ca_encaisse:int, nb_envoyee:int, nb_payee:int}>
     */
    public function statsByMonth(int $year): array
    {
        $stmt = $this->db->prepare("
            SELECT
                CAST(strftime('%m', issued_at) AS INTEGER) AS month,
                COALESCE(SUM(CASE WHEN status IN ('envoyée','payée') THEN total_net ELSE 0 END), 0) AS ca_engage,
                COALESCE(SUM(CASE WHEN status = 'payée'              THEN total_net ELSE 0 END), 0) AS ca_encaisse,
                SUM(CASE WHEN status = 'envoyée' THEN 1 ELSE 0 END) AS nb_envoyee,
                SUM(CASE WHEN status = 'payée'   THEN 1 ELSE 0 END) AS nb_payee
            FROM invoices
            WHERE strftime('%Y', issued_at) = :year
              AND status IN ('envoyée', 'payée')
            GROUP BY month
            ORDER BY month
        ");
        $stmt->execute([':year' => (string) $year]);
        $rows = $stmt->fetchAll();

        $byMonth = [];
        foreach ($rows as $r) {
            $byMonth[(int) $r['month']] = [
                'month'       => (int) $r['month'],
                'ca_engage'   => (int) $r['ca_engage'],
                'ca_encaisse' => (int) $r['ca_encaisse'],
                'nb_envoyee'  => (int) $r['nb_envoyee'],
                'nb_payee'    => (int) $r['nb_payee'],
            ];
        }

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $result[$m] = $byMonth[$m] ?? [
                'month' => $m, 'ca_engage' => 0, 'ca_encaisse' => 0,
                'nb_envoyee' => 0, 'nb_payee' => 0,
            ];
        }
        return $result;
    }

    /** @return int[] List of years that have invoices */
    public function availableYears(): array
    {
        $rows = $this->db->query("
            SELECT DISTINCT CAST(strftime('%Y', issued_at) AS INTEGER) AS y
            FROM invoices
            WHERE issued_at IS NOT NULL AND issued_at != ''
            ORDER BY y DESC
        ")->fetchAll();

        $years = array_map(fn($r) => (int) $r['y'], $rows);
        if (empty($years)) {
            $years = [(int) date('Y')];
        }
        return $years;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO invoices
                (number, type, status, subject, issued_at, due_at,
                 issuer_name, issuer_address, issuer_phone, issuer_email, issuer_ifu, issuer_logo_path,
                 client_name, client_address, client_contact,
                 tax_rate, tax_label, signatory_title, signatory_name, footer_text,
                 prestation_label, prestation_amount, total_ht, total_net)
            VALUES
                (:number, :type, :status, :subject, :issued_at, :due_at,
                 :issuer_name, :issuer_address, :issuer_phone, :issuer_email, :issuer_ifu, :issuer_logo_path,
                 :client_name, :client_address, :client_contact,
                 :tax_rate, :tax_label, :signatory_title, :signatory_name, :footer_text,
                 :prestation_label, :prestation_amount, :total_ht, :total_net)
        ");
        $stmt->execute($this->extractFields($data));
        $id = (int) $this->db->lastInsertId();
        $this->saveLines($id, $data['lines'] ?? []);
        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE invoices SET
                number = :number, type = :type, status = :status, subject = :subject,
                issued_at = :issued_at, due_at = :due_at,
                issuer_name = :issuer_name, issuer_address = :issuer_address,
                issuer_phone = :issuer_phone, issuer_email = :issuer_email,
                issuer_ifu = :issuer_ifu, issuer_logo_path = :issuer_logo_path,
                client_name = :client_name, client_address = :client_address, client_contact = :client_contact,
                tax_rate = :tax_rate, tax_label = :tax_label,
                signatory_title = :signatory_title, signatory_name = :signatory_name,
                footer_text = :footer_text,
                prestation_label = :prestation_label, prestation_amount = :prestation_amount,
                total_ht = :total_ht, total_net = :total_net,
                updated_at = datetime('now','localtime')
            WHERE id = :id
        ");
        $stmt->execute(array_merge($this->extractFields($data), [':id' => $id]));
        $this->db->prepare("DELETE FROM invoice_lines WHERE invoice_id = ?")->execute([$id]);
        $this->saveLines($id, $data['lines'] ?? []);
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare(
            "UPDATE invoices SET status = ?, updated_at = datetime('now','localtime') WHERE id = ?"
        );
        $stmt->execute([$status, $id]);
    }

    public function countByPrefix(string $prefix): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM invoices WHERE number LIKE ?");
        $stmt->execute([$prefix . '-%']);
        return (int) $stmt->fetchColumn();
    }

    public function nextNumber(string $date = ''): string
    {
        $day = $date ?: date('Y-m-d');
        $datePrefix = str_replace('-', '', $day); // 20260624

        // Use custom prefix from settings if defined, e.g. "FAC" → "FAC-20260624"
        $customPrefix = $this->db->query(
            "SELECT value FROM company_settings WHERE key = 'invoice_prefix'"
        )->fetchColumn();

        $prefix = ($customPrefix && $customPrefix !== '')
            ? $customPrefix . '-' . $datePrefix
            : $datePrefix;

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM invoices WHERE number LIKE ?"
        );
        $stmt->execute([$prefix . '-%']);
        $count = (int) $stmt->fetchColumn();

        return $prefix . '-' . ($count + 1);
    }

    /** Factures envoyées dont la date d'échéance est dépassée */
    public function overdue(): array
    {
        return $this->db->query("
            SELECT * FROM invoices
            WHERE status = 'envoyée'
              AND due_at IS NOT NULL AND due_at != ''
              AND due_at < date('now','localtime')
            ORDER BY due_at ASC
        ")->fetchAll();
    }

    public function overdueStats(): array
    {
        $row = $this->db->query("
            SELECT
                COUNT(*) AS count,
                COALESCE(SUM(total_net), 0) AS total
            FROM invoices
            WHERE status = 'envoyée'
              AND due_at IS NOT NULL AND due_at != ''
              AND due_at < date('now','localtime')
        ")->fetch();
        return ['count' => (int)$row['count'], 'total' => (int)$row['total']];
    }

    public function topClients(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT
                client_name,
                COUNT(*) AS nb_invoices,
                COALESCE(SUM(total_net), 0) AS ca
            FROM invoices
            WHERE client_name IS NOT NULL AND client_name != ''
              AND status IN ('envoyée','payée')
            GROUP BY client_name
            ORDER BY ca DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM invoices WHERE id = ?")->execute([$id]);
    }

    /** @param array<string, mixed> $data
     *  @return array<string, mixed> */
    private function extractFields(array $data): array
    {
        return [
            ':number'          => $data['number']          ?? '',
            ':type'            => $data['type']            ?? 'FACTURE PROFORMA',
            ':status'          => $data['status']          ?? 'brouillon',
            ':subject'         => $data['subject']         ?? '',
            ':issued_at'       => $data['issued_at']       ?? '',
            ':due_at'          => $data['due_at']          ?? '',
            ':issuer_name'     => $data['issuer_name']     ?? '',
            ':issuer_address'  => $data['issuer_address']  ?? '',
            ':issuer_phone'    => $data['issuer_phone']    ?? '',
            ':issuer_email'    => $data['issuer_email']    ?? '',
            ':issuer_ifu'      => $data['issuer_ifu']      ?? '',
            ':issuer_logo_path'=> $data['issuer_logo_path']?? '',
            ':client_name'     => $data['client_name']     ?? '',
            ':client_address'  => $data['client_address']  ?? '',
            ':client_contact'  => $data['client_contact']  ?? '',
            ':tax_rate'        => (float) ($data['tax_rate']  ?? 5),
            ':tax_label'       => $data['tax_label']       ?? 'Prelevement 5%',
            ':signatory_title' => $data['signatory_title'] ?? '',
            ':signatory_name'  => $data['signatory_name']  ?? '',
            ':footer_text'        => $data['footer_text']        ?? '',
            ':prestation_label'   => $data['prestation_label']   ?? 'Frais de prestation',
            ':prestation_amount'  => (int) ($data['prestation_amount'] ?? 0),
            ':total_ht'           => (int) ($data['total_ht']  ?? 0),
            ':total_net'          => (int) ($data['total_net'] ?? 0),
        ];
    }

    /** @param array<array<string, mixed>> $lines */
    private function saveLines(int $invoiceId, array $lines): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO invoice_lines (invoice_id, sort_order, description, quantity, unit_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($lines as $i => $line) {
            if (trim((string) ($line['description'] ?? '')) === '') {
                continue;
            }
            $stmt->execute([
                $invoiceId,
                $i,
                $line['description'],
                (int) ($line['quantity']   ?? 1),
                (int) ($line['unit_price'] ?? 0),
            ]);
        }
    }
}
