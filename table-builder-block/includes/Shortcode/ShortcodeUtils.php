<?php

namespace TableBuilder\Shortcode;

use TableBuilder\Config\CPT\TableCPT;
use TableBuilder\Helpers\Utils;

defined('ABSPATH') || exit;

class ShortcodeUtils
{
	public static function get_block_asset_handle(string $block_name, string $asset_type): string
	{
		if (function_exists('generate_block_asset_handle')) {
			return generate_block_asset_handle($block_name, $asset_type, 0);
		}

		$base = str_replace('/', '-', $block_name);

		return 'style' === $asset_type ? "{$base}-style" : "{$base}-view-script";
	}

	public static function filter_table_blocks(array $blocks): array
	{
		$found       = [];
		$block_names = TableCPT::get_table_blocks();

		foreach ($blocks as $block) {
			if (in_array($block['blockName'] ?? '', $block_names, true)) {
				$found[] = $block;
			}
			if (! empty($block['innerBlocks'])) {
				foreach (self::filter_table_blocks($block['innerBlocks']) as $inner) {
					$found[] = $inner;
				}
			}
		}

		return array_values($found);
	}

	public static function get_block_label(string $block_name): string
	{
		return TableCPT::get_block_label($block_name);
	}

	public static function decode_json(string $value): array
	{
		$value = trim($value);
		if ($value === '') {
			return [];
		}

		$decoded = json_decode(wp_unslash($value), true);

		return (JSON_ERROR_NONE === json_last_error() && is_array($decoded))
			? $decoded
			: [];
	}

	public static function enqueue_shared_styles(): void
	{
		if (! defined('TABLE_BUILDER_BLOCK_PLUGIN_URL')) {
			return;
		}

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
	}

	public static function enqueue_block_assets(string $block_name): void
	{
		$style  = self::get_block_asset_handle($block_name, 'style');
		$script = self::get_block_asset_handle($block_name, 'viewScript');

		if ($style && wp_style_is($style, 'registered')) {
			wp_enqueue_style($style);
		}
		if ($script && wp_script_is($script, 'registered')) {
			wp_enqueue_script($script);
		}
	}

	public static function collect_blocks_recursive(array $blocks, callable $predicate): array
	{
		$found = [];
		foreach ($blocks as $block) {
			if ($predicate($block)) {
				$found[] = $block;
			}
			if (! empty($block['innerBlocks'])) {
				$found = array_merge(
					$found,
					self::collect_blocks_recursive($block['innerBlocks'], $predicate)
				);
			}
		}
		return array_values($found);
	}

	public static function is_elementor_editor(): bool
	{
		if (! defined('ELEMENTOR_VERSION') || ! class_exists('\\Elementor\\Plugin')) {
			return false;
		}

		$plugin = \Elementor\Plugin::$instance ?? null;
		if (! $plugin) {
			return false;
		}

		$is_edit = isset($plugin->editor)
			&& method_exists($plugin->editor, 'is_edit_mode')
			&& $plugin->editor->is_edit_mode();

		$is_preview = isset($plugin->preview)
			&& method_exists($plugin->preview, 'is_preview_mode')
			&& $plugin->preview->is_preview_mode();

		return $is_edit || $is_preview;
	}

	public static function get_elementor_init_script(): string
	{
		if (! self::is_elementor_editor()) {
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

	public static function collect_block_css(array $block): string
	{
		$css = self::build_css_from_attrs($block['attrs'] ?? []);

		foreach ($block['innerBlocks'] ?? [] as $inner_block) {
			$css .= self::collect_block_css($inner_block);
		}

		return $css;
	}

	public static function build_css_from_attrs(array $attrs): string
	{
		static $device_list = null;
		$device_list ??= Utils::get_device_list();

		$blocks_css = $attrs['blocksCSS'] ?? null;

		if (empty($blocks_css) || ! is_array($blocks_css)) {
			return '';
		}

		$css_map = array_filter(
			$blocks_css,
			static fn($value) => is_string($value) && '' !== trim($value)
		);

		if (empty($css_map)) {
			return '';
		}

		$output = '';

		foreach ($device_list as $device) {
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

		if (! empty($css_map['customStyles'])) {
			$output .= $css_map['customStyles'];
		}

		return $output;
	}
}
