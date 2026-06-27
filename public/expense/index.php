<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\ExpenseRepository;
use App\Database\InvoiceRepository;

$expRepo  = new ExpenseRepository();
$invRepo  = new InvoiceRepository();

$filterInvoice = (int) ($_GET['invoice_id'] ?? 0);
$expenses = $filterInvoice > 0
    ? $expRepo->allForInvoice($filterInvoice)
    : $expRepo->all();

$gStats   = $expRepo->globalStats();
$invoices = array_filter($invRepo->all(), fn($inv) => in_array($inv['status'], ['envoyée', 'payée'], true));

$pageTitle   = 'Dépenses';
$currentPage = 'expenses';
$addUrl      = $filterInvoice > 0
    ? '/expense/create.php?invoice_id=' . $filterInvoice
    : '/expense/create.php';
$topbarActions = Auth::can('write') ? '<a href="' . $addUrl . '" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle dépense</a>' : '';

require __DIR__ . '/../../templates/layout.php';
?>

<!-- Stats globales -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:22px">
  <div class="stat-card navy">
    <div class="stat-top"><div class="stat-label">CA Engagé</div><div class="stat-badge navy"><i class="fa-solid fa-envelope-open-text"></i></div></div>
    <div class="stat-value" style="font-size:1.2rem"><?= number_format($gStats['ca_engage'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA · envoyées + payées</div>
  </div>
  <div class="stat-card green">
    <div class="stat-top"><div class="stat-label">CA Encaissé</div><div class="stat-badge green"><i class="fa-solid fa-money-bill-wave"></i></div></div>
    <div class="stat-value" style="font-size:1.2rem"><?= number_format($gStats['ca_encaisse'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA · factures payées</div>
  </div>
  <div class="stat-card red">
    <div class="stat-top"><div class="stat-label">Total dépenses</div><div class="stat-badge red"><i class="fa-solid fa-arrow-trend-down"></i></div></div>
    <div class="stat-value" style="font-size:1.2rem"><?= number_format($gStats['total_depenses'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA engagés</div>
  </div>
  <div class="stat-card <?= $gStats['benefice_net'] >= 0 ? 'green' : 'red' ?>">
    <div class="stat-top">
      <div class="stat-label">Bénéfice net</div>
      <div class="stat-badge <?= $gStats['benefice_net'] >= 0 ? 'green' : 'red' ?>"><i class="fa-solid <?= $gStats['benefice_net'] >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i></div>
    </div>
    <div class="stat-value" style="font-size:1.2rem;color:<?= $gStats['benefice_net'] >= 0 ? 'var(--green)' : 'var(--red)' ?>">
      <?= number_format($gStats['benefice_net'], 0, ',', ' ') ?>
    </div>
    <div class="stat-sub">CA engagé − dépenses</div>
  </div>
</div>

<!-- Barre de recherche -->
<div style="margin-bottom:14px">
  <div style="position:relative;max-width:420px">
    <span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--muted-light);font-size:14px"><i class="fa-solid fa-magnifying-glass"></i></span>
    <input type="text" id="exp-search" placeholder="Rechercher une dépense, catégorie, facture…"
           style="width:100%;padding:8px 11px 8px 34px;border:1px solid var(--border);border-radius:8px;
                  font-size:.83rem;font-family:inherit;color:var(--text);background:var(--white);outline:none;
                  transition:border .15s,box-shadow .15s"
           onfocus="this.style.borderColor='var(--gold)';this.style.boxShadow='0 0 0 3px rgba(245,158,11,.12)'"
           onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
    <span id="search-count" style="position:absolute;right:11px;top:50%;transform:translateY(-50%);font-size:.72rem;color:var(--muted-light)"></span>
  </div>
</div>

<!-- Répartition par catégorie + filtre -->
<div style="display:grid;grid-template-columns:1fr 260px;gap:14px;margin-bottom:18px;align-items:start">

  <!-- Filtre par facture -->
  <div style="display:flex;align-items:center;gap:10px">
    <label style="font-size:.78rem;font-weight:600;color:var(--muted);white-space:nowrap">Filtrer par facture</label>
    <select id="invoice-filter" onchange="location.href='/expense/index.php'+(this.value?'?invoice_id='+this.value:'')"
            style="border:1px solid var(--border);border-radius:8px;padding:7px 32px 7px 11px;font-size:.82rem;
                   font-family:inherit;color:var(--text);background:var(--white);cursor:pointer;
                   appearance:none;background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2394a3b8'/%3E%3C/svg%3E\");
                   background-repeat:no-repeat;background-position:right 10px center;min-width:220px">
      <option value="">— Toutes les factures —</option>
      <?php foreach ($invoices as $inv): ?>
      <option value="<?= $inv['id'] ?>" <?= $filterInvoice === (int)$inv['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($inv['number']) ?><?= $inv['client_name'] ? ' · ' . htmlspecialchars($inv['client_name']) : '' ?>
      </option>
      <?php endforeach; ?>
    </select>
    <?php if ($filterInvoice > 0): ?>
    <a href="/expense/index.php" style="font-size:.78rem;color:var(--muted);text-decoration:none">✕ Réinitialiser</a>
    <?php endif; ?>
  </div>

  <!-- Répartition -->
  <div class="card" style="padding:12px 16px">
    <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;color:var(--muted);margin-bottom:10px">Par catégorie</div>
    <?php foreach (ExpenseRepository::CATEGORIES as $key => $label):
      $amount = $gStats['by_category'][$key] ?? 0;
      $pct    = $gStats['total_depenses'] > 0 ? round($amount / $gStats['total_depenses'] * 100) : 0;
    ?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:.75rem;margin-bottom:3px">
        <span><?= $label ?></span>
        <span style="color:var(--muted)"><?= number_format($amount, 0, ',', ' ') ?> FCFA</span>
      </div>
      <div style="height:4px;background:var(--border-soft);border-radius:99px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:var(--navy);border-radius:99px"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Table dépenses -->
<div class="card">
  <?php if (empty($expenses)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fa-solid fa-receipt"></i></div>
    <h3>Aucune dépense</h3>
    <p>Enregistrez vos coûts pour calculer votre bénéfice net par projet.</p>
    <a href="<?= $addUrl ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle dépense</a>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Description</th>
          <th>Catégorie</th>
          <th>Facture</th>
          <th>Date</th>
          <th style="text-align:right">Montant</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($expenses as $exp): ?>
      <tr>
        <td style="font-weight:500"><?= htmlspecialchars($exp['description']) ?></td>
        <td>
          <span style="font-size:.75rem;padding:3px 9px;border-radius:20px;background:var(--bg);border:1px solid var(--border);color:var(--muted)">
            <?= htmlspecialchars(ExpenseRepository::CATEGORIES[$exp['category']] ?? $exp['category']) ?>
          </span>
        </td>
        <td style="font-size:.8rem">
          <?php if ($exp['invoice_id']): ?>
          <a href="/invoice/edit.php?id=<?= $exp['invoice_id'] ?>"
             style="color:var(--navy);text-decoration:none;font-weight:600">
            <?= htmlspecialchars($exp['invoice_number'] ?? '') ?>
          </a>
          <div style="font-size:.7rem;color:var(--muted)"><?= htmlspecialchars($exp['client_name'] ?? '') ?></div>
          <?php else: ?>
          <span style="color:var(--muted-light)">—</span>
          <?php endif; ?>
        </td>
        <td style="color:var(--muted);font-size:.8rem"><?= $exp['date'] ? date('d/m/Y', strtotime($exp['date'])) : '—' ?></td>
        <td style="text-align:right;font-weight:700;color:var(--red)">
          <?= number_format((int)$exp['amount'], 0, ',', ' ') ?> <span style="font-size:.7rem;font-weight:400;color:var(--muted)">FCFA</span>
        </td>
        <td>
          <?php if (Auth::can('write')): ?>
          <form method="POST" action="/expense/delete.php" style="display:inline">
            <input type="hidden" name="id" value="<?= $exp['id'] ?>">
            <input type="hidden" name="back" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
            <button type="submit" class="btn btn-danger btn-sm btn-icon"
                    onclick="return confirm('Supprimer cette dépense ?')" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
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

<script>
const searchInput  = document.getElementById('exp-search');
const searchCount  = document.getElementById('search-count');
const tbody        = document.querySelector('.data-table tbody');

if (searchInput && tbody) {
  searchInput.addEventListener('input', function () {
    const q    = this.value.toLowerCase().trim();
    const rows = tbody.querySelectorAll('tr');
    let visible = 0;

    rows.forEach(tr => {
      const text = tr.textContent.toLowerCase();
      const show = q === '' || text.includes(q);
      tr.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    searchCount.textContent = q ? visible + ' résultat' + (visible > 1 ? 's' : '') : '';
  });
}
</script>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
