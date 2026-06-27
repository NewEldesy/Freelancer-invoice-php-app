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
use App\Database\SettingsRepository;

$invRepo      = new InvoiceRepository();
$expRepo      = new ExpenseRepository();
$settingsRepo = new SettingsRepository();

$availableYears = $invRepo->availableYears();
$currentYear    = (int) ($_GET['year'] ?? date('Y'));
$prevYear       = $currentYear - 1;

if (!in_array($currentYear, $availableYears, true)) {
    $availableYears[] = $currentYear;
    rsort($availableYears);
}

// Company info from settings
$company = $settingsRepo->get('issuer_name', 'Mon Entreprise');
$address = $settingsRepo->get('issuer_address', '');

// Current year data
$invN  = $invRepo->statsByMonth($currentYear);
$expN  = $expRepo->statsByMonth($currentYear);

// Previous year data
$invP  = $invRepo->statsByMonth($prevYear);
$expP  = $expRepo->statsByMonth($prevYear);

// Aggregate annual totals
function sumCol(array $months, string $col): int {
    return array_sum(array_column($months, $col));
}

$n = [
    'ca_engage'   => sumCol($invN, 'ca_engage'),
    'ca_encaisse' => sumCol($invN, 'ca_encaisse'),
    'depenses'    => sumCol($expN, 'total'),
    'nb_envoyee'  => sumCol($invN, 'nb_envoyee'),
    'nb_payee'    => sumCol($invN, 'nb_payee'),
];
$n['benefice_brut'] = $n['ca_engage'] - $n['depenses'];
$n['marge']         = $n['ca_engage'] > 0 ? round($n['benefice_brut'] / $n['ca_engage'] * 100, 1) : 0;
$n['non_encaisse']  = $n['ca_engage'] - $n['ca_encaisse'];

$p = [
    'ca_engage'   => sumCol($invP, 'ca_engage'),
    'ca_encaisse' => sumCol($invP, 'ca_encaisse'),
    'depenses'    => sumCol($expP, 'total'),
    'nb_envoyee'  => sumCol($invP, 'nb_envoyee'),
    'nb_payee'    => sumCol($invP, 'nb_payee'),
];
$p['benefice_brut'] = $p['ca_engage'] - $p['depenses'];
$p['marge']         = $p['ca_engage'] > 0 ? round($p['benefice_brut'] / $p['ca_engage'] * 100, 1) : 0;
$p['non_encaisse']  = $p['ca_engage'] - $p['ca_encaisse'];

// % change helper
function pct(int $prev, int $curr): ?float {
    if ($prev === 0) return null;
    return round(($curr - $prev) / abs($prev) * 100, 1);
}
function fmtPct(?float $v): string {
    if ($v === null) return '<span style="color:#94a3b8">N/A</span>';
    $color = $v >= 0 ? '#059669' : '#dc2626';
    $arrow = $v >= 0 ? '▲' : '▼';
    return "<span style=\"color:{$color};font-weight:700\">{$arrow} " . abs($v) . "%</span>";
}
function fmt(int $v): string {
    return number_format($v, 0, ',', ' ');
}

// Monthly P&L for the detail section
$MONTHS_FR = [
    1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Jun',
    7=>'Jul',8=>'Aoû',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc',
];

$pageTitle   = 'Rapport annuel ' . $currentYear;
$currentPage = 'accounting';
$topbarActions = '
  <a href="/accounting/index.php?year=' . $currentYear . '" class="btn btn-secondary"><i class="fa-solid fa-chart-bar"></i> Vue mensuelle</a>
  <a href="/accounting/export.php?type=annual&year=' . $currentYear . '" class="btn btn-secondary"><i class="fa-solid fa-file-excel"></i> Exporter Excel</a>
  <button onclick="window.print()" class="btn btn-secondary" id="print-btn"><i class="fa-solid fa-print"></i> Imprimer / PDF</button>
';

require __DIR__ . '/../../templates/layout.php';
?>

<style>
/* ════════════════════════════════
   SCREEN
════════════════════════════════ */
.rpt-year-nav { display:flex; align-items:center; gap:8px; margin-bottom:20px; flex-wrap:wrap; }
.rpt-year-btn {
  padding:5px 14px; border-radius:7px; border:1px solid var(--border);
  background:var(--white); color:var(--muted); font-size:.8rem; font-weight:600;
  text-decoration:none; transition:all .15s;
}
.rpt-year-btn:hover  { background:var(--bg); color:var(--text); }
.rpt-year-btn.active { background:var(--navy); color:#fff; border-color:var(--navy); }

/* Report card */
.rpt-card {
  background:var(--white); border:1px solid var(--border);
  border-radius:12px; box-shadow:var(--shadow-sm);
  margin-bottom:18px; overflow:hidden;
}

/* Summary banner (top 4 KPIs) */
.rpt-banner {
  display:grid; grid-template-columns:repeat(4,1fr);
}
.rpt-kpi {
  padding:20px 22px; border-right:1px solid var(--border-soft);
  position:relative; overflow:hidden;
}
.rpt-kpi:last-child { border-right:none; }
.rpt-kpi::before {
  content:''; position:absolute; top:0; left:0; right:0; height:3px;
}
.rpt-kpi.navy::before  { background:linear-gradient(90deg,var(--navy),#334155); }
.rpt-kpi.green::before { background:linear-gradient(90deg,var(--green),#34d399); }
.rpt-kpi.gold::before  { background:linear-gradient(90deg,var(--gold),#fbbf24); }
.rpt-kpi.red::before   { background:linear-gradient(90deg,var(--red),#f87171); }

.rpt-kpi-label { font-size:.68rem; text-transform:uppercase; letter-spacing:.8px; color:var(--muted); font-weight:700; margin-bottom:8px; }
.rpt-kpi-value { font-size:1.4rem; font-weight:800; color:var(--navy); letter-spacing:-.02em; line-height:1; }
.rpt-kpi-sub   { font-size:.7rem; color:var(--muted-light); margin-top:5px; }
.rpt-kpi-delta { margin-top:6px; font-size:.72rem; }

/* P&L table */
.pl-table { width:100%; border-collapse:collapse; font-size:.83rem; }
.pl-table th {
  padding:10px 18px; font-size:.68rem; text-transform:uppercase; letter-spacing:.5px;
  color:var(--muted); font-weight:600; border-bottom:2px solid var(--border);
  background:#fafbfc;
}
.pl-table th:not(:first-child) { text-align:right; }
.pl-table td { padding:11px 18px; border-bottom:1px solid var(--border-soft); vertical-align:middle; }
.pl-table td:not(:first-child) { text-align:right; font-variant-numeric:tabular-nums; }
.pl-table tbody tr:not(.pl-section-head):not(.pl-subtotal):not(.pl-total):hover td { background:#f8fafc; }

.pl-section-head td {
  background:var(--navy); color:rgba(255,255,255,.9);
  font-size:.7rem; font-weight:700; letter-spacing:.6px;
  text-transform:uppercase; padding:7px 18px;
}
.pl-subtotal td {
  background:#f1f5f9; font-weight:700; font-size:.84rem; color:var(--navy);
  border-top:1px solid var(--border); border-bottom:2px solid var(--border);
}
.pl-total td {
  background:var(--navy); color:#fff; font-weight:800; font-size:.9rem;
  border:none;
}
.pl-total td:first-child { border-radius:0; }

/* Monthly breakdown table (compact) */
.monthly-mini { width:100%; border-collapse:collapse; font-size:.76rem; }
.monthly-mini th {
  padding:7px 10px; font-size:.65rem; text-transform:uppercase; letter-spacing:.4px;
  color:var(--muted); font-weight:600; border-bottom:1px solid var(--border);
  background:#fafbfc; text-align:right;
}
.monthly-mini th:first-child { text-align:left; }
.monthly-mini td { padding:8px 10px; border-bottom:1px solid var(--border-soft); text-align:right; }
.monthly-mini td:first-child { text-align:left; font-weight:500; color:var(--navy); }
.monthly-mini tr.current-month td { background:#fffbeb; }
.monthly-mini tfoot td { font-weight:700; background:#f1f5f9; border-top:2px solid var(--border); }

/* ════════════════════════════════
   PRINT
════════════════════════════════ */
@media print {
  @page { size: A4 portrait; margin: 12mm 14mm; }

  body { background:#fff !important; font-size:10px !important; color:#000 !important; }

  .sidebar, .topbar, #print-btn, .rpt-year-nav,
  .no-print, .btn { display:none !important; }

  .main { margin-left:0 !important; }
  .content { padding:6px !important; }

  /* Print header */
  .print-header { display:flex !important; }

  /* KPI banner: 4 columns */
  .rpt-banner { grid-template-columns:repeat(4,1fr) !important; }
  .rpt-kpi { padding:10px 12px !important; }
  .rpt-kpi-value { font-size:1rem !important; }

  .rpt-card { box-shadow:none !important; border:1px solid #ccc !important; margin-bottom:10px !important; break-inside:avoid; }

  .pl-table th, .pl-table td { padding:6px 12px !important; font-size:9px !important; }
  .pl-section-head td { padding:5px 12px !important; font-size:8px !important; }

  .monthly-mini th, .monthly-mini td { padding:5px 8px !important; font-size:8.5px !important; }

  .print-break { page-break-before:always; }
  .no-print-section { display:none !important; }
}
</style>

<!-- Print-only header -->
<div class="print-header" style="display:none; justify-content:space-between; align-items:flex-start; margin-bottom:16px; padding-bottom:12px; border-bottom:3px solid #0f172a;">
  <div>
    <div style="font-size:9px;text-transform:uppercase;letter-spacing:1.5px;color:#64748b;font-weight:700;margin-bottom:4px">Rapport financier annuel</div>
    <div style="font-size:22px;font-weight:900;color:#0f172a;letter-spacing:-.03em"><?= htmlspecialchars($company) ?></div>
    <?php if ($address): ?>
    <div style="font-size:9px;color:#64748b;margin-top:2px"><?= htmlspecialchars($address) ?></div>
    <?php endif; ?>
  </div>
  <div style="text-align:right">
    <div style="font-size:28px;font-weight:900;color:#0f172a;letter-spacing:-.03em"><?= $currentYear ?></div>
    <div style="font-size:9px;color:#64748b;margin-top:2px">vs <?= $prevYear ?> · Généré le <?= date('d/m/Y') ?></div>
  </div>
</div>

<!-- Screen header -->
<div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
  <div>
    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);font-weight:700;margin-bottom:2px">Rapport financier annuel</div>
    <div style="font-size:1.2rem;font-weight:800;color:var(--navy)"><?= htmlspecialchars($company) ?></div>
  </div>
  <div class="rpt-year-nav">
    <span style="font-size:.75rem;color:var(--muted);font-weight:600">Année :</span>
    <?php foreach ($availableYears as $y): ?>
    <a href="/accounting/report.php?year=<?= $y ?>" class="rpt-year-btn <?= $y === $currentYear ? 'active' : '' ?>"><?= $y ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══ KPI Banner ══ -->
<div class="rpt-card">
  <div class="rpt-banner">
    <div class="rpt-kpi navy">
      <div class="rpt-kpi-label">CA Engagé</div>
      <div class="rpt-kpi-value"><?= fmt($n['ca_engage']) ?></div>
      <div class="rpt-kpi-sub">FCFA · <?= $currentYear ?></div>
      <div class="rpt-kpi-delta"><?= fmtPct(pct($p['ca_engage'], $n['ca_engage'])) ?> vs <?= $prevYear ?></div>
    </div>
    <div class="rpt-kpi green">
      <div class="rpt-kpi-label">CA Encaissé</div>
      <div class="rpt-kpi-value"><?= fmt($n['ca_encaisse']) ?></div>
      <div class="rpt-kpi-sub">FCFA · factures payées</div>
      <div class="rpt-kpi-delta"><?= fmtPct(pct($p['ca_encaisse'], $n['ca_encaisse'])) ?> vs <?= $prevYear ?></div>
    </div>
    <div class="rpt-kpi red">
      <div class="rpt-kpi-label">Total Dépenses</div>
      <div class="rpt-kpi-value"><?= fmt($n['depenses']) ?></div>
      <div class="rpt-kpi-sub">FCFA engagés</div>
      <div class="rpt-kpi-delta"><?= fmtPct(pct($p['depenses'], $n['depenses'])) ?> vs <?= $prevYear ?></div>
    </div>
    <div class="rpt-kpi gold">
      <div class="rpt-kpi-label">Bénéfice Net</div>
      <div class="rpt-kpi-value" style="color:<?= $n['benefice_brut'] >= 0 ? 'var(--green)' : 'var(--red)' ?>">
        <?= fmt($n['benefice_brut']) ?>
      </div>
      <div class="rpt-kpi-sub">Marge <?= $n['marge'] ?>%</div>
      <div class="rpt-kpi-delta"><?= fmtPct(pct($p['benefice_brut'], $n['benefice_brut'])) ?> vs <?= $prevYear ?></div>
    </div>
  </div>
</div>

<!-- ══ P&L Table ══ -->
<div class="rpt-card">
  <div style="padding:16px 20px; border-bottom:1px solid var(--border-soft); display:flex; justify-content:space-between; align-items:center;">
    <div>
      <div style="font-weight:700;color:var(--navy);font-size:.92rem">Compte de résultat</div>
      <div style="font-size:.72rem;color:var(--muted);margin-top:2px">Comparaison annuelle</div>
    </div>
    <div style="display:flex;gap:20px;font-size:.72rem;color:var(--muted)">
      <span style="font-weight:700;color:var(--navy)"><?= $currentYear ?></span>
      <span><?= $prevYear ?></span>
      <span>Variation</span>
    </div>
  </div>
  <table class="pl-table">
    <thead>
      <tr>
        <th style="text-align:left;width:45%">Indicateur</th>
        <th><?= $currentYear ?></th>
        <th><?= $prevYear ?></th>
        <th>Variation</th>
      </tr>
    </thead>
    <tbody>

      <!-- REVENUS -->
      <tr class="pl-section-head"><td colspan="4">Revenus</td></tr>
      <tr>
        <td>CA Engagé <span style="font-size:.7rem;color:var(--muted)">(factures envoyées + payées)</span></td>
        <td style="font-weight:600"><?= fmt($n['ca_engage']) ?> <span style="font-size:.7rem;color:var(--muted)">FCFA</span></td>
        <td style="color:var(--muted)"><?= fmt($p['ca_engage']) ?></td>
        <td><?= fmtPct(pct($p['ca_engage'], $n['ca_engage'])) ?></td>
      </tr>
      <tr>
        <td>CA Encaissé <span style="font-size:.7rem;color:var(--muted)">(factures payées uniquement)</span></td>
        <td style="font-weight:600"><?= fmt($n['ca_encaisse']) ?> <span style="font-size:.7rem;color:var(--muted)">FCFA</span></td>
        <td style="color:var(--muted)"><?= fmt($p['ca_encaisse']) ?></td>
        <td><?= fmtPct(pct($p['ca_encaisse'], $n['ca_encaisse'])) ?></td>
      </tr>
      <tr>
        <td>Créances en attente <span style="font-size:.7rem;color:var(--muted)">(engagé − encaissé)</span></td>
        <td style="color:<?= $n['non_encaisse'] > 0 ? '#d97706' : 'var(--text)' ?>;font-weight:600">
          <?= fmt($n['non_encaisse']) ?> <span style="font-size:.7rem;color:var(--muted)">FCFA</span>
        </td>
        <td style="color:var(--muted)"><?= fmt($p['non_encaisse']) ?></td>
        <td><?= fmtPct(pct($p['non_encaisse'], $n['non_encaisse'])) ?></td>
      </tr>
      <tr>
        <td>Nombre de factures émises</td>
        <td style="font-weight:600"><?= $n['nb_envoyee'] + $n['nb_payee'] ?></td>
        <td style="color:var(--muted)"><?= $p['nb_envoyee'] + $p['nb_payee'] ?></td>
        <td><?= fmtPct(pct($p['nb_envoyee'] + $p['nb_payee'], $n['nb_envoyee'] + $n['nb_payee'])) ?></td>
      </tr>
      <tr>
        <td>Dont payées</td>
        <td style="font-weight:600"><?= $n['nb_payee'] ?></td>
        <td style="color:var(--muted)"><?= $p['nb_payee'] ?></td>
        <td><?= fmtPct(pct($p['nb_payee'], $n['nb_payee'])) ?></td>
      </tr>

      <!-- CHARGES -->
      <tr class="pl-section-head"><td colspan="4">Charges</td></tr>
      <tr>
        <td>Total dépenses</td>
        <td style="font-weight:600;color:var(--red)"><?= fmt($n['depenses']) ?> <span style="font-size:.7rem;color:var(--muted)">FCFA</span></td>
        <td style="color:var(--muted)"><?= fmt($p['depenses']) ?></td>
        <td>
          <?php
            // For expenses, increase = bad (red), decrease = good (green)
            $expPct = pct($p['depenses'], $n['depenses']);
            if ($expPct === null) echo '<span style="color:#94a3b8">N/A</span>';
            else {
              $color = $expPct <= 0 ? '#059669' : '#dc2626';
              $arrow = $expPct <= 0 ? '▼' : '▲';
              echo "<span style=\"color:{$color};font-weight:700\">{$arrow} " . abs($expPct) . "%</span>";
            }
          ?>
        </td>
      </tr>
      <?php if ($n['ca_engage'] > 0): ?>
      <tr>
        <td>Ratio charges / CA engagé</td>
        <td style="font-weight:600"><?= $n['ca_engage'] > 0 ? round($n['depenses'] / $n['ca_engage'] * 100, 1) : 0 ?>%</td>
        <td style="color:var(--muted)"><?= $p['ca_engage'] > 0 ? round($p['depenses'] / $p['ca_engage'] * 100, 1) : 0 ?>%</td>
        <td>—</td>
      </tr>
      <?php endif; ?>

      <!-- RÉSULTAT -->
      <tr class="pl-section-head"><td colspan="4">Résultat</td></tr>
      <tr class="pl-subtotal">
        <td>Bénéfice net <span style="font-size:.7rem;font-weight:400;color:var(--muted)">(CA engagé − dépenses)</span></td>
        <td style="color:<?= $n['benefice_brut'] >= 0 ? '#059669' : '#dc2626' ?>">
          <?= fmt($n['benefice_brut']) ?> <span style="font-size:.7rem;font-weight:400;color:var(--muted)">FCFA</span>
        </td>
        <td style="color:<?= $p['benefice_brut'] >= 0 ? '#059669' : '#dc2626' ?>;font-weight:600">
          <?= fmt($p['benefice_brut']) ?>
        </td>
        <td><?= fmtPct(pct($p['benefice_brut'], $n['benefice_brut'])) ?></td>
      </tr>
      <tr class="pl-total">
        <td>Marge nette</td>
        <td><?= $n['marge'] ?>%</td>
        <td style="opacity:.7"><?= $p['marge'] ?>%</td>
        <td>
          <?php
            $margeDelta = $n['marge'] - $p['marge'];
            if ($p['marge'] != 0) {
              $color = $margeDelta >= 0 ? '#34d399' : '#fca5a5';
              $arrow = $margeDelta >= 0 ? '▲' : '▼';
              echo "<span style=\"color:{$color};font-weight:700\">{$arrow} " . abs(round($margeDelta, 1)) . " pt</span>";
            } else {
              echo '<span style="opacity:.5">N/A</span>';
            }
          ?>
        </td>
      </tr>
    </tbody>
  </table>
</div>

<!-- ══ Monthly breakdown ══ -->
<div class="rpt-card no-print-section">
  <div style="padding:14px 20px; border-bottom:1px solid var(--border-soft); display:flex; justify-content:space-between; align-items:center;">
    <div style="font-weight:700;color:var(--navy);font-size:.88rem">Détail mensuel — <?= $currentYear ?></div>
    <span style="font-size:.72rem;color:var(--muted)">CA Engagé · Dépenses · Bénéfice</span>
  </div>
  <div style="overflow-x:auto">
  <table class="monthly-mini">
    <thead>
      <tr>
        <th style="text-align:left">Mois</th>
        <th>CA Engagé</th>
        <th>CA Encaissé</th>
        <th>Dépenses</th>
        <th>Bénéfice</th>
        <th>Marge</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $MONTHS_FULL = [
        1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
        7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre',
    ];
    for ($m = 1; $m <= 12; $m++):
        $caE  = $invN[$m]['ca_engage']   ?? 0;
        $caC  = $invN[$m]['ca_encaisse'] ?? 0;
        $dep  = $expN[$m]['total']       ?? 0;
        $ben  = $caE - $dep;
        $mar  = $caE > 0 ? round($ben / $caE * 100, 1) : null;
        $empty = $caE === 0 && $dep === 0;
        $isCurr = (int)date('n') === $m && (int)date('Y') === $currentYear;
    ?>
    <tr <?= $isCurr ? 'class="current-month"' : '' ?>>
      <td>
        <?= $MONTHS_FULL[$m] ?>
        <?php if ($isCurr): ?><span style="font-size:.65rem;color:var(--gold);margin-left:3px">●</span><?php endif; ?>
      </td>
      <td><?= $empty ? '<span style="color:#94a3b8">—</span>' : fmt($caE) ?></td>
      <td><?= $empty ? '<span style="color:#94a3b8">—</span>' : fmt($caC) ?></td>
      <td><?= $dep === 0 ? '<span style="color:#94a3b8">—</span>' : fmt($dep) ?></td>
      <td>
        <?php if (!$empty): ?>
        <span style="color:<?= $ben >= 0 ? '#059669' : '#dc2626' ?>;font-weight:600"><?= fmt($ben) ?></span>
        <?php else: ?><span style="color:#94a3b8">—</span><?php endif; ?>
      </td>
      <td>
        <?php if ($mar !== null): ?>
        <span style="font-weight:600;color:<?= $mar >= 0 ? '#059669' : '#dc2626' ?>"><?= $mar ?>%</span>
        <?php else: ?><span style="color:#94a3b8">—</span><?php endif; ?>
      </td>
    </tr>
    <?php endfor; ?>
    </tbody>
    <tfoot>
      <tr>
        <td>TOTAL <?= $currentYear ?></td>
        <td><?= fmt($n['ca_engage']) ?></td>
        <td><?= fmt($n['ca_encaisse']) ?></td>
        <td><?= fmt($n['depenses']) ?></td>
        <td style="color:<?= $n['benefice_brut'] >= 0 ? '#059669' : '#dc2626' ?>"><?= fmt($n['benefice_brut']) ?></td>
        <td style="color:<?= $n['marge'] >= 0 ? '#059669' : '#dc2626' ?>"><?= $n['marge'] ?>%</td>
      </tr>
    </tfoot>
  </table>
  </div>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
