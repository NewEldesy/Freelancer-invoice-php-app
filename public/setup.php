<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\UserRepository;
use App\Auth\Auth;
use App\Services\LicenseService;

$repo = new UserRepository();

/* Already configured — redirect */
if ($repo->count() > 0) {
    header('Location: /login.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Superadmin credentials
    $saUser = trim($_POST['sa_username'] ?? '');
    $saEmail = trim($_POST['sa_email']   ?? '');
    $saPass  = $_POST['sa_password']     ?? '';
    $saConf  = $_POST['sa_confirm']      ?? '';

    // ── Admin credentials
    $adUser  = trim($_POST['ad_username'] ?? '');
    $adEmail = trim($_POST['ad_email']    ?? '');
    $adPass  = $_POST['ad_password']      ?? '';
    $adConf  = $_POST['ad_confirm']       ?? '';

    if ($saUser === '')         $errors[] = "Nom du compte superadmin obligatoire.";
    if ($saEmail === '')        $errors[] = "Email du compte superadmin obligatoire.";
    if (strlen($saPass) < 6)   $errors[] = "Mot de passe superadmin : minimum 6 caractères.";
    if ($saPass !== $saConf)   $errors[] = "Mots de passe superadmin ne correspondent pas.";
    if ($adUser === '')         $errors[] = "Nom du compte administrateur obligatoire.";
    if ($adEmail === '')        $errors[] = "Email du compte administrateur obligatoire.";
    if (strlen($adPass) < 6)   $errors[] = "Mot de passe administrateur : minimum 6 caractères.";
    if ($adPass !== $adConf)   $errors[] = "Mots de passe administrateur ne correspondent pas.";
    if ($saEmail === $adEmail)  $errors[] = "Les deux comptes doivent avoir des emails différents.";
    if ($saUser === $adUser)    $errors[] = "Les deux comptes doivent avoir des noms différents.";

    if (empty($errors)) {
        // Create superadmin
        $repo->create($saUser, $saEmail, $saPass, 'superadmin');

        // Create admin
        $repo->create($adUser, $adEmail, $adPass, 'admin');

        // Activate free plan
        LicenseService::activateFree();

        // Pre-generate Pro keys (5 total: 3m, 6m, 1y, 2y, permanent)
        $periods = ['3m', '6m', '1y', '2y', 'permanent'];
        foreach ($periods as $p) {
            LicenseService::generateAndStore('pro', $p);
        }

        // Pre-generate Enterprise keys (10 total: 2× each period)
        foreach ($periods as $p) {
            LicenseService::generateAndStore('enterprise', $p);
            LicenseService::generateAndStore('enterprise', $p);
        }

        header('Location: /login.php?setup=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configuration initiale — Invoices Project</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 24px; }
.box { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 24px rgba(0,0,0,.07); padding: 36px 40px; width: 100%; max-width: 560px; }
.logo { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
.logo-icon { width: 42px; height: 42px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.logo-name { font-size: 1.05rem; font-weight: 700; color: #0f172a; }
.logo-sub  { font-size: .72rem; color: #94a3b8; margin-top: 1px; }
.badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: .7rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; margin-bottom: 20px; }
.section-label {
  font-size: .65rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700;
  color: #fff; background: #0f172a; padding: 6px 14px; border-radius: 7px;
  margin: 20px 0 14px; display: inline-block;
}
.section-label.gold { background: linear-gradient(90deg, #d97706, #f59e0b); color: #0f172a; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field { margin-bottom: 12px; }
.field label { display: block; font-size: .73rem; font-weight: 600; color: #64748b; margin-bottom: 4px; }
.field input { width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: .84rem; font-family: inherit; outline: none; transition: border .15s; }
.field input:focus { border-color: #0f172a; box-shadow: 0 0 0 3px rgba(15,23,42,.08); }
.divider { border: none; border-top: 2px dashed #e2e8f0; margin: 24px 0; }
.btn { width: 100%; padding: 11px; background: #0f172a; color: #fff; border: none; border-radius: 9px; font-size: .9rem; font-weight: 700; cursor: pointer; font-family: inherit; margin-top: 8px; transition: background .15s; }
.btn:hover { background: #1e293b; }
.alert { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: .82rem; margin-bottom: 18px; }
.hint { font-size: .7rem; color: #94a3b8; margin-top: 8px; padding: 8px 12px; background: #f8fafc; border-radius: 7px; border: 1px solid #e2e8f0; }
.keygen-preview { margin-top: 18px; padding: 12px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 9px; font-size: .76rem; color: #166534; }
.keygen-preview strong { display: block; margin-bottom: 4px; font-size: .72rem; }
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <div class="logo-icon">🧾</div>
    <div>
      <div class="logo-name">Invoices Project</div>
      <div class="logo-sub">by ISSU DEV</div>
    </div>
  </div>
  <span class="badge">⚙️ Configuration initiale</span>

  <?php if (!empty($errors)): ?>
  <div class="alert">⚠ <?= implode('<br>⚠ ', array_map('htmlspecialchars', $errors)) ?></div>
  <?php endif; ?>

  <form method="POST">

    <!-- Superadmin -->
    <div class="section-label">🔑 Compte ISSU DEV (superadmin)</div>
    <p style="font-size:.75rem;color:#64748b;margin-bottom:14px">
      Ce compte gère les clés de licence. Gardez ces identifiants confidentiels.
    </p>
    <div class="grid-2">
      <div class="field">
        <label>Nom d'utilisateur</label>
        <input type="text" name="sa_username" value="<?= htmlspecialchars($_POST['sa_username'] ?? '') ?>" required autofocus>
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="sa_email" value="<?= htmlspecialchars($_POST['sa_email'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Mot de passe</label>
        <input type="password" name="sa_password" required>
      </div>
      <div class="field">
        <label>Confirmer</label>
        <input type="password" name="sa_confirm" required>
      </div>
    </div>

    <hr class="divider">

    <!-- Admin client -->
    <div class="section-label gold">👤 Compte administrateur client</div>
    <p style="font-size:.75rem;color:#64748b;margin-bottom:14px">
      Ce compte gère les utilisateurs de l'application (gestionnaires, utilisateurs).
    </p>
    <div class="grid-2">
      <div class="field">
        <label>Nom d'utilisateur</label>
        <input type="text" name="ad_username" value="<?= htmlspecialchars($_POST['ad_username'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="ad_email" value="<?= htmlspecialchars($_POST['ad_email'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label>Mot de passe</label>
        <input type="password" name="ad_password" required>
      </div>
      <div class="field">
        <label>Confirmer</label>
        <input type="password" name="ad_confirm" required>
      </div>
    </div>

    <div class="keygen-preview">
      <strong>✅ Clés générées automatiquement à l'initialisation :</strong>
      5 clés Pro (3m · 6m · 1y · 2y · permanent) &nbsp;+&nbsp;
      10 clés Entreprise (2× par période).<br>
      Plan <strong>Gratuit</strong> activé par défaut.
    </div>

    <button type="submit" class="btn">🚀 Initialiser l'application</button>
  </form>
</div>
</body>
</html>
