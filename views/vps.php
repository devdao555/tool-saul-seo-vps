<?php

use App\Support\Csrf;

/** @var array $vpsList */
/** @var string|null $ok */
/** @var string|null $error */
?>

<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid">
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
    <h2>Danh sách VPS</h2>
    <table>
      <thead><tr><th>Label</th><th>IP</th><th>SSH</th><th>PHP</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($vpsList)): ?>
          <tr><td colspan="5" style="color:var(--text-dim);">Chưa có VPS nào.</td></tr>
        <?php else: ?>
          <?php foreach ($vpsList as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['label']) ?></td>
              <td><?= htmlspecialchars($v['ip']) ?></td>
              <td><?= htmlspecialchars($v['ssh_user']) ?>@<?= (int) $v['ssh_port'] ?></td>
              <td><?= htmlspecialchars($v['php_version']) ?></td>
              <td>
                <form method="post" action="/vps.php" onsubmit="return confirm('Xoá VPS này khỏi danh sách? (Không xoá gì trên VPS thật)');">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                  <button type="submit" class="btn btn-danger" style="margin-top:0;padding:6px 12px;font-size:12px;">Xoá</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
