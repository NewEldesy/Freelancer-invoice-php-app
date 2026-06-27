<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\ClientRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /client/index.php');
    exit;
}
Auth::verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    (new ClientRepository())->delete($id);
}

header('Location: /client/index.php');
exit;
