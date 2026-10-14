<?php
/**
 * Pro-owned settings (tracking exclusions). The tracking toggles themselves live in the free
 * plugin's Carbon Fields settings; these are stored separately so Pro does not modify them.
 */
namespace LPManagerPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    const OPTION     = 'lpmanager_pro_settings';
    const GROUP      = 'lpmanager_pro_settings_group';
    const PAGE_SLUG  = 'lp_pro_settings';
    const CAPABILITY = 'manage_options';

    const DEFAULTS = [
        'exclude_editors'       => true,
        'exclude_bots'          => true,
        'honor_privacy_signals' => true,
        'require_consent'       => false,
    ];

    /**
     * User-agent fragments for crawlers, monitors, previews, and automation. Most crawlers do
     * not run the tracking script, but headless browsers and link-preview fetchers can.
     */
    const BOT_PATTERN = '/bot|crawl|spider|slurp|mediapartners|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|facebookexternalhit|embedly|preview|python|curl|wget|httpclient|okhttp|java\/|go-http|phantomjs|selenium|puppeteer|playwright/i';

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_page' ], 30 );
        add_action( 'admin_init', [ __CLASS__, 'add_privacy_policy_content' ] );
        // Declares WP Consent API support so consent plugins know Pro respects the "statistics" category.
        add_filter( 'wp_consent_api_registered_' . plugin_basename( LP_MANAGER_PRO_PLUGIN_PATH . 'landing-page-manager-pro.php' ), '__return_true' );
    }

    /**
     * Whether tracking must wait for consent from the visitor (WP Consent API "statistics", or the
     * JavaScript API in tracking.js).
     */
    public static function requires_consent() {
        /**
         * Filters whether tracking waits for visitor consent.
         *
         * @param bool $require Value of the "Require consent" setting.
         */
        return (bool) apply_filters( 'lpmanager_pro_require_consent', self::get()['require_consent'] );
    }

    /** Global Privacy Control (Sec-GPC) or Do Not Track (DNT) header on the current request. */
    public static function has_privacy_signal() {
        $gpc = isset( $_SERVER['HTTP_SEC_GPC'] ) ? trim( (string) $_SERVER['HTTP_SEC_GPC'] ) : '';
        $dnt = isset( $_SERVER['HTTP_DNT'] ) ? trim( (string) $_SERVER['HTTP_DNT'] ) : '';
        return $gpc === '1' || $dnt === '1';
    }

    /** @return array{exclude_editors: bool, exclude_bots: bool} */
    public static function get() {
        $saved = get_option( self::OPTION, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }
        $settings = [];
        foreach ( self::DEFAULTS as $key => $default ) {
            $settings[ $key ] = array_key_exists( $key, $saved ) ? (bool) $saved[ $key ] : $default;
        }
        return $settings;
    }

    /**
     * Whether the current request comes from internal traffic or a bot and must not be tracked.
     */
    public static function is_excluded_request() {
        static $excluded = null;
        if ( $excluded !== null ) {
            return $excluded;
        }
        $settings = self::get();
        $result   = false;
        if ( $settings['exclude_editors'] && is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
            $result = true;
        }
        if ( ! $result && $settings['honor_privacy_signals'] && self::has_privacy_signal() ) {
            $result = true;
        }
        if ( ! $result && $settings['exclude_bots'] ) {
            $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
            $result     = self::is_bot_user_agent( $user_agent );
        }
        /**
         * Filters whether the current request is excluded from tracking (internal traffic, bots,
         * or a Global Privacy Control / Do Not Track signal, per Pro Settings).
         * Use it for custom rules such as office IP ranges.
         *
         * @param bool $result Whether the request is excluded.
         */
        $excluded = (bool) apply_filters( 'lpmanager_pro_exclude_request', $result );
        return $excluded;
    }

    public static function is_bot_user_agent( $user_agent ) {
        $user_agent = trim( (string) $user_agent );
        // Real browsers always send a user agent.
        return $user_agent === '' || (bool) preg_match( self::BOT_PATTERN, $user_agent );
    }

    public static function register() {
        register_setting( self::GROUP, self::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize' ],
            'default'           => self::DEFAULTS,
        ] );
    }

    /** Unchecked checkboxes are absent from the submission, so every known key is set explicitly. */
    public static function sanitize( $input ) {
        $input = is_array( $input ) ? $input : [];
        $clean = [];
        foreach ( array_keys( self::DEFAULTS ) as $key ) {
            $clean[ $key ] = ! empty( $input[ $key ] );
        }
        return $clean;
    }

    /** Suggested text for Settings > Privacy describing what Pro stores. */
    public static function add_privacy_policy_content() {
        if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
            return;
        }
        $content = '<p>' . esc_html__( 'Landing pages on this site use Landing Page Manager Pro to measure how pages perform. It records aggregate counts per page: page views, call and link clicks (with the link address and text), outbound link clicks, whether a call-to-action became visible, form starts and submissions (not what was typed), time on page, scroll depth, a coarse grid of mouse positions, and repeated rapid clicks.', 'landing-page-manager-pro' ) . '</p>'
            . '<p>' . esc_html__( 'It does not set cookies, store IP addresses or user agents, or record personal data, and the counts cannot be linked to an individual visitor. Visitors whose browser sends Global Privacy Control or Do Not Track are not tracked when the site honors those signals.', 'landing-page-manager-pro' ) . '</p>';
        wp_add_privacy_policy_content( __( 'Landing Page Manager Pro', 'landing-page-manager-pro' ), wp_kses_post( $content ) );
    }

    public static function add_page() {
        add_submenu_page(
            'lp_dashboard',
            __( 'Pro Settings', 'landing-page-manager-pro' ),
            __( 'Pro Settings', 'landing-page-manager-pro' ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }
        $settings = self::get();
        $fields   = [
            'exclude_editors' => [
                __( 'Exclude internal traffic', 'landing-page-manager-pro' ),
                __( 'Do not track logged-in users who can edit content (editors, administrators, and similar roles).', 'landing-page-manager-pro' ),
            ],
            'exclude_bots'    => [
                __( 'Exclude bots', 'landing-page-manager-pro' ),
                __( 'Do not track requests from known crawlers, uptime monitors, link-preview fetchers, and automation tools, or requests without a user agent.', 'landing-page-manager-pro' ),
            ],
            'honor_privacy_signals' => [
                __( 'Honor privacy signals', 'landing-page-manager-pro' ),
                __( 'Do not track visitors whose browser sends Global Privacy Control or Do Not Track.', 'landing-page-manager-pro' ),
            ],
            'require_consent' => [
                __( 'Require consent', 'landing-page-manager-pro' ),
                __( 'Do not track until the visitor consents to statistics, through a consent plugin that supports the WP Consent API or the JavaScript API described in the README. Tracking uses no cookies and stores no personal data, so this is off by default.', 'landing-page-manager-pro' ),
            ],
        ];
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Landing Page Manager Pro Settings', 'landing-page-manager-pro' ) . '</h1>';
        echo '<p>' . esc_html__( 'Which tracking features are on is set in the Landing Page Manager settings. These options control whose visits are tracked.', 'landing-page-manager-pro' ) . '</p>';
        echo '<form method="post" action="options.php">';
        settings_fields( self::GROUP );
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ( $fields as $key => $field ) {
            $id = 'lpmanager-pro-' . str_replace( '_', '-', $key );
            echo '<tr><th scope="row">' . esc_html( $field[0] ) . '</th><td>';
            echo '<label for="' . esc_attr( $id ) . '">';
            echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::OPTION . '[' . $key . ']' ) . '" value="1"' . checked( $settings[ $key ], true, false ) . ' /> ';
            echo esc_html( $field[1] );
            echo '</label></td></tr>';
        }
        echo '</tbody></table>';
        submit_button();
        echo '</form></div>';
    }
}
