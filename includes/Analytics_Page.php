<?php
/**
 * Pro Analytics screen: per-page engagement, interactions, rage clicks, top links, and the heatmap.
 * Analytics are Pro-only (owner decision 2026-10-16), so this data is shown here rather than in
 * Landing Page Manager's own screens. Read-only; filters arrive by GET.
 *
 * Copyright (c) 2026 Odell Duppins (d/b/a Duppins Technology). Licensed under GPL-2.0-or-later; see LICENSE.md.
 */
namespace LPManagerPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Analytics_Page {

    const PAGE_SLUG  = 'lp_pro_analytics';
    const CAPABILITY = 'manage_options';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_page' ], 25 );
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

    public static function render_page() {
        if ( ! current_user_can( self::CAPABILITY ) ) {
            return;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters; nothing is changed.
        $client_id = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;
        $template  = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
        $page_id   = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $data    = Pro_Analytics::build_analytics_data( $client_id, $template );
        $enabled = $data['tracking_enabled'];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Pro Analytics', 'landing-page-manager-pro' ) . '</h1>';
        self::render_filters( $data, $client_id, $template );
        self::render_summary( $data['summary'] );

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

    private static function render_summary( array $summary ) {
        $items = [
            [ __( 'Landing pages', 'landing-page-manager-pro' ), number_format_i18n( $summary['total_pages'] ) ],
            [ __( 'Views', 'landing-page-manager-pro' ), number_format_i18n( $summary['total_views'] ) ],
            /* translators: 1: number of conversions, 2: conversion rate in percent. */
            [ __( 'Conversions', 'landing-page-manager-pro' ), sprintf( __( '%1$s (%2$s%%)', 'landing-page-manager-pro' ), number_format_i18n( $summary['total_conversions'] ), number_format_i18n( $summary['ctr_conversion'], 1 ) ) ],
            /* translators: 1: number of link clicks, 2: click-through rate in percent. */
            [ __( 'Link clicks', 'landing-page-manager-pro' ), sprintf( __( '%1$s (%2$s%%)', 'landing-page-manager-pro' ), number_format_i18n( $summary['total_clicks'] ), number_format_i18n( $summary['ctr_clicks'], 1 ) ) ],
        ];
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
        $back = remove_query_arg( 'page_id' );
        echo '<p><a href="' . esc_url( $back ) . '">' . esc_html__( '← All landing pages', 'landing-page-manager-pro' ) . '</a></p>';
        echo '<h2>' . esc_html( $row['title'] ) . '</h2>';

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
        echo '<table class="lpmanager-pro-heatmap" style="border-collapse:collapse;table-layout:fixed;width:min(100%,32em)"><caption class="screen-reader-text">' . esc_html__( 'Mouse-position counts by grid cell', 'landing-page-manager-pro' ) . '</caption><tbody>';
        for ( $r = 0; $r < 10; $r++ ) {
            echo '<tr>';
            for ( $c = 0; $c < 10; $c++ ) {
                $count = (int) $grid[ $r * 10 + $c ];
                $alpha = round( $count / $max, 2 );
                $color = $alpha > 0.55 ? '#fff' : '#1d2327';
                echo '<td style="border:1px solid #dcdcde;padding:.35em 0;text-align:center;font-size:12px;color:' . esc_attr( $color ) . ';background:rgba(214,54,56,' . esc_attr( $alpha ) . ')">' . esc_html( number_format_i18n( $count ) ) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}
