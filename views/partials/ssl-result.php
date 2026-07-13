<?php
/**
 * Renders SSL/domain check result rows (fresh scan or DB history).
 */
/** @var array $results */
if (empty($results)) {
    return;
}

$badgeFor = static function (string $status): array {
    return match ($status) {
        'ok' => ['badge-success', 'OK'],
        'expiring_soon' => ['badge-warn', 'Sắp hết hạn'],
        'expired' => ['badge-danger', 'Đã hết hạn'],
        default => ['badge-danger', 'Lỗi'],
    };
};
?>
<div class="log-box" style="max-height:none; margin-top:16px;">
  <?php foreach ($results as $row): ?>
    <?php [$badgeClass, $badgeLabel] = $badgeFor($row['status'] ?? 'error'); ?>
    <div class="log-line">
      <span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
      <strong><?= htmlspecialchars($row['domain'] ?? '') ?></strong>
      <?php if (!empty($row['summary'])): ?>
        — <?= htmlspecialchars($row['summary']) ?>
      <?php elseif (!empty($row['valid_to'])): ?>
        — hết hạn <?= htmlspecialchars($row['valid_to']) ?>
        <?php if (isset($row['days_left'])): ?> (còn <?= (int) $row['days_left'] ?> ngày)<?php endif; ?>
        <?php if (!empty($row['issuer'])): ?> · CA: <?= htmlspecialchars($row['issuer']) ?><?php endif; ?>
        <?php if (array_key_exists('http_redirects_https', $row)): ?>
          · HTTP→HTTPS: <?= $row['http_redirects_https'] ? 'có' : 'không' ?>
        <?php endif; ?>
      <?php elseif (!empty($row['error'])): ?>
        — <?= htmlspecialchars($row['error']) ?>
      <?php endif; ?>
      <?php if (!empty($row['checked_at'])): ?>
        <span style="color:var(--text-dim);"> · <?= htmlspecialchars($row['checked_at']) ?></span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
