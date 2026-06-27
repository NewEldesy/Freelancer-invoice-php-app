<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\OpportunityRepository;

$repo   = new OpportunityRepository();
$id     = (int) ($_GET['id'] ?? 0);
$record = $repo->find($id);

if ($record === null) {
    header('Location: /pipeline/index.php');
    exit;
}

$errors       = [];
$flashSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title'            => trim($_POST['title']            ?? ''),
        'client_name'      => trim($_POST['client_name']      ?? ''),
        'client_address'   => trim($_POST['client_address']   ?? ''),
        'client_contact'   => trim($_POST['client_contact']   ?? ''),
        'description'      => trim($_POST['description']      ?? ''),
        'estimated_amount' => (int) ($_POST['estimated_amount'] ?? 0),
        'status'           => $_POST['status']                ?? 'prospect',
        'source'           => trim($_POST['source']           ?? ''),
        'notes'            => trim($_POST['notes']            ?? ''),
        'expected_close'   => $_POST['expected_close']        ?? '',
    ];

    if ($data['title'] === '') {
        $errors[] = 'Le titre est obligatoire.';
    }

    if (empty($errors)) {
        $repo->update($id, $data);
        $record       = $repo->find($id);
        $flashSuccess = 'Opportunité mise à jour.';
    }
}

$d = $record;

$pageTitle   = 'Modifier — ' . htmlspecialchars($record['title']);
$currentPage = 'pipeline';
$topbarActions = '
  <a href="/pipeline/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour pipeline</a>
';

require __DIR__ . '/../../templates/layout.php';
?>

<div class="card">
  <div class="card-header"><h2>Modifier l'opportunité</h2></div>
  <div class="card-body">
    <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode('<br><i class="fa-solid fa-triangle-exclamation"></i> ', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <?php if ($d['invoice_id']): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Opportunité convertie — <a href="/invoice/edit.php?id=<?= $d['invoice_id'] ?>" style="font-weight:600">Voir la facture</a></div>
    <?php endif; ?>

    <form method="POST">
      <div class="section-title"><i class="fa-solid fa-bullseye"></i> Opportunité</div>
      <div class="form-grid-2" style="margin-bottom:16px">
        <div class="field" style="grid-column:1/-1">
          <label>Titre *</label>
          <input type="text" name="title" value="<?= htmlspecialchars($d['title']) ?>" required>
        </div>
        <div class="field">
          <label>Statut</label>
          <select name="status">
            <?php foreach (['prospect'=>'Prospect','devis_envoye'=>'Devis envoyé','negociation'=>'Négociation','gagne'=>'Gagné','perdu'=>'Perdu'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $d['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Montant estimé (FCFA)</label>
          <input type="number" name="estimated_amount" value="<?= (int)$d['estimated_amount'] ?>" min="0" step="1000">
        </div>
        <div class="field">
          <label>Source</label>
          <input type="text" name="source" value="<?= htmlspecialchars($d['source'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Date de clôture prévue</label>
          <input type="date" name="expected_close" value="<?= htmlspecialchars($d['expected_close'] ?? '') ?>">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Description</label>
          <textarea name="description" rows="3"><?= htmlspecialchars($d['description'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="section-title"><i class="fa-solid fa-user"></i> Client prospect</div>
      <div class="form-grid-2" style="margin-bottom:16px">
        <div class="field">
          <label>Nom / Entreprise</label>
          <input type="text" name="client_name" value="<?= htmlspecialchars($d['client_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Contact</label>
          <input type="text" name="client_contact" value="<?= htmlspecialchars($d['client_contact'] ?? '') ?>">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Adresse</label>
          <input type="text" name="client_address" value="<?= htmlspecialchars($d['client_address'] ?? '') ?>">
        </div>
      </div>

      <div class="section-title"><i class="fa-solid fa-note-sticky"></i> Notes internes</div>
      <div class="field" style="margin-bottom:20px">
        <textarea name="notes" rows="3"><?= htmlspecialchars($d['notes'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <button onclick="deleteOpp()" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Supprimer</button>
      </div>
    </form>

    <?php if (!$d['invoice_id']): ?>
    <form method="POST" action="/pipeline/convert.php" style="margin-top:12px">
      <input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" class="btn btn-success"><i class="fa-solid fa-file-invoice"></i> Convertir en facture</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<form id="del-form" method="POST" action="/pipeline/delete.php" style="display:none">
  <input type="hidden" name="id" value="<?= $id ?>">
</form>

<script>
function deleteOpp() {
  if (confirm('Supprimer cette opportunité ?')) document.getElementById('del-form').submit();
}
</script>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
