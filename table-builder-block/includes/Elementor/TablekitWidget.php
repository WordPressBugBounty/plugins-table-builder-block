<?php

/**
 * Elementor widget for TableKit tables.
 *
 * @package TableKit
 */

use TableBuilder\Shortcode\ShortcodeUtils;

if (! defined('ABSPATH')) {
	exit;
}

class TableKit_Elementor_Widget extends \Elementor\Widget_Base
{
	private const ALL_BLOCKS = '';

	private static bool $global_styles_printed = false;
	private static array $printed_block_styles = [];
	private static array $printed_block_scripts = [];
	private static ?array $table_options_cache = null;

	// -------------------------------------------------------------------------
	// Identity
	// -------------------------------------------------------------------------

	public function get_name(): string
	{
		return 'tablekit_table';
	}

	public function get_title(): string
	{
		return __('TableKit', 'tablekit');
	}

	public function get_icon(): string
	{
		return 'eicon-table';
	}

	public function get_categories(): array
	{
		return ['general'];
	}

	public function get_keywords(): array
	{
		return ['table', 'tablekit', 'data', 'grid'];
	}

	public function is_reload_preview_required(): bool
	{
		return true;
	}

	// -------------------------------------------------------------------------
	// Controls
	// -------------------------------------------------------------------------

	protected function register_controls(): void
	{
		$this->start_controls_section(
			'tablekit_table_section',
			[
				'label' => __('Table', 'tablekit'),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'table_id',
			[
				'label'       => __('Select Table', 'tablekit'),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $this->get_table_options(),
				'default'     => '',
				'render_type' => 'template',
				'label_block' => true,
				'description' => __('Create and manage tables under TableKit → Tables in the WP admin.', 'tablekit'),
			]
		);

		$this->add_control(
			'block_index',
			[
				'label'       => __('Select Block', 'tablekit'),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => ['' => __('— All Blocks —', 'tablekit')],
				'default'     => self::ALL_BLOCKS,
				'render_type' => 'template',
				'label_block' => true,
				'description' => __('Choose a specific block to display. Or select — All Blocks — to show all blocks.', 'tablekit'),
			]
		);

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	protected function render(): void
	{
		$settings = $this->get_settings_for_display();
		$raw_id   = $settings['table_id'] ?? 0;

		if (is_array($raw_id)) {
			$raw_id = reset($raw_id);
		}

		$table_id = absint($raw_id);

		if (empty($table_id)) {
			$this->render_placeholder(__('Select a table to display.', 'tablekit'));
			return;
		}

		$post = get_post($table_id);

		if (! $post) {
			$this->render_placeholder(__('Table not found.', 'tablekit'));
			return;
		}

		if (class_exists('\\TableBuilder\\Config\\Blocks')) {
			\TableBuilder\Config\Blocks::instance()->enqueue_block_assets();
		}

		$all_blocks    = parse_blocks($post->post_content);
		$table_blocks  = $this->filter_table_blocks($all_blocks);
		$block_index   = $settings['block_index'] ?? self::ALL_BLOCKS;
		$blocks_to_render = $all_blocks;

		if (self::ALL_BLOCKS !== $block_index && '' !== $block_index) {
			$index = (int) $block_index;
			if (isset($table_blocks[$index])) {
				$blocks_to_render = array($table_blocks[$index]);
			}
		}

		$block_names = $this->collect_block_names($blocks_to_render);

		$this->print_shared_styles($block_names);
		$this->print_table_styles($table_id, $post->post_content);

		echo '<div class="tablekit-elementor-wrap">';
		foreach ($blocks_to_render as $block) {
			echo (new WP_Block($block))->render();
		}
		echo '</div>';

		$this->print_block_scripts($block_names);

		if (class_exists('\\TableBuilder\\Shortcode\\ShortcodeUtils')) {
			echo \TableBuilder\Shortcode\ShortcodeUtils::get_elementor_init_script();
		}
	}

	protected function content_template(): void {}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	private function render_placeholder(string $message): void
	{
		echo '<div class="tablekit-el-placeholder" style="padding:1.5em;border:1px dashed #ccc;text-align:center;color:#999;">'
			. '<span class="eicon-table" style="font-size:2em;display:block;margin-bottom:.5em;opacity:.35;"></span>'
			. '<p style="margin:0;">' . esc_html($message) . '</p>'
			. '</div>';
	}

	private function filter_table_blocks(array $blocks): array
	{
		return ShortcodeUtils::filter_table_blocks($blocks);
	}

	/**
	 * Print shared CSS once per page load.
	 */
	private function print_shared_styles(array $block_names): void
	{
		$css = '';

		if (! self::$global_styles_printed) {
			foreach (['global.css', 'components.css'] as $filename) {
				$path = TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/tablebuilder/' . $filename;
				if (file_exists($path)) {
					$css .= file_get_contents($path) . "\n"; // phpcs:ignore
				}
			}

			$css .= $this->get_registered_style_css('tablebuilder/table-builder');
			self::$global_styles_printed = true;
			self::$printed_block_styles['tablebuilder/table-builder'] = true;
		}

		foreach ($block_names as $name) {
			if (isset(self::$printed_block_styles[$name])) {
				continue;
			}
			$css .= $this->get_registered_style_css($name);
			self::$printed_block_styles[$name] = true;
		}

		if ('' !== trim($css)) {
			echo '<style id="tablekit-shared-styles">' . $css . '</style>'; // phpcs:ignore
		}
	}

	/**
	 * Print per-table dynamic CSS. Unique ID per table so multiple widgets coexist.
	 */
	private function print_table_styles(int $table_id, string $post_content): void
	{
		if (! class_exists('\\TableBuilder\\Shortcode\\Shortcode')) {
			return;
		}

		$shortcode = \TableBuilder\Shortcode\Shortcode::instance();

		if (! $shortcode || ! method_exists($shortcode, 'collect_blocks_css_from_content')) {
			return;
		}

		$css = $shortcode->collect_blocks_css_from_content($post_content);

		if ('' !== trim($css)) {
			echo '<style id="tablekit-el-css-' . esc_attr((string) $table_id) . '">' . $css . '</style>'; // phpcs:ignore
		}
	}

	/**
	 * Enqueue + immediately print block view-scripts inline.
	 * Required for the editor iframe where wp_footer never fires.
	 */
	private function print_block_scripts(array $block_names): void
	{
		// FIX 8: only print once per full or partial render cycle
		static $printed = [];

		foreach (array_unique(array_merge(['tablebuilder/table-builder'], $block_names)) as $name) {
			$handle = \TableBuilder\Shortcode\ShortcodeUtils::get_block_asset_handle($name, 'viewScript');
			if (isset($printed[$handle])) {
				continue;
			}
			wp_enqueue_script($handle);
			wp_print_scripts($handle);
			$printed[$handle] = true;
		}
	}

	/**
	 * Read the CSS file of a registered WP stylesheet handle.
	 */
	private function get_registered_style_css(string $block_name): string
	{
		$handle = \TableBuilder\Shortcode\ShortcodeUtils::get_block_asset_handle($block_name, 'style');
		$style  = wp_styles()->query($handle, 'registered');

		if (! $style || empty($style->src)) {
			return '';
		}

		$path = str_replace(content_url(), WP_CONTENT_DIR, (string) $style->src);
		$path = (string) strtok($path, '?');

		if (! file_exists($path)) {
			return '';
		}

		return file_get_contents($path) . "\n"; // phpcs:ignore
	}

	/**
	 * Recursively collect unique block names from a block tree.
	 */
	private function collect_block_names(array $blocks): array
	{
		$all = ShortcodeUtils::collect_blocks_recursive(
			$blocks,
			static fn(array $block): bool => is_string($block['blockName'] ?? null) && '' !== $block['blockName']
		);

		return array_values(array_unique(array_column($all, 'blockName')));
	}

	/**
	 * Build the SELECT2 options array for the table picker control.
	 */
	private function get_table_options(): array
	{
		if (is_array(self::$table_options_cache)) {
			return self::$table_options_cache;
		}

		$options = ['' => __('-- Select a Table --', 'tablekit')];

		$tables = get_posts(
			[
				'post_type'      => 'tablekit_table',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		foreach ($tables as $table) {
			$options[(string) $table->ID] = esc_html($table->post_title);
		}

		if (class_exists('\\TableBuilder\\Config\\CPT\\TableCPT')) {
			$inline = \TableBuilder\Config\CPT\TableCPT::instance()->get_inline_table_source_options();
			foreach ($inline as $id => $label) {
				$key = (string) $id;
				if (! isset($options[$key])) {
					$options[$key] = esc_html($label);
				}
			}
		}

		self::$table_options_cache = $options;

		return $options;
	}
}
