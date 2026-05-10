<?php
/**
 * Uninstall script for Oblique AI Scout
 *
 * Runs when the plugin is deleted from WordPress.
 * Removes all database tables, options, transients, and cron jobs.
 *
 * @package Oblique_AI_Scout
 */

// Prevent direct access.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom database tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oblique_ai_hits" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oblique_ai_requests" );

// Legacy table from v1.0.0.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oblique_ai_log" );

// Delete plugin options.
delete_option( 'oblique_ai_scout_version' );
delete_option( 'oblique_ai_scout_db_version' );
delete_option( 'oblique_ai_scout_settings' );

// Clear scheduled cron jobs.
wp_clear_scheduled_hook( 'oblique_ai_scout_daily_cleanup' );

// Legacy cron from v1.0.0.
wp_clear_scheduled_hook( 'oblique_prune_logs' );

// Delete transients.
delete_transient( 'oblique_ai_scout_robots_txt' );

// Delete blind spot transients (various day values).
foreach ( array( 7, 30, 90, 365 ) as $days ) {
	delete_transient( 'oblique_ai_scout_blindspots_' . $days );
}
