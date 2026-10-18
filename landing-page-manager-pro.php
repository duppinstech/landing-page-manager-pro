<?php
/**
 * Plugin Name: Landing Page Manager Pro
 * Plugin URI: https://github.com/duppinstech/landing-page-manager-pro
 * Description: Page views, conversions, link clicks, engagement, heatmaps, rage clicks, outbound clicks, CTA views, and form tracking for Landing Page Manager, with bot and internal-traffic exclusion and privacy and consent controls.
 * Version: 1.0.6
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author: Duppins Technology
 * Author URI: https://duppinstech.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: landing-page-manager-pro
 * Requires Plugins: landing-page-manager
 * Update URI: https://github.com/duppinstech/landing-page-manager-pro
 *
 * Copyright (c) 2026 Odell Duppins (d/b/a Duppins Technology). Licensed under GPL-2.0-or-later; see LICENSE.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'LP_MANAGER_PRO_VERSION', '1.0.6' );

if ( ! defined( 'LP_MANAGER_PRO_PLUGIN_PATH' ) ) {
    define( 'LP_MANAGER_PRO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'LP_MANAGER_PRO_PLUGIN_URL' ) ) {
    define( 'LP_MANAGER_PRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once __DIR__ . '/includes/class-lpmanager-pro-github-updater.php';
require_once __DIR__ . '/includes/class-landing-page-manager-pro-plugin-info.php';

// Updates from GitHub Releases. Booted at load, before the dependency check, so WP-Cron and background
// auto-updates see releases. Disable with define( 'LPMANAGER_PRO_DISABLE_GITHUB_UPDATER', true ).
LPManager_Pro_GitHub_Updater::boot(
    [
        'file'    => __FILE__,
        'version' => LP_MANAGER_PRO_VERSION,
        'owner'   => 'duppinstech',
        'repo'    => 'landing-page-manager-pro',
        'prefix'  => 'lpmanager_pro',
    ]
);

// Plugins screen links and the View details modal. Landing Page Manager 1.0.13+ owns the Tracking
// screen (lp_tracking); older releases use Pro's own menu item.
$lpmanager_pro_settings_url = 'lp_pro_settings';
$lpmanager_pro_slots         = array();
if ( function_exists( 'lpmanager_pro_screen_slots' ) ) {
    $lpmanager_pro_slots = (array) lpmanager_pro_screen_slots();
    if ( ! empty( $lpmanager_pro_slots['tracking'] ) ) {
        $lpmanager_pro_settings_url = (string) $lpmanager_pro_slots['tracking'];
    }
}
LPManager_Pro_Plugin_Info::boot(
    [
        'file'         => __FILE__,
        'settings_url' => $lpmanager_pro_settings_url,
        'help_url'     => 'https://duppinstech.com/knowledge-base/landing-page-manager/#install',
        'support_url'  => 'mailto:support@duppinstech.com',
    ]
);
unset( $lpmanager_pro_settings_url, $lpmanager_pro_slots );

/**
 * Pro loader: only the real Pro plugin defines this. Prevents bypass via snippet.
 *
 * @return bool
 */
function lpmanager_pro_loaded() {
    return true;
}

/**
 * Tracking toggles owned by Pro (since 1.0.4), in the shape of Landing Page Manager's
 * lpmanager_tracking_options(), which delegates here when this function exists.
 *
 * @return array<string, bool> Empty when Pro is not loaded (the free plugin is missing).
 */
function lpmanager_pro_tracking_options() {
    return class_exists( 'LPManagerPro\Settings' ) ? \LPManagerPro\Settings::tracking_options() : [];
}

register_activation_hook( __FILE__, function () {
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if ( ! is_plugin_active( 'landing-page-manager/landing-page-manager.php' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__( 'Landing Page Manager Pro requires Landing Page Manager to be installed and active before activation.', 'landing-page-manager-pro' ),
            esc_html__( 'Plugin dependency missing', 'landing-page-manager-pro' ),
            [ 'back_link' => true ]
        );
    }
    // Reactivation must not overwrite tracking choices the admin has made since.
    if ( ! get_option( 'lpmanager_pro_defaults_applied' ) ) {
        update_option( 'lpmanager_pro_activation_pending', true );
    }
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'LPManager\Plugin' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Landing Page Manager Pro requires the free Landing Page Manager plugin to be installed and active.', 'landing-page-manager-pro' ) . '</p></div>';
        } );
        return;
    }
    define( 'LP_MANAGER_PRO_LOADED', true );

    $pro_path = plugin_dir_path( __FILE__ );
    require_once $pro_path . 'includes/Settings.php';
    require_once $pro_path . 'includes/Stats.php';
    require_once $pro_path . 'includes/Tracking_Manager.php';
    require_once $pro_path . 'includes/Pro_Analytics.php';
    require_once $pro_path . 'includes/Analytics_Page.php';

    LPManagerPro\Stats::init();
    LPManagerPro\Stats::maybe_install();
    LPManagerPro\Settings::init();
    LPManagerPro\Tracking_Manager::init();
    LPManagerPro\Pro_Analytics::init();
    LPManagerPro\Analytics_Page::init();

    lpmanager_pro_apply_activation_defaults();
}, 20 );

/**
 * On first activation, enable all tracking options so users get full features immediately.
 */
function lpmanager_pro_apply_activation_defaults() {
    if ( ! get_option( 'lpmanager_pro_activation_pending' ) ) {
        return;
    }
    // Attempt once: a failure that repeated on every request would keep overwriting the admin's choices.
    delete_option( 'lpmanager_pro_activation_pending' );
    // Pro owns the toggle rows (see Settings::TRACKING_TOGGLES), so this no longer waits for Carbon Fields.
    foreach ( LPManagerPro\Settings::TRACKING_TOGGLES as $key ) {
        LPManagerPro\Settings::set_tracking_option( $key, true );
    }
    update_option( 'lpmanager_pro_defaults_applied', true, false );
}
