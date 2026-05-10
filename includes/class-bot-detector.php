<?php
/**
 * Bot Detector Class
 *
 * Detects AI/LLM crawler user agents on front-end requests
 * and triggers logging via Oblique_Logger.
 *
 * @package Oblique_AI_Scout
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Oblique_Bot_Detector
 */
class Oblique_Bot_Detector {

	/**
	 * Initialize the bot detector.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_log_hit' ), 1 );
	}

	/**
	 * Detect an AI bot on the current request and log the visit.
	 *
	 * Skips admin, AJAX, cron, REST API, feed, and 404 requests.
	 *
	 * @return void
	 */
	public static function maybe_log_hit() {
		// Skip non-frontend contexts.
		if ( is_admin() || wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return;
		}
		// Skip 404s — no value in logging error pages.
		if ( function_exists( 'is_404' ) && is_404() ) {
			return;
		}

		$user_agent = self::get_user_agent();
		if ( '' === $user_agent ) {
			return;
		}

		$bot_name = self::detect_bot_name( $user_agent );
		if ( '' === $bot_name ) {
			return;
		}

		// Build normalized URL path (no query string).
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: '/';

		$path = (string) wp_parse_url( home_url( $request_uri ), PHP_URL_PATH );
		if ( '' === $path ) {
			$path = '/';
		}

		// Resolve IP address (best-effort through proxies).
		$ip = self::resolve_ip();

		Oblique_Logger::log_hit( $bot_name, $path, $user_agent, $ip );
	}

	/**
	 * Get the user agent string from server variables.
	 *
	 * @return string Sanitized user agent or empty string.
	 */
	private static function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
	}

	/**
	 * Resolve the client IP address.
	 *
	 * Checks X-Forwarded-For first (first token), then falls back to REMOTE_ADDR.
	 * Anonymizes by zeroing the last IPv4 octet for privacy.
	 *
	 * @return string Anonymized IP address.
	 */
	private static function resolve_ip() {
		$ip = '';

		if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$xff   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$parts = array_map( 'trim', explode( ',', $xff ) );
			if ( ! empty( $parts[0] ) ) {
				$ip = $parts[0];
			}
		}

		if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return self::anonymize_ip( $ip );
	}

	/**
	 * Anonymize an IP address for GDPR-style privacy.
	 *
	 * IPv4: zeros the last octet (192.168.1.42 → 192.168.1.0).
	 * IPv6: keeps first 4 groups and zeros the rest.
	 *
	 * @param string $ip Raw IP address.
	 * @return string Anonymized IP.
	 */
	private static function anonymize_ip( $ip ) {
		$ip = trim( $ip );
		if ( '' === $ip ) {
			return '';
		}

		// IPv4.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			if ( 4 === count( $parts ) ) {
				$parts[3] = '0';
				return implode( '.', $parts );
			}
			return $ip;
		}

		// IPv6.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $packed ) {
				return $ip;
			}
			$hex = unpack( 'H*', $packed );
			$hex = is_array( $hex ) && isset( $hex[1] ) ? $hex[1] : '';
			if ( '' === $hex || 32 !== strlen( $hex ) ) {
				return $ip;
			}
			$groups = str_split( $hex, 4 );
			$out    = array_slice( $groups, 0, 4 );
			return strtolower( implode( ':', $out ) ) . '::';
		}

		return $ip;
	}

	/**
	 * Match a user agent string against known AI/LLM bot signatures.
	 *
	 * Uses a static variable so the array is only built once per request.
	 *
	 * @param string $user_agent The raw user agent string.
	 * @return string Bot label if matched, empty string otherwise.
	 */
	public static function detect_bot_name( $user_agent ) {
		if ( empty( $user_agent ) ) {
			return '';
		}

		$ua = strtolower( $user_agent );

		// Static array: built once, reused across calls in the same request.
		static $bots = null;
		if ( null === $bots ) {
			$bots = array(
				// OpenAI.
				'gptbot'                => 'GPTBot',
				'chatgpt-user'          => 'ChatGPT-User',
				'chatgpt-browser'       => 'ChatGPT-Browser',
				'oai-searchbot'         => 'OAI-SearchBot',
				// Anthropic.
				'claudebot'             => 'ClaudeBot',
				'claude-web'            => 'Claude-Web',
				'claude-searchbot'      => 'Claude-SearchBot',
				'claude-user'           => 'Claude-User',
				'anthropic-ai'          => 'Anthropic-AI',
				// Perplexity.
				'perplexitybot'         => 'PerplexityBot',
				'perplexity-user'       => 'Perplexity-User',
				// Google.
				'google-extended'       => 'Google-Extended',
				'google-cloudvertexbot' => 'Google-CloudVertexBot',
				'googleagent-mariner'   => 'GoogleAgent-Mariner',
				'gemini-deep-research'  => 'Gemini-Deep-Research',
				'gemini-ai'             => 'Gemini-AI',
				'bard-ai'              => 'Bard-AI',
				'apis-google'           => 'APIs-Google',
				// Meta.
				'meta-externalagent'    => 'Meta-ExternalAgent',
				'meta-externalfetcher'  => 'Meta-ExternalFetcher',
				'facebookexternalhit'   => 'FacebookExternalHit',
				'facebookbot'           => 'FacebookBot',
				'linkedinbot'           => 'LinkedInBot',
				// Mistral.
				'mistralai-user'        => 'MistralAI-User',
				'mistral le chat'       => 'Mistral-Le-Chat',
				// xAI / Grok.
				'xai-bot'              => 'xAI-Bot',
				// DeepSeek.
				'deepseekbot'           => 'DeepSeekBot',
				// ByteDance.
				'bytespider'            => 'Bytespider',
				// Amazon.
				'amazonbot'             => 'Amazonbot',
				'novaact'               => 'NovaAct',
				// Apple.
				'applebot-extended'     => 'Applebot-Extended',
				// Common Crawl.
				'ccbot'                 => 'CCBot',
				// Search & AI aggregators.
				'omgilibot'             => 'Omgilibot',
				'omgili'                => 'Omgili',
				'youbot'                => 'YouBot',
				'timpibot'              => 'Timpibot',
				'diffbot'               => 'Diffbot',
				'ai2bot'                => 'AI2Bot',
				'cohere-ai'             => 'Cohere-AI',
				'cohere-command'        => 'Cohere-Command',
				'duckassistbot'         => 'DuckAssistBot',
				'andibot'               => 'Andibot',
				'pangubot'              => 'PanguBot',
				'petalbot'              => 'PetalBot',
				// Infrastructure & tools.
				'devin'                 => 'Devin',
				'linerbot'              => 'LinerBot',
				'qualifiedbot'          => 'QualifiedBot',
				'proratainc'            => 'ProRataInc',
				'firecrawlagent'        => 'FirecrawlAgent',
				'huggingface-bot'       => 'HuggingFace-Bot',
				'character-ai'          => 'Character-AI',
				'groq-bot'              => 'Groq-Bot',
				'together-bot'          => 'Together-Bot',
				'replicate-bot'         => 'Replicate-Bot',
				'runpod-bot'            => 'RunPod-Bot',
				'brightbot'             => 'BrightBot',
				'webzio-extended'       => 'Webzio-Extended',
				'imagesiftbot'          => 'ImagesiftBot',
				'bigsur.ai'             => 'BigSur-AI',
			);

			/**
			 * Filter the bot detection list.
			 *
			 * @param array $bots Associative array of needle => label.
			 */
			$bots = (array) apply_filters( 'oblique_ai_scout_bot_list', $bots );
		}

		foreach ( $bots as $needle => $label ) {
			if ( false !== strpos( $ua, $needle ) ) {
				return $label;
			}
		}

		return '';
	}

	/**
	 * Get all supported bot labels as a flat array.
	 *
	 * @return array List of bot label strings.
	 */
	public static function get_bot_labels() {
		// Trigger static init by calling with a dummy UA.
		self::detect_bot_name( 'init' );

		static $labels = null;
		if ( null === $labels ) {
			$bots   = (array) apply_filters( 'oblique_ai_scout_bot_list', self::get_default_bots() );
			$labels = array_values( $bots );
		}
		return $labels;
	}

	/**
	 * Return the default bots array (for use in filters and label retrieval).
	 *
	 * @return array Associative array of needle => label.
	 */
	private static function get_default_bots() {
		return array(
			'gptbot'                => 'GPTBot',
			'chatgpt-user'          => 'ChatGPT-User',
			'chatgpt-browser'       => 'ChatGPT-Browser',
			'oai-searchbot'         => 'OAI-SearchBot',
			'claudebot'             => 'ClaudeBot',
			'claude-web'            => 'Claude-Web',
			'claude-searchbot'      => 'Claude-SearchBot',
			'claude-user'           => 'Claude-User',
			'anthropic-ai'          => 'Anthropic-AI',
			'perplexitybot'         => 'PerplexityBot',
			'perplexity-user'       => 'Perplexity-User',
			'google-extended'       => 'Google-Extended',
			'google-cloudvertexbot' => 'Google-CloudVertexBot',
			'googleagent-mariner'   => 'GoogleAgent-Mariner',
			'gemini-deep-research'  => 'Gemini-Deep-Research',
			'gemini-ai'             => 'Gemini-AI',
			'bard-ai'              => 'Bard-AI',
			'apis-google'           => 'APIs-Google',
			'meta-externalagent'    => 'Meta-ExternalAgent',
			'meta-externalfetcher'  => 'Meta-ExternalFetcher',
			'facebookexternalhit'   => 'FacebookExternalHit',
			'facebookbot'           => 'FacebookBot',
			'linkedinbot'           => 'LinkedInBot',
			'mistralai-user'        => 'MistralAI-User',
			'mistral le chat'       => 'Mistral-Le-Chat',
			'xai-bot'              => 'xAI-Bot',
			'deepseekbot'           => 'DeepSeekBot',
			'bytespider'            => 'Bytespider',
			'amazonbot'             => 'Amazonbot',
			'novaact'               => 'NovaAct',
			'applebot-extended'     => 'Applebot-Extended',
			'ccbot'                 => 'CCBot',
			'omgilibot'             => 'Omgilibot',
			'omgili'                => 'Omgili',
			'youbot'                => 'YouBot',
			'timpibot'              => 'Timpibot',
			'diffbot'               => 'Diffbot',
			'ai2bot'                => 'AI2Bot',
			'cohere-ai'             => 'Cohere-AI',
			'cohere-command'        => 'Cohere-Command',
			'duckassistbot'         => 'DuckAssistBot',
			'andibot'               => 'Andibot',
			'pangubot'              => 'PanguBot',
			'petalbot'              => 'PetalBot',
			'devin'                 => 'Devin',
			'linerbot'              => 'LinerBot',
			'qualifiedbot'          => 'QualifiedBot',
			'proratainc'            => 'ProRataInc',
			'firecrawlagent'        => 'FirecrawlAgent',
			'huggingface-bot'       => 'HuggingFace-Bot',
			'character-ai'          => 'Character-AI',
			'groq-bot'              => 'Groq-Bot',
			'together-bot'          => 'Together-Bot',
			'replicate-bot'         => 'Replicate-Bot',
			'runpod-bot'            => 'RunPod-Bot',
			'brightbot'             => 'BrightBot',
			'webzio-extended'       => 'Webzio-Extended',
			'imagesiftbot'          => 'ImagesiftBot',
			'bigsur.ai'             => 'BigSur-AI',
		);
	}

	/**
	 * Map a bot label to its parent company domain (for favicon lookup).
	 *
	 * @param string $bot_name The bot label.
	 * @return string Domain name without scheme, or empty string.
	 */
	public static function get_bot_favicon_domain( $bot_name ) {
		$map = array(
			'GPTBot'               => 'openai.com',
			'OAI-SearchBot'        => 'openai.com',
			'ChatGPT-User'         => 'openai.com',
			'ChatGPT-Browser'      => 'openai.com',
			'ClaudeBot'            => 'anthropic.com',
			'Claude-Web'           => 'anthropic.com',
			'Claude-SearchBot'     => 'anthropic.com',
			'Claude-User'          => 'anthropic.com',
			'Anthropic-AI'         => 'anthropic.com',
			'PerplexityBot'        => 'perplexity.ai',
			'Perplexity-User'      => 'perplexity.ai',
			'Google-Extended'      => 'google.com',
			'Google-CloudVertexBot' => 'cloud.google.com',
			'GoogleAgent-Mariner'  => 'google.com',
			'Gemini-Deep-Research' => 'gemini.google.com',
			'Gemini-AI'            => 'gemini.google.com',
			'Bard-AI'             => 'google.com',
			'APIs-Google'          => 'google.com',
			'Meta-ExternalAgent'   => 'meta.com',
			'Meta-ExternalFetcher' => 'meta.com',
			'FacebookExternalHit'  => 'meta.com',
			'FacebookBot'          => 'meta.com',
			'LinkedInBot'          => 'linkedin.com',
			'MistralAI-User'       => 'mistral.ai',
			'Mistral-Le-Chat'      => 'mistral.ai',
			'xAI-Bot'             => 'x.ai',
			'DeepSeekBot'          => 'deepseek.com',
			'Bytespider'           => 'bytedance.com',
			'Amazonbot'            => 'amazon.com',
			'NovaAct'              => 'amazon.com',
			'Applebot-Extended'    => 'apple.com',
			'CCBot'                => 'commoncrawl.org',
			'Omgilibot'            => 'omgili.com',
			'Omgili'               => 'omgili.com',
			'YouBot'               => 'you.com',
			'Diffbot'              => 'diffbot.com',
			'Cohere-AI'            => 'cohere.com',
			'Cohere-Command'       => 'cohere.com',
			'DuckAssistBot'        => 'duckduckgo.com',
			'Devin'                => 'devin.ai',
			'HuggingFace-Bot'      => 'huggingface.co',
			'FirecrawlAgent'       => 'firecrawl.dev',
		);

		$domain = isset( $map[ $bot_name ] ) ? $map[ $bot_name ] : '';

		/**
		 * Filter the favicon domain for a bot label.
		 *
		 * @param string $domain   Domain name.
		 * @param string $bot_name Bot label.
		 */
		return (string) apply_filters( 'oblique_ai_scout_favicon_domain', $domain, $bot_name );
	}
}
