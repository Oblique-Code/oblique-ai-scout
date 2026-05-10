<?php
/**
 * CSV Exporter Class
 *
 * Handles secure, memory-safe CSV exports of crawler log data.
 *
 * @package Oblique_AI_Scout
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Oblique_CSV_Exporter
 */
class Oblique_CSV_Exporter {

	/**
	 * Hook into admin_init for early export handling.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_export' ) );
	}

	/**
	 * Check if an export was requested and handle it.
	 *
	 * @return void
	 */
	public static function maybe_export() {
		if (
			! isset( $_GET['oblique_export'] ) ||
			'1' !== $_GET['oblique_export'] ||
			! isset( $_GET['page'] ) ||
			'oblique-ai-scout' !== $_GET['page']
		) {
			return;
		}

		self::handle_export();
	}

	/**
	 * Execute the CSV export.
	 *
	 * @return void
	 */
	private static function handle_export() {
		// Capability check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'oblique-ai-scout' ) );
		}

		// Nonce verification.
		if (
			! isset( $_GET['_oblique_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_oblique_nonce'] ) ), 'oblique_export_csv' )
		) {
			wp_die( esc_html__( 'Security check failed.', 'oblique-ai-scout' ) );
		}

		global $wpdb;
		$table = Oblique_Logger::get_requests_table();

		// Collect filters.
		$filters = array();
		foreach ( array( 'bot', 'path', 'ip', 'from', 'to' ) as $key ) {
			if ( isset( $_GET[ $key ] ) && '' !== trim( $_GET[ $key ] ) ) {
				$filters[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}

		$query_parts = Oblique_Logger::build_query_filters( $filters );
		$where_parts = ! empty( $query_parts['where'] ) ? $query_parts['where'] : array( '1=1' );
		$args        = $query_parts['args'];

		$sql = 'SELECT hit_at, bot_name, url_path, ip_address, user_agent FROM ' . $table
			. ' WHERE ' . implode( ' AND ', $where_parts )
			. ' ORDER BY id DESC LIMIT ' . OBLIQUE_AI_SCOUT_MAX_EXPORT_ROWS;

		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $sql );
		}

		if ( empty( $rows ) ) {
			$rows = array(
				(object) array(
					'hit_at'     => wp_date( 'Y-m-d H:i:s' ),
					'bot_name'   => 'No Data',
					'url_path'   => 'No AI bot visits match your current filters',
					'ip_address' => '',
					'user_agent' => '',
				),
			);
		}

		// Send CSV headers.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="oblique-ai-scout-' . wp_date( 'Y-m-d-His' ) . '.csv"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// Header row.
		fputcsv(
			$output,
			array(
				esc_html__( 'Date/Time', 'oblique-ai-scout' ),
				esc_html__( 'Bot Name', 'oblique-ai-scout' ),
				esc_html__( 'URL Path', 'oblique-ai-scout' ),
				esc_html__( 'IP Address', 'oblique-ai-scout' ),
				esc_html__( 'User Agent', 'oblique-ai-scout' ),
			)
		);

		// Data rows with memory safety check.
		$row_count = 0;
		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row->hit_at,
					$row->bot_name,
					$row->url_path,
					$row->ip_address,
					$row->user_agent,
				)
			);

			if ( 0 === ++$row_count % 1000 && memory_get_usage() > 100 * 1024 * 1024 ) {
				fputcsv(
					$output,
					array(
						wp_date( 'Y-m-d H:i:s' ),
						'Export Limited',
						'Memory limit reached. Export truncated at ' . $row_count . ' rows.',
						'',
						'',
					)
				);
				break;
			}
		}

		fclose( $output );
		exit;
	}
}
