<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

$dbPath = __DIR__ . '/../../storage/invoices.sqlite';

if (!file_exists($dbPath)) {
    http_response_code(404);
    exit('Base de données introuvable.');
}

$filename = 'freelancer-invoice-backup-' . date('Ymd-His') . '.sqlite';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($dbPath));
header('Cache-Control: no-cache, must-revalidate');

readfile($dbPath);
exit;
