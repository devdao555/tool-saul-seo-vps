<?php
/**
 * Renders a generic per-line result list produced by the bulk-action controllers.
 * Expects $results as an array of assoc arrays with at least 'ok' plus a label field
 * ('domain' or 'line') and optional 'error' / free-form extra fields.
 */
/** @var array $results */
if (empty($results)) {
    return;
}
?>
<div class="log-box" style="margin-top:16px;">
  <?php foreach ($results as $row): ?>
    <?php
      $label = $row['domain'] ?? $row['line'] ?? '';
      $ok = !empty($row['ok']);
    ?>
    <div class="log-line">
      <span class="badge <?= $ok ? 'badge-success' : 'badge-danger' ?>"><?= $ok ? 'OK' : 'LỖI' ?></span>
      <strong><?= htmlspecialchars((string) $label) ?></strong>
      <?php if ($ok): ?>
        <?php if (!empty($row['ns'])): ?>
          — NS: <?= htmlspecialchars(implode(', ', $row['ns'])) ?>
        <?php endif; ?>
        <?php if (!empty($row['status'])): ?>
          — <?= htmlspecialchars($row['status']) ?>
        <?php endif; ?>
        <?php if (isset($row['count'])): ?>
          — đã xoá <?= (int) $row['count'] ?> mục
        <?php endif; ?>
      <?php else: ?>
        — <?= htmlspecialchars($row['error'] ?? 'Không xác định') ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
