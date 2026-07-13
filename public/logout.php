<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Support\Csrf;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
}

Auth::logout();
header('Location: /login.php');
exit;
