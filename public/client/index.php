<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\ClientRepository;

$repo    = new ClientRepository();
$clients = $repo->all();

$flash = $_GET['created'] ?? false ? 'Client ajouté.' : (($_GET['updated'] ?? false) ? 'Client mis à jour.' : null);

$pageTitle     = 'Clients';
$currentPage   = 'clients';
$topbarActions = Auth::can('write')
    ? '<a href="/client/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouveau client</a>'
    : '';

require __DIR__ . '/../../templates/layout.php';
?>

<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <h2>Clients (<?= count($clients) ?>)</h2>
  </div>

  <?php if ($flash): ?>
  <div style="padding:10px 20px">
    <div class="alert" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:8px;padding:10px 14px;font-size:.83rem">
      <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash) ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($clients)): ?>
  <div style="padding:48px;text-align:center;color:var(--muted)">
    <div style="font-size:2rem;margin-bottom:12px">👥</div>
    <div style="font-weight:600;margin-bottom:6px">Aucun client enregistré</div>
    <?php if (Auth::can('write')): ?>
    <a href="/client/create.php" class="btn btn-primary" style="margin-top:10px"><i class="fa-solid fa-plus"></i> Ajouter un client</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Nom</th>
          <th>Contact</th>
          <th>Email</th>
          <th>Téléphone</th>
          <th>Adresse</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($clients as $c): ?>
      <tr>
        <td>
          <div style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($c['name']) ?></div>
          <?php if ($c['notes']): ?>
          <div style="font-size:.7rem;color:var(--muted)"><?= htmlspecialchars(mb_substr($c['notes'], 0, 60)) ?><?= mb_strlen($c['notes']) > 60 ? '…' : '' ?></div>
          <?php endif; ?>
        </td>
        <td style="font-size:.83rem"><?= htmlspecialchars($c['contact'] ?? '—') ?></td>
        <td style="font-size:.83rem;color:var(--muted)"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
        <td style="font-size:.83rem;color:var(--muted)"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
        <td style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars(mb_substr($c['address'] ?? '', 0, 40)) ?><?= mb_strlen($c['address'] ?? '') > 40 ? '…' : '' ?></td>
        <td style="white-space:nowrap">
          <?php if (Auth::can('write')): ?>
          <a href="/client/edit.php?id=<?= $c['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
          <form method="POST" action="/client/delete.php" style="display:inline" onsubmit="return confirm('Supprimer <?= htmlspecialchars(addslashes($c['name'])) ?> ?')">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm btn-icon"><i class="fa-solid fa-trash"></i></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
