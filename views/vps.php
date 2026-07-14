<?php

use App\Support\Csrf;

/** @var array $vpsList */
/** @var array $healthByVps */
/** @var array|null $bulkVpsResults */
/** @var array|null $bootstrapResult */
/** @var string|null $ok */
/** @var string|null $error */

$pctBadge = static function (?int $pct): string {
    if ($pct === null) {
        return '<span class="badge badge-muted">-</span>';
    }
    $class = $pct >= 90 ? 'badge-danger' : ($pct >= 75 ? 'badge-warn' : 'badge-success');
    return "<span class=\"badge {$class}\">{$pct}%</span>";
};

$svcBadge = static function (?string $state): string {
    if ($state === null) {
        return '<span class="badge badge-muted">-</span>';
    }
    return $state === 'active'
        ? '<span class="badge badge-success">on</span>'
        : '<span class="badge badge-danger">off</span>';
};
?>

<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Bootstrap SSH key hàng loạt</h2>
    <p class="hint">Dùng khi VPS chỉ có SSH <strong>password</strong> (chưa có key). Tool sẽ tạo 1 SSH key mới, đăng nhập bằng password hiện có <strong>đúng 1 lần</strong> để cài key đó vào từng VPS — sau đó dùng key này với "Thêm hàng loạt VPS" bên dưới, không cần password nữa. Cần cài <code>sshpass</code> trên VPS đang chạy tool (<code>apt install -y sshpass</code>).</p>
    <form method="post" action="/vps.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="bootstrap_ssh">
      <label>SSH User (dùng chung)</label>
      <input type="text" name="bootstrap_ssh_user" value="root">
      <label>SSH Port (dùng chung)</label>
      <input type="number" name="bootstrap_ssh_port" value="22">
      <label>Danh sách VPS — mỗi dòng: ip|ssh_password</label>
      <textarea name="bootstrap_lines" placeholder="103.2.226.244|MatKhauSshHienTai1&#10;103.2.226.245|MatKhauSshHienTai2" required></textarea>
      <button type="submit" class="btn btn-primary">Cài SSH key hàng loạt</button>
    </form>
    <?php if (!empty($bootstrapResult)): ?>
      <div class="alert alert-warn" style="margin-top:16px;">
        SSH Private Key vừa tạo — <strong>copy ngay</strong>, chỉ hiện 1 lần, dùng cho form "Thêm hàng loạt VPS" bên dưới:
      </div>
      <textarea readonly onclick="this.select()" style="font-size:12px;"><?= htmlspecialchars($bootstrapResult['private_key']) ?></textarea>
      <?php $results = $bootstrapResult['results']; require __DIR__ . '/partials/result-table.php'; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Thêm VPS</h2>
    <p class="hint">VPS cần đã cài sẵn aaPanel + Nginx + PHP-FPM + MySQL + WP-CLI. SSH dùng key (không mật khẩu) — thêm public key tương ứng vào <code>authorized_keys</code> của VPS trước.</p>
    <form method="post" action="/vps.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add">
      <label>Label</label>
      <input type="text" name="label" placeholder="VPS SEO 01" required>
      <label>IP</label>
      <input type="text" name="ip" placeholder="103.2.226.244" required>
      <label>SSH User</label>
      <input type="text" name="ssh_user" value="root">
      <label>SSH Port</label>
      <input type="number" name="ssh_port" value="22">
      <label>PHP Version (aaPanel, vd 81 cho PHP 8.1)</label>
      <input type="text" name="php_version" value="81">
      <label>Webroot base</label>
      <input type="text" name="webroot_base" value="/www/wwwroot">
      <label>MySQL user (thường là root)</label>
      <input type="text" name="mysql_user" value="root">
      <label>MySQL password</label>
      <input type="text" name="mysql_password" placeholder="Mật khẩu MySQL root trên VPS">
      <label>SSH Private Key (nội dung file .pem/id_rsa)</label>
      <textarea name="private_key" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----" required></textarea>
      <button type="submit" class="btn btn-primary">+ Thêm VPS</button>
    </form>
  </div>

  <div class="card">
    <h2>Thêm hàng loạt VPS</h2>
    <p class="hint">Dùng khi nhiều VPS dùng chung 1 SSH key (khuyến nghị: tạo 1 key, add public key vào <code>authorized_keys</code> của tất cả VPS). Cấu hình chung điền 1 lần bên dưới, riêng Label/IP/MySQL password nhập theo danh sách.</p>
    <form method="post" action="/vps.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="bulk_add">
      <label>SSH User (dùng chung)</label>
      <input type="text" name="bulk_ssh_user" value="root">
      <label>SSH Port (dùng chung)</label>
      <input type="number" name="bulk_ssh_port" value="22">
      <label>PHP Version (dùng chung, vd 81 cho PHP 8.1)</label>
      <input type="text" name="bulk_php_version" value="81">
      <label>Webroot base (dùng chung)</label>
      <input type="text" name="bulk_webroot_base" value="/www/wwwroot">
      <label>MySQL user (dùng chung, thường là root)</label>
      <input type="text" name="bulk_mysql_user" value="root">
      <label>SSH Private Key (dùng chung cho tất cả VPS bên dưới)</label>
      <textarea name="bulk_private_key" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----&#10;...&#10;-----END OPENSSH PRIVATE KEY-----" required></textarea>
      <label>Danh sách VPS — mỗi dòng: label|ip|mysql_password</label>
      <textarea name="bulk_vps_lines" placeholder="VPS 01|103.2.226.244|mysqlpass1&#10;VPS 02|103.2.226.245|mysqlpass2" required></textarea>
      <button type="submit" class="btn btn-primary">Thêm hàng loạt</button>
    </form>
    <?php $results = $bulkVpsResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>

  <div class="card">
    <h2>Danh sách VPS</h2>
    <form method="post" action="/vps.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="check_all_health">
      <button type="submit" class="btn btn-ghost" style="margin-top:0;" <?= empty($vpsList) ? 'disabled' : '' ?>>Kiểm tra tất cả (mất ~1-2s/VPS)</button>
    </form>
    <table style="margin-top:16px;">
      <thead><tr><th>Label</th><th>IP</th><th>CPU</th><th>RAM</th><th>Disk</th><th>nginx</th><th>mysql</th><th>redis</th><th>php-fpm</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($vpsList)): ?>
          <tr><td colspan="10" style="color:var(--text-dim);">Chưa có VPS nào.</td></tr>
        <?php else: ?>
          <?php foreach ($vpsList as $v): ?>
            <?php $h = $healthByVps[(int) $v['id']] ?? null; ?>
            <tr>
              <td><?= htmlspecialchars($v['label']) ?></td>
              <td><?= htmlspecialchars($v['ip']) ?></td>
              <?php if (!$h): ?>
                <td colspan="7" style="color:var(--text-dim);">Chưa kiểm tra.</td>
              <?php elseif (empty($h['reachable'])): ?>
                <td colspan="7"><span class="badge badge-danger">Không kết nối được</span></td>
              <?php else: ?>
                <td><?= $pctBadge((int) $h['cpu_percent']) ?></td>
                <td><?= $pctBadge((int) $h['ram_percent']) ?></td>
                <td><?= $pctBadge((int) $h['disk_percent']) ?></td>
                <?php $svc = $h['services'] ?? []; ?>
                <td><?= $svcBadge($svc['nginx'] ?? null) ?></td>
                <td><?= $svcBadge($svc['mysql'] ?? null) ?></td>
                <td><?= $svcBadge($svc['redis'] ?? null) ?></td>
                <td><?= $svcBadge($svc['php-fpm'] ?? null) ?></td>
              <?php endif; ?>
              <td style="white-space:nowrap;">
                <form method="post" action="/vps.php" style="display:inline;">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="check_health">
                  <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                  <button type="submit" class="btn btn-ghost" style="margin-top:0;padding:6px 10px;font-size:12px;">Check</button>
                </form>
                <form method="post" action="/vps.php" onsubmit="return confirm('Xoá VPS này khỏi danh sách? (Không xoá gì trên VPS thật)');" style="display:inline;">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                  <button type="submit" class="btn btn-danger" style="margin-top:0;padding:6px 10px;font-size:12px;">Xoá</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <?php if (!empty($vpsList)): ?>
      <p class="small-note">
        Dịch vụ "off" nghĩa là không active theo systemd (có thể do dùng init khác, hoặc thật sự đang dừng) — kiểm tra thủ công trước khi kết luận.
        Restart nhanh:
        <?php foreach ($vpsList as $v): ?>
          <?php foreach (['nginx', 'mysql', 'redis', 'php-fpm'] as $svcKey): ?>
            <form method="post" action="/vps.php" style="display:inline;" onsubmit="return confirm('Restart <?= $svcKey ?> trên <?= htmlspecialchars($v['label']) ?>?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="action" value="restart_service">
              <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
              <input type="hidden" name="service" value="<?= $svcKey ?>">
              <button type="submit" class="btn btn-ghost" style="margin-top:6px;margin-right:6px;padding:4px 8px;font-size:11px;"><?= htmlspecialchars($v['label']) ?> · <?= $svcKey ?></button>
            </form>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
  </div>
</div>
