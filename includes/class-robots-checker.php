<?php
/**
 * Robots Checker Class
 *
 * Parses robots.txt to check if AI bots are allowed or blocked.
 * Caches the robots.txt content for 1 hour to avoid repeated HTTP calls.
 *
 * @package Oblique_AI_Scout
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Oblique_Robots_Checker
 */
class Oblique_Robots_Checker {

	/**
	 * Mapping of bot display names to their robots.txt user-agent directives.
	 *
	 * Only includes major bots that typically have robots.txt rules.
	 *
	 * @var array
	 */
	private static $bot_ua_map = array(
		'GPTBot'             => 'GPTBot',
		'ChatGPT-User'       => 'ChatGPT-User',
		'OAI-SearchBot'      => 'OAI-SearchBot',
		'ClaudeBot'          => 'ClaudeBot',
		'Claude-Web'         => 'Claude-Web',
		'Anthropic-AI'       => 'Anthropic-AI',
		'PerplexityBot'      => 'PerplexityBot',
		'Google-Extended'    => 'Google-Extended',
		'Gemini-AI'          => 'Gemini-AI',
		'Bytespider'         => 'Bytespider',
		'CCBot'              => 'CCBot',
		'Amazonbot'          => 'Amazonbot',
		'Applebot-Extended'  => 'Applebot-Extended',
		'Meta-ExternalAgent' => 'Meta-ExternalAgent',
		'xAI-Bot'           => 'xAI-Bot',
		'DeepSeekBot'        => 'DeepSeekBot',
		'Diffbot'            => 'Diffbot',
		'FacebookBot'        => 'FacebookBot',
		'Cohere-AI'          => 'Cohere-AI',
	);

	/**
	 * Check if a specific bot is blocked by robots.txt.
	 *
	 * @param string $bot_name The bot display name.
	 * @return array Associative array with 'status' and 'message'.
	 */
	public static function check_bot_status( $bot_name ) {
		$user_agent = isset( self::$bot_ua_map[ $bot_name ] ) ? self::$bot_ua_map[ $bot_name ] : null;

		if ( ! $user_agent ) {
			return array(
				'status'  => 'unknown',
				'message' => __( 'Unknown bot', 'oblique-ai-scout' ),
			);
		}

		$robots_content = self::fetch_robots_txt();

		if ( false === $robots_content ) {
			return array(
				'status'  => 'unknown',
				'message' => __( 'Could not fetch robots.txt', 'oblique-ai-scout' ),
			);
		}

		$is_blocked = self::parse_robots_txt( $robots_content, $user_agent );

		return array(
			'status'  => $is_blocked ? 'blocked' : 'allowed',
			'message' => $is_blocked
				? __( 'Blocked by robots.txt', 'oblique-ai-scout' )
				: __( 'Allowed', 'oblique-ai-scout' ),
		);
	}

	/**
	 * Fetch robots.txt content with 1-hour transient cache.
	 *
	 * @return string|false Content or false on error.
	 */
	private static function fetch_robots_txt() {
		$cache_key = 'oblique_ai_scout_robots_txt';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$robots_url = home_url( '/robots.txt' );

		$response = wp_remote_get(
			$robots_url,
			array(
				'timeout'   => 5,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		set_transient( $cache_key, $body, HOUR_IN_SECONDS );

		return $body;
	}

	/**
	 * Parse robots.txt content to determine if a user agent is blocked.
	 *
	 * @param string $content   robots.txt content.
	 * @param string $target_ua User agent string to check.
	 * @return bool True if blocked, false if allowed.
	 */
	private static function parse_robots_txt( $content, $target_ua ) {
		$lines = explode( "\n", $content );

		$found_match = false;
		$is_blocked  = false;

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip empty lines and comments.
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			// User-agent directive.
			if ( 0 === stripos( $line, 'User-agent:' ) ) {
				$ua_value = trim( str_ireplace( 'User-agent:', '', $line ) );

				// If we already found a specific match, stop processing.
				if ( $found_match && strtolower( $ua_value ) !== strtolower( $target_ua ) && '*' !== $ua_value ) {
					break;
				}

				$found_match = false;
				if ( '*' === $ua_value || strtolower( $ua_value ) === strtolower( $target_ua ) ) {
					$found_match = true;
					$is_blocked  = false; // Reset for this block.
				}
				continue;
			}

			// Disallow directive.
			if ( 0 === stripos( $line, 'Disallow:' ) && $found_match ) {
				$disallow_value = trim( str_ireplace( 'Disallow:', '', $line ) );
				if ( '/' === $disallow_value ) {
					$is_blocked = true;
				}
			}

			// Allow directive (can override Disallow).
			if ( 0 === stripos( $line, 'Allow:' ) && $found_match ) {
				$allow_value = trim( str_ireplace( 'Allow:', '', $line ) );
				if ( '/' === $allow_value ) {
					$is_blocked = false;
				}
			}
		}

		return $is_blocked;
	}

	/**
	 * Get status for all bots in the map.
	 *
	 * @return array Associative array of bot_name => status info.
	 */
	public static function get_all_bot_statuses() {
		$statuses = array();

		foreach ( self::$bot_ua_map as $bot_name => $ua ) {
			$statuses[ $bot_name ] = self::check_bot_status( $bot_name );
		}

		return $statuses;
	}

	/**
	 * Get the robots.txt URL for the current site.
	 *
	 * @return string
	 */
	public static function get_robots_url() {
		return home_url( '/robots.txt' );
	}
}
