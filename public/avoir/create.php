<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Services\RequestValidator;

$repo      = new InvoiceRepository();
$originId  = (int) ($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? 0);
$origin    = $originId > 0 ? $repo->find($originId) : null;

if ($origin === null || $origin['type'] === 'AVOIR') {
    header('Location: /invoice/list.php');
    exit;
}

$errors  = [];
$today   = date('Y-m-d');
// Unique avoir number: AV-YYYYMMDD-N (increments across all avoirs today for this origin)
$avPrefix   = 'AV-' . date('Ymd');
$stmt       = \App\Database\Database::connection()->prepare(
    "SELECT COUNT(*) FROM invoices WHERE type = 'AVOIR' AND number LIKE ?"
);
$stmt->execute([$avPrefix . '-%']);
$avSeq = (int) $stmt->fetchColumn() + 1;

$invoice = [
    'number'           => $avPrefix . '-' . $avSeq,
    'type'             => 'AVOIR',
    'status'           => 'brouillon',
    'issued_at'        => $today,
    'due_at'           => $today,
    'issuer_name'      => $origin['issuer_name'],
    'issuer_address'   => $origin['issuer_address'],
    'issuer_phone'     => $origin['issuer_phone'],
    'issuer_email'     => $origin['issuer_email'],
    'issuer_ifu'       => $origin['issuer_ifu'],
    'issuer_logo_path' => $origin['issuer_logo_path'],
    'client_name'      => $origin['client_name'],
    'client_address'   => $origin['client_address'],
    'client_contact'   => $origin['client_contact'],
    'tax_rate'         => $origin['tax_rate'],
    'tax_label'        => $origin['tax_label'],
    'signatory_title'  => $origin['signatory_title'],
    'signatory_name'   => $origin['signatory_name'],
    'footer_text'      => $origin['footer_text'],
    'prestation_label' => $origin['prestation_label'],
    'prestation_amount'=> 0,
    'total_ht'         => 0,
    'total_net'        => 0,
    'subject'          => 'Avoir sur ' . $origin['number'],
    'origin_id'        => $originId,
];

$originLines = $repo->linesOf($originId);
$lines = array_map(fn($l) => [
    'description' => $l['description'],
    'quantity'    => $l['quantity'],
    'unit_price'  => $l['unit_price'],
], $originLines);
if (empty($lines)) {
    $lines = [['description' => '', 'quantity' => 1, 'unit_price' => 0]];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postLines = [];
    foreach ($_POST['line_desc'] ?? [] as $i => $desc) {
        $postLines[] = [
            'description' => $desc,
            'quantity'    => (int) ($_POST['line_qty'][$i]   ?? 1),
            'unit_price'  => (int) ($_POST['line_price'][$i] ?? 0),
        ];
    }

    $postData = [
        'number'          => trim($_POST['number']          ?? ''),
        'type'            => 'AVOIR',
        'status'          => in_array($_POST['status'] ?? '', ['brouillon', 'émis'], true) ? $_POST['status'] : 'brouillon',
        'subject'         => trim($_POST['subject']         ?? ''),
        'issued_at'       => $_POST['issued_at']            ?? '',
        'due_at'          => $_POST['due_at']               ?? '',
        'issuer_name'     => trim($_POST['issuer_name']     ?? ''),
        'issuer_address'  => trim($_POST['issuer_address']  ?? ''),
        'issuer_phone'    => trim($_POST['issuer_phone']    ?? ''),
        'issuer_email'    => trim($_POST['issuer_email']    ?? ''),
        'issuer_ifu'      => trim($_POST['issuer_ifu']      ?? ''),
        'issuer_logo_path'=> $_POST['issuer_logo_path']    ?? '',
        'client_name'     => trim($_POST['client_name']     ?? ''),
        'client_address'  => trim($_POST['client_address']  ?? ''),
        'client_contact'  => trim($_POST['client_contact']  ?? ''),
        'tax_rate'        => (float) ($_POST['tax_rate']    ?? 5),
        'tax_label'       => trim($_POST['tax_label']       ?? ''),
        'signatory_title' => trim($_POST['signatory_title'] ?? ''),
        'signatory_name'  => trim($_POST['signatory_name']  ?? ''),
        'footer_text'        => $_POST['footer_text']             ?? '',
        'prestation_label'   => trim($_POST['prestation_label']   ?? 'Frais de prestation'),
        'prestation_amount'  => (int) ($_POST['prestation_amount'] ?? 0),
        'total_ht'           => (int) ($_POST['total_ht']         ?? 0),
        'total_net'          => (int) ($_POST['total_net']        ?? 0),
        'origin_id'          => $originId,
        'lines'              => $postLines,
    ];

    $validator = new RequestValidator();
    if ($validator->validate($postData)) {
        $newId = $repo->create($postData);
        header('Location: /invoice/edit.php?id=' . $originId . '&avoir_created=1');
        exit;
    }

    $errors  = $validator->errors();
    $invoice = $postData;
    $lines   = $postLines;
}

$pageTitle     = 'Nouvel avoir — facture ' . htmlspecialchars($origin['number']);
$currentPage   = 'list';
$formAction    = '/avoir/create.php?invoice_id=' . $originId;
$lockedType    = 'AVOIR';
$avoirStatuses = true;
$topbarActions = '<a href="/invoice/edit.php?id=' . $originId . '" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>';

require __DIR__ . '/../../templates/layout.php';

if (!empty($errors)): ?>
<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i>
  <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
</div>
<?php endif; ?>

<div class="alert" style="background:#fff7ed;border:1px solid #fed7aa;color:#92400e;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.83rem">
  <i class="fa-solid fa-triangle-exclamation"></i>
  Avoir sur la facture <strong><?= htmlspecialchars($origin['number']) ?></strong>
  (<?= htmlspecialchars($origin['client_name']) ?>) — montant original : <?= number_format((int)$origin['total_net'], 0, ',', ' ') ?> FCFA.
  Ajustez les lignes et montants selon le remboursement.
</div>

<?php require __DIR__ . '/../../templates/invoice_form.php'; ?>

<?php require __DIR__ . '/../../templates/layout_end.php'; ?>
