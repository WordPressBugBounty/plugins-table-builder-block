<?php
/**
 * Generates and enqueues per-post/per-block CSS and font assets
 *
 * @package TableKit
 */

namespace TableBuilder\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Collects block CSS/typography into inline styles and Google Fonts URLs,
 * and persists shortId attributes into saved block content.
 */
class AssetGenerator {


	use \TableBuilder\Traits\Singleton;

	/**
	 * Accumulated inline CSS for the current request.
	 *
	 * @var string
	 */
	public $css = '';

	/**
	 * Font-family => font-weight[] map collected from block typography attributes.
	 *
	 * @var array
	 */
	protected $fonts = array();

	/**
	 * Handle used for the theme-stylesheet backward-compat fallback (frontend only).
	 *
	 * @var string
	 */
	const THEME_STYLESHEET_FALLBACK_HANDLE = 'tablekit-theme-stylesheet-fallback';

	/**
	 * Max nesting depth when following synced patterns / registered patterns.
	 *
	 * @var int
	 */
	const MAX_PATTERN_DEPTH = 10;

	/**
	 * Keys of table blocks whose CSS has already been collected/output this request.
	 *
	 * @var array<string,bool>
	 */
	protected $emitted_blocks = array();

	/**
	 * CSS of table blocks rendered after the <head> CSS was finalised, keyed by
	 * block key, waiting to be printed right before that block's markup.
	 *
	 * @var array<string,string>
	 */
	protected $pending_late_css = array();

	/**
	 * Whether the collected CSS has already been handed to WordPress for <head>.
	 *
	 * @var bool
	 */
	protected $head_css_added = false;

	/**
	 * Nesting counter for renders that print their own CSS (e.g. [tableKit]
	 * shortcode, Elementor widget). While > 0, late CSS is not printed.
	 *
	 * @var int
	 */
	protected $late_css_suspended = 0;

	/**
	 * AssetGenerator class constructor.
	 * private for singleton
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'save_post', array( $this, 'save_post_hook' ), 10, 3 );
		add_filter( 'wp_insert_post_data', array( $this, 'persist_short_ids_in_content' ), 10, 2 );
		add_filter( 'render_block_data', array( $this, 'set_blocks_css' ), 10 );
		add_filter( 'wp_resource_hints', array( $this, 'fonts_resource_hints' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
		add_action( 'enqueue_block_assets', array( $this, 'block_assets' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_dequeue_theme_stylesheet_fallback' ), 999 );
		add_filter( 'render_block', array( $this, 'prepend_late_block_css' ), 1000, 2 );

		// Clear FSE cache when templates are saved.
		add_action( 'save_post_wp_template', array( $this, 'clear_fse_cache' ) );
		add_action( 'save_post_wp_template_part', array( $this, 'clear_fse_cache' ) );
	}

	/**
	 * Clears the cached FSE (Full Site Editing) template block scan, hooked to
	 * saving a wp_template/wp_template_part post.
	 *
	 * @return void
	 */
	public function clear_fse_cache() {
		delete_transient( 'table_builder_fse_blocks' );
	}

	/**
	 * Filters an array of blocks and returns only those where the block name contains 'tablebuilder'.
	 *
	 * @param array $blocks An array of blocks. Each block is an associative array that must contain a 'blockName' key. Default is an empty array.
	 * @return array Returns an array of blocks where the block name contains 'tablebuilder'.
	 */
	public function filter_blocks( $blocks = array() ) {
		$filtered_blocks = array();

		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && false !== strpos( $block['blockName'], 'tablebuilder' ) ) {
				$filtered_blocks[] = $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$filtered_blocks = array_merge( $filtered_blocks, $this->filter_blocks( $block['innerBlocks'] ) );
			}
		}

		return $filtered_blocks;
	}

	/**
	 * Minifies CSS by condensing white spaces and removing comments.
	 *
	 * @param string $css The input CSS.
	 * @return string Minified CSS.
	 */
	public function minimize_css( $css ) {
		if ( '' === trim( $css ) ) {
			return $css;
		}

		return preg_replace(
			array(
				'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')|\/\*(?!\!)(?>.*?\*\/)|^\s*|\s*$#s',
				'#("(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\'|\/\*(?>.*?\*\/))|\s*+;\s*+(})\s*+|\s*+([*$~^|]?+=|[{};,>~]|\s(?![0-9\.])|!important\b)\s*+|([[(:])\s++|\s++([])])|\s++(:)\s*+(?!(?>[^{}"\']++|"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\\]++|\\\.)*+\')*+{)|^\s++|\s++\z|(\s)\s+#si',
				'#(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)#si',
				'#:(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)#i',
				'#(background-position):0(?=[;\}])#si',
				'#(?<=[\s:,\-])0+\.(\d+)#s',
				'#(\/\*(?>.*?\*\/))|(?<!content\:)([\'"])([a-z_][a-z0-9\-_]*?)\2(?=[\s\{\}\];,])#si',
				'#(\/\*(?>.*?\*\/))|(\burl\()([\'"])([^\s]+?)\3(\))#si',
				'#(?<=[\{;])(border|outline):none(?=[;\}\!])#',
				'#(\/\*(?>.*?\*\/))|(^|[\{\}])(?:[^\s\{\}]+)\{\}#s',
			),
			array(
				'$1',
				'$1$2$3$4$5$6$7',
				'$1',
				':0',
				'$1:0 0',
				'.$1',
				'$1$3',
				'$1$2$4$5',
				'$1$2$3',
				'$1:0',
				'$1$2',
			),
			$css
		);
	}

	/**
	 * Fires once a post has been saved.
	 *
	 * @param int     $post_id The ID of the post.
	 * @param WP_Post $post The post object.
	 * @param bool    $update Whether this is an existing post being updated.
	 * @return void
	 */
	public function save_post_hook( $post_id, $post, $update ) {
		// Bail out if is draft, revision, or autosave.
		if ( 'auto-draft' === $post->post_status || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
			return;
		}

		// Skip if this is not an update (new post).
		if ( ! $update ) {
			return;
		}

		// Early bailout: Check if post content has tablebuilder blocks before parsing.
		if ( false === strpos( $post->post_content, 'tablebuilder' ) ) {
			return;
		}

		$post         = get_post( $post_id );
		$parse_blocks = $this->filter_blocks( parse_blocks( $post->post_content ) );

		if ( $parse_blocks ) {
			$fse = in_array( $post->post_type, array( 'wp_template_part', 'wp_template' ), true );
			if ( $fse ) {
				$this->set_fonts( null, $this->generate_fse_assets(), true );
			} else {
				$this->set_fonts( $post_id, $parse_blocks );
			}
		}
	}

	/**
	 * Persists shortId into saved block content before WordPress writes the post.
	 *
	 * @param array $data    Sanitized post data about to be inserted/updated.
	 * @param array $postarr Raw post data array as passed to wp_insert_post()/wp_update_post().
	 * @return array Modified post data.
	 */
	public function persist_short_ids_in_content( array $data, array $postarr ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the "wp_insert_post_data" filter signature; not needed in the body.
		if ( empty( $data['post_content'] ) || false === strpos( (string) $data['post_content'], 'tablebuilder/' ) ) {
			return $data;
		}

		$content = wp_unslash( (string) $data['post_content'] );
		$blocks  = parse_blocks( $content );

		if ( empty( $blocks ) ) {
			return $data;
		}

		$updated_blocks  = $this->ensure_short_ids_for_blocks( $blocks );
		$updated_content = serialize_blocks( $updated_blocks );

		if ( '' === $updated_content || $updated_content === $content ) {
			return $data;
		}

		$data['post_content'] = wp_slash( $updated_content );

		return $data;
	}

	/**
	 * Recursively walks blocks and populates missing shortId values for table blocks.
	 *
	 * @param array $blocks Parsed block tree to update in place.
	 * @return array Block tree with shortId attributes filled in where missing.
	 */
	protected function ensure_short_ids_for_blocks( array $blocks ): array {
		$target_blocks = array( 'tablebuilder/table-builder', 'tablebuilder/data-table', 'tablebuilder/post-table' );

		foreach ( $blocks as $index => $block ) {
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->ensure_short_ids_for_blocks( $block['innerBlocks'] );
			}

			if ( in_array( $block['blockName'] ?? '', $target_blocks, true ) ) {
				$attrs = $block['attrs'] ?? array();

				if ( empty( $attrs['shortId'] ) ) {
					$block_id = (string) ( $attrs['blockID'] ?? '' );

					if ( '' !== $block_id ) {
						$attrs['shortId'] = $this->generate_short_id( $block_id );
						$block['attrs']   = $attrs;
					}
				}
			}

			$blocks[ $index ] = $block;
		}

		return $blocks;
	}

	/**
	 * Generate the same 6-digit shortId value used by the editor and list table.
	 *
	 * @param string $block_id Block's own ID (blockID attribute) to derive the hash from.
	 * @return string 6-digit zero-padded numeric ID.
	 */
	protected function generate_short_id( string $block_id ): string {
		$hash = 0;

		foreach ( str_split( $block_id ) as $character ) {
			$hash = ( ( $hash * 31 ) + ord( $character ) ) & 0xffffffff;

			if ( $hash > 0x7fffffff ) {
				$hash -= 0x100000000;
			}
		}

		return str_pad( (string) ( abs( $hash ) % 1000000 ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Generate assets for templates.
	 * Now with caching to improve performance.
	 *
	 * @return array $filtered_blocks The filtered blocks for FSE templates.
	 */
	protected function generate_fse_assets() {
		// Check cache first (expires after 6 hours).
		$cached_blocks = get_transient( 'table_builder_fse_blocks' );
		if ( false !== $cached_blocks && is_array( $cached_blocks ) ) {
			return $cached_blocks;
		}

		$args = array(
			'post_type'              => array( 'wp_template_part', 'wp_template' ),
			'posts_per_page'         => 100, // Limit to 100 templates for performance.
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true, // Improve query performance.
			'update_post_meta_cache' => false, // Skip meta cache.
			'update_post_term_cache' => false, // Skip term cache.
		);

		$posts         = get_posts( $args );
		$merged_blocks = array();

		foreach ( $posts as $post ) {
			$merged_blocks = array_merge( $merged_blocks, parse_blocks( $post->post_content ) );
		}

		$filtered_blocks = $this->filter_blocks( $merged_blocks );

		// Cache for 6 hours.
		set_transient( 'table_builder_fse_blocks', $filtered_blocks, 6 * HOUR_IN_SECONDS );

		return $filtered_blocks;
	}

	/**
	 * Sets the fonts for a given post or Full Site Editing (FSE) template.
	 *
	 * @param int   $post_id The ID of the post or FSE template.
	 * @param array $blocks An array of blocks.
	 * @param bool  $fse    Whether this is an FSE template.
	 * @return void
	 */
	protected function set_fonts( $post_id, $blocks, $fse = false ) {
		$fonts = array();

		foreach ( $blocks as $block ) {
			if ( isset( $block['attrs'] ) ) {
				$typographies = array_filter(
					$block['attrs'],
					function ( $key ) {
						return str_contains( strtolower( $key ), 'typography' );
					},
					ARRAY_FILTER_USE_KEY
				);

				if ( ! empty( $typographies ) ) {
					foreach ( $typographies as $typography ) {
						$font_weight = ! empty( $typography['fontWeight']['value'] ) ? $typography['fontWeight']['value'] : 400;
						if ( ! empty( $typography['fontFamily']['value'] ) ) {
							$fonts[ $typography['fontFamily']['value'] ][] = $font_weight;
						}
					}
				}
			}
		}

		// Update fonts.
		if ( ! empty( $fonts ) ) {
			if ( $fse ) {
				update_option( 'table_builder_fse_fonts', $fonts );
			} else {
				update_post_meta( $post_id, 'table_builder_posts_fonts', $fonts );
			}
		} elseif ( $fse ) {
				delete_option( 'table_builder_fse_fonts' );
		} else {
			delete_post_meta( $post_id, 'table_builder_posts_fonts' );
		}
	}

	/**
	 * Combines block assets (CSS and JS) based on the used blocks.
	 *
	 * @param array $parsed_block The parsed block.
	 * @return string Combined CSS content.
	 */
	protected function combine_blocks_assets( $parsed_block = array() ) {
		$blocks_css = array();

		if ( isset( $parsed_block['blockName'] ) && false !== strpos( $parsed_block['blockName'], 'tablebuilder' ) ) {
			if ( isset( $parsed_block['attrs']['blocksCSS'] ) ) {
				foreach ( $parsed_block['attrs']['blocksCSS'] as $device => $css ) {
					if ( ! isset( $blocks_css[ $device ] ) ) {
						$blocks_css[ $device ] = '';
					}

					if ( is_string( $css ) ) {
						$blocks_css[ $device ] .= preg_replace( '/<[^>]*>?/', '', $css );
					}
				}
			}

			// block typography.
			$this->set_typography( $parsed_block );

			// block common style.
			if ( isset( $parsed_block['attrs']['commonStyle'] ) ) {
				foreach ( $parsed_block['attrs']['commonStyle'] as $device => $css ) {
					if ( ! isset( $blocks_css[ $device ] ) ) {
						$blocks_css[ $device ] = '';
					}

					if ( is_string( $css ) ) {
						$blocks_css[ $device ] .= preg_replace( '/<[^>]*>?/', '', $css );
					}
				}
			}
		}

		// Concatenate CSS content into a single string.
		$css_content            = '';
		$is_custom_styles_added = false;
		$device_list            = \TableBuilder\Helpers\Utils::get_device_list();

		if ( ! empty( $blocks_css ) ) {
			foreach ( $device_list as $device ) {
				foreach ( $blocks_css as $key => $block ) {
					if ( ! empty( $block ) && '' !== trim( $block ) ) {
						$direction  = $device['direction'] ?? 'max';
						$width      = $device['value'] ?? '';
						$device_key = strtolower( $device['slug'] ?? '' );

						if ( 'base' === $device['value'] && 'desktop' === $key ) {
							$css_content .= $block;
						} elseif ( ! empty( $direction ) && ! empty( $width ) && $device_key === $key ) {
							$css_content .= "@media ({$direction}-width: {$width}px) {" . trim( $block ) . '}';
						}

						if ( 'customStyles' === $key && ! $is_custom_styles_added ) {
							$is_custom_styles_added = true;
							$css_content           .= $block;
						}
					}
				}
			}
		}

		return $css_content;
	}

	/**
	 * Collects font-family/font-weight pairs from a block's typography-related
	 * attributes into $this->fonts, for later Google Fonts URL generation.
	 *
	 * @param array $parsed_block The parsed block data.
	 * @return void
	 */
	protected function set_typography( $parsed_block ) {
		if ( isset( $parsed_block['attrs'] ) ) {
			$typographies = array_filter(
				$parsed_block['attrs'],
				function ( $key ) {
					$key = strtolower( $key );
					return str_contains( $key, 'typography' ) || str_contains( $key, 'typo' );
				},
				ARRAY_FILTER_USE_KEY
			);

			if ( ! empty( $typographies ) ) {
				foreach ( $typographies as $typography ) {
					$font_weight = ! empty( $typography['fontWeight']['value'] ) ? $typography['fontWeight']['value'] : 400;
					if ( ! empty( $typography['fontFamily']['value'] ) ) {
						$this->fonts[ $typography['fontFamily']['value'] ][] = $font_weight;
					}
				}
			}
		}
	}

	/**
	 * Generate Google Fonts URL.
	 *
	 * @return string|bool Google Fonts URL or false if no fonts.
	 */
	protected function generate_fonts_url() {
		if ( ! empty( $this->fonts ) ) {
			$font_families = array();
			$font_url      = 'https://fonts.googleapis.com/css2?family=';

			// Remove duplicates and sort the fonts.
			$all_fonts = array_map(
				function ( $arr ) {
					$arr = array_unique( $arr );
					sort( $arr );
					return $arr;
				},
				$this->fonts
			);

			foreach ( $all_fonts as $font => $weights ) {
				$weights = array_map(
					function ( $weight ) {
						$invalid_list = array( 'normal', 'inherit', 'initial' );
						return in_array( $weight, $invalid_list, true ) ? '400' : $weight;
					},
					$weights
				);
				sort( $weights );
				$font_families[] = str_replace( ' ', '+', $font ) . ':wght@' . implode( ';', array_unique( $weights ) );
			}

			$font_url .= implode( '&family=', $font_families );
			$font_url .= '&display=swap';

			return $font_url;
		}

		return false;
	}

	/**
	 * Sets the CSS for the blocks.
	 *
	 * @param array $parsed_block The parsed block data.
	 * @return array The modified parsed block data.
	 */
	public function set_blocks_css( $parsed_block ) {
		if ( ! is_array( $parsed_block ) || ! $this->is_table_block( $parsed_block ) ) {
			return $parsed_block;
		}

		$key = $this->get_block_key( $parsed_block );

		if ( isset( $this->emitted_blocks[ $key ] ) ) {
			return $parsed_block;
		}

		// Before the <head> CSS is finalised (block themes render templates early,
		// classic themes are pre-collected in enqueue_scripts()): add to the inline CSS.
		if ( ! $this->head_css_added ) {
			$this->emitted_blocks[ $key ] = true;

			$css_content = $this->combine_blocks_assets( $parsed_block );
			if ( ! empty( $css_content ) ) {
				$this->css .= $css_content;
			}

			return $parsed_block;
		}

		// Rendered after <head> (widgets, archive loops, nested queries...): collect
		// this block's whole subtree once and print it before the block markup.
		$late_css = $this->collect_late_tree_css( $parsed_block );

		if ( '' !== $late_css && 0 === $this->late_css_suspended ) {
			$this->pending_late_css[ $key ] = $late_css;
		}

		return $parsed_block;
	}

	/**
	 * Collects CSS for a table block and all its inner table blocks that haven't
	 * been output yet, marking each one as output.
	 *
	 * @param array $parsed_block Parsed block.
	 * @return string Combined CSS.
	 */
	protected function collect_late_tree_css( array $parsed_block ): string {
		$css = '';

		if ( $this->is_table_block( $parsed_block ) ) {
			$key = $this->get_block_key( $parsed_block );

			if ( ! isset( $this->emitted_blocks[ $key ] ) ) {
				$this->emitted_blocks[ $key ] = true;
				$css                         .= $this->combine_blocks_assets( $parsed_block );
			}
		}

		foreach ( $parsed_block['innerBlocks'] ?? array() as $inner_block ) {
			if ( is_array( $inner_block ) ) {
				$css .= $this->collect_late_tree_css( $inner_block );
			}
		}

		return $css;
	}

	/**
	 * Prints CSS collected for a late-rendered table block right before its markup.
	 * Hooked to "render_block" at a late priority so other markup filters
	 * (which target the block's first tag) run first.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $parsed_block  Parsed block data.
	 * @return string
	 */
	public function prepend_late_block_css( $block_content, $parsed_block ) {
		if ( empty( $this->pending_late_css ) || ! is_array( $parsed_block ) || ! $this->is_table_block( $parsed_block ) ) {
			return $block_content;
		}

		$key = $this->get_block_key( $parsed_block );

		if ( ! isset( $this->pending_late_css[ $key ] ) ) {
			return $block_content;
		}

		$css = $this->pending_late_css[ $key ];
		unset( $this->pending_late_css[ $key ] );

		return '<style id="tablekit-css-' . esc_attr( sanitize_html_class( $key ) ) . '">' . $css . '</style>' . $block_content;
	}

	/**
	 * Suspends late CSS printing, for renderers that print their own CSS
	 * (e.g. the [tableKit] shortcode or the Elementor widget). Must be paired
	 * with resume_late_css().
	 *
	 * @return void
	 */
	public function suspend_late_css(): void {
		++$this->late_css_suspended;
	}

	/**
	 * Resumes late CSS printing after suspend_late_css().
	 *
	 * @return void
	 */
	public function resume_late_css(): void {
		$this->late_css_suspended = max( 0, $this->late_css_suspended - 1 );
	}

	/**
	 * Whether a parsed block is one of TableKit's blocks.
	 *
	 * @param array $parsed_block Parsed block.
	 * @return bool
	 */
	protected function is_table_block( array $parsed_block ): bool {
		return isset( $parsed_block['blockName'] ) && is_string( $parsed_block['blockName'] ) && false !== strpos( $parsed_block['blockName'], 'tablebuilder' );
	}

	/**
	 * Stable per-request key for a table block (its blockID, or a hash of its attributes).
	 *
	 * @param array $parsed_block Parsed block.
	 * @return string
	 */
	protected function get_block_key( array $parsed_block ): string {
		$block_id = $parsed_block['attrs']['blockID'] ?? '';

		if ( is_string( $block_id ) && '' !== $block_id ) {
			return $parsed_block['blockName'] . ':' . $block_id;
		}

		return $parsed_block['blockName'] . ':' . md5( (string) wp_json_encode( $parsed_block['attrs'] ?? array() ) );
	}

	/**
	 * Collects TableKit CSS (and enqueues block assets) for the current post on
	 * classic themes WITHOUT rendering any block.
	 *
	 * IMPORTANT: never render blocks here (no do_blocks, render_block or the_content). Rendering
	 * third-party blocks early triggers their one-time side effects (e.g. Formidable
	 * Forms prints and marks its stylesheet as loaded inside the discarded output,
	 * so forms lose their styling) and processes form submissions twice.
	 *
	 * @param \WP_Post $post Post to collect CSS for.
	 * @return void
	 */
	protected function collect_post_css( \WP_Post $post ): void {
		$content = (string) $post->post_content;

		if ( '' === $content
			|| ( false === strpos( $content, 'tablebuilder' )
				&& false === strpos( $content, '<!-- wp:block ' )
				&& false === strpos( $content, '<!-- wp:pattern ' ) )
		) {
			return;
		}

		$seen = array();
		$this->collect_css_from_blocks( parse_blocks( $content ), $seen, 0 );
	}

	/**
	 * Walks a parsed block tree (following synced patterns and registered
	 * patterns) and collects CSS for every TableKit block, in document order.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $seen   Pattern references already followed (loop guard).
	 * @param int   $depth  Current pattern nesting depth.
	 * @return void
	 */
	protected function collect_css_from_blocks( array $blocks, array &$seen, int $depth ): void {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = $block['blockName'] ?? '';

			if ( $this->is_table_block( $block ) ) {
				$this->set_blocks_css( $block );
				$this->enqueue_table_block_assets( $name );
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->collect_css_from_blocks( $block['innerBlocks'], $seen, $depth );
			}

			if ( $depth >= self::MAX_PATTERN_DEPTH ) {
				continue;
			}

			$pattern_content = $this->get_pattern_content( $block, $seen );

			if ( '' !== $pattern_content ) {
				$this->collect_css_from_blocks( parse_blocks( $pattern_content ), $seen, $depth + 1 );
			}
		}
	}

	/**
	 * Returns the content of a synced pattern (core/block) or registered pattern
	 * (core/pattern) block, once per request.
	 *
	 * @param array $block Parsed block.
	 * @param array $seen  Pattern references already followed (loop guard).
	 * @return string Pattern content, or an empty string.
	 */
	protected function get_pattern_content( array $block, array &$seen ): string {
		$name = $block['blockName'] ?? '';

		if ( 'core/block' === $name && ! empty( $block['attrs']['ref'] ) ) {
			$ref = 'ref:' . absint( $block['attrs']['ref'] );

			if ( isset( $seen[ $ref ] ) ) {
				return '';
			}
			$seen[ $ref ] = true;

			$pattern = get_post( absint( $block['attrs']['ref'] ) );

			if ( $pattern instanceof \WP_Post && 'wp_block' === $pattern->post_type && 'publish' === $pattern->post_status ) {
				return (string) $pattern->post_content;
			}

			return '';
		}

		if ( 'core/pattern' === $name && ! empty( $block['attrs']['slug'] ) && class_exists( '\\WP_Block_Patterns_Registry' ) ) {
			$slug = 'slug:' . (string) $block['attrs']['slug'];

			if ( isset( $seen[ $slug ] ) ) {
				return '';
			}
			$seen[ $slug ] = true;

			$registered = \WP_Block_Patterns_Registry::get_instance()->get_registered( (string) $block['attrs']['slug'] );

			return is_array( $registered ) && ! empty( $registered['content'] ) ? (string) $registered['content'] : '';
		}

		return '';
	}

	/**
	 * Enqueues a TableKit block's registered frontend style/view-script handles
	 * up front, so they still print in <head> exactly as they did when the block
	 * was rendered early (prevents a flash of unstyled table).
	 *
	 * @param string $block_name Block name, e.g. "tablebuilder/table-builder".
	 * @return void
	 */
	protected function enqueue_table_block_assets( string $block_name ): void {
		static $done = array();

		if ( isset( $done[ $block_name ] ) ) {
			return;
		}
		$done[ $block_name ] = true;

		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

		if ( ! $block_type ) {
			return;
		}

		foreach ( (array) ( $block_type->style_handles ?? array() ) as $handle ) {
			if ( is_string( $handle ) && wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
			}
		}

		foreach ( (array) ( $block_type->view_script_handles ?? array() ) as $handle ) {
			if ( is_string( $handle ) && wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
			}
		}
	}

	/**
	 * Adds a preconnect resource hint for Google Fonts.
	 *
	 * @param array  $urls URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed.
	 * @return array
	 */
	public function fonts_resource_hints( $urls, $relation_type ) {
		if ( wp_style_is( 'table-builder-google-fonts', 'queue' ) && 'preconnect' === $relation_type ) {
			$urls[] = array(
				'href' => 'https://fonts.gstatic.com',
				'crossorigin',
			);
		}

		return $urls;
	}

	/**
	 * Enqueues the Google Fonts stylesheet if available.
	 * Enqueues inline styles for the TableBuilder frontend.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		global $post;

		// Classic themes render the post after <head>, so collect its TableKit CSS
		// now by reading block attributes only, never by rendering (see collect_post_css()).
		if ( ! wp_is_block_theme() && $post instanceof \WP_Post && ! empty( $post->post_content ) ) {
			$this->collect_post_css( $post );
		}

		$fonts_url = $this->generate_fonts_url();
		if ( $fonts_url ) {
			wp_enqueue_style( 'table-builder-google-fonts', $fonts_url, false, TABLE_BUILDER_BLOCK_PLUGIN_VERSION );
		}

		if ( $this->css ) {
			wp_add_inline_style( 'table-builder-style', $this->css );
			$this->print_inline_css_after_block_styles();
		}

		$this->head_css_added = true;
	}

	/**
	 * Makes TableKit's per-block inline CSS print AFTER the TableKit block
	 * stylesheets (style-index.css) that are enqueued on this page.
	 *
	 * The per-block CSS generated from block settings uses selectors with the
	 * same specificity as the block's base stylesheet, so it only wins when it
	 * comes later in the page. Classic themes (e.g. Kadence) enqueue things in
	 * an order where the inline CSS was printed first, so user-chosen colours,
	 * paddings etc. were silently overridden by the defaults.
	 *
	 * @return void
	 */
	protected function print_inline_css_after_block_styles(): void {
		$styles = wp_styles();

		if ( empty( $styles->registered['table-builder-style'] ) ) {
			return;
		}

		$deps = array();

		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $block_name => $block_type ) {
			if ( false === strpos( (string) $block_name, 'tablebuilder' ) ) {
				continue;
			}

			foreach ( (array) ( $block_type->style_handles ?? array() ) as $handle ) {
				if ( is_string( $handle ) && 'table-builder-style' !== $handle && wp_style_is( $handle, 'enqueued' ) ) {
					$deps[] = $handle;
				}
			}
		}

		if ( empty( $deps ) ) {
			return;
		}

		$style       = $styles->registered['table-builder-style'];
		$style->deps = array_values( array_unique( array_merge( (array) $style->deps, $deps ) ) );
	}

	/**
	 * Enqueues block assets (CSS & JS).
	 *
	 * @return void
	 */
	public function block_assets() {
		// Block editor: unchanged behaviour.
		if ( is_admin() ) {
			wp_enqueue_style( 'table-builder-style', get_stylesheet_uri(), array(), TABLE_BUILDER_BLOCK_PLUGIN_VERSION );
			return;
		}

		/**
		 * Frontend backward-compat: some (child) themes never enqueue their own
		 * style.css and may have relied on TableKit loading it. It is enqueued here
		 * (same position as before) and dequeued again in
		 * maybe_dequeue_theme_stylesheet_fallback() when the theme loads it itself,
		 * so it is never printed twice.
		 *
		 * @since 2.2.15
		 *
		 * @param bool $load Whether to load the theme stylesheet as a fallback. Default true.
		 */
		if ( apply_filters( 'tablekit_load_theme_stylesheet_fallback', true ) ) {
			wp_enqueue_style( self::THEME_STYLESHEET_FALLBACK_HANDLE, get_stylesheet_uri(), array(), TABLE_BUILDER_BLOCK_PLUGIN_VERSION );
		}

		// Source-less handle that only carries TableKit's inline CSS.
		wp_register_style( 'table-builder-style', false, array(), TABLE_BUILDER_BLOCK_PLUGIN_VERSION );
		wp_enqueue_style( 'table-builder-style' );
	}

	/**
	 * Dequeues the theme-stylesheet fallback when the theme (or anything else)
	 * already enqueues the same stylesheet, directly or as a dependency.
	 *
	 * @return void
	 */
	public function maybe_dequeue_theme_stylesheet_fallback() {
		if ( is_admin() || ! wp_style_is( self::THEME_STYLESHEET_FALLBACK_HANDLE, 'enqueued' ) ) {
			return;
		}

		$target = $this->normalize_style_src( get_stylesheet_uri() );
		$styles = wp_styles();
		$stack  = (array) $styles->queue;
		$seen   = array();

		while ( ! empty( $stack ) ) {
			$handle = array_pop( $stack );

			if ( ! is_string( $handle ) || isset( $seen[ $handle ] ) || self::THEME_STYLESHEET_FALLBACK_HANDLE === $handle ) {
				continue;
			}
			$seen[ $handle ] = true;

			$style = $styles->registered[ $handle ] ?? null;

			if ( ! $style ) {
				continue;
			}

			if ( is_string( $style->src ) && '' !== $style->src && $this->normalize_style_src( $style->src ) === $target ) {
				wp_dequeue_style( self::THEME_STYLESHEET_FALLBACK_HANDLE );
				return;
			}

			foreach ( (array) $style->deps as $dep ) {
				$stack[] = $dep;
			}
		}
	}

	/**
	 * Normalises a stylesheet URL for comparison (drops scheme, host and query).
	 *
	 * @param string $src Stylesheet URL (absolute, protocol-relative or root-relative).
	 * @return string
	 */
	protected function normalize_style_src( string $src ): string {
		$src = strtok( $src, '?#' );
		$src = preg_replace( '#^(?:https?:)?//[^/]+#i', '', (string) $src );

		return strtolower( untrailingslashit( (string) $src ) );
	}
}
