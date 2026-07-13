<?php

use App\Auth\Auth;

/** @var string $pageTitle */
/** @var string $pageSub */
/** @var string $activeNav */
/** @var string $content */

$appName = App\Support\Env::get('APP_NAME', 'SAUL SEO Tool');
$navItems = [
    'dashboard' => ['label' => 'Bảng tin', 'href' => '/index.php'],
    'domains' => ['label' => 'Tên miền & DNS', 'href' => '/domains.php'],
    'redirects' => ['label' => 'Chuyển hướng 301', 'href' => '/redirects.php'],
    'wordpress' => ['label' => 'Cấu hình website', 'href' => '/wordpress.php'],
    'manage-sites' => ['label' => 'Quản lý website', 'href' => '/manage-sites.php'],
    'vps' => ['label' => 'VPS', 'href' => '/vps.php'],
    'settings' => ['label' => 'Cài đặt', 'href' => '/settings.php'],
];
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> · <?= htmlspecialchars($appName) ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">
      <div class="brand-badge">S</div>
      <div>
        <div class="brand-title"><?= htmlspecialchars($appName) ?></div>
        <div class="brand-sub">Control Panel</div>
      </div>
    </div>
    <nav class="nav">
      <?php foreach ($navItems as $key => $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $activeNav === $key ? 'active' : '' ?>"><?= htmlspecialchars($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <form method="post" action="/logout.php">
      <?= App\Support\Csrf::field() ?>
      <button type="submit" class="logout-btn">Đăng xuất</button>
    </form>
  </aside>
  <main class="main">
    <div class="topbar">
      <div>
        <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="page-sub"><?= htmlspecialchars($pageSub) ?></p>
      </div>
      <div class="user-chip">
        <div class="avatar"><?= htmlspecialchars(strtoupper(substr((string) Auth::user(), 0, 1))) ?></div>
        <div>
          <div>Đăng nhập</div>
          <strong><?= htmlspecialchars((string) Auth::user()) ?></strong>
        </div>
      </div>
    </div>
    <?= $content ?>
  </main>
</div>
</body>
</html>
