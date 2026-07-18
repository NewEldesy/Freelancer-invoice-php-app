<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\RecurringRepository;

$repo      = new RecurringRepository();
$templates = $repo->all();

$intervalLabels = [
    'hebdomadaire' => 'Hebdomadaire',
    'mensuel'      => 'Mensuel',
    'trimestriel'  => 'Trimestriel',
    'annuel'       => 'Annuel',
];

$pageTitle   = 'Factures récurrentes';
$currentPage = 'recurring';

$flashSuccess = !empty($_GET['deleted'])   ? 'Modèle supprimé.'             : null;
$flashSuccess = !empty($_GET['toggled'])   ? 'Statut mis à jour.'           : $flashSuccess;
$flashSuccess = !empty($_GET['generated']) ? 'Facture générée avec succès !' : $flashSuccess;

require_once __DIR__ . '/../../templates/layout.php';
?>

<style>
.rec-grid { display: grid; gap: 14px; }
.rec-card {
  background: var(--white); border-radius: var(--radius);
  border: 1px solid var(--border); padding: 18px 20px;
  display: flex; align-items: center; gap: 16px;
  box-shadow: var(--shadow-sm); transition: box-shadow .15s;
}
.rec-card:hover { box-shadow: var(--shadow); }
.rec-card.inactive { opacity: .55; }
.rec-icon {
  width: 44px; height: 44px; border-radius: 10px;
  background: var(--gold-dim); color: var(--gold);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; flex-shrink: 0;
}
.rec-body { flex: 1; min-width: 0; }
.rec-label { font-size: .9rem; font-weight: 700; color: var(--navy); }
.rec-sub   { font-size: .77rem; color: var(--muted); margin-top: 3px; }
.rec-next  { font-size: .75rem; font-weight: 600; }
.rec-next.due    { color: var(--red); }
.rec-next.coming { color: var(--orange); }
.rec-next.ok     { color: var(--green); }
.rec-actions { display: flex; gap: 6px; align-items: center; }
.badge-interval {
  font-size: .68rem; font-weight: 600; padding: 2px 9px; border-radius: 99px;
  background: var(--blue-bg); color: var(--blue); border: 1px solid #bfdbfe;
}
</style>

<?php if (Auth::can('write')): ?>
<div style="margin-bottom:20px">
  <a href="/recurring/create.php" class="btn btn-primary">
    <i class="fa-solid fa-plus"></i> Nouveau modèle récurrent
  </a>
</div>
<?php endif; ?>

<?php if (empty($templates)): ?>
<div class="card" style="padding:48px;text-align:center;color:var(--muted)">
  <i class="fa-solid fa-arrows-rotate" style="font-size:2.5rem;opacity:.25;display:block;margin-bottom:14px"></i>
  Aucun modèle de facture récurrente.<br>
  <?php if (Auth::can('write')): ?>
  <a href="/recurring/create.php" class="btn btn-primary" style="margin-top:16px">Créer le premier</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="rec-grid">
<?php
$today = date('Y-m-d');
foreach ($templates as $t):
    $daysLeft = (int) round((strtotime($t['next_due_at']) - strtotime($today)) / 86400);
    $nextClass = $daysLeft < 0 ? 'due' : ($daysLeft <= 7 ? 'coming' : 'ok');
    $nextLabel = $daysLeft < 0
        ? 'En retard de ' . abs($daysLeft) . ' j'
        : ($daysLeft === 0 ? 'Génération aujourd\'hui' : 'Dans ' . $daysLeft . ' j — ' . $t['next_due_at']);
?>
<div class="rec-card <?= $t['active'] ? '' : 'inactive' ?>">
  <div class="rec-icon"><i class="fa-solid fa-arrows-rotate"></i></div>
  <div class="rec-body">
    <div class="rec-label"><?= htmlspecialchars($t['label']) ?></div>
    <div class="rec-sub">
      <?= htmlspecialchars($t['client_name']) ?>
      <?php if ($t['subject']): ?> · <?= htmlspecialchars($t['subject']) ?><?php endif; ?>
    </div>
    <div class="rec-next <?= $nextClass ?>" style="margin-top:5px">
      <i class="fa-solid fa-calendar-day"></i>
      <?= htmlspecialchars($nextLabel) ?>
    </div>
  </div>
  <div style="text-align:center">
    <span class="badge-interval"><?= $intervalLabels[$t['interval_type']] ?? $t['interval_type'] ?></span>
    <?php if (!$t['active']): ?>
    <div style="font-size:.68rem;color:var(--muted);margin-top:4px">En pause</div>
    <?php endif; ?>
  </div>
  <div class="rec-actions">
    <?php if (Auth::can('write') && $t['active'] && $daysLeft <= 0): ?>
    <form method="POST" action="/recurring/generate.php" style="display:inline">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= $t['id'] ?>">
      <button class="btn btn-success btn-sm" title="Générer la facture maintenant">
        <i class="fa-solid fa-bolt"></i> Générer
      </button>
    </form>
    <?php endif; ?>
    <?php if (Auth::can('write')): ?>
    <a href="/recurring/edit.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier">
      <i class="fa-solid fa-pen"></i>
    </a>
    <form method="POST" action="/recurring/toggle.php" style="display:inline">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= $t['id'] ?>">
      <button class="btn btn-secondary btn-sm btn-icon" title="<?= $t['active'] ? 'Mettre en pause' : 'Réactiver' ?>">
        <i class="fa-solid fa-<?= $t['active'] ? 'pause' : 'play' ?>"></i>
      </button>
    </form>
    <form method="POST" action="/recurring/delete.php" style="display:inline"
          onsubmit="return confirm('Supprimer ce modèle ?')">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= $t['id'] ?>">
      <button class="btn btn-danger btn-sm btn-icon" title="Supprimer">
        <i class="fa-solid fa-trash"></i>
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../templates/layout_end.php'; ?>
