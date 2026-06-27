<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\OpportunityRepository;
use App\Database\ClientRepository;
use App\Services\LicenseService;

$repo   = new OpportunityRepository();
$errors = [];

// Free plan limit
$pipelineCount = $repo->count();
$pipelineMax   = LicenseService::pipelineMax();
$pipelineLocked = !LicenseService::canAdd('pipeline', $pipelineCount);

if ($pipelineLocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors[] = "Limite du plan gratuit atteinte ({$pipelineMax} opportunités maximum). Activez une licence Pro pour continuer.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$pipelineLocked) {
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
        $id = $repo->create($data);
        header('Location: /pipeline/index.php?created=1');
        exit;
    }
}

$pageTitle   = 'Nouvelle opportunité';
$currentPage = 'pipeline';
$topbarActions = '<a href="/pipeline/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>';
$clients = (new ClientRepository())->allForSelect();

require __DIR__ . '/../../templates/layout.php';
?>

<div class="card">
  <div class="card-header"><h2>Nouvelle opportunité commerciale</h2></div>
  <div class="card-body">
    <?php if ($pipelineLocked): ?>
    <div class="alert" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:16px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px">
      <div>
        <strong>🔒 Limite atteinte — Plan gratuit</strong><br>
        <span style="font-size:.82rem">Vous avez atteint la limite de <strong><?= $pipelineMax ?> opportunités</strong> du plan gratuit.</span>
      </div>
      <a href="/activate.php" class="btn btn-primary" style="white-space:nowrap;flex-shrink:0">⭐ Passer Pro</a>
    </div>
    <?php elseif (!empty($errors)): ?>
    <div class="alert alert-error">⚠ <?= implode('<br>⚠ ', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" <?= $pipelineLocked ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
      <div class="section-title">🎯 Opportunité</div>
      <div class="form-grid-2" style="margin-bottom:16px">
        <div class="field" style="grid-column:1/-1">
          <label>Titre *</label>
          <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="Ex: Rénovation bureaux Client X" required>
        </div>
        <div class="field">
          <label>Statut</label>
          <select name="status">
            <?php foreach (['prospect'=>'Prospect','devis_envoye'=>'Devis envoyé','negociation'=>'Négociation','gagne'=>'Gagné','perdu'=>'Perdu'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($_POST['status'] ?? 'prospect') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Montant estimé (FCFA)</label>
          <input type="number" name="estimated_amount" value="<?= (int)($_POST['estimated_amount'] ?? 0) ?>" min="0" step="1000">
        </div>
        <div class="field">
          <label>Source</label>
          <input type="text" name="source" value="<?= htmlspecialchars($_POST['source'] ?? '') ?>" placeholder="Réseau, recommandation, appel entrant…">
        </div>
        <div class="field">
          <label>Date de clôture prévue</label>
          <input type="date" name="expected_close" value="<?= htmlspecialchars($_POST['expected_close'] ?? '') ?>">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Description du besoin</label>
          <textarea name="description" rows="3" placeholder="Détaillez le besoin du prospect…"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="section-title">👤 Client prospect</div>
      <?php if (!empty($clients)): ?>
      <div class="field" style="margin-bottom:12px">
        <label>Choisir depuis la base clients</label>
        <select id="client-picker" onchange="pickClient(this)">
          <option value="">— Sélectionner un client (optionnel) —</option>
          <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>"
              data-name="<?= htmlspecialchars($c['name']) ?>"
              data-address="<?= htmlspecialchars($c['address'] ?? '') ?>"
              data-contact="<?= htmlspecialchars($c['contact'] ?? '') ?>">
              <?= htmlspecialchars($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <script>
      function pickClient(sel) {
          const opt = sel.options[sel.selectedIndex];
          if (!opt.value) return;
          document.getElementById('p-client-name').value    = opt.dataset.name    || '';
          document.getElementById('p-client-address').value = opt.dataset.address || '';
          document.getElementById('p-client-contact').value = opt.dataset.contact || '';
      }
      </script>
      <?php endif; ?>
      <div class="form-grid-2" style="margin-bottom:16px">
        <div class="field">
          <label>Nom / Entreprise</label>
          <input type="text" id="p-client-name" name="client_name" value="<?= htmlspecialchars($_POST['client_name'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Contact (téléphone / email)</label>
          <input type="text" id="p-client-contact" name="client_contact" value="<?= htmlspecialchars($_POST['client_contact'] ?? '') ?>">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Adresse</label>
          <input type="text" id="p-client-address" name="client_address" value="<?= htmlspecialchars($_POST['client_address'] ?? '') ?>">
        </div>
      </div>

      <div class="section-title">📝 Notes internes</div>
      <div class="field" style="margin-bottom:20px">
        <textarea name="notes" rows="3" placeholder="Remarques, points d'attention, suite à donner…"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <a href="/pipeline/index.php" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
