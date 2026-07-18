<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Services\ActivityLogger;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /invoice/list.php');
    exit;
}
Auth::verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $repo = new InvoiceRepository();
    $inv  = $repo->find($id);
    $repo->delete($id);
    ActivityLogger::log('invoice.delete', 'Suppression facture #' . ($inv['number'] ?? $id));
}

header('Location: /invoice/list.php');
exit;
