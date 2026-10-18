<?php
/**
 * Pro-only: tracking, conversions, engagement. Loaded only when Pro plugin is active.
 *
 * Copyright (c) 2026 Odell Duppins (d/b/a Duppins Technology). Licensed under GPL-2.0-or-later; see LICENSE.md.
 */
namespace LPManagerPro;

class Tracking_Manager {

    /** Distinct links stored per page; recording endpoints are public, so storage must stay bounded. */
    const MAX_LINKS_PER_PAGE = 200;

    /** Stored link addresses are capped and kept without query strings or fragments (see clean_href). */
    const MAX_HREF_LENGTH = 512;

    /** Stored link labels are capped; the click key uses the same capped label so rows can be re-keyed. */
    const MAX_LABEL_LENGTH = 100;

    /** Nonce action for the public tracking endpoints; separate from the free plugin's lpmanager_nonce. */
    const NONCE_ACTION = 'lpmanager_pro_tracking';

    /** Script handle; distinct from the free plugin's former lpmanager-tracking handle (see dequeue_legacy_script). */
    const SCRIPT_HANDLE = 'lpmanager-pro-tracking';

    /** One page view's heatmap; tracking.js stops sampling at the same number. */
    const MAX_HEATMAP_CELLS_PER_REQUEST = 600;

    /** Longer time-on-page values are dropped rather than skewing the average. */
    const MAX_ENGAGEMENT_SECONDS = 3600;

    /**
     * Signed session tokens (S1). lpmanager_pro_session issues one per page view; every recording
     * endpoint requires it, so post IDs cannot be forged and the shared nonce alone is not enough.
     * Nothing is stored: the token is verified by its signature.
     */
    const SESSION_TTL = 3600;

    /** Per-session limits, enforced only with a persistent object cache (never in the database). */
    const RATE_LIMIT_GROUP = 'lpmanager_pro_rl';

    const MAX_REPEAT_EVENTS_PER_SESSION = 10;

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
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'dequeue_legacy_script' ], PHP_INT_MAX );
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
        // admin_menu, not admin_init: WordPress denies unknown admin pages while building the menu, before admin_init.
        add_action( 'admin_menu', [ __CLASS__, 'redirect_legacy_screens' ], 1 );
    }

    /**
     * Server-side counting on template_redirect. Used only in 'server' mode; by default the
     * tracking script counts the view (see start_session) so full-page caches do not hide views.
     */
    public static function increment_page_view() {
        $opts = Settings::tracking_options();
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
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- This endpoint issues the nonce and session token; it only reads a post ID and a flag.
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        if ( Settings::is_excluded_request() ) {
            // Tells the script to stop sending events for this page view.
            wp_send_json_success( [ 'track' => false, 'counted' => false ] );
        }
        $opts    = Settings::tracking_options();
        $counted = false;
        if ( ! empty( $opts['track_page_views'] )
            && ! empty( $_POST['count_view'] )
            && self::get_view_counting_mode() === 'client'
            && self::should_count_page_view( true, $post_id )
        ) {
            self::record_page_view( $post_id );
            $counted = true;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        wp_send_json_success( [
            'track'   => true,
            'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
            'session' => self::create_session_token( $post_id ),
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
        Stats::increment_meta( $post_id, '_lpmanager_page_views' );
        Stats::add( $post_id, [ 'views' => 1 ] );
    }

    public static function enqueue_tracking_scripts() {
        $opts = Settings::tracking_options();
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
            self::SCRIPT_HANDLE,
            LP_MANAGER_PRO_PLUGIN_URL . 'assets/js/tracking.js',
            [],
            LP_MANAGER_PRO_VERSION,
            true
        );
        wp_localize_script( self::SCRIPT_HANDLE, 'lpmanagerTracking', [
            'ajaxurl'                    => admin_url( 'admin-ajax.php', 'relative' ),
            // Fallback only: the script requests a fresh nonce on load, since this one may come from a cached page.
            'nonce'                      => wp_create_nonce( self::NONCE_ACTION ),
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

    /**
     * Older free-plugin releases enqueued their own tracking script as lpmanager-tracking. It has no
     * consent gate and would double-count, so only Pro's script may run on landing pages.
     */
    public static function dequeue_legacy_script() {
        if ( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
            wp_dequeue_script( 'lpmanager-tracking' );
        }
    }

    public static function record_conversion() {
        $opts = Settings::tracking_options();
        if ( empty( $opts['enable_conversion_tracking'] ) ) {
            wp_send_json_error( 'Conversion tracking disabled.' );
        }
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        self::exit_if_limited( self::require_session( $post_id ), 'conversion', self::MAX_REPEAT_EVENTS_PER_SESSION );
        Stats::increment_meta( $post_id, '_lpmanager_conversions' );
        Stats::add( $post_id, [ 'conversions' => 1 ] );

        wp_send_json_success( 'Conversion recorded.' );
    }

    public static function record_click() {
        $opts = Settings::tracking_options();
        if ( empty( $opts['track_link_clicks'] ) ) {
            wp_send_json_error( 'Link click tracking disabled.' );
        }
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $href    = isset( $_POST['href'] ) && is_string( $_POST['href'] ) ? self::clean_href( wp_unslash( $_POST['href'] ) ) : '';
        $text    = isset( $_POST['text'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['text'] ) ), 0, self::MAX_LABEL_LENGTH ) : '';
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        self::exit_if_limited( self::require_session( $post_id ), 'click', self::MAX_REPEAT_EVENTS_PER_SESSION );
        // Past the per-page link cap, a click still counts toward the page total but gets no link row.
        Stats::add_link_click( $post_id, self::normalize_click_key( $href, $text ), $href, $text );
        Stats::increment_meta( $post_id, '_lpmanager_total_clicks' );
        Stats::add( $post_id, [ 'clicks' => 1 ] );

        wp_send_json_success( 'Click recorded.' );
    }

    public static function record_engagement() {
        $opts = Settings::tracking_options();
        if ( empty( $opts['track_time_on_page'] ) && empty( $opts['track_scroll_depth'] ) ) {
            wp_send_json_error( 'Engagement tracking disabled.' );
        }
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        $session = self::require_session( $post_id );
        $amounts = [];
        if ( ! empty( $opts['track_time_on_page'] ) ) {
            $seconds = isset( $_POST['seconds'] ) ? absint( $_POST['seconds'] ) : 0;
            if ( $seconds > 0 && $seconds <= self::MAX_ENGAGEMENT_SECONDS && self::allow_event( $session, 'time', 1 ) ) {
                $amounts['time_sum']   = $seconds;
                $amounts['time_count'] = 1;
            }
        }
        if ( ! empty( $opts['track_scroll_depth'] ) ) {
            $depth = isset( $_POST['scroll_depth'] ) ? absint( $_POST['scroll_depth'] ) : 0;
            if ( in_array( $depth, [ 25, 50, 75, 100 ], true ) && self::allow_event( $session, 'scroll_' . $depth, 1 ) ) {
                $amounts[ 'scroll_' . $depth ] = 1;
            }
        }
        Stats::add( $post_id, $amounts );
        wp_send_json_success( 'Engagement recorded.' );
    }

    public static function record_heatmap() {
        $opts = Settings::tracking_options();
        if ( empty( $opts['track_heatmap'] ) ) {
            wp_send_json_error( 'Heatmap tracking disabled.' );
        }
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
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
        self::exit_if_limited( self::require_session( $post_id ), 'heatmap', 1 );
        $amounts = [];
        foreach ( $cells as $idx ) {
            if ( $idx < 100 ) {
                $metric             = Stats::heat_metric( $idx );
                $amounts[ $metric ] = ( $amounts[ $metric ] ?? 0 ) + 1;
            }
        }
        // All-time bucket: a heatmap per day would add 100 rows per page per day.
        Stats::add( $post_id, $amounts, Stats::ALL_TIME_DAY );
        wp_send_json_success( 'Heatmap recorded.' );
    }

    public static function record_rage_click() {
        $opts = Settings::tracking_options();
        if ( empty( $opts['track_rage_clicks'] ) ) {
            wp_send_json_error( 'Rage click tracking disabled.' );
        }
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        self::exit_if_limited( self::require_session( $post_id ), 'rage_click', self::MAX_REPEAT_EVENTS_PER_SESSION );
        Stats::increment_meta( $post_id, '_lpmanager_rage_clicks' );
        Stats::add( $post_id, [ 'rage_clicks' => 1 ] );
        wp_send_json_success( 'Rage click recorded.' );
    }

    public static function record_interaction() {
        $type = isset( $_POST['type'] ) && is_string( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
        if ( ! isset( self::INTERACTIONS[ $type ] ) ) {
            wp_send_json_error( 'Invalid interaction type.' );
        }
        $opts = Settings::tracking_options();
        if ( empty( $opts[ self::INTERACTIONS[ $type ]['option'] ] ) ) {
            wp_send_json_error( 'Interaction tracking disabled.' );
        }
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );
        self::exit_if_excluded();
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::is_trackable_post( $post_id ) ) {
            wp_send_json_error( 'Invalid post.' );
        }
        // A CTA view happens once per page view; the other interactions can repeat.
        self::exit_if_limited( self::require_session( $post_id ), $type, $type === 'cta_view' ? 1 : self::MAX_REPEAT_EVENTS_PER_SESSION );
        Stats::increment_meta( $post_id, self::INTERACTIONS[ $type ]['meta'] );
        Stats::add( $post_id, [ $type => 1 ] );
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

    /**
     * Token format: "post_id.issued_at.session_id.signature". The session ID is random and lives
     * only in the browser's memory (and briefly in a persistent object cache, if the site has one).
     */
    public static function create_session_token( $post_id, $now = null ) {
        $issued_at  = $now === null ? time() : (int) $now;
        $session_id = bin2hex( random_bytes( 8 ) );
        return implode( '.', [ (int) $post_id, $issued_at, $session_id, self::sign_session( (int) $post_id, $issued_at, $session_id ) ] );
    }

    /**
     * @return string|false The session ID when the token is valid for this page and not expired.
     */
    public static function verify_session_token( $token, $post_id, $now = null ) {
        $now   = $now === null ? time() : (int) $now;
        $parts = is_string( $token ) ? explode( '.', $token ) : [];
        if ( count( $parts ) !== 4 || ! ctype_digit( $parts[0] ) || ! ctype_digit( $parts[1] ) || ! preg_match( '/^[0-9a-f]{16}$/', $parts[2] ) ) {
            return false;
        }
        $issued_at = (int) $parts[1];
        if ( (int) $parts[0] !== (int) $post_id || $issued_at > $now + 60 || $now - $issued_at > self::SESSION_TTL ) {
            return false;
        }
        return hash_equals( self::sign_session( (int) $post_id, $issued_at, $parts[2] ), $parts[3] ) ? $parts[2] : false;
    }

    private static function sign_session( $post_id, $issued_at, $session_id ) {
        return wp_hash( 'lpmanager_pro_session|' . $post_id . '|' . $issued_at . '|' . $session_id, 'nonce' );
    }

    /** Ends the request unless it carries a valid session token for this page. */
    private static function require_session( $post_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Every caller runs check_ajax_referer() first.
        $token      = isset( $_POST['session'] ) && is_string( $_POST['session'] ) ? sanitize_text_field( wp_unslash( $_POST['session'] ) ) : '';
        $session_id = self::verify_session_token( $token, $post_id );
        if ( ! $session_id ) {
            wp_send_json_error( 'Invalid session.' );
        }
        return $session_id;
    }

    /**
     * Whether this session may record the event again. Enforced only with a persistent object
     * cache; without one the limit is skipped rather than stored in the database.
     */
    public static function allow_event( $session_id, $event, $limit ) {
        if ( ! wp_using_ext_object_cache() ) {
            return true;
        }
        $key = $session_id . ':' . $event;
        if ( $limit === 1 ) {
            return (bool) wp_cache_add( $key, 1, self::RATE_LIMIT_GROUP, self::SESSION_TTL );
        }
        wp_cache_add( $key, 0, self::RATE_LIMIT_GROUP, self::SESSION_TTL );
        $count = wp_cache_incr( $key, 1, self::RATE_LIMIT_GROUP );
        return $count !== false && $count <= $limit;
    }

    /** Over-limit events get a success response so the script does not retry. */
    private static function exit_if_limited( $session_id, $event, $limit ) {
        if ( ! self::allow_event( $session_id, $event, $limit ) ) {
            wp_send_json_success( 'Limit reached.' );
        }
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

    /**
     * Drops the query string and fragment, which can carry visitor tokens or email addresses, and
     * caps the length so public callers cannot store large values.
     */
    public static function clean_href( $href ) {
        $href = esc_url_raw( preg_replace( '/[?#].*$/s', '', (string) $href ) );
        // Cut after escaping, which can lengthen the value by percent-encoding characters.
        return substr( $href, 0, self::MAX_HREF_LENGTH );
    }

    /**
     * Cleans each row's address with clean_href(), caps its label, and merges rows that now share
     * a key, summing their counts.
     *
     * @param array $stored Link rows keyed by post ID, then by click key.
     * @return array
     */
    public static function clean_link_rows( array $stored ) {
        $cleaned = [];
        foreach ( $stored as $post_id => $rows ) {
            if ( ! is_array( $rows ) ) {
                continue;
            }
            $cleaned[ $post_id ] = [];
            foreach ( $rows as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                $href  = self::clean_href( isset( $row['href'] ) ? $row['href'] : '' );
                $label = mb_substr( isset( $row['label'] ) ? (string) $row['label'] : '', 0, self::MAX_LABEL_LENGTH );
                $count = isset( $row['count'] ) ? (int) $row['count'] : 0;
                $key   = self::normalize_click_key( $href, $label );
                if ( isset( $cleaned[ $post_id ][ $key ] ) ) {
                    $cleaned[ $post_id ][ $key ]['count'] += $count;
                } else {
                    $cleaned[ $post_id ][ $key ] = [ 'count' => $count, 'href' => $href, 'label' => $label ];
                }
            }
        }
        return $cleaned;
    }

    private static function normalize_click_key( $href, $text ) {
        $norm = preg_replace( '#^tel:#i', 'tel:', $href );
        $norm = trim( $norm ) ?: $href;
        $label = trim( $text ) ?: $norm;
        return md5( $norm . '|' . $label );
    }

    /**
     * Old links and bookmarks land on the current screen instead of an "access denied" page: the
     * Conversions screen (folded into analytics in 1.0.4), and, with Landing Page Manager 1.0.13+, Pro's
     * own Pro Analytics and Pro Settings screens (now Landing Pages → Analytics and → Tracking). Hooked on
     * admin_menu, because WordPress denies unknown pages before admin_init.
     */
    public static function redirect_legacy_screens() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation; only redirects.
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        // Keep a bookmarked page detail view and its filters.
        $args = [];
        foreach ( [ 'page_id', 'client_id' ] as $key ) {
            if ( ! empty( $_GET[ $key ] ) ) {
                $args[ $key ] = absint( $_GET[ $key ] );
            }
        }
        if ( isset( $_GET['template'] ) && '' !== $_GET['template'] ) {
            $args['template'] = rawurlencode( sanitize_text_field( wp_unslash( $_GET['template'] ) ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $targets = [ 'lp_conversions' => Analytics_Page::url() ];
        if ( Analytics_Page::uses_core_screen() ) {
            $targets[ Analytics_Page::PAGE_SLUG ] = Analytics_Page::url( $args );
            $targets[ Settings::PAGE_SLUG ]       = add_query_arg( 'page', Settings::slug(), admin_url( 'admin.php' ) );
        }
        // Never redirect a screen to itself (if Landing Page Manager names no Analytics screen).
        if ( ! isset( $targets[ $page ] ) || 0 === strpos( $targets[ $page ], add_query_arg( 'page', $page, admin_url( 'admin.php' ) ) ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        wp_safe_redirect( $targets[ $page ] );
        exit;
    }
}

// Pre-1.0.3 name, which sat in the free plugin's namespace; kept so external references still resolve.
if ( ! class_exists( 'LPManager\admin\Tracking_Manager', false ) ) {
    class_alias( __NAMESPACE__ . '\Tracking_Manager', 'LPManager\admin\Tracking_Manager' );
}
