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

$repo   = new InvoiceRepository();
$id     = (int) ($_POST['id'] ?? 0);
$record = $repo->find($id);

if ($record === null || $record['type'] !== 'DEVIS') {
    header('Location: /devis/index.php');
    exit;
}

$repo->delete($id);
header('Location: /devis/index.php?deleted=1');
exit;
