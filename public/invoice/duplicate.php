<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Services\LicenseService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /invoice/list.php');
    exit;
}
Auth::verifyCsrf();

$repo = new InvoiceRepository();
$id   = (int) ($_POST['id'] ?? 0);

$source = $repo->find($id);
if ($source === null) {
    header('Location: /invoice/list.php');
    exit;
}

if (!LicenseService::canAdd('duplicate', LicenseService::getCounter('duplicate'))) {
    header('Location: /invoice/list.php?limit=duplicate');
    exit;
}
LicenseService::incrementCounter('duplicate');

$lines = $repo->linesOf($id);

/* New invoice: fresh number + today's dates + reset status */
$today     = date('Y-m-d');
$nextMonth = date('Y-m-d', strtotime('+1 month'));

$newData = array_merge($source, [
    'number'             => $repo->nextNumber(),
    'status'             => 'brouillon',
    'issued_at'          => $today,
    'due_at'             => $nextMonth,
    'total_ht'           => $source['total_ht'],
    'total_net'          => $source['total_net'],
    'lines'              => array_map(fn($l) => [
        'description' => $l['description'],
        'quantity'    => $l['quantity'],
        'unit_price'  => $l['unit_price'],
    ], $lines),
]);

unset($newData['id'], $newData['created_at'], $newData['updated_at']);

$newId = $repo->create($newData);

header('Location: /invoice/edit.php?id=' . $newId . '&duplicated=1');
exit;
