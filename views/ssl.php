<?php

use App\Support\Csrf;

/** @var array|null $sslResults */
/** @var array|null $registrarResults */
/** @var array|null $renewResults */
/** @var array $history */
/** @var string|null $error */
?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Check hạn SSL</h2>
    <p class="hint">Kiểm tra trực tiếp qua cổng 443 — không cần VPS/SSH, áp dụng cho mọi domain public.</p>
    <form method="post" action="/ssl.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="check_ssl">
      <label>Danh sách domain</label>
      <textarea name="domains" placeholder="domain.com&#10;domain2.com" required></textarea>
      <button type="submit" class="btn btn-primary">Check SSL</button>
    </form>
    <?php $results = $sslResults; require __DIR__ . '/partials/ssl-result.php'; ?>
  </div>

  <div class="card">
    <h2>Renew SSL qua VPS</h2>
    <p class="hint">Thử certbot rồi tới acme.sh trên VPS đã gắn domain. Best-effort — nếu VPS dùng cách khác (vd chỉ qua giao diện aaPanel) sẽ báo không tìm thấy.</p>
    <form method="post" action="/ssl.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="renew_ssl">
      <label>Danh sách domain</label>
      <textarea name="domains_renew" placeholder="domain.com&#10;domain2.com" required></textarea>
      <button type="submit" class="btn btn-primary">Renew SSL</button>
    </form>
    <?php $results = $renewResults; require __DIR__ . '/partials/ssl-result.php'; ?>
  </div>
</div>

<div class="card">
  <h2>Check hạn domain (Namecheap)</h2>
  <p class="hint">Chỉ hoạt động với domain đăng ký tại Namecheap và đã cấu hình API ở <a href="/settings.php">Cài đặt</a>.</p>
  <form method="post" action="/ssl.php">
    <?= Csrf::field() ?>
    <input type="hidden" name="action" value="check_registrar">
    <label>Danh sách domain</label>
    <textarea name="domains_registrar" placeholder="domain.com&#10;domain2.com" required></textarea>
    <button type="submit" class="btn btn-primary">Check hạn domain</button>
  </form>
  <?php $results = $registrarResults; require __DIR__ . '/partials/ssl-result.php'; ?>
</div>

<div class="card">
  <h2>Lịch sử check SSL</h2>
  <p class="hint">Sắp xếp theo số ngày còn lại — domain sắp hết hạn hiện lên trước.</p>
  <?php if (empty($history)): ?>
    <p style="color:var(--text-dim);">Chưa check lần nào.</p>
  <?php else: ?>
    <?php $results = $history; require __DIR__ . '/partials/ssl-result.php'; ?>
  <?php endif; ?>
</div>
