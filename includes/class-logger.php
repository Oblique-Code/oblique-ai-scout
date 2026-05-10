<?php
/**
 * Logger Class
 *
 * Handles dual-table database operations for bot visit logging.
 * Table 1 (hits): aggregated daily counts per bot + URL.
 * Table 2 (requests): individual raw request rows for detailed views.
 *
 * @package Oblique_AI_Scout
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Oblique_Logger
 */
class Oblique_Logger {

	/**
	 * Initialize the logger.
	 *
	 * @return void
	 */
	public static function init() {
		// No specific initialization needed.
	}

	/**
	 * Get the aggregate hits table name.
	 *
	 * @return string
	 */
	public static function get_hits_table() {
		global $wpdb;
		return $wpdb->prefix . 'oblique_ai_hits';
	}

	/**
	 * Get the raw requests table name.
	 *
	 * @return string
	 */
	public static function get_requests_table() {
		global $wpdb;
		return $wpdb->prefix . 'oblique_ai_requests';
	}

	/**
	 * Log a bot hit into both tables.
	 *
	 * @param string $bot_name   Detected bot label.
	 * @param string $url_path   Normalized URL path.
	 * @param string $user_agent Full user agent string.
	 * @param string $ip_address Anonymized IP address.
	 * @return void
	 */
	public static function log_hit( $bot_name, $url_path, $user_agent, $ip_address ) {
		global $wpdb;

		$date = current_time( 'Y-m-d' );
		$hash = md5( $url_path );

		$hits_table     = self::get_hits_table();
		$requests_table = self::get_requests_table();

		// Atomic upsert into aggregate table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix is safe.
				"INSERT INTO {$hits_table} (hit_date, bot_name, url_path, url_hash, hits) VALUES (%s, %s, %s, %s, 1) ON DUPLICATE KEY UPDATE hits = hits + 1",
				$date,
				$bot_name,
				$url_path,
				$hash
			)
		);

		// Raw request row for detailed log view.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$requests_table,
			array(
				'hit_at'     => current_time( 'mysql' ),
				'bot_name'   => $bot_name,
				'url_path'   => $url_path,
				'url_hash'   => $hash,
				'user_agent' => $user_agent,
				'ip_address' => $ip_address,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	// ------------------------------------------------------------------
	// Dashboard statistics
	// ------------------------------------------------------------------

	/**
	 * Get total request count for a time period.
	 *
	 * @param string $since_dt MySQL datetime string.
	 * @return int
	 */
	public static function get_total_since( $since_dt ) {
		global $wpdb;
		$table = self::get_requests_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix is safe.
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE hit_at >= %s", $since_dt )
		);

		return $result ? (int) $result : 0;
	}

	/**
	 * Get total request count (all time).
	 *
	 * @return int
	 */
	public static function get_total_all() {
		global $wpdb;
		$table = self::get_requests_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix is safe.
		$result = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		return $result ? (int) $result : 0;
	}

	/**
	 * Get daily hit counts for the last N days (for trend chart).
	 *
	 * @param string $since_dt MySQL datetime string.
	 * @return array Array of objects with ->d (date) and ->c (count).
	 */
	public static function get_daily_trend( $since_dt ) {
		global $wpdb;
		$table = self::get_requests_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix is safe.
				"SELECT DATE(hit_at) AS d, COUNT(*) AS c FROM {$table} WHERE hit_at >= %s GROUP BY DATE(hit_at) ORDER BY d ASC",
				$since_dt
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get top bots by hit count within a time period (for bar chart).
	 *
	 * @param string $since_dt MySQL datetime string.
	 * @param int    $limit    Maximum bots to return.
	 * @return array Array of objects with ->bot_name and ->c.
	 */
	public static function get_top_bots( $since_dt, $limit = 8 ) {
		global $wpdb;
		$table = self::get_requests_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix is safe.
				"SELECT bot_name, COUNT(*) AS c FROM {$table} WHERE hit_at >= %s GROUP BY bot_name ORDER BY c DESC LIMIT %d",
				$since_dt,
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	// ------------------------------------------------------------------
	// Filterable request log with pagination
	// ------------------------------------------------------------------

	/**
	 * Build WHERE clause and args array from filter parameters.
	 *
	 * @param array $filters Associative array of filter values.
	 * @return array Array with 'where' and 'args' keys.
	 */
	public static function build_query_filters( $filters ) {
		global $wpdb;

		$where = array();
		$args  = array();

		if ( ! empty( $filters['bot'] ) ) {
			$where[] = 'bot_name LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $filters['bot'] ) . '%';
		}
		if ( ! empty( $filters['path'] ) ) {
			$where[] = 'url_path LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $filters['path'] ) . '%';
		}
		if ( ! empty( $filters['ip'] ) ) {
			$where[] = 'ip_address LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $filters['ip'] ) . '%';
		}
		if ( ! empty( $filters['from'] ) ) {
			$where[] = 'hit_at >= %s';
			$args[]  = $filters['from'] . ' 00:00:00';
		}
		if ( ! empty( $filters['to'] ) ) {
			$where[] = 'hit_at <= %s';
			$args[]  = $filters['to'] . ' 23:59:59';
		}

		return array(
			'where' => $where,
			'args'  => $args,
		);
	}

	/**
	 * Get filtered request rows with pagination.
	 *
	 * @param array $filters  Associative filter values (bot, path, ip, from, to).
	 * @param int   $per_page Rows per page.
	 * @param int   $paged    Current page number.
	 * @return array Array with 'rows', 'total', and 'total_pages'.
	 */
	public static function get_filtered_requests( $filters, $per_page = 50, $paged = 1 ) {
		global $wpdb;
		$table = self::get_requests_table();

		$query_parts = self::build_query_filters( $filters );
		$where_parts = ! empty( $query_parts['where'] ) ? $query_parts['where'] : array( '1=1' );
		$args        = $query_parts['args'];
		$offset      = ( $paged - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where_parts );

		// Total count.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and dynamic where parts are safe.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $count_sql );
		}

		// Fetch page.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and dynamic where parts are safe.
		$list_sql  = "SELECT id, hit_at, bot_name, url_path, ip_address FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$list_args = array_merge( $args, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ) );

		return array(
			'rows'        => $rows,
			'total'       => $total,
			'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}

	/**
	 * Get distinct bot names that exist in the requests table.
	 *
	 * @return array List of bot name strings.
	 */
	public static function get_logged_bot_names() {
		global $wpdb;
		$table = self::get_requests_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix is safe.
		$results = $wpdb->get_col( "SELECT DISTINCT bot_name FROM {$table} ORDER BY bot_name ASC LIMIT 200" );

		return is_array( $results ) ? $results : array();
	}

	// ------------------------------------------------------------------
	// Delete operations
	// ------------------------------------------------------------------

	/**
	 * Delete selected request rows by ID.
	 *
	 * @param array $ids Array of integer IDs.
	 * @return int Number of rows deleted.
	 */
	public static function delete_selected( $ids ) {
		global $wpdb;
		$table = self::get_requests_table();

		$ids = array_map( 'absint', $ids );
		$ids = array_values( array_filter( $ids, function ( $i ) { return $i > 0; } ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );

		return $deleted;
	}

	/**
	 * Delete request rows matching filters.
	 *
	 * @param array $filters Associative filter values.
	 * @return int Number of rows deleted.
	 */
	public static function delete_filtered( $filters ) {
		global $wpdb;
		$table = self::get_requests_table();

		$query_parts = self::build_query_filters( $filters );
		$where_parts = ! empty( $query_parts['where'] ) ? $query_parts['where'] : array( '1=1' );
		$args        = $query_parts['args'];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix is safe.
		$sql = "DELETE FROM {$table} WHERE " . implode( ' AND ', $where_parts );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$deleted = (int) $wpdb->query( $wpdb->prepare( $sql, $args ) );

		return $deleted;
	}

	/**
	 * Get visited URL paths within a time period (for blind spots analysis).
	 *
	 * @param int $days Number of days to look back.
	 * @return array List of URL path strings.
	 */
	public static function get_visited_paths( $days = 30 ) {
		global $wpdb;
		$table = self::get_requests_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_col(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix is safe.
				"SELECT DISTINCT url_path FROM {$table} WHERE hit_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Check if the requests table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table = self::get_requests_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return $result === $table;
	}
}
