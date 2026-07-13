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

    private function nginxVhost(string $domain, string $webroot): string
    {
        $phpVersion = preg_replace('/[^0-9]/', '', (string) $this->vps['php_version']);
        return <<<CONF
server {
    listen 80;
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

wp db export {$this->q($sourceRoot . '/saul-clone-export.sql')} --allow-root --path={$this->q($sourceRoot)}
mysql -u{$this->q($mysqlUser)} -p{$this->q($mysqlPass)} {$this->q($dbName)} < {$this->q($sourceRoot . '/saul-clone-export.sql')}
rm -f {$this->q($sourceRoot . '/saul-clone-export.sql')}

wp config set DB_NAME {$this->q($dbName)} --allow-root --path={$this->q($targetRoot)}
wp config set DB_USER {$this->q($dbUser)} --allow-root --path={$this->q($targetRoot)}
wp config set DB_PASSWORD {$this->q($dbPass)} --allow-root --path={$this->q($targetRoot)}
wp config set DB_HOST localhost --allow-root --path={$this->q($targetRoot)}

wp search-replace {$this->q('https://' . $sourceDomain)} {$this->q('https://' . $targetDomain)} --all-tables --allow-root --path={$this->q($targetRoot)} || true
wp search-replace {$this->q('http://' . $sourceDomain)} {$this->q('https://' . $targetDomain)} --all-tables --allow-root --path={$this->q($targetRoot)} || true
wp search-replace {$this->q($sourceDomain)} {$this->q($targetDomain)} --all-tables --allow-root --path={$this->q($targetRoot)} || true
{$blogPublicCmd}

chown -R www:www {$this->q($targetRoot)} || true
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

    private function run(string $script): SshResult
    {
        $ssh = SshClient::forVps($this->vps);
        return $ssh->runScript($script);
    }
}
