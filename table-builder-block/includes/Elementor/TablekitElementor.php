<?php
/**
 * Elementor integration loader.
 *
 * @package TableKit
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once TABLE_BUILDER_BLOCK_INC_DIR . 'Elementor/TablekitElementorEditor.php';
require_once TABLE_BUILDER_BLOCK_INC_DIR . 'Elementor/TablekitElementorRest.php';

class TableKit_Elementor {
	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_widget' ), 20 );
	}

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

	public static function register_widget( $widgets_manager ): void {
		if ( ! self::is_pro_active() ) {
			return;
		}

		require_once TABLE_BUILDER_BLOCK_INC_DIR . 'Elementor/TablekitWidget.php';

		$widgets_manager->register( new TableKit_Elementor_Widget() );
	}

	public static function is_active(): bool {
		return defined( 'ELEMENTOR_VERSION' ) && class_exists( '\\Elementor\\Plugin' );
	}

	private static function is_pro_active(): bool {
		return defined( 'TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION' );
	}
}
