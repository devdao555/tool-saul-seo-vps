# SAUL SEO Tool

Công cụ tổng hợp cho IT/SEO: quản lý domain + Cloudflare (zone, DNS, 301 redirect), tự trỏ nameserver ở Namecheap, và dựng/quản lý WordPress trên VPS — gộp lại quy trình từ "mua domain" đến "có site WordPress sống" trong một tool duy nhất.

Viết bằng **PHP thuần, không Composer, không framework** — chỉ cần PHP 8.1+ với các extension chuẩn (`pdo_sqlite`, `curl`, `openssl`), rất phù hợp deploy trực tiếp trên aaPanel.

## Tính năng

- **Tên miền & DNS**: thêm domain (tạo zone Cloudflare, trả về NS), check NS/trạng thái, push NS sang Namecheap, push DNS (A + CNAME www), xoá DNS.
- **Chuyển hướng 301**: tạo/xoá Cloudflare Page Rules để redirect hàng loạt domain nguồn về 1 domain đích.
- **Cấu hình website**: tạo WordPress trắng hoặc clone WordPress sang domain khác, trên VPS bất kỳ trong danh sách.
- **Quản lý website**: xoá site (kèm xoá DNS Cloudflare), đổi mật khẩu admin WordPress, clear cache.
- **Bảo mật**: scan mã độc/webshell — theo domain cụ thể hoặc quét toàn bộ site tìm thấy trên 1 VPS, kết hợp `wp core verify-checksums` (so khớp core với WordPress.org) và heuristic grep (pattern webshell/backdoor phổ biến). Lưu lịch sử scan, hiển thị badge bảo mật ngay trong danh sách domain.
- **SSL & Domain**: check hạn SSL + HTTP→HTTPS redirect (không cần VPS, check trực tiếp qua cổng 443), renew SSL qua SSH (certbot/acme.sh best-effort), check hạn đăng ký domain (qua Namecheap API).
- **VPS**: thêm/xoá VPS, kiểm tra CPU/RAM/Disk/Load + trạng thái nginx/mysql/redis/php-fpm (từng VPS hoặc tất cả cùng lúc), restart nhanh 1 trong 4 dịch vụ đó (whitelist cố định, không nhận tên dịch vụ tuỳ ý).
- **Cloudflare nâng cao**: purge cache, bật/tắt proxy (mây cam) hàng loạt, push DNS hỗ trợ cả AAAA (IPv6), scan DNS toàn hệ thống để phát hiện domain thiếu record hoặc IP lệch so với VPS đã gán.
- **Cài đặt**: quản lý nhiều Cloudflare account, cấu hình Namecheap API, quản lý danh sách VPS.
- Toàn bộ hành động được ghi log trong "Log hệ thống".

## Yêu cầu

- PHP >= 8.1 với extension: `pdo_sqlite`, `curl`, `openssl`.
- OpenSSH client (`ssh`) trên máy chạy PHP (có sẵn trên hầu hết Linux, kể cả aaPanel).
- Mỗi VPS quản lý WordPress cần có sẵn: aaPanel, Nginx, PHP-FPM, MySQL, [WP-CLI](https://wp-cli.org/) (`wp` phải chạy được từ SSH).
- Cloudflare API Token (quyền Zone Edit, DNS Edit, Page Rules Edit).
- (Tuỳ chọn) Namecheap API access nếu muốn tự trỏ NS.

## Cài đặt / Deploy lên aaPanel

1. **Tạo site PHP trong aaPanel**: Website → Add site → chọn PHP 8.1+, KHÔNG cần MySQL (tool dùng SQLite riêng). Domain trỏ tới site này chính là domain quản trị tool (khác với các domain bạn sẽ quản lý).
2. **Đưa code lên server**: dùng Git (aaPanel hỗ trợ "Clone" trong File Manager, hoặc SSH `git clone`), hoặc upload zip rồi giải nén vào thư mục site.
3. **Trỏ document root vào `/public`**: trong aaPanel → Site Settings → Site Directory, đặt "Run directory" là `/public`.
4. **Tạo `.env`**: copy `.env.example` thành `.env`, điền:
   - `APP_KEY`: chuỗi ngẫu nhiên (`php -r "echo bin2hex(random_bytes(16));"`).
   - `ADMIN_USERNAME`, `ADMIN_PASSWORD_HASH` (`php -r "echo password_hash('mat-khau-cua-ban', PASSWORD_DEFAULT);"`).
5. **Khởi tạo database**: chạy `php database/migrate.php` (qua SSH, hoặc Terminal trong aaPanel). Lệnh này tạo `storage/db.sqlite`.
6. **Set quyền ghi** cho `storage/` (aaPanel: chown về user chạy PHP-FPM của site, thường là `www`):
   ```
   chown -R www:www storage
   chmod -R 770 storage
   ```
7. Truy cập domain quản trị → đăng nhập bằng `ADMIN_USERNAME` / mật khẩu gốc bạn đã hash ở bước 4.
8. Vào **Cài đặt** để thêm Cloudflare account (API Token + Account ID) và (tuỳ chọn) Namecheap API.
9. Vào **VPS** để thêm từng VPS sẽ dùng để dựng WordPress (xem phần SSH key bên dưới).

### Chạy thử ở local (không có aaPanel)

```
php -S localhost:8000 -t public
```

Rồi mở `http://localhost:8000`. Các thao tác gọi Cloudflare/Namecheap/VPS vẫn cần credentials thật để hoạt động — không có credentials sẽ trả lỗi rõ ràng thay vì giả lập thành công.

## Chuẩn bị SSH key cho VPS

Tool dùng SSH key (không dùng mật khẩu) để chạy lệnh trên VPS:

```
ssh-keygen -t ed25519 -f saul-tool-key -N ""
```

- Copy nội dung `saul-tool-key.pub` vào `~/.ssh/authorized_keys` của user SSH trên từng VPS (thường là `root`).
- Khi thêm VPS trong tool (mục **VPS**), dán nội dung **private key** (`saul-tool-key`) vào ô "SSH Private Key". Tool lưu file này vào `storage/keys/` (không commit lên Git, đã có trong `.gitignore`) và chỉ dùng nó để SSH tới đúng VPS đó.

## Lưu ý quan trọng về module VPS/WordPress

Vhost Nginx và lệnh tạo site được tool tự sinh (`src/Vps/WordPressManager.php`) dựa trên quy ước chuẩn của aaPanel:
- Webroot: `/www/wwwroot/<domain>`
- Vhost Nginx: `/www/server/panel/vhost/nginx/<domain>.conf`
- PHP-FPM socket: `/tmp/php-cgi-<version>.sock`

Đây là quy ước ổn định trên hầu hết bản aaPanel, nhưng **hãy test trên 1 domain thử trước** khi dùng hàng loạt trên VPS production, vì cấu hình chi tiết (OpenLiteSpeed thay vì Nginx, custom PHP path, v.v.) có thể khác tuỳ server. Có thể chỉnh sửa template trong `nginxVhost()` nếu cần.

## Lưu ý quan trọng về module Bảo mật (quét mã độc)

`src/Vps/SecurityScanner.php` chỉ làm 2 việc, cả hai đều **best-effort**, không phải antivirus:

1. `wp core verify-checksums` — so khớp core WordPress với bản gốc trên WordPress.org qua mạng. Thoát khác 0 có thể do file bị sửa, **hoặc** do VPS không ra được internet, **hoặc** bản WP không có trong checksum DB — luôn xem "Xem chi tiết" trước khi kết luận.
2. Grep heuristic tìm pattern webshell/backdoor phổ biến (`eval(base64_decode(...`, gọi hàm động từ `$_GET/$_POST`, tên file kiểu `c99.php`/`r57.php`/`wso.php`...). Có thể bỏ sót mã độc được obfuscate kỹ, hoặc báo nhầm code hợp lệ trùng pattern.

Chỉ scan được domain đã gắn VPS trong hệ thống (mục "Scan theo domain"), hoặc mọi thư mục tìm thấy trong webroot của 1 VPS (mục "Scan toàn bộ 1 VPS", kể cả domain chưa được tool quản lý). Kết quả nghi ngờ nên được admin tự kiểm tra file trước khi xoá — tool không tự động xoá file nghi ngờ.

## Lưu ý về module VPS Monitoring / Restart dịch vụ

`src/Vps/VpsMonitor.php` chỉ được phép restart 4 dịch vụ cố định: `nginx`, `mysql`, `redis`, `php-fpm` (tên service cụ thể tự dò theo `php_version` của từng VPS). Đây là whitelist cứng trong code — không có đường nào để restart một service tuỳ ý từ giao diện, kể cả khi sửa request. Trạng thái dịch vụ dựa trên `systemctl is-active`; VPS không dùng systemd sẽ luôn hiện "off" dù dịch vụ thật sự đang chạy.

## Lưu ý về module SSL & Domain

- Check SSL (cert + redirect) chạy trực tiếp từ server host tool, không qua SSH — hoạt động với bất kỳ domain public nào, không cần domain đó có trong danh sách VPS.
- Renew SSL cần domain đã gắn VPS, và VPS đó phải có `certbot` hoặc `~/.acme.sh/acme.sh` — nếu aaPanel quản lý SSL bằng cách khác, renew sẽ báo "không tìm thấy", lúc đó vào giao diện aaPanel renew thủ công.
- Check hạn domain chỉ đúng với domain đăng ký tại Namecheap (dùng chung cấu hình API với tính năng push NS).

## Bảo mật hệ thống

- API token, mật khẩu MySQL, Namecheap key được mã hoá (`AES-256-CBC`) bằng `APP_KEY` trước khi lưu vào SQLite.
- SSH dùng key riêng cho tool, không dùng mật khẩu.
- Mọi form POST có CSRF token.
- Domain/IP/username luôn được validate bằng regex nghiêm ngặt trước khi đưa vào lệnh shell hoặc API call, để chống command injection.
- Các hành động phá huỷ (xoá DNS, xoá Page Rules, xoá WordPress) đều có xác nhận (`confirm()`) phía client — vẫn nên cẩn trọng vì các API này không có "thùng rác".
- **Không commit `.env` và `storage/` lên Git** — đã cấu hình sẵn trong `.gitignore`.

## Cấu trúc thư mục

```
public/       # document root — front controller cho từng trang + assets
src/          # toàn bộ logic PHP (Support, Auth, Cloudflare, Namecheap, Security, Ssl, Vps, Controllers)
views/        # template HTML (PHP thuần, không blade/twig)
database/     # schema.sql + script migrate
storage/      # db.sqlite, ssh keys, logs (gitignored)
```
