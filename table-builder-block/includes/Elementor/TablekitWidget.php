<?php
/**
 * Elementor widget for TableKit tables
 *
 * @package TableKit
 */

use TableBuilder\Shortcode\ShortcodeUtils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a TableKit table as a native Elementor widget.
 */
class TableKit_Elementor_Widget extends \Elementor\Widget_Base {

	private const ALL_BLOCKS = '';

	/**
	 * Whether the shared global styles have been printed yet this request.
	 *
	 * @var bool
	 */
	private static bool $global_styles_printed = false;

	/**
	 * Block IDs whose per-instance styles have already been printed.
	 *
	 * @var array
	 */
	private static array $printed_block_styles = array();

	/**
	 * Block IDs whose per-instance scripts have already been printed.
	 *
	 * @var array
	 */
	private static array $printed_block_scripts = array();

	/**
	 * Cached table options list, or null if not yet built.
	 *
	 * @var array|null
	 */
	private static ?array $table_options_cache = null;

	// -------------------------------------------------------------------------
	// Identity
	// -------------------------------------------------------------------------

	/**
	 * Elementor widget's internal name/slug.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'tablekit_table';
	}

	/**
	 * Elementor widget's display title (shown in the widget panel).
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'TableKit', 'table-builder-block' );
	}

	/**
	 * Elementor widget's icon class.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-table';
	}

	/**
	 * Elementor category slugs this widget is listed under.
	 *
	 * @return string[]
	 */
	public function get_categories(): array {
		return array( 'general' );
	}

	/**
	 * Search keywords Elementor's widget panel matches against.
	 *
	 * @return string[]
	 */
	public function get_keywords(): array {
		return array( 'table', 'tablekit', 'data', 'grid' );
	}

	/**
	 * Whether Elementor should force a full preview reload when this widget's
	 * settings change (true, since table selection/rendering isn't done via
	 * live-refreshable controls).
	 *
	 * @return bool
	 */
	public function is_reload_preview_required(): bool {
		return true;
	}

	// -------------------------------------------------------------------------
	// Controls
	// -------------------------------------------------------------------------

	/**
	 * Registers the widget's Elementor editor controls (table picker, block picker).
	 *
	 * @return void
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'tablekit_table_section',
			array(
				'label' => __( 'Table', 'table-builder-block' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'table_id',
			array(
				'label'       => __( 'Select Table', 'table-builder-block' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'options'     => $this->get_table_options(),
				'default'     => '',
				'render_type' => 'template',
				'label_block' => true,
				'description' => __( 'Create and manage tables under TableKit → Tables in the WP admin.', 'table-builder-block' ),
			)
		);

		$this->add_control(
			'block_index',
			array(
				'label'       => __( 'Select Block', 'table-builder-block' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => array( '' => __( '— All Blocks —', 'table-builder-block' ) ),
				'default'     => self::ALL_BLOCKS,
				'render_type' => 'template',
				'label_block' => true,
				'description' => __( 'Choose a specific block to display. Or select — All Blocks — to show all blocks.', 'table-builder-block' ),
			)
		);

		$this->end_controls_section();
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	/**
	 * Renders the widget on the frontend/editor: resolves the selected table post,
	 * optionally narrow to one block, print shared/per-table CSS, render the
	 * table block(s), and print their view scripts.
	 *
	 * @return void
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$raw_id   = $settings['table_id'] ?? 0;

		if ( is_array( $raw_id ) ) {
			$raw_id = reset( $raw_id );
		}

		$table_id = absint( $raw_id );

		if ( empty( $table_id ) ) {
			$this->render_placeholder( __( 'Select a table to display.', 'table-builder-block' ) );
			return;
		}

		$post = get_post( $table_id );

		if ( ! $post ) {
			$this->render_placeholder( __( 'Table not found.', 'table-builder-block' ) );
			return;
		}

		if ( class_exists( '\\TableBuilder\\Config\\Blocks' ) ) {
			\TableBuilder\Config\Blocks::instance()->enqueue_block_assets();
		}

		$all_blocks       = parse_blocks( $post->post_content );
		$table_blocks     = $this->filter_table_blocks( $all_blocks );
		$block_index      = $settings['block_index'] ?? self::ALL_BLOCKS;
		$blocks_to_render = $all_blocks;

		if ( self::ALL_BLOCKS !== $block_index && '' !== $block_index ) {
			$index = (int) $block_index;
			if ( isset( $table_blocks[ $index ] ) ) {
				$blocks_to_render = array( $table_blocks[ $index ] );
			}
		}

		$block_names = $this->collect_block_names( $blocks_to_render );

		$this->print_shared_styles( $block_names );
		$this->print_table_styles( $table_id, $post->post_content );

		echo '<div class="tablekit-elementor-wrap">';
		foreach ( $blocks_to_render as $block ) {
			echo ( new WP_Block( $block ) )->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- standard core block-rendering output, same as render_block().
		}
		echo '</div>';

		$this->print_block_scripts( $block_names );

		if ( class_exists( '\\TableBuilder\\Shortcode\\ShortcodeUtils' ) ) {
			echo \TableBuilder\Shortcode\ShortcodeUtils::get_elementor_init_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static hardcoded <script> string, no user input.
		}
	}

	/**
	 * No-op: this widget renders server-side only (render() above), Elementor's
	 * JS live-preview template is intentionally unused.
	 *
	 * @return void
	 */
	protected function content_template(): void {}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Renders a simple placeholder message in place of the table (used when no
	 * table is selected, or the selected table post can't be found).
	 *
	 * @param string $message Message to display.
	 * @return void
	 */
	private function render_placeholder( string $message ): void {
		echo '<div class="tablekit-el-placeholder" style="padding:1.5em;border:1px dashed #ccc;text-align:center;color:#999;">'
			. '<span class="eicon-table" style="font-size:2em;display:block;margin-bottom:.5em;opacity:.35;"></span>'
			. '<p style="margin:0;">' . esc_html( $message ) . '</p>'
			. '</div>';
	}

	/**
	 * Narrow a parsed block tree down to just the plugin's table blocks.
	 *
	 * @param array $blocks Parsed block tree (as from parse_blocks()).
	 * @return array Table blocks only.
	 */
	private function filter_table_blocks( array $blocks ): array {
		return ShortcodeUtils::filter_table_blocks( $blocks );
	}

	/**
	 * Prints shared CSS once per page load.
	 *
	 * @param string[] $block_names Block names appearing in this render, used to
	 *                              additionally print each block's registered style once.
	 * @return void
	 */
	private function print_shared_styles( array $block_names ): void {
		$css = '';

		if ( ! self::$global_styles_printed ) {
			foreach ( array( 'global.css', 'components.css' ) as $filename ) {
				$path = TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/tablebuilder/' . $filename;
				if ( file_exists( $path ) ) {
					$css .= file_get_contents($path) . "\n"; // phpcs:ignore
				}
			}

			$css                        .= $this->get_registered_style_css( 'tablebuilder/table-builder' );
			self::$global_styles_printed = true;
			self::$printed_block_styles['tablebuilder/table-builder'] = true;
		}

		foreach ( $block_names as $name ) {
			if ( isset( self::$printed_block_styles[ $name ] ) ) {
				continue;
			}
			$css                                .= $this->get_registered_style_css( $name );
			self::$printed_block_styles[ $name ] = true;
		}

		if ( '' !== trim( $css ) ) {
			echo '<style id="tablekit-shared-styles">' . $css . '</style>'; // phpcs:ignore
		}
	}

	/**
	 * Prints per-table dynamic CSS. Uses a unique ID per table so multiple widgets coexist.
	 *
	 * @param int    $table_id     Table post ID, used to build a unique <style> element ID.
	 * @param string $post_content Raw post content to collect block-generated CSS from.
	 * @return void
	 */
	private function print_table_styles( int $table_id, string $post_content ): void {
		if ( ! class_exists( '\\TableBuilder\\Shortcode\\Shortcode' ) ) {
			return;
		}

		$shortcode = \TableBuilder\Shortcode\Shortcode::instance();

		if ( ! $shortcode || ! method_exists( $shortcode, 'collect_blocks_css_from_content' ) ) {
			return;
		}

		$css = $shortcode->collect_blocks_css_from_content( $post_content );

		if ( '' !== trim( $css ) ) {
			echo '<style id="tablekit-el-css-' . esc_attr((string) $table_id) . '">' . $css . '</style>'; // phpcs:ignore
		}
	}

	/**
	 * Enqueues and immediately prints block view-scripts inline.
	 * Required for the editor iframe where wp_footer never fires.
	 *
	 * @param string[] $block_names Block names to print view scripts for, in addition
	 *                              to the base "tablebuilder/table-builder" script.
	 * @return void
	 */
	private function print_block_scripts( array $block_names ): void {
		// FIX 8: only print once per full or partial render cycle.
		static $printed = array();

		foreach ( array_unique( array_merge( array( 'tablebuilder/table-builder' ), $block_names ) ) as $name ) {
			$handle = \TableBuilder\Shortcode\ShortcodeUtils::get_block_asset_handle( $name, 'viewScript' );
			if ( isset( $printed[ $handle ] ) ) {
				continue;
			}
			wp_enqueue_script( $handle );
			wp_print_scripts( $handle );
			$printed[ $handle ] = true;
		}
	}

	/**
	 * Reads the CSS file of a registered WP stylesheet handle.
	 *
	 * @param string $block_name Block name to resolve the registered "style" asset handle for.
	 * @return string The stylesheet's contents, or an empty string if not found/registered.
	 */
	private function get_registered_style_css( string $block_name ): string {
		$handle = \TableBuilder\Shortcode\ShortcodeUtils::get_block_asset_handle( $block_name, 'style' );
		$style  = wp_styles()->query( $handle, 'registered' );

		if ( ! $style || empty( $style->src ) ) {
			return '';
		}

		$path = str_replace( content_url(), WP_CONTENT_DIR, (string) $style->src );
		$path = (string) strtok( $path, '?' );

		if ( ! file_exists( $path ) ) {
			return '';
		}

		return file_get_contents($path) . "\n"; // phpcs:ignore
	}

	/**
	 * Recursively collects unique block names from a block tree.
	 *
	 * @param array $blocks Parsed block tree.
	 * @return string[] Unique block names found in the tree.
	 */
	private function collect_block_names( array $blocks ): array {
		$all = ShortcodeUtils::collect_blocks_recursive(
			$blocks,
			static fn( array $block ): bool => is_string( $block['blockName'] ?? null ) && '' !== $block['blockName']
		);

		return array_values( array_unique( array_column( $all, 'blockName' ) ) );
	}

	/**
	 * Builds the SELECT2 options array for the table picker control (published
	 * tablekit_table posts plus any inline-block source posts), memoized per request.
	 *
	 * @return array<string,string> Options keyed by post ID (as a string), valued by label.
	 */
	private function get_table_options(): array {
		if ( is_array( self::$table_options_cache ) ) {
			return self::$table_options_cache;
		}

		$options = array( '' => __( '-- Select a Table --', 'table-builder-block' ) );

		$tables = get_posts(
			array(
				'post_type'      => 'tablekit_table',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $tables as $table ) {
			$options[ (string) $table->ID ] = esc_html( $table->post_title );
		}

		if ( class_exists( '\\TableBuilder\\Config\\CPT\\TableCPT' ) ) {
			$inline = \TableBuilder\Config\CPT\TableCPT::instance()->get_inline_table_source_options();
			foreach ( $inline as $id => $label ) {
				$key = (string) $id;
				if ( ! isset( $options[ $key ] ) ) {
					$options[ $key ] = esc_html( $label );
				}
			}
		}

		self::$table_options_cache = $options;

		return $options;
	}
}
