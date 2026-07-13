<?php
/** @var string|null $error */
$appName = App\Support\Env::get('APP_NAME', 'SAUL SEO Tool');
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng nhập · <?= htmlspecialchars($appName) ?></title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h1><?= htmlspecialchars($appName) ?></h1>
    <p>Đăng nhập để quản lý domain, DNS và website.</p>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/login.php">
      <?= App\Support\Csrf::field() ?>
      <label>Tài khoản</label>
      <input type="text" name="username" autocomplete="username" required autofocus>
      <label>Mật khẩu</label>
      <input type="password" name="password" autocomplete="current-password" required>
      <button type="submit" class="btn btn-primary" style="width:100%">Đăng nhập</button>
    </form>
  </div>
</div>
</body>
</html>
