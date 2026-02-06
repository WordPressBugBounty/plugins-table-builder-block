<?php
/**
 * Plugin Name: Table Builder Block
 * Description: Powerful table builder block for Gutenberg block editor.
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Plugin URI: https://wpmet.com/plugin/gutenkit/
 * Author: Wpmet
 * Version: 2.2.0
 * Author URI: https://wpmet.com/
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Text Domain: table-builder-block
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TableBuilder {
	/**
	 * plugin version
	 *
	 * @var string
	 */
	const VERSION = '2.2.0';

	private static $instance = null;

	public static function get_instance(): TableBuilder {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		/**
		 * Plugins helper constants
		 *
		 * @return void
		 * @since 1.0.0
		 */
		$this->define_constants();

		/**
		 * Make sure ADD AUTOLOAD is scoped/vendor/scoper-autoload.php file
		 *
		 * @return void
		 * @since 2.0.1
		 */
		require_once TABLE_BUILDER_BLOCK_PLUGIN_DIR . '/scoped/vendor/scoper-autoload.php';

		/**
		 * Fires after initialization of the GutenKit plugin
		 *
		 * @return void
		 * @since 1.0.0
		 */
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
	}

	private function define_constants(): void {
		define( 'TABLE_BUILDER_BLOCK_PLUGIN_VERSION', self::VERSION );
		define( 'TABLE_BUILDER_BLOCK_PLUGIN_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
		define( 'TABLE_BUILDER_BLOCK_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'TABLE_BUILDER_BLOCK_INC_DIR', TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'includes/' );
		define( 'TABLE_BUILDER_BLOCK_STYLE_DIR', TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/styles/' );
		define( 'TABLE_BUILDER_BLOCK_DIR', TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/blocks/' );
	}

	public function on_plugins_loaded(): void {
		do_action( 'tablebuilder/before_init' );

		TableBuilder\Hooks\AssetGenerator::instance();
		TableBuilder\Core\Enqueue::instance();
		TableBuilder\Config\Blocks::instance();
		TableBuilder\Admin\Admin::instance();

		// Data migration
		// TODO:: WIll be removed in next upcoming version
		(new TableBuilder\Core\DataMigration());

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'plugin_action_link' ] );
	}

	public function plugin_action_link( array $plugin_actions ): array {
		return $plugin_actions;
	}
}

if ( class_exists( 'TableBuilder' ) ) {
	TableBuilder::get_instance();
}
