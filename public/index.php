<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Controllers\DashboardController;

Auth::requireLogin('/login.php');

$stats = DashboardController::stats();
$logs = DashboardController::recentLogs(20);

$pageTitle = 'Bảng tin';
$pageSub = 'Tổng quan hệ thống quản lý domain & website';
$activeNav = 'dashboard';

ob_start();
require __DIR__ . '/../views/dashboard.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
