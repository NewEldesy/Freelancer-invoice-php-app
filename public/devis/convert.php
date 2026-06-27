<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /devis/index.php');
    exit;
}
Auth::verifyCsrf();

use App\Database\InvoiceRepository;

$repo = new InvoiceRepository();
$id   = (int) ($_POST['id'] ?? 0);

$devis = $repo->find($id);
if ($devis === null || $devis['type'] !== 'DEVIS') {
    header('Location: /devis/index.php');
    exit;
}

// Idempotent: redirect to existing facture if already converted
$existing = $repo->findConvertedInvoice($id);
if ($existing !== null) {
    header('Location: /invoice/edit.php?id=' . $existing['id']);
    exit;
}

$lines = $repo->linesOf($id);
$today = date('Y-m-d');

$newData = array_merge($devis, [
    'number'    => $repo->nextNumber($today),
    'type'      => 'FACTURE PROFORMA',
    'status'    => 'brouillon',
    'issued_at' => $today,
    'due_at'    => date('Y-m-d', strtotime('+1 month')),
    'origin_id' => $id,
    'lines'     => array_map(fn($l) => [
        'description' => $l['description'],
        'quantity'    => $l['quantity'],
        'unit_price'  => $l['unit_price'],
    ], $lines),
]);
unset($newData['id'], $newData['created_at'], $newData['updated_at']);

$newId = $repo->create($newData);

// Mark devis as accepted if not already
if ($devis['status'] !== 'accepté') {
    $repo->updateStatus($id, 'accepté');
}

header('Location: /invoice/edit.php?id=' . $newId . '&from_devis=1');
exit;
