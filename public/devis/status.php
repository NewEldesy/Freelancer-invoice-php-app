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
$status = $_POST['status'] ?? '';
$allowed = ['brouillon', 'envoyé', 'accepté', 'refusé'];

$record = $repo->find($id);
if ($record === null || $record['type'] !== 'DEVIS' || !in_array($status, $allowed, true)) {
    header('Location: /devis/index.php');
    exit;
}

$repo->updateStatus($id, $status);
header('Location: /devis/edit.php?id=' . $id);
exit;
