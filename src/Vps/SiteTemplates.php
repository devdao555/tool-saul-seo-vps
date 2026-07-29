<?php

namespace App\Vps;

/**
 * Fingerprint presets for the "rebuild from template" flow. Each preset captures the theme +
 * plugin stack detected on a known source site, so the operator can 1-click fill the form
 * instead of retyping slugs. ZIP URLs are intentionally left empty — the operator supplies
 * their own license-holding copies; only the free wp.org slugs and the activate-theme slug
 * are pre-filled from the fingerprint.
 */
class SiteTemplates
{
    /**
     * @return array<string, array{label:string, activate_theme:string, plugin_slugs:string, theme_hint:string, plugin_zip_hint:string}>
     */
    public static function all(): array
    {
        return [
            'agency7' => [
                'label' => 'agency7.mauthemewp.com (Flatsome)',
                'activate_theme' => 'themeweb',
                'plugin_slugs' => 'woocommerce, contact-form-7, font-awesome-4-menus, builder-responsive-pricing-tables',
                'theme_hint' => 'flatsome (parent) + themeweb (child)',
                'plugin_zip_hint' => 'advanced-product-fields-for-woocommerce-pro, hostiko-domain-checker',
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
