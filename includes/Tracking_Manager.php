<?php
/**
 * Pro-only: tracking, conversions, engagement. Loaded only when Pro plugin is active.
 */
namespace LPManager\admin;

class Tracking_Manager {

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'increment_page_view' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_tracking_scripts' ] );
        add_action( 'wp_ajax_lpmanager_record_conversion', [ __CLASS__, 'record_conversion' ] );
        add_action( 'wp_ajax_nopriv_lpmanager_record_conversion', [ __CLASS__, 'record_conversion' ] );
        add_action( 'wp_ajax_lpmanager_record_click', [ __CLASS__, 'record_click' ] );
        add_action( 'wp_ajax_nopriv_lpmanager_record_click', [ __CLASS__, 'record_click' ] );
        add_action( 'wp_ajax_lpmanager_record_engagement', [ __CLASS__, 'record_engagement' ] );
        add_action( 'wp_ajax_nopriv_lpmanager_record_engagement', [ __CLASS__, 'record_engagement' ] );
        add_action( 'wp_ajax_lpmanager_record_heatmap', [ __CLASS__, 'record_heatmap' ] );
        add_action( 'wp_ajax_nopriv_lpmanager_record_heatmap', [ __CLASS__, 'record_heatmap' ] );
        add_action( 'wp_ajax_lpmanager_record_rage_click', [ __CLASS__, 'record_rage_click' ] );
        add_action( 'wp_ajax_nopriv_lpmanager_record_rage_click', [ __CLASS__, 'record_rage_click' ] );
        add_action( 'admin_menu', [ __CLASS__, 'maybe_add_conversions_submenu' ] );
    }

    public static function increment_page_view() {
        if ( ! function_exists( 'lpmanager_tracking_options' ) ) {
            return;
        }
        $opts = lpmanager_tracking_options();
        if ( empty( $opts['track_page_views'] ) ) {
            return;
        }
        if ( ! is_singular( 'landing_page' ) ) {
            return;
        }
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            return;
        }
        $views = (int) get_post_meta( $post_id, '_lpmanager_page_views', true );
        update_post_meta( $post_id, '_lpmanager_page_views', $views + 1 );

        $today = gmdate( 'Y-m-d' );
        $daily = get_option( 'lpmanager_daily_visits', [] );
        if ( ! is_array( $daily ) ) {
            $daily = [];
        }
        $daily[ $today ] = isset( $daily[ $today ] ) ? (int) $daily[ $today ] + 1 : 1;
        update_option( 'lpmanager_daily_visits', $daily );
    }

    public static function enqueue_tracking_scripts() {
        if ( ! function_exists( 'lpmanager_tracking_options' ) ) {
            return;
        }
        $opts = lpmanager_tracking_options();
        $needs_script = ! empty( $opts['track_link_clicks'] )
            || ! empty( $opts['enable_conversion_tracking'] )
            || ! empty( $opts['track_time_on_page'] )
            || ! empty( $opts['track_scroll_depth'] )
            || ! empty( $opts['track_heatmap'] )
            || ! empty( $opts['track_rage_clicks'] )
            || ! empty( $opts['track_outbound_clicks'] )
            || ! empty( $opts['track_cta_visibility'] )
            || ! empty( $opts['track_form_interactions'] );
        if ( ! $needs_script ) {
            return;
        }
        if ( ! is_singular( 'landing_page' ) ) {
            return;
        }
        $post_id = get_the_ID();
        $plugin_url = defined( 'LP_MANAGER_PLUGIN_URL' ) ? LP_MANAGER_PLUGIN_URL : '';
        if ( ! $plugin_url ) {
            return;
        }
        wp_enqueue_script(
            'lpmanager-tracking',
            $plugin_url . 'assets/js/tracking.js',
            [ 'jquery' ],
            '1.2',
            true
        );
        wp_localize_script( 'lpmanager-tracking', 'lpmanagerTracking', [
            'ajaxurl'                    => admin_url( 'admin-ajax.php' ),
            'nonce'                      => wp_create_nonce( 'lpmanager_nonce' ),
            'postId'                     => $post_id,
            'trackLinkClicks'            => ! empty( $opts['track_link_clicks'] ),
            'trackConversions'          => ! empty( $opts['enable_conversion_tracking'] ),
            'trackTimeOnPage'            => ! empty( $opts['track_time_on_page'] ),
            'trackScrollDepth'           => ! empty( $opts['track_scroll_depth'] ),
            'trackHeatmap'              => ! empty( $opts['track_heatmap'] ),
            'trackRageClicks'            => ! empty( $opts['track_rage_clicks'] ),
            'trackOutboundClicks'        => ! empty( $opts['track_outbound_clicks'] ),
            'trackCtaVisibility'        => ! empty( $opts['track_cta_visibility'] ),
            'trackFormInteractions'      => ! empty( $opts['track_form_interactions'] ),
        ] );
    }

    public static function record_conversion() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['enable_conversion_tracking'] ) ) {
            wp_send_json_error( 'Conversion tracking disabled.' );
        }
        check_ajax_referer( 'lpmanager_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id ) {
            wp_send_json_error( 'Invalid post ID.' );
        }
        $conversions = (int) get_post_meta( $post_id, '_lpmanager_conversions', true );
        update_post_meta( $post_id, '_lpmanager_conversions', $conversions + 1 );

        $today = gmdate( 'Y-m-d' );
        $daily = get_option( 'lpmanager_daily_conversions', [] );
        if ( ! is_array( $daily ) ) {
            $daily = [];
        }
        $daily[ $today ] = isset( $daily[ $today ] ) ? (int) $daily[ $today ] + 1 : 1;
        update_option( 'lpmanager_daily_conversions', $daily );

        wp_send_json_success( 'Conversion recorded.' );
    }

    public static function record_click() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['track_link_clicks'] ) ) {
            wp_send_json_error( 'Link click tracking disabled.' );
        }
        check_ajax_referer( 'lpmanager_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $href    = isset( $_POST['href'] ) ? esc_url_raw( wp_unslash( $_POST['href'] ) ) : '';
        $text    = isset( $_POST['text'] ) ? sanitize_text_field( wp_unslash( $_POST['text'] ) ) : '';
        if ( ! $post_id || get_post_type( $post_id ) !== 'landing_page' ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $key = self::normalize_click_key( $href, $text );
        $stored = get_option( 'lpmanager_link_clicks', [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }
        if ( ! isset( $stored[ $post_id ] ) || ! is_array( $stored[ $post_id ] ) ) {
            $stored[ $post_id ] = [];
        }
        if ( ! isset( $stored[ $post_id ][ $key ] ) ) {
            $stored[ $post_id ][ $key ] = [ 'count' => 0, 'href' => $href, 'label' => mb_substr( $text, 0, 100 ) ];
        }
        $stored[ $post_id ][ $key ]['count'] = (int) $stored[ $post_id ][ $key ]['count'] + 1;
        update_option( 'lpmanager_link_clicks', $stored );

        $total_clicks = (int) get_post_meta( $post_id, '_lpmanager_total_clicks', true );
        update_post_meta( $post_id, '_lpmanager_total_clicks', $total_clicks + 1 );

        wp_send_json_success( 'Click recorded.' );
    }

    public static function record_engagement() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['track_time_on_page'] ) && empty( $opts['track_scroll_depth'] ) ) {
            wp_send_json_error( 'Engagement tracking disabled.' );
        }
        check_ajax_referer( 'lpmanager_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || get_post_type( $post_id ) !== 'landing_page' ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $stored = get_option( 'lpmanager_engagement', [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }
        if ( ! isset( $stored[ $post_id ] ) || ! is_array( $stored[ $post_id ] ) ) {
            $stored[ $post_id ] = [ 'time_sum' => 0, 'time_count' => 0, 'scroll_25' => 0, 'scroll_50' => 0, 'scroll_75' => 0, 'scroll_100' => 0 ];
        }
        if ( ! empty( $opts['track_time_on_page'] ) ) {
            $seconds = isset( $_POST['seconds'] ) ? absint( $_POST['seconds'] ) : 0;
            if ( $seconds > 0 && $seconds < 86400 ) {
                $stored[ $post_id ]['time_sum']   = (int) $stored[ $post_id ]['time_sum'] + $seconds;
                $stored[ $post_id ]['time_count'] = (int) $stored[ $post_id ]['time_count'] + 1;
            }
        }
        if ( ! empty( $opts['track_scroll_depth'] ) ) {
            $depth = isset( $_POST['scroll_depth'] ) ? absint( $_POST['scroll_depth'] ) : 0;
            if ( in_array( $depth, [ 25, 50, 75, 100 ], true ) ) {
                $key = 'scroll_' . $depth;
                $stored[ $post_id ][ $key ] = (int) ( $stored[ $post_id ][ $key ] ?? 0 ) + 1;
            }
        }
        update_option( 'lpmanager_engagement', $stored );
        wp_send_json_success( 'Engagement recorded.' );
    }

    public static function record_heatmap() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['track_heatmap'] ) ) {
            wp_send_json_error( 'Heatmap tracking disabled.' );
        }
        check_ajax_referer( 'lpmanager_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || get_post_type( $post_id ) !== 'landing_page' ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $cells_raw = isset( $_POST['cells'] ) ? wp_unslash( $_POST['cells'] ) : '';
        if ( is_string( $cells_raw ) ) {
            $cells_raw = json_decode( $cells_raw, true );
        }
        $cells = is_array( $cells_raw ) ? array_map( 'absint', $cells_raw ) : [];
        if ( empty( $cells ) ) {
            wp_send_json_success( 'No data.' );
        }
        $stored = get_option( 'lpmanager_heatmap', [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }
        if ( ! isset( $stored[ $post_id ] ) || ! is_array( $stored[ $post_id ] ) ) {
            $stored[ $post_id ] = array_fill( 0, 100, 0 );
        }
        foreach ( $cells as $idx ) {
            if ( $idx >= 0 && $idx < 100 ) {
                $stored[ $post_id ][ $idx ] = (int) ( $stored[ $post_id ][ $idx ] ?? 0 ) + 1;
            }
        }
        update_option( 'lpmanager_heatmap', $stored );
        wp_send_json_success( 'Heatmap recorded.' );
    }

    public static function record_rage_click() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['track_rage_clicks'] ) ) {
            wp_send_json_error( 'Rage click tracking disabled.' );
        }
        check_ajax_referer( 'lpmanager_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || get_post_type( $post_id ) !== 'landing_page' ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $count = (int) get_post_meta( $post_id, '_lpmanager_rage_clicks', true );
        update_post_meta( $post_id, '_lpmanager_rage_clicks', $count + 1 );
        wp_send_json_success( 'Rage click recorded.' );
    }

    private static function normalize_click_key( $href, $text ) {
        $norm = preg_replace( '#^tel:#i', 'tel:', $href );
        $norm = trim( $norm ) ?: $href;
        $label = trim( $text ) ?: $norm;
        return md5( $norm . '|' . $label );
    }

    public static function render_conversions_page() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['enable_conversion_tracking'] ) ) {
            echo '<div class="wrap"><h1>Conversions</h1><p>Conversion tracking is currently disabled. Enable it in settings to view analytics.</p></div>';
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>Conversions</h1>';
        echo '<p>This table shows phone link click conversions for each landing page.</p>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>Landing Page</th><th>Visits</th><th>Conversions</th><th>Conversion Rate</th></tr></thead><tbody>';
        $pages = get_posts( [ 'post_type' => 'landing_page', 'posts_per_page' => -1 ] );
        foreach ( $pages as $page ) {
            $visits = (int) get_post_meta( $page->ID, '_lpmanager_page_views', true );
            $conversions = (int) get_post_meta( $page->ID, '_lpmanager_conversions', true );
            $rate = $visits > 0 ? round( ( $conversions / $visits ) * 100, 1 ) . '%' : '0%';
            $edit_link = get_edit_post_link( $page->ID );
            echo '<tr>';
            echo '<td><a href="' . esc_url( $edit_link ) . '">' . esc_html( get_the_title( $page ) ) . '</a></td>';
            echo '<td>' . esc_html( $visits ) . '</td>';
            echo '<td>' . esc_html( $conversions ) . '</td>';
            echo '<td>' . esc_html( $rate ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public static function maybe_add_conversions_submenu() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['enable_conversion_tracking'] ) ) {
            return;
        }
        add_submenu_page(
            'lp_dashboard',
            __( 'Conversions', 'landing-page-manager' ),
            __( 'Conversions', 'landing-page-manager' ),
            'manage_options',
            'lp_conversions',
            [ __CLASS__, 'render_conversions_page' ]
        );
    }
}
