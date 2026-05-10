<?php
/**
 * Dashboard View — Single Page Layout
 *
 * No tabs. A scrollable dashboard with sections:
 * 1. Metric banner
 * 2. Activity timeline (left) + Bot leaderboard (right)
 * 3. Content insights cards
 *
 * @package Oblique_AI_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oblique_now_ts = current_time( 'timestamp' );
$oblique_d7_dt  = wp_date( 'Y-m-d H:i:s', $oblique_now_ts - 7 * DAY_IN_SECONDS );
$oblique_d30_dt = wp_date( 'Y-m-d H:i:s', $oblique_now_ts - 30 * DAY_IN_SECONDS );
$oblique_d90_dt = wp_date( 'Y-m-d H:i:s', $oblique_now_ts - 90 * DAY_IN_SECONDS );

$oblique_total_all = Oblique_Logger::get_total_all();
$oblique_total_7   = Oblique_Logger::get_total_since( $oblique_d7_dt );
$oblique_total_30  = Oblique_Logger::get_total_since( $oblique_d30_dt );

$oblique_daily_rows  = Oblique_Logger::get_daily_trend( $oblique_d30_dt );
$oblique_topbot_rows = Oblique_Logger::get_top_bots( $oblique_d7_dt, 6 );

// Recent activity feed (latest 15).
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$oblique_per_page = isset( $_GET['per_page'] ) ? max( 10, min( 100, absint( $_GET['per_page'] ) ) ) : 15;
$oblique_paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable

$oblique_result     = Oblique_Logger::get_filtered_requests( array(), $oblique_per_page, $oblique_paged );
$oblique_feed_rows  = $oblique_result['rows'];
$oblique_feed_total = $oblique_result['total'];
$oblique_feed_pages = $oblique_result['total_pages'];

// Content insights.
$oblique_radar            = Oblique_Blindspot_Analyzer::get_ignored_pages( 30, 1, 6 );
$oblique_undiscovered     = $oblique_radar['total'];
$oblique_undiscovered_list = $oblique_radar['pages'];

$oblique_published_posts = wp_count_posts( 'post' );
$oblique_published_pages = wp_count_posts( 'page' );
$oblique_total_published = (int) $oblique_published_posts->publish + (int) $oblique_published_pages->publish;
$oblique_coverage        = $oblique_total_published > 0
	? (int) round( ( ( $oblique_total_published - $oblique_undiscovered ) / $oblique_total_published ) * 100 )
	: 0;

// Handle POST delete.
$oblique_notice = '';
if ( isset( $_POST['oblique_action'] ) ) {
	check_admin_referer( 'oblique_log_action', 'oblique_log_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized.', 'oblique-ai-scout' ) );
	}

	$oblique_action = sanitize_text_field( wp_unslash( $_POST['oblique_action'] ) );

	if ( 'delete_selected' === $oblique_action ) {
		$oblique_ids     = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : array();
		$oblique_deleted = Oblique_Logger::delete_selected( $oblique_ids );
		/* translators: %d = number of deleted entries. */
		$oblique_notice = sprintf( esc_html__( '%d entries removed.', 'oblique-ai-scout' ), $oblique_deleted );
		// Refresh data.
		$oblique_result     = Oblique_Logger::get_filtered_requests( array(), $oblique_per_page, 1 );
		$oblique_feed_rows  = $oblique_result['rows'];
		$oblique_feed_total = $oblique_result['total'];
		$oblique_feed_pages = $oblique_result['total_pages'];
		$oblique_paged      = 1;
	}
}
?>

<div class="wrap oais-wrap">

	<!-- ── Header ── -->
	<div class="oais-header">
		<div class="oais-header__left">
			<h1 class="oais-header__title">
				<span class="dashicons dashicons-visibility"></span>
				<?php echo esc_html__( 'AI Scout', 'oblique-ai-scout' ); ?>
			</h1>
			<span class="oais-badge"><?php echo esc_html( 'v' . OBLIQUE_AI_SCOUT_VERSION ); ?></span>
		</div>
		<div class="oais-header__right">
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
			<a href="<?php echo esc_url( $oblique_export_url ); ?>" class="button">
				📥 <?php echo esc_html__( 'Export CSV', 'oblique-ai-scout' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=oblique-ai-scout-settings' ) ); ?>" class="button">
				⚙️ <?php echo esc_html__( 'Settings', 'oblique-ai-scout' ); ?>
			</a>
		</div>
	</div>

	<?php if ( '' !== $oblique_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $oblique_notice ); ?></p></div>
	<?php endif; ?>

	<!-- ── Metric Banner ── -->
	<div class="oais-banner">
		<div class="oais-banner__item">
			<span class="oais-banner__number"><?php echo esc_html( number_format_i18n( $oblique_total_all ) ); ?></span>
			<span class="oais-banner__text"><?php echo esc_html__( 'Total Visits', 'oblique-ai-scout' ); ?></span>
		</div>
		<div class="oais-banner__divider"></div>
		<div class="oais-banner__item">
			<span class="oais-banner__number"><?php echo esc_html( number_format_i18n( $oblique_total_7 ) ); ?></span>
			<span class="oais-banner__text"><?php echo esc_html__( 'Last 7 Days', 'oblique-ai-scout' ); ?></span>
		</div>
		<div class="oais-banner__divider"></div>
		<div class="oais-banner__item">
			<span class="oais-banner__number"><?php echo esc_html( number_format_i18n( $oblique_total_30 ) ); ?></span>
			<span class="oais-banner__text"><?php echo esc_html__( 'Last 30 Days', 'oblique-ai-scout' ); ?></span>
		</div>
		<div class="oais-banner__divider"></div>
		<div class="oais-banner__item">
			<span class="oais-banner__number"><?php echo esc_html( $oblique_coverage ); ?>%</span>
			<span class="oais-banner__text"><?php echo esc_html__( 'AI Coverage', 'oblique-ai-scout' ); ?></span>
		</div>
	</div>

	<!-- ── Main Grid: Chart + Bot Leaderboard ── -->
	<div class="oais-grid oais-grid--2col">

		<!-- 30-Day Sparkline -->
		<div class="oais-panel">
			<div class="oais-panel__head">
				<h2><?php echo esc_html__( '30-Day Activity', 'oblique-ai-scout' ); ?></h2>
			</div>
			<div class="oais-panel__body">
				<?php
				$oblique_daily_max = 0;
				foreach ( $oblique_daily_rows as $oblique_r ) {
					$oblique_daily_max = max( $oblique_daily_max, (int) $oblique_r->c );
				}

				if ( ! empty( $oblique_daily_rows ) && $oblique_daily_max > 0 ) :
					$oblique_w  = 620;
					$oblique_h  = 160;
					$oblique_ml = 36;
					$oblique_mr = 8;
					$oblique_mt = 12;
					$oblique_mb = 22;
					$oblique_pw = $oblique_w - $oblique_ml - $oblique_mr;
					$oblique_ph = $oblique_h - $oblique_mt - $oblique_mb;
					$oblique_n  = count( $oblique_daily_rows );
					$oblique_st = ( $oblique_n > 1 ) ? $oblique_pw / ( $oblique_n - 1 ) : 0;

					$oblique_pts = array();
					$oblique_i   = 0;
					foreach ( $oblique_daily_rows as $oblique_r ) {
						$oblique_yv = (int) $oblique_r->c;
						$oblique_cx = ( $oblique_n > 1 ) ? $oblique_ml + $oblique_i * $oblique_st : $oblique_ml + $oblique_pw / 2;
						$oblique_cy = $oblique_mt + $oblique_ph - ( $oblique_daily_max > 0 ? ( $oblique_yv / $oblique_daily_max ) * $oblique_ph : 0 );
						$oblique_pts[] = array( (int) round( $oblique_cx ), (int) round( $oblique_cy ), (string) $oblique_r->d, $oblique_yv );
						$oblique_i++;
					}

					$oblique_poly = '';
					foreach ( $oblique_pts as $oblique_p ) {
						$oblique_poly .= $oblique_p[0] . ',' . $oblique_p[1] . ' ';
					}

					$oblique_area = 'M ' . $oblique_ml . ' ' . ( $oblique_mt + $oblique_ph ) . ' ';
					foreach ( $oblique_pts as $oblique_p ) {
						$oblique_area .= 'L ' . $oblique_p[0] . ' ' . $oblique_p[1] . ' ';
					}
					$oblique_area .= 'L ' . ( $oblique_ml + $oblique_pw ) . ' ' . ( $oblique_mt + $oblique_ph ) . ' Z';
					?>
					<svg role="img" aria-label="<?php echo esc_attr__( '30-day activity', 'oblique-ai-scout' ); ?>" width="100%" viewBox="0 0 <?php echo (int) $oblique_w; ?> <?php echo (int) $oblique_h; ?>">
						<defs>
							<linearGradient id="oaisGrad" x1="0" y1="0" x2="0" y2="1">
								<stop offset="0%" stop-color="#6c5ce7" stop-opacity="0.3"/>
								<stop offset="100%" stop-color="#6c5ce7" stop-opacity="0.02"/>
							</linearGradient>
						</defs>
						<?php for ( $oblique_g = 0; $oblique_g <= 3; $oblique_g++ ) :
							$oblique_frac = $oblique_g / 3.0;
							$oblique_gy   = $oblique_mt + $oblique_ph - $oblique_ph * $oblique_frac;
							$oblique_val  = (int) round( $oblique_daily_max * $oblique_frac );
							?>
							<line x1="<?php echo (int) $oblique_ml; ?>" y1="<?php echo (int) $oblique_gy; ?>" x2="<?php echo (int) ( $oblique_ml + $oblique_pw ); ?>" y2="<?php echo (int) $oblique_gy; ?>" stroke="#eee" stroke-width="1"/>
							<text x="<?php echo (int) ( $oblique_ml - 6 ); ?>" y="<?php echo (int) ( $oblique_gy + 4 ); ?>" font-size="9" text-anchor="end" fill="#999"><?php echo esc_html( $oblique_val ); ?></text>
						<?php endfor; ?>
						<path d="<?php echo esc_attr( $oblique_area ); ?>" fill="url(#oaisGrad)"/>
						<polyline points="<?php echo esc_attr( trim( $oblique_poly ) ); ?>" fill="none" stroke="#6c5ce7" stroke-width="2" stroke-linecap="round"/>
						<?php foreach ( $oblique_pts as $oblique_p ) : ?>
							<circle cx="<?php echo (int) $oblique_p[0]; ?>" cy="<?php echo (int) $oblique_p[1]; ?>" r="2" fill="#6c5ce7"><title><?php echo esc_html( $oblique_p[2] . ': ' . $oblique_p[3] ); ?></title></circle>
						<?php endforeach; ?>
					</svg>
				<?php else : ?>
					<div class="oais-empty">
						<span class="dashicons dashicons-chart-area"></span>
						<p><?php echo esc_html__( 'No activity recorded yet. AI bot visits will appear here.', 'oblique-ai-scout' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Bot Leaderboard -->
		<div class="oais-panel">
			<div class="oais-panel__head">
				<h2><?php echo esc_html__( 'Bot Leaderboard', 'oblique-ai-scout' ); ?></h2>
				<span class="oais-panel__sub"><?php echo esc_html__( '7 days', 'oblique-ai-scout' ); ?></span>
			</div>
			<div class="oais-panel__body">
				<?php if ( ! empty( $oblique_topbot_rows ) ) : ?>
					<ol class="oais-leaderboard">
						<?php
						$oblique_rank = 1;
						foreach ( $oblique_topbot_rows as $oblique_r ) :
							$oblique_name   = (string) $oblique_r->bot_name;
							$oblique_count  = (int) $oblique_r->c;
							$oblique_domain = Oblique_Bot_Detector::get_bot_favicon_domain( $oblique_name );
							$oblique_icon   = $oblique_domain ? 'https://www.google.com/s2/favicons?sz=20&domain=' . rawurlencode( $oblique_domain ) : '';
							?>
							<li class="oais-leaderboard__item">
								<span class="oais-leaderboard__rank">#<?php echo (int) $oblique_rank; ?></span>
								<?php if ( $oblique_icon ) : ?>
									<img src="<?php echo esc_url( $oblique_icon ); ?>" alt="" width="20" height="20" class="oais-leaderboard__icon"/>
								<?php else : ?>
									<span class="oais-leaderboard__icon oais-leaderboard__icon--placeholder">🤖</span>
								<?php endif; ?>
								<span class="oais-leaderboard__name"><?php echo esc_html( $oblique_name ); ?></span>
								<span class="oais-leaderboard__count"><?php echo esc_html( number_format_i18n( $oblique_count ) ); ?></span>
							</li>
							<?php
							$oblique_rank++;
						endforeach;
						?>
					</ol>
				<?php else : ?>
					<div class="oais-empty">
						<span class="dashicons dashicons-groups"></span>
						<p><?php echo esc_html__( 'No bots detected yet.', 'oblique-ai-scout' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- ── Activity Timeline ── -->
	<div class="oais-panel oais-panel--wide">
		<div class="oais-panel__head">
			<h2><?php echo esc_html__( 'Recent Activity', 'oblique-ai-scout' ); ?></h2>
			<span class="oais-panel__sub"><?php echo esc_html( number_format_i18n( $oblique_feed_total ) . ' ' . __( 'total', 'oblique-ai-scout' ) ); ?></span>
		</div>
		<div class="oais-panel__body oais-panel__body--flush">
			<?php if ( ! empty( $oblique_feed_rows ) ) : ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'oblique_log_action', 'oblique_log_nonce' ); ?>
					<div class="oais-timeline">
						<?php foreach ( $oblique_feed_rows as $oblique_row ) :
							$oblique_bot      = (string) $oblique_row->bot_name;
							$oblique_path_v   = (string) $oblique_row->url_path;
							$oblique_ip_str   = (string) $oblique_row->ip_address;
							$oblique_hit_time = (string) $oblique_row->hit_at;

							$oblique_domain  = Oblique_Bot_Detector::get_bot_favicon_domain( $oblique_bot );
							$oblique_icon_u  = $oblique_domain ? 'https://www.google.com/s2/favicons?sz=20&domain=' . rawurlencode( $oblique_domain ) : '';

							$oblique_time_diff = time() - strtotime( $oblique_hit_time );
							if ( $oblique_time_diff < 60 ) {
								$oblique_ago = __( 'Just now', 'oblique-ai-scout' );
							} elseif ( $oblique_time_diff < 3600 ) {
								/* translators: %d = number of minutes ago. */
								$oblique_ago = sprintf( __( '%d min ago', 'oblique-ai-scout' ), (int) floor( $oblique_time_diff / 60 ) );
							} elseif ( $oblique_time_diff < 86400 ) {
								/* translators: %d = number of hours ago. */
								$oblique_ago = sprintf( __( '%d hr ago', 'oblique-ai-scout' ), (int) floor( $oblique_time_diff / 3600 ) );
							} else {
								$oblique_ago = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $oblique_hit_time, true );
							}
							?>
							<div class="oais-timeline__row">
								<label class="oais-timeline__check">
									<input type="checkbox" name="ids[]" value="<?php echo (int) $oblique_row->id; ?>" class="oais-cb"/>
								</label>
								<div class="oais-timeline__icon">
									<?php if ( $oblique_icon_u ) : ?>
										<img src="<?php echo esc_url( $oblique_icon_u ); ?>" alt="" width="20" height="20"/>
									<?php else : ?>
										<span>🤖</span>
									<?php endif; ?>
								</div>
								<div class="oais-timeline__content">
									<strong class="oais-timeline__bot"><?php echo esc_html( $oblique_bot ); ?></strong>
									<span class="oais-timeline__verb"><?php echo esc_html__( 'visited', 'oblique-ai-scout' ); ?></span>
									<a href="<?php echo esc_url( home_url( $oblique_path_v ) ); ?>" target="_blank" rel="noopener" class="oais-timeline__path"><?php echo esc_html( $oblique_path_v ); ?></a>
								</div>
								<div class="oais-timeline__meta">
									<?php if ( $oblique_ip_str ) : ?>
										<span class="oais-timeline__ip"><?php echo esc_html( $oblique_ip_str ); ?></span>
									<?php endif; ?>
									<span class="oais-timeline__time"><?php echo esc_html( $oblique_ago ); ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="oais-timeline__actions">
						<button type="submit" name="oblique_action" value="delete_selected" class="button" onclick="return confirm(obliqueScout.i18n.confirmDelete);">
							🗑️ <?php echo esc_html__( 'Delete Selected', 'oblique-ai-scout' ); ?>
						</button>

						<?php if ( $oblique_feed_pages > 1 ) : ?>
							<div class="oais-timeline__paging">
								<?php
								$oblique_prev = max( 1, $oblique_paged - 1 );
								$oblique_next = min( $oblique_feed_pages, $oblique_paged + 1 );
								$oblique_base = admin_url( 'admin.php?page=oblique-ai-scout&per_page=' . $oblique_per_page );
								?>
								<a class="button <?php echo ( $oblique_paged <= 1 ) ? 'disabled' : ''; ?>" href="<?php echo esc_url( $oblique_base . '&paged=' . $oblique_prev ); ?>">← <?php echo esc_html__( 'Newer', 'oblique-ai-scout' ); ?></a>
								<span class="oais-timeline__page"><?php echo (int) $oblique_paged; ?> / <?php echo (int) $oblique_feed_pages; ?></span>
								<a class="button <?php echo ( $oblique_paged >= $oblique_feed_pages ) ? 'disabled' : ''; ?>" href="<?php echo esc_url( $oblique_base . '&paged=' . $oblique_next ); ?>"><?php echo esc_html__( 'Older', 'oblique-ai-scout' ); ?> →</a>
							</div>
						<?php endif; ?>
					</div>
				</form>
			<?php else : ?>
				<div class="oais-empty oais-empty--padded">
					<span class="dashicons dashicons-clock"></span>
					<p><?php echo esc_html__( 'No bot visits recorded yet. Activity will appear here as AI crawlers visit your site.', 'oblique-ai-scout' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- ── Content Insights ── -->
	<?php if ( $oblique_undiscovered > 0 ) : ?>
		<div class="oais-panel oais-panel--wide">
			<div class="oais-panel__head">
				<h2><?php echo esc_html__( 'Content Insights', 'oblique-ai-scout' ); ?></h2>
				<span class="oais-panel__sub">
					<?php
					printf(
						/* translators: 1: number of undiscovered pages, 2: coverage percent. */
						esc_html__( '%1$d pages not yet found by AI · %2$d%% coverage', 'oblique-ai-scout' ),
						(int) $oblique_undiscovered,
						(int) $oblique_coverage
					);
					?>
				</span>
			</div>
			<div class="oais-panel__body">
				<div class="oais-insight-grid">
					<?php foreach ( $oblique_undiscovered_list as $oblique_pg ) :
						$oblique_score       = (int) $oblique_pg['ai_score'];
						$oblique_score_color = $oblique_score >= 70 ? '#00b894' : ( $oblique_score >= 40 ? '#fdcb6e' : '#e17055' );
						?>
						<div class="oais-insight-card">
							<div class="oais-insight-card__score" style="--score-color:<?php echo esc_attr( $oblique_score_color ); ?>;">
								<svg width="48" height="48" viewBox="0 0 48 48">
									<circle cx="24" cy="24" r="20" fill="none" stroke="#eee" stroke-width="4"/>
									<circle cx="24" cy="24" r="20" fill="none" stroke="<?php echo esc_attr( $oblique_score_color ); ?>" stroke-width="4" stroke-dasharray="<?php echo (int) ( 125.6 * $oblique_score / 100 ); ?> 125.6" stroke-linecap="round" transform="rotate(-90 24 24)"/>
									<text x="24" y="28" text-anchor="middle" font-size="12" font-weight="700" fill="<?php echo esc_attr( $oblique_score_color ); ?>"><?php echo (int) $oblique_score; ?></text>
								</svg>
							</div>
							<div class="oais-insight-card__info">
								<a href="<?php echo esc_url( get_edit_post_link( $oblique_pg['id'] ) ); ?>" class="oais-insight-card__title">
									<?php echo esc_html( $oblique_pg['title'] ? $oblique_pg['title'] : __( '(untitled)', 'oblique-ai-scout' ) ); ?>
								</a>
								<span class="oais-insight-card__type"><?php echo esc_html( ucfirst( $oblique_pg['type'] ) ); ?></span>
							</div>
							<a href="<?php echo esc_url( $oblique_pg['permalink'] ); ?>" target="_blank" rel="noopener" class="oais-insight-card__view" title="<?php echo esc_attr__( 'View page', 'oblique-ai-scout' ); ?>">↗</a>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $oblique_undiscovered > 6 ) : ?>
					<p class="oais-insight-more">
						<?php
						printf(
							/* translators: %d = remaining pages. */
							esc_html__( '+ %d more pages need attention.', 'oblique-ai-scout' ),
							(int) ( $oblique_undiscovered - 6 )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- ── Footer ── -->
	<div class="oais-footer">
		<p>
			<?php
			printf(
				/* translators: %s = Oblique Code link. */
				esc_html__( 'Built by %s — Tracking 56+ AI crawlers locally.', 'oblique-ai-scout' ),
				'<a href="https://obliquecode.com" target="_blank" rel="noopener">Oblique Code</a>'
			);
			?>
		</p>
	</div>
</div>
