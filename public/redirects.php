<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Controllers\RedirectController;
use App\Support\Csrf;
use App\Support\Flash;

Auth::requireLogin('/login.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $results = RedirectController::createRedirects(
                    (string) ($_POST['target_domain'] ?? ''),
                    (string) ($_POST['source_domains'] ?? '')
                );
                Flash::set('create_results', $results);
                break;

            case 'delete':
                $results = RedirectController::deleteRedirects((string) ($_POST['delete_domains'] ?? ''));
                Flash::set('delete_results', $results);
                break;
        }
    } catch (\Throwable $e) {
        Flash::set('error', $e->getMessage());
    }

    header('Location: /redirects.php');
    exit;
}

$createResults = Flash::pull('create_results');
$deleteResults = Flash::pull('delete_results');
$error = Flash::pull('error');

$pageTitle = 'Chuyển hướng 301';
$pageSub = 'Xoá rule cũ và tạo Page Rules mới';
$activeNav = 'redirects';

ob_start();
require __DIR__ . '/../views/redirects.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
