<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class SearchRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Recherche globale — retourne un tableau groupé par type de résultat.
     * @return array{invoices:array, devis:array, clients:array, expenses:array}
     */
    public function search(string $q): array
    {
        // Échapper les wildcards SQLite avant d'entourer avec %
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $like    = '%' . $escaped . '%';

        $invoices = $this->db->prepare("
            SELECT id, number, type, status, client_name, subject, total_net, issued_at
            FROM invoices
            WHERE type NOT IN ('DEVIS','AVOIR')
              AND (number LIKE ? ESCAPE '\\' OR client_name LIKE ? ESCAPE '\\' OR subject LIKE ? ESCAPE '\\')
            ORDER BY created_at DESC LIMIT 20
        ");
        $invoices->execute([$like, $like, $like]);

        $devis = $this->db->prepare("
            SELECT id, number, type, status, client_name, subject, total_net, issued_at
            FROM invoices
            WHERE type = 'DEVIS'
              AND (number LIKE ? ESCAPE '\\' OR client_name LIKE ? ESCAPE '\\' OR subject LIKE ? ESCAPE '\\')
            ORDER BY created_at DESC LIMIT 10
        ");
        $devis->execute([$like, $like, $like]);

        $clients = $this->db->prepare("
            SELECT id, name, address, contact, email, phone, whatsapp
            FROM clients
            WHERE name LIKE ? ESCAPE '\\' OR email LIKE ? ESCAPE '\\' OR phone LIKE ? ESCAPE '\\' OR contact LIKE ? ESCAPE '\\'
            ORDER BY name ASC LIMIT 10
        ");
        $clients->execute([$like, $like, $like, $like]);

        $expenses = $this->db->prepare("
            SELECT id, description, category, amount, date
            FROM expenses
            WHERE description LIKE ? ESCAPE '\\' OR category LIKE ? ESCAPE '\\'
            ORDER BY date DESC LIMIT 10
        ");
        $expenses->execute([$like, $like]);

        return [
            'invoices' => $invoices->fetchAll(),
            'devis'    => $devis->fetchAll(),
            'clients'  => $clients->fetchAll(),
            'expenses' => $expenses->fetchAll(),
        ];
    }
}
