<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Auth;
use App\Services\LicenseService;

Auth::check(); // redirects to /login.php if not authenticated

$error   = '';
$success = '';
$expired = isset($_GET['expired']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'free') {
        LicenseService::activateFree();
        header('Location: /index.php'); exit;
    }

    if ($action === 'activate') {
        $key    = trim($_POST['license_key'] ?? '');
        $result = LicenseService::activate($key);

        if ($result['success']) {
            $editionLabel = match($result['edition']) {
                'pro'        => 'Pro',
                'enterprise' => 'Entreprise',
                default      => ucfirst($result['edition']),
            };
            header('Location: /index.php?activated=' . urlencode($editionLabel)); exit;
        }
        $error = $result['error'];
    }
}

$machineId = LicenseService::machineId();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activation — Freelancer-invoice</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #f1f5f9;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .wrapper { width: 100%; max-width: 560px; }

  .brand {
    text-align: center; margin-bottom: 28px;
  }
  .brand-logo {
    width: 52px; height: 52px; background: #0f172a;
    border-radius: 14px; display: inline-flex; align-items: center;
    justify-content: center; margin-bottom: 10px;
    font-size: 22px;
  }
  .brand-name { font-size: 1.15rem; font-weight: 800; color: #0f172a; }
  .brand-sub  { font-size: .75rem; color: #64748b; margin-top: 2px; }

  .card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 24px rgba(15,23,42,.08);
    overflow: hidden;
  }
  .card-header {
    background: #0f172a; padding: 22px 28px;
    color: #fff;
  }
  .card-header h1 { font-size: 1.1rem; font-weight: 700; }
  .card-header p  { font-size: .78rem; color: rgba(255,255,255,.65); margin-top: 4px; }

  <?php if ($expired): ?>
  .card-header { background: linear-gradient(135deg, #7f1d1d, #991b1b); }
  <?php endif; ?>

  .card-body { padding: 28px; }

  .alert-error {
    background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
    border-radius: 8px; padding: 12px 14px; font-size: .82rem;
    margin-bottom: 18px; display: flex; align-items: center; gap: 8px;
  }

  /* Plans grid */
  .plans { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 22px; }
  .plan {
    border: 2px solid #e2e8f0; border-radius: 12px; padding: 18px;
    cursor: pointer; transition: all .15s; position: relative;
  }
  .plan:hover { border-color: #94a3b8; }
  .plan.highlight { border-color: #0f172a; }
  .plan-badge {
    position: absolute; top: -10px; left: 50%; transform: translateX(-50%);
    background: #0f172a; color: #fff; font-size: .6rem; font-weight: 700;
    padding: 2px 10px; border-radius: 20px; letter-spacing: .5px;
    text-transform: uppercase; white-space: nowrap;
  }
  .plan-name  { font-size: .9rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
  .plan-price { font-size: 1.3rem; font-weight: 800; color: #0f172a; }
  .plan-price span { font-size: .7rem; font-weight: 400; color: #64748b; }
  .plan-features { margin-top: 10px; }
  .plan-features li {
    font-size: .73rem; color: #64748b; list-style: none;
    padding: 2px 0; display: flex; align-items: center; gap: 5px;
  }
  .plan-features li::before { content: '✓'; color: #059669; font-weight: 700; }
  .plan-features li.no::before { content: '×'; color: #94a3b8; }

  /* Key input section */
  .divider {
    display: flex; align-items: center; gap: 10px;
    font-size: .72rem; color: #94a3b8; margin: 20px 0;
  }
  .divider::before, .divider::after {
    content: ''; flex: 1; height: 1px; background: #e2e8f0;
  }

  label  { font-size: .78rem; font-weight: 600; color: #374151; display: block; margin-bottom: 6px; }
  input[type=text] {
    width: 100%; padding: 11px 14px; border: 1.5px solid #e2e8f0;
    border-radius: 9px; font-size: .84rem; color: #0f172a;
    font-family: 'Courier New', monospace; letter-spacing: .05em;
    outline: none; transition: border-color .15s;
  }
  input[type=text]:focus { border-color: #0f172a; }

  .btn-row { display: flex; gap: 10px; margin-top: 14px; }
  .btn {
    flex: 1; padding: 12px; border-radius: 9px; font-size: .85rem;
    font-weight: 700; border: none; cursor: pointer; transition: all .15s;
  }
  .btn-primary { background: #0f172a; color: #fff; }
  .btn-primary:hover { background: #1e293b; }
  .btn-outline  { background: #fff; color: #64748b; border: 1.5px solid #e2e8f0; }
  .btn-outline:hover  { border-color: #94a3b8; color: #374151; }

  .machine-id {
    margin-top: 20px; padding: 10px 14px; background: #f8fafc;
    border-radius: 8px; font-size: .7rem; color: #94a3b8;
    border: 1px solid #e2e8f0;
  }
  .machine-id code { font-family: monospace; color: #475569; font-size: .72rem; }

  .footer-note {
    text-align: center; margin-top: 18px; font-size: .7rem; color: #94a3b8;
  }
</style>
</head>
<body>

<div class="wrapper">
  <div class="brand">
    <div class="brand-logo">📋</div>
    <div class="brand-name">Freelancer-invoice</div>
    <div class="brand-sub">© ISSU DEV</div>
  </div>

  <div class="card">
    <div class="card-header">
      <?php if ($expired): ?>
      <h1>🔴 Votre licence a expiré</h1>
      <p>Renouvelez votre abonnement ou continuez en version gratuite.</p>
      <?php else: ?>
      <h1>Activation du logiciel</h1>
      <p>Choisissez votre plan ou entrez une clé de licence.</p>
      <?php endif; ?>
    </div>
    <div class="card-body">

      <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- Plans -->
      <div class="plans">
        <div class="plan">
          <div class="plan-name">Gratuit</div>
          <div class="plan-price">0 <span>FCFA / toujours</span></div>
          <ul class="plan-features">
            <li>Factures (10 max)</li>
            <li>Duplications (5 max)</li>
            <li>Export PDF (15 max)</li>
            <li>Pipeline (5 max)</li>
            <li>Dépenses (5 max)</li>
            <li>Export Excel (15 max)</li>
            <li class="no">Comptabilité</li>
            <li class="no">Rapport annuel</li>
            <li class="no">Multi-utilisateurs</li>
          </ul>
        </div>
        <div class="plan highlight">
          <div class="plan-badge">⭐ Recommandé</div>
          <div class="plan-name">Pro</div>
          <div class="plan-price">— <span>clé requise</span></div>
          <ul class="plan-features">
            <li>Factures illimitées</li>
            <li>Pipeline illimité</li>
            <li>Dépenses illimitées</li>
            <li>Comptabilité complète</li>
            <li>Rapport annuel N vs N-1</li>
            <li>Export Excel / PDF illimité</li>
            <li>Multi-utilisateurs</li>
          </ul>
        </div>
      </div>

      <!-- Free activation -->
      <form method="POST" action="/activate.php">
        <input type="hidden" name="action" value="free">
        <button type="submit" class="btn btn-outline" style="width:100%;margin-bottom:4px">
          Continuer en version gratuite →
        </button>
      </form>

      <div class="divider">J'ai une clé de licence</div>

      <!-- Key activation -->
      <form method="POST" action="/activate.php">
        <input type="hidden" name="action" value="activate">
        <label for="license_key">Clé de licence Pro / Entreprise</label>
        <input
          type="text"
          id="license_key"
          name="license_key"
          placeholder="XXXX...XXXX.YYYYYYYY"
          autocomplete="off"
          spellcheck="false"
        >
        <div class="btn-row">
          <button type="submit" class="btn btn-primary">Activer →</button>
        </div>
      </form>

      <div class="machine-id">
        Identifiant de ce poste : <code><?= htmlspecialchars($machineId) ?></code>
        <br>Communiquez cet identifiant pour obtenir une clé liée à ce poste.
      </div>

    </div>
  </div>

  <div class="footer-note">Freelancer-invoice · © ISSU DEV · Tous droits réservés</div>
</div>

</body>
</html>
