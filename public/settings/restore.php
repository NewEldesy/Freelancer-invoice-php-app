<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Auth\Auth;
Auth::requireManager();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /settings.php?tab=backup');
    exit;
}
Auth::verifyCsrf();

$back = '/settings.php?tab=backup';

if (empty($_FILES['backup']['tmp_name']) || $_FILES['backup']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ' . $back . '&restore_error=no_file');
    exit;
}

$tmpPath = $_FILES['backup']['tmp_name'];

// Verify SQLite magic bytes
$fh      = fopen($tmpPath, 'rb');
$magic   = fread($fh, 16);
fclose($fh);

if (!str_starts_with($magic, "SQLite format 3\000")) {
    header('Location: ' . $back . '&restore_error=invalid_file');
    exit;
}

$dbPath = __DIR__ . '/../../storage/invoices.sqlite';

// Safety copy before overwriting (keep last 5 only)
$safeBackup = __DIR__ . '/../../storage/invoices-pre-restore-' . date('Ymd-His') . '.sqlite';
copy($dbPath, $safeBackup);
$oldBackups = glob(__DIR__ . '/../../storage/invoices-pre-restore-*.sqlite') ?: [];
if (count($oldBackups) > 5) {
    usort($oldBackups, fn($a, $b) => filemtime($a) - filemtime($b));
    foreach (array_slice($oldBackups, 0, count($oldBackups) - 5) as $old) {
        @unlink($old);
    }
}

if (!copy($tmpPath, $dbPath)) {
    header('Location: ' . $back . '&restore_error=write_failed');
    exit;
}

header('Location: ' . $back . '&restore_ok=1');
exit;
