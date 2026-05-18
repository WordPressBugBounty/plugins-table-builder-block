<?php

/**
 * Elementor REST helpers for TableKit.
 *
 * @package TableKit
 */

use TableBuilder\Shortcode\ShortcodeUtils;

if (! defined('ABSPATH')) {
	exit;
}

class TableKit_Elementor_Rest
{
	public static function register(): void
	{
		add_action('rest_api_init', array(__CLASS__, 'register_rest_routes'));
	}

	public static function register_rest_routes(): void
	{
		register_rest_route(
			'tablekit/v1',
			'/table-blocks',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array(__CLASS__, 'rest_table_blocks'),
				'permission_callback' => static function (): bool {
					return current_user_can('edit_posts');
				},
				'args'                => array(
					'table_id' => array(
						'required'          => true,
						'validate_callback' => static function ($value): bool {
							return is_numeric($value) && (int) $value > 0;
						},
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public static function rest_table_blocks(\WP_REST_Request $request)
	{
		$table_id = (int) $request->get_param('table_id');
		$post     = get_post($table_id);
		$content = null;

		if ($post) {
			$content = $post->post_content;
		} elseif (class_exists('\\TableBuilder\\Config\\CPT\\TableCPT')) {
			$inline = \TableBuilder\Config\CPT\TableCPT::instance()
				->get_inline_table_content($table_id); 
			$content = $inline ?? null;
		}

		if (null === $content) {
			return new \WP_Error(
				'tablekit_not_found',
				__('Table not found.', 'tablekit'),
				array('status' => 404)
			);
		}

		$blocks       = parse_blocks($content);
		$table_blocks = self::filter_table_blocks($blocks);
		$result       = array();

		foreach ($table_blocks as $index => $block) {
			$block_name = $block['blockName'] ?? '';
			$result[]   = array(
				'index'      => $index,
				'label'      => ShortcodeUtils::get_block_label($block_name),
				'block_name' => $block_name,
			);
		}

		return rest_ensure_response($result);
	}

	private static function filter_table_blocks(array $blocks): array
	{
		return ShortcodeUtils::filter_table_blocks($blocks);
	}
}
