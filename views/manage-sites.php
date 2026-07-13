<?php

use App\Support\Csrf;

/** @var array|null $deleteResults */
/** @var array|null $cacheResults */
/** @var string|null $ok */
/** @var string|null $error */
?>

<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Xoá WordPress</h2>
    <p class="hint">Tool sẽ xoá site trên VPS (file + database + vhost) và DNS Cloudflare nếu tìm thấy. Không thể hoàn tác.</p>
    <form method="post" action="/manage-sites.php" onsubmit="return confirm('Xoá vĩnh viễn các website này? Hành động không thể hoàn tác!');">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="delete">
      <label>Danh sách domain</label>
      <textarea name="delete_domains" placeholder="domain.com&#10;domain2.com" required></textarea>
      <button type="submit" class="btn btn-danger">Xoá website</button>
    </form>
    <?php $results = $deleteResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>

  <div class="card">
    <h2>Đổi mật khẩu WordPress</h2>
    <form method="post" action="/manage-sites.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="change_password">
      <label>Domain</label>
      <input type="text" name="pw_domain" placeholder="domain.com" required>
      <label>Username</label>
      <input type="text" name="pw_username" value="admin" required>
      <label>Mật khẩu mới</label>
      <input type="text" name="pw_new_password" placeholder="Mật khẩu mới" required>
      <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
    </form>
  </div>
</div>

<div class="card">
  <h2>Clear Cache WordPress</h2>
  <p class="hint">Tự phát hiện WP Rocket / W3TC / WP Super Cache / LiteSpeed Cache và flush kèm wp cache flush.</p>
  <form method="post" action="/manage-sites.php">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="clear_cache">
    <label>Danh sách domain cần clear cache</label>
    <textarea name="cache_domains" placeholder="mv88z.net" required></textarea>
    <button type="submit" class="btn btn-primary">Clear Cache</button>
  </form>
  <?php $results = $cacheResults; require __DIR__ . '/partials/result-table.php'; ?>
</div>
