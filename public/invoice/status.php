<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Database\ProjectRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
Auth::verifyCsrf();

$id     = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$allowed = ['brouillon', 'envoyée', 'payée', 'annulée'];

if ($id > 0 && in_array($status, $allowed, true)) {
    $invRepo = new InvoiceRepository();
    $invRepo->updateStatus($id, $status);

    /* Auto-create project when invoice is sent for the first time */
    if ($status === 'envoyée') {
        $projRepo = new ProjectRepository();
        if ($projRepo->findByInvoice($id) === null) {
            $invoice = $invRepo->find($id);
            if ($invoice !== null) {
                $title = ($invoice['subject'] ?: $invoice['client_name'] ?: 'Projet ' . $invoice['number']);
                $projRepo->create($id, $title);
            }
        }
    }

    http_response_code(200);
} else {
    http_response_code(400);
}
