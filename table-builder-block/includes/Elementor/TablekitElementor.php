<?php
/**
 * Elementor integration loader
 *
 * @package TableKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once TABLE_BUILDER_BLOCK_INC_DIR . 'Elementor/TablekitElementorEditor.php';
require_once TABLE_BUILDER_BLOCK_INC_DIR . 'Elementor/TablekitElementorRest.php';

/**
 * Bootstraps TableKit's Elementor widget, editor assets, and REST support
 * once Elementor (and the Pro add-on) are confirmed active.
 */
class TableKit_Elementor {

	/**
	 * Hooks widget loading into "plugins_loaded", after Elementor itself has loaded.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_widget' ), 20 );
	}

	/**
	 * Loads the Elementor editor integration, widget registration, and REST
	 * support, when Elementor has loaded and the Pro add-on is active.
	 *
	 * @return void
	 */
	public static function load_widget(): void {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		if ( ! self::is_pro_active() ) {
			return;
		}

		TableKit_Elementor_Editor::register();
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widget' ) );
		TableKit_Elementor_Rest::register();
	}

	/**
	 * Registers the TableKit Elementor widget, hooked to "elementor/widgets/register".
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor's widget manager.
	 * @return void
	 */
	public static function register_widget( $widgets_manager ): void {
		if ( ! self::is_pro_active() ) {
			return;
		}

		require_once TABLE_BUILDER_BLOCK_INC_DIR . 'Elementor/TablekitWidget.php';

		$widgets_manager->register( new TableKit_Elementor_Widget() );
	}

	/**
	 * Whether Elementor is installed and active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return defined( 'ELEMENTOR_VERSION' ) && class_exists( '\\Elementor\\Plugin' );
	}

	/**
	 * Whether the Pro add-on plugin is active.
	 *
	 * @return bool
	 */
	private static function is_pro_active(): bool {
		return defined( 'TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION' );
	}
}
