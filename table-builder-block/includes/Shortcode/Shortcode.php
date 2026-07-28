<?php
/**
 * The [tableKit] shortcode
 *
 * @package TableKit
 */

namespace TableBuilder\Shortcode;

use TableBuilder\Shortcode\ShortcodeUtils;
use TableBuilder\Render\BlockRenderer;
use TableBuilder\Traits\Singleton;

defined( 'ABSPATH' ) || exit;

/**
 * Handles registration and rendering of the [tableKit] shortcode.
 */
class Shortcode {


	use Singleton;

	/**
	 * Whether frontend assets have been enqueued at least once.
	 *
	 * @var bool
	 */
	private static bool $assets_enqueued = false;

	// Default shortcode attribute values.
	private const DEFAULT_ATTS = array(
		'id'         => '',
		'post_id'    => 0,
		'block_id'   => '',
		'class'      => '',
		'attrs_json' => '{}',
	);

	// Default block attribute values.
	private const DEFAULT_BLOCK_ATTRS = array(
		'headers'     => array(),
		'footers'     => array(),
		'hasHeader'   => true,
		'hasFooter'   => false,
		'columnCount' => 0,
	);

	/**
	 * Hooks shortcode registration into WordPress init.
	 */
	protected function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
	}

	/**
	 * Registers all shortcodes for this plugin.
	 *
	 * @return void
	 */
	public function register_shortcodes(): void {
		add_shortcode( 'tableKit', array( $this, 'render' ) );
	}

	/**
	 * [tableKit] shortcode callback. Renders either every table block found in
	 * the resolved source post (when no specific block/id is given) or a single
	 * resolved block.
	 *
	 * @param array|string $raw_atts Raw shortcode attributes as passed by WordPress.
	 * @param string       $content  Optional. Shortcode enclosed content (currently unused
	 *                               beyond being passed through to the block renderer).
	 * @return string Rendered HTML.
	 */
	public function render( $raw_atts, string $content = '' ): string {
		$atts = $this->sanitize_atts( shortcode_atts( self::DEFAULT_ATTS, (array) $raw_atts, 'tableKit' ) );
		$ids  = $this->parse_ids( $atts );

		ob_start();

		if ( empty( $ids['block_id'] ) && empty( $ids['raw_id'] ) ) {
			echo $this->render_all_blocks( $ids, $atts, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from the_content filter output and esc_*()-escaped pieces, see render_all_blocks().
			return (string) ob_get_clean();
		}

		$block = $this->locate_block( $ids['post_id'], $ids['block_id'], $ids['raw_id'] );

		if ( ! empty( $block ) ) {
			echo $this->render_single_block( $block, $atts, $ids, $content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- delegates to BlockRenderer::render_table_builder(), which escapes/kses's its own output.
		}

		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Multi-block rendering (no block_id given)
	// -------------------------------------------------------------------------

	/**
	 * Renders the source post/page content for post_id-only shortcodes.
	 * For standalone tablekit_table CPT entries, this preserves the original
	 * behavior of rendering the stored block content.
	 *
	 * @param array  $ids     Resolved ID set from parse_ids() (post_id/block_id/raw_id).
	 * @param array  $atts    Sanitized shortcode attributes.
	 * @param string $content Shortcode enclosed content (unused here).
	 * @return string Rendered HTML, or an empty string if the post can't be found/viewed.
	 */
	private function render_all_blocks( array $ids, array $atts, string $content ): string {
		if ( empty( $ids['post_id'] ) ) {
			return '';
		}

		$post_id        = (int) $ids['post_id'];
		static $cleared = array();
		if ( ShortcodeUtils::is_elementor_editor() && ! isset( $cleared[ $post_id ] ) ) {
			clean_post_cache( $post_id );
			$cleared[ $post_id ] = true;
		}

		$post = get_post( $post_id );

		if ( ! $post || empty( $post->post_content ) || ! $this->can_view_post( $post ) ) {
			return '';
		}
		$this->block_enqueue_assets();
		$css   = $this->collect_blocks_css_from_content( $post->post_content );
		$style = $css ? '<style id="tbk-shortcode-' . absint( $post->ID ) . '">' . $css . '</style>' : '';

		$previous_post = $GLOBALS['post'] ?? null;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- required so the "the_content" filter (and any callback that reads $GLOBALS['post']/get_post()) sees the correct post context; restored immediately below via wp_reset_postdata()/the original value.
		$GLOBALS['post'] = $post; // Ensure filters use the shortcode post context.
		setup_postdata( $post );
		$content = (string) apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- "the_content" is a WordPress core filter, not a hook defined by this plugin.
		wp_reset_postdata();
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring the previous global post value, not overriding it.
		$GLOBALS['post'] = $previous_post;

		return $style . $content . ShortcodeUtils::get_elementor_init_script();
	}

	// -------------------------------------------------------------------------
	// Single-block rendering
	// -------------------------------------------------------------------------

	/**
	 * Renders a single resolved block. Used both by the multi-block path and the
	 * specific block_id path.
	 *
	 * @param array  $block   Parsed block array to render.
	 * @param array  $atts    Sanitized shortcode attributes.
	 * @param array  $ids     Resolved ID set from parse_ids().
	 * @param string $content Shortcode enclosed content, passed through to the "tablekit/render_block" filter.
	 * @return string Rendered HTML.
	 */
	private function render_single_block( array $block, array $atts, array $ids, string $content ): string {
		// Pro hooks handle their own block types.
		/**
		 * Filters the rendered output for a single [tableKit] block, letting the
		 * Pro add-on (or third parties) render block types this plugin doesn't know about.
		 *
		 * @since Unknown
		 *
		 * @param string|null $output  Rendered HTML to short-circuit with, or null to use the default renderer.
		 * @param array       $block   Parsed block array to render.
		 * @param array       $atts    Sanitized shortcode attributes.
		 * @param array       $ids     Resolved ID set from parse_ids().
		 * @param string      $content Shortcode enclosed content.
		 */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-namespaced hook name is this plugin's established public API (used by the Pro add-on); renaming would be a breaking change.
		$output = apply_filters( 'tablekit/render_block', null, $block, $atts, $ids, $content );
		if ( null !== $output ) {
			return (string) $output;
		}

		$attrs = $this->prepare_attrs( $block, $atts, $ids['block_id'] );
		$rows  = $block['innerBlocks'] ?? array();
		$css   = ShortcodeUtils::collect_block_css( $block );
		$this->block_enqueue_assets();
		$style = $css ? '<style id="tb-' . esc_attr( $attrs['blockID'] ) . '">' . $css . '</style>' : '';

		return $style . BlockRenderer::render_table_builder( $attrs, $rows, $content );
	}

	// -------------------------------------------------------------------------
	// Attribute Preparation
	// -------------------------------------------------------------------------

	/**
	 * Resolves a block's final attribute set: applies any `attrs_json` shortcode
	 * override, assigns a stable block ID, appends an extra CSS class, and fills
	 * in default header/footer/column values.
	 *
	 * @param array  $block    Parsed block array (its stored `attrs` are the base).
	 * @param array  $atts     Sanitized shortcode attributes.
	 * @param string $block_id Resolved block ID (may be empty; falls back to the block's own ID or a generated UUID).
	 * @return array Final attribute array.
	 */
	private function prepare_attrs( array $block, array $atts, string $block_id ): array {
		$attrs = $block['attrs'] ?? array();
		$rows  = $block['innerBlocks'] ?? array();

		// Override stored attributes with any inline JSON overrides.
		$json_overrides = ShortcodeUtils::decode_json( $atts['attrs_json'] );
		if ( $json_overrides ) {
			$attrs = array_replace_recursive( $attrs, $json_overrides );
		}

		// Ensure a stable unique block ID.
		if ( $block_id ) {
			$attrs['blockID'] = $block_id;
		} elseif ( empty( $attrs['blockID'] ) ) {
			$attrs['blockID'] = 'tb-' . wp_generate_uuid4();
		}

		// Append any extra CSS class passed via shortcode.
		if ( ! empty( $atts['class'] ) ) {
			$extra_class         = sanitize_html_class( $atts['class'] );
			$attrs['blockClass'] = trim( ( $attrs['blockClass'] ?? '' ) . ' ' . $extra_class );
		}

		return $this->apply_block_defaults( $attrs, $rows );
	}

	/**
	 * Merges in default block attribute values, and generates placeholder header/footer
	 * cells when a table has headers/footers enabled but no cell data yet.
	 *
	 * @param array $attrs Block attributes to fill in defaults for.
	 * @param array $rows  The block's inner row blocks, used to detect column count when needed.
	 * @return array Attribute array with defaults applied.
	 */
	private function apply_block_defaults( array $attrs, array $rows ): array {
		$attrs = array_replace_recursive( self::DEFAULT_BLOCK_ATTRS, $attrs );

		$col_count = (int) $attrs['columnCount'];

		if ( ! $col_count && $rows ) {
			$col_count = $this->detect_column_count( $rows );
		}

		if ( $attrs['hasHeader'] && empty( $attrs['headers'] ) && $col_count ) {
			$attrs['headers'] = $this->generate_placeholder_cells( $col_count, 'Header' );
		}

		if ( $attrs['hasFooter'] && empty( $attrs['footers'] ) && $col_count ) {
			$attrs['footers'] = $this->generate_placeholder_cells( $col_count, 'Footer' );
		}

		return $attrs;
	}

	/**
	 * Counts columns from the first "tablebuilder/table-builder-row" block's inner blocks.
	 *
	 * @param array $rows Row blocks (a table block's innerBlocks).
	 * @return int Detected column count, or 0 if no row block is found.
	 */
	private function detect_column_count( array $rows ): int {
		foreach ( $rows as $row ) {
			if ( 'tablebuilder/table-builder-row' === ( $row['blockName'] ?? '' ) ) {
				return count( $row['innerBlocks'] ?? array() );
			}
		}

		return 0;
	}

	/**
	 * Builds a list of placeholder cell arrays, e.g. "Header 1", "Header 2", ...
	 *
	 * @param int    $count  Number of cells to generate.
	 * @param string $prefix Label prefix, e.g. "Header" or "Footer".
	 * @return array[] Array of ['title' => string] cell descriptors.
	 */
	private function generate_placeholder_cells( int $count, string $prefix ): array {
		return array_map(
			static fn( int $i ) => array( 'title' => "{$prefix} " . ( $i + 1 ) ),
			range( 0, $count - 1 )
		);
	}

	// -------------------------------------------------------------------------
	// ID Resolution
	// -------------------------------------------------------------------------

	/**
	 * Resolves the "id" attribute (which may be a numeric post ID or an alphanumeric
	 * block ID) alongside the explicit "post_id"/"block_id" attributes into a single
	 * unambiguous ID set.
	 *
	 * @param array $atts Sanitized shortcode attributes.
	 * @return array{raw_id:string,post_id:int,block_id:string} Resolved IDs.
	 */
	private function parse_ids( array $atts ): array {
		$raw_id   = trim( (string) ( $atts['id'] ?? '' ) );
		$post_id  = (int) ( $atts['post_id'] ?? 0 );
		$block_id = (string) ( $atts['block_id'] ?? '' );

		if ( ! $post_id && ctype_digit( $raw_id ) ) {
			$post_id = (int) $raw_id;
		}

		if ( ! $block_id && $raw_id && ! ctype_digit( $raw_id ) ) {
			$block_id = $raw_id;
		}

		return compact( 'raw_id', 'post_id', 'block_id' );
	}

	// -------------------------------------------------------------------------
	// Block Resolution (specific block_id path only)
	// -------------------------------------------------------------------------

	/**
	 * Locates a target block, either within a specific post (when $post_id is given)
	 * or by searching the database for any post containing the block ID.
	 *
	 * @param int    $post_id  Post ID to look within first, or 0 to skip straight to a database search.
	 * @param string $block_id Explicit block ID to search for.
	 * @param string $raw_id   Fallback ID (from the shortcode's "id" attribute) used when $block_id is empty.
	 * @return array The located block, or an empty array if not found.
	 */
	private function locate_block( int $post_id, string $block_id, string $raw_id ): array {
		if ( $post_id ) {
			$block = $this->get_block_from_post( $post_id, $block_id );
			if ( $block ) {
				return $block;
			}
		}

		$lookup = $block_id ? $block_id : $raw_id;

		return $lookup ? $this->search_block_by_id( $lookup ) : array();
	}

	/**
	 * Parses a specific post's block content and finds the target block.
	 *
	 * @param int    $post_id  Post ID to load and parse.
	 * @param string $block_id Block ID to search for.
	 * @return array The located block, or an empty array if not found/not viewable.
	 */
	private function get_block_from_post( int $post_id, string $block_id ): array {
		$post = get_post( $post_id );

		if ( ! $post || empty( $post->post_content ) || ! $this->can_view_post( $post ) ) {
			return array();
		}

		return $this->find_table_block( parse_blocks( $post->post_content ), $block_id );
	}

	/**
	 * Whether the current request is allowed to see a post's content.
	 * Prevents the [tableKit] shortcode from being used to pull table
	 * content out of private/draft/pending posts the viewer can't read.
	 *
	 * @param \WP_Post $post Post to check.
	 * @return bool True if the post is published, or the viewer has "read_post" capability on it.
	 */
	private function can_view_post( \WP_Post $post ): bool {
		if ( 'publish' === $post->post_status ) {
			return true;
		}

		return current_user_can( 'read_post', $post->ID );
	}

	/**
	 * Searches the database for posts containing the given block ID.
	 * Candidate post IDs are cached for 5 minutes per block ID.
	 *
	 * @param string $block_id Block ID (or short ID) to search for in post content.
	 * @return array The located block, or an empty array if not found.
	 */
	private function search_block_by_id( string $block_id ): array {
		global $wpdb;

		$cache_key     = 'tablekit_block_search_' . md5( $block_id );
		$candidate_ids = wp_cache_get( $cache_key, 'table-builder-block' );

		if ( false === $candidate_ids ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- no WP_Query equivalent for "find posts whose content contains this block ID"; result is cached just below.
			$candidate_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID
					 FROM   {$wpdb->posts}
					 WHERE  post_content LIKE %s
					 AND    post_status NOT IN ('trash', 'auto-draft')
					 LIMIT  10",
					'%' . $wpdb->esc_like( $block_id ) . '%'
				)
			);

			wp_cache_set( $cache_key, $candidate_ids, 'table-builder-block', 5 * MINUTE_IN_SECONDS );
		}

		foreach ( $candidate_ids as $id ) {
			$block = $this->get_block_from_post( (int) $id, $block_id );
			if ( $block ) {
				return $block;
			}
		}

		return array();
	}

	/**
	 * Recursively searches a block tree for a table block matching the given ID
	 * (by blockID or shortId attribute), or the first table block if no ID is given.
	 *
	 * @param array  $blocks   Block tree to search (parsed blocks, possibly with innerBlocks).
	 * @param string $block_id Block ID to match; empty string matches the first table block found.
	 * @return array The matched block, or an empty array if not found.
	 */
	private function find_table_block( array $blocks, string $block_id ): array {
		/**
		 * Filters the block names treated as "table blocks" by the [tableKit] shortcode,
		 * letting the Pro add-on (or third parties) register additional table block types.
		 *
		 * @since Unknown
		 *
		 * @param string[] $block_names Block names to match, e.g. "tablebuilder/table-builder".
		 */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-namespaced hook name is this plugin's established public API (used by the Pro add-on); renaming would be a breaking change.
		$block_names = apply_filters( 'tablekit/block_names', array( 'tablebuilder/table-builder' ) );

		foreach ( $blocks as $block ) {
			if ( in_array( $block['blockName'] ?? '', $block_names, true ) ) {
				$id       = (string) ( $block['attrs']['blockID'] ?? '' );
				$short_id = (string) ( $block['attrs']['shortId'] ?? '' );

				if ( ! $block_id || $id === $block_id || $short_id === $block_id ) {
					return $block;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->find_table_block( $block['innerBlocks'], $block_id );
				if ( $found ) {
					return $found;
				}
			}
		}

		return array();
	}

	// -------------------------------------------------------------------------
	// CSS Collection
	// -------------------------------------------------------------------------

	/**
	 * Collects combined CSS for every table block found within a block of post content.
	 *
	 * @param string $content Serialized block content (e.g. a post's post_content).
	 * @return string Combined CSS, or an empty string if there's no content/no table blocks.
	 */
	public function collect_blocks_css_from_content( string $content ): string {
		if ( '' === trim( $content ) ) {
			return '';
		}

		$blocks = parse_blocks( $content );
		if ( empty( $blocks ) ) {
			return '';
		}

		/** This filter is documented in includes/Shortcode/Shortcode.php */
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-namespaced hook name is this plugin's established public API (used by the Pro add-on); renaming would be a breaking change.
		$block_names = apply_filters( 'tablekit/block_names', array( 'tablebuilder/table-builder' ) );
		$css         = '';

		foreach ( $blocks as $block ) {
			$css .= $this->collect_css_from_block_tree( $block, $block_names );
		}

		return $css;
	}

	/**
	 * Recursively collects CSS from a block and its inner blocks, for any block
	 * whose name is in the given allow-list.
	 *
	 * @param array $block       Parsed block array.
	 * @param array $block_names Block names to collect CSS for (e.g. "tablebuilder/table-builder").
	 * @return string Combined CSS from this block and its descendants.
	 */
	private function collect_css_from_block_tree( array $block, array $block_names ): string {
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

	/**
	 * Enqueues the plugin's shared styles + the base table-builder block's assets,
	 * once per request.
	 *
	 * @return void
	 */
	private function block_enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		self::$assets_enqueued = true;
		ShortcodeUtils::enqueue_shared_styles();
		ShortcodeUtils::enqueue_block_assets( 'tablebuilder/table-builder' );
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Whether the shortcode's shared frontend assets have been enqueued yet this request.
	 *
	 * @return bool
	 */
	public static function is_assets_enqueued(): bool {
		return self::$assets_enqueued;
	}

	/**
	 * Sanitizes raw shortcode attributes.
	 *
	 * @param array $atts Raw attributes (already merged with defaults via shortcode_atts()).
	 * @return array Sanitized attributes.
	 */
	private function sanitize_atts( array $atts ): array {
		$atts['id']         = sanitize_text_field( (string) ( $atts['id'] ?? '' ) );
		$atts['post_id']    = absint( $atts['post_id'] ?? 0 );
		$atts['block_id']   = sanitize_text_field( (string) ( $atts['block_id'] ?? '' ) );
		$atts['class']      = sanitize_text_field( (string) ( $atts['class'] ?? '' ) );
		$atts['attrs_json'] = (string) ( $atts['attrs_json'] ?? '' );

		return $atts;
	}
}
