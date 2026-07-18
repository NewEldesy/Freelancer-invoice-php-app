<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Auth;
use App\Services\ActivityLogger;

if (!empty($_SESSION['user_id'])) {
    ActivityLogger::log('auth.logout', 'Déconnexion');
}
Auth::logout();
header('Location: /login.php?logout=1');
exit;
