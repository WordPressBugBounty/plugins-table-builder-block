<?php
/**
 * Block registration, editor/frontend asset enqueueing, and saved-markup filtering
 *
 * @package TableKit
 */

namespace TableBuilder\Config;

defined( 'ABSPATH' ) || exit;

use WP_Query;
use TableBuilder\Traits\Singleton;
use TableBuilder\Helpers\Utils;

/**
 * Registers TableKit's blocks, their assets, and post-processes their saved markup.
 */
class Blocks {

	use Singleton;

	/**
	 * Hooks block registration and asset enqueueing into WordPress.
	 */
	protected function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'block_categories_all', array( $this, 'register_block_categories' ), 10, 2 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_assets' ), 5 );
		add_filter( 'render_block', array( $this, 'save_block_element' ), 10, 3 );
	}

	/**
	 * Registers custom blocks for TableBuilder.
	 */
	public function register_blocks() {
		$blocks_list = \TableBuilder\Config\BlockList::get_block_list();

		if ( ! empty( $blocks_list ) ) {
			foreach ( $blocks_list as $key => $block ) {
				$package     = isset( $block['package'] ) ? $block['package'] : '';
				$blocks_dir  = '';
				$plugin_dir  = '';
				$plugin_slug = '';

				if ( ! empty( $package ) && 'free' === $package ) {
					$plugin_dir  = TABLE_BUILDER_BLOCK_PLUGIN_DIR;
					$blocks_dir  = TABLE_BUILDER_BLOCK_DIR . $key;
					$plugin_slug = 'table-builder-block';
				}

				if ( ! empty( $package ) && 'pro' === $package && defined( 'TABLE_BUILDER_BLOCK_PRO_PLUGIN_DIR' ) ) {
					$plugin_dir  = TABLE_BUILDER_BLOCK_PRO_PLUGIN_DIR;
					$blocks_dir  = $plugin_dir . '/build/blocks/' . $key;
					$plugin_slug = 'table-builder-block-pro';
				}

				if ( file_exists( $blocks_dir ) ) {
					register_block_type( $blocks_dir );
				}
			}
		}
	}

	/**
	 * Registers the "Table Builder" block category.
	 *
	 * @param array[]       $categories Existing block categories.
	 * @param \WP_Post|null $post      The post being edited, if any.
	 * @return array[] Categories with the Table Builder category prepended.
	 */
	public function register_block_categories( $categories, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the "block_categories_all" filter signature; not needed in the body.
		return array_merge(
			array(
				array(
					'slug'  => 'tablebuilder',
					'title' => __( 'Table Builder', 'table-builder-block' ),
				),
			),
			$categories
		);
	}


	/**
	 * Enqueues the shared global/component stylesheets on both the editor and frontend.
	 *
	 * @return void
	 */
	public function enqueue_block_assets() {
		wp_enqueue_style(
			'gkit-components',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/tablebuilder/components.css',
			array(),
			TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
			'all'
		);

		wp_enqueue_style(
			'table-builder-block-global',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/tablebuilder/global.css',
			array(),
			TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
			'all'
		);
	}

	/**
	 * Enqueues each registered block's editor script/style, based on its
	 * generated index.asset.php dependency manifest.
	 *
	 * @return void
	 */
	public function block_editor_assets() {
		$blocks_list = \TableBuilder\Config\BlockList::get_block_list();

		foreach ( $blocks_list as $key => $block ) {
			$block_dir  = TABLE_BUILDER_BLOCK_PLUGIN_DIR . "blocks/{$key}";
			$plugin_url = TABLE_BUILDER_BLOCK_PLUGIN_URL . "build/blocks/{$key}";

			if ( ! file_exists( "{$block_dir}/index.asset.php" ) ) {
				continue;
			}

			$editor_asset = include "{$block_dir}/index.asset.php";

			wp_enqueue_script(
				"{$key}-editor",
				"{$plugin_url}/index.js",
				$editor_asset['dependencies'] ?? array(),
				$editor_asset['version'] ?? TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
				true
			);

			wp_enqueue_style(
				"{$key}-editor-style",
				"{$plugin_url}/index.css",
				array(),
				TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
				'all'
			);
		}
	}

	/**
	 * Filters a table block's saved markup to add a stable id/data attributes
	 * and the "table-builder-block" class, via WP_HTML_Tag_Processor.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $parsed_block  Parsed block data (name, attrs, etc.).
	 * @param mixed  $instance      The block instance (WP_Block or similar).
	 * @return string The filtered block HTML.
	 */
	public function save_block_element( $block_content, $parsed_block, $instance ) {
		if ( ! empty( $block_content ) && Utils::is_table_builder_block( $block_content, $parsed_block, 'blockClass' ) ) {
			$block_processor = new \WP_HTML_Tag_Processor( $block_content );
			$block_processor->next_tag();

			$attributes = array(
				'id'         => 'block-' . ( $parsed_block['attrs']['blockID'] ?? 'default' ),
				'data-block' => $parsed_block['blockName'] ?? '',
			);

			foreach ( $attributes as $attr => $value ) {
				if ( empty( $block_processor->get_attribute( $attr ) ) ) {
					$block_processor->set_attribute( $attr, $value );
				}
			}

			if ( ! empty( $parsed_block['attrs']['blockClass'] ) ) {
				$block_processor->add_class( $parsed_block['attrs']['blockClass'] );
			}

			$block_processor->add_class( 'table-builder-block' );

			/**
			 * Filters markup prepended to a table block's saved element, before rendering.
			 *
			 * @since Unknown
			 *
			 * @param string $before_markup Markup to prepend; empty by default.
			 * @param array  $parsed_block  Parsed block data (name, attrs, etc.).
			 */
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-namespaced hook names are this plugin's established public API (used by the Pro add-on); renaming would be a breaking change.
			$before_markup = apply_filters( 'tablebuilder/save_element_markup_before', '', $parsed_block );

			/**
			 * Filters markup appended to a table block's saved element, after rendering.
			 *
			 * @since Unknown
			 *
			 * @param string $after_markup Markup to append; empty by default.
			 * @param array  $parsed_block Parsed block data (name, attrs, etc.).
			 */
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-namespaced hook names are this plugin's established public API (used by the Pro add-on); renaming would be a breaking change.
			$after_markup  = apply_filters( 'tablebuilder/save_element_markup_after', '', $parsed_block );

			/**
			 * Filters the WP_HTML_Tag_Processor instance for a table block's saved element markup,
			 * letting callbacks make further attribute/class changes before it's serialized.
			 *
			 * @since Unknown
			 *
			 * @param \WP_HTML_Tag_Processor $block_processor Tag processor wrapping the block's markup.
			 * @param array                  $parsed_block    Parsed block data (name, attrs, etc.).
			 * @param mixed                  $instance         The block instance (WP_Block or similar).
			 */
			$block_content = apply_filters( 'tablebuilder_save_element_markup', $block_processor, $parsed_block, $instance );

			if ( method_exists( $block_content, 'get_updated_html' ) ) {
				$block_content = $block_content->get_updated_html();
			}

			return $before_markup . $block_content . $after_markup;
		}

		return $block_content;
	}
}
