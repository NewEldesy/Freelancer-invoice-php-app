<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Services\RequestValidator;

$repo    = new InvoiceRepository();
$errors  = [];
$invoice = [
    'number' => $repo->nextQuoteNumber(),
    'type'   => 'DEVIS',
    'status' => 'brouillon',
];
$lines   = [['description' => '', 'quantity' => 1, 'unit_price' => 0]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$postData, $logoPath] = processDevisPost();

    $validator = new RequestValidator();
    if ($validator->validate($postData)) {
        $id = $repo->create($postData);
        header('Location: /devis/edit.php?id=' . $id . '&created=1');
        exit;
    }

    $errors  = $validator->errors();
    $invoice = $postData;
    $lines   = $postData['lines'] ?? $lines;
}

function processDevisPost(): array
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

$pageTitle     = 'Nouveau devis';
$currentPage   = 'devis';
$formAction    = '/devis/create.php';
$lockedType    = 'DEVIS';
$devisStatuses = true;
$topbarActions = '<a href="/devis/index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Retour</a>';

require __DIR__ . '/../../templates/layout.php';
require __DIR__ . '/../../templates/invoice_form.php';
require __DIR__ . '/../../templates/layout_end.php';
