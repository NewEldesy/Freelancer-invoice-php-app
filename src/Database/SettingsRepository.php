<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class SettingsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS company_settings (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL DEFAULT ''
            );
        ");
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $rows = $this->db->query("SELECT key, value FROM company_settings")->fetchAll();
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    }

    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare("SELECT value FROM company_settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    }

    /** @param array<string, string> $data */
    public function saveAll(array $data): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO company_settings (key, value) VALUES (?, ?)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value"
        );
        foreach ($data as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }
}
