<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Controllers\VpsController;
use App\Support\Csrf;
use App\Support\Flash;
use App\Vps\VpsHealthRepository;
use App\Vps\VpsRepository;

Auth::requireLogin('/login.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add':
                VpsController::addVps($_POST);
                Flash::set('ok', 'Đã thêm VPS.');
                break;

            case 'delete':
                VpsController::deleteVps((int) ($_POST['id'] ?? 0));
                Flash::set('ok', 'Đã xoá VPS khỏi hệ thống (không xoá gì trên VPS thật).');
                break;

            case 'bootstrap_ssh':
                $bootstrapResult = VpsController::bulkBootstrapSsh(
                    (string) ($_POST['bootstrap_ssh_user'] ?? 'root'),
                    (int) ($_POST['bootstrap_ssh_port'] ?? 22),
                    (string) ($_POST['bootstrap_lines'] ?? '')
                );
                Flash::set('bootstrap_result', $bootstrapResult);
                break;

            case 'bulk_add':
                $results = VpsController::bulkAddVps([
                    'ssh_user' => (string) ($_POST['bulk_ssh_user'] ?? 'root'),
                    'ssh_port' => (int) ($_POST['bulk_ssh_port'] ?? 22),
                    'php_version' => (string) ($_POST['bulk_php_version'] ?? '81'),
                    'webroot_base' => (string) ($_POST['bulk_webroot_base'] ?? '/www/wwwroot'),
                    'mysql_user' => (string) ($_POST['bulk_mysql_user'] ?? 'root'),
                    'private_key' => (string) ($_POST['bulk_private_key'] ?? ''),
                ], (string) ($_POST['bulk_vps_lines'] ?? ''));
                Flash::set('bulk_vps_results', $results);
                break;

            case 'check_health':
                VpsController::checkHealth((int) ($_POST['id'] ?? 0));
                Flash::set('ok', 'Đã kiểm tra tình trạng VPS.');
                break;

            case 'check_all_health':
                VpsController::checkAllHealth();
                Flash::set('ok', 'Đã kiểm tra tình trạng toàn bộ VPS.');
                break;

            case 'restart_service':
                VpsController::restartService((int) ($_POST['id'] ?? 0), (string) ($_POST['service'] ?? ''));
                Flash::set('ok', 'Đã gửi lệnh restart dịch vụ.');
                break;
        }
    } catch (\Throwable $e) {
        Flash::set('error', $e->getMessage());
    }

    header('Location: /vps.php');
    exit;
}

$vpsList = VpsRepository::all();
$healthByVps = VpsHealthRepository::all();
$ok = Flash::pull('ok');
$error = Flash::pull('error');
$bulkVpsResults = Flash::pull('bulk_vps_results');
$bootstrapResult = Flash::pull('bootstrap_result');

$pageTitle = 'VPS';
$pageSub = 'Quản lý danh sách VPS dùng để dựng WordPress';
$activeNav = 'vps';

ob_start();
require __DIR__ . '/../views/vps.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
