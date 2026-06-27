<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\ServiceRepository;

$repo    = new ServiceRepository();
$grouped = $repo->allGrouped();
$flash   = ($_GET['created'] ?? false) ? 'Prestation ajoutée.' : (($_GET['updated'] ?? false) ? 'Prestation mise à jour.' : null);

$pageTitle     = 'Catalogue de prestations';
$currentPage   = 'services';
$topbarActions = Auth::can('write')
    ? '<a href="/services/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle prestation</a>'
    : '';

require __DIR__ . '/../../templates/layout.php';
?>

<?php if ($flash): ?>
<div class="alert" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:8px;padding:10px 14px;font-size:.83rem;margin-bottom:16px">
  <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<?php if (empty($grouped)): ?>
<div class="card" style="padding:48px;text-align:center;color:var(--muted)">
  <div style="font-size:2rem;margin-bottom:12px"><i class="fa-solid fa-box-open"></i></div>
  <div style="font-weight:600;margin-bottom:6px">Aucune prestation dans le catalogue</div>
  <?php if (Auth::can('write')): ?>
  <a href="/services/create.php" class="btn btn-primary" style="margin-top:10px"><i class="fa-solid fa-plus"></i> Ajouter une prestation</a>
  <?php endif; ?>
</div>
<?php else: ?>

<?php foreach ($grouped as $cat => $services): ?>
<div class="card" style="margin-bottom:16px">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <h2><?= htmlspecialchars(ServiceRepository::CATEGORIES[$cat] ?? $cat) ?> <span style="font-size:.75rem;color:var(--muted);font-weight:400">(<?= count($services) ?>)</span></h2>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Nom</th>
          <th>Description</th>
          <th style="text-align:right">Prix unitaire</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($services as $s): ?>
      <tr>
        <td style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($s['name']) ?></td>
        <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($s['description']) ?></td>
        <td style="text-align:right;font-weight:700"><?= number_format((int)$s['unit_price'], 0, ',', ' ') ?> FCFA</td>
        <td style="white-space:nowrap">
          <?php if (Auth::can('write')): ?>
          <a href="/services/edit.php?id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
          <form method="POST" action="/services/delete.php" style="display:inline" onsubmit="return confirm('Supprimer cette prestation ?')">
            <input type="hidden" name="id" value="<?= $s['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
