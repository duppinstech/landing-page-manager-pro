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
// F6 storage: tables exist and hold this run's events; the old options are no longer written.
lpm_check( 'storage tables installed', (int) get_option( \LPManagerPro\Stats::DB_VERSION_OPTION ) === \LPManagerPro\Stats::DB_VERSION && $wpdb->get_var( 'SHOW TABLES LIKE \'' . \LPManagerPro\Stats::stats_table() . '\'' ) );
$links = \LPManagerPro\Stats::links_for_post( $lpm_pid );
$row   = current( $links );
lpm_check( 'link href stored without query or fragment', $row && $row['href'] === 'https://example.org/out', $row ? $row['href'] : 'none' );
$stats = \LPManagerPro\Stats::totals( [ $lpm_pid ] )[ $lpm_pid ] ?? [];
lpm_check( 'time over one hour dropped', (int) ( $stats['time_count'] ?? 0 ) === 1 && (int) ( $stats['time_sum'] ?? 0 ) === 30 );
$heat = 0;
for ( $cell = 0; $cell < 100; $cell++ ) {
	$heat += (int) ( $stats[ \LPManagerPro\Stats::heat_metric( $cell ) ] ?? 0 );
}
lpm_check( 'heatmap cells recorded', $heat === 5, (string) $heat );
lpm_check( 'daily series from the stats table', ( \LPManagerPro\Stats::daily_series( 'views' )[ gmdate( 'Y-m-d' ) ] ?? 0 ) >= 1 && ( \LPManagerPro\Stats::daily_series( 'conversions' )[ gmdate( 'Y-m-d' ) ] ?? 0 ) === 1 );
lpm_check( 'old tracking options not written', get_option( 'lpmanager_link_clicks' ) === false && get_option( 'lpmanager_engagement' ) === false && get_option( 'lpmanager_heatmap' ) === false );

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

// Migration of pre-1.0.5 options: copied once, merged with live data, safe to repeat.
update_option( 'lpmanager_link_clicks', [ $lpm_pid => [ 'a' => [ 'count' => 4, 'href' => 'https://example.org/out?utm_source=old', 'label' => $row['label'] ] ] ], false );
update_option( 'lpmanager_engagement', [ $lpm_pid => [ 'time_sum' => 100, 'time_count' => 2, 'scroll_25' => 3, 'scroll_50' => 0, 'scroll_75' => 0, 'scroll_100' => 1 ] ], false );
update_option( 'lpmanager_heatmap', [ $lpm_pid => array_fill( 0, 100, 1 ), 999 => [ 5 => 3 ] ], false );
update_option( 'lpmanager_daily_visits', [ '2026-01-02' => 7 ], false );
delete_option( \LPManagerPro\Stats::MIGRATED_OPTION );
\LPManagerPro\Stats::migrate();
\LPManagerPro\Stats::migrate();
$migrated = \LPManagerPro\Stats::totals( [ $lpm_pid ] )[ $lpm_pid ];
$merged   = \LPManagerPro\Stats::links_for_post( $lpm_pid );
lpm_check( 'migration merges legacy link clicks with live ones', count( $merged ) === 1 && $merged[0]['count'] === 5, json_encode( $merged ) );
lpm_check( 'migration copies engagement once', $migrated['time_sum'] === 130 && $migrated['time_count'] === 3 && $migrated['scroll_25'] === 3 && $migrated['scroll_100'] === 1 );
// Cells 0 and 99 also had one live sample each; cell 2 is legacy only.
lpm_check( 'migration copies the heatmap once', $migrated['heat_00'] === 2 && $migrated['heat_99'] === 2 && $migrated['heat_02'] === 1 );
lpm_check( 'migration keeps sparse heatmap cell positions', ( \LPManagerPro\Stats::totals( [ 999 ] )[999]['heat_05'] ?? 0 ) === 3 && ! isset( \LPManagerPro\Stats::totals( [ 999 ] )[999]['heat_00'] ) );
lpm_check( 'migration cleans the old link option in place', strpos( wp_json_encode( get_option( 'lpmanager_link_clicks' ) ), 'utm_source' ) === false );
update_option( \LPManagerPro\Stats::MIGRATION_LOCK_OPTION, time(), false );
lpm_check( 'migration waits for a fresh lock', \LPManagerPro\Stats::migrate() === false );
update_option( \LPManagerPro\Stats::MIGRATION_LOCK_OPTION, time() - 601, false );
lpm_check( 'migration takes over a stale lock', \LPManagerPro\Stats::migrate() === true && get_option( \LPManagerPro\Stats::MIGRATION_LOCK_OPTION ) === false );
// Pre-existing duplicate counter rows: only the first row is incremented, so no event is counted twice.
add_post_meta( $lpm_pid, '_lpmanager_dup_probe', 3 );
add_post_meta( $lpm_pid, '_lpmanager_dup_probe', 4 );
\LPManagerPro\Stats::increment_meta( $lpm_pid, '_lpmanager_dup_probe' );
lpm_check( 'counter update touches only the first row', get_post_meta( $lpm_pid, '_lpmanager_dup_probe' ) == [ '4', '4' ], wp_json_encode( get_post_meta( $lpm_pid, '_lpmanager_dup_probe' ) ) );
delete_post_meta( $lpm_pid, '_lpmanager_dup_probe' );
lpm_check( 'migration keeps the old daily series', ( \LPManagerPro\Stats::daily_series( 'views' )['2026-01-02'] ?? 0 ) === 7 );
lpm_check( 'migration marked done', (bool) get_option( \LPManagerPro\Stats::MIGRATED_OPTION ) && get_option( 'lpmanager_pro_migration_lock' ) === false );

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
