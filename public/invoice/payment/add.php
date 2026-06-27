<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Database\PaymentRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /invoice/list.php');
    exit;
}
Auth::verifyCsrf();

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
$amount    = (int) ($_POST['amount']     ?? 0);
$paidAt    = trim($_POST['paid_at']      ?? date('Y-m-d'));
$note      = trim($_POST['note']         ?? '');

if ($invoiceId <= 0) {
    header('Location: /invoice/list.php');
    exit;
}
if ($amount <= 0) {
    header('Location: /invoice/edit.php?id=' . $invoiceId . '&pay_error=1');
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt)) {
    $paidAt = date('Y-m-d');
}

$invoiceRepo = new InvoiceRepository();
$invoice     = $invoiceRepo->find($invoiceId);

if ($invoice === null) {
    header('Location: /invoice/list.php');
    exit;
}

$payRepo = new PaymentRepository();
$payRepo->add($invoiceId, $amount, $paidAt, $note);

// Auto-mark as paid if total payments >= total_net
$totalPaid = $payRepo->totalForInvoice($invoiceId);
if ($totalPaid >= (int) $invoice['total_net'] && $invoice['status'] === 'envoyée') {
    $invoiceRepo->updateStatus($invoiceId, 'payée');
}

header('Location: /invoice/edit.php?id=' . $invoiceId . '&pay_added=1');
exit;
