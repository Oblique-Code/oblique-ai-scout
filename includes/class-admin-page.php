<?php
/**
 * Admin Page Class
 *
 * Single-page dashboard layout (no tabs) with a separate Settings submenu.
 *
 * @package Oblique_AI_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Oblique_Admin_Page
 */
class Oblique_Admin_Page {

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu and submenu pages.
	 *
	 * @return void
	 */
	public static function register_menus() {
		// Main dashboard page.
		add_menu_page(
			__( 'AI Scout', 'oblique-ai-scout' ),
			__( 'AI Scout', 'oblique-ai-scout' ),
			'manage_options',
			'oblique-ai-scout',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-visibility',
			80
		);

		// Submenu: Dashboard (same as parent).
		add_submenu_page(
			'oblique-ai-scout',
			__( 'Dashboard', 'oblique-ai-scout' ),
			__( 'Dashboard', 'oblique-ai-scout' ),
			'manage_options',
			'oblique-ai-scout',
			array( __CLASS__, 'render_dashboard' )
		);

		// Submenu: Settings.
		add_submenu_page(
			'oblique-ai-scout',
			__( 'Settings', 'oblique-ai-scout' ),
			__( 'Settings', 'oblique-ai-scout' ),
			'manage_options',
			'oblique-ai-scout-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	/**
	 * Enqueue admin CSS and JS only on our pages.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		$our_pages = array(
			'toplevel_page_oblique-ai-scout',
			'ai-scout_page_oblique-ai-scout-settings',
		);

		if ( ! in_array( $hook, $our_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'oblique-ai-scout-admin',
			OBLIQUE_AI_SCOUT_PLUGIN_URL . 'assets/admin.css',
			array(),
			OBLIQUE_AI_SCOUT_VERSION
		);

		wp_enqueue_script(
			'oblique-ai-scout-admin',
			OBLIQUE_AI_SCOUT_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			OBLIQUE_AI_SCOUT_VERSION,
			true
		);

		wp_localize_script(
			'oblique-ai-scout-admin',
			'obliqueScout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'oblique_ai_scout_nonce' ),
				'i18n'    => array(
					'copied'        => __( 'Copied to clipboard!', 'oblique-ai-scout' ),
					'copyFail'      => __( 'Please manually select and copy the patterns above.', 'oblique-ai-scout' ),
					'confirmDelete' => __( 'Delete selected entries?', 'oblique-ai-scout' ),
					'confirmPurge'  => __( 'Permanently delete ALL log data? This cannot be undone.', 'oblique-ai-scout' ),
				),
			)
		);
	}

	/**
	 * Render the main dashboard (single-page layout).
	 *
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'oblique-ai-scout' ) );
		}

		include OBLIQUE_AI_SCOUT_PLUGIN_DIR . 'includes/views/dashboard.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'oblique-ai-scout' ) );
		}

		include OBLIQUE_AI_SCOUT_PLUGIN_DIR . 'includes/views/settings.php';
	}
}
