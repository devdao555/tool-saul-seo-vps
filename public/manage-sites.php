<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Controllers\WordPressController;
use App\Support\Csrf;
use App\Support\Flash;

Auth::requireLogin('/login.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'delete':
                $results = WordPressController::deleteSites((string) ($_POST['delete_domains'] ?? ''));
                Flash::set('delete_results', $results);
                break;

            case 'change_password':
                WordPressController::changePassword(
                    (string) ($_POST['pw_domain'] ?? ''),
                    (string) ($_POST['pw_username'] ?? 'admin'),
                    (string) ($_POST['pw_new_password'] ?? '')
                );
                Flash::set('ok', 'Đã đổi mật khẩu.');
                break;

            case 'clear_cache':
                $results = WordPressController::clearCache((string) ($_POST['cache_domains'] ?? ''));
                Flash::set('cache_results', $results);
                break;
        }
    } catch (\Throwable $e) {
        Flash::set('error', $e->getMessage());
    }

    header('Location: /manage-sites.php');
    exit;
}

$deleteResults = Flash::pull('delete_results');
$cacheResults = Flash::pull('cache_results');
$ok = Flash::pull('ok');
$error = Flash::pull('error');

$pageTitle = 'Quản lý website';
$pageSub = 'Xoá website, đổi mật khẩu admin WP hoặc clear cache WordPress';
$activeNav = 'manage-sites';

ob_start();
require __DIR__ . '/../views/manage-sites.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
