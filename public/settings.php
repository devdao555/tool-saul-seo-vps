<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Cloudflare\CfAccountRepository;
use App\Controllers\SettingsController;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\SettingsRepository;

Auth::requireLogin('/login.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_cf_account':
                SettingsController::addCfAccount(
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['api_token'] ?? ''),
                    (string) ($_POST['account_id'] ?? '')
                );
                Flash::set('ok', 'Đã thêm Cloudflare account.');
                break;

            case 'delete_cf_account':
                SettingsController::deleteCfAccount((int) ($_POST['id'] ?? 0));
                Flash::set('ok', 'Đã xoá Cloudflare account.');
                break;

            case 'save_namecheap':
                SettingsController::saveNamecheap(
                    (string) ($_POST['nc_api_user'] ?? ''),
                    (string) ($_POST['nc_api_key'] ?? ''),
                    (string) ($_POST['nc_client_ip'] ?? '')
                );
                Flash::set('ok', 'Đã lưu cấu hình Namecheap.');
                break;
        }
    } catch (\Throwable $e) {
        Flash::set('error', $e->getMessage());
    }

    header('Location: /settings.php');
    exit;
}

$cfAccounts = CfAccountRepository::all();
$namecheap = [
    'api_user' => SettingsRepository::get('namecheap_api_user') ?? '',
    'client_ip' => SettingsRepository::get('namecheap_client_ip') ?? '',
    'has_key' => SettingsRepository::get('namecheap_api_key') !== null,
];
$ok = Flash::pull('ok');
$error = Flash::pull('error');

$pageTitle = 'Cài đặt';
$pageSub = 'Quản lý Cloudflare account, Namecheap API và VPS';
$activeNav = 'settings';

ob_start();
require __DIR__ . '/../views/settings.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
