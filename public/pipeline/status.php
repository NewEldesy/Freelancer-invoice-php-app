<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\OpportunityRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
Auth::verifyCsrf();

$id     = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$allowed = ['prospect', 'devis_envoye', 'negociation', 'gagne', 'perdu'];

if ($id > 0 && in_array($status, $allowed, true)) {
    (new OpportunityRepository())->updateStatus($id, $status);
    http_response_code(200);
} else {
    http_response_code(400);
}
