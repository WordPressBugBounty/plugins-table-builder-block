<?php
/**
 * Admin list-table integration for the "tablekit_table" post type
 *
 * @package TableKit
 */

namespace TableBuilder\Config\CPT;

defined( 'ABSPATH' ) || exit;

use WP_Post;

/**
 * Adds source/shortcode columns, row actions, and notices to the Tables admin list table.
 */
class TableCPTAdmin {

	/**
	 * Scans post content for inline table blocks.
	 *
	 * @var InlineTableScanner
	 */
	private InlineTableScanner $scanner;

	/**
	 * Builds virtual "tablekit_table" posts for inline-only tables.
	 *
	 * @var VirtualPostBuilder
	 */
	private VirtualPostBuilder $virtual_posts;

	/**
	 * Stores the collaborators used to resolve and render inline-table admin data.
	 *
	 * @param InlineTableScanner $scanner       Scanner used to locate inline table blocks.
	 * @param VirtualPostBuilder $virtual_posts Builder used to render shortcode inputs/panels for admin list columns.
	 */
	public function __construct( InlineTableScanner $scanner, VirtualPostBuilder $virtual_posts ) {
		$this->scanner       = $scanner;
		$this->virtual_posts = $virtual_posts;
	}

	/**
	 * Prints an admin notice on the Tables list screen recommending a single
	 * table per page for easier management/performance.
	 *
	 * @return void
	 */
	public function print_recommendation_notice(): void {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || TableCPT::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$message = __( 'TableKit supports multiple tables in the same page. However, to keep your content easier to manage and improve compatibility, performance, and shortcode handling, we recommend creating a single table whenever possible.', 'table-builder-block' );

		echo '<div class="notice notice-info is-dismissible" >';
		echo '<p>' . esc_html( $message ) . '</p>';
		echo '</div>';
	}

	/**
	 * Remap any virtual (negative, synthetic) post IDs in a bulk action's
	 * selected posts back to their real source post IDs, before WP core's own
	 * bulk-action handling runs on edit.php. Hooked to "load-edit.php".
	 *
	 * @return void
	 */
	public function prepare_bulk_post_ids(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- this only
		// remaps virtual->real post IDs ahead of WP core's own bulk-action handling on
		// edit.php (load-edit.php runs before edit.php verifies the bulk-action nonce);
		// it never performs a state change itself.
		$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) ) : '';

		if ( '' === $post_type || TableCPT::POST_TYPE !== $post_type ) {
			return;
		}

		if ( empty( $_REQUEST['post'] ) ) {
			return;
		}

		$virtual_source_map = $this->virtual_posts->get_virtual_source_id_map();

		if ( empty( $virtual_source_map ) ) {
			return;
		}

		$post_ids = array_map( 'intval', (array) wp_unslash( $_REQUEST['post'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$normalized_ids = array();

		foreach ( $post_ids as $post_id ) {
			if ( $post_id > 0 ) {
				$normalized_ids[] = $post_id;
				continue;
			}

			if ( isset( $virtual_source_map[ $post_id ] ) ) {
				$normalized_ids[] = (int) $virtual_source_map[ $post_id ];
			}
		}

		$normalized_ids = array_values( array_unique( array_filter( $normalized_ids ) ) );

		$_REQUEST['post'] = $normalized_ids;
		$_POST['post']    = $normalized_ids;
	}

	/**
	 * Determines whether a bulk-action checkbox should show for a list-table row,
	 * gating virtual rows on the viewer's capability over the real source post.
	 *
	 * @param bool    $show Default visibility as determined by WP core.
	 * @param WP_Post $post The row's post (real or virtual).
	 * @return bool
	 */
	public function show_list_table_checkbox( bool $show, WP_Post $post ): bool {
		if ( empty( $post->tablekit_is_virtual ) ) {
			return $show;
		}

		return current_user_can( 'edit_post', (int) $post->tablekit_source_id );
	}

	/**
	 * Adds TableKit-specific columns (source/author, and shortcode columns when
	 * Pro is active) to the Tables admin list table, dropping the default "author" column.
	 *
	 * @param array $columns Existing list table columns.
	 * @return array Modified columns.
	 */
	public function add_list_columns( array $columns ): array {
		$updated = array();

		foreach ( $columns as $key => $label ) {
			if ( 'author' === $key ) {
				continue;
			}

			$updated[ $key ] = $label;

			if ( 'title' === $key ) {
				if ( $this->is_pro_active() ) {
					$updated['tablekit_shortcode']       = __( 'Shortcode', 'table-builder-block' );
					$updated['tablekit_child_shortcode'] = __( 'Child Shortcodes', 'table-builder-block' );
				}

				$updated['tablekit_source'] = __( 'Source', 'table-builder-block' );
				$updated['tablekit_author'] = __( 'Author', 'table-builder-block' );
			}
		}

		return $updated;
	}

	/**
	 * Renders the contents of a custom Tables admin list-table column.
	 *
	 * @param string $column  Column key being rendered.
	 * @param int    $post_id The row's post ID (unused directly; the global $post is used instead).
	 * @return void
	 */
	public function render_list_column( string $column, int $post_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the "manage_{$post_type}_posts_custom_column" action signature; the global $post is used instead.
		global $post;

		if ( ! ( $post instanceof WP_Post ) ) {
			return;
		}

		switch ( $column ) {
			case 'tablekit_shortcode':
				if ( ! $this->is_pro_active() ) {
					break;
				}

				$this->render_column_shortcode( $post );
				break;
			case 'tablekit_child_shortcode':
				if ( ! $this->is_pro_active() ) {
					break;
				}

				$this->render_column_child_shortcodes( $post );
				break;
			case 'tablekit_source':
				$this->render_column_source( $post );
				break;
			case 'tablekit_author':
				$this->render_column_author( $post );
				break;
		}
	}

	/**
	 * Replace a virtual row's default row actions (Edit/Trash/Delete, etc.) with
	 * actions pointed at its real source post instead of the non-existent virtual post.
	 *
	 * @param array   $actions Default row actions.
	 * @param WP_Post $post    The row's post (real or virtual).
	 * @return array Row actions to display.
	 */
	public function filter_row_actions( array $actions, WP_Post $post ): array {
		if ( empty( $post->tablekit_is_virtual ) ) {
			return $actions;
		}

		$source_id      = (int) $post->tablekit_source_id;
		$source_post    = get_post( $source_id );
		$source_actions = array();
		$title          = (string) $post->tablekit_source_title;

		if ( $source_post instanceof WP_Post && current_user_can( 'edit_post', $source_id ) && 'trash' !== $source_post->post_status ) {
			$edit_link = get_edit_post_link( $source_id );

			if ( false !== $edit_link && '' !== $edit_link ) {
				$source_actions['edit'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					esc_url( (string) $edit_link ),
					/* translators: %s: Title of the source post the table belongs to. */
					esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;', 'table-builder-block' ), $title ) ),
					esc_html__( 'Edit', 'table-builder-block' )
				);
			}

			if ( 'publish' === $source_post->post_status ) {
				$view_link = get_permalink( $source_id );

				if ( false !== $view_link && '' !== $view_link ) {
					$source_actions['view'] = sprintf(
						'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
						esc_url( (string) $view_link ),
						/* translators: %s: Title of the source post the table belongs to. */
						esc_attr( sprintf( __( 'View &#8220;%s&#8221;', 'table-builder-block' ), $title ) ),
						esc_html__( 'View', 'table-builder-block' )
					);
				}
			}
		}

		if ( current_user_can( 'delete_post', $source_id ) ) {
			if ( $source_post instanceof WP_Post && 'trash' === $source_post->post_status ) {
				$source_actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $source_id ),
					/* translators: %s: Title of the source post the table belongs to. */
					esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently', 'table-builder-block' ), $title ) ),
					esc_html__( 'Delete Permanently', 'table-builder-block' )
				);
			} elseif ( EMPTY_TRASH_DAYS ) {
				$source_actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $source_id ),
					/* translators: %s: Title of the source post the table belongs to. */
					esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash', 'table-builder-block' ), $title ) ),
					esc_html_x( 'Trash', 'verb', 'table-builder-block' )
				);
			}

			if ( $source_post instanceof WP_Post && ( 'trash' === $source_post->post_status || ! EMPTY_TRASH_DAYS ) ) {
				$source_actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $source_id ),
					/* translators: %s: Title of the source post the table belongs to. */
					esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently', 'table-builder-block' ), $title ) ),
					esc_html__( 'Delete Permanently', 'table-builder-block' )
				);
			}
		}

		return empty( $source_actions ) ? $actions : $source_actions;
	}

	/**
	 * Enqueues the Tables admin list-table's CSS/JS on the "edit.php" screen
	 * for the Tables post type only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || TableCPT::POST_TYPE !== $screen->post_type ) {
			return;
		}

		$style_path  = TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'assets/css/table-cpt-admin.css';
		$script_path = TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'assets/js/table-cpt-admin.js';

		wp_enqueue_style(
			'tablekit-cpt-admin',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'assets/css/table-cpt-admin.css',
			array(),
			file_exists( $style_path ) ? (string) filemtime( $style_path ) : TABLE_BUILDER_BLOCK_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'tablekit-cpt-admin',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'assets/js/table-cpt-admin.js',
			array(),
			file_exists( $script_path ) ? (string) filemtime( $script_path ) : TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'tablekit-cpt-admin',
			'tablekitCptAdmin',
			array(
				'copiedText' => __( 'Copied!', 'table-builder-block' ),
				'failedText' => __( 'Copy failed', 'table-builder-block' ),
			)
		);
	}

	/**
	 * Renders the "Shortcode" column: a copyable `[tableKit post_id="..."]` shortcode.
	 *
	 * @param WP_Post $post The row's post (real or virtual).
	 * @return void
	 */
	private function render_column_shortcode( WP_Post $post ): void {
		if ( ! empty( $post->tablekit_is_virtual ) ) {
			$this->virtual_posts->render_shortcode_input(
				sprintf( '[tableKit post_id="%d"]', (int) $post->tablekit_source_id )
			);
			return;
		}

		$this->virtual_posts->render_shortcode_input( sprintf( '[tableKit post_id="%d"]', (int) $post->ID ) );
	}

	/**
	 * Renders the "Child Shortcodes" column: a collapsible panel listing shortcodes
	 * for each child table block found in the row's post.
	 *
	 * @param WP_Post $post The row's post (real or virtual).
	 * @return void
	 */
	private function render_column_child_shortcodes( WP_Post $post ): void {
		$child_blocks = $this->get_child_shortcode_blocks( $post );

		if ( empty( $child_blocks ) ) {
			echo '&mdash;';
			return;
		}

		$source_id = ! empty( $post->tablekit_is_virtual )
			? (int) $post->tablekit_source_id
			: (int) $post->ID;

		$this->virtual_posts->render_child_shortcode_panel( $child_blocks, (int) $post->ID, $source_id );
	}

	/**
	 * Renders the "Source" column, dispatching to the virtual- or real-post variant.
	 *
	 * @param WP_Post $post The row's post (real or virtual).
	 * @return void
	 */
	private function render_column_source( WP_Post $post ): void {
		if ( ! empty( $post->tablekit_is_virtual ) ) {
			$this->render_virtual_source_cell( $post );
			return;
		}

		$this->render_real_source_cell( $post );
	}

	/**
	 * Renders the "Source" column contents for a virtual row: a link to the
	 * source post plus a breakdown of block types/counts found in it.
	 *
	 * @param WP_Post $post The virtual row's post.
	 * @return void
	 */
	private function render_virtual_source_cell( WP_Post $post ): void {
		$source_type_obj = get_post_type_object( $post->tablekit_source_type );
		$type_label      = $source_type_obj
			? esc_html( $source_type_obj->labels->singular_name )
			: esc_html( $post->tablekit_source_type );

		$block_counts = $this->count_blocks_by_name( (array) $post->tablekit_blocks );

		echo '<div class="tablekit-source-cell">';
		printf(
			'<strong>%s:</strong> <a href="%s" target="_blank">%s</a><br>',
			$type_label, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already run through esc_html() above.
			esc_url( (string) get_edit_post_link( $post->tablekit_source_id ) ),
			esc_html( (string) $post->tablekit_source_title )
		);
		$this->render_block_type_list( $block_counts );
		echo '</div>';
	}

	/**
	 * Renders the "Source" column contents for a real tablekit_table post: an
	 * "All Tables" label plus a breakdown of the table blocks it contains.
	 *
	 * @param WP_Post $post The real row's post.
	 * @return void
	 */
	private function render_real_source_cell( WP_Post $post ): void {
		echo '<div class="tablekit-source-cell">';
		echo '<strong>' . esc_html__( 'All Tables', 'table-builder-block' ) . '</strong><br>';

		$blocks = $this->get_child_shortcode_blocks( $post );

		if ( empty( $blocks ) ) {
			echo '<span style="color:#999;font-size:11px;">'
				. esc_html__( 'No table blocks found', 'table-builder-block' )
				. '</span>';
		} else {
			$this->render_block_type_list( $this->count_blocks_by_name( $blocks ) );
		}

		echo '</div>';
	}

	/**
	 * Renders the "Author" column: source post's author for virtual rows,
	 * or the post's own author otherwise.
	 *
	 * @param WP_Post $post The row's post (real or virtual).
	 * @return void
	 */
	private function render_column_author( WP_Post $post ): void {
		if ( ! empty( $post->tablekit_is_virtual ) ) {
			$author = $post->tablekit_source_author;
			echo esc_html( $author ? $author : '&mdash;' );
			return;
		}

		$author_name = get_the_author_meta( 'display_name', $post->post_author );
		echo esc_html( $author_name ? $author_name : '&mdash;' );
	}

	/**
	 * Gets the list of child table-block descriptors for a row's post (from its
	 * cached virtual data, or freshly scanned for a real post).
	 *
	 * @param WP_Post $post The row's post (real or virtual).
	 * @return array Block descriptors with block_name/block_id/shortcode_id keys.
	 */
	private function get_child_shortcode_blocks( WP_Post $post ): array {
		if ( ! empty( $post->tablekit_is_virtual ) && ! empty( $post->tablekit_blocks ) ) {
			return (array) $post->tablekit_blocks;
		}

		$matched = $this->scanner->extract_table_blocks( parse_blocks( (string) $post->post_content ) );
		$blocks  = array();

		foreach ( $matched as $block ) {
			$attrs    = $block['attrs'] ?? array();
			$block_id = (string) ( $attrs['blockID'] ?? '' );

			$blocks[] = array(
				'block_name'   => (string) ( $block['blockName'] ?? '' ),
				'block_id'     => $block_id,
				'shortcode_id' => $this->scanner->resolve_shortcode_id( $attrs, $block_id ),
			);
		}

		return $blocks;
	}

	/**
	 * Renders a list of block-type labels with their counts (e.g. "-> Data Table block ×2").
	 *
	 * @param array $block_counts Block counts keyed by block name, from count_blocks_by_name().
	 * @return void
	 */
	private function render_block_type_list( array $block_counts ): void {
		foreach ( $block_counts as $block_name => $count ) {
			echo '<span class="tablekit-source-block">-&gt; ' . esc_html( TableCPT::get_block_label( $block_name ) );

			if ( $count > 1 ) {
				printf( ' <strong class="tablekit-source-block-count">&times;%d</strong>', $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- %d forces integer output.
			}

			echo '</span>';
		}
	}

	/**
	 * Tallies block descriptors by block name.
	 *
	 * @param array $blocks Block descriptors with a "block_name" key.
	 * @return array<string,int> Counts keyed by block name.
	 */
	private function count_blocks_by_name( array $blocks ): array {
		$counts = array();

		foreach ( $blocks as $block ) {
			$name = (string) ( $block['block_name'] ?? '' );

			if ( '' !== $name ) {
				$counts[ $name ] = ( $counts[ $name ] ?? 0 ) + 1;
			}
		}

		return $counts;
	}

	/**
	 * Whether the Pro add-on plugin is active.
	 *
	 * @return bool
	 */
	private function is_pro_active(): bool {
		return defined( 'TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION' );
	}
}
