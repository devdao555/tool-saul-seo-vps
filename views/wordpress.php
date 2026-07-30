<?php

use App\Support\Csrf;

/** @var array $vpsList */
/** @var array|null $createResults */
/** @var array|null $cloneResults */
/** @var array|null $rebuildResults */
/** @var array|null $uploadResults */
/** @var array|null $sslResults */
/** @var array|null $aiResults */
/** @var array $siteTemplates */
/** @var array $libraryArchives */
/** @var int $libraryVpsId */
/** @var string $libraryDir */
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

  <div class="card">
    <h2>Kho source (.zip / .wpress)</h2>
    <p class="hint">Đẩy file source lên VPS để dùng cho ô "Dựng site" bên dưới — không cần host file ở đâu công khai. Sau khi upload, copy đường dẫn hiện ra và dán vào ô ZIP/.wpress tương ứng.</p>

    <form method="post" action="/wordpress.php" enctype="multipart/form-data">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="upload_archive">
      <label>Chọn VPS</label>
      <select name="upload_vps_id" required>
        <?php foreach ($vpsList as $v): ?>
          <option value="<?= (int) $v['id'] ?>" <?= $libraryVpsId === (int) $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['label']) ?> — <?= htmlspecialchars($v['ip']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>File .zip hoặc .wpress</label>
      <input type="file" name="archive" accept=".zip,.wpress" required>
      <button type="submit" class="btn btn-primary" <?= empty($vpsList) ? 'disabled' : '' ?>>Upload lên VPS</button>
    </form>
    <p class="hint" style="margin-top:6px;">File <code>.wpress</code> thường rất nặng. Nếu báo lỗi vượt giới hạn upload, tăng <code>upload_max_filesize</code> và <code>post_max_size</code> trong aaPanel, hoặc up thẳng file vào <code><?= htmlspecialchars($libraryDir) ?></code> trên VPS rồi bấm "Xem kho" bên dưới.</p>
    <?php $results = $uploadResults; require __DIR__ . '/partials/result-table.php'; ?>

    <form method="get" action="/wordpress.php" style="margin-top:14px;">
      <label>Xem file đã có trong kho</label>
      <select name="library_vps" required>
        <option value="">— chọn VPS —</option>
        <?php foreach ($vpsList as $v): ?>
          <option value="<?= (int) $v['id'] ?>" <?= $libraryVpsId === (int) $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['label']) ?> — <?= htmlspecialchars($v['ip']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-ghost" <?= empty($vpsList) ? 'disabled' : '' ?>>Xem kho</button>
    </form>

    <?php if ($libraryVpsId > 0): ?>
      <?php if (empty($libraryArchives)): ?>
        <p class="hint" style="margin-top:10px;">Kho <code><?= htmlspecialchars($libraryDir) ?></code> chưa có file .zip hoặc .wpress nào.</p>
      <?php else: ?>
        <table style="margin-top:10px;">
          <thead><tr><th>File</th><th>Dung lượng</th><th>Đường dẫn để dán</th></tr></thead>
          <tbody>
            <?php foreach ($libraryArchives as $archive): ?>
              <tr>
                <td><?= htmlspecialchars($archive['name']) ?></td>
                <td><?= htmlspecialchars($archive['size']) ?></td>
                <td><code><?= htmlspecialchars($archive['path']) ?></code></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Cài SSL cho domain / subdomain</h2>
    <p class="hint">Cài Cloudflare Origin Certificate đã lưu trong <a href="/settings.php">Cài đặt</a> lên site, rồi chuyển vhost sang HTTPS (kèm redirect 80 → 443). Một cert wildcard <code>*.domain.com</code> dùng được cho mọi subdomain.</p>
    <div class="alert alert-warn" style="margin-bottom:12px;">
      Origin Certificate chỉ được Cloudflare tin tưởng, không phải trình duyệt. Domain <strong>bắt buộc phải bật mây cam (proxied)</strong> và zone để SSL mode <strong>Full (strict)</strong>, nếu không khách vào site sẽ thấy cảnh báo chứng chỉ.
    </div>
    <form method="post" action="/wordpress.php">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="install_ssl">
      <label>Mỗi dòng 1 domain (phải đã tạo site và đã gắn VPS)</label>
      <textarea name="ssl_domains" placeholder="demo1.domain.com&#10;demo2.domain.com" required></textarea>
      <button type="submit" class="btn btn-primary">Cài SSL</button>
    </form>
    <?php $results = $sslResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>

  <div class="card">
    <h2>Dựng lại từ template (rebuild site đã scan)</h2>
    <p class="hint">Dùng khi bạn scan một site bên ngoài và muốn dựng lại giao diện: tool cài WP trắng rồi cài theme + plugin từ các file ZIP bạn cung cấp URL. <strong>Bạn phải tự có bản license</strong> của theme/plugin premium — tool không tải hộ. Plugin miễn phí điền slug wp.org là được.</p>
    <?php if (!empty($siteTemplates)): ?>
      <div class="preset-row" style="margin-bottom:12px;">
        <span class="hint" style="margin-right:6px;">Preset:</span>
        <?php foreach ($siteTemplates as $key => $tpl): ?>
          <button type="button" class="btn btn-ghost preset-btn"
            data-activate-theme="<?= htmlspecialchars($tpl['activate_theme']) ?>"
            data-plugin-slugs="<?= htmlspecialchars($tpl['plugin_slugs']) ?>"
            data-theme-hint="<?= htmlspecialchars($tpl['theme_hint']) ?>"
            data-plugin-zip-hint="<?= htmlspecialchars($tpl['plugin_zip_hint']) ?>">
            <?= htmlspecialchars($tpl['label']) ?>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" action="/wordpress.php" id="rebuild-form">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="rebuild">
      <label>Chọn VPS</label>
      <select name="rebuild_vps_id" required>
        <?php foreach ($vpsList as $v): ?>
          <option value="<?= (int) $v['id'] ?>"><?= htmlspecialchars($v['label']) ?> — <?= htmlspecialchars($v['ip']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Domain</label>
      <input type="text" name="rebuild_domain" placeholder="newsite.com" required>
      <label>Theme ZIP — URL hoặc đường dẫn file trong kho (mỗi dòng 1 cái, parent trước, child sau)</label>
      <textarea name="theme_zips" placeholder="https://yourhost.com/flatsome.zip&#10;<?= htmlspecialchars($libraryDir) ?>/themeweb-child.zip"></textarea>
      <label>Theme slug để kích hoạt</label>
      <input type="text" name="activate_theme" placeholder="themeweb">
      <label>Plugin miễn phí (slug wp.org, cách nhau bởi dấu phẩy)</label>
      <input type="text" name="plugin_slugs" placeholder="woocommerce, contact-form-7, font-awesome-4-menus">
      <label>Plugin premium ZIP — URL hoặc đường dẫn file trong kho (mỗi dòng 1 cái)</label>
      <textarea name="plugin_zips" placeholder="<?= htmlspecialchars($libraryDir) ?>/advanced-product-fields-pro.zip"></textarea>
      <label>Demo content XML (tuỳ chọn — URL hoặc file .xml trong kho)</label>
      <input type="text" name="demo_xml_url" placeholder="https://yourhost.com/agency7-demo.xml">
      <label>File .wpress — URL hoặc đường dẫn trong kho (All-in-One WP Migration, clone 1:1 cả file + database)</label>
      <input type="text" name="wpress_url" placeholder="<?= htmlspecialchars($libraryDir) ?>/agency7.wpress">
      <p class="hint" style="margin-top:6px;">Nếu bạn có file <code>.wpress</code>, đây là cách clone <strong>giống 100%</strong>: tool cài All-in-One WP Migration rồi restore nguyên site. Khi đó các ô theme/plugin/XML ở trên là tuỳ chọn (không bắt buộc). Lưu ý: sau restore, tài khoản admin sẽ là của site gốc, không phải user/pass bạn nhập bên dưới.</p>
      <label>Admin username</label>
      <input type="text" name="rebuild_admin_user" value="admin">
      <label>Admin password</label>
      <input type="text" name="rebuild_admin_password" placeholder="Mật khẩu admin WP" required>
      <label>Admin email</label>
      <input type="text" name="rebuild_admin_email" placeholder="you@example.com" required>
      <button type="submit" class="btn btn-primary" <?= empty($vpsList) ? 'disabled' : '' ?>>Dựng site</button>
    </form>
    <p class="hint" style="margin-top:10px;">Fingerprint agency7.mauthemewp.com: theme <code>flatsome</code> + child <code>themeweb</code>; plugin free <code>woocommerce, contact-form-7, font-awesome-4-menus, builder-responsive-pricing-tables</code>; plugin premium <code>advanced-product-fields-for-woocommerce-pro, hostiko-domain-checker</code>.</p>
    <?php $results = $rebuildResults; require __DIR__ . '/partials/result-table.php'; ?>
  </div>

  <div class="card">
    <h2>Viết bài tự động bằng AI (SAUL AI Writer)</h2>
    <p class="hint">Cài plugin SAUL AI Writer lên các site đã có trong hệ thống (mỗi dòng 1 domain, tự tìm đúng VPS). Plugin sẽ tự viết bài theo hàng đợi từ khoá và lịch đã chọn — vào <code>wp-admin → Cài đặt → SAUL AI Writer</code> trên từng site để chỉnh riêng. Deploy lại sẽ ghi đè cấu hình và <strong>cộng thêm</strong> từ khoá vào hàng đợi hiện có.</p>
    <form method="post" action="/wordpress.php" id="ai-writer-form">
      <?= Csrf::field() ?>
      <input type="hidden" name="action" value="ai_writer">
      <label>Danh sách domain (site đã tồn tại trong tool)</label>
      <textarea name="ai_domains" placeholder="abc.com&#10;xyz.com" required></textarea>
      <label>Nhà cung cấp AI</label>
      <select name="ai_provider" id="ai_provider">
        <option value="openai">OpenAI</option>
        <option value="claude">Claude (Anthropic)</option>
        <option value="gemini">Gemini (Google)</option>
      </select>
      <label>API key</label>
      <input type="text" name="ai_api_key" placeholder="sk-..." required autocomplete="off">
      <label>Model (để trống = mặc định)</label>
      <input type="text" name="ai_model" id="ai_model" placeholder="Mặc định: gpt-4o-mini">
      <label>Ngôn ngữ bài viết</label>
      <input type="text" name="ai_language" value="Tiếng Việt">
      <label>Số bài mỗi ngày (1–48)</label>
      <input type="number" name="ai_posts_per_day" value="2" min="1" max="48">
      <label>Trạng thái bài đăng</label>
      <select name="ai_post_status">
        <option value="publish">Đăng ngay (publish)</option>
        <option value="draft">Bản nháp (draft)</option>
        <option value="pending">Chờ duyệt (pending)</option>
      </select>
      <label>Chuyên mục (tự tạo nếu chưa có — để trống thì dùng mặc định)</label>
      <input type="text" name="ai_category" placeholder="Tin tức">
      <label>Ảnh đại diện (AI tạo, đặt làm featured image)</label>
      <select name="ai_image_provider">
        <option value="none">Không tạo ảnh</option>
        <option value="openai">OpenAI (DALL·E 3)</option>
        <option value="gemini">Google (Imagen 3)</option>
      </select>
      <label>API key tạo ảnh (để trống = dùng API key chính nếu cùng hãng)</label>
      <input type="text" name="ai_image_api_key" placeholder="Chỉ cần khi khác hãng với AI viết bài" autocomplete="off">
      <label>Yêu cầu thêm cho AI (tuỳ chọn)</label>
      <textarea name="ai_prompt_extra" placeholder="VD: giọng văn thân thiện, hướng tới người mới, chèn danh sách gạch đầu dòng..."></textarea>
      <label>Hàng đợi từ khoá (mỗi dòng 1 từ khoá — dùng chung cho tất cả domain ở trên)</label>
      <textarea name="ai_keywords" placeholder="cách chọn hosting cho website&#10;so sánh vps và hosting&#10;hướng dẫn trỏ tên miền về vps"></textarea>
      <div class="checkbox-row">
        <input type="checkbox" name="ai_run_now" id="ai_run_now" value="1">
        <label for="ai_run_now" style="margin:0;">Viết ngay 1 bài đầu tiên sau khi cài (chạy lâu hơn — chờ AI trả kết quả)</label>
      </div>
      <button type="submit" class="btn btn-primary" <?= empty($vpsList) ? 'disabled' : '' ?>>Cài AI Writer</button>
    </form>
    <?php $results = $aiResults; require __DIR__ . '/partials/result-table.php'; ?>
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

document.addEventListener('DOMContentLoaded', function () {
  var provider = document.getElementById('ai_provider');
  var model = document.getElementById('ai_model');
  if (!provider || !model) return;
  var defaults = {
    openai: 'gpt-4o-mini',
    claude: 'claude-haiku-4-5-20251001',
    gemini: 'gemini-2.0-flash'
  };
  provider.addEventListener('change', function () {
    model.placeholder = 'Mặc định: ' + (defaults[provider.value] || '');
  });
});

document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('rebuild-form');
  if (!form) return;
  document.querySelectorAll('.preset-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      form.querySelector('[name="activate_theme"]').value = b.dataset.activateTheme || '';
      form.querySelector('[name="plugin_slugs"]').value = b.dataset.pluginSlugs || '';
      form.querySelector('[name="theme_zips"]').placeholder =
        'Theme cần: ' + (b.dataset.themeHint || '') + ' — dán URL ZIP bản license của bạn';
      form.querySelector('[name="plugin_zips"]').placeholder =
        'Plugin premium cần: ' + (b.dataset.pluginZipHint || '') + ' — dán URL ZIP bản license';
    });
  });
});
</script>
