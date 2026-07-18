<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\InvoiceRepository;
use App\Database\ProjectRepository;
use App\Services\ActivityLogger;

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
    $inv     = $invRepo->find($id);
    if ($inv === null) { http_response_code(404); exit; }

    $invRepo->updateStatus($id, $status);
    ActivityLogger::log('invoice.status', 'Statut facture #' . $inv['number'] . ' → ' . $status);

    /* Auto-create project when invoice is sent for the first time */
    if ($status === 'envoyée') {
        $projRepo = new ProjectRepository();
        if ($projRepo->findByInvoice($id) === null) {
            $title = ($inv['subject'] ?: $inv['client_name'] ?: 'Projet ' . $inv['number']);
            $projRepo->create($id, $title);
        }
    }

    http_response_code(200);
} else {
    http_response_code(400);
}
