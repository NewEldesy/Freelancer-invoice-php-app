<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\ExpenseRepository;
use App\Database\InvoiceRepository;
use App\Services\LicenseService;

$expRepo  = new ExpenseRepository();
$invRepo  = new InvoiceRepository();
$invoices = array_filter($invRepo->all(), fn($inv) => in_array($inv['status'], ['envoyée', 'payée'], true));

$preselectedInvoice = (int) ($_GET['invoice_id'] ?? 0);
$errors = [];
$saved  = 0;

// Free plan limit
$expenseCount  = $expRepo->count();
$expenseMax    = LicenseService::expenseMax();
$expenseLocked = !LicenseService::canAdd('expense', $expenseCount);

if ($expenseLocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors[] = "Limite du plan gratuit atteinte ({$expenseMax} dépenses maximum). Activez une licence Pro pour continuer.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$expenseLocked) {
    $invoiceId = (int) ($_POST['invoice_id'] ?? 0) ?: null;
    $date      = $_POST['date'] ?? date('Y-m-d');
    $rows      = $_POST['rows'] ?? [];

    foreach ($rows as $row) {
        $desc   = trim($row['description'] ?? '');
        $amount = (int) ($row['amount'] ?? 0);
        if ($desc === '' || $amount <= 0) continue;
        $expRepo->create([
            'invoice_id'  => $invoiceId,
            'category'    => $row['category'] ?? 'autre',
            'description' => $desc,
            'amount'      => $amount,
            'date'        => $date,
        ]);
        $saved++;
    }

    if ($saved === 0) {
        $errors[] = 'Ajoutez au moins une dépense avec une description et un montant valide.';
    } else {
        $back = $invoiceId
            ? '/expense/index.php?invoice_id=' . $invoiceId
            : '/expense/index.php';
        header('Location: ' . $back . '&created=' . $saved);
        exit;
    }
}

$pageTitle     = 'Nouvelles dépenses';
$currentPage   = 'expenses';
$topbarActions = '<a href="/expense/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>';

require __DIR__ . '/../../templates/layout.php';
?>

<style>
.exp-table { width:100%; border-collapse:collapse; font-size:.83rem; }
.exp-table thead tr { background:var(--navy); }
.exp-table th {
  color:rgba(255,255,255,.85); padding:9px 10px;
  font-size:.7rem; font-weight:600; text-align:left; letter-spacing:.3px;
}
.exp-table th:first-child { border-radius:8px 0 0 0; }
.exp-table th:last-child  { border-radius:0 8px 0 0; }
.exp-table td { padding:5px 4px; border-bottom:1px solid var(--border-soft); }
.exp-table input, .exp-table select {
  border:1px solid var(--border); border-radius:6px;
  padding:6px 9px; font-size:.81rem; width:100%;
  font-family:inherit; color:var(--text); background:var(--white);
  transition:border .15s;
}
.exp-table input:focus, .exp-table select:focus {
  border-color:var(--gold); box-shadow:0 0 0 2px rgba(245,158,11,.1); outline:none;
}
.exp-table tbody tr:hover td { background:#fafbfc; }
.total-bar {
  display:flex; justify-content:flex-end; align-items:center;
  gap:16px; padding:12px 6px; font-size:.84rem;
}
</style>

<div class="card">
  <div class="card-header"><h2>Enregistrer des dépenses</h2></div>
  <div class="card-body">
    <?php if ($expenseLocked): ?>
    <div class="alert" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:16px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px">
      <div>
        <strong>🔒 Limite atteinte — Plan gratuit</strong><br>
        <span style="font-size:.82rem">Vous avez atteint la limite de <strong><?= $expenseMax ?> dépenses</strong> du plan gratuit.</span>
      </div>
      <a href="/activate.php" class="btn btn-primary" style="white-space:nowrap;flex-shrink:0">⭐ Passer Pro</a>
    </div>
    <?php elseif (!empty($errors)): ?>
    <div class="alert alert-error">⚠ <?= implode('<br>⚠ ', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" id="exp-form">

      <!-- En-tête commun -->
      <div class="form-grid-2" style="margin-bottom:20px">
        <div class="field">
          <label>Facture liée</label>
          <select name="invoice_id" id="invoice_id">
            <option value="">— Aucune —</option>
            <?php foreach ($invoices as $inv): ?>
            <option value="<?= $inv['id'] ?>"
              <?= ((int)($_POST['invoice_id'] ?? $preselectedInvoice)) === (int)$inv['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($inv['number']) ?> · <?= htmlspecialchars($inv['client_name'] ?: '—') ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Date des dépenses</label>
          <input type="date" name="date" value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>">
        </div>
      </div>

      <!-- Tableau des lignes -->
      <div style="overflow-x:auto;margin-bottom:10px">
        <table class="exp-table">
          <thead>
            <tr>
              <th style="width:40%">Description</th>
              <th style="width:18%">Catégorie</th>
              <th style="width:18%">Montant (FCFA)</th>
              <th style="width:50px"></th>
            </tr>
          </thead>
          <tbody id="exp-body">
            <?php
            $postRows = $_POST['rows'] ?? [];
            $initRows = !empty($postRows) ? $postRows : array_fill(0, 3, ['description'=>'','category'=>'autre','amount'=>'']);
            foreach ($initRows as $i => $row):
            ?>
            <tr>
              <td><input type="text" name="rows[<?= $i ?>][description]"
                         value="<?= htmlspecialchars($row['description'] ?? '') ?>"
                         placeholder="Ex: Achat câbles réseau"></td>
              <td>
                <select name="rows[<?= $i ?>][category]">
                  <?php foreach (ExpenseRepository::CATEGORIES as $v => $l): ?>
                  <option value="<?= $v ?>" <?= ($row['category'] ?? 'autre') === $v ? 'selected' : '' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="number" name="rows[<?= $i ?>][amount]"
                         value="<?= (int)($row['amount'] ?? 0) ?: '' ?>"
                         min="0" step="1" autocomplete="off"
                         placeholder="0" class="amount-input"></td>
              <td style="text-align:center">
                <button type="button" onclick="removeRow(this)" class="btn btn-danger btn-sm btn-icon" title="Supprimer">✕</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Barre totaux + actions -->
      <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
        <button type="button" id="add-row" class="btn btn-secondary">➕ Ajouter une ligne</button>
        <div class="total-bar">
          <span style="color:var(--muted)">Total :</span>
          <span id="grand-total" style="font-size:1rem;font-weight:700;color:var(--navy)">0 FCFA</span>
        </div>
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Enregistrer tout</button>
        <a href="/expense/index.php" class="btn btn-secondary">Annuler</a>
      </div>
    </form>
  </div>
</div>

<script>
const CATEGORIES = <?= json_encode(array_map(null, array_keys(ExpenseRepository::CATEGORIES), array_values(ExpenseRepository::CATEGORIES))) ?>;
let rowIndex = <?= count($initRows) ?>;

function categoryOptions(selected = 'autre') {
  return <?= json_encode(ExpenseRepository::CATEGORIES) ?>;
}

function addRow() {
  const tbody = document.getElementById('exp-body');
  const cats  = <?= json_encode(ExpenseRepository::CATEGORIES) ?>;
  let opts = '';
  for (const [v, l] of Object.entries(cats)) {
    opts += `<option value="${v}"${v === 'autre' ? ' selected' : ''}>${l}</option>`;
  }
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="text" name="rows[${rowIndex}][description]" placeholder="Ex: Achat câbles réseau"></td>
    <td><select name="rows[${rowIndex}][category]">${opts}</select></td>
    <td><input type="number" name="rows[${rowIndex}][amount]" min="0" step="1" autocomplete="off" placeholder="0" class="amount-input"></td>
    <td style="text-align:center">
      <button type="button" onclick="removeRow(this)" class="btn btn-danger btn-sm btn-icon" title="Supprimer">✕</button>
    </td>
  `;
  tbody.appendChild(tr);
  rowIndex++;
  tr.querySelector('input[type=text]').focus();
  bindAmountListeners();
}

function removeRow(btn) {
  const tr = btn.closest('tr');
  const tbody = document.getElementById('exp-body');
  if (tbody.querySelectorAll('tr').length <= 1) {
    tr.querySelectorAll('input').forEach(i => i.value = '');
    return;
  }
  tr.remove();
  updateTotal();
}

function updateTotal() {
  let total = 0;
  document.querySelectorAll('.amount-input').forEach(inp => {
    total += parseInt(inp.value) || 0;
  });
  document.getElementById('grand-total').textContent =
    total.toLocaleString('fr-FR') + ' FCFA';
}

function bindAmountListeners() {
  document.querySelectorAll('.amount-input').forEach(inp => {
    inp.removeEventListener('input', updateTotal);
    inp.addEventListener('input', updateTotal);
  });
}

document.getElementById('add-row').addEventListener('click', addRow);
bindAmountListeners();
updateTotal();
</script>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
