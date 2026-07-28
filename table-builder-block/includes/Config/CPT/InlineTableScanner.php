<?php
/**
 * Scans post content for inline table blocks
 *
 * @package TableKit
 */

namespace TableBuilder\Config\CPT;

defined( 'ABSPATH' ) || exit;

use TableBuilder\Shortcode\ShortcodeUtils;
use WP_Post;
use WP_Query;

/**
 * Finds and caches table blocks embedded inline within other posts' content.
 */
class InlineTableScanner {

	private const BLOCK_NAMESPACE = 'tablebuilder/';
	private const CACHE_GROUP     = 'tablekit_blocks';
	private const CACHE_KEY       = 'inline_tables_v2';
	private const CACHE_TTL       = 5 * MINUTE_IN_SECONDS;

	/**
	 * Builds a SELECT-style options list of source posts containing inline table
	 * blocks, keyed by source post ID, labeled with block count.
	 *
	 * @return array<string,string> Options keyed by source post ID (as a string).
	 */
	public function get_inline_table_source_options(): array {
		$inline_tables = $this->get_all_inline_block_tables();

		if ( empty( $inline_tables ) ) {
			return array();
		}

		$options = array();
		$grouped = $this->group_inline_tables_by_source( $inline_tables );

		foreach ( array_values( $grouped ) as $group ) {
			$block_count = count( $group['blocks'] );

			$label = sprintf(
				/* translators: %2$s = source post title. */
				_n( 'Table in: %2$s', 'Tables in: %2$s', $block_count, 'table-builder-block' ),
				$block_count,
				$group['source_title']
			);

			$options[ (string) $group['source_id'] ] = $label;
		}

		return $options;
	}

	/**
	 * Scans every scannable post type for table blocks embedded inline (i.e. not
	 * inside a dedicated tablekit_table post), cached for 5 minutes.
	 *
	 * @return array Flat list of inline table block records (source_id/source_title/
	 *               source_type/source_author/source_date/block_name/block_id/shortcode_id/attrs).
	 */
	public function get_all_inline_block_tables(): array {
		$cached = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );

		if ( false !== $cached ) {
			return (array) $cached;
		}

		$results    = array();
		$post_types = $this->get_scannable_post_types();

		if ( empty( $post_types ) ) {
			$this->store_cache( $results );
			return $results;
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				's'              => self::BLOCK_NAMESPACE,
				'search_columns' => array( 'post_content' ),
			)
		);

		if ( ! $query->have_posts() ) {
			$this->store_cache( $results );
			return $results;
		}

		foreach ( $query->posts as $post ) {
			$found = $this->extract_table_blocks( parse_blocks( $post->post_content ) );

			if ( empty( $found ) ) {
				continue;
			}

			$author_name = (string) get_the_author_meta( 'display_name', $post->post_author );

			foreach ( $found as $block ) {
				$attrs    = $block['attrs'] ?? array();
				$block_id = (string) ( $attrs['blockID'] ?? '' );

				$results[] = array(
					'source_id'     => (int) $post->ID,
					'source_title'  => (string) get_the_title( $post ),
					'source_type'   => (string) $post->post_type,
					'source_author' => $author_name,
					'source_date'   => (string) $post->post_date,
					'block_name'    => (string) $block['blockName'],
					'block_id'      => $block_id,
					'shortcode_id'  => $this->resolve_shortcode_id( $attrs, $block_id ),
					'attrs'         => $attrs,
				);
			}
		}

		$this->store_cache( $results );

		return $results;
	}

	/**
	 * Gets the list of post types to scan for inline table blocks: every
	 * REST-exposed post type except the Tables CPT itself, filterable via
	 * "tablekit_scannable_post_types".
	 *
	 * @return string[] Post type names.
	 */
	public function get_scannable_post_types(): array {
		static $cached = null;
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );
		unset( $post_types[ TableCPT::POST_TYPE ] );

		/**
		 * Filters the post types scanned for inline table blocks.
		 *
		 * @since Unknown
		 *
		 * @param string[] $post_types REST-exposed post type names, excluding the Tables CPT itself.
		 */
		$cached = (array) apply_filters( 'tablekit_scannable_post_types', array_values( $post_types ) );

		return $cached;
	}

	/**
	 * Narrow a parsed block tree down to just the plugin's table blocks.
	 *
	 * @param array $blocks Parsed block tree.
	 * @return array Table blocks only.
	 */
	public function extract_table_blocks( array $blocks ): array {
		return ShortcodeUtils::filter_table_blocks( $blocks );
	}

	/**
	 * Invalidates the inline-table scan cache if the saved/deleted post is one
	 * of the scannable post types. Hooked to "save_post"/"delete_post".
	 *
	 * @param int          $post_id Post ID being saved/deleted.
	 * @param WP_Post|null $post    Optional. The post object, to avoid a redundant get_post() lookup.
	 * @return void
	 */
	public function maybe_invalidate_cache( int $post_id, ?WP_Post $post = null ): void {
		if ( null === $post ) {
			$post = get_post( $post_id );
		}

		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		if ( in_array( $post->post_type, $this->get_scannable_post_types(), true ) ) {
			$this->invalidate_cache();
		}
	}

	/**
	 * Clears the inline-table scan cache.
	 *
	 * @return void
	 */
	public function invalidate_cache(): void {
		wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
	}

	/**
	 * Resolves a block's short shortcode ID: its stored "shortId" attribute if
	 * present, otherwise a deterministic 6-digit hash derived from its blockID.
	 *
	 * @param array  $attrs    Block attributes.
	 * @param string $block_id Block's own ID (blockID attribute), used as the hash source.
	 * @return string The resolved shortcode ID, or an empty string if $block_id is also empty.
	 */
	public function resolve_shortcode_id( array $attrs, string $block_id ): string {
		$shortcode_id = (string) ( $attrs['shortId'] ?? '' );

		if ( '' !== $shortcode_id ) {
			return $shortcode_id;
		}

		if ( '' === $block_id ) {
			return '';
		}

		$hash = 0;

		foreach ( str_split( $block_id ) as $char ) {
			$hash = ( ( $hash * 31 ) + ord( $char ) ) & 0x7FFFFFFF;
		}

		return str_pad( (string) ( $hash % 1000000 ), 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Group flat inline-table records by their source post ID.
	 *
	 * @param array $inline_tables Flat list of inline table records from get_all_inline_block_tables().
	 * @return array<int,array> Records grouped by source_id, each with a "blocks" sub-array.
	 */
	private function group_inline_tables_by_source( array $inline_tables ): array {
		$grouped = array();

		foreach ( $inline_tables as $table ) {
			$sid = (int) $table['source_id'];

			if ( ! isset( $grouped[ $sid ] ) ) {
				$grouped[ $sid ] = array(
					'source_id'     => $sid,
					'source_title'  => $table['source_title'],
					'source_type'   => $table['source_type'],
					'source_author' => $table['source_author'],
					'source_date'   => $table['source_date'],
					'blocks'        => array(),
				);
			}

			$grouped[ $sid ]['blocks'][] = array(
				'block_name'   => $table['block_name'],
				'block_id'     => $table['block_id'],
				'shortcode_id' => $table['shortcode_id'],
			);
		}

		return $grouped;
	}

	/**
	 * Stores a scan result set in the object cache.
	 *
	 * @param array $results Result set from get_all_inline_block_tables().
	 * @return void
	 */
	private function store_cache( array $results ): void {
		wp_cache_set( self::CACHE_KEY, $results, self::CACHE_GROUP, self::CACHE_TTL );
	}
}
