<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Auth\Auth;
use App\Database\UserRepository;
use App\Services\LicenseService;

Auth::requireAdmin();

$repo   = new UserRepository();
$errors = [];

// Free plan: only 1 non-admin user allowed
$userLocked = false;
if (!LicenseService::canMultiUsers()) {
    $nonAdminCount = count(array_filter($repo->all(), fn($u) => $u['role'] !== 'admin'));
    if ($nonAdminCount >= 1) {
        $userLocked = true;
    }
}
if ($userLocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors[] = 'Le plan gratuit est limité à 1 utilisateur. Activez une licence Pro pour ajouter des utilisateurs.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $role     = in_array($_POST['role'] ?? '', ['gestionnaire', 'utilisateur', 'admin'], true)
                ? $_POST['role']
                : 'utilisateur';

    if ($username === '')                              $errors[] = "Le nom d'utilisateur est obligatoire.";
    if ($email === '')                                 $errors[] = "L'email est obligatoire.";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'adresse email est invalide.";
    if (strlen($password) < 6)                         $errors[] = "Le mot de passe doit faire au moins 6 caractères.";
    if ($password !== $confirm) $errors[] = "Les mots de passe ne correspondent pas.";
    if ($repo->findByUsername($username) !== null) $errors[] = "Ce nom d'utilisateur est déjà pris.";

    if (empty($errors)) {
        $repo->create($username, $email, $password, $role);
        header('Location: /admin/users.php?created=1');
        exit;
    }
}

$pageTitle     = 'Nouvel utilisateur';
$currentPage   = 'admin_users';
$topbarActions = '<a href="/admin/users.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>';

require __DIR__ . '/../../../templates/layout.php';
?>

<div class="card" style="max-width:480px">
  <div class="card-header"><h2>Créer un utilisateur</h2></div>
  <div class="card-body">
    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">⚠ <?= implode('<br>⚠ ', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-grid-2" style="margin-bottom:16px">
        <div class="field">
          <label>Nom d'utilisateur *</label>
          <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
        </div>
        <div class="field">
          <label>Rôle</label>
          <select name="role">
            <option value="gestionnaire" <?= ($_POST['role'] ?? '') === 'gestionnaire' ? 'selected' : '' ?>>Gestionnaire</option>
            <option value="utilisateur"  <?= ($_POST['role'] ?? 'utilisateur') === 'utilisateur' ? 'selected' : '' ?>>Utilisateur (lecture)</option>
            <option value="admin"        <?= ($_POST['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
          </select>
          <span style="font-size:.7rem;color:var(--muted-light)">
            Gestionnaire = accès complet · Utilisateur = lecture seule · Admin = gestion des comptes uniquement
          </span>
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Email *</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="field">
          <label>Mot de passe * <span style="color:var(--muted-light);font-weight:400">(min. 6 car.)</span></label>
          <input type="password" name="password" required>
        </div>
        <div class="field">
          <label>Confirmer</label>
          <input type="password" name="confirm" required>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary">💾 Créer l'utilisateur</button>
        <a href="/admin/users.php" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../../../templates/layout_end.php'; ?>
