<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\ProjectRepository;
use App\Database\ExpenseRepository;

$repo    = new ProjectRepository();
$expRepo = new ExpenseRepository();

$id      = (int) ($_GET['id'] ?? 0);
$project = $repo->find($id);

if ($project === null) {
    header('Location: /project/index.php');
    exit;
}

$flashSuccess = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title'      => trim($_POST['title']      ?? ''),
        'status'     => $_POST['status']          ?? 'non_commence',
        'start_date' => $_POST['start_date']      ?? '',
        'end_date'   => $_POST['end_date']        ?? '',
        'notes'      => trim($_POST['notes']      ?? ''),
    ];

    if ($data['title'] === '') $errors[] = 'Le titre est obligatoire.';

    if (empty($errors)) {
        $repo->update($id, $data);
        $project      = $repo->find($id);
        $flashSuccess = 'Projet mis à jour.';
    }
}

$expenses = $expRepo->allForInvoice((int)$project['invoice_id']);
$totalExp = array_sum(array_column($expenses, 'amount'));
$marge    = (int)$project['total_net'] - $totalExp;

$pageTitle   = 'Projet — ' . htmlspecialchars($project['title']);
$currentPage = 'projects';
$topbarActions = '
  <a href="/expense/create.php?invoice_id=' . $project['invoice_id'] . '" class="btn btn-secondary"><i class="fa-solid fa-plus"></i> Ajouter dépense</a>
  <a href="/project/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
';

require __DIR__ . '/../../templates/layout.php';
?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start">

  <!-- Formulaire -->
  <div class="card">
    <div class="card-header"><h2>Détails du projet</h2></div>
    <div class="card-body">
      <?php if (!empty($errors)): ?>
      <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode('<br><i class="fa-solid fa-triangle-exclamation"></i> ', array_map('htmlspecialchars', $errors)) ?></div>
      <?php endif; ?>

      <div style="margin-bottom:14px;padding:12px 16px;background:var(--bg);border-radius:8px;border:1px solid var(--border)">
        <div style="font-size:.72rem;color:var(--muted);margin-bottom:4px">Facture liée</div>
        <a href="/invoice/edit.php?id=<?= $project['invoice_id'] ?>"
           style="font-weight:700;color:var(--navy);text-decoration:none">
          <?= htmlspecialchars($project['invoice_number']) ?>
        </a>
        <span style="color:var(--muted);font-size:.8rem"> · <?= htmlspecialchars($project['client_name'] ?: '—') ?></span>
        <span style="font-weight:600;color:var(--green);float:right"><?= number_format((int)$project['total_net'], 0, ',', ' ') ?> FCFA</span>
      </div>

      <form method="POST">
        <div class="form-grid-2" style="margin-bottom:16px">
          <div class="field" style="grid-column:1/-1">
            <label>Titre du projet</label>
            <input type="text" name="title" value="<?= htmlspecialchars($project['title']) ?>" required>
          </div>
          <div class="field">
            <label>Statut</label>
            <select name="status">
              <?php foreach (['non_commence'=>'Non commencé','en_cours'=>'En cours','livre'=>'Livré','valide'=>'Validé client'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= $project['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><!-- spacer --></div>
          <div class="field">
            <label>Date de début</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($project['start_date'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Date de fin prévue</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($project['end_date'] ?? '') ?>">
          </div>
          <div class="field" style="grid-column:1/-1">
            <label>Notes</label>
            <textarea name="notes" rows="4"><?= htmlspecialchars($project['notes'] ?? '') ?></textarea>
          </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
      </form>
    </div>
  </div>

  <!-- Colonne droite : finances -->
  <div style="display:flex;flex-direction:column;gap:12px">

    <!-- Résumé financier -->
    <div class="card">
      <div class="card-header"><h2>Rentabilité</h2></div>
      <div class="card-body" style="padding:14px 16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
          <span style="font-size:.8rem;color:var(--muted)">CA (facturé)</span>
          <span style="font-weight:700;color:var(--navy)"><?= number_format((int)$project['total_net'], 0, ',', ' ') ?> FCFA</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
          <span style="font-size:.8rem;color:var(--muted)">Coûts totaux</span>
          <span style="font-weight:600;color:var(--red)">−<?= number_format($totalExp, 0, ',', ' ') ?> FCFA</span>
        </div>
        <div style="border-top:2px solid var(--border);padding-top:10px;display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:.85rem;font-weight:700">Bénéfice net</span>
          <span style="font-size:1.1rem;font-weight:800;color:<?= $marge >= 0 ? '#059669' : '#dc2626' ?>">
            <?= number_format($marge, 0, ',', ' ') ?> FCFA
          </span>
        </div>
        <?php if ((int)$project['total_net'] > 0): ?>
        <?php $pct = round($marge / (int)$project['total_net'] * 100, 1); ?>
        <div style="margin-top:10px;font-size:.75rem;text-align:center;color:var(--muted)">
          Marge : <strong style="color:<?= $pct >= 0 ? '#059669' : '#dc2626' ?>"><?= $pct ?>%</strong>
        </div>
        <?php endif; ?>
        <a href="/expense/create.php?invoice_id=<?= $project['invoice_id'] ?>"
           class="btn btn-secondary" style="width:100%;margin-top:12px;justify-content:center">
          <i class="fa-solid fa-plus"></i> Ajouter une dépense
        </a>
      </div>
    </div>

    <!-- Liste dépenses -->
    <?php if (!empty($expenses)): ?>
    <div class="card">
      <div class="card-header"><h2>Dépenses</h2></div>
      <div class="card-body" style="padding:0">
        <?php foreach ($expenses as $exp): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border-soft)">
          <div>
            <div style="font-size:.8rem;font-weight:500"><?= htmlspecialchars($exp['description']) ?></div>
            <div style="font-size:.7rem;color:var(--muted)"><?= htmlspecialchars(App\Database\ExpenseRepository::CATEGORIES[$exp['category']] ?? $exp['category']) ?><?= $exp['date'] ? ' · ' . date('d/m/Y', strtotime($exp['date'])) : '' ?></div>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <span style="font-weight:700;font-size:.85rem;color:var(--red)">−<?= number_format((int)$exp['amount'], 0, ',', ' ') ?></span>
            <form method="POST" action="/expense/delete.php" style="display:inline">
              <input type="hidden" name="id" value="<?= $exp['id'] ?>">
              <input type="hidden" name="back" value="/project/edit.php?id=<?= $id ?>">
              <button type="submit" class="btn btn-danger btn-sm btn-icon"
                      onclick="return confirm('Supprimer cette dépense ?')" title="Supprimer"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
