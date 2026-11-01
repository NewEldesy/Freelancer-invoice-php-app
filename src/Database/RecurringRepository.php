<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class RecurringRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        return $this->db->query(
            "SELECT * FROM recurring_templates ORDER BY next_due_at ASC"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM recurring_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Templates actifs dont la prochaine échéance est aujourd'hui ou dépassée (max 50) */
    public function dueNow(): array
    {
        return $this->db->query(
            "SELECT * FROM recurring_templates WHERE active = 1 AND next_due_at <= date('now','localtime') LIMIT 50"
        )->fetchAll();
    }

    /**
     * Génère une facture depuis un template dans une transaction atomique :
     * next_due_at est avancé EN PREMIER, puis la facture est créée.
     * Si le process est interrompu après l'avancement mais avant la création,
     * aucune facture en double ne peut être générée au prochain appel.
     *
     * @return int ID de la nouvelle facture
     */
    public function generateInvoice(array $tmpl, InvoiceRepository $repo, array $settings): int
    {
        $lines    = json_decode((string)$tmpl['lines'], true) ?: [];
        $totalHt  = array_sum(array_map(fn($l) => (int)$l['quantity'] * (int)$l['unit_price'], $lines));
        $tax      = (int) round($totalHt * (float)$tmpl['tax_rate'] / 100);
        $totalNet = $totalHt - $tax + (int)$tmpl['prestation_amount'];
        $today    = date('Y-m-d');

        $invData = [
            'number'            => $repo->nextNumber(),
            'type'              => $tmpl['type'],
            'status'            => 'brouillon',
            'subject'           => $tmpl['subject'],
            'issued_at'         => $today,
            'due_at'            => date('Y-m-d', strtotime('+30 days')),
            'issuer_name'       => $settings['issuer_name']      ?? '',
            'issuer_address'    => $settings['issuer_address']   ?? '',
            'issuer_phone'      => $settings['issuer_phone']     ?? '',
            'issuer_email'      => $settings['issuer_email']     ?? '',
            'issuer_ifu'        => $settings['issuer_ifu']       ?? '',
            'issuer_logo_path'  => $settings['issuer_logo_path'] ?? '',
            'client_name'       => $tmpl['client_name'],
            'client_address'    => $tmpl['client_address'],
            'client_contact'    => $tmpl['client_contact'],
            'client_whatsapp'   => $tmpl['client_whatsapp'],
            'tax_rate'          => $tmpl['tax_rate'],
            'tax_label'         => $tmpl['tax_label'],
            'signatory_title'   => $settings['signatory_title']  ?? '',
            'signatory_name'    => $settings['signatory_name']   ?? '',
            'footer_text'       => $settings['footer_text']      ?? '',
            'prestation_label'  => $tmpl['prestation_label'],
            'prestation_amount' => $tmpl['prestation_amount'],
            'total_ht'          => $totalHt,
            'total_net'         => $totalNet,
            'origin_id'         => null,
            'lines'             => $lines,
        ];

        // SQLite ne supporte pas les transactions imbriquées et détecter une transaction
        // externe ouverte sur la même connexion singleton pour éviter un rollback silencieux.
        if ($this->db->inTransaction()) {
            throw new \LogicException('generateInvoice() ne peut pas être appelé dans une transaction active.');
        }

        $this->db->beginTransaction();
        try {
            // Avancer le template EN PREMIER, si la requête est interrompue
            // après ça, le template est déjà avancé et ne sera plus dans dueNow().
            $this->markGenerated((int)$tmpl['id'], self::nextDate($today, $tmpl['interval_type']));
            $invoiceId = $repo->create($invData);
            $this->db->commit();
            return $invoiceId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO recurring_templates
                (label, client_name, client_address, client_contact, client_whatsapp,
                 subject, type, tax_rate, tax_label, prestation_label, prestation_amount,
                 lines, interval_type, next_due_at, active)
            VALUES
                (:label, :client_name, :client_address, :client_contact, :client_whatsapp,
                 :subject, :type, :tax_rate, :tax_label, :prestation_label, :prestation_amount,
                 :lines, :interval_type, :next_due_at, :active)
        ");
        $stmt->execute($this->fields($data));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE recurring_templates SET
                label=:label, client_name=:client_name, client_address=:client_address,
                client_contact=:client_contact, client_whatsapp=:client_whatsapp,
                subject=:subject, type=:type, tax_rate=:tax_rate, tax_label=:tax_label,
                prestation_label=:prestation_label, prestation_amount=:prestation_amount,
                lines=:lines, interval_type=:interval_type, next_due_at=:next_due_at,
                active=:active, updated_at=datetime('now','localtime')
            WHERE id=:id
        ");
        $stmt->execute(array_merge($this->fields($data), [':id' => $id]));
    }

    public function markGenerated(int $id, string $nextDueAt): void
    {
        $this->db->prepare("
            UPDATE recurring_templates
            SET last_generated_at = datetime('now','localtime'), next_due_at = ?, updated_at = datetime('now','localtime')
            WHERE id = ?
        ")->execute([$nextDueAt, $id]);
    }

    public function toggleActive(int $id): void
    {
        $this->db->prepare(
            "UPDATE recurring_templates SET active = 1 - active WHERE id = ?"
        )->execute([$id]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM recurring_templates WHERE id = ?")->execute([$id]);
    }

    /** Calcule la prochaine date en fonction de l'intervalle */
    public static function nextDate(string $from, string $interval): string
    {
        $dt      = new \DateTime($from);
        $origDay = (int) $dt->format('j');

        match ($interval) {
            'hebdomadaire' => $dt->modify('+7 days'),
            'trimestriel'  => $dt->modify('+3 months'),
            'annuel'       => $dt->modify('+1 year'),
            default        => $dt->modify('+1 month'),  // mensuel
        };

        // Recadrage du débordement de fin de mois : 31 jan + 1 mois donne 3 mars en PHP.
        // Détecte le débordement (nouveau jour < jour original) et recule au dernier jour du mois précédent.
        if ($interval !== 'hebdomadaire' && (int) $dt->format('j') < $origDay) {
            $dt->modify('last day of last month');
        }

        return $dt->format('Y-m-d');
    }

    private function fields(array $data): array
    {
        return [
            ':label'              => trim($data['label']              ?? ''),
            ':client_name'        => trim($data['client_name']        ?? ''),
            ':client_address'     => trim($data['client_address']     ?? ''),
            ':client_contact'     => trim($data['client_contact']     ?? ''),
            ':client_whatsapp'    => trim($data['client_whatsapp']    ?? ''),
            ':subject'            => trim($data['subject']            ?? ''),
            ':type'               => $data['type']                    ?? 'FACTURE PROFORMA',
            ':tax_rate'           => (float) ($data['tax_rate']       ?? 5),
            ':tax_label'          => $data['tax_label']               ?? 'Prelevement 5%',
            ':prestation_label'   => $data['prestation_label']        ?? 'Frais de prestation',
            ':prestation_amount'  => (int) ($data['prestation_amount'] ?? 0),
            ':lines'              => is_string($data['lines']) ? $data['lines'] : json_encode($data['lines'] ?? []),
            ':interval_type'      => $data['interval_type']           ?? 'mensuel',
            ':next_due_at'        => $data['next_due_at']             ?? date('Y-m-d'),
            ':active'             => isset($data['active']) ? (int) $data['active'] : 1,
        ];
    }
}
