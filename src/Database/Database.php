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
            $envPath = $_ENV['DB_PATH'] ?? getenv('DB_PATH') ?: '';
            $path    = ($envPath !== '')
                ? $envPath
                : __DIR__ . '/../../storage/invoices.sqlite';
            self::$instance = new PDO('sqlite:' . $path);
            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // WAL améliore les lectures concurrentes sans bloquer pendant les écritures
            self::$instance->exec('PRAGMA journal_mode = WAL;');
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

        /* ── Factures récurrentes ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS recurring_templates (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                label            TEXT    NOT NULL,
                client_name      TEXT    NOT NULL,
                client_address   TEXT,
                client_contact   TEXT,
                client_whatsapp  TEXT,
                subject          TEXT,
                type             TEXT    NOT NULL DEFAULT 'FACTURE PROFORMA',
                tax_rate         REAL    NOT NULL DEFAULT 5,
                tax_label        TEXT    NOT NULL DEFAULT 'Prelevement 5%',
                prestation_label TEXT    NOT NULL DEFAULT 'Frais de prestation',
                prestation_amount INTEGER NOT NULL DEFAULT 0,
                lines            TEXT    NOT NULL DEFAULT '[]',
                interval_type    TEXT    NOT NULL DEFAULT 'mensuel',
                next_due_at      TEXT    NOT NULL,
                last_generated_at TEXT,
                active           INTEGER NOT NULL DEFAULT 1,
                created_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                updated_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            );
        ");

        /* ── Paramètres entreprise ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS company_settings (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL DEFAULT ''
            );
        ");

        /* ── Journal d'activité ── */
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER,
                username   TEXT    NOT NULL,
                role       TEXT    NOT NULL DEFAULT '',
                action     TEXT    NOT NULL,
                label      TEXT    NOT NULL,
                ip         TEXT,
                created_at TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
            );
        ");
        $pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_logs_created_at ON activity_logs (created_at);
            CREATE INDEX IF NOT EXISTS idx_logs_user_id    ON activity_logs (user_id);
        ");

        /* Ajouts de colonnes sécurisés pour les bases existantes */
        foreach ([
            "ALTER TABLE invoices ADD COLUMN prestation_label  TEXT    NOT NULL DEFAULT 'Frais de prestation'",
            "ALTER TABLE invoices ADD COLUMN prestation_amount INTEGER NOT NULL DEFAULT 0",
            "ALTER TABLE invoices ADD COLUMN client_id         INTEGER",
            "ALTER TABLE opportunities ADD COLUMN client_id    INTEGER",
            "ALTER TABLE invoices ADD COLUMN origin_id         INTEGER",
            "ALTER TABLE invoices ADD COLUMN client_whatsapp   TEXT",
            "ALTER TABLE clients  ADD COLUMN whatsapp          TEXT",
            "ALTER TABLE users    ADD COLUMN first_name        TEXT NOT NULL DEFAULT ''",
            "ALTER TABLE users    ADD COLUMN last_name         TEXT NOT NULL DEFAULT ''",
            "ALTER TABLE users    ADD COLUMN phone             TEXT NOT NULL DEFAULT ''",
            "ALTER TABLE users    ADD COLUMN avatar            TEXT",
        ] as $sql) {
            try { $pdo->exec($sql); } catch (\Exception) { /* column already exists */ }
        }

        /* ── Index secondaires ──────────────────────────────────────────────────────
         * CREATE INDEX IF NOT EXISTS est idempotent : coût nul si l'index existe déjà.
         * Chaque index cible un pattern de requête fréquent identifié dans les repos.
         * ─────────────────────────────────────────────────────────────────────────── */
        $pdo->exec("
            -- invoices : filtre par status (allByStatus, stats, overdue, topClients)
            CREATE INDEX IF NOT EXISTS idx_invoices_status
                ON invoices (status);

            -- invoices : filtre par type (allDevis, allDevisByStatus, search)
            CREATE INDEX IF NOT EXISTS idx_invoices_type
                ON invoices (type);

            -- invoices : tri principal de toutes les listes
            CREATE INDEX IF NOT EXISTS idx_invoices_created_at
                ON invoices (created_at DESC);

            -- invoices : overdue() filtre composé status + due_at
            CREATE INDEX IF NOT EXISTS idx_invoices_status_due_at
                ON invoices (status, due_at);

            -- invoices : findAvoirs() et convertedInvoicesByDevisIds() clé étrangère logique
            CREATE INDEX IF NOT EXISTS idx_invoices_origin_id
                ON invoices (origin_id);

            -- invoices : statsByMonth() filtre + group sur issued_at
            CREATE INDEX IF NOT EXISTS idx_invoices_issued_at
                ON invoices (issued_at);

            -- invoices : nextNumber() / countByPrefix() LIKE 'PREFIX-%'
            CREATE INDEX IF NOT EXISTS idx_invoices_number
                ON invoices (number);

            -- invoice_lines : linesOf() lookup FK + tri sort_order
            CREATE INDEX IF NOT EXISTS idx_invoice_lines_invoice_id
                ON invoice_lines (invoice_id, sort_order);

            -- payments : allForInvoice() / totalForInvoice()
            CREATE INDEX IF NOT EXISTS idx_payments_invoice_id
                ON payments (invoice_id);

            -- expenses : allForInvoice() / globalStats()
            CREATE INDEX IF NOT EXISTS idx_expenses_invoice_id
                ON expenses (invoice_id);

            -- expenses : globalStats() filtre par status via JOIN invoices
            CREATE INDEX IF NOT EXISTS idx_expenses_date
                ON expenses (date);

            -- recurring_templates : dueNow() filtre composé active + next_due_at
            CREATE INDEX IF NOT EXISTS idx_recurring_active_due
                ON recurring_templates (active, next_due_at);

            -- opportunities : filtre par status (pipeline)
            CREATE INDEX IF NOT EXISTS idx_opportunities_status
                ON opportunities (status);

            -- projects : findByInvoice()
            CREATE INDEX IF NOT EXISTS idx_projects_invoice_id
                ON projects (invoice_id);

            -- projects : all() ORDER BY + stats() filtre par status
            CREATE INDEX IF NOT EXISTS idx_projects_status
                ON projects (status);
            CREATE INDEX IF NOT EXISTS idx_projects_created_at
                ON projects (created_at DESC);

            -- expenses : all() ORDER BY date DESC, created_at DESC
            CREATE INDEX IF NOT EXISTS idx_expenses_date_created
                ON expenses (date DESC, created_at DESC);

            -- expenses : globalStats() / statsByMonth() GROUP BY category
            CREATE INDEX IF NOT EXISTS idx_expenses_category
                ON expenses (category);

            -- opportunities : all() ORDER BY created_at DESC
            CREATE INDEX IF NOT EXISTS idx_opportunities_created_at
                ON opportunities (created_at DESC);

            -- opportunities : stats() JOIN invoices ON i.id = o.invoice_id
            CREATE INDEX IF NOT EXISTS idx_opportunities_invoice_id
                ON opportunities (invoice_id);

            -- clients : all() / allForSelect() ORDER BY name ASC
            CREATE INDEX IF NOT EXISTS idx_clients_name
                ON clients (name ASC);

            -- services : all() ORDER BY category ASC, name ASC
            CREATE INDEX IF NOT EXISTS idx_services_category_name
                ON services (category ASC, name ASC);
        ");
    }
}
