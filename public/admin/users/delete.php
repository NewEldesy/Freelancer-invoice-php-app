<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use App\Auth\Auth;
use App\Database\UserRepository;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/users.php');
    exit;
}
Auth::verifyCsrf();

$id   = (int) ($_POST['id'] ?? 0);
$me   = Auth::user();

/* Prevent self-deletion */
if ($id > 0 && $id !== (int)$me['id']) {
    (new UserRepository())->delete($id);
}

header('Location: /admin/users.php');
exit;
