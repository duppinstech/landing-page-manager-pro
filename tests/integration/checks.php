<?php
/**
 * Server-side integration checks, run with: wp eval-file /opt/lpm-integration/checks.php <post_id>
 * after run.sh has sent tracking requests. Prints one line per check and exits non-zero on failure.
 *
 * @package LPManager_Pro
 */

$lpm_pid    = (int) $args[0];
$lpm_failed = 0;

function lpm_check( $label, $ok, $detail = '' ) {
	global $lpm_failed;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $detail !== '' ? " ($detail)" : '' ) . "\n";
	if ( ! $ok ) {
		$lpm_failed++;
	}
}

global $wpdb;

// Counters written by run.sh's requests.
foreach ( [ '_lpmanager_page_views', '_lpmanager_conversions', '_lpmanager_total_clicks', '_lpmanager_rage_clicks', '_lpmanager_cta_views' ] as $key ) {
	lpm_check( "$key recorded", (int) get_post_meta( $lpm_pid, $key, true ) === 1, (string) get_post_meta( $lpm_pid, $key, true ) );
}
$links = get_option( 'lpmanager_link_clicks' )[ $lpm_pid ] ?? [];
$row   = current( $links );
lpm_check( 'link href stored without query or fragment', $row && $row['href'] === 'https://example.org/out', $row ? $row['href'] : 'none' );
$engagement = get_option( 'lpmanager_engagement' )[ $lpm_pid ] ?? [];
lpm_check( 'time over one hour dropped', (int) ( $engagement['time_count'] ?? 0 ) === 1 && (int) $engagement['time_sum'] === 30 );
lpm_check( 'heatmap cells recorded', array_sum( get_option( 'lpmanager_heatmap' )[ $lpm_pid ] ?? [] ) === 5 );
$autoloaded = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name IN ('lpmanager_link_clicks','lpmanager_engagement','lpmanager_heatmap','lpmanager_daily_visits','lpmanager_daily_conversions') AND autoload IN ('yes','on','auto-on')" );
lpm_check( 'tracking options not autoloaded', $autoloaded === 0, (string) $autoloaded );
lpm_check( 'P4 cleanup skipped on AJAX', get_option( 'lpmanager_pro_link_clicks_cleaned' ) === false );

// REST permissions and toggle filtering.
wp_set_current_user( 0 );
lpm_check( 'REST rejects anonymous', rest_do_request( new WP_REST_Request( 'GET', '/lpmanager/v1/analytics-data' ) )->get_status() === 401 );
wp_set_current_user( 1 );
$response = rest_do_request( new WP_REST_Request( 'GET', '/lpmanager/v1/analytics-data' ) );
lpm_check( 'REST allows admin', $response->get_status() === 200 );
\LPManagerPro\Settings::set_tracking_option( 'track_form_interactions', false );
$data = rest_do_request( new WP_REST_Request( 'GET', '/lpmanager/v1/analytics-data' ) )->get_data();
\LPManagerPro\Settings::set_tracking_option( 'track_form_interactions', true );
$page_row = current( array_filter( $data['interactions'], function ( $r ) use ( $lpm_pid ) { return (int) $r['post_id'] === $lpm_pid; } ) );
lpm_check( 'REST omits disabled interaction types', $page_row && ! array_key_exists( 'form_start', $page_row ) && array_key_exists( 'cta_view', $page_row ) );

// P4 cleanup on an admin request.
$stored                         = get_option( 'lpmanager_link_clicks', [] );
$stored[ $lpm_pid ]['legacy']   = [ 'count' => 4, 'href' => 'https://example.org/out?utm_source=old', 'label' => $row['label'] ];
update_option( 'lpmanager_link_clicks', $stored, false );
\LPManagerPro\Tracking_Manager::maybe_clean_stored_link_clicks();
$after = get_option( 'lpmanager_link_clicks' )[ $lpm_pid ];
lpm_check( 'P4 cleanup merges legacy rows', count( $after ) === 1 && (int) current( $after )['count'] === 5 && get_option( 'lpmanager_pro_link_clicks_cleaned' ) );

// Admin screens.
ob_start();
\LPManagerPro\Settings::render_page();
$html = ob_get_clean();
lpm_check( 'Pro Settings renders', strpos( $html, 'Landing Page Manager Pro Settings' ) !== false && strpos( $html, 'delete_data_on_uninstall' ) !== false );
lpm_check( 'Pro Settings shows tracking toggles', substr_count( $html, 'name="_lpmanager_track_' ) === 9 && strpos( $html, 'What is tracked' ) !== false );
delete_option( '_lpmanager_track_scroll_depth' );
lpm_check( 'a never-saved toggle reads as on', lpmanager_pro_tracking_options()['track_scroll_depth'] === true );
\LPManagerPro\Settings::set_tracking_option( 'track_scroll_depth', true );
$lpm_editor = wp_insert_user( [ 'user_login' => 'lpm-editor', 'user_pass' => wp_generate_password(), 'role' => 'editor' ] );
wp_set_current_user( $lpm_editor );
ob_start();
\LPManagerPro\Analytics_Page::render_page();
lpm_check( 'Pro Analytics hidden from editors', trim( ob_get_clean() ) === '' );
wp_set_current_user( 1 );
lpm_check( 'tracking toggles stored in the shared rows', get_option( '_lpmanager_track_heatmap' ) === 'yes' && lpmanager_pro_tracking_options()['track_heatmap'] === true );
ob_start();
\LPManagerPro\Tracking_Manager::render_conversions_page();
$html = ob_get_clean();
lpm_check( 'Conversions page renders', strpos( $html, 'Conversion Rate' ) !== false && strpos( $html, 'Interactions' ) !== false );

// Pro Analytics screen (F15): overview and page detail.
ob_start();
\LPManagerPro\Analytics_Page::render_page();
$html = ob_get_clean();
lpm_check( 'Pro Analytics overview renders', strpos( $html, 'Pro Analytics' ) !== false && strpos( $html, 'Analytics by landing page' ) !== false && strpos( $html, 'page_id=' . $lpm_pid ) !== false );
$_GET['page_id'] = $lpm_pid;
ob_start();
\LPManagerPro\Analytics_Page::render_page();
$html = ob_get_clean();
unset( $_GET['page_id'] );
lpm_check( 'Pro Analytics detail shows heatmap and top links', strpos( $html, 'lpmanager-pro-heatmap' ) !== false && strpos( $html, 'https://example.org/out' ) !== false && strpos( $html, 'Scroll depth' ) !== false );

// Uninstall: kept by default, removed when opted in. Shared analytics always kept.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', 'landing-page-manager-pro/landing-page-manager-pro.php' );
}
update_option( 'lpmanager_pro_settings', \LPManagerPro\Settings::DEFAULTS );
include WP_PLUGIN_DIR . '/landing-page-manager-pro/uninstall.php';
lpm_check( 'uninstall keeps Pro settings by default', get_option( 'lpmanager_pro_settings' ) !== false && get_option( 'lpmanager_pro_defaults_applied' ) !== false );
update_option( 'lpmanager_pro_settings', array_merge( \LPManagerPro\Settings::DEFAULTS, [ 'delete_data_on_uninstall' => true ] ) );
include WP_PLUGIN_DIR . '/landing-page-manager-pro/uninstall.php';
lpm_check( 'uninstall removes Pro options when opted in', get_option( '_lpmanager_track_heatmap' ) === false && get_option( 'lpmanager_pro_settings' ) === false && get_option( 'lpmanager_pro_defaults_applied' ) === false && get_option( 'lpmanager_pro_link_clicks_cleaned' ) === false );
lpm_check( 'uninstall keeps shared analytics', get_option( 'lpmanager_link_clicks' ) !== false && (int) get_post_meta( $lpm_pid, '_lpmanager_page_views', true ) === 1 );

echo $lpm_failed ? "FAILED: $lpm_failed check(s)\n" : "All server-side checks passed.\n";
exit( $lpm_failed ? 1 : 0 );
