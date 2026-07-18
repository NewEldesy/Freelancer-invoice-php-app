<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
use App\Database\Database;

Auth::requireSuperAdmin();

// Récupérer et valider les IDs
$raw = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $raw)), fn($id) => $id > 0);

if (empty($ids)) {
    header('Location: /superadmin/keys.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = Database::connection()->prepare(
    "SELECT * FROM license_keys WHERE id IN ($placeholders) ORDER BY edition ASC, period ASC"
);
$stmt->execute(array_values($ids));
$keys = $stmt->fetchAll();

if (empty($keys)) {
    header('Location: /superadmin/keys.php');
    exit;
}

$periodLabel = [
    '3m'        => '3 mois',
    '6m'        => '6 mois',
    '1y'        => '1 an',
    '2y'        => '2 ans',
    'permanent' => 'Permanente',
];

$today = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Clé d'activation <?= date('d-m-Y') ?></title>
<link rel="stylesheet" href="/assets/fa/css/all.min.css">
<style>
  @import url('/assets/fonts/inter.css');

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Inter', -apple-system, sans-serif;
    background: #f1f5f9;
    padding: 32px 24px;
    color: #0f172a;
  }

  /* Barre d'action (masquée à l'impression) */
  .no-print {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 28px;
    padding: 14px 20px;
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
  }
  .no-print .info { font-size: .84rem; color: #475569; flex: 1 }
  .no-print .info strong { color: #0f172a }
  .btn-print {
    background: #0f172a; color: #fff; border: none;
    padding: 10px 22px; border-radius: 8px; font-size: .88rem;
    font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;
  }
  .btn-print:hover { background: #1e293b }
  .btn-back {
    background: #fff; color: #64748b; border: 1px solid #e2e8f0;
    padding: 10px 18px; border-radius: 8px; font-size: .85rem;
    font-weight: 600; cursor: pointer; text-decoration: none;
    display: flex; align-items: center; gap: 7px;
  }
  .btn-back:hover { border-color: #94a3b8; color: #0f172a }

  /* Grille de cartes */
  .cards-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    max-width: 900px;
    margin: 0 auto;
  }

  /* Carte certificat */
  .cert-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
    page-break-inside: avoid;
    break-inside: avoid;
  }

  /* En-tête de la carte */
  .cert-header {
    background: #0f172a;
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 14px;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .cert-header.pro        { background: linear-gradient(135deg, #0f172a 60%, #1e3a5f); }
  .cert-header.enterprise { background: linear-gradient(135deg, #0f172a 60%, #3b1f6e); }

  .cert-logo {
    width: 44px; height: 44px; background: rgba(255,255,255,.12);
    border-radius: 10px; display: flex; align-items: center;
    justify-content: center; font-size: 20px; color: #f59e0b;
    flex-shrink: 0;
  }
  .cert-title { flex: 1 }
  .cert-app-name  { font-size: .95rem; font-weight: 800; color: #fff; }
  .cert-app-sub   { font-size: .68rem; color: rgba(255,255,255,.55); margin-top: 2px; }
  .cert-badge {
    font-size: .65rem; font-weight: 800; padding: 4px 10px;
    border-radius: 20px; letter-spacing: .5px; text-transform: uppercase;
    white-space: nowrap;
  }
  .cert-badge.pro        { background: #fef3c7; color: #92400e; }
  .cert-badge.enterprise { background: #ede9fe; color: #5b21b6; }

  /* Corps */
  .cert-body { padding: 20px 22px; }

  .cert-section-title {
    font-size: .62rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .7px; color: #94a3b8; margin-bottom: 10px;
  }

  .cert-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 7px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: .8rem;
  }
  .cert-row:last-child { border-bottom: none }
  .cert-row .lbl { color: #64748b; display: flex; align-items: center; gap: 7px }
  .cert-row .lbl i { width: 14px; text-align: center; color: #94a3b8 }
  .cert-row .val { font-weight: 600; color: #0f172a }

  /* Clé de licence */
  .cert-key-block {
    margin-top: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
  }
  .cert-key-label {
    font-size: .62rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .7px; color: #94a3b8; margin-bottom: 8px;
  }
  .cert-key-value {
    font-family: 'Courier New', monospace;
    font-size: .7rem;
    color: #0f172a;
    word-break: break-all;
    line-height: 1.6;
    font-weight: 600;
  }

  /* Pied de carte */
  .cert-footer {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 12px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .cert-footer .note { font-size: .67rem; color: #94a3b8 }
  .cert-footer .date { font-size: .67rem; color: #94a3b8 }

  /* Instructions d'activation */
  .cert-steps {
    margin-top: 14px;
    padding: 12px 14px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 8px;
  }
  .cert-steps-title {
    font-size: .65rem; font-weight: 700; color: #166534;
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px;
  }
  .cert-steps ol {
    padding-left: 16px; margin: 0;
  }
  .cert-steps ol li {
    font-size: .72rem; color: #15803d; line-height: 1.7;
  }

  /* ── Styles d'impression ── */
  @media print {
    @page {
      size: A4;
      margin: 12mm 10mm;
    }
    body {
      background: #fff;
      padding: 0;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .no-print { display: none !important; }
    .cards-grid {
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      max-width: 100%;
    }
    .cert-card {
      box-shadow: none;
      border: 1px solid #cbd5e1;
      border-radius: 8px;
    }
    .cert-header { padding: 14px 16px; }
    .cert-body   { padding: 14px 16px; }
    .cert-footer { padding: 10px 16px; }
    .cert-logo   { width: 36px; height: 36px; font-size: 16px; }
    .cert-key-value { font-size: .6rem; }
    .cert-steps { margin-top: 16px; }
  }
</style>
</head>
<body>

<!-- Barre d'action (non imprimée) -->
<div class="no-print">
  <a href="/superadmin/keys.php" class="btn-back">
    <i class="fa-solid fa-arrow-left"></i> Retour
  </a>
  <div class="info">
    <strong><?= count($keys) ?> certificat<?= count($keys) > 1 ? 's' : '' ?></strong> prêt<?= count($keys) > 1 ? 's' : '' ?> à imprimer
  </div>
  <button onclick="window.print()" class="btn-print">
    <i class="fa-solid fa-print"></i> Lancer l'impression
  </button>
</div>

<!-- Grille de certificats -->
<div class="cards-grid">
<?php foreach ($keys as $k): ?>
<?php
  $isPro = $k['edition'] === 'pro';
  $editionLabel = $isPro ? 'Pro' : 'Entreprise';
  $period = $periodLabel[$k['period']] ?? $k['period'];
  $genDate = $k['generated_at'] ? date('d/m/Y', strtotime($k['generated_at'])) : $today;
?>
<div class="cert-card">

  <!-- En-tête -->
  <div class="cert-header <?= $k['edition'] ?>">
    <div class="cert-logo">
      <i class="fa-solid fa-file-invoice-dollar"></i>
    </div>
    <div class="cert-title">
      <div class="cert-app-name">Freelancer-invoice</div>
      <div class="cert-app-sub">by ISSU DEV &nbsp;·&nbsp; Certificat de licence</div>
    </div>
    <div class="cert-badge <?= $k['edition'] ?>">
      <?= $isPro ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-solid fa-building"></i>' ?>
      <?= htmlspecialchars($editionLabel) ?>
    </div>
  </div>

  <!-- Corps -->
  <div class="cert-body">

    <div class="cert-section-title"><i class="fa-solid fa-circle-info"></i> Détails de la licence</div>

    <div class="cert-row">
      <span class="lbl"><i class="fa-solid fa-cube"></i> Édition</span>
      <span class="val"><?= htmlspecialchars($editionLabel) ?></span>
    </div>
    <div class="cert-row">
      <span class="lbl"><i class="fa-solid fa-clock"></i> Durée</span>
      <span class="val"><?= htmlspecialchars($period) ?></span>
    </div>
    <div class="cert-row">
      <span class="lbl"><i class="fa-solid fa-calendar-plus"></i> Émise le</span>
      <span class="val"><?= htmlspecialchars($genDate) ?></span>
    </div>
    <div class="cert-row">
      <span class="lbl"><i class="fa-solid fa-shield-halved"></i> Statut</span>
      <span class="val" style="color:<?= $k['used'] ? '#dc2626' : '#059669' ?>">
        <?= $k['used'] ? 'Utilisée' : 'Disponible' ?>
      </span>
    </div>

    <!-- Clé -->
    <div class="cert-key-block">
      <div class="cert-key-label"><i class="fa-solid fa-key"></i> Clé de licence</div>
      <div class="cert-key-value"><?= htmlspecialchars($k['key_value']) ?></div>
    </div>


  </div>

  <!-- Pied -->
  <div class="cert-footer">
    <span class="note"><i class="fa-solid fa-triangle-exclamation" style="color:#dc2626"></i> Clé à usage unique — à conserver précieusement</span>
    <span class="date">Émis le <?= htmlspecialchars($today) ?></span>
  </div>

</div>
<?php endforeach; ?>
</div>

<!-- Instructions d'activation — une seule fois en bas de page -->
<div class="cert-steps" style="max-width:900px;margin:28px auto 0;-webkit-print-color-adjust:exact;print-color-adjust:exact">
  <div class="cert-steps-title"><i class="fa-solid fa-circle-check"></i> Comment activer votre licence</div>
  <table style="width:100%;border-collapse:collapse;table-layout:fixed">
    <tr>
      <td style="width:25%;padding:8px 12px 4px 0;vertical-align:top">
        <div style="display:flex;align-items:center;gap:8px;font-size:.75rem;color:#15803d;font-weight:600">
          <span style="background:#166534;color:#fff;border-radius:50%;width:20px;height:20px;min-width:20px;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700">1</span>
          Ouvrez <strong style="margin-left:3px">Freelancer-invoice</strong>
        </div>
        <div style="font-size:.7rem;color:#4ade80;margin-top:2px;padding-left:28px">et connectez-vous</div>
      </td>
      <td style="width:25%;padding:8px 12px 4px 0;vertical-align:top">
        <div style="display:flex;align-items:center;gap:8px;font-size:.75rem;color:#15803d;font-weight:600">
          <span style="background:#166534;color:#fff;border-radius:50%;width:20px;height:20px;min-width:20px;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700">2</span>
          Allez dans
        </div>
        <div style="font-size:.7rem;color:#15803d;margin-top:2px;padding-left:28px;font-weight:700">Paramètres → Licence</div>
      </td>
      <td style="width:25%;padding:8px 12px 4px 0;vertical-align:top">
        <div style="display:flex;align-items:center;gap:8px;font-size:.75rem;color:#15803d;font-weight:600">
          <span style="background:#166534;color:#fff;border-radius:50%;width:20px;height:20px;min-width:20px;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700">3</span>
          Cliquez sur
        </div>
        <div style="font-size:.7rem;color:#15803d;margin-top:2px;padding-left:28px;font-weight:700">Changer de plan / Activer une clé</div>
      </td>
      <td style="width:25%;padding:8px 0 4px 0;vertical-align:top">
        <div style="display:flex;align-items:center;gap:8px;font-size:.75rem;color:#15803d;font-weight:600">
          <span style="background:#166534;color:#fff;border-radius:50%;width:20px;height:20px;min-width:20px;display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700">4</span>
          Collez la clé et validez
        </div>
      </td>
    </tr>
  </table>
</div>

</body>
</html>
