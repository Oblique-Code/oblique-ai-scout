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

$now_ts = current_time( 'timestamp' );
$d7_dt  = wp_date( 'Y-m-d H:i:s', $now_ts - 7 * DAY_IN_SECONDS );
$d30_dt = wp_date( 'Y-m-d H:i:s', $now_ts - 30 * DAY_IN_SECONDS );
$d90_dt = wp_date( 'Y-m-d H:i:s', $now_ts - 90 * DAY_IN_SECONDS );

$total_all = Oblique_Logger::get_total_all();
$total_7   = Oblique_Logger::get_total_since( $d7_dt );
$total_30  = Oblique_Logger::get_total_since( $d30_dt );

$daily_rows  = Oblique_Logger::get_daily_trend( $d30_dt );
$topbot_rows = Oblique_Logger::get_top_bots( $d7_dt, 6 );

// Recent activity feed (latest 15).
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$per_page = isset( $_GET['per_page'] ) ? max( 10, min( 100, absint( $_GET['per_page'] ) ) ) : 15;
$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
// phpcs:enable

$result      = Oblique_Logger::get_filtered_requests( array(), $per_page, $paged );
$feed_rows   = $result['rows'];
$feed_total  = $result['total'];
$feed_pages  = $result['total_pages'];

// Content insights.
$radar = Oblique_Blindspot_Analyzer::get_ignored_pages( 30, 1, 6 );
$undiscovered      = $radar['total'];
$undiscovered_list = $radar['pages'];

$published_posts = wp_count_posts( 'post' );
$published_pages = wp_count_posts( 'page' );
$total_published = (int) $published_posts->publish + (int) $published_pages->publish;
$coverage        = $total_published > 0
	? (int) round( ( ( $total_published - $undiscovered ) / $total_published ) * 100 )
	: 0;

// Handle POST delete.
$notice = '';
if ( isset( $_POST['oblique_action'] ) ) {
	check_admin_referer( 'oblique_log_action', 'oblique_log_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Unauthorized.', 'oblique-ai-scout' ) );
	}

	$action = sanitize_text_field( wp_unslash( $_POST['oblique_action'] ) );

	if ( 'delete_selected' === $action ) {
		$ids     = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', $_POST['ids'] ) : array();
		$deleted = Oblique_Logger::delete_selected( $ids );
		/* translators: %d = number of deleted entries. */
		$notice = sprintf( esc_html__( '%d entries removed.', 'oblique-ai-scout' ), $deleted );
		// Refresh data.
		$result     = Oblique_Logger::get_filtered_requests( array(), $per_page, 1 );
		$feed_rows  = $result['rows'];
		$feed_total = $result['total'];
		$feed_pages = $result['total_pages'];
		$paged      = 1;
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
			$export_url = add_query_arg(
				array(
					'page'           => 'oblique-ai-scout',
					'oblique_export' => '1',
					'_oblique_nonce' => wp_create_nonce( 'oblique_export_csv' ),
				),
				admin_url( 'admin.php' )
			);
			?>
			<a href="<?php echo esc_url( $export_url ); ?>" class="button">
				📥 <?php echo esc_html__( 'Export CSV', 'oblique-ai-scout' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=oblique-ai-scout-settings' ) ); ?>" class="button">
				⚙️ <?php echo esc_html__( 'Settings', 'oblique-ai-scout' ); ?>
			</a>
		</div>
	</div>

	<?php if ( '' !== $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<!-- ── Metric Banner ── -->
	<div class="oais-banner">
		<div class="oais-banner__item">
			<span class="oais-banner__number"><?php echo esc_html( number_format_i18n( $total_all ) ); ?></span>
			<span class="oais-banner__text"><?php echo esc_html__( 'Total Visits', 'oblique-ai-scout' ); ?></span>
		</div>
		<div class="oais-banner__divider"></div>
		<div class="oais-banner__item">
			<span class="oais-banner__number"><?php echo esc_html( number_format_i18n( $total_7 ) ); ?></span>
			<span class="oais-banner__text"><?php echo esc_html__( 'Last 7 Days', 'oblique-ai-scout' ); ?></span>
		</div>
		<div class="oais-banner__divider"></div>
		<div class="oais-banner__item">
			<span class="oais-banner__number"><?php echo esc_html( number_format_i18n( $total_30 ) ); ?></span>
			<span class="oais-banner__text"><?php echo esc_html__( 'Last 30 Days', 'oblique-ai-scout' ); ?></span>
		</div>
		<div class="oais-banner__divider"></div>
		<div class="oais-banner__item">
			<span class="oais-banner__number"><?php echo esc_html( $coverage ); ?>%</span>
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
				$daily_max = 0;
				foreach ( $daily_rows as $r ) {
					$daily_max = max( $daily_max, (int) $r->c );
				}

				if ( ! empty( $daily_rows ) && $daily_max > 0 ) :
					$w  = 620;
					$h  = 160;
					$ml = 36;
					$mr = 8;
					$mt = 12;
					$mb = 22;
					$pw = $w - $ml - $mr;
					$ph = $h - $mt - $mb;
					$n  = count( $daily_rows );
					$st = ( $n > 1 ) ? $pw / ( $n - 1 ) : 0;

					$pts = array();
					$i   = 0;
					foreach ( $daily_rows as $r ) {
						$yv = (int) $r->c;
						$cx = ( $n > 1 ) ? $ml + $i * $st : $ml + $pw / 2;
						$cy = $mt + $ph - ( $daily_max > 0 ? ( $yv / $daily_max ) * $ph : 0 );
						$pts[] = array( (int) round( $cx ), (int) round( $cy ), (string) $r->d, $yv );
						$i++;
					}

					$poly = '';
					foreach ( $pts as $p ) {
						$poly .= $p[0] . ',' . $p[1] . ' ';
					}

					$area = 'M ' . $ml . ' ' . ( $mt + $ph ) . ' ';
					foreach ( $pts as $p ) {
						$area .= 'L ' . $p[0] . ' ' . $p[1] . ' ';
					}
					$area .= 'L ' . ( $ml + $pw ) . ' ' . ( $mt + $ph ) . ' Z';
					?>
					<svg role="img" aria-label="<?php echo esc_attr__( '30-day activity', 'oblique-ai-scout' ); ?>" width="100%" viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h; ?>">
						<defs>
							<linearGradient id="oaisGrad" x1="0" y1="0" x2="0" y2="1">
								<stop offset="0%" stop-color="#6c5ce7" stop-opacity="0.3"/>
								<stop offset="100%" stop-color="#6c5ce7" stop-opacity="0.02"/>
							</linearGradient>
						</defs>
						<?php for ( $g = 0; $g <= 3; $g++ ) :
							$frac = $g / 3.0;
							$gy   = $mt + $ph - $ph * $frac;
							$val  = (int) round( $daily_max * $frac );
							?>
							<line x1="<?php echo (int) $ml; ?>" y1="<?php echo (int) $gy; ?>" x2="<?php echo (int) ( $ml + $pw ); ?>" y2="<?php echo (int) $gy; ?>" stroke="#eee" stroke-width="1"/>
							<text x="<?php echo (int) ( $ml - 6 ); ?>" y="<?php echo (int) ( $gy + 4 ); ?>" font-size="9" text-anchor="end" fill="#999"><?php echo esc_html( $val ); ?></text>
						<?php endfor; ?>
						<path d="<?php echo esc_attr( $area ); ?>" fill="url(#oaisGrad)"/>
						<polyline points="<?php echo esc_attr( trim( $poly ) ); ?>" fill="none" stroke="#6c5ce7" stroke-width="2" stroke-linecap="round"/>
						<?php foreach ( $pts as $p ) : ?>
							<circle cx="<?php echo (int) $p[0]; ?>" cy="<?php echo (int) $p[1]; ?>" r="2" fill="#6c5ce7"><title><?php echo esc_html( $p[2] . ': ' . $p[3] ); ?></title></circle>
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
				<?php if ( ! empty( $topbot_rows ) ) : ?>
					<ol class="oais-leaderboard">
						<?php
						$rank = 1;
						foreach ( $topbot_rows as $r ) :
							$name   = (string) $r->bot_name;
							$count  = (int) $r->c;
							$domain = Oblique_Bot_Detector::get_bot_favicon_domain( $name );
							$icon   = $domain ? 'https://www.google.com/s2/favicons?sz=20&domain=' . rawurlencode( $domain ) : '';
							?>
							<li class="oais-leaderboard__item">
								<span class="oais-leaderboard__rank">#<?php echo (int) $rank; ?></span>
								<?php if ( $icon ) : ?>
									<img src="<?php echo esc_url( $icon ); ?>" alt="" width="20" height="20" class="oais-leaderboard__icon"/>
								<?php else : ?>
									<span class="oais-leaderboard__icon oais-leaderboard__icon--placeholder">🤖</span>
								<?php endif; ?>
								<span class="oais-leaderboard__name"><?php echo esc_html( $name ); ?></span>
								<span class="oais-leaderboard__count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							</li>
							<?php
							$rank++;
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
			<span class="oais-panel__sub"><?php echo esc_html( number_format_i18n( $feed_total ) . ' ' . __( 'total', 'oblique-ai-scout' ) ); ?></span>
		</div>
		<div class="oais-panel__body oais-panel__body--flush">
			<?php if ( ! empty( $feed_rows ) ) : ?>
				<form method="post" action="">
					<?php wp_nonce_field( 'oblique_log_action', 'oblique_log_nonce' ); ?>
					<div class="oais-timeline">
						<?php foreach ( $feed_rows as $row ) :
							$bot      = (string) $row->bot_name;
							$path_v   = (string) $row->url_path;
							$ip_str   = (string) $row->ip_address;
							$hit_time = (string) $row->hit_at;

							$domain  = Oblique_Bot_Detector::get_bot_favicon_domain( $bot );
							$icon_u  = $domain ? 'https://www.google.com/s2/favicons?sz=20&domain=' . rawurlencode( $domain ) : '';

							$time_diff = time() - strtotime( $hit_time );
							if ( $time_diff < 60 ) {
								$ago = __( 'Just now', 'oblique-ai-scout' );
							} elseif ( $time_diff < 3600 ) {
								$ago = sprintf( __( '%d min ago', 'oblique-ai-scout' ), (int) floor( $time_diff / 60 ) );
							} elseif ( $time_diff < 86400 ) {
								$ago = sprintf( __( '%d hr ago', 'oblique-ai-scout' ), (int) floor( $time_diff / 3600 ) );
							} else {
								$ago = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $hit_time, true );
							}
							?>
							<div class="oais-timeline__row">
								<label class="oais-timeline__check">
									<input type="checkbox" name="ids[]" value="<?php echo (int) $row->id; ?>" class="oais-cb"/>
								</label>
								<div class="oais-timeline__icon">
									<?php if ( $icon_u ) : ?>
										<img src="<?php echo esc_url( $icon_u ); ?>" alt="" width="20" height="20"/>
									<?php else : ?>
										<span>🤖</span>
									<?php endif; ?>
								</div>
								<div class="oais-timeline__content">
									<strong class="oais-timeline__bot"><?php echo esc_html( $bot ); ?></strong>
									<span class="oais-timeline__verb"><?php echo esc_html__( 'visited', 'oblique-ai-scout' ); ?></span>
									<a href="<?php echo esc_url( home_url( $path_v ) ); ?>" target="_blank" rel="noopener" class="oais-timeline__path"><?php echo esc_html( $path_v ); ?></a>
								</div>
								<div class="oais-timeline__meta">
									<?php if ( $ip_str ) : ?>
										<span class="oais-timeline__ip"><?php echo esc_html( $ip_str ); ?></span>
									<?php endif; ?>
									<span class="oais-timeline__time"><?php echo esc_html( $ago ); ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="oais-timeline__actions">
						<button type="submit" name="oblique_action" value="delete_selected" class="button" onclick="return confirm(obliqueScout.i18n.confirmDelete);">
							🗑️ <?php echo esc_html__( 'Delete Selected', 'oblique-ai-scout' ); ?>
						</button>

						<?php if ( $feed_pages > 1 ) : ?>
							<div class="oais-timeline__paging">
								<?php
								$prev = max( 1, $paged - 1 );
								$next = min( $feed_pages, $paged + 1 );
								$base = admin_url( 'admin.php?page=oblique-ai-scout&per_page=' . $per_page );
								?>
								<a class="button <?php echo ( $paged <= 1 ) ? 'disabled' : ''; ?>" href="<?php echo esc_url( $base . '&paged=' . $prev ); ?>">← <?php echo esc_html__( 'Newer', 'oblique-ai-scout' ); ?></a>
								<span class="oais-timeline__page"><?php echo (int) $paged; ?> / <?php echo (int) $feed_pages; ?></span>
								<a class="button <?php echo ( $paged >= $feed_pages ) ? 'disabled' : ''; ?>" href="<?php echo esc_url( $base . '&paged=' . $next ); ?>"><?php echo esc_html__( 'Older', 'oblique-ai-scout' ); ?> →</a>
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
	<?php if ( $undiscovered > 0 ) : ?>
		<div class="oais-panel oais-panel--wide">
			<div class="oais-panel__head">
				<h2><?php echo esc_html__( 'Content Insights', 'oblique-ai-scout' ); ?></h2>
				<span class="oais-panel__sub">
					<?php
					printf(
						/* translators: 1: number of undiscovered pages, 2: coverage percent. */
						esc_html__( '%1$d pages not yet found by AI · %2$d%% coverage', 'oblique-ai-scout' ),
						$undiscovered,
						$coverage
					);
					?>
				</span>
			</div>
			<div class="oais-panel__body">
				<div class="oais-insight-grid">
					<?php foreach ( $undiscovered_list as $pg ) :
						$score       = (int) $pg['ai_score'];
						$score_color = $score >= 70 ? '#00b894' : ( $score >= 40 ? '#fdcb6e' : '#e17055' );
						?>
						<div class="oais-insight-card">
							<div class="oais-insight-card__score" style="--score-color:<?php echo esc_attr( $score_color ); ?>;">
								<svg width="48" height="48" viewBox="0 0 48 48">
									<circle cx="24" cy="24" r="20" fill="none" stroke="#eee" stroke-width="4"/>
									<circle cx="24" cy="24" r="20" fill="none" stroke="<?php echo esc_attr( $score_color ); ?>" stroke-width="4" stroke-dasharray="<?php echo (int) ( 125.6 * $score / 100 ); ?> 125.6" stroke-linecap="round" transform="rotate(-90 24 24)"/>
									<text x="24" y="28" text-anchor="middle" font-size="12" font-weight="700" fill="<?php echo esc_attr( $score_color ); ?>"><?php echo (int) $score; ?></text>
								</svg>
							</div>
							<div class="oais-insight-card__info">
								<a href="<?php echo esc_url( get_edit_post_link( $pg['id'] ) ); ?>" class="oais-insight-card__title">
									<?php echo esc_html( $pg['title'] ? $pg['title'] : __( '(untitled)', 'oblique-ai-scout' ) ); ?>
								</a>
								<span class="oais-insight-card__type"><?php echo esc_html( ucfirst( $pg['type'] ) ); ?></span>
							</div>
							<a href="<?php echo esc_url( $pg['permalink'] ); ?>" target="_blank" rel="noopener" class="oais-insight-card__view" title="<?php echo esc_attr__( 'View page', 'oblique-ai-scout' ); ?>">↗</a>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $undiscovered > 6 ) : ?>
					<p class="oais-insight-more">
						<?php
						printf(
							/* translators: %d = remaining pages. */
							esc_html__( '+ %d more pages need attention.', 'oblique-ai-scout' ),
							$undiscovered - 6
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
