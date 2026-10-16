<?php
/**
 * Pro-owned settings (tracking exclusions). The tracking toggles themselves live in the free
 * plugin's Carbon Fields settings; these are stored separately so Pro does not modify them.
 *
 * Copyright (c) 2026 Odell Duppins (d/b/a Duppins Technology). Licensed under GPL-2.0-or-later; see LICENSE.md.
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
     * "bot" skips "Cubot", an Android phone brand whose model names appear in real browser user agents.
     */
    const BOT_PATTERN = '/(?<!cu)bot|crawl|spider|slurp|mediapartners|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|facebookexternalhit|embedly|preview|python|curl|wget|httpclient|okhttp|java\/|go-http|phantomjs|selenium|puppeteer|playwright/i';

    /**
     * Consent and cookie-banner plugins, keyed by plugin folder. Detection only informs the admin;
     * Pro reads consent solely through the WP Consent API or its JavaScript API.
     */
    const CONSENT_PLUGINS = [
        'complianz-gdpr'                 => 'Complianz',
        'complianz-gdpr-premium'         => 'Complianz',
        'cookie-law-info'                => 'CookieYes',
        'cookiebot'                      => 'Cookiebot',
        'cookie-notice'                  => 'Cookie Notice',
        'real-cookie-banner'             => 'Real Cookie Banner',
        'real-cookie-banner-pro'         => 'Real Cookie Banner',
        'borlabs-cookie'                 => 'Borlabs Cookie',
        'gdpr-cookie-compliance'         => 'GDPR Cookie Compliance',
        'iubenda-cookie-law-solution'    => 'iubenda',
        'uk-cookie-consent'              => 'Termly',
        'cookieadmin'                    => 'CookieAdmin',
    ];

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
            . '<p>' . esc_html__( 'It is designed to store only aggregate counts per page. It does not set cookies or store IP addresses or user agents. Link addresses are stored without query strings or fragments. The browser\'s user agent and privacy-signal headers are checked on each request to filter bots and honor opt-outs, and are not stored. Each page view gets a random identifier that stays in the browser\'s memory. It is not stored in cookies or local storage and is not linked to an IP address. If the site uses a persistent object cache, it may be kept there for up to one hour to limit repeat events, and then it expires. Visitors whose browser sends Global Privacy Control or Do Not Track are not tracked when the site honors those signals.', 'landing-page-manager-pro' ) . '</p>';
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
                __( 'Do not track logged-in users who can edit content (contributors, authors, editors, and administrators).', 'landing-page-manager-pro' ),
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
                __( 'Do not track until the visitor consents to statistics, through a consent plugin that supports the WP Consent API or a banner that calls window.lpmanagerPro.grantConsent(). Pro sets no cookies and is designed to store only aggregate counts. Whether consent is required depends on the laws that apply to your site.', 'landing-page-manager-pro' ),
            ],
        ];
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Landing Page Manager Pro Settings', 'landing-page-manager-pro' ) . '</h1>';
        settings_errors();
        echo '<p>' . esc_html__( 'Which tracking features are on is set in the Landing Page Manager settings. These options control whose visits are tracked.', 'landing-page-manager-pro' ) . '</p>';
        echo '<p>' . esc_html__( 'Excluded visits are not counted, so turning an option on lowers the counts from that point and turning it off raises them. Since version 1.0.3, previews, logged-in users who can edit content, bots, and visitors who send privacy signals are excluded by default, so counts can be lower than before the update. Earlier data is not changed.', 'landing-page-manager-pro' ) . '</p>';
        echo '<form method="post" action="options.php">';
        settings_fields( self::GROUP );
        // The filter can require consent even when the box is unchecked, so the notice uses the effective value.
        $require_consent = self::requires_consent();
        $consent_status  = self::get_consent_status( $require_consent );
        if ( $consent_status && $require_consent !== $settings['require_consent'] ) {
            $consent_status['message'] .= ' ' . __( 'This is set by the lpmanager_pro_require_consent filter, which overrides this checkbox.', 'landing-page-manager-pro' );
        }
        $status_id      = 'lpmanager-pro-consent-status';
        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ( $fields as $key => $field ) {
            $id        = 'lpmanager-pro-' . str_replace( '_', '-', $key );
            $describes = $key === 'require_consent' && $consent_status ? ' aria-describedby="' . esc_attr( $status_id ) . '"' : '';
            echo '<tr><th scope="row">' . esc_html( $field[0] ) . '</th><td>';
            echo '<label for="' . esc_attr( $id ) . '">';
            echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( self::OPTION . '[' . $key . ']' ) . '" value="1"' . checked( $settings[ $key ], true, false ) . $describes . ' /> ';
            echo esc_html( $field[1] );
            echo '</label>';
            if ( $describes ) {
                echo '<div id="' . esc_attr( $status_id ) . '" class="notice inline ' . esc_attr( $consent_status['class'] ) . '"><p>' . esc_html( $consent_status['message'] ) . '</p></div>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        submit_button();
        echo '</form></div>';
    }

    /** Names of active consent or cookie-banner plugins. */
    public static function detect_consent_plugins() {
        $active = (array) get_option( 'active_plugins', [] );
        if ( is_multisite() ) {
            $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
        }
        $found = [];
        foreach ( $active as $basename ) {
            $folder = strtok( (string) $basename, '/' );
            if ( isset( self::CONSENT_PLUGINS[ $folder ] ) ) {
                $found[ self::CONSENT_PLUGINS[ $folder ] ] = true;
            }
        }
        return array_keys( $found );
    }

    /** Whether the WP Consent API, which Pro uses to read consent, is active. */
    public static function consent_api_active() {
        return function_exists( 'wp_has_consent' );
    }

    /**
     * Compares "Require consent" with the consent tooling detected on the site. Detection is by
     * plugin folder only, so messages never claim that a detected plugin actually sends consent.
     *
     * @return array{class: string, message: string}|null Notice to show, or null when nothing needs saying.
     */
    public static function get_consent_status( $require_consent ) {
        $plugins = self::detect_consent_plugins();
        $api     = self::consent_api_active();
        $names   = wp_sprintf_l( '%l', $plugins );
        if ( ! $require_consent ) {
            if ( ! $plugins ) {
                return null;
            }
            return [
                'class'   => 'notice-warning',
                /* translators: %s: names of the detected consent plugins. */
                'message' => sprintf( __( 'Consent plugin detected: %s. "Require consent" is off, so Pro tracks visitors whatever they choose in the banner. Turn it on if the banner should control analytics.', 'landing-page-manager-pro' ), $names ),
            ];
        }
        if ( $api && $plugins ) {
            return [
                'class'   => 'notice-success',
                /* translators: %s: names of the detected consent plugins. */
                'message' => sprintf( __( 'Pro waits for statistics consent through the WP Consent API. Detected: %s. Confirm that its WP Consent API integration is turned on; if counts stay at zero, it is not sending consent.', 'landing-page-manager-pro' ), $names ),
            ];
        }
        if ( $api ) {
            return [
                'class'   => 'notice-warning',
                'message' => __( 'The WP Consent API is active, but no known consent plugin was detected. If no consent plugin is active, the WP Consent API reports consent for every visitor, so Pro tracks immediately. Activate a consent plugin that supports the WP Consent API.', 'landing-page-manager-pro' ),
            ];
        }
        return [
            'class'   => 'notice-warning',
            'message' => $plugins
                /* translators: %s: names of the detected consent plugins. */
                ? sprintf( __( 'Consent plugin detected: %s, but the WP Consent API is not active, so Pro cannot read its consent. Nothing is tracked until the WP Consent API is active or your banner calls window.lpmanagerPro.grantConsent().', 'landing-page-manager-pro' ), $names )
                : __( 'No consent plugin or WP Consent API detected. Nothing is tracked until a consent plugin that supports the WP Consent API is active or your banner calls window.lpmanagerPro.grantConsent().', 'landing-page-manager-pro' ),
        ];
    }
}
