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

$pageTitle = 'VPS';
$pageSub = 'Quản lý danh sách VPS dùng để dựng WordPress';
$activeNav = 'vps';

ob_start();
require __DIR__ . '/../views/vps.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
