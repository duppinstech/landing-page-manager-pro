<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Only the GitHub updater cache is removed here. Tracking data and settings are left in place.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// GitHub updater cache. Both keys are network-wide on multisite, so one call each covers every site.
delete_site_option( 'lpmanager_pro_github_release' );
// Release cache of the updater used before 1.0.3.
delete_site_transient( 'lpmanager_pro_github_release' );
