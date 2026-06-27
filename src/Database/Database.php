<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $path = __DIR__ . '/../../storage/invoices.sqlite';
            self::$instance = new PDO('sqlite:' . $path);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$instance->exec('PRAGMA foreign_keys = ON;');
            self::migrate(self::$instance);
        }
        return self::$instance;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS invoices (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                number           TEXT    NOT NULL,
                type             TEXT    NOT NULL DEFAULT 'FACTURE PROFORMA',
                status           TEXT    NOT NULL DEFAULT 'brouillon',
                subject          TEXT,
                issued_at        TEXT,
                due_at           TEXT,
                issuer_name      TEXT,
                issuer_address   TEXT,
                issuer_phone     TEXT,
                issuer_email     TEXT,
                issuer_ifu       TEXT,
                issuer_logo_path TEXT,
                client_name      TEXT,
                client_address   TEXT,
                client_contact   TEXT,
                tax_rate         REAL    NOT NULL DEFAULT 5,
                tax_label        TEXT    NOT NULL DEFAULT 'Prelevement 5%',
                signatory_title  TEXT,
                signatory_name   TEXT,
                footer_text          TEXT,
                prestation_label     TEXT    NOT NULL DEFAULT 'Frais de prestation',
                prestation_amount    INTEGER NOT NULL DEFAULT 0,
                total_ht             INTEGER NOT NULL DEFAULT 0,
                total_net            INTEGER NOT NULL DEFAULT 0,
                created_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                updated_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            );

            CREATE TABLE IF NOT EXISTS invoice_lines (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id   INTEGER NOT NULL,
                sort_order   INTEGER NOT NULL DEFAULT 0,
                description  TEXT    NOT NULL,
                quantity     INTEGER NOT NULL DEFAULT 1,
                unit_price   INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            );
        ");

        /* ── Authentification ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT    NOT NULL UNIQUE,
                email         TEXT    NOT NULL UNIQUE,
                password_hash TEXT    NOT NULL,
                role          TEXT    NOT NULL DEFAULT 'user',
                created_at    TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            );
        ");

        /* ── Phase 1 : pipeline commercial ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS opportunities (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                title            TEXT    NOT NULL,
                client_name      TEXT,
                client_address   TEXT,
                client_contact   TEXT,
                description      TEXT,
                estimated_amount INTEGER NOT NULL DEFAULT 0,
                status           TEXT    NOT NULL DEFAULT 'prospect',
                source           TEXT,
                notes            TEXT,
                invoice_id       INTEGER,
                expected_close   TEXT,
                created_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                updated_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            );
        ");

        /* ── Phase 2 : suivi exécution ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS projects (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id       INTEGER NOT NULL,
                title            TEXT    NOT NULL,
                status           TEXT    NOT NULL DEFAULT 'non_commence',
                start_date       TEXT,
                end_date         TEXT,
                notes            TEXT,
                created_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                updated_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
            );
        ");

        /* ── Phase 3 : dépenses / bénéfice ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS expenses (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id  INTEGER,
                category    TEXT    NOT NULL DEFAULT 'autre',
                description TEXT    NOT NULL,
                amount      INTEGER NOT NULL DEFAULT 0,
                date        TEXT,
                created_at  TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
            );
        ");

        /* ── Clés de licence pré-générées ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS license_keys (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                key_value    TEXT    NOT NULL UNIQUE,
                edition      TEXT    NOT NULL,
                period       TEXT    NOT NULL,
                expires_at   TEXT,
                generated_at TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                used         INTEGER NOT NULL DEFAULT 0,
                used_at      TEXT,
                used_machine TEXT
            );
        ");

        /* ── Système de licence ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS license (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                license_key  TEXT,
                edition      TEXT    NOT NULL DEFAULT 'free',
                expires_at   TEXT,
                machine_id   TEXT,
                activated_at TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            );
        ");

        /* ── Compteurs d'usage (plan gratuit) ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS feature_usage (
                feature TEXT PRIMARY KEY,
                count   INTEGER NOT NULL DEFAULT 0
            );
        ");

        /* ── Base clients ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clients (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL,
                address    TEXT,
                contact    TEXT,
                email      TEXT,
                phone      TEXT,
                notes      TEXT,
                created_at TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                updated_at TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            );
        ");

        /* ── Catalogue de prestations ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS services (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL,
                description TEXT    NOT NULL,
                unit_price  INTEGER NOT NULL DEFAULT 0,
                category    TEXT    NOT NULL DEFAULT 'general',
                created_at  TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            );
        ");

        /* ── Paiements partiels ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payments (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
                amount     INTEGER NOT NULL DEFAULT 0,
                paid_at    TEXT    NOT NULL DEFAULT (date('now','localtime')),
                note       TEXT,
                created_at TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            );
        ");

        /* Safe column additions for existing databases */
        foreach ([
            "ALTER TABLE invoices ADD COLUMN prestation_label  TEXT    NOT NULL DEFAULT 'Frais de prestation'",
            "ALTER TABLE invoices ADD COLUMN prestation_amount INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE invoices ADD COLUMN client_id         INTEGER",
            "ALTER TABLE opportunities ADD COLUMN client_id    INTEGER",
            "ALTER TABLE invoices ADD COLUMN origin_id         INTEGER",
        ] as $sql) {
            try { $pdo->exec($sql); } catch (\Exception) { /* column already exists */ }
        }
    }
}
