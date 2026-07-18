import Database from 'better-sqlite3';
import { join, resolve } from 'path';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));

const DB_PATH = process.env.DB_PATH
  ?? resolve(__dirname, '../../storage/invoices.sqlite');

let _db: Database.Database | null = null;

export function db(): Database.Database {
  if (!_db) {
    _db = new Database(DB_PATH, { readonly: false });
    _db.pragma('journal_mode = WAL');
    _db.pragma('foreign_keys = ON');
  }
  return _db;
}

// ── Type helpers ──────────────────────────────────────────────────────────────

export interface InvoiceSummary {
  id: number;
  number: string;
  type: string;
  status: string;
  client_name: string;
  subject: string;
  issued_at: string;
  due_at: string;
  total_ht: number;
  total_net: number;
  origin_id: number | null;
}

export interface InvoiceLine {
  description: string;
  quantity: number;
  unit_price: number;
}

export interface InvoiceFull extends InvoiceSummary {
  issuer_name: string;
  issuer_address: string;
  issuer_phone: string;
  issuer_email: string;
  issuer_ifu: string;
  issuer_logo_path: string;
  client_address: string;
  client_contact: string;
  tax_rate: number;
  tax_label: string;
  prestation_label: string;
  prestation_amount: number;
  signatory_title: string;
  signatory_name: string;
  footer_text: string;
  lines: InvoiceLine[];
}

export interface Client {
  id: number;
  name: string;
  address: string | null;
  contact: string | null;
  email: string | null;
  phone: string | null;
  notes: string | null;
}

export interface Expense {
  id: number;
  invoice_id: number | null;
  category: string;
  description: string;
  amount: number;
  date: string;
}

export interface Payment {
  id: number;
  invoice_id: number;
  amount: number;
  paid_at: string;
  note: string | null;
}

export interface Stats {
  total: number;
  brouillon: number;
  envoyee: number;
  payee: number;
  annulee: number;
  ca_engage: number;
  ca_encaisse: number;
}

// ── Queries ───────────────────────────────────────────────────────────────────

export function listInvoices(opts: {
  status?: string;
  type?: string;
  client?: string;
  limit?: number;
}): InvoiceSummary[] {
  const conditions: string[] = ["type NOT IN ('DEVIS','AVOIR')"];
  const params: unknown[] = [];

  if (opts.status) { conditions.push('status = ?'); params.push(opts.status); }
  if (opts.type)   { conditions.push('type = ?');   params.push(opts.type); }
  if (opts.client) { conditions.push('client_name LIKE ?'); params.push(`%${opts.client}%`); }

  const where = conditions.join(' AND ');
  const limit = opts.limit ?? 50;

  return db().prepare(
    `SELECT id, number, type, status, client_name, subject, issued_at, due_at, total_ht, total_net, origin_id
     FROM invoices WHERE ${where} ORDER BY created_at DESC LIMIT ?`
  ).all(...params, limit) as InvoiceSummary[];
}

export function getInvoice(id: number): InvoiceFull | null {
  const row = db().prepare('SELECT * FROM invoices WHERE id = ?').get(id) as Record<string, unknown> | undefined;
  if (!row) return null;

  const lines = db().prepare(
    'SELECT description, quantity, unit_price FROM invoice_lines WHERE invoice_id = ? ORDER BY sort_order'
  ).all(id) as InvoiceLine[];

  return { ...row, lines } as InvoiceFull;
}

export function listDevis(status?: string): InvoiceSummary[] {
  if (status) {
    return db().prepare(
      `SELECT id, number, type, status, client_name, subject, issued_at, due_at, total_ht, total_net, origin_id
       FROM invoices WHERE type = 'DEVIS' AND status = ? ORDER BY created_at DESC`
    ).all(status) as InvoiceSummary[];
  }
  return db().prepare(
    `SELECT id, number, type, status, client_name, subject, issued_at, due_at, total_ht, total_net, origin_id
     FROM invoices WHERE type = 'DEVIS' ORDER BY created_at DESC`
  ).all() as InvoiceSummary[];
}

export function listOverdue(): InvoiceSummary[] {
  return db().prepare(
    `SELECT id, number, type, status, client_name, subject, issued_at, due_at, total_ht, total_net, origin_id
     FROM invoices
     WHERE status = 'envoyée' AND due_at IS NOT NULL AND due_at != '' AND due_at < date('now')
     ORDER BY due_at ASC`
  ).all() as InvoiceSummary[];
}

export function getStats(): Stats {
  return db().prepare(`
    SELECT
      COUNT(*) AS total,
      SUM(status = 'brouillon' AND type NOT IN ('DEVIS','AVOIR')) AS brouillon,
      SUM(status = 'envoyée'   AND type NOT IN ('DEVIS','AVOIR')) AS envoyee,
      SUM(status = 'payée'     AND type NOT IN ('DEVIS','AVOIR')) AS payee,
      SUM(status = 'annulée'   AND type NOT IN ('DEVIS','AVOIR')) AS annulee,
      COALESCE(SUM(CASE WHEN status IN ('envoyée','payée') AND type NOT IN ('DEVIS','AVOIR') THEN total_net ELSE 0 END),0) AS ca_engage,
      COALESCE(SUM(CASE WHEN status = 'payée' AND type NOT IN ('DEVIS','AVOIR') THEN total_net ELSE 0 END),0) AS ca_encaisse
    FROM invoices
  `).get() as Stats;
}

export function listClients(): Client[] {
  return db().prepare('SELECT * FROM clients ORDER BY name').all() as Client[];
}

export function getClient(id: number): Client | null {
  return (db().prepare('SELECT * FROM clients WHERE id = ?').get(id) as Client | undefined) ?? null;
}

export function listExpenses(opts: { category?: string; limit?: number }): Expense[] {
  const conditions: string[] = [];
  const params: unknown[] = [];
  if (opts.category) { conditions.push('category = ?'); params.push(opts.category); }
  const where = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';
  const limit = opts.limit ?? 50;
  return db().prepare(
    `SELECT * FROM expenses ${where} ORDER BY date DESC LIMIT ?`
  ).all(...params, limit) as Expense[];
}

export function getPaymentsForInvoice(invoiceId: number): Payment[] {
  return db().prepare(
    'SELECT * FROM payments WHERE invoice_id = ? ORDER BY paid_at DESC'
  ).all(invoiceId) as Payment[];
}

export function nextInvoiceNumber(): string {
  const today = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const custom = (db().prepare(
    "SELECT value FROM company_settings WHERE key = 'invoice_prefix'"
  ).get() as { value: string } | undefined)?.value ?? '';

  const prefix = custom && custom !== '' ? `${custom}-${today}` : today;
  const row = db().prepare(
    "SELECT COUNT(*) AS c FROM invoices WHERE number LIKE ?"
  ).get(`${prefix}-%`) as { c: number };
  return `${prefix}-${row.c + 1}`;
}

export function nextQuoteNumber(): string {
  const today = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const prefix = `DEV-${today}`;
  const row = db().prepare(
    "SELECT COUNT(*) AS c FROM invoices WHERE number LIKE ? AND type = 'DEVIS'"
  ).get(`${prefix}-%`) as { c: number };
  return `${prefix}-${row.c + 1}`;
}
