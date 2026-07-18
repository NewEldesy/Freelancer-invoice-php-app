<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
use App\Services\ActivityLogger;

Auth::requireAdmin();

$preset  = $_GET['preset']   ?? 'week';
$fromGet = $_GET['from']     ?? '';
$toGet   = $_GET['to']       ?? '';
$userId  = (int) ($_GET['user_id'] ?? 0);
$action  = $_GET['action']   ?? '';

$today = date('Y-m-d');
switch ($preset) {
    case 'today':  $from = $today;  $to = $today; break;
    case 'month':  $from = date('Y-m-01'); $to = $today; break;
    case 'custom': $from = $fromGet ?: date('Y-m-d', strtotime('-7 days')); $to = $toGet ?: $today; break;
    default:       $from = date('Y-m-d', strtotime('monday this week')); $to = $today;
}

$filters = array_filter([
    'from'    => $from,
    'to'      => $to,
    'user_id' => $userId ?: null,
    'action'  => $action ?: null,
]);

$logs = ActivityLogger::fetch($filters, 5000);

$periodLabel = ($from === $to)
    ? date('d/m/Y', strtotime($from))
    : 'du ' . date('d/m/Y', strtotime($from)) . ' au ' . date('d/m/Y', strtotime($to));

$actionGroupLabel = match(explode('.', $action)[0]) {
    'auth'     => ' — Connexions',
    'invoice'  => ' — Factures',
    'devis'    => ' — Devis',
    'client'   => ' — Clients',
    'user'     => ' — Utilisateurs',
    'expense'  => ' — Dépenses',
    'settings' => ' — Paramètres',
    default    => '',
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Journal d'activité — <?= htmlspecialchars($periodLabel) ?></title>
<link rel="stylesheet" href="/assets/fa/css/all.min.css">
<style>
@page { size: A4 landscape; margin: 15mm 12mm; }
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 11px; color: #0f172a; background: #fff; }

.print-header {
  display: flex; justify-content: space-between; align-items: flex-end;
  padding-bottom: 10px; border-bottom: 2px solid #0f172a; margin-bottom: 14px;
}
.print-header h1 { font-size: 16px; font-weight: 800; color: #0f172a; }
.print-header .meta { font-size: 10px; color: #475569; text-align: right; }
.print-header .brand { font-size: 10px; color: #94a3b8; }

.summary { display: flex; gap: 20px; margin-bottom: 14px; }
.summary-item {
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
  padding: 8px 14px; font-size: 10px; color: #64748b;
}
.summary-item strong { display: block; font-size: 18px; font-weight: 800; color: #0f172a; }

table { width: 100%; border-collapse: collapse; }
thead { background: #0f172a; color: #fff; }
thead th { padding: 7px 8px; text-align: left; font-size: 10px; font-weight: 700; letter-spacing: .3px; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody tr { border-bottom: 1px solid #e2e8f0; }
tbody td { padding: 6px 8px; vertical-align: middle; }

.badge {
  display: inline-block; padding: 2px 6px; border-radius: 4px;
  font-size: 9px; font-weight: 700; text-transform: uppercase;
}
.badge-create   { background: #d1fae5; color: #065f46; }
.badge-edit     { background: #dbeafe; color: #1e40af; }
.badge-delete   { background: #fee2e2; color: #991b1b; }
.badge-login    { background: #d1fae5; color: #065f46; }
.badge-logout   { background: #f1f5f9; color: #475569; }
.badge-duplicate { background: #f0fdf4; color: #166534; }
.badge-status   { background: #eff6ff; color: #1d4ed8; }
.badge-restore  { background: #fff1f2; color: #be123c; }

.role-badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: 600; }
.role-admin        { background: #dbeafe; color: #1e40af; }
.role-gestionnaire { background: #d1fae5; color: #065f46; }
.role-utilisateur  { background: #f1f5f9; color: #475569; }
.role-superadmin   { background: #fee2e2; color: #991b1b; }

.print-footer {
  margin-top: 16px; padding-top: 8px; border-top: 1px solid #e2e8f0;
  font-size: 9px; color: #94a3b8; display: flex; justify-content: space-between;
}

@media print {
  body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  .no-print { display: none !important; }
  thead { background: #0f172a !important; color: #fff !important; }
}
</style>
</head>
<body>

<div class="no-print" style="padding:12px;background:#0f172a;color:#fff;display:flex;gap:12px;align-items:center">
  <button onclick="window.print()" style="background:#f59e0b;color:#0f172a;border:none;padding:8px 18px;border-radius:7px;font-weight:700;cursor:pointer;font-size:13px">
    <i class="fa-solid fa-print"></i> Imprimer / PDF
  </button>
  <a href="/admin/logs.php" style="color:#94a3b8;font-size:12px;text-decoration:none">← Retour au journal</a>
</div>

<div style="padding: 0 4px">

<div class="print-header">
  <div>
    <h1>Journal d'activité<?= htmlspecialchars($actionGroupLabel) ?></h1>
    <div class="brand">Freelancer-invoice · ISSU DEV</div>
  </div>
  <div class="meta">
    <div><?= htmlspecialchars($periodLabel) ?></div>
    <div>Généré le <?= date('d/m/Y à H:i') ?></div>
    <?php if ($userId > 0 && !empty($logs)): ?>
    <div>Utilisateur : <?= htmlspecialchars($logs[0]['username'] ?? '') ?></div>
    <?php endif; ?>
  </div>
</div>

<?php
/* Statistiques de synthèse */
$total   = count($logs);
$byUser  = [];
$byDay   = [];
foreach ($logs as $l) {
    $byUser[$l['username']] = ($byUser[$l['username']] ?? 0) + 1;
    $day = date('d/m', strtotime($l['created_at']));
    $byDay[$day] = ($byDay[$day] ?? 0) + 1;
}
arsort($byUser);
$topUser = array_key_first($byUser) ?? '—';
?>

<div class="summary">
  <div class="summary-item"><strong><?= $total ?></strong>Entrées totales</div>
  <div class="summary-item"><strong><?= count(array_unique(array_column($logs, 'user_id'))) ?></strong>Utilisateur(s) actif(s)</div>
  <div class="summary-item"><strong><?= count($byDay) ?></strong>Jour(s) avec activité</div>
  <?php if ($total > 0): ?>
  <div class="summary-item"><strong><?= htmlspecialchars($topUser) ?></strong>Utilisateur le plus actif</div>
  <?php endif; ?>
</div>

<?php if (empty($logs)): ?>
<p style="text-align:center;color:#94a3b8;padding:40px 0">Aucune activité pour cette période.</p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th style="width:110px">Date &amp; Heure</th>
      <th style="width:100px">Utilisateur</th>
      <th style="width:80px">Rôle</th>
      <th style="width:80px">Action</th>
      <th>Détail</th>
      <th style="width:100px">Adresse IP</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($logs as $log):
    $sub = explode('.', $log['action'])[1] ?? '';
    $badgeClass = match($sub) {
      'login'     => 'badge-login',
      'logout'    => 'badge-logout',
      'create'    => 'badge-create',
      'edit'      => 'badge-edit',
      'delete'    => 'badge-delete',
      'duplicate' => 'badge-duplicate',
      'status'    => 'badge-status',
      'restore'   => 'badge-restore',
      default     => 'badge-edit',
    };
    $roleClass = match($log['role']) {
      'admin'        => 'role-admin',
      'gestionnaire' => 'role-gestionnaire',
      'utilisateur'  => 'role-utilisateur',
      default        => 'role-superadmin',
    };
  ?>
  <tr>
    <td>
      <?= date('d/m/Y', strtotime($log['created_at'])) ?>
      <span style="color:#94a3b8"> <?= date('H:i:s', strtotime($log['created_at'])) ?></span>
    </td>
    <td style="font-weight:600"><?= htmlspecialchars($log['username']) ?></td>
    <td><span class="role-badge <?= $roleClass ?>"><?= htmlspecialchars($log['role']) ?></span></td>
    <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($sub) ?></span></td>
    <td><?= htmlspecialchars($log['label']) ?></td>
    <td style="color:#94a3b8"><?= htmlspecialchars($log['ip'] ?? '—') ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="print-footer">
  <span>Freelancer-invoice · © ISSU DEV</span>
  <span>Journal d'activité · <?= htmlspecialchars($periodLabel) ?></span>
  <span><?= $total ?> entrée<?= $total > 1 ? 's' : '' ?></span>
</div>

</div>
</body>
</html>
