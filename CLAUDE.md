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
- **`extractFields()` / `create()` / `update()` must stay in sync.** Every key returned by `extractFields()` must have a matching placeholder in both the INSERT and the UPDATE SQL. Adding a field to `extractFields()` without adding it to `update()`'s SET clause causes `PDOException: column index out of range`.

## Form fields — use `.field` class

Always wrap form inputs with the `.field` class defined in `layout.php`. This gives consistent border, focus ring (gold), padding, and font. Never use `class="form-control"` or raw inline styles on inputs — that class is not defined and will produce unstyled browser-default fields.

```html
<div class="field">
  <label>Label text</label>
  <input type="text" name="foo" value="...">
  <span class="hint">Optional helper text</span>  <!-- .hint = small muted text -->
</div>
```

Use `class="form-grid-2"` for a two-column responsive grid of `.field` blocks (already defined in the layout CSS).

## Flash messages (PRG pattern)

Pages that handle POST actions use the Post-Redirect-Get pattern to avoid duplicate submissions. Flash messages are stored in `$_SESSION` before the redirect and read+cleared on GET:

```php
// In the POST handler:
$_SESSION['_mypage_success'] = 'Action réalisée.';
header('Location: /mypage.php'); exit;

// At the top of GET rendering:
$flashSuccess = '';
if (!empty($_SESSION['_mypage_success'])) {
    $flashSuccess = $_SESSION['_mypage_success'];
    unset($_SESSION['_mypage_success']);
}
```

**Critical:** never pre-initialize `$flashSuccess = ''` before checking — the `isset()` in `layout.php` uses that variable to conditionally render the alert banner. An empty pre-initialized string triggers an empty banner on every page load.

## Auto-dismiss alerts

`templates/layout_end.php` includes JS that auto-dismisses `.alert`, `.alert-success`, and `.alert-error` elements after 4 seconds (fade + height collapse + `el.remove()`). This only runs on pages that include `layout_end.php`.

Standalone pages (`login.php`, `activate.php`) don't include `layout_end.php` — they must include the same dismiss snippet inline before `</body>`:

```js
document.querySelectorAll('.alert-error, .alert-success').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .4s ease, max-height .4s ease, margin .4s ease, padding .4s ease';
    el.style.opacity = '0'; el.style.maxHeight = '0';
    el.style.margin = '0'; el.style.padding = '0'; el.style.overflow = 'hidden';
    setTimeout(() => el.remove(), 420);
  }, 4000);
});
```

## onclick attributes with JSON strings

When passing PHP strings into `onclick` via `json_encode`, always apply `htmlspecialchars()` **after** `json_encode()`, not before:

```php
// ✅ correct — HTML-encodes the surrounding JSON quotes so the attribute stays valid
onclick="fn(<?= htmlspecialchars(json_encode($value), ENT_QUOTES) ?>)"

// ❌ wrong — json_encode wraps in double quotes which breaks the HTML attribute
onclick="fn(<?= json_encode(htmlspecialchars($value)) ?>)"
```

## Authentication & roles

`src/Auth/Auth.php` — static session helper. Call one guard at the top of every page:

| Guard | Who can access |
|-------|---------------|
| `Auth::requireSuperAdmin()` | `superadmin` only |
| `Auth::requireAdmin()` | `admin` only (also used by `settings.php`, `settings/backup.php`, `settings/restore.php`) |
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
- `Auth::user()` → `['id', 'username', 'email', 'role', 'avatar']` from session (`avatar` is a URL path like `/uploads/avatars/avatar_1.jpg`, or `null`)

**Session security:** `Auth::logout()` clears `$_SESSION`, deletes the session cookie (`setcookie(..., time()-3600)`), then calls `session_destroy()`. Never call `session_start()` directly — always go through `Auth::start()`.

`src/Database/UserRepository.php` — CRUD for users. Passwords stored with `password_hash(PASSWORD_BCRYPT)`. `verify(username, password)` returns the user row or null. `create()` accepts roles: `superadmin`, `admin`, `gestionnaire`, `utilisateur`. Role is whitelisted server-side in `admin/users/create.php` — never trust `$_POST['role']` raw.

## Data layer

`Database` — PDO singleton, SQLite, auto-migrates schema on first connection.

Tables: `invoices`, `invoice_lines`, `opportunities`, `projects`, `expenses`, `users`, `license`, `license_keys`, `feature_usage`, `company_settings`, `clients`, `services`, `payments`, `recurring_templates`.

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

`ProjectRepository` — `findByInvoice(int $invoiceId)` prevents duplicate auto-creation when invoice status → `envoyée`. Auto-creation is triggered from **both** `invoice/status.php` (Kanban AJAX) and `invoice/edit.php` (form save).

`ExpenseRepository` — `globalStats()` returns `benefice_net = ca_engage - total_depenses`. `CATEGORIES` const used in PHP and JS templates. `allForInvoice()` LEFT JOINs invoices for `invoice_number` and `client_name`.

`SettingsRepository` — key/value store in `company_settings`. Pre-fills issuer info on new invoices. Key: `invoice_prefix` for custom invoice numbering.

`ClientRepository` — CRUD on `clients` table. `allForSelect()` returns slim rows for dropdowns.

`ServiceRepository` — CRUD on `services` table. `CATEGORIES` const for 7 categories. `allGrouped()` returns rows keyed by category.

`PaymentRepository` — partial payments on invoices. `allForInvoice(int $id)`, `totalForInvoice(int $id): int`, `add()`, `delete()`, `find()`. After adding a payment, `invoice/payment/add.php` re-queries the total and auto-marks the invoice `payée` if `totalPaid >= total_net`. `payments` table has `ON DELETE CASCADE` on `invoice_id`.

`RecurringRepository` — CRUD on `recurring_templates`. `dueNow()` returns active templates whose `next_due_at <= today`. `markGenerated(int $id, string $nextDueAt)` advances the schedule. `nextDate(string $from, string $interval): string` is a static helper that returns the next date given an interval (`hebdomadaire` / `mensuel` / `trimestriel` / `annuel`). Lines stored as JSON in the `lines` TEXT column.

`SearchRepository` — `search(string $q): array` returns 4 groups (`invoices`, `devis`, `clients`, `expenses`), each capped at 10–20 rows. Used exclusively by `public/search.php`.

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
    ↓ invoice/status.php → envoyée   (Kanban AJAX)
    ↓ invoice/edit.php   → envoyée   (edit form — same logic)
Project [en_cours]  (auto-created on first envoyée via either path, title = subject or client_name)
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
- **gestionnaire**: Dashboard, Factures, Devis, Acquisition (Clients + Pipeline), Exécution (Projets), Bénéfice (Dépenses + Comptabilité), Automatisation (Récurrentes), Configuration (Catalogue prestations)
- **utilisateur**: Same as gestionnaire minus "Récurrentes" and "Catalogue prestations"

`$currentPage` values: `dashboard`, `list`, `devis`, `devis_create`, `create`, `pipeline`, `projects`, `expenses`, `settings`, `admin_users`, `superadmin_keys`, `clients`, `services`, `recurring`, `search`.

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

### Null check before delete + log
When logging a delete action, always fetch the record first and guard on `$record !== null` before calling `delete()` and `ActivityLogger::log()`. Calling `$record['field']` on null generates a PHP Warning:

```php
// ✅ correct
$record = $repo->find($id);
if ($record !== null) {
    $repo->delete($id);
    ActivityLogger::log('x.delete', 'Suppression : ' . $record['name']);
}

// ❌ wrong — $record may be null on double-submit or race
$record = $repo->find($id);
$repo->delete($id);
ActivityLogger::log('x.delete', 'Suppression : ' . ($record['name'] ?? $id));
```

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

**Icon system:** Font Awesome `<i class="fa-solid fa-*"></i>` everywhere. Never reintroduce emoji as UI icons. The `<link>` for FA is in `templates/layout.php`; `login.php`, `setup.php`, and **any other standalone page** (e.g. `logs_print.php`) don't include the layout, so add the local FA `<link>` in their `<head>` if icons are used:

```html
<link rel="stylesheet" href="/assets/fa/css/all.min.css">
```

Do not use CDN links for FA — all assets are local (`public/assets/fa/`).

Intentional text characters kept as-is (not FA icons): `✓` (U+2713) in CSS `content:`, plan tables, inline "all paid" messages; `✕` (U+2715) on remove-line buttons in `invoice_form.php` and `expense/create.php`.

## Recherche globale

Barre de recherche dans la topbar (toutes les pages) → `GET /search.php?q=...`. Recherche en SQL `LIKE %q%` sur les factures, devis, clients et dépenses. Minimum 2 caractères côté UI (pas de guard serveur). La page `expense/index.php` conserve aussi une recherche JS locale intégrée dans la card du tableau pour filtrer dans le contexte courant (filtre par facture inclus).

## Factures récurrentes

Table `recurring_templates` — modèles avec intervalles `hebdomadaire` / `mensuel` / `trimestriel` / `annuel`. Les lignes sont stockées en JSON dans la colonne `lines TEXT`.

**Auto-génération :** à chaque chargement du dashboard (`public/index.php`), si l'utilisateur est `gestionnaire`, `RecurringRepository::dueNow()` est appelé et toutes les factures dues sont créées silencieusement via `InvoiceRepository::create()`. Un flash `$_SESSION['recurring_generated']` est affiché une fois puis supprimé.

**Pages :** `public/recurring/index.php` · `create.php` · `edit.php` · `generate.php` (POST, génère manuellement) · `toggle.php` (pause/reprise) · `delete.php`.

## Activity logging

`src/Services/ActivityLogger.php` — static logger. Call `ActivityLogger::log(string $action, string $label)` immediately **before** the redirect that follows a successful write operation. It reads the authenticated user from `$_SESSION` automatically and silently swallows any DB error so a log failure never crashes the page.

```php
use App\Services\ActivityLogger;

// inside a POST handler, just before header('Location: ...')
ActivityLogger::log('invoice.create', 'Création facture #' . $number);
```

**Action naming convention:** `{category}.{verb}` — e.g. `invoice.create`, `client.delete`, `auth.login`. Categories in use: `auth`, `invoice`, `devis`, `client`, `user`, `expense`, `settings`.

**Admin UI:** `public/admin/logs.php` (filterable list — today / this week / this month / custom range + user + category) and `public/admin/logs_print.php` (printable A4 landscape with summary stats). Both are restricted to `admin` role via `Auth::requireAdmin()`. The sidebar link appears under Administration for admins (`currentPage = 'admin_logs'`).

**Table:** `activity_logs` — columns `user_id`, `username`, `role`, `action`, `label`, `ip`, `created_at`. Indexed on `created_at` and `user_id`. Created automatically by `Database::migrate()`. IP is recorded from `REMOTE_ADDR` only — never `HTTP_X_FORWARDED_FOR` (client-spoofable).

**Currently instrumented:** login, logout, invoice create/delete/status/duplicate, devis create/delete, client create/edit/delete, expense create, user create/delete, DB restore.

## User profile

`public/profile.php` — accessible to all authenticated roles. Two tabs: `?tab=info` (name, email, phone, avatar) and `?tab=password`.

- **Avatar:** uploaded to `public/uploads/avatars/avatar_{id}.{ext}`, stored in DB as `/uploads/avatars/avatar_{id}.jpg`. Path is kept in sync in `$_SESSION['auth_user']['avatar']` on upload/delete — no DB query needed per page. When reading avatar from `__DIR__` in `profile.php`, the correct path is `__DIR__ . $user['avatar']` (since `__DIR__` = `public/`).
- **Users table columns:** `first_name`, `last_name`, `phone`, `avatar` were added via safe migrations in `Database::migrate()`. `UserRepository::create()` accepts these as optional parameters.
- **Sidebar avatar:** `templates/layout.php` reads avatar from `$_SESSION['auth_user']['avatar']` — never queries the DB. The file-exists check uses `__DIR__ . '/../public' . $avatarPath`.

## Admin — user deletion protection

`public/admin/users/delete.php` and `public/admin/users.php` both protect superadmin accounts from deletion:
- The delete button is hidden in the UI for any user with `role === 'superadmin'` (replaced by a lock icon)
- `delete.php` re-fetches the target user and aborts if `role === 'superadmin'` — UI bypass is blocked server-side

## License certificate printing

`public/superadmin/keys_print.php` — accepts `?ids=1,2,3` (comma-separated key IDs), fetches them via parameterized query, and renders a print-ready 2-column grid of certificate cards. Each card has a dark gradient header, edition badge, key in monospace, and machine/expiry details. A single "Comment activer" table is shown at the bottom. Uses `print-color-adjust: exact` to preserve background colors in print/PDF.

## WhatsApp

Champ `client_whatsapp` sur `invoices` et `clients` (format international sans `+`, ex : `22961000000`). Le formulaire de facture (`invoice_form.php`) l'affiche et le `pickClient()` JS le remplit automatiquement depuis la sélection client. Sur `invoice/edit.php`, si le champ est renseigné, un bouton "Relancer" vert apparaît dans la topbar — il ouvre `https://wa.me/{numéro}?text={message_pré_rempli}` avec numéro de facture, montant et mention d'échéance dépassée si applicable.

## App name

**Freelancer-invoice** by **ISSU DEV**. Footer: `© ISSU DEV`.
