<?php

use App\Support\Csrf;

/** @var array $vpsList */
/** @var array|null $scanResults */
/** @var array $history */
/** @var string|null $error */
?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="alert alert-warn">
  Đây là quét <strong>best-effort</strong>: WP-CLI checksum so khớp core với bản gốc WordPress.org, kết hợp dò pattern webshell/backdoor phổ biến (heuristic). Không thay thế được antivirus chuyên dụng — nghi ngờ không đồng nghĩa với chắc chắn nhiễm, hãy luôn xem chi tiết trước khi xoá file.
</div>

<?php if (empty($vpsList)): ?>
  <div class="alert alert-warn">Chưa có VPS nào. Vào <a href="/vps.php">VPS</a> để thêm trước.</div>
<?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Scan theo domain</h2>
    <p class="hint">Domain phải đã được gắn VPS trong hệ thống (qua Cấu hình website hoặc gán thủ công).</p>
    <form method="post" action="/security.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="scan_domains">
      <label>Danh sách domain</label>
      <textarea name="domains" placeholder="domain.com&#10;domain2.com" required></textarea>
      <button type="submit" class="btn btn-primary">Scan domain</button>
    </form>
  </div>

  <div class="card">
    <h2>Scan toàn bộ 1 VPS</h2>
    <p class="hint">Quét tất cả thư mục site tìm thấy trong webroot của VPS — kể cả domain chưa có trong danh sách quản lý.</p>
    <form method="post" action="/security.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="scan_vps">
      <label>Chọn VPS</label>
      <select name="vps_id" required>
        <?php foreach ($vpsList as $v): ?>
          <option value="<?= (int) $v['id'] ?>"><?= htmlspecialchars($v['label']) ?> — <?= htmlspecialchars($v['ip']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary" <?= empty($vpsList) ? 'disabled' : '' ?>>Scan toàn bộ VPS</button>
    </form>
  </div>
</div>

<?php if ($scanResults !== null): ?>
  <div class="card">
    <h2>Kết quả scan vừa chạy</h2>
    <?php $results = $scanResults; require __DIR__ . '/partials/security-result.php'; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Lịch sử scan</h2>
  <p class="hint">Kết quả lần quét gần nhất cho mỗi domain.</p>
  <?php if (empty($history)): ?>
    <p style="color:var(--text-dim);">Chưa scan lần nào.</p>
  <?php else: ?>
    <?php $results = $history; require __DIR__ . '/partials/security-result.php'; ?>
  <?php endif; ?>
</div>
