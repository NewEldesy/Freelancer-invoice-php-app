<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Services\RequestValidator;

$repo = new InvoiceRepository();
$id   = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$record = $repo->find($id);
if ($record === null || $record['type'] !== 'DEVIS') {
    header('Location: /devis/index.php');
    exit;
}

$errors       = [];
$flashSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$postData, $logoPath] = processDevisPost($record['issuer_logo_path'] ?? '');

    $validator = new RequestValidator();
    if ($validator->validate($postData)) {
        $repo->update($id, $postData);
        $record       = $repo->find($id);
        $flashSuccess = 'Devis mis à jour.';
    } else {
        $errors = $validator->errors();
        $record = array_merge($record, $postData);
    }
}

if (isset($_GET['created']))    $flashSuccess = 'Devis créé avec succès !';
if (isset($_GET['converted']))  $flashSuccess = 'Devis converti en facture avec succès.';

$converted = $repo->findConvertedInvoice($id);

$lines = $repo->linesOf($id);
if (empty($lines)) {
    $lines = [['description' => '', 'quantity' => 1, 'unit_price' => 0]];
}

function processDevisPost(string $existingLogo): array
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
        'type'            => 'DEVIS',
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

$pageTitle     = 'Devis — ' . htmlspecialchars($record['number']);
$currentPage   = 'devis';
$lockedType    = 'DEVIS';
$devisStatuses = true;
$invoice       = $record;
$formAction    = '/devis/edit.php';
$topbarActions = '
  <a href="/devis/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>
  <a href="/devis/pdf.php?id=' . $id . '" class="btn btn-secondary" target="_blank"><i class="fa-solid fa-file-pdf"></i> PDF</a>
';

require __DIR__ . '/../../templates/layout.php';

if ($flashSuccess): ?>
<div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif; ?>

<?php if ($converted): ?>
<div class="alert alert-success" style="margin-bottom:12px">
  <i class="fa-solid fa-circle-check"></i> Converti en facture
  <strong><?= htmlspecialchars($converted['number']) ?></strong> —
  <a href="/invoice/edit.php?id=<?= $converted['id'] ?>" style="font-weight:600">Voir la facture</a>
</div>
<?php elseif (Auth::can('write') && $record['status'] === 'accepté'): ?>
<div style="margin-bottom:16px">
  <form method="POST" action="/devis/convert.php" style="display:inline">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-success">
      <i class="fa-solid fa-file-invoice"></i> Convertir en facture
    </button>
  </form>
  <span style="font-size:.78rem;color:var(--muted);margin-left:8px">Crée une nouvelle facture à partir de ce devis</span>
</div>
<?php elseif (Auth::can('write') && $record['status'] === 'brouillon'): ?>
<div style="margin-bottom:16px;display:flex;gap:8px">
  <form method="POST" action="/devis/status.php">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="status" value="envoyé">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-envelope"></i> Marquer envoyé</button>
  </form>
</div>
<?php elseif (Auth::can('write') && $record['status'] === 'envoyé'): ?>
<div style="margin-bottom:16px;display:flex;gap:8px">
  <form method="POST" action="/devis/status.php">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="status" value="accepté">
    <button type="submit" class="btn btn-success"><i class="fa-solid fa-circle-check"></i> Marquer accepté</button>
  </form>
  <form method="POST" action="/devis/status.php">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="status" value="refusé">
    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-circle-xmark"></i> Marquer refusé</button>
  </form>
</div>
<?php endif; ?>

<?php if (Auth::can('write')): ?>
<?php require __DIR__ . '/../../templates/invoice_form.php'; ?>
<?php endif; ?>

<?php if (Auth::can('write')): ?>
<div style="margin-top:16px">
  <form method="POST" action="/devis/delete.php" onsubmit="return confirm('Supprimer ce devis ?')">
    <input type="hidden" name="id" value="<?= $id ?>">
    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Supprimer le devis</button>
  </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
