<?php
/**
 * Settings View — Separate submenu page
 *
 * Sections: robots.txt status, data management, cache guidance.
 *
 * @package Oblique_AI_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle purge action.
$oblique_purge_notice = '';
if ( isset( $_POST['oblique_purge'] ) && '1' === $_POST['oblique_purge'] ) {
	check_admin_referer( 'oblique_settings_action', 'oblique_settings_nonce' );

	if ( current_user_can( 'manage_options' ) ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}oblique_ai_requests" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}oblique_ai_hits" );
		$oblique_purge_notice = __( 'All log data has been purged.', 'oblique-ai-scout' );
	}
}

// robots.txt data.
$oblique_bot_statuses = Oblique_Robots_Checker::get_all_bot_statuses();

// Detect caching plugin.
$oblique_detected_cache = '';
if ( defined( 'WP_ROCKET_VERSION' ) ) {
	$oblique_detected_cache = 'WP Rocket';
} elseif ( defined( 'LSCWP_V' ) || defined( 'LSCWP_VERSION' ) ) {
	$oblique_detected_cache = 'LiteSpeed Cache';
} elseif ( defined( 'W3TC' ) ) {
	$oblique_detected_cache = 'W3 Total Cache';
} elseif ( defined( 'WPCACHEHOME' ) ) {
	$oblique_detected_cache = 'WP Super Cache';
}

// DB stats.
$oblique_total_requests = Oblique_Logger::get_total_all();
$oblique_total_bots     = count( Oblique_Logger::get_logged_bot_names() );

// Bot patterns.
$oblique_bot_patterns = array(
	'gptbot', 'chatgpt-user', 'oai-searchbot',
	'claudebot', 'claude-web', 'claude-searchbot',
	'perplexitybot', 'google-extended', 'gemini-deep-research',
	'meta-externalagent', 'meta-externalfetcher',
	'mistralai-user', 'xai-bot', 'deepseekbot',
	'bytespider', 'amazonbot', 'applebot-extended',
	'ccbot', 'diffbot', 'cohere-ai', 'duckassistbot', 'devin',
);
?>

<!-- Toast -->
<div id="oblique-toast" class="oais-toast"><?php echo esc_html__( 'Copied!', 'oblique-ai-scout' ); ?></div>

<div class="wrap oais-wrap">
	<div class="oais-header">
		<div class="oais-header__left">
			<h1 class="oais-header__title">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php echo esc_html__( 'AI Scout Settings', 'oblique-ai-scout' ); ?>
			</h1>
		</div>
		<div class="oais-header__right">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=oblique-ai-scout' ) ); ?>" class="button">
				← <?php echo esc_html__( 'Back to Dashboard', 'oblique-ai-scout' ); ?>
			</a>
		</div>
	</div>

	<?php if ( '' !== $oblique_purge_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $oblique_purge_notice ); ?></p></div>
	<?php endif; ?>

	<div class="oais-settings-grid">

		<!-- Left Column -->
		<div class="oais-settings-col">

			<!-- Section: Data Management -->
			<div class="oais-panel">
				<div class="oais-panel__head">
					<h2>📊 <?php echo esc_html__( 'Data Management', 'oblique-ai-scout' ); ?></h2>
				</div>
				<div class="oais-panel__body">
					<div class="oais-data-stats">
						<div class="oais-data-stat">
							<span class="oais-data-stat__val"><?php echo esc_html( number_format_i18n( $oblique_total_requests ) ); ?></span>
							<span class="oais-data-stat__lbl"><?php echo esc_html__( 'Logged Requests', 'oblique-ai-scout' ); ?></span>
						</div>
						<div class="oais-data-stat">
							<span class="oais-data-stat__val"><?php echo esc_html( number_format_i18n( $oblique_total_bots ) ); ?></span>
							<span class="oais-data-stat__lbl"><?php echo esc_html__( 'Unique Bots', 'oblique-ai-scout' ); ?></span>
						</div>
						<div class="oais-data-stat">
							<span class="oais-data-stat__val"><?php echo esc_html( OBLIQUE_AI_SCOUT_CLEANUP_DAYS . 'd' ); ?></span>
							<span class="oais-data-stat__lbl"><?php echo esc_html__( 'Retention', 'oblique-ai-scout' ); ?></span>
						</div>
					</div>

					<?php
					$oblique_export_url = add_query_arg(
						array(
							'page'           => 'oblique-ai-scout',
							'oblique_export' => '1',
							'_oblique_nonce' => wp_create_nonce( 'oblique_export_csv' ),
						),
						admin_url( 'admin.php' )
					);
					?>
					<div class="oais-data-actions">
						<a href="<?php echo esc_url( $oblique_export_url ); ?>" class="button">
							📥 <?php echo esc_html__( 'Export All as CSV', 'oblique-ai-scout' ); ?>
						</a>

						<form method="post" action="" class="oais-purge-form">
							<?php wp_nonce_field( 'oblique_settings_action', 'oblique_settings_nonce' ); ?>
							<input type="hidden" name="oblique_purge" value="1"/>
							<button type="submit" class="button oais-btn--danger" onclick="return confirm(obliqueScout.i18n.confirmPurge);">
								🗑️ <?php echo esc_html__( 'Purge All Data', 'oblique-ai-scout' ); ?>
							</button>
						</form>
					</div>

					<p class="oais-hint">
						<?php echo esc_html__( 'Logs older than 90 days are automatically cleaned up daily.', 'oblique-ai-scout' ); ?>
					</p>
				</div>
			</div>

			<!-- Section: Cache Exclusion -->
			<div class="oais-panel">
				<div class="oais-panel__head">
					<h2>⚡ <?php echo esc_html__( 'Cache Exclusion', 'oblique-ai-scout' ); ?></h2>
				</div>
				<div class="oais-panel__body">
					<?php if ( '' !== $oblique_detected_cache ) : ?>
						<div class="oais-notice oais-notice--info">
							<?php
							printf(
								/* translators: %s = cache plugin name. */
								esc_html__( '%s detected on your site. Add the bot patterns below to its "excluded user agents" setting for accurate tracking.', 'oblique-ai-scout' ),
								'<strong>' . esc_html( $oblique_detected_cache ) . '</strong>'
							);
							?>
						</div>
					<?php else : ?>
						<p><?php echo esc_html__( 'If you use a caching plugin, exclude AI bot user agents so visits reach PHP and get tracked.', 'oblique-ai-scout' ); ?></p>
					<?php endif; ?>

					<div class="oais-ua-box" id="oblique-ua-patterns"><?php echo esc_html( implode( "\n", $oblique_bot_patterns ) ); ?></div>

					<button class="button button-primary" id="oblique-copy-btn">
						📋 <?php echo esc_html__( 'Copy Patterns', 'oblique-ai-scout' ); ?>
					</button>
					<span class="oais-hint" style="margin-left:8px;">
						<?php echo esc_html__( 'Paste into your caching plugin\'s "excluded user agents" field.', 'oblique-ai-scout' ); ?>
					</span>
				</div>
			</div>
		</div>

		<!-- Right Column -->
		<div class="oais-settings-col">

			<!-- Section: robots.txt Status -->
			<div class="oais-panel">
				<div class="oais-panel__head">
					<h2>🤖 <?php echo esc_html__( 'robots.txt Status', 'oblique-ai-scout' ); ?></h2>
					<a href="<?php echo esc_url( Oblique_Robots_Checker::get_robots_url() ); ?>" target="_blank" rel="noopener" class="oais-panel__link">
						<?php echo esc_html__( 'View file →', 'oblique-ai-scout' ); ?>
					</a>
				</div>
				<div class="oais-panel__body oais-panel__body--flush">
					<div class="oais-robots-list">
						<?php
						$oblique_allowed_count = 0;
						$oblique_blocked_count = 0;
						foreach ( $oblique_bot_statuses as $oblique_status ) {
							if ( 'allowed' === $oblique_status['status'] ) {
								$oblique_allowed_count++;
							} elseif ( 'blocked' === $oblique_status['status'] ) {
								$oblique_blocked_count++;
							}
						}
						?>

						<div class="oais-robots-summary">
							<span class="oais-robots-summary__item oais-robots-summary__item--ok">
								✅ <?php echo esc_html( $oblique_allowed_count . ' ' . __( 'allowed', 'oblique-ai-scout' ) ); ?>
							</span>
							<span class="oais-robots-summary__item oais-robots-summary__item--blocked">
								🚫 <?php echo esc_html( $oblique_blocked_count . ' ' . __( 'blocked', 'oblique-ai-scout' ) ); ?>
							</span>
						</div>

						<?php foreach ( $oblique_bot_statuses as $oblique_bot_name => $oblique_status ) :
							$oblique_dot_class = 'oais-dot--unknown';
							if ( 'allowed' === $oblique_status['status'] ) {
								$oblique_dot_class = 'oais-dot--ok';
							} elseif ( 'blocked' === $oblique_status['status'] ) {
								$oblique_dot_class = 'oais-dot--blocked';
							}
							?>
							<div class="oais-robots-row">
								<span class="oais-dot <?php echo esc_attr( $oblique_dot_class ); ?>"></span>
								<span class="oais-robots-row__name"><?php echo esc_html( $oblique_bot_name ); ?></span>
								<span class="oais-robots-row__status"><?php echo esc_html( ucfirst( $oblique_status['status'] ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- Section: About -->
			<div class="oais-panel">
				<div class="oais-panel__head">
					<h2>ℹ️ <?php echo esc_html__( 'About', 'oblique-ai-scout' ); ?></h2>
				</div>
				<div class="oais-panel__body">
					<dl class="oais-about">
						<dt><?php echo esc_html__( 'Version', 'oblique-ai-scout' ); ?></dt>
						<dd><?php echo esc_html( OBLIQUE_AI_SCOUT_VERSION ); ?></dd>

						<dt><?php echo esc_html__( 'Bots Tracked', 'oblique-ai-scout' ); ?></dt>
						<dd>56+</dd>

						<dt><?php echo esc_html__( 'Privacy', 'oblique-ai-scout' ); ?></dt>
						<dd><?php echo esc_html__( 'IPs anonymized, no external APIs', 'oblique-ai-scout' ); ?></dd>

						<dt><?php echo esc_html__( 'Auto-cleanup', 'oblique-ai-scout' ); ?></dt>
						<dd><?php echo esc_html__( 'Daily, 90-day retention', 'oblique-ai-scout' ); ?></dd>
					</dl>
				</div>
			</div>
		</div>
	</div>
</div>
