<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireBusiness();

use App\Services\LicenseService;
if (!LicenseService::canAdd('excel', LicenseService::getCounter('excel'))) {
    http_response_code(403);
    exit('Limite du plan gratuit atteinte (' . LicenseService::excelMax() . ' exports Excel). Activez une licence Pro.');
}
LicenseService::incrementCounter('excel');

use App\Database\InvoiceRepository;
use App\Database\ExpenseRepository;
use App\Database\SettingsRepository;

$type = $_GET['type'] ?? 'monthly'; // 'monthly' or 'annual'
$year = (int) ($_GET['year'] ?? date('Y'));

$invRepo      = new InvoiceRepository();
$expRepo      = new ExpenseRepository();
$settingsRepo = new SettingsRepository();

$company = $settingsRepo->get('issuer_name', 'Entreprise');

// ── helpers ──────────────────────────────────────────────────────────────────

function csvRow(array $cols): string {
    return implode(';', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $cols)) . "\r\n";
}

function pctDiff(int $prev, int $curr): string {
    if ($prev === 0) return 'N/A';
    return round(($curr - $prev) / abs($prev) * 100, 1) . '%';
}

$MONTHS_FR = [
    1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
    7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre',
];

// ── output headers ────────────────────────────────────────────────────────────

if ($type === 'annual') {
    $filename = "rapport-annuel-{$year}.csv";
} else {
    $filename = "comptabilite-mensuelle-{$year}.csv";
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

// UTF-8 BOM so Excel auto-detects encoding
echo "\xEF\xBB\xBF";

// ── MONTHLY export ────────────────────────────────────────────────────────────

if ($type === 'monthly') {
    $invMonths = $invRepo->statsByMonth($year);
    $expMonths = $expRepo->statsByMonth($year);

    // Header block
    echo csvRow([$company . ' — Comptabilité mensuelle ' . $year]);
    echo csvRow(['Généré le : ' . date('d/m/Y à H:i')]);
    echo csvRow([]);

    // Column headers
    echo csvRow([
        'Mois',
        'CA Engagé (FCFA)',
        'CA Encaissé (FCFA)',
        'Dépenses (FCFA)',
        'Bénéfice net (FCFA)',
        'Marge (%)',
        'Factures émises',
        'Dont payées',
    ]);

    $totCaE = $totCaC = $totDep = $totBen = $totEnv = $totPay = 0;

    for ($m = 1; $m <= 12; $m++) {
        $caE = $invMonths[$m]['ca_engage']   ?? 0;
        $caC = $invMonths[$m]['ca_encaisse'] ?? 0;
        $dep = $expMonths[$m]['total']       ?? 0;
        $env = ($invMonths[$m]['nb_envoyee'] ?? 0) + ($invMonths[$m]['nb_payee'] ?? 0);
        $pay = $invMonths[$m]['nb_payee']    ?? 0;
        $ben = $caE - $dep;
        $mar = $caE > 0 ? round($ben / $caE * 100, 1) : 0;

        echo csvRow([
            $MONTHS_FR[$m],
            $caE,
            $caC,
            $dep,
            $ben,
            $mar . '%',
            $env,
            $pay,
        ]);

        $totCaE += $caE;
        $totCaC += $caC;
        $totDep += $dep;
        $totBen += $ben;
        $totEnv += $env;
        $totPay += $pay;
    }

    // Total row
    echo csvRow([]);
    $totMar = $totCaE > 0 ? round($totBen / $totCaE * 100, 1) : 0;
    echo csvRow([
        'TOTAL ' . $year,
        $totCaE,
        $totCaC,
        $totDep,
        $totBen,
        $totMar . '%',
        $totEnv,
        $totPay,
    ]);
    exit;
}

// ── ANNUAL (P&L) export ───────────────────────────────────────────────────────

$prevYear = $year - 1;

$invN = $invRepo->statsByMonth($year);
$expN = $expRepo->statsByMonth($year);
$invP = $invRepo->statsByMonth($prevYear);
$expP = $expRepo->statsByMonth($prevYear);

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

// Header block
echo csvRow([$company . ' — Rapport financier annuel ' . $year]);
echo csvRow(['Généré le : ' . date('d/m/Y à H:i')]);
echo csvRow([]);

// Column headers
echo csvRow(['Indicateur', $year . ' (N)', $prevYear . ' (N-1)', 'Variation (%)']);

// ── REVENUS
echo csvRow(['=== REVENUS ===', '', '', '']);
echo csvRow(['CA Engagé (FCFA)', $n['ca_engage'], $p['ca_engage'], pctDiff($p['ca_engage'], $n['ca_engage'])]);
echo csvRow(['CA Encaissé (FCFA)', $n['ca_encaisse'], $p['ca_encaisse'], pctDiff($p['ca_encaisse'], $n['ca_encaisse'])]);
echo csvRow(['Créances en attente (FCFA)', $n['non_encaisse'], $p['non_encaisse'], pctDiff($p['non_encaisse'], $n['non_encaisse'])]);
echo csvRow(['Nb factures émises', $n['nb_envoyee'] + $n['nb_payee'], $p['nb_envoyee'] + $p['nb_payee'], pctDiff($p['nb_envoyee'] + $p['nb_payee'], $n['nb_envoyee'] + $n['nb_payee'])]);
echo csvRow(['Dont payées', $n['nb_payee'], $p['nb_payee'], pctDiff($p['nb_payee'], $n['nb_payee'])]);

echo csvRow([]);

// ── CHARGES
echo csvRow(['=== CHARGES ===', '', '', '']);
echo csvRow(['Total dépenses (FCFA)', $n['depenses'], $p['depenses'], pctDiff($p['depenses'], $n['depenses'])]);
$ratioN = $n['ca_engage'] > 0 ? round($n['depenses'] / $n['ca_engage'] * 100, 1) : 0;
$ratioP = $p['ca_engage'] > 0 ? round($p['depenses'] / $p['ca_engage'] * 100, 1) : 0;
echo csvRow(['Ratio charges / CA engagé', $ratioN . '%', $ratioP . '%', '—']);

echo csvRow([]);

// ── RÉSULTAT
echo csvRow(['=== RÉSULTAT ===', '', '', '']);
echo csvRow(['Bénéfice net (FCFA)', $n['benefice_brut'], $p['benefice_brut'], pctDiff($p['benefice_brut'], $n['benefice_brut'])]);
echo csvRow(['Marge nette', $n['marge'] . '%', $p['marge'] . '%', ($n['marge'] - $p['marge']) . ' pt']);

echo csvRow([]);

// ── DÉTAIL MENSUEL
echo csvRow(['=== DÉTAIL MENSUEL ' . $year . ' ===', '', '', '']);
echo csvRow(['Mois', 'CA Engagé (FCFA)', 'CA Encaissé (FCFA)', 'Dépenses (FCFA)', 'Bénéfice (FCFA)', 'Marge (%)']);

$totCaE = $totCaC = $totDep = $totBen = 0;
for ($m = 1; $m <= 12; $m++) {
    $caE = $invN[$m]['ca_engage']   ?? 0;
    $caC = $invN[$m]['ca_encaisse'] ?? 0;
    $dep = $expN[$m]['total']       ?? 0;
    $ben = $caE - $dep;
    $mar = $caE > 0 ? round($ben / $caE * 100, 1) : 0;

    echo csvRow([$MONTHS_FR[$m], $caE, $caC, $dep, $ben, $mar . '%']);

    $totCaE += $caE;
    $totCaC += $caC;
    $totDep += $dep;
    $totBen += $ben;
}

echo csvRow([]);
$totMar = $totCaE > 0 ? round($totBen / $totCaE * 100, 1) : 0;
echo csvRow(['TOTAL ' . $year, $totCaE, $totCaC, $totDep, $totBen, $totMar . '%']);

exit;
