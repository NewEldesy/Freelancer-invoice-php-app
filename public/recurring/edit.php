<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\RecurringRepository;
use App\Database\ClientRepository;

$repo   = new RecurringRepository();
$id     = (int) ($_GET['id'] ?? 0);
$tmpl   = $repo->find($id);

if ($tmpl === null) {
    header('Location: /recurring/index.php');
    exit;
}

$clients = (new ClientRepository())->allForSelect();
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    $lines = [];
    foreach ($_POST['desc'] ?? [] as $i => $desc) {
        if (trim($desc) !== '') {
            $lines[] = [
                'description' => trim($desc),
                'quantity'    => max(1, (int) ($_POST['qty'][$i] ?? 1)),
                'unit_price'  => max(0, (int) ($_POST['price'][$i] ?? 0)),
            ];
        }
    }

    $data = [
        'label'            => trim($_POST['label']            ?? ''),
        'client_name'      => trim($_POST['client_name']      ?? ''),
        'client_address'   => trim($_POST['client_address']   ?? ''),
        'client_contact'   => trim($_POST['client_contact']   ?? ''),
        'client_whatsapp'  => trim($_POST['client_whatsapp']  ?? ''),
        'subject'          => trim($_POST['subject']          ?? ''),
        'type'             => in_array($_POST['type'] ?? '', ['FACTURE PROFORMA','FACTURE'], true) ? $_POST['type'] : $tmpl['type'],
        'tax_rate'         => max(0, (float) ($_POST['tax_rate'] ?? 5)),
        'tax_label'        => trim($_POST['tax_label']        ?? 'Prelevement 5%'),
        'prestation_label' => trim($_POST['prestation_label'] ?? 'Frais de prestation'),
        'prestation_amount'=> max(0, (int) ($_POST['prestation_amount'] ?? 0)),
        'lines'            => json_encode($lines),
        'interval_type'    => in_array($_POST['interval_type'] ?? '', ['hebdomadaire','mensuel','trimestriel','annuel'], true) ? $_POST['interval_type'] : $tmpl['interval_type'],
        'next_due_at'      => $_POST['next_due_at'] ?? $tmpl['next_due_at'],
        'active'           => $tmpl['active'],
    ];

    if ($data['label'] === '')       $errors[] = 'Le libellé est obligatoire.';
    if ($data['client_name'] === '') $errors[] = 'Le nom du client est obligatoire.';
    if (empty($lines))               $errors[] = 'Au moins une ligne est requise.';

    if (empty($errors)) {
        $repo->update($id, $data);
        header('Location: /recurring/index.php');
        exit;
    }
    $tmpl = array_merge($tmpl, $data);
}

$savedLines = json_decode($tmpl['lines'], true) ?: [];

$pageTitle     = 'Modifier le modèle';
$currentPage   = 'recurring';
$topbarActions = '<a href="/recurring/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>';

require_once __DIR__ . '/../../templates/layout.php';
?>

<style>
.lines-table { width: 100%; border-collapse: collapse; }
.lines-table th { font-size: .72rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; padding: 6px 10px; border-bottom: 1px solid var(--border); text-align: left; }
.lines-table td { padding: 6px 6px; border-bottom: 1px solid var(--border-soft); vertical-align: middle; }
.lines-table input { width: 100%; padding: 7px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: .82rem; font-family: inherit; }
.lines-table input:focus { outline: none; border-color: var(--gold); }
</style>

<div class="card" style="max-width:700px">
  <div class="card-header"><h2><i class="fa-solid fa-arrows-rotate"></i> Modifier le modèle</h2></div>
  <div class="card-body">

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label>Libellé du modèle *</label>
        <input type="text" name="label" value="<?= htmlspecialchars($tmpl['label']) ?>" required autofocus>
      </div>

      <div class="form-grid-2" style="margin-top:16px">
        <div class="field" style="grid-column:1/-1">
          <label>Client *</label>
          <input type="text" name="client_name" id="client_name" value="<?= htmlspecialchars($tmpl['client_name']) ?>" list="clients-list" required autocomplete="off">
          <datalist id="clients-list">
            <?php foreach ($clients as $c): ?>
            <option value="<?= htmlspecialchars($c['name']) ?>" data-address="<?= htmlspecialchars($c['address'] ?? '') ?>" data-contact="<?= htmlspecialchars($c['contact'] ?? '') ?>" data-whatsapp="<?= htmlspecialchars($c['whatsapp'] ?? '') ?>">
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="field">
          <label>Contact</label>
          <input type="text" name="client_contact" id="client_contact" value="<?= htmlspecialchars($tmpl['client_contact'] ?? '') ?>">
        </div>
        <div class="field">
          <label><i class="fa-brands fa-whatsapp" style="color:#25d366"></i> WhatsApp</label>
          <input type="text" name="client_whatsapp" id="client_whatsapp" value="<?= htmlspecialchars($tmpl['client_whatsapp'] ?? '') ?>" placeholder="22961000000">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Adresse client</label>
          <input type="text" name="client_address" id="client_address" value="<?= htmlspecialchars($tmpl['client_address'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Objet</label>
          <input type="text" name="subject" value="<?= htmlspecialchars($tmpl['subject'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Type de document</label>
          <select name="type">
            <option value="FACTURE PROFORMA" <?= $tmpl['type'] === 'FACTURE PROFORMA' ? 'selected' : '' ?>>Facture Proforma</option>
            <option value="FACTURE"          <?= $tmpl['type'] === 'FACTURE'          ? 'selected' : '' ?>>Facture</option>
          </select>
        </div>
        <div class="field">
          <label>Intervalle</label>
          <select name="interval_type">
            <?php foreach (['hebdomadaire'=>'Hebdomadaire','mensuel'=>'Mensuel','trimestriel'=>'Trimestriel','annuel'=>'Annuel'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= $tmpl['interval_type'] === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Prochaine génération</label>
          <input type="date" name="next_due_at" value="<?= htmlspecialchars($tmpl['next_due_at']) ?>">
        </div>
        <div class="field">
          <label>Taux de taxe (%)</label>
          <input type="number" name="tax_rate" value="<?= htmlspecialchars((string)$tmpl['tax_rate']) ?>" min="0" max="100" step="0.01">
        </div>
        <div class="field">
          <label>Libellé taxe</label>
          <input type="text" name="tax_label" value="<?= htmlspecialchars($tmpl['tax_label']) ?>">
        </div>
      </div>

      <div style="margin-top:20px">
        <div style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:10px">Lignes d'articles</div>
        <table class="lines-table" id="lines-table">
          <thead>
            <tr>
              <th style="width:50%">Description</th>
              <th style="width:12%">Qté</th>
              <th style="width:20%">Prix unitaire</th>
              <th style="width:10%">Total</th>
              <th style="width:8%"></th>
            </tr>
          </thead>
          <tbody id="lines-body">
            <?php foreach ($savedLines as $line): ?>
            <tr>
              <td><input type="text" name="desc[]" value="<?= htmlspecialchars($line['description']) ?>"></td>
              <td><input type="number" name="qty[]" value="<?= (int)$line['quantity'] ?>" min="1" onchange="recalcLine(this)"></td>
              <td><input type="number" name="price[]" value="<?= (int)$line['unit_price'] ?>" min="0" onchange="recalcLine(this)"></td>
              <td class="line-total" style="text-align:right;font-weight:600;font-size:.82rem"><?= number_format((int)$line['quantity'] * (int)$line['unit_price'], 0, ',', ' ') ?></td>
              <td><button type="button" onclick="removeLine(this)" class="btn btn-danger btn-sm btn-icon">✕</button></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($savedLines)): ?>
            <tr>
              <td><input type="text" name="desc[]" placeholder="Description"></td>
              <td><input type="number" name="qty[]" value="1" min="1" onchange="recalcLine(this)"></td>
              <td><input type="number" name="price[]" value="0" min="0" onchange="recalcLine(this)"></td>
              <td class="line-total" style="text-align:right;font-weight:600;font-size:.82rem">0</td>
              <td><button type="button" onclick="removeLine(this)" class="btn btn-danger btn-sm btn-icon">✕</button></td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
        <button type="button" onclick="addLine()" class="btn btn-secondary btn-sm" style="margin-top:8px">
          <i class="fa-solid fa-plus"></i> Ajouter une ligne
        </button>
      </div>

      <div style="display:flex;gap:8px;margin-top:24px">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        <a href="/recurring/index.php" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('client_name').addEventListener('change', function() {
  const opts = document.querySelectorAll('#clients-list option');
  for (const o of opts) {
    if (o.value === this.value) {
      document.getElementById('client_address').value = o.dataset.address || '';
      document.getElementById('client_contact').value = o.dataset.contact || '';
      document.getElementById('client_whatsapp').value = o.dataset.whatsapp || '';
      break;
    }
  }
});

function recalcLine(el) {
  const row = el.closest('tr');
  const qty = parseInt(row.querySelector('[name="qty[]"]').value) || 0;
  const price = parseInt(row.querySelector('[name="price[]"]').value) || 0;
  row.querySelector('.line-total').textContent = (qty * price).toLocaleString('fr-FR');
}

function addLine() {
  const tbody = document.getElementById('lines-body');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="text" name="desc[]" placeholder="Description"></td>
    <td><input type="number" name="qty[]" value="1" min="1" onchange="recalcLine(this)"></td>
    <td><input type="number" name="price[]" value="0" min="0" onchange="recalcLine(this)"></td>
    <td class="line-total" style="text-align:right;font-weight:600;font-size:.82rem">0</td>
    <td><button type="button" onclick="removeLine(this)" class="btn btn-danger btn-sm btn-icon">✕</button></td>
  `;
  tbody.appendChild(tr);
}

function removeLine(btn) {
  const rows = document.querySelectorAll('#lines-body tr');
  if (rows.length > 1) btn.closest('tr').remove();
}
</script>

<?php require_once __DIR__ . '/../../templates/layout_end.php'; ?>
