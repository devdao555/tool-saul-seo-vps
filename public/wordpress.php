<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Controllers\WordPressController;
use App\Support\Csrf;
use App\Support\Flash;
use App\Vps\VpsRepository;

Auth::requireLogin('/login.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $results = WordPressController::createBlankSites(
                    (int) ($_POST['vps_id'] ?? 0),
                    (string) ($_POST['domains'] ?? ''),
                    (string) ($_POST['admin_user'] ?? 'admin'),
                    (string) ($_POST['admin_password'] ?? ''),
                    (string) ($_POST['admin_email'] ?? '')
                );
                Flash::set('create_results', $results);
                break;

            case 'clone':
                $results = WordPressController::cloneSites(
                    (int) ($_POST['target_vps_id'] ?? 0),
                    (string) ($_POST['mapping'] ?? ''),
                    !empty($_POST['close_indexing'])
                );
                Flash::set('clone_results', $results);
                break;
        }
    } catch (\Throwable $e) {
        Flash::set('error', $e->getMessage());
    }

    header('Location: /wordpress.php');
    exit;
}

$vpsList = VpsRepository::all();
$createResults = Flash::pull('create_results');
$cloneResults = Flash::pull('clone_results');
$error = Flash::pull('error');

$pageTitle = 'Cấu hình website';
$pageSub = 'Add WP trắng hoặc clone WordPress';
$activeNav = 'wordpress';

ob_start();
require __DIR__ . '/../views/wordpress.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
