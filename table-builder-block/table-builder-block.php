<?php
/**
 * Plugin Name:       TableKit
 * Plugin URI:        https://wpmet.com/plugin/gutenkit/
 * Description:       Powerful Table Builder for Gutenberg block editor.
 * Version:           2.2.10
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            Wpmet
 * Author URI:        https://wpmet.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       table-builder-block
 * Domain Path:       /languages
 *
 * @package TableKit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap class for TableKit.
 */
final class TableBuilder {
	const VERSION = '2.2.10';

	/**
	 * Singleton instance.
	 *
	 * @var TableBuilder|null
	 */
	private static $instance = null;

	/**
	 * Gets (and lazily creates) the singleton plugin instance.
	 *
	 * @return TableBuilder
	 */
	public static function get_instance(): TableBuilder {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Defines plugin constants and registers the plugin's core hooks.
	 */
	private function __construct() {
		$this->define_constants();

		// Prevent redirects during programmatic plugin activation.
		// This hook runs very early to intercept activation redirects from other plugins.
		add_action( 'admin_init', array( $this, 'prevent_activation_redirect' ), 1 );

		// Make sure ADD AUTOLOAD is scoped/vendor/scoper-autoload.php file.
		require_once TABLE_BUILDER_BLOCK_PLUGIN_DIR . '/scoped/vendor/scoper-autoload.php';
		require_once TABLE_BUILDER_BLOCK_INC_DIR . 'Elementor/TablekitElementor.php';

		// Fires after initialization of the GutenKit plugin.
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_onboard' ) );

		// Load translated strings for PHP (.mo). JS/React strings are handled
		// separately per-script via wp_set_script_translations() in Enqueue.php.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads the plugin's PHP translation file.
	 *
	 * WordPress 5.9+ can auto-discover this from the "Text Domain"/"Domain Path"
	 * plugin headers, but declaring it explicitly is the standard, version-safe
	 * pattern and is required for translations shipped under
	 * wp-content/languages/plugins/ instead of this plugin's own /languages folder.
	 */
	public function load_textdomain(): void {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- kept for translation delivery outside the WordPress.org directory (e.g. wp-content/languages/table-builder-block/ or a bundled distribution); WordPress.org itself auto-loads by slug since 4.6 and doesn't need this call.
		load_plugin_textdomain(
			'table-builder-block',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Plugin activation callback: records the install time and, on first
	 * activation, primes the transients that trigger the onboarding redirect.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( ! get_option( 'tablebuilder_installed_time' ) ) {
			add_option( 'tablebuilder_installed_time', time() );
		}

		if ( get_transient( 'tablekit_skip_activation_redirect' ) ) {
			return;
		}

		if ( ! get_option( 'tablebuilder_onboard_completed', false ) ) {
			set_transient( 'tablebuilder_show_onboard', 1, DAY_IN_SECONDS );
			set_transient( 'tablebuilder_do_activation_redirect', 1, MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Defines the plugin's path/URL/version constants.
	 *
	 * @return void
	 */
	private function define_constants(): void {
		define( 'TABLE_BUILDER_BLOCK_PLUGIN_VERSION', self::VERSION );
		define( 'TABLE_BUILDER_BLOCK_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
		define( 'TABLE_BUILDER_BLOCK_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'TABLE_BUILDER_BLOCK_INC_DIR', TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'includes/' );
		define( 'TABLE_BUILDER_BLOCK_STYLE_DIR', TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/styles/' );
		define( 'TABLE_BUILDER_BLOCK_DIR', TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/blocks/' );
	}


	/**
	 * Prevents activation redirects when plugins are activated programmatically.
	 * Intercepts wp_redirect/wp_safe_redirect during REST API activation.
	 *
	 * @return void
	 */
	public function prevent_activation_redirect(): void {
		if ( get_transient( 'tablekit_skip_activation_redirect' ) ) {
			add_filter( 'wp_redirect', '__return_empty_string', 999 );
			add_filter( 'wp_safe_redirect_fallback', '__return_empty_string', 999 );
		}
	}

	/**
	 * Boots the plugin's core classes once all plugins have loaded.
	 *
	 * @return void
	 */
	public function on_plugins_loaded(): void {
		/**
		 * Fires right before TableKit's core classes are instantiated, on "plugins_loaded".
		 *
		 * @since Unknown
		 */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-namespaced hook name is this plugin's established public API (used by the Pro add-on); renaming would be a breaking change.
		do_action( 'tablebuilder/before_init' );

		// Note: translations are already loaded via load_textdomain(), hooked to 'init'
		// in the constructor above (which runs before the 'init' callbacks registered
		// by the classes below) — no need to call load_plugin_textdomain() again here.

		TableBuilder\Hooks\AssetGenerator::instance();
		TableBuilder\Core\ScriptTranslationMerger::instance();
		TableBuilder\Core\Enqueue::instance();
		TableBuilder\Core\RestApi::instance();
		TableBuilder\Config\CPT\TableCPT::instance();
		TableBuilder\Config\Blocks::instance();
		TableBuilder\Shortcode\Shortcode::instance();
		TableBuilder\Admin\Admin::instance();
		TableKit_Elementor::init();

		// Receive deactivation reason data from the plugins-page modal.
		new TableBuilder\Routes\DeactivationFeedback();

		if ( is_admin() ) {
			TableBuilder\Libs\UtilityPackages::instance();
		}

		// Data migration.
		// TODO:: Will be removed in next upcoming version.
		( new TableBuilder\Core\DataMigration() );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_link' ) );
	}

	/**
	 * Redirects to the onboarding screen right after activation, when eligible.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_onboard(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! get_transient( 'tablebuilder_do_activation_redirect' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check (same pattern WP core uses internally) to skip a redirect during bulk activation; no state change.
		if ( isset( $_GET['activate-multi'] ) ) {
			delete_transient( 'tablebuilder_do_activation_redirect' );
			return;
		}

		delete_transient( 'tablebuilder_do_activation_redirect' );
		wp_safe_redirect( admin_url( 'admin.php?page=tablebuilder&onboard=1' ) );
		exit;
	}

	/**
	 * Filter callback for the plugin's row action links (currently a no-op passthrough).
	 *
	 * @param array $plugin_actions Existing action links.
	 * @return array Unmodified action links.
	 */
	public function plugin_action_link( array $plugin_actions ): array {
		return $plugin_actions;
	}
}

if ( class_exists( 'TableBuilder' ) ) {
	TableBuilder::get_instance();
}

register_activation_hook( __FILE__, array( 'TableBuilder', 'activate' ) );
