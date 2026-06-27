<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\ClientRepository;

$repo   = new ClientRepository();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $data = [
        'name'    => trim($_POST['name']    ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'contact' => trim($_POST['contact'] ?? ''),
        'email'   => trim($_POST['email']   ?? ''),
        'phone'   => trim($_POST['phone']   ?? ''),
        'notes'   => trim($_POST['notes']   ?? ''),
    ];

    if ($data['name'] === '') $errors[] = 'Le nom du client est obligatoire.';
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse email est invalide.';
    }

    if (empty($errors)) {
        $repo->create($data);
        header('Location: /client/index.php?created=1');
        exit;
    }
}

$pageTitle     = 'Nouveau client';
$currentPage   = 'clients';
$topbarActions = '<a href="/client/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>';

require __DIR__ . '/../../templates/layout.php';
?>

<div class="card" style="max-width:560px">
  <div class="card-header"><h2>Nouveau client</h2></div>
  <div class="card-body">

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">⚠ <?= implode('<br>⚠ ', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-grid-2">
        <div class="field" style="grid-column:1/-1">
          <label>Nom / Raison sociale *</label>
          <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus>
        </div>
        <div class="field">
          <label>Personne de contact</label>
          <input type="text" name="contact" value="<?= htmlspecialchars($_POST['contact'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Téléphone</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Adresse</label>
          <textarea name="address" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:.84rem;font-family:inherit;resize:vertical"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Notes internes</label>
          <textarea name="notes" rows="2" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:.84rem;font-family:inherit;resize:vertical"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <a href="/client/index.php" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
