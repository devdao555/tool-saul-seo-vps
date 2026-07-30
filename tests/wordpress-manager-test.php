<?php

/**
 * Asserts on the bash that WordPressManager generates, with the SSH layer stubbed out —
 * no VPS, no database, no network. Run it after touching site provisioning:
 *
 *     php tests/wordpress-manager-test.php
 *
 * These commands run as root on production servers, so the cases that matter most here are
 * the ones where a wrong string is silently destructive: rewriting URLs into a corrupt form,
 * leaving a database dump inside a public webroot, or letting a crafted path reach WP-CLI.
 */

declare(strict_types=1);

// Only used to exercise the encrypt/decrypt round-trip on the VPS mysql password field.
if (getenv('APP_KEY') === false) {
    putenv('APP_KEY=wordpress-manager-test-key');
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Support\Crypto;
use App\Vps\SshResult;
use App\Vps\WordPressManager;

final class ScriptCapturingManager extends WordPressManager
{
    public string $script = '';

    protected function run(string $script, int $timeout = 180): SshResult
    {
        $this->script = $script;
        return new SshResult(0, "SAUL_TOOL_OK\n", '');
    }
}

$vps = [
    'id' => 1,
    'ip' => '203.0.113.10',
    'ssh_user' => 'root',
    'ssh_port' => 22,
    'ssh_key_file' => 'test.key',
    'php_version' => '84',
    'webroot_base' => '/www/wwwroot',
    'mysql_user' => 'root',
    'mysql_password_enc' => Crypto::encrypt('test-mysql-password'),
];

$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $hint = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  ok    {$name}\n";
        return;
    }
    $failed++;
    echo "  FAIL  {$name}" . ($hint !== '' ? "\n        {$hint}" : '') . "\n";
}

function manager(array $vps): ScriptCapturingManager
{
    return new ScriptCapturingManager($vps);
}

echo "\nClone: root domain into its own subdomain\n";
$m = manager($vps);
$m->cloneSite('example.com', 'demo1.example.com');
$script = $m->script;

check(
    'no direct source -> target replacement',
    !str_contains($script, "wp search-replace 'example.com' 'demo1.example.com'"),
    'A direct replacement re-matches what the URL pass wrote: demo1.demo1.example.com'
);
check(
    'rewrites through an intermediate placeholder',
    preg_match("/wp search-replace 'example\.com' 'saulclone[a-f0-9]+\.invalid'/", $script) === 1
        && preg_match("/wp search-replace 'saulclone[a-f0-9]+\.invalid' 'demo1\.example\.com'/", $script) === 1
);
check(
    'placeholder does not contain the source host',
    preg_match("/'(saulclone[a-f0-9]+\.invalid)'/", $script, $found) === 1
        && !str_contains($found[1], 'example.com')
);
check(
    'pins home and siteurl to the target',
    str_contains($script, "wp option update home 'https://demo1.example.com'")
        && str_contains($script, "wp option update siteurl 'https://demo1.example.com'")
);
check('leaves guid columns alone', substr_count($script, '--skip-columns=guid') >= 3);

echo "\nClone: database dump stays off the public web\n";
check(
    'dump is written under /tmp',
    preg_match("#wp db export '/tmp/saul-clone-[a-f0-9]+\.sql'#", $script) === 1
);
check(
    'dump is not written into the source webroot',
    !str_contains($script, '/www/wwwroot/example.com/saul-clone'),
    'A dump under the webroot is downloadable over HTTP while the clone runs'
);
check('dump is removed afterwards', preg_match("#rm -f '/tmp/saul-clone-[a-f0-9]+\.sql'#", $script) === 1);

echo "\nClone: unrelated target domain still behaves as before\n";
$m = manager($vps);
$m->cloneSite('old.com', 'new.net');
check('rewrites the host', str_contains($m->script, "'old.com'") && str_contains($m->script, "'new.net'"));
check('upgrades http to https', str_contains($m->script, "wp search-replace 'http://new.net' 'https://new.net'"));

echo "\nVhost template\n";
$vhost = new ReflectionMethod(WordPressManager::class, 'nginxVhost');
$vhost->setAccessible(true);
$m = manager($vps);

$plain = $vhost->invoke($m, 'demo1.example.com', '/www/wwwroot/demo1.example.com', false);
check('defaults to plain HTTP', str_contains($plain, 'listen 80;') && !str_contains($plain, 'listen 443'));

$secure = $vhost->invoke($m, 'demo1.example.com', '/www/wwwroot/demo1.example.com', true);
check(
    'serves 443 with the site certificate',
    str_contains($secure, 'listen 443 ssl;')
        && str_contains($secure, 'ssl_certificate /www/server/panel/vhost/cert/demo1.example.com/fullchain.pem')
);
check('redirects port 80 to HTTPS', str_contains($secure, 'return 301 https://$host$request_uri;'));
check(
    'nginx variables survive PHP interpolation',
    str_contains($secure, 'try_files $uri $uri/ /index.php?$args;')
        && str_contains($secure, '$document_root$fastcgi_script_name')
);
check("uses the VPS's PHP-FPM socket", str_contains($secure, 'unix:/tmp/php-cgi-84.sock'));

echo "\nOrigin certificate install\n";
$certPem = "-----BEGIN CERTIFICATE-----\nMIIBexample\n-----END CERTIFICATE-----";
$keyPem = "-----BEGIN PRIVATE KEY-----\nMIIEexample\n-----END PRIVATE KEY-----";
$m = manager($vps);
$m->installOriginCert('demo1.example.com', $certPem, $keyPem);
$script = $m->script;

check(
    'writes cert and key into the aaPanel cert dir',
    str_contains($script, "cat > '/www/server/panel/vhost/cert/demo1.example.com/fullchain.pem'")
        && str_contains($script, "cat > '/www/server/panel/vhost/cert/demo1.example.com/privkey.pem'")
);
check(
    'private key is not world-readable',
    str_contains($script, "chmod 600 '/www/server/panel/vhost/cert/demo1.example.com/privkey.pem'")
);
check('validates the config before reloading nginx', str_contains($script, 'nginx -t && ('));
check('switches the vhost to HTTPS', str_contains($script, 'listen 443 ssl;'));
check('bails out when the site has no webroot', str_contains($script, 'SITE_NOT_FOUND'));

$rejected = 0;
foreach ([['not a certificate', $keyPem], [$certPem, 'not a key']] as [$cert, $key]) {
    try {
        manager($vps)->installOriginCert('demo1.example.com', $cert, $key);
    } catch (InvalidArgumentException) {
        $rejected++;
    }
}
check('rejects malformed PEM input', $rejected === 2);

echo "\nArchive sources: library path and URL\n";
$library = WordPressManager::LIBRARY_DIR;

$m = manager($vps);
$m->createSiteFromTemplate('demo2.example.com', 'admin', 'pw', 'a@b.com', [], '', [], [], '', $library . '/site.wpress');
check('library .wpress is copied, not fetched', str_contains($m->script, "cp -f '{$library}/site.wpress'"));

$m = manager($vps);
$m->createSiteFromTemplate('demo3.example.com', 'admin', 'pw', 'a@b.com', [], '', [], [], '', 'https://cdn.example.com/site.wpress');
check('remote .wpress is downloaded with curl', str_contains($m->script, "curl -fsSL 'https://cdn.example.com/site.wpress'"));

$m = manager($vps);
$m->createSiteFromTemplate('demo4.example.com', 'admin', 'pw', 'a@b.com', [$library . '/theme.zip'], 'child', [], [], '', '');
check('library theme ZIP installs directly', str_contains($m->script, "wp theme install '{$library}/theme.zip'"));

$traversal = [
    '/etc/passwd',
    '/www/wwwroot/example.com/wp-config.php',
    $library . '/../../etc/shadow',
    'file:///etc/passwd',
];
$blocked = 0;
foreach ($traversal as $path) {
    try {
        manager($vps)->createSiteFromTemplate('demo5.example.com', 'admin', 'pw', 'a@b.com', [], '', [], [], '', $path);
    } catch (InvalidArgumentException) {
        $blocked++;
    }
}
check(
    'rejects archive paths outside the library dir',
    $blocked === count($traversal),
    'A path outside the library dir reached WP-CLI'
);

echo "\n" . str_repeat('-', 52) . "\n";
if ($failed === 0) {
    echo "{$passed} checks passed\n";
    exit(0);
}
echo "{$passed} passed, {$failed} failed\n";
exit(1);
