<?php
/**
 * Elementor editor assets for TableKit
 *
 * @package TableKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues TableKit's editor-only styles/scripts within the Elementor editor.
 */
class TableKit_Elementor_Editor {

	/**
	 * Hooks editor asset enqueueing into the Elementor editor lifecycle.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'enqueue_editor_styles' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue_editor_scripts' ) );
	}

	/**
	 * Enqueues TableKit's stylesheet for the Elementor editor screen.
	 *
	 * @return void
	 */
	public static function enqueue_editor_styles(): void {
		wp_enqueue_style(
			'tablekit-elementor-editor',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'assets/css/elementor-editor.css',
			array(),
			TABLE_BUILDER_BLOCK_PLUGIN_VERSION
		);
	}

	/**
	 * Enqueues the Elementor-editor block-picker script and localizes its data.
	 *
	 * @return void
	 */
	public static function enqueue_editor_scripts(): void {
		wp_enqueue_script(
			'tablekit-editor-block-picker',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'assets/js/elementor-editor-block-picker.js',
			array( 'elementor-editor', 'jquery' ),
			TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'tablekit-editor-block-picker',
			'tablekitEditorData',
			array(
				'restUrl'  => rest_url( 'tablekit/v1/table-blocks' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'allLabel' => __( '— All Blocks —', 'table-builder-block' ),
				'loading'  => __( 'Loading blocks…', 'table-builder-block' ),
			)
		);
	}
}
