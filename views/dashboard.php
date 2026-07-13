<?php
/** @var array $stats */
/** @var array $logs */
?>
<div class="grid">
  <div class="card">
    <h2>Tổng quan</h2>
    <p class="hint">Số liệu domain, Cloudflare account và VPS đang quản lý.</p>
    <table>
      <tr><td>Tổng số domain</td><td><strong><?= (int) $stats['domains'] ?></strong></td></tr>
      <tr><td>Domain đang active (NS đã trỏ)</td><td><strong><?= (int) $stats['active_domains'] ?></strong></td></tr>
      <tr><td>Cloudflare account</td><td><strong><?= (int) $stats['cf_accounts'] ?></strong></td></tr>
      <tr><td>VPS đang quản lý</td><td><strong><?= (int) $stats['vps'] ?></strong></td></tr>
    </table>
  </div>
  <div class="card">
    <h2>Bắt đầu nhanh</h2>
    <p class="hint">Quy trình gợi ý cho 1 domain mới.</p>
    <table>
      <tr><td>1.</td><td>Thêm domain &amp; tạo zone ở <a href="/domains.php">Tên miền &amp; DNS</a></td></tr>
      <tr><td>2.</td><td>Push NS sang Namecheap (hoặc registrar khác thủ công)</td></tr>
      <tr><td>3.</td><td>Push DNS trỏ VPS, hoặc <a href="/wordpress.php">tạo WordPress trắng</a></td></tr>
      <tr><td>4.</td><td>Cấu hình <a href="/redirects.php">301 redirect</a> nếu cần gộp domain</td></tr>
    </table>
  </div>
</div>

<div class="card">
  <h2>Log hệ thống</h2>
  <p class="hint">20 hoạt động gần nhất.</p>
  <div class="log-box">
    <?php if (empty($logs)): ?>
      <div class="log-empty">Chưa có log...</div>
    <?php else: ?>
      <?php foreach ($logs as $log): ?>
        <div class="log-line">
          [<?= htmlspecialchars($log['created_at']) ?>] <strong><?= htmlspecialchars($log['module']) ?>/<?= htmlspecialchars($log['action']) ?></strong>
          <?= htmlspecialchars((string) $log['target']) ?> —
          <span class="badge <?= $log['status'] === 'success' ? 'badge-success' : ($log['status'] === 'error' ? 'badge-danger' : 'badge-muted') ?>"><?= htmlspecialchars($log['status']) ?></span>
          <?php if (!empty($log['message'])): ?> · <?= htmlspecialchars($log['message']) ?><?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
