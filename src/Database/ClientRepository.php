<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class ClientRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(): array
    {
        return $this->db->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO clients (name, address, contact, email, phone, notes)
            VALUES (:name, :address, :contact, :email, :phone, :notes)
        ");
        $stmt->execute([
            'name'    => trim($data['name']    ?? ''),
            'address' => trim($data['address'] ?? ''),
            'contact' => trim($data['contact'] ?? ''),
            'email'   => trim($data['email']   ?? ''),
            'phone'   => trim($data['phone']   ?? ''),
            'notes'   => trim($data['notes']   ?? ''),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE clients
            SET name=:name, address=:address, contact=:contact,
                email=:email, phone=:phone, notes=:notes,
                updated_at=datetime('now','localtime')
            WHERE id=:id
        ");
        $stmt->execute([
            'name'    => trim($data['name']    ?? ''),
            'address' => trim($data['address'] ?? ''),
            'contact' => trim($data['contact'] ?? ''),
            'email'   => trim($data['email']   ?? ''),
            'phone'   => trim($data['phone']   ?? ''),
            'notes'   => trim($data['notes']   ?? ''),
            'id'      => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
    }

    /** @return array{id:int,name:string,address:string,contact:string,email:string,phone:string}[] */
    public function allForSelect(): array
    {
        return $this->db->query(
            "SELECT id, name, address, contact, email, phone FROM clients ORDER BY name ASC"
        )->fetchAll();
    }
}
