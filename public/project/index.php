<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\ProjectRepository;
use App\Database\ExpenseRepository;

$repo    = new ProjectRepository();
$expRepo = new ExpenseRepository();
$all     = $repo->all();
$stats   = $repo->stats();

$statusConfig = [
    'non_commence' => ['label' => 'Non commencé', 'class' => 'badge-draft',     'icon' => '⏳'],
    'en_cours'     => ['label' => 'En cours',     'class' => 'badge-sent',      'icon' => '🔨'],
    'livre'        => ['label' => 'Livré',         'class' => 'badge-paid',      'icon' => '<i class="fa-solid fa-box-open"></i>'],
    'valide'       => ['label' => 'Validé client', 'class' => 'badge-paid',      'icon' => '<i class="fa-solid fa-circle-check"></i>'],
];

$filterStatus = $_GET['status'] ?? '';
if ($filterStatus !== '') {
    $all = array_filter($all, fn($r) => $r['status'] === $filterStatus);
}

$pageTitle   = 'Projets en cours';
$currentPage = 'projects';
$topbarActions = '';

require __DIR__ . '/../../templates/layout.php';
?>

<style>
.proj-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:22px; }
</style>

<!-- Stats -->
<div class="proj-stats">
  <div class="stat-card navy">
    <div class="stat-top"><div class="stat-label">Total projets</div><div class="stat-badge navy"><i class="fa-solid fa-helmet-safety"></i></div></div>
    <div class="stat-value"><?= $stats['total'] ?></div>
    <div class="stat-sub">actifs</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-top"><div class="stat-label">En cours</div><div class="stat-badge gold"><i class="fa-solid fa-hammer"></i></div></div>
    <div class="stat-value"><?= $stats['en_cours'] ?></div>
    <div class="stat-sub"><?= $stats['non_commence'] ?> non commencé<?= $stats['non_commence'] > 1 ? 's' : '' ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-top"><div class="stat-label">Livrés</div><div class="stat-badge green"><i class="fa-solid fa-box-open"></i></div></div>
    <div class="stat-value"><?= $stats['livre'] ?></div>
    <div class="stat-sub">en attente validation</div>
  </div>
  <div class="stat-card green">
    <div class="stat-top"><div class="stat-label">Validés</div><div class="stat-badge green"><i class="fa-solid fa-circle-check"></i></div></div>
    <div class="stat-value"><?= $stats['valide'] ?></div>
    <div class="stat-sub">clôturés</div>
  </div>
</div>

<!-- Filtres -->
<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
  <?php foreach (['' => 'Tous', 'non_commence' => 'Non commencés', 'en_cours' => 'En cours', 'livre' => 'Livrés', 'valide' => 'Validés'] as $val => $label): ?>
  <a href="?status=<?= urlencode($val) ?>"
     style="display:inline-flex;align-items:center;padding:5px 13px;border-radius:20px;font-size:.78rem;font-weight:600;text-decoration:none;border:1px solid;transition:all .15s;
       <?= $filterStatus === $val ? 'background:var(--navy);color:#fff;border-color:var(--navy);' : 'background:var(--white);color:var(--muted);border-color:var(--border);' ?>"
  ><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (empty($all)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fa-solid fa-helmet-safety"></i></div>
    <h3>Aucun projet</h3>
    <p>Les projets sont créés automatiquement quand une facture passe au statut "Envoyée".</p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Projet</th>
          <th>Client</th>
          <th>Facture liée</th>
          <th style="text-align:right">Montant</th>
          <th style="text-align:right">Coûts</th>
          <th style="text-align:right">Marge</th>
          <th>Statut</th>
          <th>Dates</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($all as $proj): ?>
        <?php
          $s     = $statusConfig[$proj['status']] ?? ['label' => $proj['status'], 'class' => 'badge-draft', 'icon' => '?'];
          $costs = $expRepo->totalForInvoice((int)$proj['invoice_id']);
          $marge = (int)$proj['total_net'] - $costs;
          $margeClass = $marge >= 0 ? 'color:#059669' : 'color:#dc2626';
        ?>
        <tr>
          <td>
            <div style="font-weight:600;font-size:.83rem"><?= htmlspecialchars($proj['title']) ?></div>
          </td>
          <td style="font-size:.8rem"><?= htmlspecialchars($proj['client_name'] ?: '—') ?></td>
          <td>
            <a href="/invoice/edit.php?id=<?= $proj['invoice_id'] ?>"
               style="font-size:.78rem;font-weight:600;color:var(--navy);text-decoration:none">
              <?= htmlspecialchars($proj['invoice_number']) ?>
            </a>
          </td>
          <td style="text-align:right;font-size:.8rem;font-weight:600">
            <?= number_format((int)$proj['total_net'], 0, ',', ' ') ?> <span style="color:var(--muted);font-weight:400">FCFA</span>
          </td>
          <td style="text-align:right;font-size:.8rem;color:var(--red)">
            <?= $costs > 0 ? '−' . number_format($costs, 0, ',', ' ') . ' FCFA' : '—' ?>
          </td>
          <td style="text-align:right;font-size:.8rem;font-weight:700;<?= $margeClass ?>">
            <?= number_format($marge, 0, ',', ' ') ?> FCFA
          </td>
          <td>
            <select class="status-select" data-id="<?= $proj['id'] ?>">
              <?php foreach (['non_commence'=>'Non commencé','en_cours'=>'En cours','livre'=>'Livré','valide'=>'Validé'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= $proj['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td style="font-size:.75rem;color:var(--muted)">
            <?= $proj['start_date'] ? date('d/m', strtotime($proj['start_date'])) : '' ?>
            <?= ($proj['start_date'] && $proj['end_date']) ? ' → ' : '' ?>
            <?= $proj['end_date'] ? date('d/m/Y', strtotime($proj['end_date'])) : '' ?>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="/project/edit.php?id=<?= $proj['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier"><i class="fa-solid fa-pen-to-square"></i></a>
              <a href="/expense/index.php?invoice_id=<?= $proj['invoice_id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Dépenses"><i class="fa-solid fa-coins"></i></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.status-select').forEach(sel => {
  sel.addEventListener('change', function() {
    fetch('/project/status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + this.dataset.id + '&status=' + encodeURIComponent(this.value)
    });
  });
});
</script>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
