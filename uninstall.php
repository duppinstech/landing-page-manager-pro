<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * The GitHub updater cache is always removed. Pro's own settings are removed only on sites where
 * "Delete data on uninstall" is on; analytics data shared with Landing Page Manager is always kept.
 *
 * Copyright (c) 2026 Odell Duppins (d/b/a Duppins Technology). Licensed under GPL-2.0-or-later; see LICENSE.md.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// GitHub updater cache. Both keys are network-wide on multisite, so one call each covers every site.
delete_site_option( 'lpmanager_pro_github_release' );
// Release cache of the updater used before 1.0.3.
delete_site_transient( 'lpmanager_pro_github_release' );

require_once __DIR__ . '/includes/Settings.php';

if ( is_multisite() ) {
    foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $lpmanager_pro_site_id ) {
        switch_to_blog( $lpmanager_pro_site_id );
        \LPManagerPro\Settings::delete_site_data_if_opted_in();
        restore_current_blog();
    }
} else {
    \LPManagerPro\Settings::delete_site_data_if_opted_in();
}
