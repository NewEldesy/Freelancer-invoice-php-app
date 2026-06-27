<?php
\App\Auth\Auth::start();

// License check — skip on the activation page itself
if (!str_ends_with($_SERVER['PHP_SELF'] ?? '', '/activate.php')) {
    \App\Services\LicenseService::requireValid();
}

$_licenseEdition = \App\Services\LicenseService::edition();
$_licenseIsFree  = \App\Services\LicenseService::isFree();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Factures') ?> — Freelancer-invoice</title>
<meta name="csrf-token" content="<?= htmlspecialchars(\App\Auth\Auth::csrfToken()) ?>">
<link rel="stylesheet" href="/assets/fa/css/all.min.css">
<style>
@import url('/assets/fonts/inter.css');

:root {
  --navy:       #0f172a;
  --navy-light: #1e293b;
  --gold:       #f59e0b;
  --gold-light: #fde68a;
  --gold-dim:   rgba(245,158,11,.12);
  --bg:         #f8fafc;
  --white:      #ffffff;
  --border:     #e2e8f0;
  --border-soft:#f1f5f9;
  --text:       #1e293b;
  --muted:      #64748b;
  --muted-light:#94a3b8;
  --green:      #10b981;
  --green-bg:   #d1fae5;
  --blue:       #3b82f6;
  --blue-bg:    #dbeafe;
  --orange:     #f97316;
  --orange-bg:  #ffedd5;
  --red:        #ef4444;
  --red-bg:     #fee2e2;
  --purple:     #8b5cf6;
  --purple-bg:  #ede9fe;
  --sidebar-w:  240px;
  --radius:     10px;
  --shadow-sm:  0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
  --shadow:     0 4px 12px rgba(0,0,0,.07), 0 1px 3px rgba(0,0,0,.05);
  --shadow-lg:  0 10px 30px rgba(0,0,0,.10), 0 2px 6px rgba(0,0,0,.06);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Inter', 'Segoe UI', sans-serif;
  background: var(--bg);
  color: var(--text);
  display: flex;
  min-height: 100vh;
  font-size: 14px;
  line-height: 1.5;
  -webkit-font-smoothing: antialiased;
}

/* ═══════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════ */
.sidebar {
  width: var(--sidebar-w);
  background: var(--navy);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
  overflow: hidden;
}

.sidebar::after {
  content: '';
  position: absolute;
  top: 0; right: 0;
  width: 1px; height: 100%;
  background: rgba(255,255,255,.04);
}

.sidebar-brand {
  padding: 20px 20px 16px;
  display: flex;
  align-items: center;
  gap: 11px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  text-decoration: none;
}

.brand-icon {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--gold), #d97706);
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: 17px;
  box-shadow: 0 2px 8px rgba(245,158,11,.35);
  flex-shrink: 0;
}

.brand-text .name { color: #fff; font-size: .9rem; font-weight: 700; letter-spacing: -.01em; }
.brand-text .sub  { color: #475569; font-size: .68rem; margin-top: 1px; }

nav { flex: 1; padding: 10px 0; overflow-y: auto; }

.nav-section {
  font-size: .62rem;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  color: #334155;
  padding: 14px 20px 5px;
  font-weight: 600;
}

nav a {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 14px 8px 20px;
  margin: 1px 8px;
  border-radius: 8px;
  color: #94a3b8;
  font-size: .83rem;
  font-weight: 500;
  text-decoration: none;
  transition: all .15s;
  position: relative;
}

nav a .nav-icon { font-size: 15px; width: 20px; text-align: center; flex-shrink: 0; }

nav a:hover {
  background: rgba(255,255,255,.06);
  color: #e2e8f0;
}

nav a.active {
  background: var(--gold-dim);
  color: var(--gold);
  font-weight: 600;
}

nav a.active .nav-icon { filter: none; }

.sidebar-foot {
  padding: 14px 20px;
  border-top: 1px solid rgba(255,255,255,.05);
  font-size: .7rem;
  color: #334155;
}

/* ═══════════════════════════════════════
   MAIN AREA
═══════════════════════════════════════ */
.main {
  margin-left: var(--sidebar-w);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.topbar {
  background: var(--white);
  border-bottom: 1px solid var(--border);
  padding: 0 28px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 50;
}

.topbar-left { display: flex; align-items: center; gap: 10px; }
.topbar-breadcrumb { color: var(--muted); font-size: .75rem; }
.topbar h1 { font-size: .95rem; font-weight: 600; color: var(--navy); letter-spacing: -.01em; }
.topbar .actions { display: flex; gap: 8px; align-items: center; }

.content { padding: 26px 28px; flex: 1; }

/* ═══════════════════════════════════════
   BUTTONS
═══════════════════════════════════════ */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  border-radius: 8px;
  border: none;
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  text-decoration: none;
  transition: all .15s;
  white-space: nowrap;
  font-family: inherit;
}

.btn-primary {
  background: var(--gold);
  color: var(--navy);
  box-shadow: 0 1px 3px rgba(245,158,11,.3);
}
.btn-primary:hover { background: #d97706; box-shadow: 0 3px 8px rgba(245,158,11,.4); transform: translateY(-1px); }

.btn-secondary {
  background: var(--white);
  color: var(--text);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
}
.btn-secondary:hover { background: var(--bg); border-color: #cbd5e1; }

.btn-danger  { background: var(--red-bg); color: var(--red); border: 1px solid #fca5a5; }
.btn-danger:hover  { background: #fecaca; }
.btn-success { background: var(--green-bg); color: #059669; border: 1px solid #6ee7b7; }
.btn-success:hover { background: #a7f3d0; }

.btn-sm  { padding: 5px 10px; font-size: .75rem; border-radius: 6px; }
.btn-icon{ padding: 6px 7px; }

/* ═══════════════════════════════════════
   CARDS
═══════════════════════════════════════ */
.card {
  background: var(--white);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-sm);
}

.card-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border-soft);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-header h2 { font-size: .88rem; font-weight: 600; color: var(--navy); }
.card-body { padding: 20px; }

/* ═══════════════════════════════════════
   STAT CARDS
═══════════════════════════════════════ */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 22px;
}

.stat-card {
  background: var(--white);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  padding: 18px 20px;
  box-shadow: var(--shadow-sm);
  position: relative;
  overflow: hidden;
  transition: box-shadow .2s, transform .2s;
}

.stat-card:hover { box-shadow: var(--shadow); transform: translateY(-2px); }

.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  border-radius: var(--radius) var(--radius) 0 0;
}

.stat-card.navy::before   { background: linear-gradient(90deg, var(--navy), #334155); }
.stat-card.green::before  { background: linear-gradient(90deg, var(--green), #34d399); }
.stat-card.gold::before   { background: linear-gradient(90deg, var(--gold), #fbbf24); }
.stat-card.red::before    { background: linear-gradient(90deg, var(--red), #f87171); }

.stat-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
.stat-label { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); }
.stat-badge {
  width: 34px; height: 34px;
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px;
}
.stat-badge.navy   { background: #e2e8f0; }
.stat-badge.green  { background: var(--green-bg); }
.stat-badge.gold   { background: #fef3c7; }
.stat-badge.red    { background: var(--red-bg); }

.stat-value { font-size: 1.6rem; font-weight: 700; color: var(--navy); letter-spacing: -.02em; line-height: 1; }
.stat-sub   { font-size: .72rem; color: var(--muted-light); margin-top: 5px; }

/* ═══════════════════════════════════════
   DATA TABLE
═══════════════════════════════════════ */
.table-wrap { overflow-x: auto; }

table.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .82rem;
}

table.data-table th {
  text-align: left;
  padding: 10px 14px;
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: var(--muted);
  border-bottom: 1px solid var(--border);
  font-weight: 600;
  background: #fafbfc;
  white-space: nowrap;
}

table.data-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border-soft);
  vertical-align: middle;
  color: var(--text);
}

table.data-table tr:last-child td { border-bottom: none; }
table.data-table tbody tr { transition: background .1s; }
table.data-table tbody tr:hover td { background: #f8fafc; }

/* ═══════════════════════════════════════
   BADGES
═══════════════════════════════════════ */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 9px;
  border-radius: 20px;
  font-size: .71rem;
  font-weight: 600;
  white-space: nowrap;
}

.badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }

.badge-draft     { background: #f1f5f9; color: #475569; }
.badge-draft::before { background: #94a3b8; }

.badge-sent      { background: var(--blue-bg); color: #1d4ed8; }
.badge-sent::before { background: var(--blue); }

.badge-paid      { background: var(--green-bg); color: #065f46; }
.badge-paid::before { background: var(--green); }

.badge-cancelled { background: var(--red-bg); color: #991b1b; }
.badge-cancelled::before { background: var(--red); }

/* ═══════════════════════════════════════
   FORMS
═══════════════════════════════════════ */
.form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

.field { display: flex; flex-direction: column; gap: 5px; }

.field label {
  font-size: .75rem;
  font-weight: 600;
  color: var(--muted);
  letter-spacing: .01em;
}

.field input,
.field select,
.field textarea {
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 8px 11px;
  font-size: .84rem;
  color: var(--text);
  background: var(--white);
  outline: none;
  transition: border .15s, box-shadow .15s;
  width: 100%;
  font-family: inherit;
}

.field input:focus,
.field select:focus,
.field textarea:focus {
  border-color: var(--gold);
  box-shadow: 0 0 0 3px rgba(245,158,11,.12);
}

.field input[readonly] {
  background: #f8fafc;
  color: var(--muted);
  cursor: default;
}

.field textarea { resize: vertical; }
.field .hint { font-size: .71rem; color: var(--muted-light); }

.section-title {
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .8px;
  color: var(--muted);
  font-weight: 600;
  margin: 24px 0 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border-soft);
  display: flex;
  align-items: center;
  gap: 7px;
}

/* Lines table */
.lines-tbl { width: 100%; border-collapse: collapse; font-size: .82rem; }
.lines-tbl thead tr { background: var(--navy); }
.lines-tbl th {
  color: rgba(255,255,255,.85);
  padding: 9px 10px;
  font-size: .7rem;
  font-weight: 600;
  text-align: left;
  letter-spacing: .3px;
}
.lines-tbl th:first-child { border-radius: 8px 0 0 0; }
.lines-tbl th:last-child  { border-radius: 0 8px 0 0; }

.lines-tbl td { padding: 5px 4px; border-bottom: 1px solid var(--border-soft); }

.lines-tbl input {
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 6px 9px;
  font-size: .81rem;
  width: 100%;
  font-family: inherit;
  color: var(--text);
  transition: border .15s, box-shadow .15s;
  background: var(--white);
}

.lines-tbl input:focus {
  border-color: var(--gold);
  box-shadow: 0 0 0 2px rgba(245,158,11,.1);
  outline: none;
}

.lines-tbl tbody tr:hover { background: #fafbfc; }

.totals-preview {
  background: linear-gradient(135deg, #fafbfc, var(--white));
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px 20px;
  margin-top: 14px;
}

.totals-preview .row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 5px 0;
  font-size: .84rem;
  color: var(--muted);
}

.totals-preview .row.final {
  font-weight: 700;
  font-size: .95rem;
  color: var(--navy);
  border-top: 2px solid var(--border);
  margin-top: 8px;
  padding-top: 10px;
}

/* Prestation block */
.prestation-block {
  background: linear-gradient(135deg, #fffbeb, #fef3c7);
  border: 1px solid #fde68a;
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 14px;
}

/* ═══════════════════════════════════════
   ALERTS
═══════════════════════════════════════ */
.alert {
  padding: 11px 16px;
  border-radius: 8px;
  font-size: .83rem;
  margin-bottom: 18px;
  display: flex;
  align-items: flex-start;
  gap: 8px;
}

.alert-success { background: var(--green-bg); border: 1px solid #6ee7b7; color: #065f46; }
.alert-error   { background: var(--red-bg); border: 1px solid #fca5a5; color: #991b1b; }

/* ═══════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════ */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
}

.empty-icon {
  width: 64px; height: 64px;
  background: var(--bg);
  border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 28px;
  margin: 0 auto 16px;
  border: 1px solid var(--border);
}

.empty-state h3 { font-size: .95rem; color: var(--navy); margin-bottom: 6px; font-weight: 600; }
.empty-state p  { font-size: .82rem; margin-bottom: 20px; }

/* ═══════════════════════════════════════
   STATUS SELECT (inline)
═══════════════════════════════════════ */
.status-select {
  font-size: .74rem;
  padding: 4px 8px;
  border-radius: 20px;
  border: 1px solid var(--border);
  background: var(--white);
  color: var(--text);
  cursor: pointer;
  font-family: inherit;
  font-weight: 500;
  appearance: none;
  padding-right: 20px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2394a3b8'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 6px center;
}

/* ═══════════════════════════════════════
   DIVIDER / PAGE TITLE
═══════════════════════════════════════ */
.page-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--navy);
  letter-spacing: -.02em;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.page-title .sub {
  font-size: .78rem;
  font-weight: 400;
  color: var(--muted);
  letter-spacing: 0;
}

/* Scrollbar */
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
</style>
</head>
<body>

<!-- ══ Sidebar ══ -->
<aside class="sidebar">
  <a href="/index.php" class="sidebar-brand">
    <div class="brand-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    <div class="brand-text">
      <div class="name">Freelancer-invoice</div>
      <div class="sub">Gestion des factures</div>
    </div>
  </a>

  <?php $_role = \App\Auth\Auth::role(); ?>
  <nav>
    <?php if ($_role !== 'admin'): ?>
    <div class="nav-section">Principal</div>
    <a href="/index.php" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span> Tableau de bord
    </a>
    <a href="/invoice/list.php" class="<?= ($currentPage ?? '') === 'list' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-file-invoice"></i></span> Factures
    </a>
    <a href="/devis/index.php" class="<?= ($currentPage ?? '') === 'devis' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-file-circle-question"></i></span> Devis
    </a>

    <div class="nav-section">Acquisition</div>
    <a href="/client/index.php" class="<?= ($currentPage ?? '') === 'clients' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-users"></i></span> Clients
    </a>
    <a href="/pipeline/index.php" class="<?= ($currentPage ?? '') === 'pipeline' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-filter"></i></span> Pipeline commercial
    </a>
    <?php if ($_role === 'gestionnaire'): ?>
    <a href="/invoice/create.php" class="<?= ($currentPage ?? '') === 'create' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-plus"></i></span> Nouvelle facture
    </a>
    <a href="/devis/create.php" class="<?= ($currentPage ?? '') === 'devis_create' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-file-pen"></i></span> Nouveau devis
    </a>
    <?php endif; ?>

    <div class="nav-section">Exécution</div>
    <a href="/project/index.php" class="<?= ($currentPage ?? '') === 'projects' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-diagram-project"></i></span> Projets
    </a>

    <div class="nav-section">Bénéfice</div>
    <a href="/expense/index.php" class="<?= ($currentPage ?? '') === 'expenses' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-arrow-trend-down"></i></span> Dépenses
    </a>
    <a href="/accounting/index.php" class="<?= ($currentPage ?? '') === 'accounting' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span> Comptabilité
    </a>

    <?php if ($_role === 'gestionnaire'): ?>
    <div class="nav-section">Configuration</div>
    <a href="/services/index.php" class="<?= ($currentPage ?? '') === 'services' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-box-open"></i></span> Catalogue prestations
    </a>
    <a href="/settings.php" class="<?= ($currentPage ?? '') === 'settings' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-gear"></i></span> Paramètres
    </a>
    <?php endif; ?>

    <?php else: ?>
    <div class="nav-section">Principal</div>
    <a href="/index.php" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-chart-pie"></i></span> Tableau de bord
    </a>
    <?php endif; ?>

    <?php if ($_role === 'superadmin'): ?>
    <div class="nav-section">ISSU DEV</div>
    <a href="/superadmin/keys.php" class="<?= ($currentPage ?? '') === 'superadmin_keys' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-key"></i></span> Clés de licence
    </a>
    <?php endif; ?>

    <?php if ($_role === 'admin'): ?>
    <div class="nav-section">Administration</div>
    <a href="/admin/users.php" class="<?= ($currentPage ?? '') === 'admin_users' ? 'active' : '' ?>">
      <span class="nav-icon"><i class="fa-solid fa-users-gear"></i></span> Utilisateurs
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-foot">
    <?php $authUser = \App\Auth\Auth::user(); ?>
    <?php if ($authUser): ?>
    <?php
      $roleLabels = ['superadmin' => 'Super Admin', 'admin' => 'Administrateur', 'gestionnaire' => 'Gestionnaire', 'utilisateur' => 'Utilisateur'];
      $roleColors = ['superadmin' => '#8b5cf6', 'admin' => '#f59e0b', 'gestionnaire' => '#10b981', 'utilisateur' => '#64748b'];
      $roleLabel  = $roleLabels[$authUser['role']] ?? $authUser['role'];
      $roleColor  = $roleColors[$authUser['role']] ?? '#64748b';
    ?>
    <!-- License badge -->
    <?php
      $licBadgeText  = match($_licenseEdition) { 'pro' => '<i class="fa-solid fa-star"></i> Pro', 'enterprise' => '<i class="fa-solid fa-building"></i> Entreprise', default => '<i class="fa-solid fa-lock-open"></i> Gratuit' };
      $licBadgeColor = match($_licenseEdition) { 'pro' => '#f59e0b', 'enterprise' => '#8b5cf6', default => '#64748b' };
    ?>
    <div style="margin-bottom:8px;padding:5px 10px;background:rgba(255,255,255,.04);border-radius:7px;border:1px solid rgba(255,255,255,.07);display:flex;align-items:center;justify-content:space-between">
      <span style="font-size:.65rem;font-weight:700;color:<?= $licBadgeColor ?>"><?= $licBadgeText ?></span>
      <?php if ($_licenseIsFree): ?>
      <a href="/activate.php" style="font-size:.6rem;color:#f59e0b;text-decoration:none;font-weight:600">Mettre à jour →</a>
      <?php endif; ?>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
      <div>
        <div style="color:#e2e8f0;font-size:.78rem;font-weight:600"><?= htmlspecialchars($authUser['username']) ?></div>
        <div style="font-size:.68rem;margin-top:2px;font-weight:500;color:<?= $roleColor ?>"><?= $roleLabel ?></div>
      </div>
      <a href="/logout.php" title="Déconnexion"
         style="display:flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:7px;background:rgba(255,255,255,.06);color:#64748b;text-decoration:none;font-size:13px;transition:all .15s;flex-shrink:0"
         onmouseover="this.style.background='rgba(239,68,68,.15)';this.style.color='#f87171'"
         onmouseout="this.style.background='rgba(255,255,255,.06)';this.style.color='#64748b'"
         title="Se déconnecter"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
    <?php else: ?>
    © ISSU DEV
    <?php endif; ?>
  </div>
</aside>

<!-- ══ Main ══ -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <h1><?= htmlspecialchars($pageTitle ?? '') ?></h1>
    </div>
    <div class="actions">
      <?php if (isset($topbarActions)) echo $topbarActions; ?>
    </div>
  </div>

  <div class="content">
    <?php if (isset($flashSuccess)): ?>
      <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if (isset($flashError)): ?>
      <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($flashError) ?></div>
    <?php endif; ?>
