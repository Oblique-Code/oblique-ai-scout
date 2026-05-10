<?php
/**
 * Blindspot Analyzer Class
 *
 * Identifies published pages that have NOT been crawled by AI bots
 * and calculates an AI Discovery Score for each.
 *
 * @package Oblique_AI_Scout
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Oblique_Blindspot_Analyzer
 */
class Oblique_Blindspot_Analyzer {

	/**
	 * Get pages not visited by AI bots, with pagination.
	 *
	 * @param int  $days          Number of days to look back.
	 * @param int  $page          Current page number.
	 * @param int  $per_page      Items per page.
	 * @param bool $force_refresh Force cache refresh.
	 * @return array Array with pages, total, per_page, current_page, total_pages.
	 */
	public static function get_ignored_pages( $days = 30, $page = 1, $per_page = 50, $force_refresh = false ) {
		$cache_key = 'oblique_ai_scout_blindspots_' . $days;

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				$total  = count( $cached );
				$offset = ( $page - 1 ) * $per_page;

				return array(
					'pages'        => array_slice( $cached, $offset, $per_page ),
					'total'        => $total,
					'per_page'     => $per_page,
					'current_page' => $page,
					'total_pages'  => (int) ceil( $total / $per_page ),
				);
			}
		}

		// Get all published posts and pages.
		$post_args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		/**
		 * Filter the post query args for blind spot analysis.
		 *
		 * @param array $post_args WP_Query args.
		 */
		$post_args = (array) apply_filters( 'oblique_ai_scout_blindspot_post_args', $post_args );

		$all_ids = get_posts( $post_args );

		if ( empty( $all_ids ) ) {
			return self::empty_result( $per_page, $page );
		}

		// Get paths visited by AI bots.
		$visited_paths = Oblique_Logger::get_visited_paths( $days );

		// Find pages whose permalink path is NOT in visited set.
		$ignored = array();

		foreach ( $all_ids as $post_id ) {
			$permalink = get_permalink( $post_id );
			$path      = (string) wp_parse_url( $permalink, PHP_URL_PATH );

			if ( ! in_array( $path, $visited_paths, true ) ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}

				$ignored[] = array(
					'id'        => $post_id,
					'title'     => $post->post_title,
					'type'      => $post->post_type,
					'date'      => $post->post_date,
					'permalink' => $permalink,
					'ai_score'  => self::calculate_score( $post ),
				);
			}
		}

		// Sort by score ascending (lowest = needs most attention).
		usort(
			$ignored,
			function ( $a, $b ) {
				return $a['ai_score'] - $b['ai_score'];
			}
		);

		// Cache for 6 hours.
		set_transient( $cache_key, $ignored, 6 * HOUR_IN_SECONDS );

		$total  = count( $ignored );
		$offset = ( $page - 1 ) * $per_page;

		return array(
			'pages'        => array_slice( $ignored, $offset, $per_page ),
			'total'        => $total,
			'per_page'     => $per_page,
			'current_page' => $page,
			'total_pages'  => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}

	/**
	 * Calculate AI Discovery Score for a post (0–100).
	 *
	 * Higher score = better optimized for AI discovery.
	 *
	 * @param WP_Post $post The post object.
	 * @return int Score from 0 to 100.
	 */
	public static function calculate_score( $post ) {
		$score = 100;

		// Content length.
		$word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
		if ( $word_count < 100 ) {
			$score -= 30;
		} elseif ( $word_count < 300 ) {
			$score -= 20;
		} elseif ( $word_count < 500 ) {
			$score -= 10;
		}

		// Title quality.
		if ( empty( $post->post_title ) || strlen( $post->post_title ) < 10 ) {
			$score -= 15;
		}

		// Excerpt presence.
		if ( empty( $post->post_excerpt ) ) {
			$score -= 10;
		}

		// Featured image.
		if ( ! has_post_thumbnail( $post->ID ) ) {
			$score -= 5;
		}

		// Post age penalty.
		$age_days = ( time() - strtotime( $post->post_date ) ) / DAY_IN_SECONDS;
		if ( $age_days > 365 ) {
			$score -= 5;
		}

		// Yoast SEO checks (if available).
		if ( class_exists( 'WPSEO_Meta' ) && method_exists( 'WPSEO_Meta', 'get_value' ) ) {
			$meta_desc = WPSEO_Meta::get_value( 'metadesc', $post->ID );
			if ( empty( $meta_desc ) ) {
				$score -= 15;
			}
			$noindex = WPSEO_Meta::get_value( 'meta-robots-noindex', $post->ID );
			if ( '1' === $noindex ) {
				$score -= 40;
			}
		}

		/**
		 * Filter the AI discovery score for a post.
		 *
		 * @param int     $score   Calculated score.
		 * @param WP_Post $post    The post object.
		 */
		$score = (int) apply_filters( 'oblique_ai_scout_discovery_score', $score, $post );

		return max( 0, min( 100, $score ) );
	}

	/**
	 * Return an empty pagination result set.
	 *
	 * @param int $per_page Items per page.
	 * @param int $page     Current page.
	 * @return array
	 */
	private static function empty_result( $per_page, $page ) {
		return array(
			'pages'        => array(),
			'total'        => 0,
			'per_page'     => $per_page,
			'current_page' => $page,
			'total_pages'  => 0,
		);
	}
}
