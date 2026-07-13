<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Controllers\SecurityController;
use App\Security\SecurityScanRepository;
use App\Support\Csrf;
use App\Support\Flash;
use App\Vps\VpsRepository;

Auth::requireLogin('/login.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'scan_domains':
                $results = SecurityController::scanDomains((string) ($_POST['domains'] ?? ''));
                Flash::set('scan_results', $results);
                break;

            case 'scan_vps':
                $results = SecurityController::scanVps((int) ($_POST['vps_id'] ?? 0));
                Flash::set('scan_results', $results);
                break;
        }
    } catch (\Throwable $e) {
        Flash::set('error', $e->getMessage());
    }

    header('Location: /security.php');
    exit;
}

$vpsList = VpsRepository::all();
$scanResults = Flash::pull('scan_results');
$error = Flash::pull('error');

$history = array_map(function ($row) {
    $detail = json_decode((string) $row['detail'], true) ?: [];
    return array_merge($detail, [
        'domain' => $row['domain'],
        'status' => $row['status'],
        'summary' => $row['summary'],
        'vps_label' => $row['vps_label'],
        'scanned_at' => $row['scanned_at'],
    ]);
}, SecurityScanRepository::all());

$pageTitle = 'Bảo mật';
$pageSub = 'Quét mã độc/webshell trên các site WordPress (WP-CLI checksum + heuristic)';
$activeNav = 'security';

ob_start();
require __DIR__ . '/../views/security.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
