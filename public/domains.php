<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Cloudflare\CfAccountRepository;
use App\Controllers\DnsController;
use App\Controllers\DomainController;
use App\Controllers\NamecheapController;
use App\Domains\DomainRepository;
use App\Support\Csrf;
use App\Support\Flash;

Auth::requireLogin('/login.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_zone':
                $results = DomainController::addDomains(
                    (int) ($_POST['cf_account_id'] ?? 0),
                    (string) ($_POST['domains'] ?? ''),
                    !empty($_POST['jump_start'])
                );
                Flash::set('add_zone_results', $results);
                break;

            case 'check_ns':
                $results = DomainController::checkNs((string) ($_POST['domains_check'] ?? ''));
                Flash::set('check_ns_results', $results);
                break;

            case 'push_dns':
                $results = DnsController::pushDns((string) ($_POST['dns_lines'] ?? ''), !empty($_POST['proxied']));
                Flash::set('push_dns_results', $results);
                break;

            case 'delete_dns':
                $results = DnsController::deleteDns((string) ($_POST['domains_delete_dns'] ?? ''));
                Flash::set('delete_dns_results', $results);
                break;

            case 'push_ns':
                $results = NamecheapController::pushNameservers((string) ($_POST['domains_push_ns'] ?? ''));
                Flash::set('push_ns_results', $results);
                break;

            case 'purge_cache':
                $results = DnsController::purgeCache((string) ($_POST['domains_purge_cache'] ?? ''));
                Flash::set('purge_cache_results', $results);
                break;

            case 'toggle_proxy':
                $results = DnsController::toggleProxy(
                    (string) ($_POST['domains_toggle_proxy'] ?? ''),
                    ($_POST['proxy_state'] ?? 'on') === 'on'
                );
                Flash::set('toggle_proxy_results', $results);
                break;

            case 'scan_dns_health':
                $results = DnsController::scanDnsHealth();
                Flash::set('scan_dns_results', $results);
                break;
        }
    } catch (\Throwable $e) {
        Flash::set('error', $e->getMessage());
    }

    header('Location: /domains.php');
    exit;
}

$cfAccounts = CfAccountRepository::all();
$domains = DomainRepository::all();

$addZoneResults = Flash::pull('add_zone_results');
$checkNsResults = Flash::pull('check_ns_results');
$pushDnsResults = Flash::pull('push_dns_results');
$deleteDnsResults = Flash::pull('delete_dns_results');
$pushNsResults = Flash::pull('push_ns_results');
$purgeCacheResults = Flash::pull('purge_cache_results');
$toggleProxyResults = Flash::pull('toggle_proxy_results');
$scanDnsResults = Flash::pull('scan_dns_results');
$error = Flash::pull('error');

$pageTitle = 'Tên miền & DNS';
$pageSub = 'Thêm domain & trả NS, check trạng thái, push/xoá DNS record';
$activeNav = 'domains';

ob_start();
require __DIR__ . '/../views/domains.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
