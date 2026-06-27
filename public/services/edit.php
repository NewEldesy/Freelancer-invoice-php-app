<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\ServiceRepository;

$repo    = new ServiceRepository();
$id      = (int) ($_GET['id'] ?? 0);
$service = $repo->find($id);

if ($service === null) {
    header('Location: /services/index.php');
    exit;
}

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
        $repo->update($id, $data);
        header('Location: /services/index.php?updated=1');
        exit;
    }

    $service = array_merge($service, $data);
}

$pageTitle     = 'Modifier — ' . htmlspecialchars($service['name']);
$currentPage   = 'services';
$topbarActions = '<a href="/services/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>';

require __DIR__ . '/../../templates/layout.php';
?>

<div class="card" style="max-width:520px">
  <div class="card-header"><h2>Modifier la prestation</h2></div>
  <div class="card-body">

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode('<br><i class="fa-solid fa-triangle-exclamation"></i> ', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-grid-2">
        <div class="field" style="grid-column:1/-1">
          <label>Nom de la prestation *</label>
          <input type="text" name="name" value="<?= htmlspecialchars($service['name']) ?>" required autofocus>
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Description *</label>
          <textarea name="description" rows="2" required style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:.84rem;font-family:inherit;resize:vertical"><?= htmlspecialchars($service['description']) ?></textarea>
        </div>
        <div class="field">
          <label>Prix unitaire (FCFA)</label>
          <input type="number" name="unit_price" value="<?= (int)$service['unit_price'] ?>" min="0" step="500">
        </div>
        <div class="field">
          <label>Catégorie</label>
          <select name="category">
            <?php foreach (ServiceRepository::CATEGORIES as $key => $label): ?>
            <option value="<?= $key ?>" <?= $service['category'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
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
