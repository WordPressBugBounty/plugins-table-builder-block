<?php

namespace TableBuilder\Shortcode;

use TableBuilder\Helpers\Utils;
use TableBuilder\Render\BlockRenderer;
use TableBuilder\Traits\Singleton;

defined('ABSPATH') || exit;

// Handles registration and rendering of the [tableKit] shortcode.
class Shortcode
{

	use Singleton;

	// Whether frontend assets have been enqueued at least once
	private static bool $assets_enqueued = false;

	// Default shortcode attribute values.
	private const DEFAULT_ATTS = [
		'id'         => '',
		'post_id'    => 0,
		'block_id'   => '',
		'class'      => '',
		'attrs_json' => '{}',
	];

	// Default block attribute values
	private const DEFAULT_BLOCK_ATTRS = [
		'headers'     => [],
		'footers'     => [],
		'hasHeader'   => true,
		'hasFooter'   => false,
		'columnCount' => 0,
	];

	// Constructor — hooks into WordPress init.
	protected function __construct()
	{
		add_action('init', [$this, 'register_shortcodes']);
	}

	// Registers all shortcodes for this plugin.
	public function register_shortcodes(): void
	{
		add_shortcode('tableKit', [$this, 'render']);
	}

	// Shortcode Rendering
	public function render($raw_atts, string $content = ''): string
	{
		$atts  = shortcode_atts(self::DEFAULT_ATTS, (array) $raw_atts, 'tableKit');
		$ids   = $this->parse_ids($atts);
		$block = $this->locate_block($ids['post_id'], $ids['block_id'], $ids['raw_id']);

		if (empty($block)) {
			return '';
		}

		// Pro hooks here to handle its own block types.
		$output = apply_filters('tablekit/render_block', null, $block, $atts, $ids, $content);
		if (null !== $output) {
			return (string) $output;
		}

		$attrs = $this->prepare_attrs($block, $atts, $ids['block_id']);
		$rows  = $block['innerBlocks'] ?? [];
		$css   = $this->collect_block_css($block);
		$this->block_enqueue_assets();
		$style = $css ? '<style id="tb-' . esc_attr($attrs['blockID']) . '">' . $css . '</style>' : '';

		return $style . BlockRenderer::render_table_builder($attrs, $rows, $content);
	}

	// Attribute Preparation
	private function prepare_attrs(array $block, array $atts, string $block_id): array
	{
		$attrs = $block['attrs'] ?? [];
		$rows  = $block['innerBlocks'] ?? [];

		// Override stored attributes with any inline JSON overrides.
		$json_overrides = $this->decode_json($atts['attrs_json']);
		if ($json_overrides) {
			$attrs = array_replace_recursive($attrs, $json_overrides);
		}

		// Ensure a stable unique block ID.
		$attrs['blockID'] = $block_id ?: (! empty($attrs['blockID']) ? $attrs['blockID'] : 'tb-' . wp_generate_uuid4());

		
		// Append any extra CSS class passed via shortcode.
		if (! empty($atts['class'])) {
			$extra_class      = sanitize_html_class($atts['class']);
			$attrs['blockClass'] = trim(($attrs['blockClass'] ?? '') . ' ' . $extra_class);
		}

		return $this->apply_block_defaults($attrs, $rows);
	}

	private function apply_block_defaults(array $attrs, array $rows): array
	{
		$attrs = array_replace_recursive(self::DEFAULT_BLOCK_ATTRS, $attrs);

		$col_count = (int) $attrs['columnCount'];

		if (! $col_count && $rows) {
			$col_count = $this->detect_column_count($rows);
		}

		if ($attrs['hasHeader'] && empty($attrs['headers']) && $col_count) {
			$attrs['headers'] = $this->generate_placeholder_cells($col_count, 'Header');
		}

		if ($attrs['hasFooter'] && empty($attrs['footers']) && $col_count) {
			$attrs['footers'] = $this->generate_placeholder_cells($col_count, 'Footer');
		}

		return $attrs;
	}

	private function detect_column_count(array $rows): int
	{
		foreach ($rows as $row) {
			if ('tablebuilder/table-builder-row' === ($row['blockName'] ?? '')) {
				return count($row['innerBlocks'] ?? []);
			}
		}

		return 0;
	}

	private function generate_placeholder_cells(int $count, string $prefix): array
	{
		return array_map(
			static fn(int $i) => ['title' => "{$prefix} " . ($i + 1)],
			range(0, $count - 1)
		);
	}

	// ID Resolution
	private function parse_ids(array $atts): array
	{
		$raw_id   = trim((string) ($atts['id'] ?? ''));
		$post_id  = absint($atts['post_id'] ?? 0);
		$block_id = sanitize_text_field($atts['block_id'] ?? '');

		if (! $post_id && ctype_digit($raw_id)) {
			$post_id = (int) $raw_id;
		}

		if (! $block_id && $raw_id && ! ctype_digit($raw_id)) {
			$block_id = $raw_id;
		}

		return compact('raw_id', 'post_id', 'block_id');
	}

	// Block Resolution
	private function locate_block(int $post_id, string $block_id, string $raw_id): array
	{
		if ($post_id) {
			$block = $this->get_block_from_post($post_id, $block_id);
			if ($block) {
				return $block;
			}
		}

		$lookup = $block_id ?: $raw_id;

		return $lookup ? $this->search_block_by_id($lookup) : [];
	}

	/**
	 * Parses a specific post's block content and finds the target block.
	 */
	private function get_block_from_post(int $post_id, string $block_id): array
	{
		$post = get_post($post_id);

		if (! $post || empty($post->post_content)) {
			return [];
		}

		return $this->find_table_block(parse_blocks($post->post_content), $block_id);
	}

	/**
	 * Searches the database for posts containing the given block ID, then
	 */
	private function search_block_by_id(string $block_id): array
	{
		global $wpdb;

		$candidate_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID
				 FROM   {$wpdb->posts}
				 WHERE  post_content LIKE %s
				 AND    post_status NOT IN ('trash', 'auto-draft')
				 LIMIT  10",
				'%' . $wpdb->esc_like($block_id) . '%'
			)
		);

		foreach ($candidate_ids as $id) {
			$block = $this->get_block_from_post((int) $id, $block_id);
			if ($block) {
				return $block;
			}
		}

		return [];
	}

	private function find_table_block(array $blocks, string $block_id): array
	{
		$block_names = apply_filters('tablekit/block_names', ['tablebuilder/table-builder']);

		foreach ($blocks as $block) {
			if (in_array($block['blockName'] ?? '', $block_names, true)) {
				$id       = (string) ($block['attrs']['blockID'] ?? '');
				$short_id = (string) ($block['attrs']['shortId'] ?? '');

				if (!$block_id || $id === $block_id || $short_id === $block_id) {
					return $block;
				}
			}

			if (!empty($block['innerBlocks'])) {
				$found = $this->find_table_block($block['innerBlocks'], $block_id);
				if ($found) {
					return $found;
				}
			}
		}

		return [];
	}

	// CSS Collection
	private function collect_block_css(array $block): string
	{
		$css = $this->build_css_from_attrs($block['attrs'] ?? []);

		foreach ($block['innerBlocks'] ?? [] as $inner_block) {
			$css .= $this->collect_block_css($inner_block);
		}

		return $css;
	}

	/**
	 * Converts a block's `blocksCSS` attribute map into a CSS string, wrapped
	 */
	private function build_css_from_attrs(array $attrs): string
	{
		$blocks_css = $attrs['blocksCSS'] ?? null;

		if (empty($blocks_css) || ! is_array($blocks_css)) {
			return '';
		}

		// Keep only non-empty string entries.
		$css_map = array_filter(
			$blocks_css,
			static fn($value) => is_string($value) && '' !== trim($value)
		);

		if (empty($css_map)) {
			return '';
		}

		$output = '';

		foreach (Utils::get_device_list() as $device) {
			$slug = strtolower($device['slug'] ?? '');
			$css  = trim($css_map[$slug] ?? '');

			if ('' === $css) {
				continue;
			}

			if ('base' === ($device['value'] ?? '')) {
				$output .= $css;
			} else {
				$output .= "@media ({$device['direction']}-width:{$device['value']}px){{$css}}";
			}
		}

		// Append any custom styles not covered by the device list.
		if (! empty($css_map['customStyles'])) {
			$output .= $css_map['customStyles'];
		}

		return $output;
	}

	// Utilities
	private function decode_json($value): array
	{
		if (! is_string($value)) {
			return [];
		}

		$decoded = json_decode(wp_unslash($value), true);

		return (JSON_ERROR_NONE === json_last_error() && is_array($decoded))
			? $decoded
			: [];
	}

	// Asset Management
	private function block_enqueue_assets(): void
	{
		if (self::$assets_enqueued) {
			return;
		}

		self::$assets_enqueued = true;

		wp_enqueue_style(
			'table-builder-global',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/tablebuilder/global.css',
			[],
			TABLE_BUILDER_BLOCK_PLUGIN_VERSION
		);

		wp_enqueue_style(
			'table-builder-components',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/tablebuilder/components.css',
			[],
			TABLE_BUILDER_BLOCK_PLUGIN_VERSION
		);

		$style_handle  = $this->get_asset_handle('tablebuilder/table-builder', 'style');
		$script_handle = $this->get_asset_handle('tablebuilder/table-builder', 'viewScript');

		if ($style_handle) {
			wp_enqueue_style($style_handle);
		}

		if ($script_handle) {
			wp_enqueue_script($script_handle);
		}
	}

	/**
	 * Returns the WordPress asset handle for a registered block asset type.
	 */
	private function get_asset_handle(string $block_name, string $asset_type): string
	{
		if (function_exists('generate_block_asset_handle')) {
			return generate_block_asset_handle($block_name, $asset_type, 0);
		}

		$base = str_replace('/', '-', $block_name);

		return 'style' === $asset_type
			? "{$base}-style"
			: "{$base}-view-script";
	}

	// Public API
	public static function is_assets_enqueued(): bool
	{
		return self::$assets_enqueued;
	}
}
