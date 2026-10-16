<?php
/**
 * Analytics storage (F6). Counters live in two custom tables updated with atomic SQL, so
 * simultaneous tracking requests no longer lose increments (B11) and no large option is
 * rewritten on every event.
 *
 * - {prefix}lpmanager_pro_stats: one row per landing page, UTC day, and metric. Heatmap cells are
 *   kept in a single all-time bucket (ALL_TIME_DAY) rather than per day, so the table grows by
 *   about 15 rows per page per day at most.
 * - {prefix}lpmanager_pro_links: one row per landing page and link.
 *
 * Per-page totals that Landing Page Manager reads directly (views, conversions, clicks, and the
 * interaction and rage-click counts) stay in post meta, incremented atomically as well.
 *
 * Data from before 1.0.5 is copied in by migrate(). It is kept apart from live data, in the
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

    /** Set to the UTC day the pre-1.0.5 options were copied into the tables. */
    const MIGRATED_OPTION = 'lpmanager_pro_storage_migrated';

    const MIGRATION_LOCK_OPTION = 'lpmanager_pro_migration_lock';

    /** Set while the tables could not be created; drives an admin notice. */
    const INSTALL_FAILED_OPTION = 'lpmanager_pro_storage_failed';

    /** Day bucket for data copied from before per-day storage existed (written only by migrate()). */
    const LEGACY_DAY = '1970-01-01';

    /** Day bucket for live metrics kept as all-time totals rather than per day (the heatmap). */
    const ALL_TIME_DAY = '1970-01-02';

    /** Post ID used for the site-wide daily series recorded before 1.0.5. */
    const SITE_POST_ID = 0;

    const MAX_LINKS_PER_PAGE = 200;

    /** Rows per INSERT during migration, which bounds statement size and memory. */
    const MIGRATION_CHUNK = 500;

    public static function stats_table() {
        global $wpdb;
        return $wpdb->prefix . 'lpmanager_pro_stats';
    }

    public static function links_table() {
        global $wpdb;
        return $wpdb->prefix . 'lpmanager_pro_links';
    }

    public static function init() {
        add_action( 'admin_notices', [ __CLASS__, 'maybe_show_install_notice' ] );
        // Multisite: drop a deleted site's tables with the rest of its tables.
        add_filter( 'wpmu_drop_tables', [ __CLASS__, 'add_tables_to_drop' ] );
    }

    /** Creates or upgrades the tables, then copies pre-1.0.5 data once. Cheap when up to date. */
    public static function maybe_install() {
        if ( (int) get_option( self::DB_VERSION_OPTION ) < self::DB_VERSION ) {
            // After a failed CREATE, retry on admin page loads or at most hourly, not on every tracking request.
            $failed = (int) get_option( self::INSTALL_FAILED_OPTION );
            if ( $failed && time() - $failed < HOUR_IN_SECONDS && ( ! is_admin() || wp_doing_ajax() ) ) {
                return;
            }
            if ( ! self::install() ) {
                return;
            }
        }
        $migrated = get_option( self::MIGRATED_OPTION );
        if ( ! $migrated || self::needs_remigration( $migrated ) ) {
            self::migrate();
        }
    }

    /**
     * A site that went back to 1.0.4 after migrating recorded daily counts in the old options;
     * copy them in again. Checked on admin page loads only, because it reads two options.
     */
    private static function needs_remigration( $migrated ) {
        if ( ! is_admin() || wp_doing_ajax() ) {
            return false;
        }
        foreach ( [ 'lpmanager_daily_visits', 'lpmanager_daily_conversions' ] as $option ) {
            $series = get_option( $option, [] );
            $days   = array_filter( array_keys( is_array( $series ) ? $series : [] ), function ( $day ) {
                return is_string( $day ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day );
            } );
            if ( $days && max( $days ) > $migrated ) {
                return true;
            }
        }
        return false;
    }

    /** @return bool Whether both tables exist afterwards. */
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
  KEY metric_day (metric,day)
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
        $exists = self::table_exists( $stats ) && self::table_exists( $links );
        if ( ! $exists ) {
            // Leave the version unset so the next request tries again (for example after the host grants CREATE).
            update_option( self::INSTALL_FAILED_OPTION, time(), false );
            return false;
        }
        delete_option( self::INSTALL_FAILED_OPTION );
        update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
        return true;
    }

    private static function table_exists( $table ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
    }

    public static function maybe_show_install_notice() {
        if ( ! get_option( self::INSTALL_FAILED_OPTION ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Landing Page Manager Pro could not create its analytics tables, so engagement, heatmap, and link data is not being saved. Ask your host to allow the database user to create tables; Pro tries again automatically on the next page load.', 'landing-page-manager-pro' ) . '</p></div>';
    }

    /** @param string[] $tables */
    public static function add_tables_to_drop( $tables ) {
        $tables[] = self::stats_table();
        $tables[] = self::links_table();
        return $tables;
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
        // VALUES() is deprecated in MySQL 8.0.20+ (it still works) but is the form MariaDB supports.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Each row tuple above is prepared.
        $wpdb->query( 'INSERT INTO ' . self::stats_table() . ' (post_id, day, metric, value) VALUES ' . implode( ', ', $rows ) . ' ON DUPLICATE KEY UPDATE value = value + VALUES(value)' );
    }

    /**
     * Atomically adds to a numeric post meta counter, creating it if missing. The free plugin reads
     * these totals directly, so they stay in post meta.
     */
    public static function increment_meta( $post_id, $meta_key, $amount = 1 ) {
        global $wpdb;
        // Only the first row: rows created by a first-hit race keep their insert value until merged below.
        $sql     = $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = CAST(COALESCE(NULLIF(meta_value, ''), '0') AS UNSIGNED) + %d WHERE post_id = %d AND meta_key = %s ORDER BY meta_id LIMIT 1", $amount, $post_id, $meta_key );
        $updated = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
        if ( ! $updated ) {
            if ( ! add_post_meta( $post_id, $meta_key, (int) $amount, true ) ) {
                // Another request created the row between the two statements.
                $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
            }
            // add_post_meta()'s unique check is not atomic: simultaneous first hits can create two rows.
            self::merge_duplicate_meta( $post_id, $meta_key );
        }
        wp_cache_delete( $post_id, 'post_meta' );
    }

    /** Folds duplicate rows of one counter into the first row (only after a first-hit race). */
    private static function merge_duplicate_meta( $post_id, $meta_key ) {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $post_id, $meta_key ), ARRAY_A );
        if ( count( (array) $rows ) < 2 ) {
            return;
        }
        $keep = array_shift( $rows );
        foreach ( $rows as $row ) {
            $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_id = %d", $row['meta_id'] ) );
            if ( $deleted ) {
                $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = CAST(COALESCE(NULLIF(meta_value, ''), '0') AS UNSIGNED) + %d WHERE meta_id = %d", (int) $row['meta_value'], $keep['meta_id'] ) );
            }
        }
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
     * Sums of every metric per page, all time (including data from before 1.0.5 and the all-time
     * heatmap) or for a UTC day range.
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
        $rows   = $wpdb->get_results( $wpdb->prepare( "SELECT day, SUM(value) AS total FROM {$table} WHERE metric = %s AND day > %s GROUP BY day ORDER BY day", $metric, self::ALL_TIME_DAY ), ARRAY_A );
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
     * live tracking does not write, so repeating it after an interruption, or after a downgrade
     * and re-upgrade, is safe. The old options are left in place so a downgrade still shows data
     * up to the upgrade; the link option is cleaned in place (P4) so no stored URL keeps a query string.
     *
     * @return bool Whether the copy completed.
     */
    public static function migrate() {
        // Only one request at a time; a lock older than ten minutes is treated as abandoned.
        if ( ! add_option( self::MIGRATION_LOCK_OPTION, time(), '', false ) ) {
            if ( time() - (int) get_option( self::MIGRATION_LOCK_OPTION ) < 600 ) {
                return false;
            }
            update_option( self::MIGRATION_LOCK_OPTION, time(), false );
        }
        $ok     = true;
        $buffer = [];
        $flush  = function () use ( &$buffer, &$ok ) {
            if ( $buffer ) {
                $ok     = self::insert_legacy_stats( $buffer ) && $ok;
                $buffer = [];
            }
        };
        $push   = function ( $post_id, $day, $metric, $value ) use ( &$buffer, $flush ) {
            if ( (int) $value > 0 ) {
                $buffer[] = [ (int) $post_id, $day, $metric, (int) $value ];
                if ( count( $buffer ) >= self::MIGRATION_CHUNK ) {
                    $flush();
                }
            }
        };

        $engagement = get_option( 'lpmanager_engagement', [] );
        foreach ( is_array( $engagement ) ? $engagement : [] as $post_id => $row ) {
            foreach ( [ 'time_sum', 'time_count', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100' ] as $metric ) {
                $push( $post_id, self::LEGACY_DAY, $metric, is_array( $row ) ? ( $row[ $metric ] ?? 0 ) : 0 );
            }
        }
        $heatmap = get_option( 'lpmanager_heatmap', [] );
        foreach ( is_array( $heatmap ) ? $heatmap : [] as $post_id => $grid ) {
            foreach ( is_array( $grid ) ? $grid : [] as $cell => $count ) {
                // Keep each cell's own position; only whole-number cells 0-99 are valid.
                if ( is_int( $cell ) && $cell >= 0 && $cell < 100 ) {
                    $push( $post_id, self::LEGACY_DAY, self::heat_metric( $cell ), $count );
                }
            }
        }
        foreach ( [ 'views' => 'lpmanager_daily_visits', 'conversions' => 'lpmanager_daily_conversions' ] as $metric => $option ) {
            $series = get_option( $option, [] );
            foreach ( is_array( $series ) ? $series : [] as $day => $count ) {
                if ( is_string( $day ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
                    $push( self::SITE_POST_ID, $day, $metric, $count );
                }
            }
        }
        $flush();

        $links = get_option( 'lpmanager_link_clicks', [] );
        if ( is_array( $links ) && $links ) {
            $links = Tracking_Manager::clean_link_rows( $links );
            // Nothing writes this option any more, so cleaning it in place cannot race with tracking.
            update_option( 'lpmanager_link_clicks', $links, false );
            $ok = self::insert_legacy_links( $links ) && $ok;
        }

        if ( $ok ) {
            update_option( self::MIGRATED_OPTION, self::today(), true );
            delete_option( self::MIGRATION_LOCK_OPTION );
        }
        // On failure the lock stays, so the next attempt waits ten minutes instead of running on every request.
        return $ok;
    }

    /** @param array<int, array{0: int, 1: string, 2: string, 3: int}> $rows */
    private static function insert_legacy_stats( array $rows ) {
        global $wpdb;
        $values = implode( ', ', array_map( function ( $row ) use ( $wpdb ) {
            return $wpdb->prepare( '(%d, %s, %s, %d)', $row[0], $row[1], $row[2], $row[3] );
        }, $rows ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Each row tuple is prepared.
        return false !== $wpdb->query( 'INSERT INTO ' . self::stats_table() . " (post_id, day, metric, value) VALUES {$values} ON DUPLICATE KEY UPDATE value = VALUES(value)" );
    }

    private static function insert_legacy_links( array $links ) {
        global $wpdb;
        $ok   = true;
        $rows = [];
        foreach ( $links as $post_id => $page_links ) {
            foreach ( array_slice( $page_links, 0, self::MAX_LINKS_PER_PAGE, true ) as $key => $link ) {
                $rows[] = $wpdb->prepare( '(%d, %s, %s, %s, %d)', $post_id, $key, $link['href'], $link['label'], $link['count'] );
                if ( count( $rows ) >= self::MIGRATION_CHUNK ) {
                    $ok   = self::insert_link_rows( $rows ) && $ok;
                    $rows = [];
                }
            }
        }
        return ( $rows ? self::insert_link_rows( $rows ) : true ) && $ok;
    }

    private static function insert_link_rows( array $rows ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Each row tuple is prepared.
        return false !== $wpdb->query( 'INSERT INTO ' . self::links_table() . ' (post_id, link_key, href, label, legacy_clicks) VALUES ' . implode( ', ', $rows ) . ' ON DUPLICATE KEY UPDATE legacy_clicks = VALUES(legacy_clicks)' );
    }

    /** Drops both tables and their options. Used only by the opt-in uninstall cleanup (P6). */
    public static function drop_tables() {
        global $wpdb;
        $stats = self::stats_table();
        $links = self::links_table();
        $wpdb->query( "DROP TABLE IF EXISTS {$stats}, {$links}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange -- Opt-in uninstall only.
    }

    /** Metric name for a heatmap cell (0-99). */
    public static function heat_metric( $cell ) {
        return sprintf( 'heat_%02d', (int) $cell );
    }
}
