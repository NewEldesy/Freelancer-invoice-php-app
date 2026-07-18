import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import {
  db, listInvoices, getInvoice, listDevis, listOverdue, getStats,
  listClients, getClient, listExpenses, getPaymentsForInvoice,
  nextInvoiceNumber, nextQuoteNumber,
} from './db.js';

const server = new McpServer({
  name: 'freelancer-invoice',
  version: '1.0.0',
});

// ── RESOURCES ─────────────────────────────────────────────────────────────────

server.resource(
  'stats',
  'freelancer-invoice://stats',
  { description: 'Tableau de bord financier : CA engagé, encaissé, bénéfice net' },
  async () => {
    const s = getStats();
    const expenses = db().prepare(
      "SELECT COALESCE(SUM(amount),0) AS total FROM expenses"
    ).get() as { total: number };

    const summary = {
      factures: { total: s.total, brouillon: s.brouillon, envoyee: s.envoyee, payee: s.payee, annulee: s.annulee },
      ca_engage_fcfa: s.ca_engage,
      ca_encaisse_fcfa: s.ca_encaisse,
      total_depenses_fcfa: expenses.total,
      benefice_net_fcfa: s.ca_engage - expenses.total,
    };
    return { contents: [{ uri: 'freelancer-invoice://stats', text: JSON.stringify(summary, null, 2), mimeType: 'application/json' }] };
  }
);

server.resource(
  'invoices',
  'freelancer-invoice://invoices',
  { description: 'Liste des 50 dernières factures (hors devis et avoirs)' },
  async () => {
    const rows = listInvoices({ limit: 50 });
    return { contents: [{ uri: 'freelancer-invoice://invoices', text: JSON.stringify(rows, null, 2), mimeType: 'application/json' }] };
  }
);

server.resource(
  'clients',
  'freelancer-invoice://clients',
  { description: 'Base clients complète' },
  async () => {
    const rows = listClients();
    return { contents: [{ uri: 'freelancer-invoice://clients', text: JSON.stringify(rows, null, 2), mimeType: 'application/json' }] };
  }
);

server.resource(
  'overdue',
  'freelancer-invoice://overdue',
  { description: "Factures envoyées dont l'échéance est dépassée" },
  async () => {
    const rows = listOverdue();
    return { contents: [{ uri: 'freelancer-invoice://overdue', text: JSON.stringify(rows, null, 2), mimeType: 'application/json' }] };
  }
);

// ── TOOLS ─────────────────────────────────────────────────────────────────────

// List invoices
server.tool(
  'list_invoices',
  'Lister les factures avec filtres optionnels',
  {
    status: z.enum(['brouillon', 'envoyée', 'payée', 'annulée']).optional().describe('Filtrer par statut'),
    type:   z.enum(['FACTURE PROFORMA', 'FACTURE']).optional().describe('Filtrer par type'),
    client: z.string().optional().describe('Recherche partielle sur le nom client'),
    limit:  z.number().int().min(1).max(200).default(20).describe('Nombre maximum de résultats'),
  },
  async ({ status, type, client, limit }) => {
    const rows = listInvoices({ status, type, client, limit });
    return { content: [{ type: 'text', text: JSON.stringify(rows, null, 2) }] };
  }
);

// Get invoice details
server.tool(
  'get_invoice',
  'Obtenir les détails complets d\'une facture (lignes, paiements, info émetteur)',
  { id: z.number().int().positive().describe('ID de la facture') },
  async ({ id }) => {
    const inv = getInvoice(id);
    if (!inv) return { content: [{ type: 'text', text: `Facture #${id} introuvable.` }], isError: true };

    const payments = getPaymentsForInvoice(id);
    const totalPaid = payments.reduce((s, p) => s + p.amount, 0);
    const solde = inv.total_net - totalPaid;

    return { content: [{ type: 'text', text: JSON.stringify({ ...inv, payments, total_paid: totalPaid, solde }, null, 2) }] };
  }
);

// Get overdue invoices
server.tool(
  'list_overdue_invoices',
  'Lister les factures en retard de paiement (envoyées + échéance dépassée)',
  {},
  async () => {
    const rows = listOverdue();
    if (rows.length === 0) return { content: [{ type: 'text', text: 'Aucune facture en retard.' }] };
    const total = rows.reduce((s, r) => s + r.total_net, 0);
    return { content: [{ type: 'text', text: JSON.stringify({ count: rows.length, total_fcfa: total, invoices: rows }, null, 2) }] };
  }
);

// Update invoice status
server.tool(
  'update_invoice_status',
  'Changer le statut d\'une facture (brouillon → envoyée → payée / annulée)',
  {
    id:     z.number().int().positive().describe('ID de la facture'),
    status: z.enum(['brouillon', 'envoyée', 'payée', 'annulée']).describe('Nouveau statut'),
  },
  async ({ id, status }) => {
    const inv = db().prepare("SELECT id, type, status FROM invoices WHERE id = ?").get(id) as { id: number; type: string; status: string } | undefined;
    if (!inv) return { content: [{ type: 'text', text: `Facture #${id} introuvable.` }], isError: true };
    if (['DEVIS', 'AVOIR'].includes(inv.type)) return { content: [{ type: 'text', text: 'Utilisez update_devis_status pour les devis.' }], isError: true };

    db().prepare("UPDATE invoices SET status = ?, updated_at = datetime('now','localtime') WHERE id = ?").run(status, id);
    return { content: [{ type: 'text', text: `Facture #${id} : statut mis à jour → ${status}` }] };
  }
);

// Add payment
server.tool(
  'add_payment',
  'Enregistrer un paiement (partiel ou total) sur une facture',
  {
    invoice_id: z.number().int().positive().describe('ID de la facture'),
    amount:     z.number().int().positive().describe('Montant en FCFA'),
    paid_at:    z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional().describe('Date du paiement (YYYY-MM-DD, défaut: aujourd\'hui)'),
    note:       z.string().optional().describe('Note (virement, chèque…)'),
  },
  async ({ invoice_id, amount, paid_at, note }) => {
    const inv = db().prepare("SELECT id, total_net, status FROM invoices WHERE id = ? AND type NOT IN ('DEVIS','AVOIR')").get(invoice_id) as { id: number; total_net: number; status: string } | undefined;
    if (!inv) return { content: [{ type: 'text', text: `Facture #${invoice_id} introuvable.` }], isError: true };
    if (inv.status === 'payée') return { content: [{ type: 'text', text: 'Facture déjà entièrement payée.' }], isError: true };

    const date = paid_at ?? new Date().toISOString().slice(0, 10);
    db().prepare("INSERT INTO payments (invoice_id, amount, paid_at, note) VALUES (?, ?, ?, ?)").run(invoice_id, amount, date, note ?? null);

    const { total } = db().prepare("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE invoice_id = ?").get(invoice_id) as { total: number };
    if (total >= inv.total_net) {
      db().prepare("UPDATE invoices SET status = 'payée', updated_at = datetime('now','localtime') WHERE id = ?").run(invoice_id);
      return { content: [{ type: 'text', text: `Paiement de ${amount} FCFA enregistré. Facture #${invoice_id} marquée payée (${total} / ${inv.total_net} FCFA).` }] };
    }

    return { content: [{ type: 'text', text: `Paiement de ${amount} FCFA enregistré. Total encaissé : ${total} / ${inv.total_net} FCFA (solde : ${inv.total_net - total} FCFA).` }] };
  }
);

// Create invoice
server.tool(
  'create_invoice',
  'Créer une nouvelle facture ou facture proforma',
  {
    type:            z.enum(['FACTURE PROFORMA', 'FACTURE']).default('FACTURE PROFORMA'),
    client_name:     z.string().min(1).describe('Nom / entreprise du client'),
    client_address:  z.string().optional(),
    client_contact:  z.string().optional().describe('Contact (email ou téléphone)'),
    subject:         z.string().optional().describe('Objet de la facture'),
    issued_at:       z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional().describe('Date d\'émission (défaut: aujourd\'hui)'),
    due_at:          z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional().describe('Date d\'échéance (défaut: +1 mois)'),
    tax_rate:        z.number().min(0).max(100).default(5),
    tax_label:       z.string().default('Prelevement 5%'),
    lines:           z.array(z.object({
      description: z.string().min(1),
      quantity:    z.number().int().positive().default(1),
      unit_price:  z.number().int().min(0),
    })).min(1).describe('Lignes d\'articles'),
  },
  async ({ type, client_name, client_address, client_contact, subject, issued_at, due_at, tax_rate, tax_label, lines }) => {
    const settings = db().prepare("SELECT key, value FROM company_settings").all() as { key: string; value: string }[];
    const s = Object.fromEntries(settings.map(r => [r.key, r.value]));

    const today = new Date().toISOString().slice(0, 10);
    const nextMonth = new Date(Date.now() + 30 * 86400_000).toISOString().slice(0, 10);

    const totalHt = lines.reduce((sum, l) => sum + l.quantity * l.unit_price, 0);
    const tax = Math.round(totalHt * tax_rate / 100);
    const totalNet = totalHt + tax;

    const number = nextInvoiceNumber();

    const info = db().prepare(`
      INSERT INTO invoices
        (number, type, status, subject, issued_at, due_at,
         issuer_name, issuer_address, issuer_phone, issuer_email, issuer_ifu, issuer_logo_path,
         client_name, client_address, client_contact,
         tax_rate, tax_label, total_ht, total_net,
         prestation_label, prestation_amount, origin_id)
      VALUES
        (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    `).run(
      number, type, 'brouillon', subject ?? '', issued_at ?? today, due_at ?? nextMonth,
      s['issuer_name'] ?? '', s['issuer_address'] ?? '', s['issuer_phone'] ?? '',
      s['issuer_email'] ?? '', s['issuer_ifu'] ?? '', s['issuer_logo_path'] ?? '',
      client_name, client_address ?? '', client_contact ?? '',
      tax_rate, tax_label, totalHt, totalNet,
      s['prestation_label'] ?? 'Frais de prestation', 0, null
    );

    const invoiceId = info.lastInsertRowid as number;

    const lineStmt = db().prepare(
      'INSERT INTO invoice_lines (invoice_id, sort_order, description, quantity, unit_price) VALUES (?,?,?,?,?)'
    );
    lines.forEach((l, i) => lineStmt.run(invoiceId, i, l.description, l.quantity, l.unit_price));

    return { content: [{ type: 'text', text: `${type} créée : N° ${number} (ID: ${invoiceId}) — Total net : ${totalNet.toLocaleString()} FCFA (HT: ${totalHt.toLocaleString()}, taxe: ${tax.toLocaleString()})` }] };
  }
);

// List devis (quotes)
server.tool(
  'list_devis',
  'Lister les devis avec filtre optionnel par statut',
  {
    status: z.enum(['brouillon', 'envoyé', 'accepté', 'refusé']).optional(),
  },
  async ({ status }) => {
    const rows = listDevis(status);
    return { content: [{ type: 'text', text: JSON.stringify(rows, null, 2) }] };
  }
);

// Update devis status
server.tool(
  'update_devis_status',
  'Changer le statut d\'un devis (brouillon → envoyé → accepté / refusé)',
  {
    id:     z.number().int().positive(),
    status: z.enum(['brouillon', 'envoyé', 'accepté', 'refusé']),
  },
  async ({ id, status }) => {
    const row = db().prepare("SELECT id, type FROM invoices WHERE id = ?").get(id) as { id: number; type: string } | undefined;
    if (!row || row.type !== 'DEVIS') return { content: [{ type: 'text', text: `Devis #${id} introuvable.` }], isError: true };
    db().prepare("UPDATE invoices SET status = ?, updated_at = datetime('now','localtime') WHERE id = ?").run(status, id);
    return { content: [{ type: 'text', text: `Devis #${id} → ${status}` }] };
  }
);

// Convert devis to invoice
server.tool(
  'convert_devis',
  'Convertir un devis accepté en facture proforma',
  { id: z.number().int().positive().describe('ID du devis') },
  async ({ id }) => {
    const devis = getInvoice(id);
    if (!devis || devis.type !== 'DEVIS') return { content: [{ type: 'text', text: `Devis #${id} introuvable.` }], isError: true };

    const existing = db().prepare("SELECT id, number FROM invoices WHERE origin_id = ? AND type NOT IN ('DEVIS','AVOIR')").get(id) as { id: number; number: string } | undefined;
    if (existing) return { content: [{ type: 'text', text: `Devis déjà converti → Facture ${existing.number} (ID: ${existing.id})` }] };

    const today = new Date().toISOString().slice(0, 10);
    const nextMonth = new Date(Date.now() + 30 * 86400_000).toISOString().slice(0, 10);
    const number = nextInvoiceNumber();

    const info = db().prepare(`
      INSERT INTO invoices
        (number, type, status, subject, issued_at, due_at,
         issuer_name, issuer_address, issuer_phone, issuer_email, issuer_ifu, issuer_logo_path,
         client_name, client_address, client_contact,
         tax_rate, tax_label, total_ht, total_net,
         prestation_label, prestation_amount, origin_id)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    `).run(
      number, 'FACTURE PROFORMA', 'brouillon', devis.subject, today, nextMonth,
      devis.issuer_name, devis.issuer_address, devis.issuer_phone,
      devis.issuer_email, devis.issuer_ifu, devis.issuer_logo_path,
      devis.client_name, devis.client_address, devis.client_contact,
      devis.tax_rate, devis.tax_label, devis.total_ht, devis.total_net,
      devis.prestation_label, devis.prestation_amount, id
    );

    const newId = info.lastInsertRowid as number;
    const lineStmt = db().prepare('INSERT INTO invoice_lines (invoice_id, sort_order, description, quantity, unit_price) VALUES (?,?,?,?,?)');
    devis.lines.forEach((l, i) => lineStmt.run(newId, i, l.description, l.quantity, l.unit_price));

    if (devis.status !== 'accepté') {
      db().prepare("UPDATE invoices SET status = 'accepté' WHERE id = ?").run(id);
    }

    return { content: [{ type: 'text', text: `Devis #${id} converti → Facture ${number} (ID: ${newId})` }] };
  }
);

// List clients
server.tool(
  'list_clients',
  'Lister tous les clients',
  { search: z.string().optional().describe('Recherche sur le nom') },
  async ({ search }) => {
    let rows = listClients();
    if (search) rows = rows.filter(c => c.name.toLowerCase().includes(search.toLowerCase()));
    return { content: [{ type: 'text', text: JSON.stringify(rows, null, 2) }] };
  }
);

// Create client
server.tool(
  'create_client',
  'Créer un nouveau client dans la base',
  {
    name:    z.string().min(1),
    address: z.string().optional(),
    contact: z.string().optional(),
    email:   z.string().email().optional(),
    phone:   z.string().optional(),
    notes:   z.string().optional(),
  },
  async ({ name, address, contact, email, phone, notes }) => {
    const info = db().prepare(
      'INSERT INTO clients (name, address, contact, email, phone, notes) VALUES (?,?,?,?,?,?)'
    ).run(name, address ?? null, contact ?? null, email ?? null, phone ?? null, notes ?? null);
    return { content: [{ type: 'text', text: `Client créé : ${name} (ID: ${info.lastInsertRowid})` }] };
  }
);

// Get accounting stats
server.tool(
  'get_stats',
  'Obtenir les statistiques financières (CA engagé, encaissé, dépenses, bénéfice net)',
  {},
  async () => {
    const s = getStats();
    const { depenses } = db().prepare("SELECT COALESCE(SUM(amount),0) AS depenses FROM expenses").get() as { depenses: number };
    const result = {
      ca_engage_fcfa:     s.ca_engage,
      ca_encaisse_fcfa:   s.ca_encaisse,
      total_depenses_fcfa: depenses,
      benefice_net_fcfa:  s.ca_engage - depenses,
      factures: {
        total:     s.total,
        brouillon: s.brouillon,
        envoyee:   s.envoyee,
        payee:     s.payee,
        annulee:   s.annulee,
      },
    };
    return { content: [{ type: 'text', text: JSON.stringify(result, null, 2) }] };
  }
);

// Add expense
server.tool(
  'add_expense',
  'Enregistrer une dépense',
  {
    description: z.string().min(1),
    amount:      z.number().int().positive().describe('Montant en FCFA'),
    category:    z.enum(['logiciel', 'materiel', 'deplacement', 'formation', 'communication', 'sous-traitance', 'autre']).default('autre'),
    date:        z.string().regex(/^\d{4}-\d{2}-\d{2}$/).optional(),
    invoice_id:  z.number().int().positive().optional().describe('Facture associée'),
  },
  async ({ description, amount, category, date, invoice_id }) => {
    const d = date ?? new Date().toISOString().slice(0, 10);
    const info = db().prepare(
      'INSERT INTO expenses (invoice_id, category, description, amount, date) VALUES (?,?,?,?,?)'
    ).run(invoice_id ?? null, category, description, amount, d);
    return { content: [{ type: 'text', text: `Dépense enregistrée : ${description} — ${amount.toLocaleString()} FCFA (ID: ${info.lastInsertRowid})` }] };
  }
);

// List expenses
server.tool(
  'list_expenses',
  'Lister les dépenses avec filtre optionnel par catégorie',
  {
    category: z.enum(['logiciel', 'materiel', 'deplacement', 'formation', 'communication', 'sous-traitance', 'autre']).optional(),
    limit:    z.number().int().min(1).max(200).default(30),
  },
  async ({ category, limit }) => {
    const rows = listExpenses({ category, limit });
    return { content: [{ type: 'text', text: JSON.stringify(rows, null, 2) }] };
  }
);

// ── PROMPTS ───────────────────────────────────────────────────────────────────

server.prompt(
  'draft_invoice_for_client',
  'Aide à rédiger une facture pour un client existant',
  { client_name: z.string().describe('Nom du client') },
  async ({ client_name }) => {
    const clients = listClients().filter(c => c.name.toLowerCase().includes(client_name.toLowerCase()));
    const invoices = listInvoices({ client: client_name, limit: 5 });

    return {
      messages: [{
        role: 'user',
        content: {
          type: 'text',
          text: `Aide-moi à créer une facture pour le client "${client_name}".

Informations client existantes :
${JSON.stringify(clients[0] ?? 'Client non trouvé', null, 2)}

Dernières factures pour ce client :
${JSON.stringify(invoices, null, 2)}

Demande-moi les articles à facturer et génère la facture avec l'outil create_invoice.`,
        },
      }],
    };
  }
);

server.prompt(
  'overdue_followup',
  'Rédiger un message de relance pour une facture en retard',
  { invoice_id: z.string().describe('ID de la facture') },
  async ({ invoice_id }) => {
    const inv = getInvoice(parseInt(invoice_id, 10));
    if (!inv) throw new Error(`Facture #${invoice_id} introuvable`);

    const payments = getPaymentsForInvoice(inv.id);
    const paid = payments.reduce((s, p) => s + p.amount, 0);
    const solde = inv.total_net - paid;

    return {
      messages: [{
        role: 'user',
        content: {
          type: 'text',
          text: `Rédige un email de relance professionnel pour la facture en retard suivante :

- Numéro : ${inv.number}
- Client : ${inv.client_name}
- Objet : ${inv.subject || '(non précisé)'}
- Date d'émission : ${inv.issued_at}
- Échéance : ${inv.due_at}
- Montant total : ${inv.total_net.toLocaleString()} FCFA
- Déjà payé : ${paid.toLocaleString()} FCFA
- Solde dû : ${solde.toLocaleString()} FCFA

Ton professionnel, courtois mais ferme.`,
        },
      }],
    };
  }
);

// ── START ─────────────────────────────────────────────────────────────────────

const transport = new StdioServerTransport();
await server.connect(transport);
