<?php
/**
 * Pro-only: tracking, conversions, engagement. Loaded only when Pro plugin is active.
 */
namespace LPManagerPro;

class Tracking_Manager {

    /** Distinct links stored per page; recording endpoints are public, so storage must stay bounded. */
    const MAX_LINKS_PER_PAGE = 200;

    const MAX_HREF_LENGTH = 2048;

    const MAX_HEATMAP_CELLS_PER_REQUEST = 5000;

    /**
     * Interaction types recorded by lpmanager_record_interaction: tracking option that enables
     * each one, and the post meta counter it increments. Fixed keys keep storage bounded.
     */
    const INTERACTIONS = [
        'outbound_click' => [ 'option' => 'track_outbound_clicks', 'meta' => '_lpmanager_outbound_clicks' ],
        'cta_view'       => [ 'option' => 'track_cta_visibility', 'meta' => '_lpmanager_cta_views' ],
        'form_start'     => [ 'option' => 'track_form_interactions', 'meta' => '_lpmanager_form_starts' ],
        'form_submit'    => [ 'option' => 'track_form_interactions', 'meta' => '_lpmanager_form_submits' ],
    ];

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
        add_action( 'wp_ajax_lpmanager_record_interaction', [ __CLASS__, 'record_interaction' ] );
        add_action( 'wp_ajax_nopriv_lpmanager_record_interaction', [ __CLASS__, 'record_interaction' ] );
        add_action( 'wp_ajax_lpmanager_pro_session', [ __CLASS__, 'start_session' ] );
        add_action( 'wp_ajax_nopriv_lpmanager_pro_session', [ __CLASS__, 'start_session' ] );
        add_action( 'admin_menu', [ __CLASS__, 'maybe_add_conversions_submenu' ] );
    }

    /**
     * Server-side counting on template_redirect. Used only in 'server' mode; by default the
     * tracking script counts the view (see start_session) so full-page caches do not hide views.
     */
    public static function increment_page_view() {
        if ( ! function_exists( 'lpmanager_tracking_options' ) ) {
            return;
        }
        $opts = lpmanager_tracking_options();
        // Consent is only known in the browser, so server-side counting is skipped when it is required.
        if ( empty( $opts['track_page_views'] ) || self::get_view_counting_mode() !== 'server' || Settings::requires_consent() || Settings::is_excluded_request() ) {
            return;
        }
        if ( ! is_singular( 'landing_page' ) ) {
            return;
        }
        $post_id = get_the_ID();
        if ( ! self::is_trackable_post( $post_id ) ) {
            return;
        }
        if ( self::should_count_page_view( ! is_preview(), $post_id ) ) {
            self::record_page_view( $post_id );
        }
    }

    /**
     * Called by the tracking script on page load. Returns a fresh nonce (the one printed into a
     * cached page may have expired) and, in 'client' mode, counts the page view. admin-ajax
     * responses are sent with no-cache headers, so this request is never served from a page cache.
     */
    public static function start_session() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        if ( Settings::is_excluded_request() ) {
            // Tells the script to stop sending events for this page view.
            wp_send_json_success( [ 'track' => false, 'counted' => false ] );
        }
        $opts    = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        $counted = false;
        if ( ! empty( $opts['track_page_views'] )
            && ! empty( $_POST['count_view'] )
            && self::get_view_counting_mode() === 'client'
            && self::should_count_page_view( true, $post_id )
        ) {
            self::record_page_view( $post_id );
            $counted = true;
        }
        wp_send_json_success( [
            'track'   => true,
            'nonce'   => wp_create_nonce( 'lpmanager_nonce' ),
            'counted' => $counted,
        ] );
    }

    /**
     * 'client' (default): the tracking script counts views, so cached pages are counted and
     * visitors without JavaScript (mostly bots) are not. 'server': count on template_redirect.
     */
    private static function get_view_counting_mode() {
        /**
         * Filters how landing page views are counted.
         *
         * @param string $mode 'client' or 'server'.
         */
        $mode = apply_filters( 'lpmanager_pro_page_view_counting', 'client' );
        return $mode === 'server' ? 'server' : 'client';
    }

    private static function should_count_page_view( $count, $post_id ) {
        /**
         * Filters whether a landing page view is counted. In 'client' mode this runs during the
         * tracking script's AJAX request, with the visitor's login state available.
         *
         * @param bool $count   Whether to count the view. Previews are excluded by default.
         * @param int  $post_id Landing page ID.
         */
        return (bool) apply_filters( 'lpmanager_pro_should_count_page_view', $count, $post_id );
    }

    private static function record_page_view( $post_id ) {
        $views = (int) get_post_meta( $post_id, '_lpmanager_page_views', true );
        update_post_meta( $post_id, '_lpmanager_page_views', $views + 1 );

        $today = gmdate( 'Y-m-d' );
        $daily = get_option( 'lpmanager_daily_visits', [] );
        if ( ! is_array( $daily ) ) {
            $daily = [];
        }
        $daily[ $today ] = isset( $daily[ $today ] ) ? (int) $daily[ $today ] + 1 : 1;
        self::save_tracking_option( 'lpmanager_daily_visits', $daily );
    }

    public static function enqueue_tracking_scripts() {
        if ( ! function_exists( 'lpmanager_tracking_options' ) ) {
            return;
        }
        $opts = lpmanager_tracking_options();
        $needs_script = ! empty( $opts['track_page_views'] )
            || ! empty( $opts['track_link_clicks'] )
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
        // Pro ships its own script so tracking changes do not depend on free-plugin releases.
        wp_enqueue_script(
            'lpmanager-tracking',
            LP_MANAGER_PRO_PLUGIN_URL . 'assets/js/tracking.js',
            [],
            LP_MANAGER_PRO_VERSION,
            true
        );
        wp_localize_script( 'lpmanager-tracking', 'lpmanagerTracking', [
            'ajaxurl'                    => admin_url( 'admin-ajax.php', 'relative' ),
            // Fallback only: the script requests a fresh nonce on load, since this one may come from a cached page.
            'nonce'                      => wp_create_nonce( 'lpmanager_nonce' ),
            'postId'                     => $post_id,
            // Previews are never served from a page cache, so excluding them here is reliable.
            'countView'                  => ! empty( $opts['track_page_views'] ) && self::get_view_counting_mode() === 'client' && ! is_preview(),
            'requireConsent'             => Settings::requires_consent(),
            'honorPrivacySignals'        => Settings::get()['honor_privacy_signals'],
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
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $conversions = (int) get_post_meta( $post_id, '_lpmanager_conversions', true );
        update_post_meta( $post_id, '_lpmanager_conversions', $conversions + 1 );

        $today = gmdate( 'Y-m-d' );
        $daily = get_option( 'lpmanager_daily_conversions', [] );
        if ( ! is_array( $daily ) ) {
            $daily = [];
        }
        $daily[ $today ] = isset( $daily[ $today ] ) ? (int) $daily[ $today ] + 1 : 1;
        self::save_tracking_option( 'lpmanager_daily_conversions', $daily );

        wp_send_json_success( 'Conversion recorded.' );
    }

    public static function record_click() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['track_link_clicks'] ) ) {
            wp_send_json_error( 'Link click tracking disabled.' );
        }
        check_ajax_referer( 'lpmanager_nonce', 'nonce' );
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $href    = isset( $_POST['href'] ) && is_string( $_POST['href'] ) ? esc_url_raw( substr( wp_unslash( $_POST['href'] ), 0, self::MAX_HREF_LENGTH ) ) : '';
        $text    = isset( $_POST['text'] ) ? sanitize_text_field( wp_unslash( $_POST['text'] ) ) : '';
        if ( ! self::is_trackable_post( $post_id ) ) {
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
        // Past the cap, new links still count toward the page total but get no per-link row.
        if ( isset( $stored[ $post_id ][ $key ] ) || count( $stored[ $post_id ] ) < self::MAX_LINKS_PER_PAGE ) {
            if ( ! isset( $stored[ $post_id ][ $key ] ) ) {
                $stored[ $post_id ][ $key ] = [ 'count' => 0, 'href' => $href, 'label' => mb_substr( $text, 0, 100 ) ];
            }
            $stored[ $post_id ][ $key ]['count'] = (int) $stored[ $post_id ][ $key ]['count'] + 1;
            self::save_tracking_option( 'lpmanager_link_clicks', $stored );
        }

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
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $stored = get_option( 'lpmanager_engagement', [] );
        if ( ! is_array( $stored ) ) {
            $stored = [];
        }
        if ( ! isset( $stored[ $post_id ] ) || ! is_array( $stored[ $post_id ] ) ) {
            $stored[ $post_id ] = [ 'time_sum' => 0, 'time_count' => 0, 'scroll_25' => 0, 'scroll_50' => 0, 'scroll_75' => 0, 'scroll_100' => 0 ];
        }
        $changed = false;
        if ( ! empty( $opts['track_time_on_page'] ) ) {
            $seconds = isset( $_POST['seconds'] ) ? absint( $_POST['seconds'] ) : 0;
            if ( $seconds > 0 && $seconds < 86400 ) {
                $stored[ $post_id ]['time_sum']   = (int) $stored[ $post_id ]['time_sum'] + $seconds;
                $stored[ $post_id ]['time_count'] = (int) $stored[ $post_id ]['time_count'] + 1;
                $changed = true;
            }
        }
        if ( ! empty( $opts['track_scroll_depth'] ) ) {
            $depth = isset( $_POST['scroll_depth'] ) ? absint( $_POST['scroll_depth'] ) : 0;
            if ( in_array( $depth, [ 25, 50, 75, 100 ], true ) ) {
                $key = 'scroll_' . $depth;
                $stored[ $post_id ][ $key ] = (int) ( $stored[ $post_id ][ $key ] ?? 0 ) + 1;
                $changed = true;
            }
        }
        // CTA-visibility and form-interaction pings carry no payload; skip the write for them.
        if ( $changed ) {
            self::save_tracking_option( 'lpmanager_engagement', $stored );
        }
        wp_send_json_success( 'Engagement recorded.' );
    }

    public static function record_heatmap() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['track_heatmap'] ) ) {
            wp_send_json_error( 'Heatmap tracking disabled.' );
        }
        check_ajax_referer( 'lpmanager_nonce', 'nonce' );
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $cells_raw = isset( $_POST['cells'] ) ? wp_unslash( $_POST['cells'] ) : '';
        if ( is_string( $cells_raw ) ) {
            $cells_raw = json_decode( $cells_raw, true );
        }
        $cells = is_array( $cells_raw ) ? array_map( 'absint', array_slice( $cells_raw, 0, self::MAX_HEATMAP_CELLS_PER_REQUEST ) ) : [];
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
        self::save_tracking_option( 'lpmanager_heatmap', $stored );
        wp_send_json_success( 'Heatmap recorded.' );
    }

    public static function record_rage_click() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['track_rage_clicks'] ) ) {
            wp_send_json_error( 'Rage click tracking disabled.' );
        }
        check_ajax_referer( 'lpmanager_nonce', 'nonce' );
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $count = (int) get_post_meta( $post_id, '_lpmanager_rage_clicks', true );
        update_post_meta( $post_id, '_lpmanager_rage_clicks', $count + 1 );
        wp_send_json_success( 'Rage click recorded.' );
    }

    public static function record_interaction() {
        $type = isset( $_POST['type'] ) && is_string( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
        if ( ! isset( self::INTERACTIONS[ $type ] ) ) {
            wp_send_json_error( 'Invalid interaction type.' );
        }
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts[ self::INTERACTIONS[ $type ]['option'] ] ) ) {
            wp_send_json_error( 'Interaction tracking disabled.' );
        }
        check_ajax_referer( 'lpmanager_nonce', 'nonce' );
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $meta_key = self::INTERACTIONS[ $type ]['meta'];
        $count    = (int) get_post_meta( $post_id, $meta_key, true );
        update_post_meta( $post_id, $meta_key, $count + 1 );
        wp_send_json_success( 'Interaction recorded.' );
    }

    /**
     * Interaction counts for a page, keyed by interaction type.
     *
     * @return array<string,int>
     */
    public static function get_interaction_counts( $post_id ) {
        $counts = [];
        foreach ( self::INTERACTIONS as $type => $def ) {
            $counts[ $type ] = (int) get_post_meta( $post_id, $def['meta'], true );
        }
        return $counts;
    }

    /** Excluded requests get a success response so the script does not retry or log errors. */
    private static function exit_if_excluded() {
        if ( Settings::is_excluded_request() ) {
            wp_send_json_success( 'Excluded.' );
        }
    }

    /** Only published landing pages may receive tracking data from the public endpoints. */
    private static function is_trackable_post( $post_id ) {
        return $post_id
            && get_post_type( $post_id ) === 'landing_page'
            && get_post_status( $post_id ) === 'publish';
    }

    /** Tracking options grow with traffic, so keep them out of the autoloaded options. */
    private static function save_tracking_option( $name, $value ) {
        update_option( $name, $value, false );
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
            echo '<div class="wrap"><h1>' . esc_html__( 'Conversions', 'landing-page-manager-pro' ) . '</h1><p>' . esc_html__( 'Conversion tracking is currently disabled. Enable it in settings to view analytics.', 'landing-page-manager-pro' ) . '</p></div>';
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Conversions', 'landing-page-manager-pro' ) . '</h1>';
        echo '<p>' . esc_html__( 'This table shows phone link click conversions for each landing page.', 'landing-page-manager-pro' ) . '</p>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Landing Page', 'landing-page-manager-pro' ) . '</th>';
        echo '<th>' . esc_html__( 'Visits', 'landing-page-manager-pro' ) . '</th>';
        echo '<th>' . esc_html__( 'Conversions', 'landing-page-manager-pro' ) . '</th>';
        echo '<th>' . esc_html__( 'Conversion Rate', 'landing-page-manager-pro' ) . '</th>';
        echo '</tr></thead><tbody>';
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
        self::render_interactions_table( $pages, $opts );
        echo '</div>';
    }

    /** Outbound clicks, CTA views, and form starts/submits; only enabled types get columns. */
    private static function render_interactions_table( array $pages, array $opts ) {
        $columns = [];
        if ( ! empty( $opts['track_outbound_clicks'] ) ) {
            $columns['outbound_click'] = __( 'Outbound Clicks', 'landing-page-manager-pro' );
        }
        if ( ! empty( $opts['track_cta_visibility'] ) ) {
            $columns['cta_view'] = __( 'CTA Views', 'landing-page-manager-pro' );
        }
        if ( ! empty( $opts['track_form_interactions'] ) ) {
            $columns['form_start']  = __( 'Form Starts', 'landing-page-manager-pro' );
            $columns['form_submit'] = __( 'Form Submits', 'landing-page-manager-pro' );
        }
        if ( ! $columns ) {
            return;
        }
        echo '<h2>' . esc_html__( 'Interactions', 'landing-page-manager-pro' ) . '</h2>';
        echo '<p>' . esc_html__( 'CTA views count page views in which a call-to-action became visible. Form starts count the first field focused in each form per page view.', 'landing-page-manager-pro' ) . '</p>';
        echo '<table class="widefat fixed striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Landing Page', 'landing-page-manager-pro' ) . '</th>';
        foreach ( $columns as $label ) {
            echo '<th>' . esc_html( $label ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $pages as $page ) {
            $counts = self::get_interaction_counts( $page->ID );
            echo '<tr><td><a href="' . esc_url( get_edit_post_link( $page->ID ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a></td>';
            foreach ( array_keys( $columns ) as $type ) {
                echo '<td>' . esc_html( $counts[ $type ] ) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public static function maybe_add_conversions_submenu() {
        $opts = function_exists( 'lpmanager_tracking_options' ) ? lpmanager_tracking_options() : [];
        if ( empty( $opts['enable_conversion_tracking'] ) ) {
            return;
        }
        add_submenu_page(
            'lp_dashboard',
            __( 'Conversions', 'landing-page-manager-pro' ),
            __( 'Conversions', 'landing-page-manager-pro' ),
            'manage_options',
            'lp_conversions',
            [ __CLASS__, 'render_conversions_page' ]
        );
    }
}

// Pre-1.0.3 name, which sat in the free plugin's namespace; kept so external references still resolve.
if ( ! class_exists( 'LPManager\admin\Tracking_Manager', false ) ) {
    class_alias( __NAMESPACE__ . '\Tracking_Manager', 'LPManager\admin\Tracking_Manager' );
}
