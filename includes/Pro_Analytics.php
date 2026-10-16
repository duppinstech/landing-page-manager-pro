<?php
/**
 * Pro-only: analytics REST endpoint and dashboard data (views, conversions, summary).
 *
 * Copyright (c) 2026 Odell Duppins (d/b/a Duppins Technology). Licensed under GPL-2.0-or-later; see LICENSE.md.
 */
namespace LPManagerPro;

class Pro_Analytics {

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_analytics_route' ], 15 );
        add_filter( 'lpmanager_dashboard_data', [ __CLASS__, 'add_dashboard_pro_data' ], 10, 1 );
    }

    public static function register_analytics_route() {
        register_rest_route( 'lpmanager/v1', '/analytics-data', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'get_analytics_data' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args'                => [
                'client_id' => [
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'default'           => 0,
                ],
                'template' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default'           => '',
                ],
            ],
        ] );
    }

    /**
     * Add Pro data to dashboard REST response (visits, conversions, summary).
     */
    public static function add_dashboard_pro_data( $data ) {
        $tracking = Settings::tracking_options();
        $pages = get_posts( [ 'post_type' => 'landing_page', 'posts_per_page' => -1 ] );

        $visits_per_page = [];
        $conversions_per_page = [];
        $labels = self::get_unique_page_labels( $pages );
        foreach ( $pages as $page ) {
            $label = $labels[ $page->ID ];
            if ( ! empty( $tracking['track_page_views'] ) ) {
                $visits_per_page[ $label ] = (int) get_post_meta( $page->ID, '_lpmanager_page_views', true );
            }
            if ( ! empty( $tracking['enable_conversion_tracking'] ) ) {
                $conversions_per_page[ $label ] = (int) get_post_meta( $page->ID, '_lpmanager_conversions', true );
            }
        }
        $data['visits_per_page'] = $visits_per_page;
        $data['conversions_per_page'] = $conversions_per_page;
        $data['daily_visits'] = ! empty( $tracking['track_page_views'] ) ? ( get_option( 'lpmanager_daily_visits', [] ) ?: [] ) : [];
        $data['daily_conversions'] = ! empty( $tracking['enable_conversion_tracking'] ) ? ( get_option( 'lpmanager_daily_conversions', [] ) ?: [] ) : [];

        $total_views = 0;
        $total_conversions = 0;
        $total_clicks = 0;
        foreach ( $pages as $page ) {
            if ( ! empty( $tracking['track_page_views'] ) ) {
                $total_views += (int) get_post_meta( $page->ID, '_lpmanager_page_views', true );
            }
            if ( ! empty( $tracking['enable_conversion_tracking'] ) ) {
                $total_conversions += (int) get_post_meta( $page->ID, '_lpmanager_conversions', true );
            }
            if ( ! empty( $tracking['track_link_clicks'] ) ) {
                $total_clicks += (int) get_post_meta( $page->ID, '_lpmanager_total_clicks', true );
            }
        }
        $data['tracking_enabled'] = [
            'page_views'   => ! empty( $tracking['track_page_views'] ),
            'conversions'  => ! empty( $tracking['enable_conversion_tracking'] ),
            'link_clicks'  => ! empty( $tracking['track_link_clicks'] ),
        ];
        $data['summary'] = [
            'total_views'       => $total_views,
            'total_conversions' => $total_conversions,
            'total_clicks'      => $total_clicks,
            'ctr_conversion'    => $total_views > 0 ? round( ( $total_conversions / $total_views ) * 100, 1 ) : 0,
            'ctr_clicks'        => $total_views > 0 ? round( ( $total_clicks / $total_views ) * 100, 1 ) : 0,
        ];
        return $data;
    }

    public static function get_analytics_data( $request ) {
        $template_filter = $request->get_param( 'template' );
        return new \WP_REST_Response( self::build_analytics_data( (int) $request->get_param( 'client_id' ), is_string( $template_filter ) ? $template_filter : '' ), 200 );
    }

    /**
     * Analytics for published landing pages, optionally filtered by client and template. Shared by
     * the REST endpoint and the Pro Analytics screen so both always show the same numbers.
     *
     * @return array<string, mixed>
     */
    public static function build_analytics_data( $client_id = 0, $template_filter = '' ) {
        $tracking        = Settings::tracking_options();
        $client_id       = (int) $client_id;
        $template_filter = trim( (string) $template_filter );
        $pages = get_posts( [
            'post_type'      => 'landing_page',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ] );
        if ( $client_id ) {
            $pages = array_filter( $pages, function ( $p ) use ( $client_id ) {
                $terms = get_the_terms( $p->ID, 'client' );
                if ( ! $terms || is_wp_error( $terms ) ) return false;
                return (int) $terms[0]->term_id === $client_id;
            } );
        }
        if ( $template_filter !== '' && class_exists( 'LPManager\templates\Template_Loader' ) ) {
            $pages = array_filter( $pages, function ( $p ) use ( $template_filter ) {
                return \LPManager\templates\Template_Loader::get_effective_template_for_page( $p->ID ) === $template_filter;
            } );
        }

        $total_views = 0;
        $total_conversions = 0;
        $total_clicks = 0;
        $by_page = [];
        $client_totals = [];
        $template_totals = [];

        foreach ( $pages as $page ) {
            $template_slug = class_exists( 'LPManager\templates\Template_Loader' ) ? \LPManager\templates\Template_Loader::get_effective_template_for_page( $page->ID ) : 'default';
            $views = ! empty( $tracking['track_page_views'] ) ? (int) get_post_meta( $page->ID, '_lpmanager_page_views', true ) : 0;
            $conversions = ! empty( $tracking['enable_conversion_tracking'] ) ? (int) get_post_meta( $page->ID, '_lpmanager_conversions', true ) : 0;
            $clicks = ! empty( $tracking['track_link_clicks'] ) ? (int) get_post_meta( $page->ID, '_lpmanager_total_clicks', true ) : 0;
            $total_views += $views;
            $total_conversions += $conversions;
            $total_clicks += $clicks;

            if ( ! isset( $template_totals[ $template_slug ] ) ) {
                $template_totals[ $template_slug ] = [ 'views' => 0, 'conversions' => 0, 'clicks' => 0, 'pages' => 0 ];
            }
            $template_totals[ $template_slug ]['views'] += $views;
            $template_totals[ $template_slug ]['conversions'] += $conversions;
            $template_totals[ $template_slug ]['clicks'] += $clicks;
            $template_totals[ $template_slug ]['pages']++;

            $terms = get_the_terms( $page->ID, 'client' );
            $client_name = '';
            $cid = 0;
            if ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $client_name = $terms[0]->name;
                $cid = (int) $terms[0]->term_id;
                if ( ! isset( $client_totals[ $cid ] ) ) {
                    $client_totals[ $cid ] = [ 'name' => $client_name, 'slug' => $terms[0]->slug, 'views' => 0, 'conversions' => 0, 'clicks' => 0, 'pages' => 0 ];
                }
                $client_totals[ $cid ]['views'] += $views;
                $client_totals[ $cid ]['conversions'] += $conversions;
                $client_totals[ $cid ]['clicks'] += $clicks;
                $client_totals[ $cid ]['pages']++;
            }

            $ctr_conv = $views > 0 ? round( ( $conversions / $views ) * 100, 1 ) : 0;
            $ctr_clicks = $views > 0 ? round( ( $clicks / $views ) * 100, 1 ) : 0;
            $by_page[] = [
                'id'            => $page->ID,
                'title'         => $page->post_title,
                'client_id'     => $cid,
                'client_name'   => $client_name,
                'template_slug' => $template_slug,
                'views'         => $views,
                'conversions'   => $conversions,
                'clicks'        => $clicks,
                'ctr_conversion'=> $ctr_conv,
                'ctr_clicks'    => $ctr_clicks,
            ];
        }

        $by_client = [];
        foreach ( $client_totals as $cid => $row ) {
            $v = $row['views'];
            $by_client[] = [
                'client_id'     => $cid,
                'name'          => $row['name'],
                'slug'          => $row['slug'],
                'pages_count'   => $row['pages'],
                'views'         => $row['views'],
                'conversions'   => $row['conversions'],
                'clicks'        => $row['clicks'],
                'ctr_conversion'=> $v > 0 ? round( ( $row['conversions'] / $v ) * 100, 1 ) : 0,
                'ctr_clicks'    => $v > 0 ? round( ( $row['clicks'] / $v ) * 100, 1 ) : 0,
            ];
        }

        $link_clicks_raw = ! empty( $tracking['track_link_clicks'] ) ? get_option( 'lpmanager_link_clicks', [] ) : [];
        $link_clicks = [];
        foreach ( $pages as $page ) {
            $pid = $page->ID;
            $links = isset( $link_clicks_raw[ $pid ] ) && is_array( $link_clicks_raw[ $pid ] ) ? $link_clicks_raw[ $pid ] : [];
            $list = [];
            foreach ( $links as $k => $item ) {
                $list[] = [
                    'href'  => isset( $item['href'] ) ? $item['href'] : '',
                    'label' => isset( $item['label'] ) ? $item['label'] : '',
                    'count' => isset( $item['count'] ) ? (int) $item['count'] : 0,
                ];
            }
            usort( $list, function ( $a, $b ) { return $b['count'] - $a['count']; } );
            $link_clicks[] = [ 'post_id' => $pid, 'title' => $page->post_title, 'links' => array_slice( $list, 0, 20 ) ];
        }

        $daily_visits = ! empty( $tracking['track_page_views'] ) ? get_option( 'lpmanager_daily_visits', [] ) : [];
        $daily_conversions = ! empty( $tracking['enable_conversion_tracking'] ) ? get_option( 'lpmanager_daily_conversions', [] ) : [];
        if ( ! is_array( $daily_visits ) ) $daily_visits = [];
        if ( ! is_array( $daily_conversions ) ) $daily_conversions = [];

        $overall_ctr_conv = $total_views > 0 ? round( ( $total_conversions / $total_views ) * 100, 1 ) : 0;
        $overall_ctr_clicks = $total_views > 0 ? round( ( $total_clicks / $total_views ) * 100, 1 ) : 0;

        $clients_list = [];
        $terms = get_terms( [ 'taxonomy' => 'client', 'hide_empty' => false ] );
        if ( ! is_wp_error( $terms ) && $terms ) {
            foreach ( $terms as $t ) {
                $clients_list[] = [ 'id' => (int) $t->term_id, 'name' => $t->name, 'slug' => $t->slug ];
            }
        }

        $templates_list = [];
        if ( class_exists( 'LPManager\templates\Template_Manager' ) ) {
            $all_templates = \LPManager\templates\Template_Manager::get_templates();
            foreach ( $all_templates as $slug => $info ) {
                $templates_list[] = [ 'slug' => $slug, 'name' => isset( $info['name'] ) ? $info['name'] : $slug ];
            }
        }

        $by_template = [];
        foreach ( $template_totals as $tpl_slug => $row ) {
            $v = $row['views'];
            $by_template[] = [
                'template_slug' => $tpl_slug,
                'template_name' => $tpl_slug,
                'pages_count'   => $row['pages'],
                'views'         => $row['views'],
                'conversions'   => $row['conversions'],
                'clicks'        => $row['clicks'],
                'ctr_conversion'=> $v > 0 ? round( ( $row['conversions'] / $v ) * 100, 1 ) : 0,
                'ctr_clicks'    => $v > 0 ? round( ( $row['clicks'] / $v ) * 100, 1 ) : 0,
            ];
        }
        foreach ( $by_template as $i => $row ) {
            foreach ( $templates_list as $t ) {
                if ( $t['slug'] === $row['template_slug'] ) {
                    $by_template[ $i ]['template_name'] = $t['name'];
                    break;
                }
            }
        }

        $engagement_raw = get_option( 'lpmanager_engagement', [] );
        if ( ! is_array( $engagement_raw ) ) {
            $engagement_raw = [];
        }
        $engagement = [];
        foreach ( $pages as $page ) {
            $pid = $page->ID;
            $e = self::get_engagement_for_post( $engagement_raw, $pid );
            $time_avg = ! empty( $e['time_count'] ) && (int) $e['time_count'] > 0 ? round( (int) ( $e['time_sum'] ?? 0 ) / (int) $e['time_count'] ) : 0;
            $engagement[] = [
                'post_id'    => $pid,
                'title'      => $page->post_title,
                'time_avg'   => $time_avg,
                'time_count' => (int) ( $e['time_count'] ?? 0 ),
                'scroll_25'  => (int) ( $e['scroll_25'] ?? 0 ),
                'scroll_50'  => (int) ( $e['scroll_50'] ?? 0 ),
                'scroll_75'  => (int) ( $e['scroll_75'] ?? 0 ),
                'scroll_100' => (int) ( $e['scroll_100'] ?? 0 ),
            ];
        }

        $heatmap_raw = get_option( 'lpmanager_heatmap', [] );
        if ( ! is_array( $heatmap_raw ) ) {
            $heatmap_raw = [];
        }
        $heatmap = [];
        foreach ( $pages as $page ) {
            $pid = $page->ID;
            $grid = self::get_heatmap_for_post( $heatmap_raw, $pid );
            $heatmap[] = [
                'post_id' => $pid,
                'title'   => $page->post_title,
                'url'     => get_permalink( $page->ID ),
                'grid'    => $grid,
            ];
        }

        $rage_clicks = [];
        foreach ( $pages as $page ) {
            $count = (int) get_post_meta( $page->ID, '_lpmanager_rage_clicks', true );
            if ( $count > 0 ) {
                $rage_clicks[] = [ 'post_id' => $page->ID, 'title' => $page->post_title, 'count' => $count ];
            }
        }
        usort( $rage_clicks, function ( $a, $b ) { return $b['count'] - $a['count']; } );

        // Like the daily series, interaction types whose tracking is off are left out.
        $enabled_types = [];
        foreach ( Tracking_Manager::INTERACTIONS as $type => $def ) {
            if ( ! empty( $tracking[ $def['option'] ] ) ) {
                $enabled_types[ $type ] = true;
            }
        }
        $interactions = [];
        if ( $enabled_types ) {
            foreach ( $pages as $page ) {
                $interactions[] = array_merge(
                    [ 'post_id' => $page->ID, 'title' => $page->post_title ],
                    array_intersect_key( Tracking_Manager::get_interaction_counts( $page->ID ), $enabled_types )
                );
            }
        }

        return [
            'tracking_enabled' => [
                'page_views'     => ! empty( $tracking['track_page_views'] ),
                'conversions'    => ! empty( $tracking['enable_conversion_tracking'] ),
                'link_clicks'    => ! empty( $tracking['track_link_clicks'] ),
                'time_on_page'   => ! empty( $tracking['track_time_on_page'] ),
                'scroll_depth'   => ! empty( $tracking['track_scroll_depth'] ),
                'heatmap'        => ! empty( $tracking['track_heatmap'] ),
                'rage_clicks'    => ! empty( $tracking['track_rage_clicks'] ),
                'outbound_clicks'   => ! empty( $tracking['track_outbound_clicks'] ),
                'cta_visibility'    => ! empty( $tracking['track_cta_visibility'] ),
                'form_interactions' => ! empty( $tracking['track_form_interactions'] ),
            ],
            'summary'           => [
                'total_pages'      => count( $pages ),
                'total_views'      => $total_views,
                'total_conversions'=> $total_conversions,
                'total_clicks'     => $total_clicks,
                'ctr_conversion'   => $overall_ctr_conv,
                'ctr_clicks'       => $overall_ctr_clicks,
            ],
            'daily_visits'      => $daily_visits,
            'daily_conversions' => $daily_conversions,
            'by_page'           => $by_page,
            'by_client'         => $by_client,
            'by_template'       => $by_template,
            'link_clicks'       => $link_clicks,
            'engagement'        => $engagement,
            'heatmap'           => $heatmap,
            'rage_clicks'       => $rage_clicks,
            'interactions'      => $interactions,
            'clients'           => $clients_list,
            'templates'         => $templates_list,
            'client_id_filter'  => $client_id,
            'template_filter'   => $template_filter,
        ];
    }

    /**
     * Chart labels keyed by post ID. The dashboard data is keyed by label, so pages that
     * share a title get their ID appended instead of overwriting each other.
     */
    private static function get_unique_page_labels( array $pages ) {
        $title_counts = array_count_values( array_map( function ( $p ) {
            return (string) $p->post_title;
        }, $pages ) );
        $labels = [];
        foreach ( $pages as $page ) {
            $title = (string) $page->post_title;
            $labels[ $page->ID ] = $title_counts[ $title ] > 1 ? sprintf( '%s (#%d)', $title, $page->ID ) : $title;
        }
        return $labels;
    }

    /** Ensures we find engagement row even when option keys are stored as strings. */
    private static function get_engagement_for_post( array $engagement_raw, $post_id ) {
        $pid = (int) $post_id;
        if ( isset( $engagement_raw[ $pid ] ) && is_array( $engagement_raw[ $pid ] ) ) {
            return $engagement_raw[ $pid ];
        }
        if ( isset( $engagement_raw[ (string) $pid ] ) && is_array( $engagement_raw[ (string) $pid ] ) ) {
            return $engagement_raw[ (string) $pid ];
        }
        return [];
    }

    /** Ensures we find heatmap grid even when option keys are stored as strings. */
    private static function get_heatmap_for_post( array $heatmap_raw, $post_id ) {
        $pid = (int) $post_id;
        if ( isset( $heatmap_raw[ $pid ] ) && is_array( $heatmap_raw[ $pid ] ) ) {
            return array_values( $heatmap_raw[ $pid ] );
        }
        if ( isset( $heatmap_raw[ (string) $pid ] ) && is_array( $heatmap_raw[ (string) $pid ] ) ) {
            return array_values( $heatmap_raw[ (string) $pid ] );
        }
        return array_fill( 0, 100, 0 );
    }
}
