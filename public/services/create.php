<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\ServiceRepository;

$repo   = new ServiceRepository();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $data = [
        'name'        => trim($_POST['name']        ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'unit_price'  => (int) str_replace([' ', '\u{00A0}'], '', $_POST['unit_price'] ?? '0'),
        'category'    => array_key_exists($_POST['category'] ?? '', ServiceRepository::CATEGORIES)
                         ? $_POST['category']
                         : 'general',
    ];

    if ($data['name'] === '')        $errors[] = 'Le nom est obligatoire.';
    if ($data['description'] === '') $errors[] = 'La description est obligatoire.';

    if (empty($errors)) {
        $repo->create($data);
        header('Location: /services/index.php?created=1');
        exit;
    }
}

$pageTitle     = 'Nouvelle prestation';
$currentPage   = 'services';
$topbarActions = '<a href="/services/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>';

require __DIR__ . '/../../templates/layout.php';
?>

<div class="card" style="max-width:520px">
  <div class="card-header"><h2>Nouvelle prestation</h2></div>
  <div class="card-body">

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">⚠ <?= implode('<br>⚠ ', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-grid-2">
        <div class="field" style="grid-column:1/-1">
          <label>Nom de la prestation *</label>
          <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus placeholder="Ex: Développement site web">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Description (apparaît sur la facture) *</label>
          <textarea name="description" rows="2" required style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:.84rem;font-family:inherit;resize:vertical" placeholder="Ex: Conception et développement d'un site vitrine responsive"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="field">
          <label>Prix unitaire (FCFA)</label>
          <input type="number" name="unit_price" value="<?= (int)($_POST['unit_price'] ?? 0) ?>" min="0" step="500">
        </div>
        <div class="field">
          <label>Catégorie</label>
          <select name="category">
            <?php foreach (ServiceRepository::CATEGORIES as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($_POST['category'] ?? 'general') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <a href="/services/index.php" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
