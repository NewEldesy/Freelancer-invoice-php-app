<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\InvoiceRepository;

$repo         = new InvoiceRepository();
$filterStatus = $_GET['status'] ?? '';
$allowed      = ['brouillon', 'envoyée', 'payée', 'annulée'];

if ($filterStatus === 'retard') {
    $all = $repo->overdue();
} elseif ($filterStatus !== '' && in_array($filterStatus, $allowed, true)) {
    $all = $repo->allByStatus($filterStatus);
} else {
    $all = $repo->all();
}

$overdueStats = $repo->overdueStats();

$statusConfig = [
    'brouillon' => ['label' => 'Brouillon', 'class' => 'badge-draft'],
    'envoyée'   => ['label' => 'Envoyée',   'class' => 'badge-sent'],
    'payée'     => ['label' => 'Payée',      'class' => 'badge-paid'],
    'annulée'   => ['label' => 'Annulée',    'class' => 'badge-cancelled'],
];

$filters = ['' => 'Toutes', 'brouillon' => 'Brouillons', 'envoyée' => 'Envoyées', 'payée' => 'Payées', 'annulée' => 'Annulées', 'retard' => '<i class="fa-solid fa-triangle-exclamation"></i> En retard'];

$pageTitle   = 'Factures';
$currentPage = 'list';
$topbarActions = Auth::can('write') ? '<a href="/invoice/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle facture</a>' : '';

require __DIR__ . '/../../templates/layout.php';
?>

<?php if ($overdueStats['count'] > 0 && $filterStatus !== 'retard'): ?>
<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 16px;margin-bottom:14px;font-size:.82rem;color:#92400e;display:flex;justify-content:space-between;align-items:center">
  <span><i class="fa-solid fa-triangle-exclamation"></i> <strong><?= $overdueStats['count'] ?> facture<?= $overdueStats['count'] > 1 ? 's' : '' ?> en retard</strong> — <?= number_format($overdueStats['total'], 0, ',', ' ') ?> FCFA à relancer</span>
  <a href="?status=retard" style="font-size:.78rem;font-weight:600;color:#92400e;text-decoration:underline">Voir</a>
</div>
<?php endif; ?>

<!-- Filtres -->
<div style="display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap">
  <?php foreach ($filters as $val => $label):
    $active = $filterStatus === $val;
    $isRetard = $val === 'retard';
  ?>
  <a href="?status=<?= urlencode($val) ?>"
     style="display:inline-flex;align-items:center;padding:5px 13px;border-radius:20px;font-size:.78rem;font-weight:600;text-decoration:none;border:1px solid;transition:all .15s;
       <?= $active
         ? ($isRetard ? 'background:#92400e;color:#fff;border-color:#92400e;' : 'background:var(--navy);color:#fff;border-color:var(--navy);')
         : ($isRetard && $overdueStats['count'] > 0 ? 'background:#fff7ed;color:#92400e;border-color:#fed7aa;' : 'background:var(--white);color:var(--muted);border-color:var(--border);') ?>"
  ><?= $label ?><?= ($isRetard && $overdueStats['count'] > 0) ? ' (' . $overdueStats['count'] . ')' : '' ?></a>
  <?php endforeach; ?>
</div>

<div class="card">
  <?php if (empty($all)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
    <h3>Aucune facture trouvée</h3>
    <p>Essayez un autre filtre ou créez une nouvelle facture.</p>
    <a href="/invoice/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle facture</a>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>N° Facture</th>
          <th>Type</th>
          <th>Client</th>
          <th>Émission</th>
          <th>Échéance</th>
          <th style="text-align:right">H.T</th>
          <th style="text-align:right">Net à Payer</th>
          <th>Statut</th>
          <th style="text-align:right">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($all as $inv):
        $s = $statusConfig[$inv['status']] ?? ['label' => $inv['status'], 'class' => 'badge-draft'];
        $rowOverdue = $inv['status'] === 'envoyée'
            && !empty($inv['due_at'])
            && $inv['due_at'] < date('Y-m-d');
        ?>
        <tr <?= $rowOverdue ? 'style="background:#fff7ed"' : '' ?>>
          <td>
            <a href="/invoice/edit.php?id=<?= $inv['id'] ?>"
               style="font-weight:600;color:var(--navy);text-decoration:none;font-size:.83rem">
              <?= htmlspecialchars($inv['number']) ?>
            </a>
            <?php if ($rowOverdue): ?>
            <div style="font-size:.68rem;color:#b45309;font-weight:600;margin-top:2px"><i class="fa-solid fa-triangle-exclamation"></i> En retard</div>
            <?php endif; ?>
          </td>
          <td>
            <span style="font-size:.73rem;color:var(--muted);background:var(--bg);padding:2px 7px;border-radius:4px;border:1px solid var(--border)">
              <?= htmlspecialchars($inv['type']) ?>
            </span>
          </td>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($inv['client_name'] ?: '—') ?></div>
            <?php if ($inv['client_contact']): ?>
            <div style="font-size:.72rem;color:var(--muted-light)"><?= htmlspecialchars($inv['client_contact']) ?></div>
            <?php endif; ?>
          </td>
          <td style="color:var(--muted);font-size:.8rem"><?= $inv['issued_at'] ? date('d/m/Y', strtotime($inv['issued_at'])) : '—' ?></td>
          <td style="color:var(--muted);font-size:.8rem"><?= $inv['due_at'] ? date('d/m/Y', strtotime($inv['due_at'])) : '—' ?></td>
          <td style="text-align:right;font-size:.8rem;color:var(--muted)"><?= number_format((int)$inv['total_ht'], 0, ',', ' ') ?></td>
          <td style="text-align:right;font-weight:600;font-size:.84rem">
            <?= number_format((int)$inv['total_net'], 0, ',', ' ') ?>
            <span style="font-size:.68rem;color:var(--muted);font-weight:400"> FCFA</span>
          </td>
          <td>
            <select class="status-select" data-id="<?= $inv['id'] ?>" <?= Auth::can('write') ? '' : 'disabled title="Lecture seule"' ?>>
              <?php foreach (['brouillon'=>'Brouillon','envoyée'=>'Envoyée','payée'=>'Payée','annulée'=>'Annulée'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= $inv['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <div style="display:flex;gap:4px;justify-content:flex-end">
              <?php if (Auth::can('write')): ?>
              <a href="/invoice/edit.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier"><i class="fa-solid fa-pen-to-square"></i></a>
              <?php endif; ?>
              <a href="/invoice/pdf.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="PDF" target="_blank"><i class="fa-solid fa-file-pdf"></i></a>
              <?php if (Auth::can('write')): ?>
              <form method="POST" action="/invoice/duplicate.php" style="display:inline">
                <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm btn-icon" title="Dupliquer"><i class="fa-solid fa-clone"></i></button>
              </form>
              <button onclick="deleteInvoice(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['number']) ?>')"
                class="btn btn-danger btn-sm btn-icon" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
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

<form id="delete-form" method="POST" action="/invoice/delete.php" style="display:none">
  <input type="hidden" name="id" id="delete-id">
</form>

<script>
document.querySelectorAll('.status-select').forEach(sel => {
  sel.addEventListener('change', function() {
    fetch('/invoice/status.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + this.dataset.id + '&status=' + encodeURIComponent(this.value)
    });
  });
});

function deleteInvoice(id, number) {
  if (!confirm('Supprimer la facture ' + number + ' ?')) return;
  document.getElementById('delete-id').value = id;
  document.getElementById('delete-form').submit();
}
</script>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
