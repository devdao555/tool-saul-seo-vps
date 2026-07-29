<?php

require __DIR__ . '/../src/bootstrap.php';

use App\Auth\Auth;
use App\Controllers\WordPressController;
use App\Support\Csrf;
use App\Support\Flash;
use App\Vps\SiteTemplates;
use App\Vps\VpsRepository;

Auth::requireLogin('/login.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyRequestOrFail();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'create':
                $results = WordPressController::createBlankSites(
                    (int) ($_POST['vps_id'] ?? 0),
                    (string) ($_POST['domains'] ?? ''),
                    (string) ($_POST['admin_user'] ?? 'admin'),
                    (string) ($_POST['admin_password'] ?? ''),
                    (string) ($_POST['admin_email'] ?? '')
                );
                Flash::set('create_results', $results);
                break;

            case 'clone':
                $results = WordPressController::cloneSites(
                    (int) ($_POST['target_vps_id'] ?? 0),
                    (string) ($_POST['mapping'] ?? ''),
                    !empty($_POST['close_indexing'])
                );
                Flash::set('clone_results', $results);
                break;

            case 'rebuild':
                $results = WordPressController::createFromTemplate(
                    (int) ($_POST['rebuild_vps_id'] ?? 0),
                    (string) ($_POST['rebuild_domain'] ?? ''),
                    (string) ($_POST['rebuild_admin_user'] ?? 'admin'),
                    (string) ($_POST['rebuild_admin_password'] ?? ''),
                    (string) ($_POST['rebuild_admin_email'] ?? ''),
                    (string) ($_POST['theme_zips'] ?? ''),
                    (string) ($_POST['activate_theme'] ?? ''),
                    (string) ($_POST['plugin_slugs'] ?? ''),
                    (string) ($_POST['plugin_zips'] ?? ''),
                    (string) ($_POST['demo_xml_url'] ?? ''),
                    (string) ($_POST['wpress_url'] ?? '')
                );
                Flash::set('rebuild_results', $results);
                break;

            case 'ai_writer':
                $results = WordPressController::deployAiWriter(
                    (string) ($_POST['ai_domains'] ?? ''),
                    [
                        'provider'      => (string) ($_POST['ai_provider'] ?? 'openai'),
                        'api_key'       => (string) ($_POST['ai_api_key'] ?? ''),
                        'model'         => (string) ($_POST['ai_model'] ?? ''),
                        'language'      => (string) ($_POST['ai_language'] ?? 'Tiếng Việt'),
                        'posts_per_day' => (int) ($_POST['ai_posts_per_day'] ?? 2),
                        'post_status'   => (string) ($_POST['ai_post_status'] ?? 'publish'),
                        'category'      => (string) ($_POST['ai_category'] ?? ''),
                        'prompt_extra'  => (string) ($_POST['ai_prompt_extra'] ?? ''),
                        'image_provider' => (string) ($_POST['ai_image_provider'] ?? 'none'),
                        'image_api_key'  => (string) ($_POST['ai_image_api_key'] ?? ''),
                    ],
                    (string) ($_POST['ai_keywords'] ?? ''),
                    !empty($_POST['ai_run_now'])
                );
                Flash::set('ai_results', $results);
                break;
        }
    } catch (\Throwable $e) {
        Flash::set('error', $e->getMessage());
    }

    header('Location: /wordpress.php');
    exit;
}

$vpsList = VpsRepository::all();
$createResults = Flash::pull('create_results');
$cloneResults = Flash::pull('clone_results');
$rebuildResults = Flash::pull('rebuild_results');
$aiResults = Flash::pull('ai_results');
$siteTemplates = SiteTemplates::all();
$error = Flash::pull('error');

$pageTitle = 'Cấu hình website';
$pageSub = 'Add WP trắng hoặc clone WordPress';
$activeNav = 'wordpress';

ob_start();
require __DIR__ . '/../views/wordpress.php';
$content = ob_get_clean();

require __DIR__ . '/../views/layout.php';
