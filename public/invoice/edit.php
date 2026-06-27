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
        <button type="submit" class="btn btn-secondary" title="Créer une nouvelle facture basée sur celle-ci">⧉ Dupliquer</button>
    </form>
    <a href="/invoice/pdf.php?id=' . $id . '" class="btn btn-secondary" target="_blank">📄 PDF</a>
    <a href="/invoice/list.php" class="btn btn-secondary">← Retour</a>
';

require __DIR__ . '/../../templates/layout.php';
?>

<div class="card">
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">⚠ <?= implode('<br>⚠ ', array_map('htmlspecialchars', $errors)) ?></div>
        <?php endif; ?>
        <?php require __DIR__ . '/../../templates/invoice_form.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
