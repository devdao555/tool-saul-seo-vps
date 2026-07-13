<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Support\Csrf;

if (Auth::check()) {
    header('Location: /index.php');
    exit;
}

$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (Auth::attempt($username, $password)) {
        header('Location: /index.php');
        exit;
    }
    $error = 'Sai tài khoản hoặc mật khẩu.';
}

require __DIR__ . '/../views/login.php';
