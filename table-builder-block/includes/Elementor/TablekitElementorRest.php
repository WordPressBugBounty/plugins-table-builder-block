<?php
/**
 * Elementor REST helpers for TableKit
 *
 * @package TableKit
 */

use TableBuilder\Shortcode\ShortcodeUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers TableKit's Elementor-specific REST routes.
 */
class TableKit_Elementor_Rest {

	/**
	 * Hooks REST route registration into rest_api_init.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Registers the tablekit/v1/table-blocks REST route used by the
	 * Elementor editor's block picker to list table blocks in a post.
	 *
	 * @return void
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			'tablekit/v1',
			'/table-blocks',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_table_blocks' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'table_id' => array(
						'required'          => true,
						'validate_callback' => static function ( $value ): bool {
							return is_numeric( $value ) && (int) $value > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * REST callback: list the table blocks found in a given post (or inline
	 * table CPT entry), for the Elementor widget's block-picker dropdown.
	 *
	 * @param \WP_REST_Request $request Request with a "table_id" param.
	 * @return \WP_REST_Response|\WP_Error List of {index, label, block_name}, or an error.
	 */
	public static function rest_table_blocks( \WP_REST_Request $request ) {
		$table_id = (int) $request->get_param( 'table_id' );
		$post     = get_post( $table_id );
		$content  = null;

		if ( $post ) {
			if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $table_id ) ) {
				return new \WP_Error(
					'tablekit_forbidden',
					__( 'You are not allowed to view this table.', 'table-builder-block' ),
					array( 'status' => 403 )
				);
			}

			$content = $post->post_content;
		} elseif ( class_exists( '\\TableBuilder\\Config\\CPT\\TableCPT' ) && method_exists( '\\TableBuilder\\Config\\CPT\\TableCPT', 'get_inline_table_content' ) ) {
			$inline  = \TableBuilder\Config\CPT\TableCPT::instance()
				->get_inline_table_content( $table_id );
			$content = $inline ?? null;
		}

		if ( null === $content ) {
			return new \WP_Error(
				'tablekit_not_found',
				__( 'Table not found.', 'table-builder-block' ),
				array( 'status' => 404 )
			);
		}

		$blocks       = parse_blocks( $content );
		$table_blocks = self::filter_table_blocks( $blocks );
		$result       = array();

		foreach ( $table_blocks as $index => $block ) {
			$block_name = $block['blockName'] ?? '';
			$result[]   = array(
				'index'      => $index,
				'label'      => ShortcodeUtils::get_block_label( $block_name ),
				'block_name' => $block_name,
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Recursively filters a parsed block tree down to table blocks only.
	 *
	 * @param array $blocks Parsed block tree, as returned by parse_blocks().
	 * @return array Matching table blocks.
	 */
	private static function filter_table_blocks( array $blocks ): array {
		return ShortcodeUtils::filter_table_blocks( $blocks );
	}
}
