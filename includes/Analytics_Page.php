<?php
/**
 * Analytics screen (Landing Pages → Analytics). Analytics are Pro-only (owner decisions 2026-10-16 and
 * 2026-10-17): Landing Page Manager 1.0.13+ provides the Analytics menu item, shows how to get Pro when
 * Pro is missing, and lets Pro render the screen through the lpmanager_render_analytics_page action.
 *
 * The screen is the analytics dashboard (stat cards, charts and report tabs, moved from Landing Page
 * Manager in 1.0.6, filled from the lpmanager/v1/analytics-data REST route). Selecting a page under
 * By Page opens its detail view on the same screen (?page_id=), rendered here on the server.
 *
 * With Landing Page Manager before 1.0.13, Pro keeps its own "Pro Analytics" menu item and the
 * server-rendered overview below, as in 1.0.4–1.0.5. Read-only; filters arrive by GET.
 *
 * Copyright (c) 2026 Odell Duppins (d/b/a Duppins Technology). Licensed under GPL-2.0-or-later; see LICENSE.md.
 */
namespace LPManagerPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Analytics_Page {

    /** Pro's own menu item, used only with Landing Page Manager before 1.0.13. */
    const PAGE_SLUG  = 'lp_pro_analytics';
    const CAPABILITY = 'manage_options';

    /** Chart.js for the dashboard charts, pinned (the free plugin loaded an unversioned copy). */
    const CHART_JS_URL     = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js';
    const CHART_JS_VERSION = '4.4.1';
    /** Subresource Integrity hash of CHART_JS_URL, so a changed CDN file is refused. */
    const CHART_JS_SRI = 'sha384-dug+JxfBvklEQdJ4AYuBBAIScUz0bVN73xpy273gcAwHjb3qI0fXmuYNaNfdyYJG';

    public static function init() {
        if ( self::uses_core_screen() ) {
            add_action( 'lpmanager_render_analytics_page', [ __CLASS__, 'render_screen' ] );
            add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
            add_filter( 'script_loader_tag', [ __CLASS__, 'chart_js_integrity' ], 10, 2 );
        } else {
            add_action( 'admin_menu', [ __CLASS__, 'add_page' ], 25 );
        }
    }

    /** Whether Landing Page Manager provides the Analytics menu item for Pro to fill (1.0.13+). */
    public static function uses_core_screen() {
        return function_exists( 'lpmanager_pro_screen_slots' );
    }

    /** The screen's admin page slug: Landing Page Manager's Analytics item, or Pro's own. */
    public static function slug() {
        if ( self::uses_core_screen() ) {
            $slots = lpmanager_pro_screen_slots();
            if ( ! empty( $slots['analytics'] ) ) {
                return (string) $slots['analytics'];
            }
        }
        return self::PAGE_SLUG;
    }

    /** URL of the screen, with optional extra query arguments. */
    public static function url( array $args = [] ) {
        return add_query_arg( array_merge( [ 'page' => self::slug() ], $args ), admin_url( 'admin.php' ) );
    }

    public static function add_page() {
        add_submenu_page(
            'lp_dashboard',
            __( 'Pro Analytics', 'landing-page-manager-pro' ),
            __( 'Pro Analytics', 'landing-page-manager-pro' ),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [ __CLASS__, 'render_page' ]
        );
    }

    /** Styles and scripts for the screen (Landing Page Manager 1.0.13+). */
    public static function enqueue_assets( $hook ) {
        if ( false === strpos( (string) $hook, self::slug() ) || ! current_user_can( self::CAPABILITY ) ) {
            return;
        }
        wp_enqueue_style( 'lpmanager-pro-analytics', LP_MANAGER_PRO_PLUGIN_URL . 'assets/css/analytics.css', [], LP_MANAGER_PRO_VERSION );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
        if ( ! empty( $_GET['page_id'] ) ) {
            return; // The detail view is rendered on the server.
        }
        wp_enqueue_script( 'lpmanager-pro-chartjs', self::CHART_JS_URL, [], self::CHART_JS_VERSION, true );
        wp_enqueue_script( 'lpmanager-pro-analytics', LP_MANAGER_PRO_PLUGIN_URL . 'assets/js/analytics.js', [ 'lpmanager-pro-chartjs' ], LP_MANAGER_PRO_VERSION, true );
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
        wp_localize_script( 'lpmanager-pro-analytics', 'lpmanagerAnalytics', [
            'apiUrl'         => rest_url( 'lpmanager/v1/analytics-data' ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'clientId'       => isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0,
            'templateFilter' => isset( $_GET['template'] ) ? sanitize_text_field( wp_unslash( $_GET['template'] ) ) : '',
            'editBase'       => admin_url( 'post.php?post=REPLACE_ID&action=edit' ),
            'adminUrl'       => admin_url( 'admin.php' ),
            'screenSlug'     => self::slug(),
            'i18n'           => self::strings(),
        ] );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    }

    /** Adds the Subresource Integrity attributes to the Chart.js script tag. */
    public static function chart_js_integrity( $tag, $handle ) {
        if ( 'lpmanager-pro-chartjs' !== $handle || false !== strpos( $tag, ' integrity=' ) ) {
            return $tag;
        }
        return str_replace( ' src=', ' integrity="' . esc_attr( self::CHART_JS_SRI ) . '" crossorigin="anonymous" src=', $tag );
    }

    /** The dashboard script's text, translated (keys match the English fallbacks in analytics.js). */
    private static function strings() {
        return [
            /* translators: %s: error message. */
            'failedToLoad'     => __( 'Failed to load analytics: %s', 'landing-page-manager-pro' ),
            'unknownError'     => __( 'Unknown error', 'landing-page-manager-pro' ),
            'allTemplates'     => __( 'All templates', 'landing-page-manager-pro' ),
            'totalViews'       => __( 'Total views', 'landing-page-manager-pro' ),
            'conversions'      => __( 'Conversions', 'landing-page-manager-pro' ),
            'linkClicks'       => __( 'Link clicks', 'landing-page-manager-pro' ),
            'conversionRate'   => __( 'Conversion rate (CTR)', 'landing-page-manager-pro' ),
            'pageViews'        => __( 'Page views', 'landing-page-manager-pro' ),
            'enableOverview'   => __( 'Turn on Page views or Link clicks under Landing Pages → Tracking to see analytics.', 'landing-page-manager-pro' ),
            'noData'           => __( 'No data', 'landing-page-manager-pro' ),
            'edit'             => __( 'Edit', 'landing-page-manager-pro' ),
            'linkLabel'        => __( 'Link / label', 'landing-page-manager-pro' ),
            'clicks'           => __( 'Clicks', 'landing-page-manager-pro' ),
            'noLinkData'       => __( 'No link click data yet.', 'landing-page-manager-pro' ),
            'enableEngagement' => __( 'Turn on Time on page or Scroll depth under Landing Pages → Tracking to see engagement data.', 'landing-page-manager-pro' ),
            'noEngagement'     => __( 'No engagement data yet.', 'landing-page-manager-pro' ),
            'page'             => __( 'Page', 'landing-page-manager-pro' ),
            'avgTime'          => __( 'Avg. time (s)', 'landing-page-manager-pro' ),
            'sessions'         => __( 'Sessions', 'landing-page-manager-pro' ),
            'enableHeatmap'    => __( 'Turn on Mouse-position heatmap under Landing Pages → Tracking to see data.', 'landing-page-manager-pro' ),
            'noHeatmap'        => __( 'No heatmap data yet. Visit landing pages with heatmap tracking turned on to see where visitors focus.', 'landing-page-manager-pro' ),
            'viewFullPage'     => __( 'View full page', 'landing-page-manager-pro' ),
            'close'            => __( 'Close', 'landing-page-manager-pro' ),
            'pagePreview'      => __( 'Page preview', 'landing-page-manager-pro' ),
            'clickToEnlarge'   => __( 'Click to view larger', 'landing-page-manager-pro' ),
            'heatmapGrid'      => __( 'Heatmap grid with counts', 'landing-page-manager-pro' ),
            'enableRage'       => __( 'Turn on Rage clicks under Landing Pages → Tracking to see data.', 'landing-page-manager-pro' ),
            'noRage'           => __( 'No rage clicks recorded yet.', 'landing-page-manager-pro' ),
            'rageCount'        => __( 'Rage click count', 'landing-page-manager-pro' ),
        ];
    }

    /** Landing Page Manager's Analytics screen (1.0.13+): the dashboard, or one page's detail view. */
    public static function render_screen() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
        $page_id = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;
        if ( $page_id ) {
            self::render_detail_screen( $page_id );
        } else {
            self::render_dashboard();
        }
    }

    /** One landing page's numbers, scroll depth, top links and heatmap grid. */
    private static function render_detail_screen( $page_id ) {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters; nothing is changed.
        $client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
        $template  = isset( $_GET['template'] ) ? sanitize_text_field( wp_unslash( $_GET['template'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $data = Pro_Analytics::build_analytics_data( $client_id, $template );
        $rows = self::page_rows( $data );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Analytics', 'landing-page-manager-pro' ) . '</h1>';
        if ( isset( $rows[ $page_id ] ) ) {
            // Its "All landing pages" link drops page_id, returning to the dashboard with the same filters.
            self::render_page_detail( $rows[ $page_id ], $data, $data['tracking_enabled'] );
        } else {
            echo '<p>' . esc_html__( 'No analytics for that landing page. Only published landing pages that match the current filters are listed.', 'landing-page-manager-pro' ) . '</p>';
            echo '<p><a href="' . esc_url( remove_query_arg( 'page_id' ) . '#pages' ) . '">' . esc_html__( '← All landing pages', 'landing-page-manager-pro' ) . '</a></p>';
        }
        echo '</div>';
    }

    /** The analytics dashboard: stat cards, charts and report tabs, filled by assets/js/analytics.js. */
    private static function render_dashboard() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
        $client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
        $clients = get_terms( [ 'taxonomy' => 'client', 'hide_empty' => false ] );
        if ( is_wp_error( $clients ) ) $clients = [];
        ?>
        <div class="wrap lp-analytics" id="lp-analytics-root">
            <header class="lp-analytics__header">
                <h1 class="lp-analytics__title"><?php esc_html_e( 'Analytics', 'landing-page-manager-pro' ); ?></h1>
                <p class="lp-analytics__subtitle"><?php esc_html_e( 'Traffic, click-through rates, and campaign performance.', 'landing-page-manager-pro' ); ?></p>
                <div class="lp-analytics__filters">
                    <?php if ( ! empty( $clients ) ) : ?>
                        <div class="lp-analytics__filter">
                            <label for="lp-client-filter"><?php esc_html_e( 'Client', 'landing-page-manager-pro' ); ?></label>
                            <select id="lp-client-filter" class="lp-select">
                                <option value="0"><?php esc_html_e( 'All clients', 'landing-page-manager-pro' ); ?></option>
                                <?php foreach ( $clients as $c ) : ?>
                                    <option value="<?php echo esc_attr( $c->term_id ); ?>" <?php selected( $client_id, $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="lp-analytics__filter">
                        <label for="lp-template-filter"><?php esc_html_e( 'Template', 'landing-page-manager-pro' ); ?></label>
                        <select id="lp-template-filter" class="lp-select">
                            <option value=""><?php esc_html_e( 'All templates', 'landing-page-manager-pro' ); ?></option>
                        </select>
                    </div>
                </div>
            </header>

            <div class="lp-tabs" role="tablist">
                <button type="button" class="lp-tabs__tab lp-tabs__tab--active" role="tab" id="tab-overview" aria-selected="true" aria-controls="panel-overview" data-tab="overview"><?php esc_html_e( 'Overview', 'landing-page-manager-pro' ); ?></button>
                <button type="button" class="lp-tabs__tab" role="tab" id="tab-clients" aria-selected="false" aria-controls="panel-clients" data-tab="clients"><?php esc_html_e( 'By Client', 'landing-page-manager-pro' ); ?></button>
                <button type="button" class="lp-tabs__tab" role="tab" id="tab-pages" aria-selected="false" aria-controls="panel-pages" data-tab="pages"><?php esc_html_e( 'By Page', 'landing-page-manager-pro' ); ?></button>
                <button type="button" class="lp-tabs__tab" role="tab" id="tab-template" aria-selected="false" aria-controls="panel-template" data-tab="template"><?php esc_html_e( 'By Template', 'landing-page-manager-pro' ); ?></button>
                <button type="button" class="lp-tabs__tab" role="tab" id="tab-links" aria-selected="false" aria-controls="panel-links" data-tab="links"><?php esc_html_e( 'Link Performance', 'landing-page-manager-pro' ); ?></button>
                <button type="button" class="lp-tabs__tab" role="tab" id="tab-engagement" aria-selected="false" aria-controls="panel-engagement" data-tab="engagement"><?php esc_html_e( 'Engagement', 'landing-page-manager-pro' ); ?></button>
                <button type="button" class="lp-tabs__tab" role="tab" id="tab-heatmap" aria-selected="false" aria-controls="panel-heatmap" data-tab="heatmap"><?php esc_html_e( 'Heatmap', 'landing-page-manager-pro' ); ?></button>
                <button type="button" class="lp-tabs__tab" role="tab" id="tab-rage" aria-selected="false" aria-controls="panel-rage" data-tab="rage"><?php esc_html_e( 'Rage Clicks', 'landing-page-manager-pro' ); ?></button>
            </div>

            <div id="panel-overview" class="lp-tab-panel lp-tab-panel--active" role="tabpanel" aria-labelledby="tab-overview">
                <div class="lp-stats lp-stats--hero lp-analytics-summary" id="lp-summary-cards"></div>
                <div class="lp-charts lp-charts--overview">
                    <div class="lp-chart-card"><canvas id="lp-chart-daily-visits"></canvas></div>
                    <div class="lp-chart-card"><canvas id="lp-chart-daily-conversions"></canvas></div>
                </div>
            </div>
            <div id="panel-clients" class="lp-tab-panel" role="tabpanel" aria-labelledby="tab-clients" hidden>
                <div class="lp-table-wrapper">
                    <table class="lp-table" id="lp-table-clients">
                        <thead><tr><th><?php esc_html_e( 'Client', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Pages', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Views', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Conversions', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Clicks', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Conv. rate', 'landing-page-manager-pro' ); ?></th></tr></thead>
                        <tbody id="lp-tbody-clients"></tbody>
                    </table>
                </div>
            </div>
            <div id="panel-pages" class="lp-tab-panel" role="tabpanel" aria-labelledby="tab-pages" hidden>
                <div class="lp-table-wrapper">
                    <table class="lp-table" id="lp-table-pages">
                        <thead><tr><th><?php esc_html_e( 'Page', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Client', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Template', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Views', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Conversions', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Clicks', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Conv. rate', 'landing-page-manager-pro' ); ?></th></tr></thead>
                        <tbody id="lp-tbody-pages"></tbody>
                    </table>
                </div>
            </div>
            <div id="panel-template" class="lp-tab-panel" role="tabpanel" aria-labelledby="tab-template" hidden>
                <div class="lp-table-wrapper">
                    <table class="lp-table" id="lp-table-template">
                        <thead><tr><th><?php esc_html_e( 'Template', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Pages', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Views', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Conversions', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Clicks', 'landing-page-manager-pro' ); ?></th><th><?php esc_html_e( 'Conv. rate', 'landing-page-manager-pro' ); ?></th></tr></thead>
                        <tbody id="lp-tbody-template"></tbody>
                    </table>
                </div>
            </div>
            <div id="panel-links" class="lp-tab-panel" role="tabpanel" aria-labelledby="tab-links" hidden>
                <div id="lp-link-performance"></div>
            </div>
            <div id="panel-engagement" class="lp-tab-panel" role="tabpanel" aria-labelledby="tab-engagement" hidden>
                <div id="lp-engagement-content"></div>
            </div>
            <div id="panel-heatmap" class="lp-tab-panel" role="tabpanel" aria-labelledby="tab-heatmap" hidden>
                <div id="lp-heatmap-content"></div>
            </div>
            <div id="panel-rage" class="lp-tab-panel" role="tabpanel" aria-labelledby="tab-rage" hidden>
                <div id="lp-rage-content"></div>
            </div>

            <div class="lp-analytics-loading" id="lp-analytics-loading"><?php esc_html_e( 'Loading…', 'landing-page-manager-pro' ); ?></div>
            <div class="lp-analytics-error" id="lp-analytics-error" hidden></div>
        </div>
        <?php
    }

    /** Pro's own screen with Landing Page Manager before 1.0.13: overview table and detail view. */
    public static function render_page() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters; nothing is changed.
        $client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
        // Same handling as the REST endpoint's template filter (a trimmed slug string).
        $template  = isset( $_GET['template'] ) ? sanitize_text_field( wp_unslash( $_GET['template'] ) ) : '';
        $page_id   = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $data    = Pro_Analytics::build_analytics_data( $client_id, $template );
        $enabled = $data['tracking_enabled'];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Pro Analytics', 'landing-page-manager-pro' ) . '</h1>';
        echo '<p>' . esc_html__( 'A conversion is a click on a phone link or call button. CTA views count page views in which a call-to-action became visible; form starts count the first field focused in each form per page view. Only published landing pages and enabled tracking features are shown.', 'landing-page-manager-pro' ) . '</p>';
        self::render_filters( $data, $client_id, $template );
        self::render_summary( $data['summary'], $enabled );

        $rows = self::page_rows( $data );
        if ( $page_id && isset( $rows[ $page_id ] ) ) {
            self::render_page_detail( $rows[ $page_id ], $data, $enabled );
        } else {
            self::render_pages_table( $rows, $enabled, $client_id, $template );
        }
        echo '</div>';
    }

    /**
     * Joins the per-page sections of the analytics data into one row per landing page.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function page_rows( array $data ) {
        $rows = [];
        foreach ( $data['by_page'] as $page ) {
            $rows[ (int) $page['id'] ] = $page + [ 'engagement' => [], 'interactions' => [], 'rage_clicks' => 0, 'links' => [], 'grid' => array_fill( 0, 100, 0 ) ];
        }
        foreach ( $data['engagement'] as $row ) {
            if ( isset( $rows[ (int) $row['post_id'] ] ) ) {
                $rows[ (int) $row['post_id'] ]['engagement'] = $row;
            }
        }
        foreach ( $data['interactions'] as $row ) {
            if ( isset( $rows[ (int) $row['post_id'] ] ) ) {
                $rows[ (int) $row['post_id'] ]['interactions'] = $row;
            }
        }
        foreach ( $data['rage_clicks'] as $row ) {
            if ( isset( $rows[ (int) $row['post_id'] ] ) ) {
                $rows[ (int) $row['post_id'] ]['rage_clicks'] = (int) $row['count'];
            }
        }
        foreach ( $data['link_clicks'] as $row ) {
            if ( isset( $rows[ (int) $row['post_id'] ] ) ) {
                $rows[ (int) $row['post_id'] ]['links'] = $row['links'];
            }
        }
        foreach ( $data['heatmap'] as $row ) {
            if ( isset( $rows[ (int) $row['post_id'] ] ) ) {
                $rows[ (int) $row['post_id'] ]['grid'] = array_pad( array_map( 'intval', (array) $row['grid'] ), 100, 0 );
            }
        }
        return $rows;
    }

    private static function render_filters( array $data, $client_id, $template ) {
        if ( ! $data['clients'] && ! $data['templates'] ) {
            return;
        }
        echo '<form method="get" class="lpmanager-pro-analytics-filters" style="margin:1em 0">';
        echo '<input type="hidden" name="page" value="' . esc_attr( self::PAGE_SLUG ) . '" />';
        if ( $data['clients'] ) {
            echo '<label for="lpmanager-pro-client">' . esc_html__( 'Client', 'landing-page-manager-pro' ) . '</label> ';
            echo '<select id="lpmanager-pro-client" name="client_id"><option value="0">' . esc_html__( 'All clients', 'landing-page-manager-pro' ) . '</option>';
            foreach ( $data['clients'] as $client ) {
                echo '<option value="' . esc_attr( $client['id'] ) . '"' . selected( $client_id, (int) $client['id'], false ) . '>' . esc_html( $client['name'] ) . '</option>';
            }
            echo '</select> ';
        }
        if ( $data['templates'] ) {
            echo '<label for="lpmanager-pro-template">' . esc_html__( 'Template', 'landing-page-manager-pro' ) . '</label> ';
            echo '<select id="lpmanager-pro-template" name="template"><option value="">' . esc_html__( 'All templates', 'landing-page-manager-pro' ) . '</option>';
            foreach ( $data['templates'] as $tpl ) {
                echo '<option value="' . esc_attr( $tpl['slug'] ) . '"' . selected( $template, (string) $tpl['slug'], false ) . '>' . esc_html( $tpl['name'] ) . '</option>';
            }
            echo '</select> ';
        }
        submit_button( __( 'Filter', 'landing-page-manager-pro' ), 'secondary', '', false );
        echo '</form>';
    }

    private static function render_summary( array $summary, array $enabled ) {
        $items = [ [ __( 'Landing pages', 'landing-page-manager-pro' ), number_format_i18n( $summary['total_pages'] ) ] ];
        if ( $enabled['page_views'] ) {
            $items[] = [ __( 'Views', 'landing-page-manager-pro' ), number_format_i18n( $summary['total_views'] ) ];
        }
        if ( $enabled['conversions'] ) {
            /* translators: 1: number of conversions, 2: conversion rate in percent. */
            $items[] = [ __( 'Conversions', 'landing-page-manager-pro' ), sprintf( __( '%1$s (%2$s%%)', 'landing-page-manager-pro' ), number_format_i18n( $summary['total_conversions'] ), number_format_i18n( $summary['ctr_conversion'], 1 ) ) ];
        }
        if ( $enabled['link_clicks'] ) {
            /* translators: 1: number of link clicks, 2: click-through rate in percent. */
            $items[] = [ __( 'Link clicks', 'landing-page-manager-pro' ), sprintf( __( '%1$s (%2$s%%)', 'landing-page-manager-pro' ), number_format_i18n( $summary['total_clicks'] ), number_format_i18n( $summary['ctr_clicks'], 1 ) ) ];
        }
        echo '<dl class="lpmanager-pro-summary" style="display:flex;flex-wrap:wrap;gap:1.5em;margin:1em 0">';
        foreach ( $items as $item ) {
            echo '<div><dt style="color:#50575e">' . esc_html( $item[0] ) . '</dt><dd style="margin:0;font-size:1.6em;font-weight:600">' . esc_html( $item[1] ) . '</dd></div>';
        }
        echo '</dl>';
    }

    /** Columns shown for the enabled tracking features only. */
    private static function columns( array $enabled ) {
        $columns = [
            'views'       => [ __( 'Views', 'landing-page-manager-pro' ), $enabled['page_views'] ],
            'conversions' => [ __( 'Conversions', 'landing-page-manager-pro' ), $enabled['conversions'] ],
            'clicks'      => [ __( 'Link clicks', 'landing-page-manager-pro' ), $enabled['link_clicks'] ],
            'time_avg'    => [ __( 'Avg. time on page', 'landing-page-manager-pro' ), $enabled['time_on_page'] ],
            'scroll_100'  => [ __( 'Read to the end', 'landing-page-manager-pro' ), $enabled['scroll_depth'] ],
            'rage'        => [ __( 'Rage clicks', 'landing-page-manager-pro' ), $enabled['rage_clicks'] ],
            'outbound'    => [ __( 'Outbound clicks', 'landing-page-manager-pro' ), $enabled['outbound_clicks'] ],
            'cta'         => [ __( 'CTA views', 'landing-page-manager-pro' ), $enabled['cta_visibility'] ],
            'forms'       => [ __( 'Form starts / submits', 'landing-page-manager-pro' ), $enabled['form_interactions'] ],
        ];
        return array_filter( $columns, function ( $column ) {
            return $column[1];
        } );
    }

    /** Display value for one column of a page row. */
    private static function cell( array $row, $column ) {
        $e = $row['engagement'];
        $i = $row['interactions'];
        switch ( $column ) {
            case 'views':
                return number_format_i18n( $row['views'] );
            case 'conversions':
                /* translators: 1: number of conversions, 2: conversion rate in percent. */
                return sprintf( __( '%1$s (%2$s%%)', 'landing-page-manager-pro' ), number_format_i18n( $row['conversions'] ), number_format_i18n( $row['ctr_conversion'], 1 ) );
            case 'clicks':
                return number_format_i18n( $row['clicks'] );
            case 'time_avg':
                return empty( $e['time_count'] ) ? '—' : self::format_duration( (int) $e['time_avg'] );
            case 'scroll_100':
                return number_format_i18n( (int) ( $e['scroll_100'] ?? 0 ) );
            case 'rage':
                return number_format_i18n( $row['rage_clicks'] );
            case 'outbound':
                return number_format_i18n( (int) ( $i['outbound_click'] ?? 0 ) );
            case 'cta':
                return number_format_i18n( (int) ( $i['cta_view'] ?? 0 ) );
            case 'forms':
                return number_format_i18n( (int) ( $i['form_start'] ?? 0 ) ) . ' / ' . number_format_i18n( (int) ( $i['form_submit'] ?? 0 ) );
        }
        return '';
    }

    public static function format_duration( $seconds ) {
        $seconds = max( 0, (int) $seconds );
        if ( $seconds < 60 ) {
            /* translators: %d: number of seconds. */
            return sprintf( _n( '%d second', '%d seconds', $seconds, 'landing-page-manager-pro' ), $seconds );
        }
        /* translators: 1: minutes, 2: seconds. */
        return sprintf( __( '%1$d min %2$02d s', 'landing-page-manager-pro' ), intdiv( $seconds, 60 ), $seconds % 60 );
    }

    private static function render_pages_table( array $rows, array $enabled, $client_id, $template ) {
        $columns = self::columns( $enabled );
        echo '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Analytics by landing page', 'landing-page-manager-pro' ) . '</caption><thead><tr>';
        echo '<th scope="col">' . esc_html__( 'Landing Page', 'landing-page-manager-pro' ) . '</th>';
        foreach ( $columns as $column ) {
            echo '<th scope="col">' . esc_html( $column[0] ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        if ( ! $rows ) {
            echo '<tr><td colspan="' . esc_attr( count( $columns ) + 1 ) . '">' . esc_html__( 'No published landing pages match.', 'landing-page-manager-pro' ) . '</td></tr>';
        }
        foreach ( $rows as $id => $row ) {
            $url = add_query_arg( array_filter( [ 'page' => self::PAGE_SLUG, 'client_id' => $client_id, 'template' => $template, 'page_id' => $id ] ), admin_url( 'admin.php' ) );
            echo '<tr><th scope="row"><a href="' . esc_url( $url ) . '">' . esc_html( $row['title'] !== '' ? $row['title'] : __( '(no title)', 'landing-page-manager-pro' ) ) . '</a></th>';
            foreach ( array_keys( $columns ) as $column ) {
                echo '<td>' . esc_html( self::cell( $row, $column ) ) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_page_detail( array $row, array $data, array $enabled ) {
        // On the dashboard (Landing Page Manager 1.0.13+), return to its By Page tab.
        $back = remove_query_arg( 'page_id' ) . ( self::uses_core_screen() ? '#pages' : '' );
        echo '<p><a href="' . esc_url( $back ) . '">' . esc_html__( '← All landing pages', 'landing-page-manager-pro' ) . '</a></p>';
        echo '<h2>' . esc_html( $row['title'] !== '' ? $row['title'] : __( '(no title)', 'landing-page-manager-pro' ) ) . '</h2>';
        $edit = get_edit_post_link( (int) $row['id'] );
        if ( $edit ) {
            echo '<p><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit page', 'landing-page-manager-pro' ) . '</a></p>';
        }

        $columns = self::columns( $enabled );
        echo '<table class="widefat striped" style="max-width:40em"><tbody>';
        foreach ( $columns as $key => $column ) {
            echo '<tr><th scope="row">' . esc_html( $column[0] ) . '</th><td>' . esc_html( self::cell( $row, $key ) ) . '</td></tr>';
        }
        echo '</tbody></table>';

        if ( $enabled['scroll_depth'] ) {
            $e = $row['engagement'];
            echo '<h3>' . esc_html__( 'Scroll depth', 'landing-page-manager-pro' ) . '</h3><table class="widefat striped" style="max-width:40em"><thead><tr>';
            foreach ( [ 25, 50, 75, 100 ] as $depth ) {
                /* translators: %d: scroll depth in percent. */
                echo '<th scope="col">' . esc_html( sprintf( __( '%d%%', 'landing-page-manager-pro' ), $depth ) ) . '</th>';
            }
            echo '</tr></thead><tbody><tr>';
            foreach ( [ 25, 50, 75, 100 ] as $depth ) {
                echo '<td>' . esc_html( number_format_i18n( (int) ( $e[ 'scroll_' . $depth ] ?? 0 ) ) ) . '</td>';
            }
            echo '</tr></tbody></table>';
        }

        if ( $enabled['link_clicks'] ) {
            echo '<h3>' . esc_html__( 'Top links', 'landing-page-manager-pro' ) . '</h3><table class="widefat striped" style="max-width:60em"><thead><tr>';
            echo '<th scope="col">' . esc_html__( 'Link text', 'landing-page-manager-pro' ) . '</th><th scope="col">' . esc_html__( 'Address', 'landing-page-manager-pro' ) . '</th><th scope="col">' . esc_html__( 'Clicks', 'landing-page-manager-pro' ) . '</th></tr></thead><tbody>';
            if ( ! $row['links'] ) {
                echo '<tr><td colspan="3">' . esc_html__( 'No link clicks yet.', 'landing-page-manager-pro' ) . '</td></tr>';
            }
            foreach ( $row['links'] as $link ) {
                echo '<tr><td>' . esc_html( $link['label'] ) . '</td><td><code>' . esc_html( $link['href'] ) . '</code></td><td>' . esc_html( number_format_i18n( $link['count'] ) ) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        if ( $enabled['heatmap'] ) {
            self::render_heatmap( $row['grid'] );
        }
    }

    /**
     * The heatmap as a 10x10 table of mouse-position counts over the visible window. Shading
     * shows intensity; every cell also contains its number, so it reads without color.
     */
    private static function render_heatmap( array $grid ) {
        $max = max( 1, max( $grid ) );
        echo '<h3>' . esc_html__( 'Heatmap', 'landing-page-manager-pro' ) . '</h3>';
        echo '<p>' . esc_html__( 'Where visitors moved the mouse, as a 10 × 10 grid of the visible window (top-left to bottom-right). Darker cells had more movement.', 'landing-page-manager-pro' ) . '</p>';
        if ( ! array_sum( $grid ) ) {
            echo '<p>' . esc_html__( 'No heatmap data yet.', 'landing-page-manager-pro' ) . '</p>';
            return;
        }
        echo '<table class="lpmanager-pro-heatmap" style="border-collapse:collapse;table-layout:fixed;width:min(100%,34em)"><caption class="screen-reader-text">' . esc_html__( 'Mouse-position counts by grid cell, rows top to bottom and columns left to right', 'landing-page-manager-pro' ) . '</caption>';
        echo '<thead><tr><td></td>';
        for ( $c = 1; $c <= 10; $c++ ) {
            /* translators: %d: column number, 1 to 10 from left to right. */
            echo '<th scope="col" style="font-size:11px;font-weight:400;color:#50575e" aria-label="' . esc_attr( sprintf( __( 'Column %d', 'landing-page-manager-pro' ), $c ) ) . '">' . esc_html( number_format_i18n( $c ) ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        for ( $r = 0; $r < 10; $r++ ) {
            /* translators: %d: row number, 1 to 10 from top to bottom. */
            echo '<tr><th scope="row" style="font-size:11px;font-weight:400;color:#50575e;padding-right:.4em" aria-label="' . esc_attr( sprintf( __( 'Row %d', 'landing-page-manager-pro' ), $r + 1 ) ) . '">' . esc_html( number_format_i18n( $r + 1 ) ) . '</th>';
            for ( $c = 0; $c < 10; $c++ ) {
                $count = (int) $grid[ $r * 10 + $c ];
                // Capped tint with dark text keeps every cell at or above 4.5:1 contrast.
                $alpha = round( 0.6 * $count / $max, 2 );
                echo '<td style="border:1px solid #dcdcde;padding:.35em 0;text-align:center;font-size:12px;color:#1d2327;background:rgba(214,54,56,' . esc_attr( $alpha ) . ')">' . esc_html( number_format_i18n( $count ) ) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}
