# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Running the app

PHP and Composer are installed at non-standard paths on this machine:

```bat
# Start dev server (http://localhost:8080)
C:\xampp\php\php.exe -S localhost:8080 -t public

# Install/update dependencies
C:\xampp\php\php.exe C:\composer\composer.phar install

# Syntax check a file
C:\xampp\php\php.exe -l path/to/file.php

# Generate a license key (CLI only, ISSU DEV use)
C:\xampp\php\php.exe tools/keygen.php pro 1y
C:\xampp\php\php.exe tools/keygen.php enterprise permanent <machine_id>
```

The SQLite database is created automatically at `storage/invoices.sqlite` on first request — no setup needed.

```bat
# Run a one-shot maintenance script
C:\xampp\php\php.exe tools/fix_icons.php
```

## First-run flow

1. `GET /setup.php` — creates **two accounts** (superadmin + admin) and pre-generates 15 license keys; redirects to login if users already exist
2. `GET /login.php` — session login; rate-limited to 10 attempts / 15 min per session
3. All other pages are protected; unauthenticated requests redirect to `/login.php`
4. On first page load with no license in DB, `LicenseService::requireValid()` silently activates the free plan — no redirect to `/activate.php`

## Architecture

Vanilla PHP 8.1+ with no framework. Entry points are individual PHP files under `public/`; there is no front controller or router.

**Request flow:**
1. Browser hits `public/invoice/create.php` (or any page)
2. Page file requires `vendor/autoload.php`, calls the appropriate `Auth::require*()` guard, then instantiates Repository classes directly
3. On POST: raw `$_POST` assembled into a flat array → optional validator → Repository method → redirect
4. On GET: repository fetches data → variables set → `templates/layout.php` included → page HTML → `templates/layout_end.php`

**Key design decisions:**
- All DB fields are **flat keys** (`issuer_name`, `issuer_email`…), not nested arrays. `RequestValidator` and `extractFields()` expect this flat shape — do not introduce nesting.
- `total_ht` and `total_net` are computed in JavaScript on the invoice form and sent as hidden fields. The PHP backend trusts these values (they are recalculated from lines on PDF generation via `InvoiceDTO`).
- The **prestation line** is stored separately on the `invoices` table (`prestation_label`, `prestation_amount`), not in `invoice_lines`. It is injected as the last entry only at PDF generation time.
- `Database::migrate()` runs on every request (cheap — uses `CREATE TABLE IF NOT EXISTS`). Adding new columns requires a safe `ALTER TABLE … ADD COLUMN` block in the `foreach` at the bottom of `migrate()`.

## Authentication & roles

`src/Auth/Auth.php` — static session helper. Call one guard at the top of every page:

| Guard | Who can access |
|-------|---------------|
| `Auth::requireSuperAdmin()` | `superadmin` only |
| `Auth::requireAdmin()` | `admin` only |
| `Auth::requireBusiness()` | `gestionnaire` + `utilisateur` (blocks `admin` and `superadmin`) |
| `Auth::requireManager()` | `gestionnaire` only (write operations) |

**4 roles:**

| Role | Rights |
|------|--------|
| `superadmin` | License key management only (`/superadmin/keys.php`). Cannot access business pages. ISSU DEV vendor account. |
| `admin` | User management only (`/admin/users.php`). Cannot access business pages. |
| `gestionnaire` | Full CRUD on invoices, devis, avoirs, pipeline, projects, expenses, settings. |
| `utilisateur` | Read-only: can view all business pages but sees no create/edit/delete buttons. |

**Checking permissions in templates:**
- `Auth::can('write')` → `gestionnaire` only
- `Auth::can('read')` → `gestionnaire` + `utilisateur`
- `Auth::can('admin')` → `admin` only
- `Auth::can('superadmin')` → `superadmin` only
- `Auth::isSuperAdmin()`, `Auth::isAdmin()`, `Auth::isManager()`, `Auth::isViewer()` — boolean helpers
- `Auth::role()` → raw role string
- `Auth::user()` → `['id', 'username', 'email', 'role']` from session

**Session security:** `Auth::logout()` clears `$_SESSION`, deletes the session cookie (`setcookie(..., time()-3600)`), then calls `session_destroy()`. Never call `session_start()` directly — always go through `Auth::start()`.

`src/Database/UserRepository.php` — CRUD for users. Passwords stored with `password_hash(PASSWORD_BCRYPT)`. `verify(username, password)` returns the user row or null. `create()` accepts roles: `superadmin`, `admin`, `gestionnaire`, `utilisateur`. Role is whitelisted server-side in `admin/users/create.php` — never trust `$_POST['role']` raw.

## Data layer

`Database` — PDO singleton, SQLite, auto-migrates schema on first connection.

Tables: `invoices`, `invoice_lines`, `opportunities`, `projects`, `expenses`, `users`, `license`, `license_keys`, `feature_usage`, `company_settings`, `clients`, `services`, `payments`.

`InvoiceRepository`:
- `all()` — all rows from `invoices` (all types) ordered by `created_at DESC`
- `allByStatus(string $status)` — filtered in SQL; includes all types
- `allDevis()` / `allDevisByStatus(string $status)` — WHERE type = 'DEVIS'
- `findAvoirs(int $invoiceId)` — avoirs linked via `origin_id` WHERE type = 'AVOIR'
- `findConvertedInvoice(int $devisId)` — facture created from a devis (origin_id = devisId, type NOT IN DEVIS/AVOIR)
- `nextNumber()` — YYYYMMDD-N (or PREFIX-YYYYMMDD-N if `invoice_prefix` is set in settings)
- `nextQuoteNumber()` — DEV-YYYYMMDD-N (devis-specific, counts only DEVIS rows)
- `stats()` — `ca_engage` and `ca_encaisse` **exclude** rows where `type IN ('DEVIS','AVOIR')`

`OpportunityRepository` — `stats()` JOINs invoices; uses `COALESCE(i.total_net, o.estimated_amount)` for won value.

`ProjectRepository` — `findByInvoice(int $invoiceId)` prevents duplicate auto-creation when invoice status → `envoyée`.

`ExpenseRepository` — `globalStats()` returns `benefice_net = ca_engage - total_depenses`. `CATEGORIES` const used in PHP and JS templates. `allForInvoice()` LEFT JOINs invoices for `invoice_number` and `client_name`.

`SettingsRepository` — key/value store in `company_settings`. Pre-fills issuer info on new invoices. Key: `invoice_prefix` for custom invoice numbering.

`ClientRepository` — CRUD on `clients` table. `allForSelect()` returns slim rows for dropdowns.

`ServiceRepository` — CRUD on `services` table. `CATEGORIES` const for 7 categories. `allGrouped()` returns rows keyed by category.

`PaymentRepository` — partial payments on invoices. `allForInvoice(int $id)`, `totalForInvoice(int $id): int`, `add()`, `delete()`, `find()`. After adding a payment, `invoice/payment/add.php` re-queries the total and auto-marks the invoice `payée` if `totalPaid >= total_net`. `payments` table has `ON DELETE CASCADE` on `invoice_id`.

## Document types (`invoices.type`)

All document types live in the `invoices` table. The `type` column distinguishes them:

| type | Statuses | Notes |
|------|----------|-------|
| `FACTURE PROFORMA` | brouillon → envoyée → payée / annulée | Default. Counts in CA. |
| `FACTURE` | brouillon → envoyée → payée / annulée | Counts in CA. |
| `DEVIS` | brouillon → envoyé → accepté / refusé | Does **not** count in CA. Numbering: `DEV-YYYYMMDD-N`. |
| `AVOIR` | brouillon → émis | Does **not** count in CA. Linked via `origin_id → invoices.id`. |

**`origin_id` column** links child documents to parents:
- When a DEVIS is converted → the new FACTURE PROFORMA gets `origin_id = devis.id`
- When an AVOIR is created → it gets `origin_id = invoice.id`

**invoice_form.php template variables:**
- `$invoice` (array) — record data, merged with POST on validation error
- `$lines` (array) — line items
- `$formAction` (string) — form action URL
- `$lockedType` (string|null) — if set, renders a readonly hidden input instead of the type select
- `$devisStatuses` (bool|null) — if true, status select uses `brouillon/envoyé/accepté/refusé`
- `$avoirStatuses` (bool|null) — if true, status select uses `brouillon/émis`

## Business pipeline

```
Opportunity (pipeline)
    ↓ pipeline/convert.php  →  pre-fills invoice; marks opp as won; idempotent
Invoice [brouillon]
    ↓ invoice/status.php → envoyée
Project [en_cours]  (auto-created on first envoyée, title = subject or client_name)
    + Expenses → expense/create.php
    → bénéfice net = CA engagé − total dépenses

Devis
    ↓ devis/convert.php  →  creates FACTURE PROFORMA with origin_id; marks devis accepté; idempotent
Invoice → avoir/create.php → AVOIR linked via origin_id; shown in invoice/edit.php "Avoirs liés" section
```

## Invoice / Devis status flow

```
# Facture
brouillon → envoyée → payée
                    ↘ annulée

# Devis
brouillon → envoyé → accepté → [convert to invoice]
                   ↘ refusé

# Avoir
brouillon → émis
```

Status changed via POST. All status endpoints call `Auth::verifyCsrf()`.

- **brouillon / envoyé / refusé / brouillon(avoir)**: excluded from CA
- **envoyée**: counts in CA engagé; triggers auto-project creation
- **payée**: counts in CA engagé + CA encaissé
- **annulée**: excluded from CA
- **accepté**: excluded from CA (it becomes a facture on conversion)
- **émis**: excluded from CA (avoirs are informational only)

## Invoice number format

- Factures: `YYYYMMDD-N` (N per day, or `PREFIX-YYYYMMDD-N` if `invoice_prefix` set in settings)
- Devis: `DEV-YYYYMMDD-N` (always, regardless of `invoice_prefix`; counts only DEVIS rows)
- Avoirs: `AV-YYYYMMDD-{originId}` (set at creation, editable in the form)

## PDF generation

`PdfGeneratorService` uses Dompdf. HTML template must use real `<table>` elements — Dompdf does not support `display:flex` or `display:table`. Footer uses `position:fixed;bottom:0`. PDF counter is incremented **after** successful generation, not before.

PDF endpoints by type:
- `public/invoice/pdf.php` — factures
- `public/devis/pdf.php` — devis (guards `type = 'DEVIS'`)
- `public/avoir/pdf.php` — avoirs (guards `type = 'AVOIR'`)

## Sauvegarde / Restauration

- **Export:** `GET /settings/backup.php` — streams `storage/invoices.sqlite` as `Content-Disposition: attachment`. No license gate.
- **Restore:** `POST /settings/restore.php` — validates SQLite magic bytes (`SQLite format 3\000`), copies a safety backup to `storage/invoices-pre-restore-*.sqlite`, then overwrites the live DB. CSRF required.
- UI: `settings.php?tab=backup`

## Comptabilité

`public/accounting/index.php` — monthly/annual reporting for `gestionnaire` and `utilisateur`.

- `InvoiceRepository::statsByMonth(int $year)` and `ExpenseRepository::statsByMonth(int $year)` always return 12 rows (zero-filled) for easy PHP merge.
- `public/accounting/report.php` — annual P&L with N vs N-1 comparison.
- `public/accounting/export.php` — CSV export with UTF-8 BOM (`;` separator for French Excel). `?type=monthly` or `?type=annual`. Excel counter gated by license.

## Sidebar nav (role-conditional)

- **superadmin**: ISSU DEV section only → Clés de licence
- **admin**: Dashboard + Administration (Utilisateurs)
- **gestionnaire**: Dashboard, Factures, Devis, Acquisition (Clients + Pipeline + Nouvelle facture + Nouveau devis), Exécution (Projets), Bénéfice (Dépenses + Comptabilité), Configuration (Catalogue prestations + Paramètres)
- **utilisateur**: Same as gestionnaire minus "Nouvelle facture", "Nouveau devis", "Catalogue prestations", and "Paramètres"

`$currentPage` values: `dashboard`, `list`, `devis`, `devis_create`, `create`, `pipeline`, `projects`, `expenses`, `settings`, `admin_users`, `superadmin_keys`, `clients`, `services`.

## Security

### CSRF protection
`Auth::csrfToken()` — per-session token in `$_SESSION['csrf_token']`. Exposed via `<meta name="csrf-token">` in layout.

`templates/layout_end.php` global JS:
- Auto-injects `<input type="hidden" name="_csrf">` into every `form[method=POST]`
- Patches `window.fetch()` to send `X-CSRF-Token` header on POST

**`Auth::verifyCsrf()` is mandatory on every POST-only action page** (delete, status change, duplicate, convert, activate, restore). It accepts the token from `$_POST['_csrf']` or `$_SERVER['HTTP_X_CSRF_TOKEN']`.

Pages without a layout (action-only endpoints) must call `Auth::verifyCsrf()` explicitly after the `REQUEST_METHOD !== 'POST'` guard.

### File uploads
Logo uploads validate both extension AND real MIME type via `finfo` (see `invoice/edit.php`). Saved filename is always `.png`. `public/uploads/.htaccess` blocks PHP execution. Apply the same MIME check to any new upload endpoint — `invoice/create.php` and `settings.php` currently validate extension only (known gap).

Restore uploads validate SQLite magic bytes before overwriting the DB.

### Open redirects
Any `$back` or redirect from `$_POST` must be validated:
```php
if (!str_starts_with($back, '/') || str_contains($back, '//')) {
    $back = '/fallback.php';
}
```

### Login rate limiting
`login.php` enforces 10 failed attempts max per 15-minute window, tracked in `$_SESSION`. Counters reset on successful login.

### Error display
`public/.user.ini` sets `display_errors = Off`. Do not change.

## License system (`src/Services/LicenseService.php`)

**Key format:** `BASE64_PAYLOAD.HMAC32`
- payload = `base64_encode(json({edition, expires:null, machine_id, issued_at}))` — payload is NOT uppercased (base64 is case-sensitive)
- HMAC32 = first 32 chars of `hash_hmac('sha256', payloadB64, secret)` — uppercased
- `expires` in payload is always `null` at generation; expiry is calculated from `period` at activation time

**Secret key:** stored in `config/license.secret` (excluded from git via `.gitignore`). Template at `config/license.secret.example`. `LicenseService::secret()` throws `RuntimeException` if file is missing or contains the placeholder value.

**Editions:** `free` | `pro` | `enterprise`

**Free plan limits** (enforced server-side):

| Feature | Limit |
|---------|-------|
| Invoices | 10 |
| Duplications | 5 |
| PDF exports | 15 |
| Excel exports | 15 |
| Pipeline | 5 |
| Expenses | 5 |
| Accounting / Multi-users | blocked |

PDF/Excel/duplicate limits use persistent counters in `feature_usage` table (`getCounter()` / `incrementCounter()`). Invoice/pipeline/expense limits compare against live DB count. Devis and avoirs are not currently gated.

**Key lifecycle:**
1. `generateAndStore(edition, period)` — generates key with `expires=null`, stores in `license_keys` with period (`3m`/`6m`/`1y`/`2y`/`permanent`), `expires_at=NULL`
2. Client activates via `/activate.php` → `activate($key)` → looks up period from `license_keys`, calls `periodToDate()`, stores computed `expires_at` in `license` table and in `license_keys` via `markKeyUsed()`
3. A key already used on a different machine is rejected: `used=1 AND used_machine != machineId()`

**Periods:** `3m` → +3 months, `6m` → +6 months, `1y` → +1 year, `2y` → +2 years, `permanent` → `null` (never expires)

**`requireValid()`:** called in `layout.php` on every page. If `edition=none` → silently calls `activateFree()`. If expired → redirects to `/activate.php?expired=1`.

**setup.php** pre-generates at first init: 5 Pro keys (one per period) + 10 Enterprise keys (2 per period).

**Superadmin key management UI:** `public/superadmin/keys.php` — lists all keys, shows status (available / used), copy-to-clipboard via `data-key` attribute, generate new keys on demand.

**Settings page:** `settings.php?tab=license` — edition status, quota bars (free only), feature comparison table, key change button.

## Front-end assets (no CDN — all local)

All static assets must be served from `public/assets/`. Do not introduce CDN links.

| Asset | Path |
|-------|------|
| Font Awesome 6.7.2 Free | `public/assets/fa/css/all.min.css` + `webfonts/` |
| Inter font v20 | `public/assets/fonts/inter.css` + 7 woff2 files |
| Chart.js 4 UMD | `public/assets/js/chart.umd.min.js` |

**Icon system:** Font Awesome `<i class="fa-solid fa-*"></i>` everywhere. Never reintroduce emoji as UI icons. The `<link>` for FA is in `templates/layout.php`; `login.php` and `setup.php` have their own inline `<style>` and don't use the layout, so add the FA link there too if icons are needed.

Intentional text characters kept as-is (not FA icons): `✓` (U+2713) in CSS `content:`, plan tables, inline "all paid" messages; `✕` (U+2715) on remove-line buttons in `invoice_form.php` and `expense/create.php`.

## App name

**Freelancer-invoice** by **ISSU DEV**. Footer: `© ISSU DEV`.
