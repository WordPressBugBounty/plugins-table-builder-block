<?php

namespace TableBuilder\Shortcode;

use TableBuilder\Shortcode\ShortcodeUtils;
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
		$atts = $this->sanitize_atts(shortcode_atts(self::DEFAULT_ATTS, (array) $raw_atts, 'tableKit'));
		$ids  = $this->parse_ids($atts);

		ob_start();

		if ( empty( $ids['block_id'] ) && empty( $ids['raw_id'] ) ) {
			echo $this->render_all_blocks( $ids, $atts, $content );
			return (string) ob_get_clean();
		}

		$block = $this->locate_block($ids['post_id'], $ids['block_id'], $ids['raw_id']);

		if (! empty($block)) {
			echo $this->render_single_block($block, $atts, $ids, $content);
		}

		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Multi-block rendering (no block_id given)
	// -------------------------------------------------------------------------

	/**
	 * Render the source post/page content for post_id-only shortcodes.
	 * For standalone tablekit_table CPT entries, this preserves the original
	 * behavior of rendering the stored block content.
	 */
	private function render_all_blocks( array $ids, array $atts, string $content ): string
	{
		if ( empty( $ids['post_id'] ) ) {
			return '';
		}

		$post_id = (int) $ids['post_id'];
		static $cleared = [];
		if ( ShortcodeUtils::is_elementor_editor() && ! isset( $cleared[ $post_id ] ) ) {
			clean_post_cache( $post_id );
			$cleared[ $post_id ] = true;
		}

		$post = get_post( $post_id );

		if ( ! $post || empty( $post->post_content ) ) {
			return '';
		}
		$this->block_enqueue_assets();
		$css   = $this->collect_blocks_css_from_content( $post->post_content );
		$style = $css ? '<style id="tbk-shortcode-' . absint( $post->ID ) . '">' . $css . '</style>' : '';

		$previous_post = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = $post; // Ensure filters use the shortcode post context.
		setup_postdata( $post );
		$content = (string) apply_filters( 'the_content', $post->post_content );
		wp_reset_postdata();
		$GLOBALS['post'] = $previous_post;

		return $style . $content . ShortcodeUtils::get_elementor_init_script();
	}

	// -------------------------------------------------------------------------
	// Single-block rendering
	// -------------------------------------------------------------------------

	/**
	 * Render a single resolved block. Used both by the multi-block path and the
	 * specific block_id path.
	 */
	private function render_single_block( array $block, array $atts, array $ids, string $content ): string
	{
		// Pro hooks handle their own block types.
		$output = apply_filters('tablekit/render_block', null, $block, $atts, $ids, $content);
		if (null !== $output) {
			return (string) $output;
		}

		$attrs = $this->prepare_attrs($block, $atts, $ids['block_id']);
		$rows  = $block['innerBlocks'] ?? [];
		$css   = ShortcodeUtils::collect_block_css($block);
		$this->block_enqueue_assets();
		$style = $css ? '<style id="tb-' . esc_attr($attrs['blockID']) . '">' . $css . '</style>' : '';

		return $style . BlockRenderer::render_table_builder($attrs, $rows, $content);
	}

	// -------------------------------------------------------------------------
	// Attribute Preparation
	// -------------------------------------------------------------------------

	private function prepare_attrs(array $block, array $atts, string $block_id): array
	{
		$attrs = $block['attrs'] ?? [];
		$rows  = $block['innerBlocks'] ?? [];

		// Override stored attributes with any inline JSON overrides.
		$json_overrides = ShortcodeUtils::decode_json($atts['attrs_json']);
		if ($json_overrides) {
			$attrs = array_replace_recursive($attrs, $json_overrides);
		}

		// Ensure a stable unique block ID.
		$attrs['blockID'] = $block_id ?: (! empty($attrs['blockID']) ? $attrs['blockID'] : 'tb-' . wp_generate_uuid4());

		// Append any extra CSS class passed via shortcode.
		if (! empty($atts['class'])) {
			$extra_class         = sanitize_html_class($atts['class']);
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

	// -------------------------------------------------------------------------
	// ID Resolution
	// -------------------------------------------------------------------------

	private function parse_ids(array $atts): array
	{
		$raw_id   = trim((string) ($atts['id'] ?? ''));
		$post_id  = (int) ($atts['post_id'] ?? 0);
		$block_id = (string) ($atts['block_id'] ?? '');

		if (! $post_id && ctype_digit($raw_id)) {
			$post_id = (int) $raw_id;
		}

		if (! $block_id && $raw_id && ! ctype_digit($raw_id)) {
			$block_id = $raw_id;
		}

		return compact('raw_id', 'post_id', 'block_id');
	}

	// -------------------------------------------------------------------------
	// Block Resolution (specific block_id path only)
	// -------------------------------------------------------------------------

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
	 * Searches the database for posts containing the given block ID.
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

	// -------------------------------------------------------------------------
	// CSS Collection
	// -------------------------------------------------------------------------

	public function collect_blocks_css_from_content( string $content ): string
	{
		if ( '' === trim( $content ) ) {
			return '';
		}

		$blocks = parse_blocks( $content );
		if ( empty( $blocks ) ) {
			return '';
		}

		$block_names = apply_filters( 'tablekit/block_names', [ 'tablebuilder/table-builder' ] );
		$css         = '';

		foreach ( $blocks as $block ) {
			$css .= $this->collect_css_from_block_tree( $block, $block_names );
		}

		return $css;
	}

	private function collect_css_from_block_tree( array $block, array $block_names ): string
	{
		$css = '';

		if ( in_array( $block['blockName'] ?? '', $block_names, true ) ) {
			$css .= ShortcodeUtils::collect_block_css( $block );
		}

		foreach ( $block['innerBlocks'] ?? array() as $inner_block ) {
			$css .= $this->collect_css_from_block_tree( $inner_block, $block_names );
		}

		return $css;
	}

	// -------------------------------------------------------------------------
	// Asset Management
	// -------------------------------------------------------------------------

	private function block_enqueue_assets(): void
	{
		if (self::$assets_enqueued) {
			return;
		}

		self::$assets_enqueued = true;
		ShortcodeUtils::enqueue_shared_styles();
		ShortcodeUtils::enqueue_block_assets('tablebuilder/table-builder');
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	public static function is_assets_enqueued(): bool
	{
		return self::$assets_enqueued;
	}

	private function sanitize_atts(array $atts): array
	{
		$atts['id']         = sanitize_text_field((string) ($atts['id'] ?? ''));
		$atts['post_id']    = absint($atts['post_id'] ?? 0);
		$atts['block_id']   = sanitize_text_field((string) ($atts['block_id'] ?? ''));
		$atts['class']      = sanitize_text_field((string) ($atts['class'] ?? ''));
		$atts['attrs_json'] = (string) ($atts['attrs_json'] ?? '');

		return $atts;
	}
}