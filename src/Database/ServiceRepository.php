<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class ServiceRepository
{
    private PDO $db;

    public const CATEGORIES = [
        'general'     => 'Général',
        'dev'         => 'Développement',
        'design'      => 'Design',
        'conseil'     => 'Conseil',
        'formation'   => 'Formation',
        'maintenance' => 'Maintenance',
        'autre'       => 'Autre',
    ];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        return $this->db->query(
            "SELECT * FROM services ORDER BY category ASC, name ASC"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM services")->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO services (name, description, unit_price, category)
            VALUES (:name, :description, :unit_price, :category)
        ");
        $stmt->execute([
            'name'        => trim($data['name']        ?? ''),
            'description' => trim($data['description'] ?? ''),
            'unit_price'  => (int) ($data['unit_price'] ?? 0),
            'category'    => $data['category'] ?? 'general',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE services
            SET name=:name, description=:description, unit_price=:unit_price, category=:category
            WHERE id=:id
        ");
        $stmt->execute([
            'name'        => trim($data['name']        ?? ''),
            'description' => trim($data['description'] ?? ''),
            'unit_price'  => (int) ($data['unit_price'] ?? 0),
            'category'    => $data['category'] ?? 'general',
            'id'          => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
    }

    public function allGrouped(): array
    {
        $rows = $this->all();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['category']][] = $row;
        }
        return $grouped;
    }
}
