<?php
/**
 * Plugin Name: Landing Page Manager Pro
 * Plugin URI: https://github.com/oduppinsjr/landing-page-manager-pro
 * Description: Unlocks analytics, conversion tracking, and click-through rates for Landing Page Manager.
 * Version: 1.0.1
 * Author: Odell Duppins
 * Text Domain: landing-page-manager-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'LP_MANAGER_PRO_PLUGIN_PATH' ) ) {
    define( 'LP_MANAGER_PRO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'LP_MANAGER_PRO_PLUGIN_URL' ) ) {
    define( 'LP_MANAGER_PRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Pro loader: only the real Pro plugin defines this. Prevents bypass via snippet.
 *
 * @return bool
 */
function lpmanager_pro_loaded() {
    return true;
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
    update_option( 'lpmanager_pro_activation_pending', true );
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'LPManager\Plugin' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Landing Page Manager Pro requires the free Landing Page Manager plugin to be installed and active.', 'landing-page-manager-pro' ) . '</p></div>';
        } );
        return;
    }
    define( 'LP_MANAGER_PRO_LOADED', true );
    define( 'LP_MANAGER_PRO_VERSION', '1.0.1' );

    $pro_path = plugin_dir_path( __FILE__ );
    require_once $pro_path . 'includes/Update_Checker.php';
    require_once $pro_path . 'includes/Tracking_Manager.php';
    require_once $pro_path . 'includes/Pro_Analytics.php';

    LPManagerPro\Update_Checker::init();
    LPManager\admin\Tracking_Manager::init();
    LPManagerPro\Pro_Analytics::init();

    // On first activation, enable all tracking options so users get full features immediately.
    if ( get_option( 'lpmanager_pro_activation_pending' ) && function_exists( 'carbon_set_theme_option' ) ) {
        delete_option( 'lpmanager_pro_activation_pending' );
        $tracking_keys = [
            'lpmanager_track_page_views',
            'lpmanager_track_link_clicks',
            'lpmanager_track_time_on_page',
            'lpmanager_track_scroll_depth',
            'lpmanager_track_heatmap',
            'lpmanager_track_rage_clicks',
            'lpmanager_track_outbound_clicks',
            'lpmanager_track_cta_visibility',
            'lpmanager_track_form_interactions',
        ];
        foreach ( $tracking_keys as $key ) {
            carbon_set_theme_option( $key, true );
        }
    }
}, 20 );
