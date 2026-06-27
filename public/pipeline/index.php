<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\OpportunityRepository;

$repo  = new OpportunityRepository();
$all   = $repo->all();
$stats = $repo->stats();

$columns = [
    'prospect'     => ['label' => 'Prospect',      'color' => '#94a3b8', 'bg' => '#f1f5f9', 'icon' => '👤'],
    'devis_envoye' => ['label' => 'Devis envoyé',  'color' => '#3b82f6', 'bg' => '#dbeafe', 'icon' => '📨'],
    'negociation'  => ['label' => 'Négociation',   'color' => '#f59e0b', 'bg' => '#fef3c7', 'icon' => '🤝'],
    'gagne'        => ['label' => 'Gagné',          'color' => '#10b981', 'bg' => '#d1fae5', 'icon' => '✅'],
    'perdu'        => ['label' => 'Perdu',          'color' => '#ef4444', 'bg' => '#fee2e2', 'icon' => '❌'],
];

$byStatus = [];
foreach ($columns as $key => $_) {
    $byStatus[$key] = [];
}
foreach ($all as $opp) {
    $byStatus[$opp['status']][] = $opp;
}

$pageTitle   = 'Pipeline commercial';
$currentPage = 'pipeline';
$topbarActions = \App\Auth\Auth::can('write') ? '<a href="/pipeline/create.php" class="btn btn-primary">➕ Nouvelle opportunité</a>' : '';

require __DIR__ . '/../../templates/layout.php';
?>

<style>
.pipeline-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:22px; }
.kanban { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; align-items:start; }
.kanban-col { background:var(--bg); border-radius:10px; border:1px solid var(--border); overflow:hidden; }
.kanban-head {
  padding:10px 14px;
  display:flex; align-items:center; justify-content:space-between;
  font-size:.75rem; font-weight:700;
}
.kanban-body { padding:8px; display:flex; flex-direction:column; gap:6px; min-height:100px; }
.opp-card {
  background:var(--white);
  border:1px solid var(--border);
  border-radius:8px;
  padding:11px 13px;
  transition:box-shadow .15s, transform .15s;
  cursor:default;
}
.opp-card:hover { box-shadow:var(--shadow); transform:translateY(-1px); }
.opp-title { font-size:.82rem; font-weight:600; color:var(--navy); margin-bottom:4px; }
.opp-client { font-size:.72rem; color:var(--muted); margin-bottom:6px; }
.opp-amount { font-size:.78rem; font-weight:700; color:var(--text); }
.opp-actions { margin-top:8px; display:flex; gap:4px; }
.col-count { font-size:.7rem; font-weight:600; }
.col-total { font-size:.68rem; opacity:.7; margin-top:1px; }
.empty-col { text-align:center; padding:20px 8px; color:var(--muted-light); font-size:.75rem; }
</style>

<!-- Stats pipeline -->
<div class="pipeline-stats">
  <div class="stat-card navy">
    <div class="stat-top">
      <div class="stat-label">Pipeline total</div>
      <div class="stat-badge navy">📊</div>
    </div>
    <div class="stat-value"><?= $stats['total'] ?></div>
    <div class="stat-sub">opportunités actives</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-top">
      <div class="stat-label">Valeur pipeline</div>
      <div class="stat-badge gold">💼</div>
    </div>
    <div class="stat-value" style="font-size:1.25rem"><?= number_format($stats['pipeline_value'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA estimés</div>
  </div>
  <div class="stat-card green">
    <div class="stat-top">
      <div class="stat-label">Valeur gagnée</div>
      <div class="stat-badge green">🏆</div>
    </div>
    <div class="stat-value" style="font-size:1.25rem"><?= number_format($stats['won_value'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA convertis</div>
  </div>
  <div class="stat-card <?= $stats['conversion_rate'] >= 50 ? 'green' : 'gold' ?>">
    <div class="stat-top">
      <div class="stat-label">Taux conversion</div>
      <div class="stat-badge <?= $stats['conversion_rate'] >= 50 ? 'green' : 'gold' ?>">🎯</div>
    </div>
    <div class="stat-value"><?= $stats['conversion_rate'] ?>%</div>
    <div class="stat-sub"><?= $stats['gagne'] ?> gagné<?= $stats['gagne'] > 1 ? 's' : '' ?> / <?= $stats['gagne'] + $stats['perdu'] ?> clôturés</div>
  </div>
</div>

<!-- Kanban -->
<div class="kanban">
<?php foreach ($columns as $status => $col): ?>
  <?php
    $cards  = $byStatus[$status];
    $colTotal = array_sum(array_column($cards, 'estimated_amount'));
  ?>
  <div class="kanban-col">
    <div class="kanban-head" style="background:<?= $col['bg'] ?>; border-bottom:2px solid <?= $col['color'] ?>">
      <div>
        <div style="color:<?= $col['color'] ?>"><?= $col['icon'] ?> <?= $col['label'] ?></div>
        <div class="col-total" style="color:<?= $col['color'] ?>">
          <?= count($cards) ?> · <?= number_format($colTotal, 0, ',', ' ') ?> FCFA
        </div>
      </div>
    </div>
    <div class="kanban-body">
      <?php if (empty($cards)): ?>
        <div class="empty-col">Aucune</div>
      <?php endif; ?>
      <?php foreach ($cards as $opp): ?>
      <div class="opp-card">
        <div class="opp-title"><?= htmlspecialchars($opp['title']) ?></div>
        <?php if ($opp['client_name']): ?>
        <div class="opp-client">👤 <?= htmlspecialchars($opp['client_name']) ?></div>
        <?php endif; ?>
        <div class="opp-amount"><?= number_format((int)$opp['estimated_amount'], 0, ',', ' ') ?> FCFA</div>
        <?php if ($opp['expected_close']): ?>
        <div style="font-size:.68rem;color:var(--muted-light);margin-top:3px">
          📅 <?= date('d/m/Y', strtotime($opp['expected_close'])) ?>
        </div>
        <?php endif; ?>
        <?php if (\App\Auth\Auth::can('write')): ?>
        <div class="opp-actions">
          <a href="/pipeline/edit.php?id=<?= $opp['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier">✏️</a>
          <?php if ($opp['status'] === 'gagne' && !$opp['invoice_id']): ?>
          <form method="POST" action="/pipeline/convert.php" style="display:inline">
            <input type="hidden" name="id" value="<?= $opp['id'] ?>">
            <button type="submit" class="btn btn-success btn-sm" title="Convertir en facture">🧾 Facture</button>
          </form>
          <?php elseif ($opp['invoice_id']): ?>
          <a href="/invoice/edit.php?id=<?= $opp['invoice_id'] ?>" class="btn btn-secondary btn-sm" title="Voir la facture">🧾 Voir</a>
          <?php endif; ?>
          <button onclick="deleteOpp(<?= $opp['id'] ?>, '<?= htmlspecialchars(addslashes($opp['title'])) ?>')"
                  class="btn btn-danger btn-sm btn-icon" title="Supprimer">🗑️</button>
        </div>
        <!-- Quick status change -->
        <div style="margin-top:8px">
          <select class="status-select" data-id="<?= $opp['id'] ?>" style="width:100%;font-size:.7rem">
            <?php foreach ($columns as $s => $c): ?>
            <option value="<?= $s ?>" <?= $opp['status'] === $s ? 'selected' : '' ?>><?= $c['icon'] ?> <?= $c['label'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php elseif ($opp['invoice_id']): ?>
        <div class="opp-actions" style="margin-top:8px">
          <a href="/invoice/edit.php?id=<?= $opp['invoice_id'] ?>" class="btn btn-secondary btn-sm">🧾 Voir facture</a>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<form id="delete-form" method="POST" action="/pipeline/delete.php" style="display:none">
  <input type="hidden" name="id" id="delete-id">
</form>

<script>
document.querySelectorAll('.status-select').forEach(sel => {
  sel.addEventListener('change', function() {
    fetch('/pipeline/status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + this.dataset.id + '&status=' + encodeURIComponent(this.value)
    }).then(() => location.reload());
  });
});

function deleteOpp(id, title) {
  if (!confirm('Supprimer "' + title + '" ?')) return;
  document.getElementById('delete-id').value = id;
  document.getElementById('delete-form').submit();
}
</script>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
