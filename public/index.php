<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Database\InvoiceRepository;
use App\Database\OpportunityRepository;
use App\Database\ProjectRepository;
use App\Database\ExpenseRepository;

$repo      = new InvoiceRepository();
$oppRepo   = new OpportunityRepository();
$projRepo  = new ProjectRepository();
$expRepo   = new ExpenseRepository();

$stats        = $repo->stats();
$overdueStats = $repo->overdueStats();
$topClients   = $repo->topClients(5);
$oppStats     = $oppRepo->stats();
$projStats    = $projRepo->stats();
$finStats     = $expRepo->globalStats();
$recent       = array_slice($repo->all(), 0, 6);

/* Build chart data — CA mensuel Jan→mois courant (année en cours) */
$chartLabels   = [];
$chartEngage   = [];
$chartEncaisse = [];
$currentYear   = (int) date('Y');
$currentMonth  = (int) date('n');
$monthlyData   = $repo->statsByMonth($currentYear);
$monthlyMap    = [];
foreach ($monthlyData as $m) {
    $monthlyMap[(int)$m['month']] = $m;
}
for ($m = 1; $m <= $currentMonth; $m++) {
    $row             = $monthlyMap[$m] ?? ['ca_engage' => 0, 'ca_encaisse' => 0];
    $chartLabels[]   = date('M', mktime(0, 0, 0, $m, 1, $currentYear));
    $chartEngage[]   = (int) $row['ca_engage'];
    $chartEncaisse[] = (int) $row['ca_encaisse'];
}

$statusLabels = [
    'brouillon' => ['label' => 'Brouillon', 'class' => 'badge-draft'],
    'envoyée'   => ['label' => 'Envoyée',   'class' => 'badge-sent'],
    'payée'     => ['label' => 'Payée',      'class' => 'badge-paid'],
    'annulée'   => ['label' => 'Annulée',    'class' => 'badge-cancelled'],
];

$pageTitle   = 'Tableau de bord';
$currentPage = 'dashboard';
$topbarActions = '<a href="/invoice/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle facture</a>';

require __DIR__ . '/../templates/layout.php';
?>

<!-- Stats -->
<!-- Ligne 1 : compteurs -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:14px">
  <div class="stat-card navy">
    <div class="stat-top">
      <div class="stat-label">Total factures</div>
      <div class="stat-badge navy"><i class="fa-solid fa-file-lines"></i></div>
    </div>
    <div class="stat-value"><?= $stats['total'] ?></div>
    <div class="stat-sub"><?= $stats['brouillon'] ?> brouillon<?= $stats['brouillon'] > 1 ? 's' : '' ?> · <?= $stats['envoyee'] ?> envoyée<?= $stats['envoyee'] > 1 ? 's' : '' ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-top">
      <div class="stat-label">Payées</div>
      <div class="stat-badge green"><i class="fa-solid fa-circle-check"></i></div>
    </div>
    <div class="stat-value"><?= $stats['payee'] ?></div>
    <div class="stat-sub"><?= $stats['envoyee'] ?> en attente de règlement</div>
  </div>
  <div class="stat-card red">
    <div class="stat-top">
      <div class="stat-label">Annulées</div>
      <div class="stat-badge red"><i class="fa-solid fa-circle-xmark"></i></div>
    </div>
    <div class="stat-value"><?= $stats['annulee'] ?></div>
    <div class="stat-sub">Exclues du chiffre d'affaires</div>
  </div>
</div>

<?php if ($overdueStats['count'] > 0): ?>
<!-- Alerte retard -->
<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px 18px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center">
  <div>
    <span style="font-size:.85rem;font-weight:700;color:#92400e"><i class="fa-solid fa-triangle-exclamation"></i> <?= $overdueStats['count'] ?> facture<?= $overdueStats['count'] > 1 ? 's' : '' ?> en retard</span>
    <span style="font-size:.78rem;color:#b45309;margin-left:8px"><?= number_format($overdueStats['total'], 0, ',', ' ') ?> FCFA à relancer</span>
  </div>
  <a href="/invoice/list.php?status=retard" class="btn btn-secondary btn-sm" style="border-color:#fed7aa;color:#92400e;background:#fff7ed">Voir →</a>
</div>
<?php endif; ?>

<!-- Ligne 2 : CA -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:22px">
  <!-- CA Engagé -->
  <div class="stat-card gold" style="padding:20px 22px">
    <div class="stat-top">
      <div>
        <div class="stat-label">CA Engagé</div>
        <div style="font-size:.7rem;color:var(--muted-light);margin-top:2px">Envoyées + Payées</div>
      </div>
      <div class="stat-badge gold"><i class="fa-solid fa-envelope-open-text"></i></div>
    </div>
    <div class="stat-value" style="font-size:1.5rem"><?= number_format($stats['ca_engage'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA · ce qui vous est dû</div>
    <?php if ($stats['ca_engage'] > 0 && $stats['ca_encaisse'] < $stats['ca_engage']): ?>
    <div style="margin-top:10px">
      <?php $pct = round($stats['ca_encaisse'] / $stats['ca_engage'] * 100); ?>
      <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--muted);margin-bottom:4px">
        <span>Encaissé</span><span><?= $pct ?>%</span>
      </div>
      <div style="height:4px;background:rgba(0,0,0,.08);border-radius:99px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:var(--green);border-radius:99px;transition:width .6s"></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- CA Encaissé -->
  <div class="stat-card green" style="padding:20px 22px">
    <div class="stat-top">
      <div>
        <div class="stat-label">CA Encaissé</div>
        <div style="font-size:.7rem;color:var(--muted-light);margin-top:2px">Payées uniquement</div>
      </div>
      <div class="stat-badge green"><i class="fa-solid fa-money-bill-wave"></i></div>
    </div>
    <div class="stat-value" style="font-size:1.5rem"><?= number_format($stats['ca_encaisse'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA · effectivement reçu</div>
    <?php if ($stats['ca_engage'] > 0 && $stats['ca_engage'] > $stats['ca_encaisse']): ?>
    <?php $reste = $stats['ca_engage'] - $stats['ca_encaisse']; ?>
    <div style="margin-top:10px;padding:6px 10px;background:rgba(239,68,68,.07);border-radius:6px;font-size:.72rem;color:#b91c1c">
      ⏳ <?= number_format($reste, 0, ',', ' ') ?> FCFA en attente d'encaissement
    </div>
    <?php elseif ($stats['ca_encaisse'] > 0 && $stats['ca_encaisse'] === $stats['ca_engage']): ?>
    <div style="margin-top:10px;padding:6px 10px;background:rgba(16,185,129,.08);border-radius:6px;font-size:.72rem;color:#065f46">
      ✓ Tout est encaissé
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Ligne 3 : Pipeline + Projets + Bénéfice -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:22px">

  <!-- Pipeline -->
  <div class="stat-card navy" style="padding:18px 20px">
    <div class="stat-top">
      <div>
        <div class="stat-label">Pipeline</div>
        <div style="font-size:.7rem;color:var(--muted-light);margin-top:2px"><?= $oppStats['total'] ?> opportunités</div>
      </div>
      <div class="stat-badge navy"><i class="fa-solid fa-bullseye"></i></div>
    </div>
    <div class="stat-value" style="font-size:1.3rem"><?= number_format($oppStats['pipeline_value'], 0, ',', ' ') ?></div>
    <div class="stat-sub">FCFA potentiels · <?= $oppStats['conversion_rate'] ?>% conversion</div>
    <a href="/pipeline/index.php" style="display:inline-block;margin-top:10px;font-size:.75rem;color:var(--gold);text-decoration:none;font-weight:600">Voir le pipeline →</a>
  </div>

  <!-- Projets -->
  <div class="stat-card gold" style="padding:18px 20px">
    <div class="stat-top">
      <div>
        <div class="stat-label">Exécution</div>
        <div style="font-size:.7rem;color:var(--muted-light);margin-top:2px"><?= $projStats['total'] ?> projets</div>
      </div>
      <div class="stat-badge gold"><i class="fa-solid fa-helmet-safety"></i></div>
    </div>
    <div class="stat-value"><?= $projStats['en_cours'] ?></div>
    <div class="stat-sub">en cours · <?= $projStats['livre'] ?> livré<?= $projStats['livre'] > 1 ? 's' : '' ?> · <?= $projStats['valide'] ?> validé<?= $projStats['valide'] > 1 ? 's' : '' ?></div>
    <a href="/project/index.php" style="display:inline-block;margin-top:10px;font-size:.75rem;color:var(--gold);text-decoration:none;font-weight:600">Voir les projets →</a>
  </div>

  <!-- Bénéfice net -->
  <div class="stat-card <?= $finStats['benefice_net'] >= 0 ? 'green' : 'red' ?>" style="padding:18px 20px">
    <div class="stat-top">
      <div>
        <div class="stat-label">Bénéfice net</div>
        <div style="font-size:.7rem;color:var(--muted-light);margin-top:2px">CA encaissé − Dépenses</div>
      </div>
      <div class="stat-badge <?= $finStats['benefice_net'] >= 0 ? 'green' : 'red' ?>">📈</div>
    </div>
    <div class="stat-value" style="font-size:1.3rem;color:<?= $finStats['benefice_net'] >= 0 ? 'var(--green)' : 'var(--red)' ?>">
      <?= number_format($finStats['benefice_net'], 0, ',', ' ') ?>
    </div>
    <div class="stat-sub">FCFA · <?= number_format($finStats['total_depenses'], 0, ',', ' ') ?> FCFA de coûts</div>
    <a href="/expense/index.php" style="display:inline-block;margin-top:10px;font-size:.75rem;color:var(--gold);text-decoration:none;font-weight:600">Voir les dépenses →</a>
  </div>
</div>

<!-- Content grid -->
<div style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start">

  <!-- Factures récentes -->
  <div class="card">
    <div class="card-header">
      <h2>Factures récentes</h2>
      <a href="/invoice/list.php" style="font-size:.78rem;color:var(--gold);text-decoration:none;font-weight:600">Voir tout →</a>
    </div>

    <?php if (empty($recent)): ?>
    <div class="empty-state">
      <div class="empty-icon">🧾</div>
      <h3>Aucune facture</h3>
      <p>Créez votre première facture pour commencer.</p>
      <a href="/invoice/create.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle facture</a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>N° Facture</th>
            <th>Client</th>
            <th>Date</th>
            <th style="text-align:right">Montant</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recent as $inv): ?>
          <?php $s = $statusLabels[$inv['status']] ?? ['label' => $inv['status'], 'class' => 'badge-draft']; ?>
          <tr>
            <td>
              <a href="/invoice/edit.php?id=<?= $inv['id'] ?>"
                 style="font-weight:600;color:var(--navy);text-decoration:none">
                <?= htmlspecialchars($inv['number']) ?>
              </a>
              <div style="font-size:.7rem;color:var(--muted-light)"><?= htmlspecialchars($inv['type']) ?></div>
            </td>
            <td><?= htmlspecialchars($inv['client_name'] ?: '—') ?></td>
            <td style="color:var(--muted);font-size:.78rem"><?= $inv['issued_at'] ? date('d/m/Y', strtotime($inv['issued_at'])) : '—' ?></td>
            <td style="text-align:right;font-weight:600;font-size:.84rem">
              <?= number_format((int)$inv['total_net'], 0, ',', ' ') ?> <span style="font-size:.7rem;color:var(--muted)">FCFA</span>
            </td>
            <td><span class="badge <?= $s['class'] ?>"><?= $s['label'] ?></span></td>
            <td>
              <div style="display:flex;gap:4px">
                <a href="/invoice/edit.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="Modifier">✏️</a>
                <a href="/invoice/pdf.php?id=<?= $inv['id'] ?>" class="btn btn-secondary btn-sm btn-icon" title="PDF" target="_blank">📄</a>
                <form method="POST" action="/invoice/duplicate.php" style="display:inline">
                  <input type="hidden" name="id" value="<?= $inv['id'] ?>">
                  <button type="submit" class="btn btn-secondary btn-sm btn-icon" title="Dupliquer">⧉</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Colonne droite -->
  <div style="display:flex;flex-direction:column;gap:14px">

    <!-- Répartition statuts -->
    <div class="card">
      <div class="card-header"><h2>Répartition</h2></div>
      <div class="card-body" style="padding:14px 16px">
        <?php
        $statuses = [
          'Brouillons' => [$stats['brouillon'], '#94a3b8'],
          'Envoyées'   => [$stats['envoyee'],   '#3b82f6'],
          'Payées'     => [$stats['payee'],      '#10b981'],
          'Annulées'   => [$stats['annulee'],    '#ef4444'],
        ];
        $total = max($stats['total'], 1);
        foreach ($statuses as $label => [$count, $color]):
          $pct = round($count / $total * 100);
        ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:4px">
            <span style="font-weight:500;color:var(--text)"><?= $label ?></span>
            <span style="color:var(--muted)"><?= $count ?> &middot; <?= $pct ?>%</span>
          </div>
          <div style="height:5px;background:var(--border-soft);border-radius:99px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:99px;transition:width .6s"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Top clients -->
    <?php if (!empty($topClients)): ?>
    <div class="card">
      <div class="card-header"><h2>Top clients</h2></div>
      <div class="card-body" style="padding:14px 16px">
        <?php
        $maxCa = max(max(array_column($topClients, 'ca')), 1);
        foreach ($topClients as $c):
          $pct = round($c['ca'] / $maxCa * 100);
        ?>
        <div style="margin-bottom:11px">
          <div style="display:flex;justify-content:space-between;font-size:.78rem;margin-bottom:4px">
            <span style="font-weight:600;color:var(--navy);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60%"><?= htmlspecialchars($c['client_name']) ?></span>
            <span style="color:var(--muted);font-size:.73rem"><?= number_format((int)$c['ca'], 0, ',', ' ') ?> FCFA</span>
          </div>
          <div style="height:4px;background:var(--border-soft);border-radius:99px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:var(--gold);border-radius:99px"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /col droite -->
</div>

<!-- Graphique CA mensuel 12 mois -->
<div class="card" style="margin-top:16px">
  <div class="card-header"><h2>CA mensuel — <?= date('Y') ?></h2></div>
  <div class="card-body" style="padding:16px">
    <canvas id="monthlyChart" height="90"></canvas>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('monthlyChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [
      {
        label: 'CA Engagé',
        data: <?= json_encode($chartEngage) ?>,
        backgroundColor: 'rgba(59,130,246,.18)',
        borderColor: '#3b82f6',
        borderWidth: 2,
        borderRadius: 4,
      },
      {
        label: 'CA Encaissé',
        data: <?= json_encode($chartEncaisse) ?>,
        backgroundColor: 'rgba(16,185,129,.22)',
        borderColor: '#10b981',
        borderWidth: 2,
        borderRadius: 4,
      }
    ]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    plugins: {
      legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } },
      tooltip: {
        callbacks: {
          label: ctx => ctx.dataset.label + ' : ' + ctx.parsed.y.toLocaleString('fr-FR') + ' FCFA'
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { font: { size: 10 }, color: '#94a3b8', callback: v => (v/1000).toLocaleString('fr-FR') + 'k' },
        grid: { color: '#f1f5f9' },
        border: { display: false }
      },
      x: {
        ticks: { font: { size: 10 }, color: '#94a3b8' },
        grid: { display: false },
        border: { display: false }
      }
    }
  }
});
</script>

<?php require __DIR__ . '/../templates/layout_end.php'; ?>
