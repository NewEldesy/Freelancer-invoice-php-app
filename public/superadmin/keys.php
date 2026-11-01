<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireSuperAdmin();

use App\Services\LicenseService;

$flash = '';

// Generate a new key on demand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    Auth::verifyCsrf();
    $edition = in_array($_POST['edition'] ?? '', ['pro', 'enterprise'], true) ? $_POST['edition'] : 'pro';
    $period  = in_array($_POST['period']  ?? '', ['3m','6m','1y','2y','permanent'], true) ? $_POST['period'] : '1y';
    LicenseService::generateAndStore($edition, $period);
    $flash = "Clé {$edition} ({$period}) générée avec succès.";
}

$keys  = LicenseService::allStoredKeys();
$stats = LicenseService::keyStats();

$periodLabel = ['3m'=>'3 mois','6m'=>'6 mois','1y'=>'1 an','2y'=>'2 ans','permanent'=>'Permanente'];

$pageTitle   = 'Gestion des licences';
$currentPage = 'superadmin_keys';

require __DIR__ . '/../../templates/layout.php';
?>

<style>
.key-table { width:100%; border-collapse:collapse; font-size:.78rem; }
.key-table th {
  padding:9px 14px; text-align:left; font-size:.65rem; text-transform:uppercase;
  letter-spacing:.5px; color:var(--muted); font-weight:700;
  border-bottom:2px solid var(--border); background:#fafbfc;
}
.key-table td { padding:10px 14px; border-bottom:1px solid var(--border-soft); vertical-align:middle; }
.key-table tr:hover td { background:#f8fafc; }
.key-code {
  font-family:'Courier New',monospace; font-size:.68rem; color:var(--navy);
  background:#f1f5f9; padding:4px 8px; border-radius:5px; cursor:pointer;
  max-width:340px; display:inline-block; overflow:hidden;
  text-overflow:ellipsis; white-space:nowrap; vertical-align:middle;
  border:1px solid var(--border);
}
.key-code:hover { background:#e2e8f0; }
.badge-edition {
  font-size:.62rem; font-weight:800; padding:3px 8px; border-radius:20px;
  text-transform:uppercase; letter-spacing:.4px;
}
.badge-pro  { background:#fef3c7; color:#92400e; }
.badge-ent  { background:#ede9fe; color:#5b21b6; }
.badge-used { background:#fee2e2; color:#991b1b; font-size:.62rem; font-weight:700; padding:3px 8px; border-radius:20px; }
.badge-free { background:#f0fdf4; color:#166534; font-size:.62rem; font-weight:700; padding:3px 8px; border-radius:20px; }
</style>

<?php if ($flash): ?>
<div class="alert" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px">
  <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">
  <?php foreach ([
    ['Total clés', $stats['total'], 'var(--navy)', '<i class="fa-solid fa-key"></i>'],
    ['Disponibles', $stats['available'], '#059669', '<i class="fa-solid fa-circle-check"></i>'],
    ['Utilisées', $stats['used'], '#dc2626', '<i class="fa-solid fa-thumbtack"></i>'],
  ] as [$label, $val, $color, $icon]): ?>
  <div class="card" style="padding:18px 22px">
    <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);font-weight:700;margin-bottom:6px"><?= $icon ?> <?= $label ?></div>
    <div style="font-size:1.6rem;font-weight:900;color:<?= $color ?>"><?= $val ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Generate form -->
<div class="card" style="margin-bottom:20px">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border-soft);font-weight:700;color:var(--navy);font-size:.88rem">
    <i class="fa-solid fa-plus"></i> Générer une nouvelle clé
  </div>
  <div style="padding:18px 20px">
    <form method="POST" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Auth::csrfToken()) ?>">
      <div style="position:relative">
        <label style="font-size:.73rem;font-weight:600;color:var(--muted);display:block;margin-bottom:4px">Édition</label>
        <input type="hidden" name="edition" id="edition-val" value="pro">
        <div id="edition-btn" onclick="toggleEditionMenu()" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;border:1px solid var(--border);font-size:.83rem;min-width:150px;cursor:pointer;background:#fff;user-select:none">
          <i class="fa-solid fa-star" style="color:#f59e0b" id="edition-icon"></i>
          <span id="edition-label">Pro</span>
          <i class="fa-solid fa-chevron-down" style="margin-left:auto;font-size:.65rem;color:var(--muted)"></i>
        </div>
        <div id="edition-menu" style="display:none;position:absolute;top:calc(100% + 4px);left:0;min-width:160px;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:100;overflow:hidden">
          <div onclick="selectEdition('pro','<i class=fa-solid fa-star style=color:#f59e0b></i>','Pro')" style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;font-size:.83rem" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
            <i class="fa-solid fa-star" style="color:#f59e0b;width:14px"></i> Pro
          </div>
          <div onclick="selectEdition('enterprise','<i class=fa-solid fa-building style=color:#6d28d9></i>','Entreprise')" style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;font-size:.83rem;border-top:1px solid var(--border-soft)" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
            <i class="fa-solid fa-building" style="color:#6d28d9;width:14px"></i> Entreprise
          </div>
        </div>
      </div>
      <div>
        <label style="font-size:.73rem;font-weight:600;color:var(--muted);display:block;margin-bottom:4px">Période</label>
        <select name="period" class="form-control" style="padding:8px 12px;border-radius:8px;border:1px solid var(--border);font-size:.83rem;min-width:140px">
          <option value="3m">3 mois</option>
          <option value="6m">6 mois</option>
          <option value="1y" selected>1 an</option>
          <option value="2y">2 ans</option>
          <option value="permanent">Permanente</option>
        </select>
      </div>
      <button type="submit" name="generate" value="1" class="btn btn-primary" style="padding:9px 20px">
        <i class="fa-solid fa-key"></i> Générer
      </button>
    </form>
  </div>
</div>

<!-- Barre d'actions de sélection (apparaît quand des cases sont cochées) -->
<div id="selection-bar" style="display:none;position:sticky;top:12px;z-index:50;background:#0f172a;color:#fff;border-radius:10px;padding:12px 20px;margin-bottom:16px;display:none;align-items:center;gap:14px">
  <i class="fa-solid fa-square-check" style="color:#f59e0b"></i>
  <span id="selection-count" style="font-size:.85rem;font-weight:600">0 clé sélectionnée</span>
  <div style="margin-left:auto;display:flex;gap:10px">
    <button onclick="clearSelection()" style="background:rgba(255,255,255,.12);border:none;color:#fff;padding:7px 14px;border-radius:7px;font-size:.78rem;cursor:pointer;font-weight:600">
      <i class="fa-solid fa-xmark"></i> Désélectionner
    </button>
    <button onclick="printSelected()" style="background:#f59e0b;border:none;color:#0f172a;padding:7px 16px;border-radius:7px;font-size:.78rem;cursor:pointer;font-weight:700">
      <i class="fa-solid fa-print"></i> Imprimer la sélection
    </button>
  </div>
</div>

<!-- Keys table -->
<div class="card">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border-soft);display:flex;justify-content:space-between;align-items:center">
    <div style="display:flex;align-items:center;gap:12px">
      <input type="checkbox" id="check-all" onchange="toggleAll(this)" style="width:15px;height:15px;cursor:pointer" title="Tout sélectionner">
      <div style="font-weight:700;color:var(--navy);font-size:.88rem">Toutes les clés</div>
    </div>
    <div style="font-size:.72rem;color:var(--muted)">Cliquez sur une clé pour la copier</div>
  </div>
  <div style="overflow-x:auto">
  <table class="key-table">
    <thead>
      <tr>
        <th style="width:36px"></th>
        <th>#</th>
        <th>Clé</th>
        <th>Édition</th>
        <th>Période</th>
        <th>Expiration</th>
        <th>Statut</th>
        <th>Poste utilisateur</th>
        <th>Activée le</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($keys as $i => $k): ?>
    <tr>
      <td>
        <input type="checkbox" class="key-check" value="<?= $k['id'] ?>"
               onchange="updateSelection()"
               style="width:15px;height:15px;cursor:pointer">
      </td>
      <td style="color:var(--muted);font-size:.72rem"><?= $i + 1 ?></td>
      <td>
        <span class="key-code" data-key="<?= htmlspecialchars($k['key_value']) ?>"
              onclick="copyKey(this)" title="Cliquer pour copier">
          <?= htmlspecialchars(substr($k['key_value'], 0, 40)) ?>…
        </span>
      </td>
      <td>
        <span class="badge-edition <?= $k['edition'] === 'pro' ? 'badge-pro' : 'badge-ent' ?>">
          <?= $k['edition'] === 'pro' ? '<i class="fa-solid fa-star"></i> Pro' : '<i class="fa-solid fa-building"></i> Entreprise' ?>
        </span>
      </td>
      <td><?= $periodLabel[$k['period']] ?? $k['period'] ?></td>
      <td style="font-size:.75rem;color:var(--muted)">
        <?php if (!$k['used']): ?>
          <span style="color:#64748b;font-style:italic"><?= $periodLabel[$k['period']] ?? $k['period'] ?></span>
        <?php elseif ($k['expires_at']): ?>
          <?= date('d/m/Y', strtotime($k['expires_at'])) ?>
        <?php else: ?>
          <span style="color:#059669;font-weight:600">Permanente</span>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($k['used']): ?>
        <span class="badge-used"><i class="fa-solid fa-thumbtack"></i> Utilisée</span>
        <?php else: ?>
        <span class="badge-free"><i class="fa-solid fa-circle-check"></i> Disponible</span>
        <?php endif; ?>
      </td>
      <td style="font-size:.65rem;font-family:monospace;color:var(--muted)">
        <?= $k['used_machine'] ? htmlspecialchars(substr($k['used_machine'], 0, 16)) . '…' : '/' ?>
      </td>
      <td style="font-size:.72rem;color:var(--muted)">
        <?= $k['used_at'] ? date('d/m/Y', strtotime($k['used_at'])) : '/' ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($keys)): ?>
    <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--muted)">Aucune clé générée</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div id="copy-toast" style="display:none;position:fixed;bottom:24px;right:24px;background:#0f172a;color:#fff;padding:10px 18px;border-radius:9px;font-size:.82rem;font-weight:600;z-index:9999">
  <i class="fa-solid fa-check"></i> Clé copiée !
</div>

<script>
function toggleEditionMenu() {
  const menu = document.getElementById('edition-menu');
  menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}
function selectEdition(value, iconHtml, label) {
  document.getElementById('edition-val').value = value;
  document.getElementById('edition-label').textContent = label;
  document.getElementById('edition-icon').outerHTML = iconHtml.replace('<i ', '<i id="edition-icon" ');
  document.getElementById('edition-menu').style.display = 'none';
}
document.addEventListener('click', function(e) {
  const btn = document.getElementById('edition-btn');
  const menu = document.getElementById('edition-menu');
  if (btn && !btn.contains(e.target) && menu && !menu.contains(e.target)) {
    menu.style.display = 'none';
  }
});

function updateSelection() {
  const checked = document.querySelectorAll('.key-check:checked');
  const bar = document.getElementById('selection-bar');
  const count = document.getElementById('selection-count');
  if (checked.length > 0) {
    bar.style.display = 'flex';
    count.textContent = checked.length + ' clé' + (checked.length > 1 ? 's sélectionnées' : ' sélectionnée');
  } else {
    bar.style.display = 'none';
  }
  document.getElementById('check-all').indeterminate =
    checked.length > 0 && checked.length < document.querySelectorAll('.key-check').length;
  document.getElementById('check-all').checked =
    checked.length === document.querySelectorAll('.key-check').length && checked.length > 0;
}
function toggleAll(cb) {
  document.querySelectorAll('.key-check').forEach(c => c.checked = cb.checked);
  updateSelection();
}
function clearSelection() {
  document.querySelectorAll('.key-check').forEach(c => c.checked = false);
  document.getElementById('check-all').checked = false;
  updateSelection();
}
function printSelected() {
  const ids = Array.from(document.querySelectorAll('.key-check:checked')).map(c => c.value);
  if (!ids.length) return;
  window.open('/superadmin/keys_print.php?ids=' + ids.join(','), '_blank');
}

function copyKey(el) {
  navigator.clipboard.writeText(el.dataset.key).then(() => {
    const t = document.getElementById('copy-toast');
    t.style.display = 'block';
    setTimeout(() => t.style.display = 'none', 2000);
  });
}
</script>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
