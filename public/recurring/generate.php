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
use App\Database\InvoiceRepository;
use App\Database\SettingsRepository;

$recurRepo = new RecurringRepository();
$invoRepo  = new InvoiceRepository();
$settings  = (new SettingsRepository())->all();

$id  = (int) ($_POST['id'] ?? 0);
$tmpl = $recurRepo->find($id);

if ($tmpl === null || !$tmpl['active']) {
    header('Location: /recurring/index.php');
    exit;
}

$newInvoiceId = $recurRepo->generateInvoice($tmpl, $invoRepo, $settings);

header('Location: /invoice/edit.php?id=' . $newInvoiceId . '&from_recurring=1');
exit;
