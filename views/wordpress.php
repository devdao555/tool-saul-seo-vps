<?php

use App\Support\Csrf;

/** @var array $vpsList */
/** @var array|null $createResults */
/** @var array|null $cloneResults */
/** @var string|null $error */
?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($vpsList)): ?>
  <div class="alert alert-warn">Chưa có VPS nào. Vào <a href="/vps.php">VPS</a> để thêm trước.</div>
<?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Add WordPress trắng</h2>
    <p class="hint">Chọn VPS một lần, sau đó nhập mỗi dòng một domain. Tool sẽ tự tạo vhost Nginx + database + cài WordPress trắng.</p>
    <form method="post" action="/wordpress.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="create">
      <label>Chọn VPS</label>
      <select name="vps_id" required>
        <?php foreach ($vpsList as $v): ?>
          <option value="<?= (int) $v['id'] ?>"><?= htmlspecialchars($v['label']) ?> — <?= htmlspecialchars($v['ip']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Danh sách domain</label>
      <textarea name="domains" placeholder="abc.com&#10;xyz.com" required></textarea>
      <label>Admin username</label>
      <input type="text" name="admin_user" value="admin">
      <label>Admin password</label>
      <input type="text" name="admin_password" id="admin_password" placeholder="Mật khẩu admin WP" required>
      <label>Admin email</label>
      <input type="text" name="admin_email" placeholder="you@example.com" required>
      <button type="submit" class="btn btn-primary" <?= empty($vpsList) ? 'disabled' : '' ?>>Tạo WP</button>
    </form>
    <?php $results = $createResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>

  <div class="card">
    <h2>Clone WordPress</h2>
    <p class="hint">Không cần nhập IP ở từng dòng nữa. Chỉ cần chọn VPS đích ở ô bên trên.</p>
    <form method="post" action="/wordpress.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="clone">
      <label>Chọn VPS đích</label>
      <select name="target_vps_id" required>
        <?php foreach ($vpsList as $v): ?>
          <option value="<?= (int) $v['id'] ?>"><?= htmlspecialchars($v['label']) ?> — <?= htmlspecialchars($v['ip']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Mỗi dòng: source.com target.com</label>
      <textarea name="mapping" placeholder="old.com new.com&#10;old.com new2.com" required></textarea>
      <div class="checkbox-row">
        <input type="checkbox" name="close_indexing" id="close_indexing" value="1">
        <label for="close_indexing" style="margin:0;">Đóng bot index sau khi clone</label>
      </div>
      <button type="submit" class="btn btn-primary" <?= empty($vpsList) ? 'disabled' : '' ?>>Clone WP</button>
    </form>
    <?php $results = $cloneResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var pwField = document.getElementById('admin_password');
  if (!pwField) return;
  var btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'btn btn-ghost';
  btn.style.marginTop = '8px';
  btn.textContent = 'Sinh mật khẩu ngẫu nhiên';
  btn.addEventListener('click', function () {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    var out = '';
    var randomValues = new Uint32Array(16);
    window.crypto.getRandomValues(randomValues);
    for (var i = 0; i < 16; i++) {
      out += chars[randomValues[i] % chars.length];
    }
    pwField.value = out;
  });
  pwField.insertAdjacentElement('afterend', btn);
});
</script>
