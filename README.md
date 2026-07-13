# SAUL SEO Tool

Công cụ tổng hợp cho IT/SEO: quản lý domain + Cloudflare (zone, DNS, 301 redirect), tự trỏ nameserver ở Namecheap, và dựng/quản lý WordPress trên VPS — gộp lại quy trình từ "mua domain" đến "có site WordPress sống" trong một tool duy nhất.

Viết bằng **PHP thuần, không Composer, không framework** — chỉ cần PHP 8.1+ với các extension chuẩn (`pdo_sqlite`, `curl`, `openssl`), rất phù hợp deploy trực tiếp trên aaPanel.

## Tính năng

- **Tên miền & DNS**: thêm domain (tạo zone Cloudflare, trả về NS), check NS/trạng thái, push NS sang Namecheap, push DNS (A + CNAME www), xoá DNS.
- **Chuyển hướng 301**: tạo/xoá Cloudflare Page Rules để redirect hàng loạt domain nguồn về 1 domain đích.
- **Cấu hình website**: tạo WordPress trắng hoặc clone WordPress sang domain khác, trên VPS bất kỳ trong danh sách.
- **Quản lý website**: xoá site (kèm xoá DNS Cloudflare), đổi mật khẩu admin WordPress, clear cache.
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

## Bảo mật

- API token, mật khẩu MySQL, Namecheap key được mã hoá (`AES-256-CBC`) bằng `APP_KEY` trước khi lưu vào SQLite.
- SSH dùng key riêng cho tool, không dùng mật khẩu.
- Mọi form POST có CSRF token.
- Domain/IP/username luôn được validate bằng regex nghiêm ngặt trước khi đưa vào lệnh shell hoặc API call, để chống command injection.
- Các hành động phá huỷ (xoá DNS, xoá Page Rules, xoá WordPress) đều có xác nhận (`confirm()`) phía client — vẫn nên cẩn trọng vì các API này không có "thùng rác".
- **Không commit `.env` và `storage/` lên Git** — đã cấu hình sẵn trong `.gitignore`.

## Cấu trúc thư mục

```
public/       # document root — front controller cho từng trang + assets
src/          # toàn bộ logic PHP (Support, Auth, Cloudflare, Namecheap, Vps, Controllers)
views/        # template HTML (PHP thuần, không blade/twig)
database/     # schema.sql + script migrate
storage/      # db.sqlite, ssh keys, logs (gitignored)
```
