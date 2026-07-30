<?php

namespace App\Vps;

use App\Support\Validator;

/**
 * Builds and runs the remote bash/WP-CLI commands used to provision and manage WordPress
 * sites on a VPS. Assumes each VPS is running aaPanel with Nginx, PHP-FPM, MySQL and
 * WP-CLI (`wp`) already installed/available on PATH.
 *
 * The Nginx vhost written here is intentionally minimal and self-contained (it does not
 * depend on aaPanel version-specific include snippets) so it works across aaPanel
 * versions, but you should still spot-check the generated conf against your server the
 * first time you use this on a new VPS image.
 */
class WordPressManager
{
    /**
     * Where operator-supplied theme/plugin ZIPs and .wpress archives live on each VPS.
     * Files land here either by upload through the web UI or by the operator dropping
     * them in via aaPanel/FTP (which sidesteps PHP's upload size limits).
     */
    public const LIBRARY_DIR = '/www/saul-library';

    public function __construct(private array $vps)
    {
    }

    private function webroot(string $domain): string
    {
        return rtrim($this->vps['webroot_base'], '/') . '/' . $domain;
    }

    /**
     * Deterministic MySQL identifier derived from the domain, so delete/clone don't need
     * to look up a stored db name — same domain always maps to the same db/user.
     */
    private function dbIdentifier(string $domain): string
    {
        return 'wp_' . substr(md5($domain), 0, 12);
    }

    private function q(string $value): string
    {
        return SshClient::bashQuote($value);
    }

    private function certDir(string $domain): string
    {
        return '/www/server/panel/vhost/cert/' . $domain;
    }

    private function nginxVhost(string $domain, string $webroot, bool $ssl = false): string
    {
        $phpVersion = preg_replace('/[^0-9]/', '', (string) $this->vps['php_version']);

        $listen = "listen 80;";
        $redirect = '';
        if ($ssl) {
            $certDir = $this->certDir($domain);
            $listen = <<<SSLCONF
listen 443 ssl;
    http2 on;
    ssl_certificate {$certDir}/fullchain.pem;
    ssl_certificate_key {$certDir}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_session_cache shared:SSL:10m;
SSLCONF;
            $redirect = <<<REDIR
server {
    listen 80;
    server_name {$domain} www.{$domain};
    return 301 https://\$host\$request_uri;
}

REDIR;
        }

        return <<<CONF
{$redirect}server {
    {$listen}
    server_name {$domain} www.{$domain};
    root {$webroot};
    index index.php index.html index.htm;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ [^/]\.php(/|\$) {
        fastcgi_pass unix:/tmp/php-cgi-{$phpVersion}.sock;
        fastcgi_index index.php;
        fastcgi_split_path_info ^(.+\.php)(/.+)\$;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    access_log /www/wwwlogs/{$domain}.log;
    error_log /www/wwwlogs/{$domain}.error.log;
}
CONF;
    }

    private function assertDomain(string $domain): void
    {
        if (!Validator::isDomain($domain)) {
            throw new \InvalidArgumentException("Domain không hợp lệ: {$domain}");
        }
    }

    public function createBlankSite(string $domain, string $adminUser, string $adminPass, string $adminEmail): SshResult
    {
        $this->assertDomain($domain);
        if (!Validator::isSafeUsername($adminUser)) {
            throw new \InvalidArgumentException('Username admin không hợp lệ.');
        }

        $webroot = $this->webroot($domain);
        $vhostPath = '/www/server/panel/vhost/nginx/' . $domain . '.conf';
        $dbName = $this->dbIdentifier($domain);
        $dbUser = $dbName;
        $dbPass = bin2hex(random_bytes(12));
        $mysqlUser = $this->vps['mysql_user'];
        $mysqlPass = VpsRepository::mysqlPassword($this->vps);

        $vhost = $this->nginxVhost($domain, $webroot);

        $script = <<<BASH
set -e
mkdir -p {$this->q($webroot)}
mkdir -p /www/wwwlogs
cat > {$this->q($vhostPath)} <<'NGINXCONF'
{$vhost}
NGINXCONF
nginx -t && (nginx -s reload || systemctl reload nginx || service nginx reload)

mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} -e "CREATE DATABASE IF NOT EXISTS \`{$dbName}\` CHARACTER SET utf8mb4;"
mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} -e "CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPass}';"
mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} -e "GRANT ALL PRIVILEGES ON \`{$dbName}\`.* TO '{$dbUser}'@'localhost'; FLUSH PRIVILEGES;"

cd {$this->q($webroot)}
wp core download --allow-root --skip-content
wp config create --dbname={$this->q($dbName)} --dbuser={$this->q($dbUser)} --dbpass={$this->q($dbPass)} --dbhost=localhost --allow-root --skip-check --force
wp core install --url={$this->q('https://' . $domain)} --title={$this->q($domain)} --admin_user={$this->q($adminUser)} --admin_password={$this->q($adminPass)} --admin_email={$this->q($adminEmail)} --skip-email --allow-root

chown -R www:www {$this->q($webroot)} || true
echo "SAUL_TOOL_OK"
BASH;

        return $this->run($script);
    }

    /**
     * Provision a fresh WordPress install and layer a theme + plugin stack on top of it, so you
     * can rebuild a site whose front-end you fingerprinted (e.g. from an external live site) but
     * whose files/DB you don't have server access to.
     *
     * All theme/plugin ZIPs are installed from URLs that YOU supply and that the VPS can reach —
     * this method never sources anything itself. Use it with license-holding copies of premium
     * themes/plugins; free ones can be pulled from the wp.org repo by slug via $pluginSlugs.
     *
     * @param string[] $themeZips     ZIP URLs to install as themes (e.g. Flatsome parent + child).
     * @param string   $activateTheme Theme slug to activate after install (e.g. 'themeweb' child).
     * @param string[] $pluginSlugs   wp.org plugin slugs, installed + activated (free plugins).
     * @param string[] $pluginZips    ZIP URLs, installed + activated (premium plugins you own).
     * @param string   $demoXmlUrl    Optional WordPress WXR (.xml) export URL to import as content.
     * @param string   $wpressUrl     Optional All-in-One WP Migration (.wpress) archive URL. This is
     *                                a FULL site (files + DB); restoring it produces a 1:1 clone and
     *                                makes the theme/plugin/XML params above redundant.
     */
    public function createSiteFromTemplate(
        string $domain,
        string $adminUser,
        string $adminPass,
        string $adminEmail,
        array $themeZips,
        string $activateTheme,
        array $pluginSlugs = [],
        array $pluginZips = [],
        string $demoXmlUrl = '',
        string $wpressUrl = ''
    ): SshResult {
        $this->assertDomain($domain);
        if (!Validator::isSafeUsername($adminUser)) {
            throw new \InvalidArgumentException('Username admin không hợp lệ.');
        }
        if ($activateTheme !== '' && !preg_match('/^[a-z0-9_-]+$/i', $activateTheme)) {
            throw new \InvalidArgumentException('Theme slug không hợp lệ.');
        }

        $webroot = $this->webroot($domain);
        $path = $this->q($webroot);

        $blank = $this->createBlankSite($domain, $adminUser, $adminPass, $adminEmail);
        if (!$blank->ok()) {
            return $blank;
        }

        $steps = [];
        foreach ($themeZips as $zip) {
            $steps[] = 'wp theme install ' . $this->q($this->assertUrlOrLibraryPath($zip)) . " --force --allow-root --path={$path}";
        }
        if ($activateTheme !== '') {
            $steps[] = 'wp theme activate ' . $this->q($activateTheme) . " --allow-root --path={$path}";
        }
        foreach ($pluginSlugs as $slug) {
            if (!preg_match('/^[a-z0-9_-]+$/i', $slug)) {
                throw new \InvalidArgumentException("Plugin slug không hợp lệ: {$slug}");
            }
            $steps[] = 'wp plugin install ' . $this->q($slug) . " --activate --allow-root --path={$path}";
        }
        foreach ($pluginZips as $zip) {
            $steps[] = 'wp plugin install ' . $this->q($this->assertUrlOrLibraryPath($zip)) . " --activate --force --allow-root --path={$path}";
        }
        if ($demoXmlUrl !== '') {
            $tmpXml = '/tmp/saul-demo-' . substr(md5($domain), 0, 8) . '.xml';
            $steps[] = "wp plugin install wordpress-importer --activate --allow-root --path={$path}";
            $steps[] = $this->fetchArchiveCmd($demoXmlUrl, $tmpXml);
            $steps[] = "wp import {$this->q($tmpXml)} --authors=create --allow-root --path={$path}";
            $steps[] = "rm -f {$this->q($tmpXml)}";
        }
        if ($wpressUrl !== '') {
            // All-in-One WP Migration: drop the .wpress into its backups dir, then restore it.
            // Restore overwrites files + DB and auto-rewrites the source URL to this site's URL.
            $backupDir = $webroot . '/wp-content/ai1wm-backups';
            $archive = 'saul-restore-' . substr(md5($domain), 0, 8) . '.wpress';
            $steps[] = "wp plugin install all-in-one-wp-migration --activate --allow-root --path={$path}";
            $steps[] = 'mkdir -p ' . $this->q($backupDir);
            $steps[] = $this->fetchArchiveCmd($wpressUrl, $backupDir . '/' . $archive);
            $steps[] = "wp ai1wm restore {$this->q($archive)} --yes --allow-root --path={$path}";
            $steps[] = 'rm -f ' . $this->q($backupDir . '/' . $archive);
        }
        $stepsScript = implode("\n", $steps);

        $script = <<<BASH
set -e
{$stepsScript}
chown -R www:www {$path} || true
echo "SAUL_TOOL_OK"
BASH;

        return $this->run($script);
    }

    private function assertUrl(string $url): string
    {
        $url = trim($url);
        if (!preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException("URL không hợp lệ: {$url}");
        }
        return $url;
    }

    /**
     * Accepts either an http(s) URL or an archive already sitting in the VPS library dir.
     * Anything outside that directory is rejected so a crafted path can't make WP-CLI read
     * arbitrary files on the server.
     */
    private function assertUrlOrLibraryPath(string $value): string
    {
        $value = trim($value);
        if (preg_match('#^https?://#i', $value)) {
            return $this->assertUrl($value);
        }
        if (!Validator::isSafePath($value) || !str_starts_with($value, self::LIBRARY_DIR . '/')) {
            throw new \InvalidArgumentException("Đường dẫn file không hợp lệ: {$value}");
        }
        return $value;
    }

    /**
     * Fetches an archive into place: a URL is downloaded on the VPS with curl, a library
     * path is copied. Returns the bash command, so callers can slot it into their script.
     */
    private function fetchArchiveCmd(string $source, string $destination): string
    {
        $source = $this->assertUrlOrLibraryPath($source);
        return preg_match('#^https?://#i', $source)
            ? 'curl -fsSL ' . $this->q($source) . ' -o ' . $this->q($destination)
            : 'cp -f ' . $this->q($source) . ' ' . $this->q($destination);
    }

    /**
     * Uploads a local archive (already validated by the controller) into the VPS library dir
     * and returns its remote path, ready to pass to createSiteFromTemplate().
     */
    public function uploadToLibrary(string $localPath, string $filename): string
    {
        if (!preg_match('/^[A-Za-z0-9._-]{1,120}$/', $filename)) {
            throw new \InvalidArgumentException("Tên file không hợp lệ: {$filename}");
        }

        $prepare = $this->run('mkdir -p ' . $this->q(self::LIBRARY_DIR) . ' && chmod 750 ' . $this->q(self::LIBRARY_DIR) . ' && echo "SAUL_TOOL_OK"');
        if (!$prepare->ok()) {
            throw new \RuntimeException('Không tạo được thư mục kho trên VPS: ' . trim($prepare->stderr));
        }

        $remotePath = self::LIBRARY_DIR . '/' . $filename;
        $upload = SshClient::forVps($this->vps)->uploadFile($localPath, $remotePath);
        if ($upload->exitCode !== 0) {
            throw new \RuntimeException('Upload lên VPS thất bại: ' . trim($upload->stderr));
        }

        return $remotePath;
    }

    /**
     * Lists archives available in the VPS library dir, so the operator can pick a file that
     * was put there outside the browser (aaPanel/FTP) — no PHP upload limit involved.
     *
     * @return array<int, array{path:string, name:string, size:string}>
     */
    public function listLibraryArchives(): array
    {
        $dir = $this->q(self::LIBRARY_DIR);
        $result = $this->run("mkdir -p {$dir}\nfind {$dir} -maxdepth 1 -type f \\( -name '*.zip' -o -name '*.wpress' \\) -printf '%f\\t%s\\n' | sort\necho \"SAUL_TOOL_OK\"");
        if (!$result->ok()) {
            return [];
        }

        $archives = [];
        foreach (preg_split('/\r\n|\r|\n/', $result->stdout) ?: [] as $line) {
            if (!str_contains($line, "\t")) {
                continue;
            }
            [$name, $bytes] = explode("\t", trim($line), 2);
            if ($name === '' || !ctype_digit($bytes)) {
                continue;
            }
            $archives[] = [
                'path' => self::LIBRARY_DIR . '/' . $name,
                'name' => $name,
                'size' => self::humanSize((int) $bytes),
            ];
        }
        return $archives;
    }

    private static function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, 1) . ' ' . $unit;
            }
            $bytes /= 1024;
        }
        return $bytes . ' B';
    }

    public function cloneSite(string $sourceDomain, string $targetDomain, bool $closeIndexing = false): SshResult
    {
        $this->assertDomain($sourceDomain);
        $this->assertDomain($targetDomain);

        $sourceRoot = $this->webroot($sourceDomain);
        $targetRoot = $this->webroot($targetDomain);
        $vhostPath = '/www/server/panel/vhost/nginx/' . $targetDomain . '.conf';
        $dbName = $this->dbIdentifier($targetDomain);
        $dbUser = $dbName;
        $dbPass = bin2hex(random_bytes(12));
        $mysqlUser = $this->vps['mysql_user'];
        $mysqlPass = VpsRepository::mysqlPassword($this->vps);

        $vhost = $this->nginxVhost($targetDomain, $targetRoot);
        $blogPublicCmd = $closeIndexing
            ? "wp option update blog_public 0 --allow-root --path={$this->q($targetRoot)}"
            : 'true';

        // Dump to /tmp, never into the source webroot — a dump sitting under the webroot is
        // downloadable over HTTP for as long as the clone runs.
        $dumpFile = '/tmp/saul-clone-' . substr(md5($sourceDomain . $targetDomain), 0, 12) . '.sql';

        // Replacing the source host directly would re-match text an earlier pass just wrote
        // whenever the target contains the source (cloning site.com to demo1.site.com yields
        // demo1.demo1.site.com). Bouncing through a placeholder that contains neither host
        // makes the rewrite safe in both directions.
        $placeholder = 'saulclone' . substr(md5($targetDomain), 0, 12) . '.invalid';

        $script = <<<BASH
set -e
if [ ! -d {$this->q($sourceRoot)} ]; then
  echo "SOURCE_NOT_FOUND"
  exit 1
fi

mkdir -p {$this->q($targetRoot)}
mkdir -p /www/wwwlogs
cp -a {$this->q($sourceRoot)}/. {$this->q($targetRoot)}/

cat > {$this->q($vhostPath)} <<'NGINXCONF'
{$vhost}
NGINXCONF
nginx -t && (nginx -s reload || systemctl reload nginx || service nginx reload)

mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} -e "CREATE DATABASE IF NOT EXISTS \`{$dbName}\` CHARACTER SET utf8mb4;"
mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} -e "CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPass}';"
mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} -e "GRANT ALL PRIVILEGES ON \`{$dbName}\`.* TO '{$dbUser}'@'localhost'; FLUSH PRIVILEGES;"

wp db export {$this->q($dumpFile)} --allow-root --path={$this->q($sourceRoot)}
mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} {$this->q($dbName)} < {$this->q($dumpFile)}
rm -f {$this->q($dumpFile)}

wp config set DB_NAME {$this->q($dbName)} --allow-root --path={$this->q($targetRoot)}
wp config set DB_USER {$this->q($dbUser)} --allow-root --path={$this->q($targetRoot)}
wp config set DB_PASSWORD {$this->q($dbPass)} --allow-root --path={$this->q($targetRoot)}
wp config set DB_HOST localhost --allow-root --path={$this->q($targetRoot)}

wp search-replace {$this->q($sourceDomain)} {$this->q($placeholder)} --all-tables --skip-columns=guid --allow-root --path={$this->q($targetRoot)} || true
wp search-replace {$this->q($placeholder)} {$this->q($targetDomain)} --all-tables --skip-columns=guid --allow-root --path={$this->q($targetRoot)} || true
wp search-replace {$this->q('http://' . $targetDomain)} {$this->q('https://' . $targetDomain)} --all-tables --skip-columns=guid --allow-root --path={$this->q($targetRoot)} || true
wp option update home {$this->q('https://' . $targetDomain)} --allow-root --path={$this->q($targetRoot)}
wp option update siteurl {$this->q('https://' . $targetDomain)} --allow-root --path={$this->q($targetRoot)}
{$blogPublicCmd}

chown -R www:www {$this->q($targetRoot)} || true
echo "SAUL_TOOL_OK"
BASH;

        return $this->run($script);
    }

    /**
     * Installs a Cloudflare Origin CA certificate on a site and switches its vhost to HTTPS.
     *
     * An origin cert is signed by Cloudflare's private CA, not a public one: browsers only
     * accept it when the hostname is proxied (orange cloud) and the zone's SSL mode is
     * Full (strict). Hitting the origin IP directly will always warn — that is by design.
     * One wildcard cert (*.example.com) covers every subdomain, so this is normally called
     * with the same cert/key for each new demo site.
     */
    public function installOriginCert(string $domain, string $certPem, string $keyPem): SshResult
    {
        $this->assertDomain($domain);

        $certPem = trim($certPem);
        $keyPem = trim($keyPem);
        if (!str_contains($certPem, '-----BEGIN CERTIFICATE-----')) {
            throw new \InvalidArgumentException('Certificate không hợp lệ — thiếu dòng BEGIN CERTIFICATE.');
        }
        if (!preg_match('/-----BEGIN (RSA |EC )?PRIVATE KEY-----/', $keyPem)) {
            throw new \InvalidArgumentException('Private key không hợp lệ — thiếu dòng BEGIN PRIVATE KEY.');
        }

        $webroot = $this->webroot($domain);
        $certDir = $this->certDir($domain);
        $vhostPath = '/www/server/panel/vhost/nginx/' . $domain . '.conf';
        $vhost = $this->nginxVhost($domain, $webroot, true);

        $script = <<<BASH
set -e
if [ ! -d {$this->q($webroot)} ]; then
  echo "SITE_NOT_FOUND"
  exit 1
fi

mkdir -p {$this->q($certDir)}
cat > {$this->q($certDir . '/fullchain.pem')} <<'SAULCERTPEM'
{$certPem}
SAULCERTPEM
cat > {$this->q($certDir . '/privkey.pem')} <<'SAULKEYPEM'
{$keyPem}
SAULKEYPEM
chmod 644 {$this->q($certDir . '/fullchain.pem')}
chmod 600 {$this->q($certDir . '/privkey.pem')}

cat > {$this->q($vhostPath)} <<'NGINXCONF'
{$vhost}
NGINXCONF
nginx -t && (nginx -s reload || systemctl reload nginx || service nginx reload)
echo "SAUL_TOOL_OK"
BASH;

        return $this->run($script);
    }

    public function deleteSite(string $domain): SshResult
    {
        $this->assertDomain($domain);

        $webroot = $this->webroot($domain);
        $vhostPath = '/www/server/panel/vhost/nginx/' . $domain . '.conf';
        $dbName = $this->dbIdentifier($domain);
        $dbUser = $dbName;
        $mysqlUser = $this->vps['mysql_user'];
        $mysqlPass = VpsRepository::mysqlPassword($this->vps);

        $script = <<<BASH
set +e
rm -f {$this->q($vhostPath)}
(nginx -s reload || systemctl reload nginx || service nginx reload)
mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} -e "DROP DATABASE IF EXISTS \`{$dbName}\`;"
mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} -e "DROP USER IF EXISTS '{$dbUser}'@'localhost';"
if [ -d {$this->q($webroot)} ]; then
  rm -rf {$this->q($webroot)}
fi
echo "SAUL_TOOL_OK"
BASH;

        return $this->run($script);
    }

    public function changeAdminPassword(string $domain, string $username, string $newPassword): SshResult
    {
        $this->assertDomain($domain);
        if (!Validator::isSafeUsername($username)) {
            throw new \InvalidArgumentException('Username không hợp lệ.');
        }

        $webroot = $this->webroot($domain);
        $script = <<<BASH
set -e
wp user update {$this->q($username)} --user_pass={$this->q($newPassword)} --allow-root --path={$this->q($webroot)}
echo "SAUL_TOOL_OK"
BASH;

        return $this->run($script);
    }

    public function clearCache(string $domain): SshResult
    {
        $this->assertDomain($domain);
        $webroot = $this->webroot($domain);
        $path = $this->q($webroot);

        $script = <<<BASH
set +e
wp rocket clean --confirm --allow-root --path={$path} 2>/dev/null
wp w3-total-cache flush all --allow-root --path={$path} 2>/dev/null
wp super-cache flush --allow-root --path={$path} 2>/dev/null
wp litespeed-purge all --allow-root --path={$path} 2>/dev/null
wp cache flush --allow-root --path={$path}
echo "SAUL_TOOL_OK"
BASH;

        return $this->run($script);
    }

    /**
     * Pushes the bundled SAUL AI Writer plugin (storage/plugins/saul-ai-writer) onto a site,
     * activates it, and writes its settings + keyword queue via WP-CLI. Also registers a
     * system crontab entry that runs due WP-Cron events every 10 minutes, because brand-new
     * sites have no visitors so WP-Cron would otherwise never fire.
     *
     * @param array    $settings saul_aiw_settings option payload (provider, api_key, model...)
     * @param string[] $keywords appended (deduped) to the site's existing queue, not replacing it
     */
    public function deployAiWriter(string $domain, array $settings, array $keywords, bool $runNow = false): SshResult
    {
        $this->assertDomain($domain);

        $pluginFile = ROOT_PATH . '/storage/plugins/saul-ai-writer/saul-ai-writer.php';
        $source = @file_get_contents($pluginFile);
        if ($source === false) {
            throw new \RuntimeException('Không tìm thấy file plugin: storage/plugins/saul-ai-writer/saul-ai-writer.php');
        }

        $webroot = $this->webroot($domain);
        $path = $this->q($webroot);
        $pluginDir = $this->q($webroot . '/wp-content/plugins/saul-ai-writer');
        $pluginDest = $this->q($webroot . '/wp-content/plugins/saul-ai-writer/saul-ai-writer.php');
        // Base64 keeps the plugin source (quotes, $vars, heredocs) intact through SSH stdin.
        $b64 = base64_encode($source);
        $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $steps = [];
        $steps[] = "mkdir -p {$pluginDir}";
        $steps[] = "echo {$this->q($b64)} | base64 -d > {$pluginDest}";
        $steps[] = "wp plugin activate saul-ai-writer --allow-root --path={$path}";
        $steps[] = "wp option update saul_aiw_settings {$this->q($settingsJson)} --format=json --allow-root --path={$path}";
        if (!empty($keywords)) {
            $steps[] = "wp saul-aiw add {$this->q(implode("\n", $keywords))} --allow-root --path={$path}";
        }

        // Crontab entry keyed on the webroot so re-deploying replaces instead of duplicating.
        $cronCmd = "cd {$webroot} && wp cron event run --due-now --allow-root >/dev/null 2>&1";
        $steps[] = '( { crontab -l 2>/dev/null | grep -vF ' . $this->q($cronCmd) . ' || true; } ; echo ' . $this->q('*/10 * * * * ' . $cronCmd) . ' ) | crontab -';

        if ($runNow) {
            $steps[] = "wp saul-aiw run --count=1 --allow-root --path={$path}";
        }
        $stepsScript = implode("\n", $steps);

        $script = <<<BASH
set -e
{$stepsScript}
chown -R www:www {$pluginDir} || true
echo "SAUL_TOOL_OK"
BASH;

        // Writing a post can take a couple of AI API round-trips; give it headroom.
        return $this->run($script, $runNow ? 420 : 180);
    }

    protected function run(string $script, int $timeout = 180): SshResult
    {
        $ssh = SshClient::forVps($this->vps);
        return $ssh->runScript($script, $timeout);
    }
}
