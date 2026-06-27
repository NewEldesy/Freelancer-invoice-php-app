<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\PaymentRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /invoice/list.php');
    exit;
}
Auth::verifyCsrf();

$payRepo   = new PaymentRepository();
$id        = (int) ($_POST['id']         ?? 0);
$invoiceId = (int) ($_POST['invoice_id'] ?? 0);

if ($invoiceId <= 0) {
    header('Location: /invoice/list.php');
    exit;
}

$payment = $id > 0 ? $payRepo->find($id) : null;
// Guard: ensure the payment belongs to the claimed invoice
if ($payment && (int) $payment['invoice_id'] === $invoiceId) {
    $payRepo->delete($id);
}

header('Location: /invoice/edit.php?id=' . $invoiceId . '&pay_deleted=1');
exit;
