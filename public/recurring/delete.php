<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /recurring/index.php');
    exit;
}

Auth::verifyCsrf();

use App\Database\RecurringRepository;

$id = (int) ($_POST['id'] ?? 0);
(new RecurringRepository())->delete($id);

header('Location: /recurring/index.php?deleted=1');
exit;
