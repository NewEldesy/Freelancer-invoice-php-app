<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Services\RequestValidator;
use App\Services\LicenseService;

$repo    = new InvoiceRepository();
$errors  = [];
$invoice = ['number' => $repo->nextNumber()];

$invoiceLocked = !LicenseService::canAdd('invoice', $repo->count());
if ($invoiceLocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors[] = 'Limite du plan gratuit atteinte (' . LicenseService::invoiceMax() . ' factures max). Activez une licence Pro pour continuer.';
}
$lines   = [
    ['description' => '', 'quantity' => 1, 'unit_price' => 0],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$postData, $logoPath] = processInvoicePost();

    $validator = new RequestValidator();
    if ($validator->validate($postData)) {
        $id   = $repo->create($postData);
        header('Location: /invoice/edit.php?id=' . $id . '&created=1');
        exit;
    }

    $errors  = $validator->errors();
    $invoice = $postData;
    $lines   = $postData['lines'] ?? $lines;
}

function processInvoicePost(): array
{
    $logoPath = '';
    if (!empty($_FILES['logo']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed, true) && $_FILES['logo']['size'] <= 2_000_000) {
            $dest = __DIR__ . '/../uploads/' . uniqid('logo_', true) . '.' . $ext;
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

$pageTitle   = 'Nouvelle facture';
$currentPage = 'create';
$formAction  = '/invoice/create.php';

$topbarActions = '<a href="/invoice/list.php" class="btn btn-secondary">← Retour</a>';

require __DIR__ . '/../../templates/layout.php';

if ($invoiceLocked): ?>
<div class="alert" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:10px;padding:16px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px">
  <div><strong>🔒 Limite atteinte — Plan gratuit</strong><br><span style="font-size:.82rem">Maximum <?= LicenseService::invoiceMax() ?> factures sur le plan gratuit.</span></div>
  <a href="/activate.php" class="btn btn-primary" style="white-space:nowrap">⭐ Passer Pro</a>
</div>
<?php elseif (!empty($errors)): ?>
<div class="alert alert-error">⚠ <?= implode('<br>⚠ ', array_map('htmlspecialchars', $errors)) ?></div>
<?php endif;
?>

<div class="card">
    <div class="card-body">
        <?php require __DIR__ . '/../../templates/invoice_form.php'; ?>
    </div>
</div>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
