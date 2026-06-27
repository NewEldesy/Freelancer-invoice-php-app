<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\InvoiceRepository;

$repo         = new InvoiceRepository();
$filterStatus = $_GET['status'] ?? '';
$devisStatuses = ['brouillon', 'envoyé', 'accepté', 'refusé'];

if ($filterStatus !== '' && in_array($filterStatus, $devisStatuses, true)) {
    $all = $repo->allDevisByStatus($filterStatus);
} else {
    $filterStatus = '';
    $all = $repo->allDevis();
}

$statusConfig = [
    'brouillon' => ['label' => 'Brouillon', 'class' => 'badge-draft'],
    'envoyé'    => ['label' => 'Envoyé',    'class' => 'badge-sent'],
    'accepté'   => ['label' => 'Accepté',   'class' => 'badge-paid'],
    'refusé'    => ['label' => 'Refusé',    'class' => 'badge-cancelled'],
];

$filters = [
    '' => 'Tous',
    'brouillon' => 'Brouillons',
    'envoyé'    => 'Envoyés',
    'accepté'   => 'Acceptés',
    'refusé'    => 'Refusés',
];

// Batch load converted invoices — avoids N+1 in the table loop
$devisIds    = array_column($all, 'id');
$convertedMap = $repo->convertedInvoicesByDevisIds(array_map('intval', $devisIds));

$pageTitle     = 'Devis';
$currentPage   = 'devis';
$topbarActions = Auth::can('write')
    ? '<a href="/devis/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouveau devis</a>'
    : '';

require __DIR__ . '/../../templates/layout.php';
?>

<!-- Filtres -->
<div style="display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap">
  <?php foreach ($filters as $val => $label): $active = $filterStatus === $val; ?>
  <a href="?status=<?= urlencode($val) ?>"
     style="display:inline-flex;align-items:center;padding:5px 13px;border-radius:20px;font-size:.78rem;font-weight:600;text-decoration:none;border:1px solid;transition:all .15s;
       <?= $active ? 'background:var(--navy);color:#fff;border-color:var(--navy);' : 'background:var(--white);color:var(--muted);border-color:var(--border);' ?>"
  ><?= $label ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (empty($all)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fa-solid fa-file-circle-question"></i></div>
    <h3>Aucun devis</h3>
    <p>Créez votre premier devis pour l'envoyer à un client.</p>
    <?php if (Auth::can('write')): ?>
    <a href="/devis/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouveau devis</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>N° Devis</th>
          <th>Client</th>
          <th>Objet</th>
          <th>Émission</th>
          <th>Validité</th>
          <th style="text-align:right">Montant TTC</th>
          <th>Statut</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($all as $d):
        $s = $statusConfig[$d['status']] ?? ['label' => $d['status'], 'class' => 'badge-draft'];
        $converted = $convertedMap[(int) $d['id']] ?? null;
      ?>
      <tr>
        <td>
          <a href="/devis/edit.php?id=<?= $d['id'] ?>"
             style="font-weight:600;color:var(--navy);text-decoration:none;font-size:.83rem">
            <?= htmlspecialchars($d['number']) ?>
          </a>
          <?php if ($converted): ?>
          <div style="font-size:.68rem;color:#059669;font-weight:600;margin-top:2px">
            <i class="fa-solid fa-circle-check"></i> Converti
          </div>
          <?php endif; ?>
        </td>
        <td>
          <div style="font-weight:500"><?= htmlspecialchars($d['client_name'] ?: '—') ?></div>
          <?php if ($d['client_contact']): ?>
          <div style="font-size:.72rem;color:var(--muted-light)"><?= htmlspecialchars($d['client_contact']) ?></div>
          <?php endif; ?>
        </td>
        <td style="font-size:.8rem;color:var(--muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= htmlspecialchars($d['subject'] ?: '—') ?>
        </td>
        <td style="color:var(--muted);font-size:.8rem"><?= $d['issued_at'] ? date('d/m/Y', strtotime($d['issued_at'])) : '—' ?></td>
        <td style="color:var(--muted);font-size:.8rem"><?= $d['due_at'] ? date('d/m/Y', strtotime($d['due_at'])) : '—' ?></td>
        <td style="text-align:right;font-weight:600;font-size:.84rem">
          <?= number_format((int)$d['total_net'], 0, ',', ' ') ?>
          <span style="font-size:.7rem;color:var(--muted);font-weight:400">FCFA</span>
        </td>
        <td><span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span></td>
        <td style="text-align:right">
          <div style="display:flex;gap:4px;justify-content:flex-end">
            <a href="/devis/edit.php?id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier">
              <i class="fa-solid fa-pen-to-square"></i>
            </a>
            <a href="/devis/pdf.php?id=<?= $d['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="PDF" target="_blank">
              <i class="fa-solid fa-file-pdf"></i>
            </a>
            <?php if (Auth::can('write') && !$converted): ?>
            <?php if ($d['status'] === 'accepté'): ?>
            <form method="POST" action="/devis/convert.php" style="display:inline">
              <input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button type="submit" class="btn btn-success btn-sm" title="Convertir en facture">
                <i class="fa-solid fa-file-invoice"></i>
              </button>
            </form>
            <?php endif; ?>
            <form method="POST" action="/devis/delete.php" style="display:inline"
                  onsubmit="return confirm('Supprimer ce devis ?')">
              <input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Supprimer">
                <i class="fa-solid fa-trash"></i>
              </button>
            </form>
            <?php elseif ($converted): ?>
            <a href="/invoice/edit.php?id=<?= $converted['id'] ?>" class="btn btn-secondary btn-sm" title="Voir la facture">
              <i class="fa-solid fa-eye"></i>
            </a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
