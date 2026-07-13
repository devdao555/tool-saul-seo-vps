<?php

use App\Support\Csrf;

/** @var array $cfAccounts */
/** @var array $namecheap */
/** @var string|null $ok */
/** @var string|null $error */
?>

<?php if ($ok): ?><div class="alert alert-success"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Cloudflare Accounts</h2>
    <p class="hint">API Token cần quyền Zone:Edit, DNS:Edit, Page Rules:Edit. Account ID lấy ở sidebar phải trang Cloudflare dashboard.</p>
    <form method="post" action="/settings.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add_cf_account">
      <label>Label</label>
      <input type="text" name="label" placeholder="VD: josephemne@gmail.com" required>
      <label>API Token</label>
      <input type="text" name="api_token" placeholder="Cloudflare API Token" required>
      <label>Account ID</label>
      <input type="text" name="account_id" placeholder="314357c438e7db1defe63c85937f74f" required>
      <button type="submit" class="btn btn-primary">Thêm account</button>
    </form>

    <table style="margin-top:20px;">
      <thead><tr><th>Label</th><th>Account ID</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($cfAccounts)): ?>
          <tr><td colspan="3" style="color:var(--text-dim);">Chưa có account nào.</td></tr>
        <?php else: ?>
          <?php foreach ($cfAccounts as $acc): ?>
            <tr>
              <td><?= htmlspecialchars($acc['label']) ?></td>
              <td><?= htmlspecialchars($acc['account_id']) ?></td>
              <td>
                <form method="post" action="/settings.php" onsubmit="return confirm('Xoá account này?');">
                  <?= Csrf::field() ?>
                  <input type="hidden" name="action" value="delete_cf_account">
                  <input type="hidden" name="id" value="<?= (int) $acc['id'] ?>">
                  <button type="submit" class="btn btn-danger" style="margin-top:0;padding:6px 12px;font-size:12px;">Xoá</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>Namecheap API</h2>
    <p class="hint">Dùng để tự trỏ NS. Nhớ whitelist IP server này ở Namecheap &gt; Profile &gt; Tools &gt; API Access.</p>
    <form method="post" action="/settings.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="save_namecheap">
      <label>API User</label>
      <input type="text" name="nc_api_user" value="<?= htmlspecialchars($namecheap['api_user']) ?>" placeholder="ApiUser">
      <label>API Key <?php if ($namecheap['has_key']): ?><span class="badge badge-success">đã lưu</span><?php endif; ?></label>
      <input type="text" name="nc_api_key" placeholder="<?= $namecheap['has_key'] ? 'Để trống nếu không đổi' : 'Namecheap API Key' ?>">
      <label>Client IP</label>
      <input type="text" name="nc_client_ip" value="<?= htmlspecialchars($namecheap['client_ip']) ?>" placeholder="IP server đã whitelist ở Namecheap">
      <button type="submit" class="btn btn-primary">Lưu cấu hình Namecheap</button>
    </form>
  </div>
</div>
