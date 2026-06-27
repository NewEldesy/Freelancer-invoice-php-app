<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

use App\Database\ExpenseRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /expense/index.php');
    exit;
}
Auth::verifyCsrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    (new ExpenseRepository())->delete($id);
}

$back = $_POST['back'] ?? '/expense/index.php';
if (!str_starts_with($back, '/') || str_contains($back, '//')) {
    $back = '/expense/index.php';
}
header('Location: ' . $back);
exit;
