<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Controllers\SslController;
use App\Ssl\SslCheckRepository;
use App\Support\Csrf;
use App\Support\Flash;

Auth::requireLogin('/login.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'check_ssl':
                $results = SslController::checkDomains((string) ($_POST['domains'] ?? ''));
                Flash::set('ssl_results', $results);
                break;

            case 'check_registrar':
                $results = SslController::checkNamecheapExpiry((string) ($_POST['domains_registrar'] ?? ''));
                Flash::set('registrar_results', $results);
                break;

            case 'renew_ssl':
                $results = SslController::renewSsl((string) ($_POST['domains_renew'] ?? ''));
                Flash::set('renew_results', $results);
                break;
        }
    } catch (\Throwable $e) {
        Flash::set('error', $e->getMessage());
    }

    header('Location: /ssl.php');
    exit;
}

$sslResults = Flash::pull('ssl_results');
$registrarResults = Flash::pull('registrar_results');
$renewResults = Flash::pull('renew_results');
$error = Flash::pull('error');
$history = SslCheckRepository::all();

$pageTitle = 'SSL & Domain';
$pageSub = 'Check hạn SSL, tự động renew, check hạn domain (Namecheap)';
$activeNav = 'ssl';

ob_start();
require __DIR__ . '/../views/ssl.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
