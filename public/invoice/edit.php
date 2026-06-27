<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Database\PaymentRepository;
use App\Services\RequestValidator;

$repo = new InvoiceRepository();
$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$record = $repo->find($id);
if ($record === null) {
    header('Location: /invoice/list.php');
    exit;
}

$errors      = [];
$flashSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$postData, $logoPath] = processInvoicePost($record['issuer_logo_path'] ?? '');

    $validator = new RequestValidator();
    if ($validator->validate($postData)) {
        $repo->update($id, $postData);
        $record       = $repo->find($id);
        $flashSuccess = 'Facture mise à jour avec succès.';
    } else {
        $errors  = $validator->errors();
        $record  = array_merge($record, $postData);
    }
}

if (isset($_GET['created'])) {
    $flashSuccess = 'Facture créée avec succès !';
}
if (isset($_GET['duplicated'])) {
    $flashSuccess = 'Facture dupliquée — numéro ' . htmlspecialchars($record['number']) . ' · modifiez les lignes puis enregistrez.';
}
if (isset($_GET['from_pipeline'])) {
    $flashSuccess = 'Opportunité convertie en facture ' . htmlspecialchars($record['number']) . ' · complétez les lignes puis enregistrez.';
}

$payRepo   = new PaymentRepository();
$payments  = $payRepo->allForInvoice($id);
$totalPaid = $payRepo->totalForInvoice($id);

if (isset($_GET['pay_added']))   $flashSuccess = 'Paiement enregistré.';
if (isset($_GET['pay_deleted'])) $flashSuccess = 'Paiement supprimé.';
if (isset($_GET['pay_error']))   $errors[]     = 'Montant invalide.';

$lines = $repo->linesOf($id);
if (empty($lines)) {
    $lines = [['description' => '', 'quantity' => 1, 'unit_price' => 0]];
}

function processInvoicePost(string $existingLogo): array
{
    $logoPath = $existingLogo;
    if (!empty($_FILES['logo']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo   = new \finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($_FILES['logo']['tmp_name']);
        if (in_array($ext, $allowed, true)
            && in_array($mime, $allowedMime, true)
            && $_FILES['logo']['size'] <= 2_000_000
        ) {
            // Force .png extension regardless of original name to prevent PHP execution
            $dest = __DIR__ . '/../uploads/' . uniqid('logo_', true) . '.png';
            move_uploaded_file($_FILES['logo']['tmp_name'], $dest);
            $logoPath = $dest;
        }
    }

    $lines = [];
    foreach ($_POST['line_desc'] ?? [] as $i => $desc) {
        $lines[] = [
            'description' => $desc,
            'quantity'    => (int) ($_POST['line_qty'][$i]   ?? 1),
            'unit_price'  => (int) ($_POST['line_price'][$i] ?? 0),
        ];
    }

    return [[
        'number'          => trim($_POST['number']          ?? ''),
        'type'            => $_POST['type']                 ?? 'FACTURE PROFORMA',
        'status'          => $_POST['status']               ?? 'brouillon',
        'subject'         => trim($_POST['subject']         ?? ''),
        'issued_at'       => $_POST['issued_at']            ?? '',
        'due_at'          => $_POST['due_at']               ?? '',
        'issuer_name'     => trim($_POST['issuer_name']     ?? ''),
        'issuer_address'  => trim($_POST['issuer_address']  ?? ''),
        'issuer_phone'    => trim($_POST['issuer_phone']    ?? ''),
        'issuer_email'    => trim($_POST['issuer_email']    ?? ''),
        'issuer_ifu'      => trim($_POST['issuer_ifu']      ?? ''),
        'issuer_logo_path'=> $logoPath,
        'client_name'     => trim($_POST['client_name']     ?? ''),
        'client_address'  => trim($_POST['client_address']  ?? ''),
        'client_contact'  => trim($_POST['client_contact']  ?? ''),
        'tax_rate'        => (float) ($_POST['tax_rate']    ?? 5),
        'tax_label'       => trim($_POST['tax_label']       ?? 'Prelevement 5%'),
        'signatory_title' => trim($_POST['signatory_title'] ?? ''),
        'signatory_name'  => trim($_POST['signatory_name']  ?? ''),
        'footer_text'        => $_POST['footer_text']             ?? '',
        'prestation_label'   => trim($_POST['prestation_label']   ?? 'Frais de prestation'),
        'prestation_amount'  => (int) ($_POST['prestation_amount'] ?? 0),
        'total_ht'           => (int) ($_POST['total_ht']         ?? 0),
        'total_net'          => (int) ($_POST['total_net']        ?? 0),
        'lines'              => $lines,
    ], $logoPath];
}

$invoice     = $record;
$pageTitle   = 'Modifier — ' . htmlspecialchars($record['number']);
$currentPage = 'list';
$formAction  = '/invoice/edit.php';

$topbarActions = '
    <form method="POST" action="/invoice/duplicate.php" style="display:inline">
        <input type="hidden" name="id" value="' . $id . '">
        <button type="submit" class="btn btn-secondary" title="Créer une nouvelle facture basée sur celle-ci"><i class="fa-solid fa-clone"></i> Dupliquer</button>
    </form>
    <a href="/invoice/pdf.php?id=' . $id . '" class="btn btn-secondary" target="_blank"><i class="fa-solid fa-file-pdf"></i> PDF</a>
    <a href="/invoice/list.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
';

require __DIR__ . '/../../templates/layout.php';
?>

<?php if ($flashSuccess): ?>
<div class="alert" style="background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;border-radius:8px;padding:10px 14px;font-size:.83rem;margin-bottom:14px">
  ✅ <?= htmlspecialchars($flashSuccess) ?>
</div>
<?php elseif (!empty($errors)): ?>
<div class="alert alert-error" style="margin-bottom:14px">⚠ <?= implode('<br>⚠ ', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <?php require __DIR__ . '/../../templates/invoice_form.php'; ?>
    </div>
</div>

<?php
$totalNet  = (int) $record['total_net'];
$solde     = $totalNet - $totalPaid;
$isPaid    = $record['status'] === 'payée';
$isSent    = $record['status'] === 'envoyée';
$isOverdue = $isSent && !empty($record['due_at']) && $record['due_at'] < date('Y-m-d');
?>
<!-- ── Section paiements ── -->
<div class="card" style="margin-top:20px">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <h2>💰 Paiements reçus</h2>
    <?php if ($totalNet > 0): ?>
    <div style="font-size:.82rem;color:var(--muted)">
      Total facture : <strong><?= number_format($totalNet, 0, ',', ' ') ?> FCFA</strong>
      · Encaissé : <strong style="color:var(--green)"><?= number_format($totalPaid, 0, ',', ' ') ?> FCFA</strong>
      · Solde : <strong style="color:<?= $solde > 0 ? 'var(--red)' : 'var(--green)' ?>"><?= number_format($solde, 0, ',', ' ') ?> FCFA</strong>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($isOverdue): ?>
  <div style="padding:10px 20px;background:#fff7ed;border-bottom:1px solid #fed7aa">
    <span style="font-size:.82rem;color:#92400e"><i class="fa-solid fa-triangle-exclamation"></i> <strong>Facture en retard</strong> — échéance dépassée depuis le <?= date('d/m/Y', strtotime($record['due_at'])) ?></span>
  </div>
  <?php endif; ?>

  <!-- Barre de progression -->
  <?php if ($totalNet > 0 && $totalPaid > 0): ?>
  <div style="padding:12px 20px 0">
    <?php $pct = min(100, round($totalPaid / $totalNet * 100)); ?>
    <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--muted);margin-bottom:5px">
      <span>Progression du paiement</span><span><?= $pct ?>%</span>
    </div>
    <div style="height:6px;background:var(--border-soft);border-radius:99px;overflow:hidden;margin-bottom:14px">
      <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct >= 100 ? 'var(--green)' : '#f59e0b' ?>;border-radius:99px;transition:width .6s"></div>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($payments)): ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Date</th>
          <th style="text-align:right">Montant</th>
          <th>Note</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
      <tr>
        <td style="font-size:.82rem"><?= date('d/m/Y', strtotime($p['paid_at'])) ?></td>
        <td style="text-align:right;font-weight:600"><?= number_format((int)$p['amount'], 0, ',', ' ') ?> <span style="font-size:.7rem;color:var(--muted);font-weight:400">FCFA</span></td>
        <td style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($p['note'] ?? '—') ?></td>
        <td>
          <?php if (Auth::can('write')): ?>
          <form method="POST" action="/invoice/payment/delete.php" style="display:inline"
                onsubmit="return confirm('Supprimer ce paiement ?')">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <input type="hidden" name="invoice_id" value="<?= $id ?>">
            <button type="submit" class="btn btn-danger btn-sm btn-icon">🗑️</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php elseif (!$isPaid): ?>
  <div style="padding:20px;text-align:center;color:var(--muted);font-size:.82rem">Aucun paiement enregistré.</div>
  <?php endif; ?>

  <!-- Formulaire d'ajout -->
  <?php if (Auth::can('write') && !$isPaid): ?>
  <div style="padding:16px 20px;border-top:1px solid var(--border);background:var(--bg)">
    <div style="font-size:.82rem;font-weight:600;margin-bottom:10px;color:var(--navy)">Enregistrer un paiement</div>
    <form method="POST" action="/invoice/payment/add.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="invoice_id" value="<?= $id ?>">
      <div class="field" style="margin:0;flex:0 0 150px">
        <label style="font-size:.75rem">Montant (FCFA) *</label>
        <input type="number" name="amount" min="1"
               value="<?= $solde > 0 ? $solde : '' ?>"
               placeholder="<?= number_format($solde > 0 ? $solde : $totalNet, 0) ?>"
               style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:.83rem" required>
      </div>
      <div class="field" style="margin:0;flex:0 0 145px">
        <label style="font-size:.75rem">Date</label>
        <input type="date" name="paid_at" value="<?= date('Y-m-d') ?>"
               style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:.83rem">
      </div>
      <div class="field" style="margin:0;flex:1;min-width:160px">
        <label style="font-size:.75rem">Note (optionnel)</label>
        <input type="text" name="note" placeholder="Virement, chèque…"
               style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:.83rem">
      </div>
      <button type="submit" class="btn btn-primary" style="padding:8px 16px;white-space:nowrap"><i class="fa-solid fa-check"></i> Enregistrer</button>
    </form>
  </div>
  <?php elseif ($isPaid): ?>
  <div style="padding:14px 20px;background:#f0fdf4;border-top:1px solid #bbf7d0;font-size:.82rem;color:#166534;text-align:center">
    ✅ Facture entièrement payée
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
