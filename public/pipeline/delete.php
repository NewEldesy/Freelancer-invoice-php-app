<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\OpportunityRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pipeline/index.php');
    exit;
}
Auth::verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    (new OpportunityRepository())->delete($id);
}

header('Location: /pipeline/index.php');
exit;
