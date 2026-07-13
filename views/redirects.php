<?php

use App\Support\Csrf;

/** @var array|null $createResults */
/** @var array|null $deleteResults */
/** @var string|null $error */
?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Thêm Page Rules 301</h2>
    <form method="post" action="/redirects.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="create">
      <label>Domain đích</label>
      <input type="text" name="target_domain" placeholder="domain-dich.com" required>
      <label>Domain nguồn</label>
      <textarea name="source_domains" placeholder="src1.com&#10;src2.com" required></textarea>
      <button type="submit" class="btn btn-primary">Tạo 301</button>
    </form>
    <?php $results = $createResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>

  <div class="card">
    <h2>Xoá Page Rules</h2>
    <p class="hint">Xoá toàn bộ Page Rules của các domain bên dưới.</p>
    <form method="post" action="/redirects.php" onsubmit="return confirm('Xoá toàn bộ Page Rules của các domain này?');">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="delete">
      <label>Danh sách domain</label>
      <textarea name="delete_domains" placeholder="domain.com" required></textarea>
      <button type="submit" class="btn btn-danger">Xoá rules</button>
    </form>
    <?php $results = $deleteResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>
</div>
