<?php
/**
 * Shared invoice form template.
 * Expects: $formAction, $invoice (array|null), $lines (array), $errors (array)
 */
use App\Database\SettingsRepository;
use App\Database\ClientRepository;
use App\Database\ServiceRepository;

$inv      = $invoice ?? [];
$e        = $errors  ?? [];
$settings = (new SettingsRepository())->all();
$clients  = (new ClientRepository())->allForSelect();
$catalogue = (new ServiceRepository())->all();

$today     = date('Y-m-d');
$nextMonth = date('Y-m-d', strtotime('+1 month'));

/* Priority: invoice record → company settings → hardcoded default */
$fv = function(string $key, string $default = '') use ($inv, $settings): string {
    $val = $inv[$key] ?? $settings[$key] ?? $default;
    return htmlspecialchars((string) $val);
};

$prestationLabel  = $fv('prestation_label', $settings['prestation_label'] ?? 'Frais de prestation');
$prestationAmount = (int) ($inv['prestation_amount'] ?? 0);
?>

<form method="POST" action="<?= $formAction ?>" enctype="multipart/form-data">
<?php if (!empty($inv['id'])): ?>
    <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
<?php endif; ?>

<!-- ── Entreprise ── -->
<div class="section-title" style="margin-top:0"><i class="fa-solid fa-building"></i> Votre entreprise</div>

<?php
$logoPath = $inv['issuer_logo_path'] ?? $settings['issuer_logo_path'] ?? '';
if ($logoPath && file_exists($logoPath)):
?>
<div style="margin-bottom:10px">
    <img src="/uploads/<?= basename($logoPath) ?>"
         style="max-height:55px;object-fit:contain;border-radius:6px;border:1px solid var(--border);padding:5px">
    <span style="font-size:.74rem;color:var(--muted);margin-left:8px">Logo actuel</span>
</div>
<?php endif; ?>

<div class="form-grid-2" style="margin-bottom:14px">
    <div class="field">
        <label>Nom *</label>
        <input type="text" name="issuer_name" value="<?= $fv('issuer_name') ?>" required>
        <input type="hidden" name="issuer_logo_path" value="<?= htmlspecialchars($logoPath) ?>">
    </div>
    <div class="field">
        <label>Remplacer le logo</label>
        <input type="file" name="logo" accept="image/*">
    </div>
</div>
<div class="form-grid-3">
    <div class="field">
        <label>Adresse</label>
        <input type="text" name="issuer_address" value="<?= $fv('issuer_address') ?>">
    </div>
    <div class="field">
        <label>Téléphone</label>
        <input type="text" name="issuer_phone" value="<?= $fv('issuer_phone') ?>">
    </div>
    <div class="field">
        <label>N°IFU</label>
        <input type="text" name="issuer_ifu" value="<?= $fv('issuer_ifu') ?>">
    </div>
</div>
<div class="field" style="margin-top:12px">
    <label>Email *</label>
    <input type="email" name="issuer_email" value="<?= $fv('issuer_email') ?>" required>
    <span style="font-size:.72rem;color:var(--muted);margin-top:2px">
        Ces informations sont pré-remplies depuis vos <a href="/settings.php" style="color:var(--gold)">Paramètres</a>.
    </span>
</div>

<!-- ── Facture ── -->
<div class="section-title"><i class="fa-solid fa-file-invoice"></i> Informations de la facture</div>
<div class="form-grid-3">
    <div class="field">
        <label>Type</label>
        <?php if (!empty($lockedType)): ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($lockedType) ?>">
        <input type="text" value="<?= htmlspecialchars($lockedType) ?>" readonly style="background:#f5f5f5;color:var(--muted);cursor:default">
        <?php else: ?>
        <select name="type">
            <?php foreach (['FACTURE PROFORMA','FACTURE','DEVIS','AVOIR'] as $t): ?>
            <option value="<?= $t ?>" <?= ($inv['type'] ?? 'FACTURE PROFORMA') === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>
    <div class="field">
        <label>N° Facture</label>
        <input type="text" name="number" value="<?= $fv('number') ?>"
               <?= empty($inv['id']) ? 'readonly style="background:#f5f5f5;color:var(--muted);cursor:default"' : '' ?>>
        <?php if (empty($inv['id'])): ?>
        <span style="font-size:.72rem;color:var(--muted);margin-top:2px">Généré automatiquement · modifiable si besoin</span>
        <?php endif; ?>
    </div>
    <div class="field">
        <label>Statut</label>
        <select name="status">
            <?php
            $statusOpts = $devisStatuses ?? false
                ? ['brouillon'=>'Brouillon','envoyé'=>'Envoyé','accepté'=>'Accepté','refusé'=>'Refusé']
                : ($avoirStatuses ?? false
                    ? ['brouillon'=>'Brouillon','émis'=>'Émis']
                    : ['brouillon'=>'Brouillon','envoyée'=>'Envoyée','payée'=>'Payée','annulée'=>'Annulée']);
            foreach ($statusOpts as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= ($inv['status'] ?? 'brouillon') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="form-grid-3" style="margin-top:12px">
    <div class="field">
        <label>Objet</label>
        <input type="text" name="subject" value="<?= $fv('subject') ?>">
    </div>
    <div class="field">
        <label>Date d'émission</label>
        <input type="date" name="issued_at" value="<?= $fv('issued_at', $today) ?>">
    </div>
    <div class="field">
        <label>Date limite</label>
        <input type="date" name="due_at" value="<?= $fv('due_at', $nextMonth) ?>">
    </div>
</div>

<!-- ── Client ── -->
<div class="section-title"><i class="fa-solid fa-user"></i> Client — Envoyé à</div>
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
<?php endif; ?>
<div class="form-grid-3">
    <div class="field">
        <label>Nom / Entreprise</label>
        <input type="text" id="f-client-name" name="client_name" value="<?= $fv('client_name') ?>">
    </div>
    <div class="field">
        <label>Adresse</label>
        <input type="text" id="f-client-address" name="client_address" value="<?= $fv('client_address') ?>">
    </div>
    <div class="field">
        <label>Contact</label>
        <input type="text" id="f-client-contact" name="client_contact" value="<?= $fv('client_contact') ?>">
    </div>
</div>
<script>
function pickClient(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    document.getElementById('f-client-name').value    = opt.dataset.name    || '';
    document.getElementById('f-client-address').value = opt.dataset.address || '';
    document.getElementById('f-client-contact').value = opt.dataset.contact || '';
}
</script>

<!-- ── Lignes ── -->
<div class="section-title"><i class="fa-solid fa-list"></i> Articles</div>
<table class="lines-tbl" style="margin-bottom:10px">
    <thead>
        <tr>
            <th style="width:36px;text-align:center">#</th>
            <th>Description</th>
            <th style="width:90px">Quantité</th>
            <th style="width:150px">Prix unitaire (FCFA)</th>
            <th style="width:120px;text-align:right">Total</th>
            <th style="width:36px"></th>
        </tr>
    </thead>
    <tbody id="lines-body">
    <?php foreach ($lines as $i => $line): ?>
        <tr>
            <td style="text-align:center;color:var(--muted);font-size:.78rem"><?= $i + 1 ?></td>
            <td><input type="text" name="line_desc[]" value="<?= htmlspecialchars($line['description'] ?? '') ?>"></td>
            <td><input type="number" name="line_qty[]" value="<?= (int)($line['quantity'] ?? 1) ?>" min="1" class="qty-i"></td>
            <td><input type="number" name="line_price[]" value="<?= (int)($line['unit_price'] ?? 0) ?>" min="0" class="price-i"></td>
            <td class="line-total" style="text-align:right;padding:0 10px;font-weight:600;font-size:.83rem">
                <?= number_format((int)($line['quantity'] ?? 1) * (int)($line['unit_price'] ?? 0), 0, ',', ' ') ?>
            </td>
            <td><button type="button" onclick="removeLine(this)"
                style="background:none;border:none;color:var(--red);cursor:pointer;font-size:1rem;padding:4px">✕</button></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<button type="button" onclick="addLine()"
    style="background:none;border:1px dashed var(--gold);color:var(--gold);border-radius:6px;padding:6px 14px;cursor:pointer;font-size:.82rem;font-weight:600">
    ＋ Ajouter une ligne
</button>
<?php if (!empty($catalogue)): ?>
<button type="button" onclick="document.getElementById('catalogue-modal').style.display='flex'"
    style="background:none;border:1px dashed var(--navy);color:var(--navy);border-radius:6px;padding:6px 14px;cursor:pointer;font-size:.82rem;font-weight:600">
    <i class="fa-solid fa-box-open"></i> Depuis le catalogue
</button>
<?php endif; ?>
</div>

<?php if (!empty($catalogue)): ?>
<!-- Catalogue modal -->
<div id="catalogue-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center"
     onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#fff;border-radius:12px;width:560px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <strong style="font-size:.95rem"><i class="fa-solid fa-box-open"></i> Catalogue de prestations</strong>
      <button type="button" onclick="document.getElementById('catalogue-modal').style.display='none'"
              style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted)">×</button>
    </div>
    <div style="overflow-y:auto;padding:12px 16px;flex:1">
      <input type="text" id="cat-search" placeholder="Rechercher…"
             oninput="filterCat(this.value)"
             style="width:100%;padding:8px 12px;border:1px solid var(--border);border-radius:8px;font-size:.84rem;margin-bottom:12px;box-sizing:border-box">
      <table style="width:100%;border-collapse:collapse;font-size:.83rem" id="cat-table">
        <?php foreach ($catalogue as $svc): ?>
        <tr class="cat-row" style="border-bottom:1px solid var(--border);cursor:pointer"
            onclick="addFromCatalogue(<?= $svc['id'] ?>, <?= json_encode(htmlspecialchars($svc['description'])) ?>, <?= (int)$svc['unit_price'] ?>)"
            onmouseenter="this.style.background='var(--bg)'" onmouseleave="this.style.background=''">
          <td style="padding:10px 8px">
            <div style="font-weight:600;color:var(--navy)"><?= htmlspecialchars($svc['name']) ?></div>
            <div style="color:var(--muted);font-size:.77rem"><?= htmlspecialchars($svc['description']) ?></div>
          </td>
          <td style="padding:10px 8px;text-align:right;white-space:nowrap;font-weight:700">
            <?= number_format((int)$svc['unit_price'], 0, ',', ' ') ?> FCFA
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
<script>
function addFromCatalogue(id, description, unitPrice) {
    addLine();
    const rows = document.querySelectorAll('#lines-body tr');
    const last = rows[rows.length - 1];
    last.querySelector('input[name="line_desc[]"]').value  = description;
    last.querySelector('input[name="line_qty[]"]').value   = 1;
    last.querySelector('input[name="line_price[]"]').value = unitPrice;
    updateTotals();
    document.getElementById('catalogue-modal').style.display = 'none';
}
function filterCat(q) {
    q = q.toLowerCase();
    document.querySelectorAll('.cat-row').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
</script>
<?php endif; ?>

<!-- ── Ligne Prestation (toujours en dernier) ── -->
<div class="section-title" style="margin-top:22px"><i class="fa-solid fa-screwdriver-wrench"></i> Frais de prestation
    <span style="font-size:.7rem;color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0;margin-left:6px">
        — apparaît en dernière position dans la facture
    </span>
</div>
<div style="display:flex;align-items:center;gap:12px;background:#fffbf0;border:1px solid #f5c040;border-radius:8px;padding:12px 16px">
    <div style="flex:1">
        <label style="font-size:.75rem;color:var(--muted);display:block;margin-bottom:4px">Libellé</label>
        <input type="text" name="prestation_label"
               value="<?= $prestationLabel ?>"
               style="border:1px solid var(--border);border-radius:6px;padding:7px 10px;font-size:.85rem;width:100%">
    </div>
    <div style="width:180px">
        <label style="font-size:.75rem;color:var(--muted);display:block;margin-bottom:4px">Montant (FCFA)</label>
        <input type="number" name="prestation_amount" id="prestation-amount"
               value="<?= $prestationAmount ?>" min="0"
               style="border:1px solid var(--border);border-radius:6px;padding:7px 10px;font-size:.85rem;width:100%">
    </div>
    <div style="width:130px;text-align:right;padding-top:18px">
        <span style="font-weight:700;font-size:.9rem" id="prestation-display">
            <?= number_format($prestationAmount, 0, ',', ' ') ?> FCFA
        </span>
    </div>
</div>

<!-- Taxes -->
<div class="form-grid-2" style="margin-top:18px">
    <div class="field">
        <label>Taux de prélèvement (%)</label>
        <input type="number" name="tax_rate" id="tax-rate" value="<?= $fv('tax_rate', $settings['default_tax_rate'] ?? '5') ?>" min="0" max="100" step="0.1">
    </div>
    <div class="field">
        <label>Libellé de la taxe</label>
        <input type="text" name="tax_label" value="<?= $fv('tax_label', $settings['default_tax_label'] ?? 'Prelevement 5%') ?>">
    </div>
</div>

<!-- Aperçu totaux -->
<div class="totals-preview">
    <div class="row"><span>Total H.T</span><span id="prev-ht">—</span></div>
    <div class="row"><span id="prev-tax-label">Prélèvement 5%</span><span id="prev-tax">—</span></div>
    <div class="row final"><span>Total Net à Payer</span><span id="prev-net">—</span></div>
</div>

<!-- ── Signature ── -->
<div class="section-title"><i class="fa-solid fa-signature"></i> Signature</div>
<div class="form-grid-2">
    <div class="field">
        <label>Titre</label>
        <input type="text" name="signatory_title" value="<?= $fv('signatory_title') ?>">
    </div>
    <div class="field">
        <label>Nom</label>
        <input type="text" name="signatory_name" value="<?= $fv('signatory_name') ?>">
    </div>
</div>

<!-- ── Pied de page ── -->
<div class="section-title"><i class="fa-solid fa-align-left"></i> Pied de page</div>
<div class="field">
    <textarea name="footer_text" rows="3"><?= $fv('footer_text') ?></textarea>
    <span style="font-size:.72rem;color:var(--muted);margin-top:2px">
        Pré-rempli depuis vos <a href="/settings.php" style="color:var(--gold)">Paramètres</a>.
    </span>
</div>

<!-- Champs cachés totaux -->
<input type="hidden" name="total_ht"  id="input-total-ht">
<input type="hidden" name="total_net" id="input-total-net">

<!-- Actions -->
<div style="display:flex;gap:10px;margin-top:28px;padding-top:20px;border-top:1px solid var(--border)">
    <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;padding:12px">
        <i class="fa-solid fa-floppy-disk"></i> Enregistrer la facture
    </button>
    <a href="/invoice/list.php" class="btn btn-secondary" style="justify-content:center;padding:12px 20px">
        Annuler
    </a>
</div>

</form>

<script>
let lineCount = document.querySelectorAll('#lines-body tr').length;
const fmt = n => Number(n||0).toLocaleString('fr-FR');

function updateTotals() {
    let ht = 0;
    document.querySelectorAll('#lines-body tr').forEach(row => {
        const qty   = parseFloat(row.querySelector('.qty-i')?.value   || 0);
        const price = parseFloat(row.querySelector('.price-i')?.value || 0);
        const total = qty * price;
        ht += total;
        const cell = row.querySelector('.line-total');
        if (cell) cell.textContent = fmt(total);
    });

    /* Add prestation */
    const prestation = parseFloat(document.getElementById('prestation-amount')?.value || 0);
    ht += prestation;
    document.getElementById('prestation-display').textContent = fmt(prestation) + ' FCFA';

    const rate = parseFloat(document.getElementById('tax-rate').value) || 0;
    const tax  = Math.round(ht * rate / 100);
    const net  = ht - tax;

    document.getElementById('prev-ht').textContent        = fmt(ht);
    document.getElementById('prev-tax').textContent       = fmt(tax);
    document.getElementById('prev-net').textContent       = fmt(net) + ' FCFA';
    document.getElementById('prev-tax-label').textContent = 'Prélèvement ' + rate + '%';
    document.getElementById('input-total-ht').value       = Math.round(ht);
    document.getElementById('input-total-net').value      = Math.round(net);
}

function addLine() {
    lineCount++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td style="text-align:center;color:var(--muted);font-size:.78rem">${lineCount}</td>
        <td><input type="text" name="line_desc[]" placeholder="Description"></td>
        <td><input type="number" name="line_qty[]" value="1" min="1" class="qty-i"></td>
        <td><input type="number" name="line_price[]" value="0" min="0" class="price-i"></td>
        <td class="line-total" style="text-align:right;padding:0 10px;font-weight:600;font-size:.83rem">0</td>
        <td><button type="button" onclick="removeLine(this)" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:1rem;padding:4px">✕</button></td>
    `;
    document.getElementById('lines-body').appendChild(tr);
    tr.querySelectorAll('input').forEach(el => el.addEventListener('input', updateTotals));
    renumberLines();
}

function removeLine(btn) {
    const rows = document.querySelectorAll('#lines-body tr');
    if (rows.length <= 1) return;
    btn.closest('tr').remove();
    renumberLines();
    updateTotals();
}

function renumberLines() {
    document.querySelectorAll('#lines-body tr').forEach((tr, i) => {
        const cell = tr.querySelector('td:first-child');
        if (cell) cell.textContent = i + 1;
    });
    lineCount = document.querySelectorAll('#lines-body tr').length;
}

document.querySelectorAll('#lines-body input').forEach(el => el.addEventListener('input', updateTotals));
document.getElementById('tax-rate').addEventListener('input', updateTotals);
document.getElementById('prestation-amount').addEventListener('input', updateTotals);
updateTotals();
</script>
