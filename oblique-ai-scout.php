<?php
/**
 * Plugin Name: Oblique AI Scout
 * Plugin URI:  https://github.com/Oblique-Code/oblique-ai-scout
 * Description: Monitor AI crawler activity on your WordPress site. Detects 56+ bots including GPTBot, ClaudeBot, PerplexityBot, Gemini, and more — all with zero external API calls.
 * Version:     1.0.0
 * Author:      Oblique Code
 * Author URI:  https://github.com/Oblique-Code
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: oblique-ai-scout
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'OBLIQUE_AI_SCOUT_VERSION', '1.0.0' );
define( 'OBLIQUE_AI_SCOUT_DB_VERSION', '1.0.0' );
define( 'OBLIQUE_AI_SCOUT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OBLIQUE_AI_SCOUT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OBLIQUE_AI_SCOUT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Performance and security constants.
define( 'OBLIQUE_AI_SCOUT_CACHE_DURATION', 300 );
define( 'OBLIQUE_AI_SCOUT_MAX_EXPORT_ROWS', 10000 );
define( 'OBLIQUE_AI_SCOUT_CLEANUP_DAYS', 90 );

/**
 * Activation hook — Create database tables and schedule cron.
 */
register_activation_hook( __FILE__, 'oblique_ai_scout_activate' );

/**
 * Run on plugin activation.
 *
 * @return void
 */
function oblique_ai_scout_activate() {
	oblique_ai_scout_create_tables();

	if ( ! wp_next_scheduled( 'oblique_ai_scout_daily_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'oblique_ai_scout_daily_cleanup' );
	}

	update_option( 'oblique_ai_scout_version', OBLIQUE_AI_SCOUT_VERSION );
	update_option( 'oblique_ai_scout_db_version', OBLIQUE_AI_SCOUT_DB_VERSION );
}

/**
 * Create or upgrade database tables.
 *
 * @return void
 */
function oblique_ai_scout_create_tables() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// Aggregate daily hits.
	$hits_table = $wpdb->prefix . 'oblique_ai_hits';
	$sql_hits   = "CREATE TABLE {$hits_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		hit_date date NOT NULL,
		bot_name varchar(64) NOT NULL,
		url_path text NOT NULL,
		url_hash char(32) NOT NULL,
		hits bigint(20) unsigned NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		UNIQUE KEY hit (hit_date, bot_name, url_hash),
		KEY bot_date (bot_name, hit_date),
		KEY url_hash (url_hash)
	) {$charset_collate};";

	dbDelta( $sql_hits );

	// Raw individual requests.
	$requests_table = $wpdb->prefix . 'oblique_ai_requests';
	$sql_requests   = "CREATE TABLE {$requests_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		hit_at datetime NOT NULL,
		bot_name varchar(64) NOT NULL,
		url_path text NOT NULL,
		url_hash char(32) NOT NULL,
		user_agent text NOT NULL,
		ip_address varchar(45) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY bot_at (bot_name, hit_at),
		KEY url_hash (url_hash),
		KEY idx_ip (ip_address)
	) {$charset_collate};";

	dbDelta( $sql_requests );
}

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, 'oblique_ai_scout_deactivate' );

/**
 * Run on plugin deactivation.
 *
 * @return void
 */
function oblique_ai_scout_deactivate() {
	wp_clear_scheduled_hook( 'oblique_ai_scout_daily_cleanup' );
}

/**
 * Check DB version on admin_init and upgrade if needed.
 */
add_action( 'admin_init', 'oblique_ai_scout_maybe_upgrade_db' );

/**
 * Compare stored DB version and run upgrade if needed.
 *
 * @return void
 */
function oblique_ai_scout_maybe_upgrade_db() {
	$installed = (string) get_option( 'oblique_ai_scout_db_version', '' );
	if ( $installed !== OBLIQUE_AI_SCOUT_DB_VERSION ) {
		oblique_ai_scout_create_tables();
		update_option( 'oblique_ai_scout_db_version', OBLIQUE_AI_SCOUT_DB_VERSION );
	}
}

/**
 * Include required class files.
 */
require_once OBLIQUE_AI_SCOUT_PLUGIN_DIR . 'includes/class-bot-detector.php';
require_once OBLIQUE_AI_SCOUT_PLUGIN_DIR . 'includes/class-logger.php';
require_once OBLIQUE_AI_SCOUT_PLUGIN_DIR . 'includes/class-robots-checker.php';
require_once OBLIQUE_AI_SCOUT_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once OBLIQUE_AI_SCOUT_PLUGIN_DIR . 'includes/class-csv-exporter.php';
require_once OBLIQUE_AI_SCOUT_PLUGIN_DIR . 'includes/class-blindspot-analyzer.php';

/**
 * Initialize plugin components.
 */
add_action( 'plugins_loaded', 'oblique_ai_scout_init' );

/**
 * Bootstrap all plugin classes.
 *
 * @return void
 */
function oblique_ai_scout_init() {
	Oblique_Bot_Detector::init();
	Oblique_Logger::init();
	Oblique_Admin_Page::init();
	Oblique_CSV_Exporter::init();
}

/**
 * Daily cron: prune old requests in batches.
 */
add_action( 'oblique_ai_scout_daily_cleanup', 'oblique_ai_scout_cleanup_old_data' );

/**
 * Delete old request rows in batches.
 *
 * @return void
 */
function oblique_ai_scout_cleanup_old_data() {
	global $wpdb;

	$days_to_keep   = (int) apply_filters( 'oblique_ai_scout_days_to_keep', OBLIQUE_AI_SCOUT_CLEANUP_DAYS );
	$requests_table = $wpdb->prefix . 'oblique_ai_requests';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe ($wpdb->prefix).
			"DELETE FROM {$requests_table} WHERE hit_at < DATE_SUB(NOW(), INTERVAL %d DAY) LIMIT 1000",
			$days_to_keep
		)
	);
}

/**
 * Add settings link to plugin row.
 */
add_filter( 'plugin_action_links_' . OBLIQUE_AI_SCOUT_PLUGIN_BASENAME, 'oblique_ai_scout_action_links' );

/**
 * Insert Dashboard link into plugin action links.
 *
 * @param array $links Existing links.
 * @return array Modified links.
 */
function oblique_ai_scout_action_links( $links ) {
	$dashboard_link = '<a href="' . esc_url( admin_url( 'admin.php?page=oblique-ai-scout' ) ) . '">'
		. esc_html__( 'Dashboard', 'oblique-ai-scout' ) . '</a>';
	array_unshift( $links, $dashboard_link );
	return $links;
}

