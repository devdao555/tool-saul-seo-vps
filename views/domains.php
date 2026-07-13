<?php

use App\Support\Csrf;

/** @var array $cfAccounts */
/** @var array $domains */
/** @var array|null $addZoneResults */
/** @var array|null $checkNsResults */
/** @var array|null $pushDnsResults */
/** @var array|null $deleteDnsResults */
/** @var array|null $pushNsResults */
/** @var string|null $error */
?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($cfAccounts)): ?>
  <div class="alert alert-warn">
    Chưa có Cloudflare account nào. Vào <a href="/settings.php">Cài đặt</a> để thêm account trước khi thêm domain.
  </div>
<?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Thêm domain &amp; trả NS</h2>
    <p class="hint">Nhập mỗi dòng 1 domain (vd: 310.cn.com, thenorthxyz.us.com).</p>
    <form method="post" action="/domains.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="add_zone">
      <label>Chọn Cloudflare Account</label>
      <select name="cf_account_id" required>
        <?php foreach ($cfAccounts as $acc): ?>
          <option value="<?= (int) $acc['id'] ?>"><?= htmlspecialchars($acc['label']) ?> — <?= htmlspecialchars($acc['account_id']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Danh sách domain</label>
      <textarea name="domains" placeholder="310.cn.com&#10;thenorthxyz.us.com" required></textarea>
      <div class="checkbox-row">
        <input type="checkbox" name="jump_start" id="jump_start" value="1">
        <label for="jump_start" style="margin:0;">Bật Jump start khi tạo zone</label>
      </div>
      <button type="submit" class="btn btn-primary" <?= empty($cfAccounts) ? 'disabled' : '' ?>>+ Thêm domain</button>
    </form>
    <?php $results = $addZoneResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>

  <div class="card">
    <h2>Check NS · Trạng thái domain</h2>
    <p class="hint">Danh sách domain (mỗi dòng 1 domain) — chỉ áp dụng cho domain đã thêm qua tool này.</p>
    <form method="post" action="/domains.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="check_ns">
      <label>Danh sách domain</label>
      <textarea name="domains_check" placeholder="310.cn.com&#10;thenorthxyz.us.com" required></textarea>
      <button type="submit" class="btn btn-ghost">Check NS</button>
    </form>
    <?php $results = $checkNsResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>
</div>

<div class="grid">
  <div class="card">
    <h2>Push NS sang Namecheap</h2>
    <p class="hint">Dùng NS Cloudflare đã lưu để tự trỏ NS bên Namecheap (cần cấu hình API ở <a href="/settings.php">Cài đặt</a>, và whitelist IP server này trong Namecheap).</p>
    <form method="post" action="/domains.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="push_ns">
      <label>Danh sách domain</label>
      <textarea name="domains_push_ns" placeholder="310.cn.com&#10;thenorthxyz.us.com" required></textarea>
      <button type="submit" class="btn btn-primary">Push NS sang Namecheap</button>
    </form>
    <?php $results = $pushNsResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>

  <div class="card">
    <h2>Push DNS</h2>
    <p class="hint">Mỗi dòng: <code>domain.com IP</code> — tạo/cập nhật A record + CNAME www.</p>
    <form method="post" action="/domains.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="push_dns">
      <label>Danh sách domain + IP</label>
      <textarea name="dns_lines" placeholder="abc.com 160.250.187.62" required></textarea>
      <div class="checkbox-row">
        <input type="checkbox" name="proxied" id="proxied" value="1" checked>
        <label for="proxied" style="margin:0;">Bật Cloudflare Proxy (mây cam)</label>
      </div>
      <button type="submit" class="btn btn-primary">Chạy Cloudflare DNS</button>
    </form>
    <?php $results = $pushDnsResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>

  <div class="card">
    <h2>Xoá DNS</h2>
    <p class="hint">Xoá toàn bộ DNS record của các domain bên dưới.</p>
    <form method="post" action="/domains.php" onsubmit="return confirm('Xoá toàn bộ DNS record của các domain này?');">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="delete_dns">
      <label>Danh sách domain</label>
      <textarea name="domains_delete_dns" placeholder="abc.com&#10;xyz.com" required></textarea>
      <button type="submit" class="btn btn-danger">Xoá toàn bộ DNS record</button>
    </form>
    <?php $results = $deleteDnsResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>
</div>

<div class="card">
  <h2>Danh sách domain đã quản lý</h2>
  <table>
    <thead>
      <tr><th>Domain</th><th>CF Account</th><th>Trạng thái</th><th>NS1</th><th>NS2</th><th>VPS</th></tr>
    </thead>
    <tbody>
      <?php if (empty($domains)): ?>
        <tr><td colspan="6" style="color:var(--text-dim);">Chưa có domain nào.</td></tr>
      <?php else: ?>
        <?php foreach ($domains as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d['domain']) ?></td>
            <td><?= htmlspecialchars($d['cf_account_label'] ?? '-') ?></td>
            <td>
              <?php $status = $d['status'] ?? 'unknown'; ?>
              <span class="badge <?= $status === 'active' ? 'badge-success' : ($status === 'pending' ? 'badge-warn' : 'badge-muted') ?>"><?= htmlspecialchars($status) ?></span>
            </td>
            <td><?= htmlspecialchars($d['ns1'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['ns2'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['vps_label'] ?? '-') ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
