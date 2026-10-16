<?php
/**
 * Analytics storage (F6). Counters live in two custom tables updated with atomic SQL, so
 * simultaneous tracking requests no longer lose increments (B11) and no large option is
 * rewritten on every event.
 *
 * - {prefix}lpmanager_pro_stats: one row per landing page, UTC day, and metric.
 * - {prefix}lpmanager_pro_links: one row per landing page and link.
 *
 * Per-page totals that Landing Page Manager reads directly (views, conversions, clicks, and the
 * interaction and rage-click counts) stay in post meta, incremented atomically as well.
 *
 * Data from before 1.0.5 is copied in once by migrate(). It is kept apart from live data, in the
 * LEGACY_DAY bucket, the links table's legacy_clicks column, and post ID 0 for the site-wide
 * daily series, so the copy can be repeated safely and never overwrites live counts.
 *
 * Copyright (c) 2026 Odell Duppins (d/b/a Duppins Technology). Licensed under GPL-2.0-or-later; see LICENSE.md.
 */
namespace LPManagerPro;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class is the plugin's storage layer for its own tables; writes must be atomic SQL, and results are not cached because they change on every tracking request.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix and constants; every value is passed through $wpdb->prepare().

class Stats {

    const DB_VERSION        = 1;
    const DB_VERSION_OPTION = 'lpmanager_pro_db_version';

    /** Set once the pre-1.0.5 options have been copied into the tables. */
    const MIGRATED_OPTION = 'lpmanager_pro_storage_migrated';

    /** Day bucket for totals recorded before per-day storage existed. */
    const LEGACY_DAY = '1970-01-01';

    /** Post ID used for the site-wide daily series recorded before 1.0.5. */
    const SITE_POST_ID = 0;

    const MAX_LINKS_PER_PAGE = 200;

    public static function stats_table() {
        global $wpdb;
        return $wpdb->prefix . 'lpmanager_pro_stats';
    }

    public static function links_table() {
        global $wpdb;
        return $wpdb->prefix . 'lpmanager_pro_links';
    }

    /** Creates or upgrades the tables, then copies pre-1.0.5 data once. Cheap when up to date. */
    public static function maybe_install() {
        if ( (int) get_option( self::DB_VERSION_OPTION ) < self::DB_VERSION ) {
            self::install();
        }
        if ( ! get_option( self::MIGRATED_OPTION ) ) {
            self::migrate();
        }
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $stats   = self::stats_table();
        $links   = self::links_table();
        dbDelta( "CREATE TABLE {$stats} (
  post_id bigint(20) unsigned NOT NULL,
  day date NOT NULL,
  metric varchar(32) NOT NULL,
  value bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (post_id,day,metric),
  KEY day_metric (day,metric)
) {$charset};
CREATE TABLE {$links} (
  post_id bigint(20) unsigned NOT NULL,
  link_key char(32) NOT NULL,
  href varchar(512) NOT NULL DEFAULT '',
  label varchar(100) NOT NULL DEFAULT '',
  clicks bigint(20) unsigned NOT NULL DEFAULT 0,
  legacy_clicks bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (post_id,link_key)
) {$charset};" );
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
    }

    public static function today() {
        return gmdate( 'Y-m-d' );
    }

    /**
     * Adds to one or more of a page's metrics for a day, in one atomic statement.
     *
     * @param array<string, int> $amounts Metric name => amount to add.
     */
    public static function add( $post_id, array $amounts, $day = null ) {
        global $wpdb;
        $rows = [];
        foreach ( $amounts as $metric => $amount ) {
            $amount = (int) $amount;
            if ( $amount > 0 ) {
                $rows[] = $wpdb->prepare( '(%d, %s, %s, %d)', $post_id, $day === null ? self::today() : $day, $metric, $amount );
            }
        }
        if ( ! $rows ) {
            return;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Each row tuple above is prepared.
        $wpdb->query( 'INSERT INTO ' . self::stats_table() . ' (post_id, day, metric, value) VALUES ' . implode( ', ', $rows ) . ' ON DUPLICATE KEY UPDATE value = value + VALUES(value)' );
    }

    /**
     * Atomically adds to a numeric post meta counter, creating it if missing. The free plugin reads
     * these totals directly, so they stay in post meta.
     */
    public static function increment_meta( $post_id, $meta_key, $amount = 1 ) {
        global $wpdb;
        $sql     = $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + %d WHERE post_id = %d AND meta_key = %s", $amount, $post_id, $meta_key );
        $updated = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
        if ( ! $updated && ! add_post_meta( $post_id, $meta_key, (int) $amount, true ) ) {
            // Another request created the row between the two statements.
            $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
        }
        wp_cache_delete( $post_id, 'post_meta' );
    }

    /**
     * Counts a click on one link. Past MAX_LINKS_PER_PAGE distinct links a page gets no new rows
     * (the cap can be exceeded slightly under concurrency, which stays bounded).
     *
     * @return bool Whether the click was stored on a link row.
     */
    public static function add_link_click( $post_id, $link_key, $href, $label ) {
        global $wpdb;
        $table   = self::links_table();
        $updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET clicks = clicks + 1 WHERE post_id = %d AND link_key = %s", $post_id, $link_key ) );
        if ( $updated ) {
            return true;
        }
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE post_id = %d", $post_id ) );
        if ( $count >= self::MAX_LINKS_PER_PAGE ) {
            return false;
        }
        $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (post_id, link_key, href, label, clicks) VALUES (%d, %s, %s, %s, 1) ON DUPLICATE KEY UPDATE clicks = clicks + 1", $post_id, $link_key, $href, $label ) );
        return true;
    }

    /**
     * Sums of every metric per page, all time (including pre-1.0.5 data) or for a UTC day range.
     *
     * @param int[] $post_ids
     * @return array<int, array<string, int>> Post ID => metric => sum.
     */
    public static function totals( array $post_ids, $from = null, $to = null ) {
        global $wpdb;
        $post_ids = array_values( array_filter( array_map( 'absint', $post_ids ) ) );
        if ( ! $post_ids ) {
            return [];
        }
        $where = 'post_id IN (' . implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) ) . ')';
        $args  = $post_ids;
        if ( $from !== null && $to !== null ) {
            $where .= ' AND day BETWEEN %s AND %s';
            $args[] = $from;
            $args[] = $to;
        }
        $table = self::stats_table();
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where holds one placeholder per value in $args, built above.
        $rows   = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, metric, SUM(value) AS total FROM {$table} WHERE {$where} GROUP BY post_id, metric", $args ), ARRAY_A );
        $totals = [];
        foreach ( (array) $rows as $row ) {
            $totals[ (int) $row['post_id'] ][ $row['metric'] ] = (int) $row['total'];
        }
        return $totals;
    }

    /**
     * Site-wide daily series for one metric, oldest first: 'Y-m-d' => count. Includes the
     * pre-1.0.5 site-wide series copied in by migrate().
     *
     * @return array<string, int>
     */
    public static function daily_series( $metric ) {
        global $wpdb;
        $table  = self::stats_table();
        $rows   = $wpdb->get_results( $wpdb->prepare( "SELECT day, SUM(value) AS total FROM {$table} WHERE metric = %s AND day > %s GROUP BY day ORDER BY day", $metric, self::LEGACY_DAY ), ARRAY_A );
        $series = [];
        foreach ( (array) $rows as $row ) {
            $series[ $row['day'] ] = (int) $row['total'];
        }
        return $series;
    }

    /**
     * A page's most-clicked links (live and pre-1.0.5 clicks combined).
     *
     * @return array<int, array{href: string, label: string, count: int}>
     */
    public static function links_for_post( $post_id, $limit = 20 ) {
        global $wpdb;
        $table = self::links_table();
        $rows  = $wpdb->get_results( $wpdb->prepare( "SELECT href, label, clicks + legacy_clicks AS total FROM {$table} WHERE post_id = %d ORDER BY total DESC, href ASC LIMIT %d", $post_id, $limit ), ARRAY_A );
        $links = [];
        foreach ( (array) $rows as $row ) {
            $links[] = [ 'href' => $row['href'], 'label' => $row['label'], 'count' => (int) $row['total'] ];
        }
        return $links;
    }

    /**
     * Copies pre-1.0.5 data from the old options. Every write sets (never adds) values in places
     * live tracking does not write, so repeating it after an interruption is safe. The old options
     * are left in place so a downgrade still shows data up to the upgrade.
     */
    public static function migrate() {
        global $wpdb;
        // Only one request at a time; a lock older than ten minutes is treated as abandoned.
        if ( ! add_option( 'lpmanager_pro_migration_lock', time(), '', false ) ) {
            if ( time() - (int) get_option( 'lpmanager_pro_migration_lock' ) < 600 ) {
                return;
            }
            update_option( 'lpmanager_pro_migration_lock', time(), false );
        }
        $stats = self::stats_table();

        $legacy = [];
        $engagement = get_option( 'lpmanager_engagement', [] );
        foreach ( is_array( $engagement ) ? $engagement : [] as $post_id => $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            foreach ( [ 'time_sum', 'time_count', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100' ] as $metric ) {
                $legacy[] = [ (int) $post_id, self::LEGACY_DAY, $metric, (int) ( $row[ $metric ] ?? 0 ) ];
            }
        }
        $heatmap = get_option( 'lpmanager_heatmap', [] );
        foreach ( is_array( $heatmap ) ? $heatmap : [] as $post_id => $grid ) {
            foreach ( array_values( is_array( $grid ) ? $grid : [] ) as $cell => $count ) {
                if ( $cell < 100 ) {
                    $legacy[] = [ (int) $post_id, self::LEGACY_DAY, self::heat_metric( $cell ), (int) $count ];
                }
            }
        }
        foreach ( [ 'views' => 'lpmanager_daily_visits', 'conversions' => 'lpmanager_daily_conversions' ] as $metric => $option ) {
            $series = get_option( $option, [] );
            foreach ( is_array( $series ) ? $series : [] as $day => $count ) {
                if ( is_string( $day ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
                    $legacy[] = [ self::SITE_POST_ID, $day, $metric, (int) $count ];
                }
            }
        }
        foreach ( array_chunk( array_filter( $legacy, function ( $row ) {
            return $row[3] > 0;
        } ), 500 ) as $chunk ) {
            $values = implode( ', ', array_map( function ( $row ) use ( $wpdb ) {
                return $wpdb->prepare( '(%d, %s, %s, %d)', $row[0], $row[1], $row[2], $row[3] );
            }, $chunk ) );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Each row tuple is prepared.
            $wpdb->query( "INSERT INTO {$stats} (post_id, day, metric, value) VALUES {$values} ON DUPLICATE KEY UPDATE value = VALUES(value)" );
        }

        $links = get_option( 'lpmanager_link_clicks', [] );
        $links = is_array( $links ) ? Tracking_Manager::clean_link_rows( $links ) : [];
        $rows  = [];
        foreach ( $links as $post_id => $page_links ) {
            foreach ( array_slice( $page_links, 0, self::MAX_LINKS_PER_PAGE, true ) as $key => $link ) {
                $rows[] = $wpdb->prepare( '(%d, %s, %s, %s, %d)', $post_id, $key, $link['href'], $link['label'], $link['count'] );
            }
        }
        foreach ( array_chunk( $rows, 500 ) as $chunk ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Each row tuple is prepared.
            $wpdb->query( 'INSERT INTO ' . self::links_table() . ' (post_id, link_key, href, label, legacy_clicks) VALUES ' . implode( ', ', $chunk ) . ' ON DUPLICATE KEY UPDATE legacy_clicks = VALUES(legacy_clicks)' );
        }

        update_option( self::MIGRATED_OPTION, self::today(), true );
        delete_option( 'lpmanager_pro_migration_lock' );
    }

    /** Metric name for a heatmap cell (0-99). */
    public static function heat_metric( $cell ) {
        return sprintf( 'heat_%02d', (int) $cell );
    }
}
