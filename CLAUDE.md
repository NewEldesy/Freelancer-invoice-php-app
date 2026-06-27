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
- The **prestation line** is stored separately on the `invoices` table (`prestation_label`, `prestation_amount`), not in `invoice_lines`. It is injected as the last entry only at PDF generation time (`pdf.php`).
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
| `gestionnaire` | Full CRUD on invoices, pipeline, projects, expenses, settings. |
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

Tables: `invoices`, `invoice_lines`, `opportunities`, `projects`, `expenses`, `users`, `license`, `license_keys`, `feature_usage`, `company_settings`, `clients`, `services`.

`InvoiceRepository`:
- `all()` — all invoices ordered by `created_at DESC`
- `allByStatus(string $status)` — filtered in SQL, use this instead of `all()` + PHP `array_filter`
- `stats()` — `ca_engage` (envoyée+payée), `ca_encaisse` (payée only)

`OpportunityRepository` — `stats()` JOINs invoices; uses `COALESCE(i.total_net, o.estimated_amount)` for won value.

`ProjectRepository` — `findByInvoice(int $invoiceId)` prevents duplicate auto-creation when invoice status → `envoyée`.

`ExpenseRepository` — `globalStats()` returns `benefice_net = ca_engage - total_depenses`. `CATEGORIES` const used in PHP and JS templates. `allForInvoice()` LEFT JOINs invoices for `invoice_number` and `client_name`.

`SettingsRepository` — key/value store in `company_settings`. Pre-fills issuer info on new invoices. New key: `invoice_prefix` (custom prefix for invoice numbering).

`ClientRepository` — CRUD on `clients` table. `allForSelect()` returns slim rows for dropdowns. Used in `invoice_form.php` and `pipeline/create.php` to pre-fill client fields.

`ServiceRepository` — CRUD on `services` table. `CATEGORIES` const for 7 categories. `allGrouped()` returns rows keyed by category. Used in `invoice_form.php` catalogue modal.

## Business pipeline (3 phases)

```
Opportunity (pipeline)
    ↓ pipeline/convert.php  →  pre-fills invoice; marks opp as won; idempotent (2nd POST redirects to existing invoice)
Invoice [brouillon]
    ↓ invoice/status.php → envoyée
Project [en_cours]  (auto-created on first envoyée, title = subject or client_name)
    + Expenses → expense/create.php
    → bénéfice net = CA engagé − total dépenses
```

## Invoice status & CA logic

```
brouillon → envoyée → payée
                    ↘ annulée
```

- **brouillon**: excluded from all CA
- **envoyée**: counts in CA engagé; triggers auto-project creation
- **payée**: counts in CA engagé + CA encaissé
- **annulée**: excluded from all CA

Status changed via `fetch()` POST. All status endpoints (`invoice/status.php`, `pipeline/status.php`, `project/status.php`) call `Auth::verifyCsrf()`.

## Invoice number format

Auto-generated as `YYYYMMDD-N` (N increments per day). If `invoice_prefix` is set in `company_settings`, format becomes `PREFIX-YYYYMMDD-N`. Readonly on create, editable on edit.

## PDF generation

`PdfGeneratorService` uses Dompdf. HTML template must use real `<table>` elements — Dompdf does not support `display:flex` or `display:table`. Footer uses `position:fixed;bottom:0`. PDF counter is incremented **after** successful generation (`pdf.php`), not before.

## Comptabilité

`public/accounting/index.php` — monthly/annual reporting for `gestionnaire` and `utilisateur`.

- `InvoiceRepository::statsByMonth(int $year)` and `ExpenseRepository::statsByMonth(int $year)` always return 12 rows (zero-filled) for easy PHP merge.
- `public/accounting/report.php` — annual P&L with N vs N-1 comparison.
- `public/accounting/export.php` — CSV export with UTF-8 BOM (`;` separator for French Excel). `?type=monthly` or `?type=annual`. Excel counter gated by license.

## Sidebar nav (role-conditional)

- **superadmin**: ISSU DEV section only → Clés de licence
- **admin**: Dashboard + Administration (Utilisateurs)
- **gestionnaire**: Dashboard, Factures, Acquisition (Clients + Pipeline + Nouvelle facture), Exécution (Projets), Bénéfice (Dépenses + Comptabilité), Configuration (Catalogue prestations + Paramètres)
- **utilisateur**: Same as gestionnaire minus "Nouvelle facture", "Catalogue prestations", and "Paramètres"

`$currentPage` values: `dashboard`, `list`, `create`, `pipeline`, `projects`, `expenses`, `settings`, `admin_users`, `superadmin_keys`, `clients`, `services`.

## Security

### CSRF protection
`Auth::csrfToken()` — per-session token in `$_SESSION['csrf_token']`. Exposed via `<meta name="csrf-token">` in layout.

`templates/layout_end.php` global JS:
- Auto-injects `<input type="hidden" name="_csrf">` into every `form[method=POST]`
- Patches `window.fetch()` to send `X-CSRF-Token` header on POST

**`Auth::verifyCsrf()` is mandatory on every POST-only action page** (delete, status change, duplicate, convert, activate). It accepts the token from `$_POST['_csrf']` or `$_SERVER['HTTP_X_CSRF_TOKEN']`.

Pages without a layout (action-only endpoints) must call `Auth::verifyCsrf()` explicitly after the `REQUEST_METHOD !== 'POST'` guard.

### File uploads
Logo uploads validate both extension AND real MIME type via `finfo` (see `invoice/edit.php`). Saved filename is always `.png`. `public/uploads/.htaccess` blocks PHP execution. Apply the same MIME check to any new upload endpoint — `invoice/create.php` and `settings.php` currently validate extension only (known gap).

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

PDF/Excel/duplicate limits use persistent counters in `feature_usage` table (`getCounter()` / `incrementCounter()`). Invoice/pipeline/expense limits compare against live DB count.

**Key lifecycle:**
1. `generateAndStore(edition, period)` — generates key with `expires=null`, stores in `license_keys` with period (`3m`/`6m`/`1y`/`2y`/`permanent`), `expires_at=NULL`
2. Client activates via `/activate.php` → `activate($key)` → looks up period from `license_keys`, calls `periodToDate()`, stores computed `expires_at` in `license` table and in `license_keys` via `markKeyUsed()`
3. A key already used on a different machine is rejected: `used=1 AND used_machine != machineId()`

**Periods:** `3m` → +3 months, `6m` → +6 months, `1y` → +1 year, `2y` → +2 years, `permanent` → `null` (never expires)

**`requireValid()`:** called in `layout.php` on every page. If `edition=none` → silently calls `activateFree()`. If expired → redirects to `/activate.php?expired=1`.

**setup.php** pre-generates at first init: 5 Pro keys (one per period) + 10 Enterprise keys (2 per period).

**Superadmin key management UI:** `public/superadmin/keys.php` — lists all keys, shows status (available / used), copy-to-clipboard via `data-key` attribute, generate new keys on demand.

**Settings page:** `settings.php?tab=license` — edition status, quota bars (free only), feature comparison table, key change button.

## App name

**Freelancer-invoice** by **ISSU DEV**. Footer: `© ISSU DEV`.
