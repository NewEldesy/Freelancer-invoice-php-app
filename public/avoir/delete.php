<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /invoice/list.php');
    exit;
}
Auth::verifyCsrf();

use App\Database\InvoiceRepository;

$repo      = new InvoiceRepository();
$id        = (int) ($_POST['id'] ?? 0);
$record    = $repo->find($id);

if ($record === null || $record['type'] !== 'AVOIR') {
    header('Location: /invoice/list.php');
    exit;
}

$originId = (int) ($record['origin_id'] ?? 0);
$repo->delete($id);

header('Location: /invoice/edit.php?id=' . $originId . '&avoir_deleted=1');
exit;
