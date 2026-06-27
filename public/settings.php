<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\SettingsRepository;
use App\Database\OpportunityRepository;
use App\Database\ExpenseRepository;
use App\Services\LicenseService;

$repo         = new SettingsRepository();
$flashSuccess = null;
$flashError   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logoPath = $repo->get('issuer_logo_path');

    if (!empty($_FILES['logo']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed, true) && $_FILES['logo']['size'] <= 2_000_000) {
            $dest     = __DIR__ . '/uploads/' . uniqid('logo_', true) . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $dest);
            $logoPath = $dest;
        } else {
            $flashError = 'Logo invalide (PNG/JPG, max 2 Mo).';
        }
    }

    if ($flashError === null) {
        $repo->saveAll([
            'issuer_name'         => trim($_POST['issuer_name']         ?? ''),
            'issuer_address'      => trim($_POST['issuer_address']      ?? ''),
            'issuer_phone'        => trim($_POST['issuer_phone']        ?? ''),
            'issuer_email'        => trim($_POST['issuer_email']        ?? ''),
            'issuer_ifu'          => trim($_POST['issuer_ifu']          ?? ''),
            'issuer_logo_path'    => $logoPath,
            'signatory_title'     => trim($_POST['signatory_title']     ?? ''),
            'signatory_name'      => trim($_POST['signatory_name']      ?? ''),
            'footer_text'         => $_POST['footer_text']              ?? '',
            'default_tax_rate'    => trim($_POST['default_tax_rate']    ?? '5'),
            'default_tax_label'   => trim($_POST['default_tax_label']   ?? 'Prelevement 5%'),
            'prestation_label'    => trim($_POST['prestation_label']    ?? 'Frais de prestation'),
            'invoice_prefix'      => preg_replace('/[^A-Z0-9\-_]/i', '', strtoupper(trim($_POST['invoice_prefix'] ?? ''))),
        ]);
        $flashSuccess = 'Paramètres sauvegardés.';
    }
}

$s = $repo->all();

$lic = LicenseService::current();

$pageTitle   = 'Paramètres';
$currentPage = 'settings';

require __DIR__ . '/../templates/layout.php';
?>

<?php
// Tab from URL
$tab = $_GET['tab'] ?? 'company';

if (isset($_GET['restore_ok']))    $flashSuccess = 'Base de données restaurée avec succès. Un backup de sécurité a été créé.';
if (isset($_GET['restore_error'])) {
    $restoreMessages = [
        'no_file'      => 'Aucun fichier reçu ou erreur d\'upload.',
        'invalid_file' => 'Fichier invalide. Seuls les fichiers .sqlite sont acceptés.',
        'write_failed' => 'Échec de l\'écriture de la base de données.',
    ];
    $flashError = $restoreMessages[$_GET['restore_error']] ?? 'Erreur inconnue lors de la restauration.';
}
?>

<style>
.settings-tabs { display:flex; gap:4px; margin-bottom:20px; border-bottom:2px solid var(--border); }
.settings-tab {
  padding:10px 20px; font-size:.82rem; font-weight:600; color:var(--muted);
  text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px;
  transition:all .15s; border-radius:6px 6px 0 0;
}
.settings-tab:hover { color:var(--navy); background:var(--bg); }
.settings-tab.active { color:var(--navy); border-bottom-color:var(--navy); background:var(--white); }
</style>

<!-- Tabs -->
<div class="settings-tabs">
  <a href="/settings.php?tab=company"  class="settings-tab <?= $tab === 'company'  ? 'active' : '' ?>"><i class="fa-solid fa-building"></i> Entreprise</a>
  <a href="/settings.php?tab=license"  class="settings-tab <?= $tab === 'license'  ? 'active' : '' ?>"><i class="fa-solid fa-key"></i> Licence</a>
  <a href="/settings.php?tab=backup"   class="settings-tab <?= $tab === 'backup'   ? 'active' : '' ?>"><i class="fa-solid fa-database"></i> Sauvegarde</a>
</div>

<?php if ($flashSuccess): ?>
<div class="alert" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px">
  <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashSuccess) ?>
</div>
<?php elseif ($flashError): ?>
<div class="alert alert-error" style="margin-bottom:16px"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<!-- ══ COMPANY TAB ══ -->
<?php if ($tab === 'company'): ?>
<div>

<div class="card">
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">

      <!-- ── Logo ── -->
      <div class="section-title" style="margin-top:0"><i class="fa-solid fa-image"></i> Logo</div>
      <?php if (!empty($s['issuer_logo_path']) && file_exists($s['issuer_logo_path'])): ?>
        <div style="margin-bottom:10px">
          <img src="/uploads/<?= basename($s['issuer_logo_path']) ?>"
               style="max-height:70px;object-fit:contain;border-radius:6px;border:1px solid var(--border);padding:6px">
          <div style="font-size:.75rem;color:var(--muted);margin-top:4px">Logo actuel — uploader un nouveau pour le remplacer</div>
        </div>
      <?php endif; ?>
      <div class="field">
        <label>Fichier logo (PNG/JPG, max 2 Mo)</label>
        <input type="file" name="logo" accept="image/*">
      </div>

      <!-- ── Identité ── -->
      <div class="section-title"><i class="fa-solid fa-building"></i> Identité de l'entreprise</div>
      <div class="form-grid-2" style="margin-bottom:14px">
        <div class="field">
          <label>Nom de l'entreprise *</label>
          <input type="text" name="issuer_name" value="<?= htmlspecialchars($s['issuer_name'] ?? '') ?>" placeholder="B'Tech Group SAS" required>
        </div>
        <div class="field">
          <label>Adresse</label>
          <input type="text" name="issuer_address" value="<?= htmlspecialchars($s['issuer_address'] ?? '') ?>" placeholder="ZONE 1, Secteur 28">
        </div>
      </div>
      <div class="form-grid-3">
        <div class="field">
          <label>Téléphone</label>
          <input type="text" name="issuer_phone" value="<?= htmlspecialchars($s['issuer_phone'] ?? '') ?>" placeholder="(+226) 06 36 76 82">
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="issuer_email" value="<?= htmlspecialchars($s['issuer_email'] ?? '') ?>" placeholder="contact@exemple.com">
        </div>
        <div class="field">
          <label>N°IFU</label>
          <input type="text" name="issuer_ifu" value="<?= htmlspecialchars($s['issuer_ifu'] ?? '') ?>" placeholder="00179631E">
        </div>
      </div>

      <!-- ── Signature ── -->
      <div class="section-title"><i class="fa-solid fa-signature"></i> Signature par défaut</div>
      <div class="form-grid-2">
        <div class="field">
          <label>Titre</label>
          <input type="text" name="signatory_title" value="<?= htmlspecialchars($s['signatory_title'] ?? '') ?>" placeholder="Le Président">
        </div>
        <div class="field">
          <label>Nom</label>
          <input type="text" name="signatory_name" value="<?= htmlspecialchars($s['signatory_name'] ?? '') ?>" placeholder="Limaba LOMPO">
        </div>
      </div>

      <!-- ── Fiscalité ── -->
      <div class="section-title"><i class="fa-solid fa-percent"></i> Fiscalité par défaut</div>
      <div class="form-grid-2">
        <div class="field">
          <label>Taux de prélèvement (%)</label>
          <input type="number" name="default_tax_rate" value="<?= htmlspecialchars($s['default_tax_rate'] ?? '5') ?>" min="0" max="100" step="0.1">
        </div>
        <div class="field">
          <label>Libellé de la taxe</label>
          <input type="text" name="default_tax_label" value="<?= htmlspecialchars($s['default_tax_label'] ?? 'Prelevement 5%') ?>">
        </div>
      </div>

      <!-- ── Prestation ── -->
      <div class="section-title"><i class="fa-solid fa-screwdriver-wrench"></i> Ligne de prestation</div>
      <div class="field">
        <label>Libellé de la ligne prestation</label>
        <input type="text" name="prestation_label"
               value="<?= htmlspecialchars($s['prestation_label'] ?? 'Frais de prestation') ?>"
               placeholder="Frais de prestation">
        <span style="font-size:.74rem;color:var(--muted);margin-top:3px">
          Cette ligne apparaît toujours en dernière position dans la facture.
        </span>
      </div>

      <!-- ── Numérotation ── -->
      <div class="section-title"><i class="fa-solid fa-hashtag"></i> Numérotation des factures</div>
      <div class="field">
        <label>Préfixe personnalisé (optionnel)</label>
        <input type="text" name="invoice_prefix"
               value="<?= htmlspecialchars($s['invoice_prefix'] ?? '') ?>"
               placeholder="Ex: FAC, INV, 2026..."
               maxlength="20"
               style="text-transform:uppercase">
        <span style="font-size:.74rem;color:var(--muted);margin-top:3px">
          Si défini : <strong>PREFIXE-YYYYMMDD-N</strong>. Vide = format par défaut <strong>YYYYMMDD-N</strong>.
        </span>
      </div>

      <!-- ── Pied de page ── -->
      <div class="section-title"><i class="fa-solid fa-align-left"></i> Pied de page par défaut</div>
      <div class="field">
        <textarea name="footer_text" rows="4" placeholder="Informations légales, coordonnées bancaires..."><?= htmlspecialchars($s['footer_text'] ?? '') ?></textarea>
      </div>

      <div style="padding-top:20px;border-top:1px solid var(--border);margin-top:24px">
        <button type="submit" class="btn btn-primary" style="padding:11px 28px">
          <i class="fa-solid fa-floppy-disk"></i> Sauvegarder les paramètres
        </button>
      </div>

    </form>
  </div>
</div>

</div>
<?php endif; ?>

<!-- ══ LICENSE TAB ══ -->
<?php if ($tab === 'license'):
  $editionLabel = match($lic['edition']) {
      'pro'        => 'Pro',
      'enterprise' => 'Entreprise',
      'free'       => 'Gratuite',
      default      => 'Non activée',
  };
  $editionColor = match($lic['edition']) {
      'pro'        => '#f59e0b',
      'enterprise' => '#8b5cf6',
      'free'       => '#64748b',
      default      => '#dc2626',
  };
  $editionIcon = match($lic['edition']) {
      'pro'        => '⭐',
      'enterprise' => '<i class="fa-solid fa-building"></i>',
      'free'       => '<i class="fa-solid fa-lock-open"></i>',
      default      => '<i class="fa-solid fa-circle-xmark"></i>',
  };

  // Free plan usage counts
  $oppCount = (new OpportunityRepository())->count();
  $expCount = (new ExpenseRepository())->count();
  $pipeMax  = LicenseService::pipelineMax();
  $expMax   = LicenseService::expenseMax();
?>
<div>

  <!-- Status card -->
  <div class="card" style="margin-bottom:16px;overflow:hidden">
    <div style="background:var(--navy);padding:20px 24px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,.5);font-weight:700;margin-bottom:6px">Licence active</div>
        <div style="font-size:1.5rem;font-weight:900;color:#fff;display:flex;align-items:center;gap:8px">
          <?= $editionIcon ?> Version <?= $editionLabel ?>
        </div>
      </div>
      <div style="background:<?= $editionColor ?>;color:#fff;font-size:.7rem;font-weight:800;padding:6px 14px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px">
        <?= $lic['valid'] ? 'Actif' : 'Expiré' ?>
      </div>
    </div>
    <div style="padding:20px 24px">
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
        <div>
          <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700;margin-bottom:4px">Édition</div>
          <div style="font-weight:700;color:var(--navy)"><?= $editionLabel ?></div>
        </div>
        <div>
          <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700;margin-bottom:4px">Expiration</div>
          <div style="font-weight:700;color:var(--navy)">
            <?= $lic['expires_at'] ? date('d/m/Y', strtotime($lic['expires_at'])) : 'Permanente' ?>
          </div>
        </div>
        <div>
          <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700;margin-bottom:4px">Identifiant poste</div>
          <div style="font-size:.72rem;font-family:monospace;color:var(--navy);word-break:break-all">
            <?= htmlspecialchars(LicenseService::machineId()) ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Quotas (plan gratuit) -->
  <?php if (LicenseService::isFree()): ?>
  <div class="card" style="margin-bottom:16px">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border-soft)">
      <div style="font-weight:700;color:var(--navy);font-size:.88rem">Utilisation — Plan gratuit</div>
      <div style="font-size:.72rem;color:var(--muted);margin-top:2px">Passez en Pro pour lever toutes les limites</div>
    </div>
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:16px">

      <!-- Pipeline -->
      <?php $pct1 = $pipeMax > 0 ? min(100, round($oppCount / $pipeMax * 100)) : 100; ?>
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <span style="font-size:.8rem;font-weight:600;color:var(--navy)">Pipeline (opportunités)</span>
          <span style="font-size:.78rem;font-weight:700;color:<?= $oppCount >= $pipeMax ? '#dc2626' : 'var(--muted)' ?>">
            <?= $oppCount ?> / <?= $pipeMax ?>
          </span>
        </div>
        <div style="height:7px;background:#e2e8f0;border-radius:4px;overflow:hidden">
          <div style="height:100%;width:<?= $pct1 ?>%;background:<?= $pct1 >= 100 ? '#dc2626' : ($pct1 >= 80 ? '#f59e0b' : '#10b981') ?>;border-radius:4px;transition:width .3s"></div>
        </div>
      </div>

      <!-- Dépenses -->
      <?php $pct2 = $expMax > 0 ? min(100, round($expCount / $expMax * 100)) : 100; ?>
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
          <span style="font-size:.8rem;font-weight:600;color:var(--navy)">Dépenses</span>
          <span style="font-size:.78rem;font-weight:700;color:<?= $expCount >= $expMax ? '#dc2626' : 'var(--muted)' ?>">
            <?= $expCount ?> / <?= $expMax ?>
          </span>
        </div>
        <div style="height:7px;background:#e2e8f0;border-radius:4px;overflow:hidden">
          <div style="height:100%;width:<?= $pct2 ?>%;background:<?= $pct2 >= 100 ? '#dc2626' : ($pct2 >= 80 ? '#f59e0b' : '#10b981') ?>;border-radius:4px;transition:width .3s"></div>
        </div>
      </div>

    </div>
  </div>
  <?php endif; ?>

  <!-- Features comparison -->
  <div class="card" style="margin-bottom:16px">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border-soft)">
      <div style="font-weight:700;color:var(--navy);font-size:.88rem">Fonctionnalités incluses</div>
    </div>
    <div style="padding:0">
      <?php
      // [label, free_value, pro_value, enterprise_value]
      // true = ✓, false = —, string = custom text
      $features = [
          ['Facturation',            '10 max',    'Illimitée',  'Illimitée'],
          ['Duplication de factures','5 max',     'Illimitée',  'Illimitée'],
          ['Export PDF',             '15 max',    'Illimité',   'Illimité'],
          ['Pipeline commercial',    '5 max',     'Illimité',   'Illimité'],
          ['Dépenses',               '5 max',     'Illimitées', 'Illimitées'],
          ['Comptabilité mensuelle', false,        true,         true],
          ['Rapport annuel N vs N-1',false,        true,         true],
          ['Export Excel',           '15 max',    'Illimité',   'Illimité'],
          ['Multi-utilisateurs',     false,        true,         true],
      ];
      $cols = ['Fonctionnalité', 'Gratuit', 'Pro', 'Entreprise'];
      $curEd = $lic['edition'];
      ?>
      <table style="width:100%;border-collapse:collapse;font-size:.8rem">
        <thead>
          <tr>
            <?php foreach ($cols as $i => $col): ?>
            <th style="padding:9px 16px;text-align:<?= $i===0?'left':'center' ?>;font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:700;border-bottom:1px solid var(--border);<?= (($i===1&&$curEd==='free')||($i===2&&$curEd==='pro')||($i===3&&$curEd==='enterprise'))?'color:var(--navy);background:#fafbfc':'' ?>">
              <?= $col ?>
              <?php if (($i===1&&$curEd==='free')||($i===2&&$curEd==='pro')||($i===3&&$curEd==='enterprise')): ?>
              <div style="font-size:.55rem;color:<?= $editionColor ?>;font-weight:800;margin-top:2px">✓ VOTRE PLAN</div>
              <?php endif; ?>
            </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($features as $f): ?>
          <tr style="border-bottom:1px solid var(--border-soft)">
            <td style="padding:9px 16px;color:var(--text);font-weight:500"><?= $f[0] ?></td>
            <?php for ($i=1;$i<=3;$i++):
              $v = $f[$i];
              if ($v === true):  ?>
                <td style="text-align:center;padding:9px 16px"><span style="color:#059669;font-weight:700;font-size:1rem">✓</span></td>
              <?php elseif ($v === false): ?>
                <td style="text-align:center;padding:9px 16px"><span style="color:#cbd5e1">—</span></td>
              <?php else: ?>
                <td style="text-align:center;padding:9px 16px;font-size:.78rem;font-weight:600;color:<?= $i===1 ? '#64748b' : '#059669' ?>"><?= htmlspecialchars($v) ?></td>
              <?php endif;
            endfor; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Activate / upgrade -->
  <div class="card">
    <div style="padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px">
      <div>
        <div style="font-weight:700;color:var(--navy);margin-bottom:3px">
          <?= LicenseService::isFree() ? 'Passer à une version supérieure' : 'Changer de clé de licence' ?>
        </div>
        <div style="font-size:.76rem;color:var(--muted)">
          <?= LicenseService::isFree()
            ? 'Entrez une clé Pro ou Entreprise pour débloquer toutes les fonctionnalités.'
            : 'Entrez une nouvelle clé pour mettre à jour ou renouveler votre licence.' ?>
        </div>
      </div>
      <a href="/activate.php" class="btn btn-primary" style="white-space:nowrap;flex-shrink:0">
        <i class="fa-solid fa-key"></i> <?= LicenseService::isFree() ? 'Activer Pro →' : 'Changer de clé →' ?>
      </a>
    </div>
  </div>

</div>
<?php endif; ?>

<?php if ($tab === 'backup'): ?>
<div>

<div class="card" style="margin-bottom:16px">
  <div style="padding:18px 24px">
    <div style="font-size:.88rem;font-weight:700;color:var(--navy);margin-bottom:6px">
      <i class="fa-solid fa-download"></i> Télécharger la sauvegarde
    </div>
    <div style="font-size:.8rem;color:var(--muted);margin-bottom:16px">
      Exporte l'intégralité de la base de données (factures, clients, paiements, paramètres…) dans un fichier <code>.sqlite</code>.
    </div>
    <a href="/settings/backup.php" class="btn btn-primary">
      <i class="fa-solid fa-download"></i> Télécharger la base de données
    </a>
  </div>
</div>

<div class="card">
  <div style="padding:18px 24px">
    <div style="font-size:.88rem;font-weight:700;color:var(--navy);margin-bottom:6px">
      <i class="fa-solid fa-upload"></i> Restaurer une sauvegarde
    </div>
    <div style="font-size:.8rem;color:var(--muted);margin-bottom:4px">
      Remplace la base de données par un fichier de sauvegarde précédent.
    </div>
    <div class="alert" style="background:#fff7ed;border:1px solid #fed7aa;color:#92400e;border-radius:8px;padding:10px 14px;font-size:.78rem;margin-bottom:16px">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <strong>Attention</strong> — cette action est irréversible. Toutes les données actuelles seront remplacées.
      Un backup automatique de sécurité est créé avant la restauration (<code>storage/invoices-pre-restore-*.sqlite</code>).
    </div>
    <form method="POST" action="/settings/restore.php" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div class="field" style="margin:0;flex:1;min-width:240px">
        <label style="font-size:.75rem;font-weight:600;color:var(--muted);display:block;margin-bottom:4px">Fichier de sauvegarde (.sqlite)</label>
        <input type="file" name="backup" accept=".sqlite" required
               style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:.83rem;background:var(--white)">
      </div>
      <button type="submit" class="btn btn-danger"
              onclick="return confirm('Restaurer cette sauvegarde ? Toutes les données actuelles seront remplacées.')">
        <i class="fa-solid fa-rotate-left"></i> Restaurer
      </button>
    </form>
  </div>
</div>

</div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/layout_end.php'; ?>
