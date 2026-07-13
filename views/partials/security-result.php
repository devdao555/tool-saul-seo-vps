<?php
/**
 * Renders scan result rows (fresh or historical — same shape after public/security.php
 * normalizes DB rows to match the live scanner output).
 */
/** @var array $results */
if (empty($results)) {
    return;
}

$badgeFor = static function (string $status): array {
    return match ($status) {
        'clean' => ['badge-success', 'Sạch'],
        'suspicious' => ['badge-danger', 'Nghi ngờ'],
        'not_wp' => ['badge-muted', 'Không phải WP'],
        default => ['badge-warn', 'Lỗi'],
    };
};
?>
<div style="margin-top:16px; display:flex; flex-direction:column; gap:10px;">
  <?php foreach ($results as $row): ?>
    <?php [$badgeClass, $badgeLabel] = $badgeFor($row['status'] ?? 'error'); ?>
    <div class="log-box" style="max-height:none;">
      <div class="log-line" style="border-bottom:none;">
        <span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
        <strong><?= htmlspecialchars($row['domain'] ?? '') ?></strong>
        <?php if (!empty($row['vps_label'])): ?> · <?= htmlspecialchars($row['vps_label']) ?><?php endif; ?>
        <?php if (!empty($row['scanned_at'])): ?> · <span style="color:var(--text-dim);"><?= htmlspecialchars($row['scanned_at']) ?></span><?php endif; ?>
        <br>
        <?= htmlspecialchars($row['summary'] ?? ($row['error'] ?? '')) ?>
      </div>
      <?php if (!empty($row['heuristic_matches']) || !empty($row['suspicious_names']) || !empty($row['checksum_detail'])): ?>
        <details style="margin-top:6px;">
          <summary style="cursor:pointer; color:var(--accent);">Xem chi tiết</summary>
          <?php if (!empty($row['heuristic_matches'])): ?>
            <div style="margin-top:8px;"><strong>File khớp pattern nghi ngờ:</strong></div>
            <?php foreach ($row['heuristic_matches'] as $path): ?>
              <div class="log-line"><?= htmlspecialchars($path) ?></div>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if (!empty($row['suspicious_names'])): ?>
            <div style="margin-top:8px;"><strong>File tên trùng webshell phổ biến:</strong></div>
            <?php foreach ($row['suspicious_names'] as $path): ?>
              <div class="log-line"><?= htmlspecialchars($path) ?></div>
            <?php endforeach; ?>
          <?php endif; ?>
          <?php if (!empty($row['checksum_detail'])): ?>
            <div style="margin-top:8px;"><strong>wp core verify-checksums:</strong></div>
            <div class="log-line" style="white-space:pre-wrap;"><?= htmlspecialchars($row['checksum_detail']) ?></div>
          <?php endif; ?>
        </details>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
