<?php

/**
 * Freelancer-invoice — Générateur de clés de licence
 * Usage CLI :
 *
 *   php tools/keygen.php pro 3m
 *   php tools/keygen.php pro 6m
 *   php tools/keygen.php pro 1y
 *   php tools/keygen.php pro 2y
 *   php tools/keygen.php pro permanent
 *   php tools/keygen.php enterprise 1y <machine_id>
 *
 * Le machine_id s'affiche sur la page /activate.php du client.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\LicenseService;

// ── Période → date d'expiration ───────────────────────────────────────────────

function resolveExpiry(?string $period): ?string
{
    if (!$period || $period === 'permanent') return null;

    $map = [
        '3m' => '+3 months',
        '6m' => '+6 months',
        '1y' => '+1 year',
        '2y' => '+2 years',
    ];

    if (isset($map[$period])) {
        return date('Y-m-d', strtotime($map[$period]));
    }

    // Accept raw YYYY-MM-DD as fallback
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
        return $period;
    }

    return null;
}

function periodLabel(?string $period): string
{
    return match($period) {
        '3m'        => '3 mois',
        '6m'        => '6 mois',
        '1y'        => '1 an',
        '2y'        => '2 ans',
        'permanent' => 'Permanente',
        null        => 'Permanente',
        default     => $period,
    };
}

// ── Parse args ────────────────────────────────────────────────────────────────

$edition   = $argv[1] ?? null;
$period    = $argv[2] ?? 'permanent';
$machineId = $argv[3] ?? '*';

$validEditions = ['pro', 'enterprise'];
$validPeriods  = ['3m', '6m', '1y', '2y', 'permanent'];

if (!$edition || !in_array($edition, $validEditions, true)) {
    echo "\nUsage: php tools/keygen.php <edition> <periode> [machine_id]\n";
    echo "  edition   : pro | enterprise\n";
    echo "  periode   : 3m | 6m | 1y | 2y | permanent  (défaut: permanent)\n";
    echo "  machine_id: identifiant du poste (optionnel — '*' = tout poste)\n\n";
    echo "Exemples:\n";
    echo "  php tools/keygen.php pro 3m\n";
    echo "  php tools/keygen.php pro 1y\n";
    echo "  php tools/keygen.php enterprise permanent\n";
    echo "  php tools/keygen.php enterprise 2y a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4\n\n";
    exit(1);
}

if (!in_array($period, $validPeriods, true) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period)) {
    echo "Erreur : Période invalide. Valeurs acceptées : 3m | 6m | 1y | 2y | permanent\n";
    exit(1);
}

$expires = resolveExpiry($period);

// ── Generate ──────────────────────────────────────────────────────────────────

$key = LicenseService::generate(
    edition:   $edition,
    expires:   $expires,
    machineId: $machineId !== '*' ? $machineId : null,
);

$expiresLabel = $expires ? date('d/m/Y', strtotime($expires)) . ' (' . periodLabel($period) . ')' : 'Aucune (permanente)';
$machineLabel = ($machineId !== '*') ? $machineId : 'Tout poste (*)';
$editionLabel = strtoupper($edition);

// ── Output ────────────────────────────────────────────────────────────────────

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║        INVOICES PROJECT — CLÉ DE LICENCE             ║\n";
echo "╚══════════════════════════════════════════════════════╝\n";
echo "\n";
echo "  Édition     : {$editionLabel}\n";
echo "  Expiration  : {$expiresLabel}\n";
echo "  Poste       : {$machineLabel}\n";
echo "  Générée le  : " . date('d/m/Y H:i') . "\n";
echo "\n";
echo "  ── CLÉ ─────────────────────────────────────────────\n";
echo "\n";
echo "  {$key}\n";
echo "\n";
echo "  ────────────────────────────────────────────────────\n";
echo "\n";
echo "  Transmettez cette clé au client.\n";
echo "  Elle s'active sur /activate.php dans l'application.\n";
echo "\n";
