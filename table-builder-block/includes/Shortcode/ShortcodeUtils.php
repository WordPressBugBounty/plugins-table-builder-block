<?php
/**
 * Shared helpers used by the [tableKit] shortcode
 *
 * @package TableKit
 */

namespace TableBuilder\Shortcode;

use TableBuilder\Config\CPT\TableCPT;
use TableBuilder\Helpers\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Static helper methods for shortcode block lookup, asset handling, and CSS collection.
 */
class ShortcodeUtils {

	/**
	 * Resolves the registered asset handle for a block's style/view script,
	 * falling back to TableKit's own naming convention on older WP versions
	 * that lack generate_block_asset_handle().
	 *
	 * @param string $block_name Block name, e.g. "tablebuilder/table-builder".
	 * @param string $asset_type Either "style" or "viewScript".
	 * @return string The resolved asset handle.
	 */
	public static function get_block_asset_handle( string $block_name, string $asset_type ): string {
		if ( function_exists( 'generate_block_asset_handle' ) ) {
			return generate_block_asset_handle( $block_name, $asset_type, 0 );
		}

		$base = str_replace( '/', '-', $block_name );

		return 'style' === $asset_type ? "{$base}-style" : "{$base}-view-script";
	}

	/**
	 * Recursively filters a parsed block tree down to registered table blocks.
	 *
	 * @param array $blocks Parsed block tree, as returned by parse_blocks().
	 * @return array Matching table blocks, including nested ones.
	 */
	public static function filter_table_blocks( array $blocks ): array {
		$found       = array();
		$block_names = TableCPT::get_table_blocks();

		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'] ?? '', $block_names, true ) ) {
				$found[] = $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				foreach ( self::filter_table_blocks( $block['innerBlocks'] ) as $inner ) {
					$found[] = $inner;
				}
			}
		}

		return array_values( $found );
	}

	/**
	 * Gets a human-readable label for a table block name.
	 *
	 * @param string $block_name Block name, e.g. "tablebuilder/table-builder".
	 * @return string The block's display label, or the block name if unknown.
	 */
	public static function get_block_label( string $block_name ): string {
		return TableCPT::get_block_label( $block_name );
	}

	/**
	 * Safely decode a JSON string (e.g. the shortcode's attrs_json attribute)
	 * into an associative array.
	 *
	 * @param string $value Raw, possibly-slashed JSON string.
	 * @return array Decoded array, or an empty array on invalid/empty input.
	 */
	public static function decode_json( string $value ): array {
		$value = trim( $value );
		if ( '' === $value ) {
			return array();
		}

		$decoded = json_decode( wp_unslash( $value ), true );

		return ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) )
			? $decoded
			: array();
	}

	/**
	 * Enqueues the shared global/component stylesheets used by table blocks
	 * on the frontend (idempotent — safe to call multiple times).
	 *
	 * @return void
	 */
	public static function enqueue_shared_styles(): void {
		if ( ! defined( 'TABLE_BUILDER_BLOCK_PLUGIN_URL' ) ) {
			return;
		}

		wp_enqueue_style(
			'table-builder-global',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/tablebuilder/global.css',
			array(),
			TABLE_BUILDER_BLOCK_PLUGIN_VERSION
		);
		wp_enqueue_style(
			'table-builder-components',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/tablebuilder/components.css',
			array(),
			TABLE_BUILDER_BLOCK_PLUGIN_VERSION
		);
	}

	/**
	 * Enqueues a specific block's style/view-script assets, if registered
	 * (used when rendering a block outside its normal render_block() path,
	 * e.g. via the [tableKit] shortcode).
	 *
	 * @param string $block_name Block name, e.g. "tablebuilder/table-builder".
	 * @return void
	 */
	public static function enqueue_block_assets( string $block_name ): void {
		$style  = self::get_block_asset_handle( $block_name, 'style' );
		$script = self::get_block_asset_handle( $block_name, 'viewScript' );

		if ( $style && wp_style_is( $style, 'registered' ) ) {
			wp_enqueue_style( $style );
		}
		if ( $script && wp_script_is( $script, 'registered' ) ) {
			wp_enqueue_script( $script );
		}
	}

	/**
	 * Recursively collects blocks (including nested inner blocks) matching a predicate.
	 *
	 * @param array    $blocks    Parsed block tree, as returned by parse_blocks().
	 * @param callable $predicate Callback receiving a single block array, returning bool.
	 * @return array Matching blocks, in document order.
	 */
	public static function collect_blocks_recursive( array $blocks, callable $predicate ): array {
		$found = array();
		foreach ( $blocks as $block ) {
			if ( $predicate( $block ) ) {
				$found[] = $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = array_merge(
					$found,
					self::collect_blocks_recursive( $block['innerBlocks'], $predicate )
				);
			}
		}
		return array_values( $found );
	}

	/**
	 * Detects whether the current request is inside the Elementor edit or preview mode.
	 *
	 * @return bool True if Elementor is active and currently editing/previewing.
	 */
	public static function is_elementor_editor(): bool {
		if ( ! defined( 'ELEMENTOR_VERSION' ) || ! class_exists( '\\Elementor\\Plugin' ) ) {
			return false;
		}

		$plugin = \Elementor\Plugin::$instance ?? null;
		if ( ! $plugin ) {
			return false;
		}

		$is_edit = isset( $plugin->editor )
			&& method_exists( $plugin->editor, 'is_edit_mode' )
			&& $plugin->editor->is_edit_mode();

		$is_preview = isset( $plugin->preview )
			&& method_exists( $plugin->preview, 'is_preview_mode' )
			&& $plugin->preview->is_preview_mode();

		return $is_edit || $is_preview;
	}

	/**
	 * Gets an inline script element that re-fires Elementor's frontend "element_ready"
	 * hooks, needed because content rendered via AJAX/shortcode inside the
	 * Elementor editor doesn't trigger Elementor's own initialization events.
	 *
	 * @return string The script element's markup, or an empty string outside the Elementor editor.
	 */
	public static function get_elementor_init_script(): string {
		if ( ! self::is_elementor_editor() ) {
			return '';
		}

		return <<<'JS'
		<script>
		(function () {
			var run = function () {
				try { document.dispatchEvent(new Event('DOMContentLoaded')); } catch (e) {}

				try {
					if (window.elementorFrontend && window.elementorFrontend.hooks) {
						window.elementorFrontend.hooks.doAction('frontend/element_ready/global');
					}
				} catch (e) {}
			};

			setTimeout(run, 0);
		}());
		</script>
		JS;
	}

	/**
	 * Recursively collects the generated per-breakpoint CSS for a block and its children.
	 *
	 * @param array $block A single parsed block (with optional 'innerBlocks').
	 * @return string Concatenated CSS for this block and all descendants.
	 */
	public static function collect_block_css( array $block ): string {
		$css = self::build_css_from_attrs( $block['attrs'] ?? array() );

		foreach ( $block['innerBlocks'] ?? array() as $inner_block ) {
			$css .= self::collect_block_css( $inner_block );
		}

		return $css;
	}

	/**
	 * Builds a single block's responsive CSS from its stored "blocksCSS" attribute,
	 * wrapping per-device rules in the appropriate min/max-width media query.
	 *
	 * @param array $attrs Block attributes, expected to contain a "blocksCSS" map keyed by device slug.
	 * @return string The generated CSS, or an empty string if there's nothing to output.
	 */
	public static function build_css_from_attrs( array $attrs ): string {
		static $device_list = null;
		$device_list      ??= Utils::get_device_list();

		$blocks_css = $attrs['blocksCSS'] ?? null;

		if ( empty( $blocks_css ) || ! is_array( $blocks_css ) ) {
			return '';
		}

		$css_map = array_filter(
			$blocks_css,
			static fn( $value ) => is_string( $value ) && '' !== trim( $value )
		);

		if ( empty( $css_map ) ) {
			return '';
		}

		$output = '';

		foreach ( $device_list as $device ) {
			$slug = strtolower( $device['slug'] ?? '' );
			$css  = trim( $css_map[ $slug ] ?? '' );

			if ( '' === $css ) {
				continue;
			}

			$css = self::sanitize_css( $css );

			if ( 'base' === ( $device['value'] ?? '' ) ) {
				$output .= $css;
			} else {
				$output .= "@media ({$device['direction']}-width:{$device['value']}px){{$css}}";
			}
		}

		if ( ! empty( $css_map['customStyles'] ) ) {
			$output .= self::sanitize_css( $css_map['customStyles'] );
		}

		return $output;
	}

	/**
	 * Strips any HTML-tag-like sequences from a stored CSS string before it's
	 * @param string $css Raw CSS string from a block attribute.
	 * @return string Sanitized CSS string.
	 */
	private static function sanitize_css( string $css ): string {
		return preg_replace( '/<[^>]*>?/', '', $css );
	}
}
