<?php
/**
 * Builds synthetic "virtual" tablekit_table posts for inline-only table blocks
 *
 * @package TableKit
 */

namespace TableBuilder\Config\CPT;

defined( 'ABSPATH' ) || exit;

use WP_Post;
use WP_Query;

/**
 * Creates and renders synthetic WP_Post rows for table blocks that exist
 * inline in other posts' content, so they can appear in the Tables admin list.
 */
class VirtualPostBuilder {

	private const VIRTUAL_ID_BASE = -1000000;

	/**
	 * Scans post content for inline table blocks.
	 *
	 * @var InlineTableScanner
	 */
	private InlineTableScanner $scanner;

	/**
	 * Stores the scanner used to locate inline table blocks.
	 *
	 * @param InlineTableScanner $scanner Scanner used to locate inline table blocks.
	 */
	public function __construct( InlineTableScanner $scanner ) {
		$this->scanner = $scanner;
		add_filter( 'post_row_actions', array( $this, 'strip_incompatible_row_actions' ), 1, 2 );
	}

	/**
	 * @param array   $actions Row action links, keyed by action name.
	 * @param WP_Post $post    The row's post (real or virtual).
	 * @return array Unmodified row actions (only third-party hook state is changed).
	 */
	public function strip_incompatible_row_actions( array $actions, WP_Post $post ): array {
		if ( empty( $post->tablekit_is_virtual ) ) {
			return $actions;
		}

		global $wp_filter;
		if ( ! isset( $wp_filter['post_row_actions'] ) || ! ( $wp_filter['post_row_actions'] instanceof \WP_Hook ) ) {
			return $actions;
		}

		foreach ( $wp_filter['post_row_actions']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$fn = $callback['function'];
				if ( is_string( $fn ) && false !== strpos( $fn, '_collaboration_row_actions' ) ) {
					remove_filter( 'post_row_actions', $fn, $priority );
				}
			}
		}

		return $actions;
	}

	/**
	 * Append synthetic "virtual" WP_Post rows to a query's results — one per
	 * source post containing inline table blocks — so they show up in the
	 * Tables admin list table alongside real tablekit_table posts.
	 *
	 * @param WP_Post[] $posts Posts already matched by the query.
	 * @param WP_Query  $query The query being filtered.
	 * @return WP_Post[] Posts with virtual entries appended, when applicable.
	 */
	public function inject_inline_block_tables( array $posts, WP_Query $query ): array {
		if ( ! $this->is_cpt_admin_list_query( $query ) ) {
			return $posts;
		}

		$inline_tables = $this->scanner->get_all_inline_block_tables();

		if ( empty( $inline_tables ) ) {
			return $posts;
		}

		$grouped = $this->group_inline_tables_by_source( $inline_tables );
		foreach ( array_values( $grouped ) as $index => $group ) {
			$posts[] = $this->build_virtual_post( $group, $index );
		}

		return $posts;
	}

	/**
	 * Map each virtual (negative) post ID back to the real source post ID it
	 * represents, so bulk actions on virtual rows can be redirected to the
	 * underlying real post.
	 *
	 * @return array<int,int> Virtual post ID => real source post ID.
	 */
	public function get_virtual_source_id_map(): array {
		$map     = array();
		$grouped = $this->group_inline_tables_by_source( $this->scanner->get_all_inline_block_tables() );

		foreach ( array_values( $grouped ) as $index => $group ) {
			$map[ self::VIRTUAL_ID_BASE - $index ] = (int) $group['source_id'];
		}

		return $map;
	}

	/**
	 * Checks whether a query is the main admin list-table query for the Tables CPT.
	 *
	 * @param WP_Query $query The query to check.
	 * @return bool True if this is the Tables admin list query.
	 */
	private function is_cpt_admin_list_query( WP_Query $query ): bool {
		return is_admin()
			&& $query->is_main_query()
			&& TableCPT::POST_TYPE === $query->get( 'post_type' );
	}

	/**
	 * Group flat inline-table records by their source post ID.
	 *
	 * @param array $inline_tables Flat list of inline table records from InlineTableScanner.
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
	 * Builds a synthetic (never-saved) WP_Post representing one source post's
	 * group of inline table blocks, for display in the Tables admin list table.
	 *
	 * @param array $group A single group from group_inline_tables_by_source().
	 * @param int   $index Zero-based index used to derive a stable negative virtual ID.
	 * @return WP_Post The virtual post, with tablekit_* properties attached.
	 */
	private function build_virtual_post( array $group, int $index ): WP_Post {
		$block_count = count( $group['blocks'] );

		$title = sprintf(
			/* translators: %2$s = source post title. */
			_n( 'Table in: %2$s', 'Tables in: %2$s', $block_count, 'table-builder-block' ),
			$block_count,
			$group['source_title']
		);

		$virtual_post = new WP_Post(
			(object) array(
				'ID'                => self::VIRTUAL_ID_BASE - $index,
				'post_title'        => $title,
				'post_type'         => TableCPT::POST_TYPE,
				'post_status'       => 'publish',
				'post_author'       => 0,
				'post_date'         => $group['source_date'],
				'post_date_gmt'     => get_gmt_from_date( $group['source_date'] ),
				'post_content'      => '',
				'post_excerpt'      => '',
				'post_name'         => '',
				'post_modified'     => $group['source_date'],
				'post_modified_gmt' => get_gmt_from_date( $group['source_date'] ),
				'comment_status'    => 'closed',
				'ping_status'       => 'closed',
				'guid'              => '',
				'menu_order'        => 0,
				'post_mime_type'    => '',
				'comment_count'     => 0,
				'filter'            => 'raw',
			)
		);

		$virtual_post->tablekit_is_virtual    = true;
		$virtual_post->tablekit_source_id     = $group['source_id'];
		$virtual_post->tablekit_source_title  = $group['source_title'];
		$virtual_post->tablekit_source_type   = $group['source_type'];
		$virtual_post->tablekit_source_author = $group['source_author'];
		$virtual_post->tablekit_blocks        = $group['blocks'];

		return $virtual_post;
	}
}
