<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
use App\Database\UserRepository;
use App\Services\ActivityLogger;

Auth::requireAdmin();

/* ── Filtres ─────────────────────────────────────────────────────── */
$preset  = $_GET['preset']   ?? 'week';
$fromGet = $_GET['from']     ?? '';
$toGet   = $_GET['to']       ?? '';
$userId  = (int) ($_GET['user_id'] ?? 0);
$action  = $_GET['action']   ?? '';

$today = date('Y-m-d');

switch ($preset) {
    case 'today':  $from = $today;  $to = $today; break;
    case 'week':   $from = date('Y-m-d', strtotime('monday this week')); $to = $today; break;
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

$logs  = ActivityLogger::fetch($filters, 1000);
$users = (new UserRepository())->all();

/* Catégories d'actions pour le filtre */
$actionGroups = [
    'auth'     => 'Connexion / Déconnexion',
    'invoice'  => 'Factures',
    'devis'    => 'Devis',
    'client'   => 'Clients',
    'user'     => 'Utilisateurs',
    'expense'  => 'Dépenses',
    'settings' => 'Paramètres',
];

/* Icônes par catégorie */
function logIcon(string $action): string {
    $cat = explode('.', $action)[0];
    return match($cat) {
        'auth'     => '<i class="fa-solid fa-right-to-bracket" style="color:#059669"></i>',
        'invoice'  => '<i class="fa-solid fa-file-invoice" style="color:#0284c7"></i>',
        'devis'    => '<i class="fa-solid fa-file-lines" style="color:#7c3aed"></i>',
        'client'   => '<i class="fa-solid fa-user-tie" style="color:#d97706"></i>',
        'user'     => '<i class="fa-solid fa-user-gear" style="color:#dc2626"></i>',
        'expense'  => '<i class="fa-solid fa-receipt" style="color:#db2777"></i>',
        'settings' => '<i class="fa-solid fa-gear" style="color:#64748b"></i>',
        default    => '<i class="fa-solid fa-circle-dot" style="color:#94a3b8"></i>',
    };
}

/* Badge couleur par sous-action */
function logBadge(string $action): string {
    $sub = explode('.', $action)[1] ?? '';
    return match($sub) {
        'login'     => '<span class="badge badge-paid">connexion</span>',
        'logout'    => '<span class="badge badge-draft">déconnexion</span>',
        'create'    => '<span class="badge badge-paid">création</span>',
        'edit'      => '<span class="badge badge-sent">modification</span>',
        'delete'    => '<span class="badge badge-cancelled">suppression</span>',
        'duplicate' => '<span class="badge badge-draft">duplication</span>',
        'status'    => '<span class="badge badge-sent">statut</span>',
        'restore'   => '<span class="badge badge-cancelled">restauration</span>',
        default     => '<span class="badge badge-draft">' . htmlspecialchars($sub) . '</span>',
    };
}

$pageTitle   = 'Journal d\'activité';
$currentPage = 'admin_logs';

$printUrl = '/admin/logs_print.php?' . http_build_query([
    'preset'  => $preset,
    'from'    => $from,
    'to'      => $to,
    'user_id' => $userId,
    'action'  => $action,
]);

$topbarActions = '<a href="' . htmlspecialchars($printUrl) . '" target="_blank" class="btn btn-secondary"><i class="fa-solid fa-print"></i> Imprimer</a>';

require __DIR__ . '/../../templates/layout.php';
?>

<style>
.filter-bar {
  display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;
  background:#fff; border:1px solid var(--border); border-radius:12px;
  padding:16px 18px; margin-bottom:20px;
}
.filter-bar .field { margin:0; min-width:140px; }
.preset-pills { display:flex; gap:6px; flex-wrap:wrap; }
.preset-pill {
  padding:6px 14px; border-radius:20px; font-size:.76rem; font-weight:600;
  border:1.5px solid var(--border); background:#fff; color:var(--muted);
  cursor:pointer; text-decoration:none; transition:all .15s;
}
.preset-pill:hover { border-color:var(--navy); color:var(--navy); }
.preset-pill.active { background:var(--navy); color:#fff; border-color:var(--navy); }
.log-icon { width:28px; text-align:center; }
.log-count { font-size:.75rem; color:var(--muted); }
</style>

<!-- Filtres -->
<form method="GET" action="/admin/logs.php" id="filter-form">
<div class="filter-bar">

  <!-- Périodes rapides -->
  <div>
    <label style="font-size:.72rem;font-weight:600;color:var(--muted);display:block;margin-bottom:6px">Période</label>
    <div class="preset-pills">
      <?php foreach (['today' => 'Aujourd\'hui', 'week' => 'Cette semaine', 'month' => 'Ce mois', 'custom' => 'Personnalisé'] as $k => $v): ?>
      <a href="?preset=<?= $k ?>&user_id=<?= $userId ?>&action=<?= urlencode($action) ?>"
         class="preset-pill <?= $preset === $k ? 'active' : '' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Dates perso -->
  <?php if ($preset === 'custom'): ?>
  <div class="field">
    <label>Du</label>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" onchange="this.form.submit()">
    <input type="hidden" name="preset" value="custom">
  </div>
  <div class="field">
    <label>Au</label>
    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" onchange="this.form.submit()">
  </div>
  <?php endif; ?>

  <!-- Utilisateur -->
  <div class="field">
    <label>Utilisateur</label>
    <select name="user_id" onchange="this.form.submit()">
      <option value="0">Tous</option>
      <?php foreach ($users as $u): ?>
      <option value="<?= $u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($u['username']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Catégorie -->
  <div class="field">
    <label>Catégorie</label>
    <select name="action" onchange="this.form.submit()">
      <option value="">Toutes</option>
      <?php foreach ($actionGroups as $k => $v): ?>
      <option value="<?= $k ?>" <?= $action === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Reset -->
  <a href="/admin/logs.php" class="btn btn-secondary" style="align-self:flex-end">
    <i class="fa-solid fa-rotate-left"></i>
  </a>
</div>
</form>

<!-- Tableau -->
<div class="card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between">
    <h2>
      Journal d'activité
      <span class="log-count"><?= count($logs) ?> entrée<?= count($logs) > 1 ? 's' : '' ?>
      du <?= date('d/m/Y', strtotime($from)) ?> au <?= date('d/m/Y', strtotime($to)) ?></span>
    </h2>
    <a href="<?= htmlspecialchars($printUrl) ?>" target="_blank" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-print"></i> Imprimer
    </a>
  </div>

  <?php if (empty($logs)): ?>
  <div style="padding:40px;text-align:center;color:var(--muted)">
    <i class="fa-solid fa-inbox" style="font-size:2rem;opacity:.3"></i>
    <p style="margin-top:10px;font-size:.85rem">Aucune activité sur cette période.</p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:36px"></th>
          <th>Date &amp; Heure</th>
          <th>Utilisateur</th>
          <th>Rôle</th>
          <th>Action</th>
          <th>Détail</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td class="log-icon"><?= logIcon($log['action']) ?></td>
        <td style="font-size:.8rem;color:var(--muted);white-space:nowrap">
          <?= date('d/m/Y', strtotime($log['created_at'])) ?><br>
          <span style="font-size:.72rem"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
        </td>
        <td style="font-weight:600"><?= htmlspecialchars($log['username']) ?></td>
        <td>
          <?php $badge = match($log['role']) {
            'superadmin'   => 'badge-cancelled',
            'admin'        => 'badge-sent',
            'gestionnaire' => 'badge-paid',
            default        => 'badge-draft',
          }; ?>
          <span class="badge <?= $badge ?>"><?= htmlspecialchars($log['role']) ?></span>
        </td>
        <td><?= logBadge($log['action']) ?></td>
        <td style="font-size:.82rem"><?= htmlspecialchars($log['label']) ?></td>
        <td style="font-size:.72rem;color:var(--muted-light)"><?= htmlspecialchars($log['ip'] ?? '/') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
