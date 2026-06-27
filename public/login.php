<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\UserRepository;
use App\Auth\Auth;

Auth::start();

/* Already logged in */
if (!empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$repo  = new UserRepository();

/* No users yet → setup */
if ($repo->count() === 0) {
    header('Location: /setup.php');
    exit;
}

$error  = null;
$setup  = isset($_GET['setup']);
$logout = isset($_GET['logout']);

// Rate limiting : max 10 tentatives par tranche de 15 minutes (stocké en session)
$rlKey    = 'login_attempts';
$rlWindow = 'login_window';
$rlMax    = 10;
$rlTtl    = 900; // 15 min

if (!isset($_SESSION[$rlWindow]) || time() > $_SESSION[$rlWindow]) {
    $_SESSION[$rlKey]    = 0;
    $_SESSION[$rlWindow] = time() + $rlTtl;
}
$tooManyAttempts = $_SESSION[$rlKey] >= $rlMax;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($tooManyAttempts) {
        $wait = ceil(($_SESSION[$rlWindow] - time()) / 60);
        $error = "Trop de tentatives. Réessayez dans {$wait} minute(s).";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = $repo->verify($username, $password);
        if ($user !== null) {
            $_SESSION[$rlKey] = 0; // reset on success
            Auth::login($user);
            header('Location: /index.php');
            exit;
        }
        $_SESSION[$rlKey]++;
        $error = "Identifiant ou mot de passe incorrect.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Connexion — Invoices Project</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
.box { background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 4px 24px rgba(0,0,0,.07); padding: 40px; width: 100%; max-width: 400px; }
.logo { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; }
.logo-icon { width: 42px; height: 42px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 2px 8px rgba(245,158,11,.3); }
.logo-name { font-size: 1.05rem; font-weight: 700; color: #0f172a; }
.logo-sub  { font-size: .72rem; color: #94a3b8; margin-top: 1px; }
h1 { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
.sub { font-size: .82rem; color: #64748b; margin-bottom: 26px; }
.field { margin-bottom: 14px; }
.field label { display: block; font-size: .75rem; font-weight: 600; color: #64748b; margin-bottom: 5px; }
.field input { width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: .84rem; font-family: inherit; outline: none; transition: border .15s, box-shadow .15s; color: #1e293b; }
.field input:focus { border-color: #f59e0b; box-shadow: 0 0 0 3px rgba(245,158,11,.12); }
.btn { width: 100%; padding: 10px; background: #f59e0b; color: #0f172a; border: none; border-radius: 8px; font-size: .9rem; font-weight: 700; cursor: pointer; font-family: inherit; margin-top: 8px; transition: background .15s, transform .15s; }
.btn:hover { background: #d97706; transform: translateY(-1px); }
.alert-error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: .82rem; margin-bottom: 18px; }
.alert-success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 10px 14px; border-radius: 8px; font-size: .82rem; margin-bottom: 18px; }
.foot { text-align: center; font-size: .7rem; color: #94a3b8; margin-top: 24px; }
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

  <h1>Connexion</h1>
  <div class="sub">Accédez à votre espace de gestion</div>

  <?php if ($setup): ?>
  <div class="alert-success">✓ Compte administrateur créé. Connectez-vous.</div>
  <?php endif; ?>
  <?php if ($logout): ?>
  <div class="alert-success">✓ Vous avez été déconnecté.</div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!--
    Anti-autofill: champs visibles avec noms aléatoires + autocomplete=off.
    Le mot de passe est rendu en type="text" puis converti en "password" par JS
    après le chargement (les navigateurs ne détectent pas le champ à ce stade).
    Au submit, JS copie les valeurs dans les vrais champs cachés.
  -->
  <form method="POST" id="login-form" autocomplete="off">
    <!-- Vrais champs soumis au serveur, cachés -->
    <input type="hidden" name="username" id="real-username">
    <input type="hidden" name="password" id="real-password">

    <!-- Leurre pour tromper l'autofill du navigateur -->
    <input type="text"  name="x_uid_<?= substr(md5('u'.date('d')), 0, 6) ?>" style="display:none" tabindex="-1" autocomplete="off">
    <input type="text" name="x_pwd_<?= substr(md5('p'.date('d')), 0, 6) ?>" style="display:none" tabindex="-1" autocomplete="off">

    <div class="field">
      <label>Nom d'utilisateur</label>
      <input type="text" id="vis-username"
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
             autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
             required autofocus>
    </div>
    <div class="field">
      <label>Mot de passe</label>
      <!-- Commence en type="text", converti en "password" par JS -->
      <input type="text" id="vis-password" autocomplete="off" required>
    </div>
    <button type="submit" class="btn">Se connecter</button>
  </form>

  <script>
    // Convertir le champ mot de passe en type "password" après chargement
    document.getElementById('vis-password').type = 'password';

    // Copier les valeurs dans les champs cachés avant soumission
    document.getElementById('login-form').addEventListener('submit', function() {
      document.getElementById('real-username').value = document.getElementById('vis-username').value;
      document.getElementById('real-password').value = document.getElementById('vis-password').value;
    });
  </script>

  <div class="foot">© ISSU DEV — Invoices Project</div>
</div>
</body>
</html>
