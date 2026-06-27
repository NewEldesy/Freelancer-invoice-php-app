<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Services\LicenseService;
if (!LicenseService::canAccounting()) {
    header('Location: /index.php?license_limit=accounting'); exit;
}

use App\Database\InvoiceRepository;
use App\Database\ExpenseRepository;

$invRepo = new InvoiceRepository();
$expRepo = new ExpenseRepository();

$availableYears = $invRepo->availableYears();
$currentYear    = (int) ($_GET['year'] ?? date('Y'));
if (!in_array($currentYear, $availableYears, true)) {
    $availableYears[] = $currentYear;
    sort($availableYears);
    $availableYears = array_reverse($availableYears);
}

$invoiceMonths = $invRepo->statsByMonth($currentYear);
$expenseMonths = $expRepo->statsByMonth($currentYear);

// Merge into unified monthly rows
$months = [];
$MONTHS_FR = [
    1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
    7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre',
];
for ($m = 1; $m <= 12; $m++) {
    $caEngage   = $invoiceMonths[$m]['ca_engage']   ?? 0;
    $caEncaisse = $invoiceMonths[$m]['ca_encaisse'] ?? 0;
    $depenses   = $expenseMonths[$m]['total']       ?? 0;
    $months[$m] = [
        'label'       => $MONTHS_FR[$m],
        'ca_engage'   => $caEngage,
        'ca_encaisse' => $caEncaisse,
        'depenses'    => $depenses,
        'benefice'    => $caEngage - $depenses,
        'nb_envoyee'  => $invoiceMonths[$m]['nb_envoyee'] ?? 0,
        'nb_payee'    => $invoiceMonths[$m]['nb_payee']   ?? 0,
    ];
}

// Annual totals
$totalCaEngage   = array_sum(array_column($months, 'ca_engage'));
$totalCaEncaisse = array_sum(array_column($months, 'ca_encaisse'));
$totalDepenses   = array_sum(array_column($months, 'depenses'));
$totalBenefice   = $totalCaEngage - $totalDepenses;
$totalNbEnvoyee  = array_sum(array_column($months, 'nb_envoyee'));
$totalNbPayee    = array_sum(array_column($months, 'nb_payee'));

// Best / worst active month
$activeMonths = array_filter($months, fn($m) => $m['ca_engage'] > 0);
$bestMonth    = !empty($activeMonths) ? array_reduce($activeMonths, fn($carry, $m) => ($m['ca_engage'] > ($carry['ca_engage'] ?? 0)) ? $m : $carry) : null;
$margeGlobal  = $totalCaEngage > 0 ? round($totalBenefice / $totalCaEngage * 100, 1) : 0;

$pageTitle   = 'Comptabilité ' . $currentYear;
$currentPage = 'accounting';
$topbarActions = '
  <a href="/accounting/report.php?year=' . $currentYear . '" class="btn btn-secondary">📋 Rapport annuel</a>
  <a href="/accounting/export.php?type=monthly&year=' . $currentYear . '" class="btn btn-secondary">📊 Exporter Excel</a>
  <button onclick="window.print()" class="btn btn-secondary" id="print-btn">🖨️ Imprimer / PDF</button>
';

require __DIR__ . '/../../templates/layout.php';
?>

<style>
/* ── Screen styles ── */
.year-nav { display:flex; align-items:center; gap:8px; margin-bottom:22px; flex-wrap:wrap; }
.year-btn  {
  padding:6px 16px; border-radius:8px; border:1px solid var(--border);
  background:var(--white); color:var(--muted); font-size:.82rem; font-weight:600;
  text-decoration:none; transition:all .15s;
}
.year-btn:hover  { background:var(--bg); border-color:#94a3b8; color:var(--text); }
.year-btn.active { background:var(--navy); color:#fff; border-color:var(--navy); }

.acc-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:22px; }

.monthly-table { width:100%; border-collapse:collapse; font-size:.82rem; }
.monthly-table th {
  padding:9px 14px; font-size:.68rem; text-transform:uppercase; letter-spacing:.5px;
  color:var(--muted); border-bottom:1px solid var(--border); background:#fafbfc;
  font-weight:600; text-align:right;
}
.monthly-table th:first-child { text-align:left; }
.monthly-table td {
  padding:11px 14px; border-bottom:1px solid var(--border-soft);
  vertical-align:middle; text-align:right;
}
.monthly-table td:first-child { text-align:left; font-weight:600; color:var(--navy); }
.monthly-table tr.total-row td {
  font-weight:700; background:#f8fafc; border-top:2px solid var(--border);
  border-bottom:none; font-size:.84rem;
}
.monthly-table tbody tr:hover td { background:#f8fafc; }
.monthly-table .zero { color:var(--muted-light); }
.benefice-pos { color:#059669; font-weight:700; }
.benefice-neg { color:#dc2626; font-weight:700; }

.chart-wrap { padding:20px; height:280px; }

/* ── Print styles ── */
@media print {
  @page { size: A4 landscape; margin: 15mm 12mm; }

  body { background:#fff !important; font-size:11px !important; }

  /* Hide everything except the report */
  .sidebar, .topbar, #print-btn, .year-nav, .no-print { display:none !important; }
  .main { margin-left:0 !important; }
  .content { padding:0 !important; }

  /* Page header */
  .print-header { display:block !important; }

  /* Cards: 2 per row in print */
  .acc-grid { grid-template-columns:repeat(4,1fr) !important; gap:8px !important; margin-bottom:14px !important; }
  .stat-card { box-shadow:none !important; border:1px solid #ccc !important; padding:10px 12px !important; }
  .stat-value { font-size:1.1rem !important; }

  /* Chart: hidden in print (replaced by table) */
  .chart-section { display:none !important; }

  /* Table */
  .monthly-table th, .monthly-table td { padding:6px 10px !important; font-size:10px !important; }
  .card { box-shadow:none !important; border:1px solid #ccc !important; break-inside:avoid; }

  /* Force page break between sections if needed */
  .print-break { page-break-before:always; }
}
</style>

<!-- Print-only header (hidden on screen) -->
<div class="print-header" style="display:none; margin-bottom:18px; border-bottom:2px solid #0f172a; padding-bottom:12px;">
  <div style="display:flex; justify-content:space-between; align-items:flex-end;">
    <div>
      <div style="font-size:18px; font-weight:800; color:#0f172a">Rapport comptable — <?= $currentYear ?></div>
      <div style="font-size:11px; color:#64748b; margin-top:3px">Généré le <?= date('d/m/Y à H:i') ?> · Invoices Project</div>
    </div>
    <div style="text-align:right; font-size:11px; color:#64748b">
      <div>CA Engagé : <strong><?= number_format($totalCaEngage, 0, ',', ' ') ?> FCFA</strong></div>
      <div>Bénéfice net : <strong style="color:<?= $totalBenefice >= 0 ? '#059669' : '#dc2626' ?>"><?= number_format($totalBenefice, 0, ',', ' ') ?> FCFA</strong></div>
    </div>
  </div>
</div>

<!-- Navigation années -->
<div class="year-nav no-print">
  <span style="font-size:.78rem;font-weight:600;color:var(--muted)">Année :</span>
  <?php foreach ($availableYears as $y): ?>
  <a href="/accounting/index.php?year=<?= $y ?>"
     class="year-btn <?= $y === $currentYear ? 'active' : '' ?>"><?= $y ?></a>
  <?php endforeach; ?>
  <span style="font-size:.75rem;color:var(--muted-light);margin-left:4px">— <?= count(array_filter($months, fn($m) => $m['ca_engage'] > 0)) ?> mois actifs</span>
</div>

<!-- Stat cards annuelles -->
<div class="acc-grid">
  <div class="stat-card navy">
    <div class="stat-top"><div class="stat-label">CA Engagé <?= $currentYear ?></div><div class="stat-badge navy">📬</div></div>
    <div class="stat-value" style="font-size:1.25rem"><?= number_format($totalCaEngage, 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA · <?= $totalNbEnvoyee + $totalNbPayee ?> factures actives</div>
  </div>
  <div class="stat-card green">
    <div class="stat-top"><div class="stat-label">CA Encaissé <?= $currentYear ?></div><div class="stat-badge green">💵</div></div>
    <div class="stat-value" style="font-size:1.25rem"><?= number_format($totalCaEncaisse, 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA · <?= $totalNbPayee ?> factures payées</div>
  </div>
  <div class="stat-card red">
    <div class="stat-top"><div class="stat-label">Total Dépenses</div><div class="stat-badge red">💸</div></div>
    <div class="stat-value" style="font-size:1.25rem"><?= number_format($totalDepenses, 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA engagés sur l'année</div>
  </div>
  <div class="stat-card <?= $totalBenefice >= 0 ? 'green' : 'red' ?>">
    <div class="stat-top">
      <div class="stat-label">Bénéfice net</div>
      <div class="stat-badge <?= $totalBenefice >= 0 ? 'green' : 'red' ?>">📈</div>
    </div>
    <div class="stat-value" style="font-size:1.25rem;color:<?= $totalBenefice >= 0 ? 'var(--green)' : 'var(--red)' ?>">
      <?= number_format($totalBenefice, 0, ',', ' ') ?>
    </div>
    <div class="stat-sub">Marge <?= $margeGlobal ?>%<?= $bestMonth ? ' · Pic : ' . $bestMonth['label'] : '' ?></div>
  </div>
</div>

<!-- Graphique mensuel -->
<div class="card chart-section no-print" style="margin-bottom:18px">
  <div class="card-header">
    <h2>Évolution mensuelle <?= $currentYear ?></h2>
    <div style="display:flex;gap:14px;font-size:.75rem;color:var(--muted)">
      <span><span style="display:inline-block;width:10px;height:10px;background:#0f172a;border-radius:2px;margin-right:4px"></span>CA Engagé</span>
      <span><span style="display:inline-block;width:10px;height:10px;background:#10b981;border-radius:2px;margin-right:4px"></span>CA Encaissé</span>
      <span><span style="display:inline-block;width:10px;height:10px;background:#ef4444;border-radius:2px;margin-right:4px"></span>Dépenses</span>
    </div>
  </div>
  <div class="chart-wrap">
    <canvas id="monthly-chart"></canvas>
  </div>
</div>

<!-- Tableau mensuel détaillé -->
<div class="card">
  <div class="card-header">
    <h2>Détail par mois — <?= $currentYear ?></h2>
    <span style="font-size:.75rem;color:var(--muted)"><?= $totalNbEnvoyee + $totalNbPayee ?> factures · <?= number_format($totalCaEngage, 0, ',', ' ') ?> FCFA engagés</span>
  </div>
  <div class="table-wrap">
    <table class="monthly-table">
      <thead>
        <tr>
          <th style="text-align:left">Mois</th>
          <th>CA Engagé</th>
          <th>CA Encaissé</th>
          <th>Dépenses</th>
          <th>Bénéfice net</th>
          <th>Marge</th>
          <th class="no-print">Factures</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($months as $num => $m): ?>
      <?php
        $isEmpty = $m['ca_engage'] === 0 && $m['depenses'] === 0;
        $marge   = $m['ca_engage'] > 0 ? round($m['benefice'] / $m['ca_engage'] * 100, 1) : null;
        $isCurrentMonth = (int)date('n') === $num && (int)date('Y') === $currentYear;
      ?>
      <tr <?= $isCurrentMonth ? 'style="background:#fffbeb"' : '' ?>>
        <td>
          <?= $m['label'] ?>
          <?php if ($isCurrentMonth): ?><span style="font-size:.68rem;color:var(--gold);margin-left:4px" class="no-print">● en cours</span><?php endif; ?>
        </td>
        <td class="<?= $isEmpty ? 'zero' : '' ?>">
          <?= $isEmpty ? '—' : number_format($m['ca_engage'], 0, ',', ' ') . ' <span style="font-size:.7rem;color:var(--muted)">FCFA</span>' ?>
        </td>
        <td class="<?= $isEmpty ? 'zero' : '' ?>">
          <?= $isEmpty ? '—' : number_format($m['ca_encaisse'], 0, ',', ' ') . ' <span style="font-size:.7rem;color:var(--muted)">FCFA</span>' ?>
        </td>
        <td class="<?= $isEmpty ? 'zero' : '' ?>">
          <?= $m['depenses'] === 0 ? '<span class="zero">—</span>' : number_format($m['depenses'], 0, ',', ' ') . ' <span style="font-size:.7rem;color:var(--muted)">FCFA</span>' ?>
        </td>
        <td>
          <?php if ($isEmpty): ?>
          <span class="zero">—</span>
          <?php else: ?>
          <span class="<?= $m['benefice'] >= 0 ? 'benefice-pos' : 'benefice-neg' ?>">
            <?= ($m['benefice'] >= 0 ? '' : '') . number_format($m['benefice'], 0, ',', ' ') ?>
            <span style="font-size:.7rem;font-weight:400;color:var(--muted)"> FCFA</span>
          </span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($marge !== null): ?>
          <span style="font-size:.8rem;font-weight:600;color:<?= $marge >= 0 ? '#059669' : '#dc2626' ?>"><?= $marge ?>%</span>
          <?php else: ?><span class="zero">—</span><?php endif; ?>
        </td>
        <td class="no-print" style="color:var(--muted);font-size:.78rem">
          <?php if ($m['nb_envoyee'] + $m['nb_payee'] > 0): ?>
          <span title="Envoyées" style="margin-right:6px">📬 <?= $m['nb_envoyee'] ?></span>
          <span title="Payées">✅ <?= $m['nb_payee'] ?></span>
          <?php else: ?><span class="zero">—</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="total-row">
          <td>TOTAL <?= $currentYear ?></td>
          <td><?= number_format($totalCaEngage, 0, ',', ' ') ?> <span style="font-size:.7rem;font-weight:400;color:var(--muted)">FCFA</span></td>
          <td><?= number_format($totalCaEncaisse, 0, ',', ' ') ?> <span style="font-size:.7rem;font-weight:400;color:var(--muted)">FCFA</span></td>
          <td><?= number_format($totalDepenses, 0, ',', ' ') ?> <span style="font-size:.7rem;font-weight:400;color:var(--muted)">FCFA</span></td>
          <td>
            <span class="<?= $totalBenefice >= 0 ? 'benefice-pos' : 'benefice-neg' ?>">
              <?= number_format($totalBenefice, 0, ',', ' ') ?> <span style="font-size:.7rem;font-weight:400;color:var(--muted)">FCFA</span>
            </span>
          </td>
          <td><span style="font-weight:700;color:<?= $margeGlobal >= 0 ? '#059669' : '#dc2626' ?>"><?= $margeGlobal ?>%</span></td>
          <td class="no-print" style="color:var(--muted);font-size:.78rem">
            📬 <?= $totalNbEnvoyee ?> · ✅ <?= $totalNbPayee ?>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const labels  = <?= json_encode(array_column($months, 'label')) ?>;
const engage  = <?= json_encode(array_values(array_column($months, 'ca_engage'))) ?>;
const encaiss = <?= json_encode(array_values(array_column($months, 'ca_encaisse'))) ?>;
const depenses= <?= json_encode(array_values(array_column($months, 'depenses'))) ?>;

const ctx = document.getElementById('monthly-chart');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'CA Engagé',
          data: engage,
          backgroundColor: 'rgba(15,23,42,.75)',
          borderRadius: 5,
          borderSkipped: false,
        },
        {
          label: 'CA Encaissé',
          data: encaiss,
          backgroundColor: 'rgba(16,185,129,.7)',
          borderRadius: 5,
          borderSkipped: false,
        },
        {
          label: 'Dépenses',
          data: depenses,
          backgroundColor: 'rgba(239,68,68,.65)',
          borderRadius: 5,
          borderSkipped: false,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' ' + ctx.dataset.label + ' : ' +
              ctx.parsed.y.toLocaleString('fr-FR') + ' FCFA',
          },
        },
      },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: {
          grid: { color: 'rgba(0,0,0,.05)' },
          ticks: {
            font: { size: 10 },
            callback: v => (v / 1000).toLocaleString('fr-FR') + 'k',
          },
        },
      },
    },
  });
}
</script>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
