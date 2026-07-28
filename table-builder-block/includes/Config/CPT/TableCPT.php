<?php
/**
 * The "tablekit_table" custom post type and its inline-table integration
 *
 * @package TableKit
 */

namespace TableBuilder\Config\CPT;

defined( 'ABSPATH' ) || exit;

use TableBuilder\Traits\Singleton;
use WP_Post;
use WP_Query;

/**
 * Registers the "tablekit_table" post type and wires together the scanner,
 * virtual-post builder, and admin-list helpers that support inline table blocks.
 */
class TableCPT {

	use Singleton;

	const POST_TYPE = 'tablekit_table';

	private const CPT_CONFIG = array(
		'singular'      => 'Table',
		'plural'        => 'Tables',
		'menu_name'     => 'Tables',
		'slug'          => 'tablekit-table',
		'menu_icon'     => 'dashicons-table-col-after',
		'menu_position' => 27,
		'text_domain'   => 'table-builder-block',
		'supports'      => array( 'title', 'editor', 'revisions', 'author' ),
	);

	private const TABLE_BLOCKS = array(
		'tablebuilder/table-builder',
		'tablebuilder/data-table',
		'tablebuilder/post-table',
	);

	private const BLOCK_LABELS = array(
		'tablebuilder/table-builder' => 'Table Builder block',
		'tablebuilder/data-table'    => 'Data Table block',
		'tablebuilder/post-table'    => 'Post Table block',
	);

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
	 * Admin list-table integration for this post type.
	 *
	 * @var TableCPTAdmin
	 */
	private TableCPTAdmin $admin;

	/**
	 * Gets the block names treated as "table blocks" throughout the plugin
	 * (used to find/inject table blocks inside arbitrary post content).
	 *
	 * @return string[] Block names, e.g. "tablebuilder/table-builder".
	 */
	public static function get_table_blocks(): array {
		return self::TABLE_BLOCKS;
	}

	/**
	 * Gets a human-readable label for one of the table block names.
	 *
	 * @param string $block_name Block name, e.g. "tablebuilder/table-builder".
	 * @return string The block's display label, or the block name if unknown.
	 */
	public static function get_block_label( string $block_name ): string {
		return self::BLOCK_LABELS[ $block_name ] ?? $block_name;
	}

	/**
	 * Instantiates collaborators and hooks post-type registration, inline-table
	 * injection, cache invalidation, and admin-list integration into WordPress.
	 */
	protected function __construct() {
		$this->scanner       = new InlineTableScanner();
		$this->virtual_posts = new VirtualPostBuilder( $this->scanner );
		$this->admin         = new TableCPTAdmin( $this->scanner, $this->virtual_posts );

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_filter( 'the_posts', array( $this, 'inject_inline_block_tables' ), 10, 2 );
		add_action( 'save_post', array( $this, 'maybe_invalidate_cache' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'maybe_invalidate_cache' ), 10, 2 );

		add_action( 'load-edit.php', array( $this->admin, 'prepare_bulk_post_ids' ) );
		add_action( 'admin_notices', array( $this->admin, 'print_recommendation_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_admin_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this->admin, 'add_list_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this->admin, 'render_list_column' ), 10, 2 );
		add_filter( 'wp_list_table_show_post_checkbox', array( $this->admin, 'show_list_table_checkbox' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this->admin, 'filter_row_actions' ), 10, 2 );
	}

	/**
	 * Registers the "tablekit_table" custom post type, hooked to "init".
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$config = self::CPT_CONFIG;
		$labels = $this->build_labels();

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'tablebuilder',
				'show_in_rest'        => true,
				'menu_position'       => $config['menu_position'],
				'menu_icon'           => $config['menu_icon'],
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => $config['supports'],
				'has_archive'         => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'rewrite'             => array( 'slug' => $config['slug'] ),
				'show_in_admin_bar'   => false,
			)
		);
	}

	/**
	 * Injects virtual "tablekit_table" posts for inline-only table blocks into
	 * a query's results, hooked to "the_posts".
	 *
	 * @param array    $posts Posts returned by the query.
	 * @param WP_Query $query The query that was executed.
	 * @return array Posts, with virtual inline-table posts merged in where applicable.
	 */
	public function inject_inline_block_tables( array $posts, WP_Query $query ): array {
		return $this->virtual_posts->inject_inline_block_tables( $posts, $query );
	}

	/**
	 * Gets the list of posts that may contain inline table blocks, for use as
	 * a source-selection dropdown/options list.
	 *
	 * @return array Options describing candidate posts.
	 */
	public function get_inline_table_source_options(): array {
		return $this->scanner->get_inline_table_source_options();
	}

	/**
	 * Invalidates the inline-table scan cache when a post is saved/deleted, if
	 * that post could plausibly contain a table block. Hooked to "save_post"
	 * and "delete_post".
	 *
	 * @param int          $post_id Post ID being saved/deleted.
	 * @param WP_Post|null $post    The post object, if available.
	 * @return void
	 */
	public function maybe_invalidate_cache( int $post_id, ?WP_Post $post = null ): void {
		$this->scanner->maybe_invalidate_cache( $post_id, $post );
	}

	/**
	 * Unconditionally clear the inline-table scan cache.
	 *
	 * @return void
	 */
	public function invalidate_cache(): void {
		$this->scanner->invalidate_cache();
	}

	/**
	 * Builds the post type labels array.
	 *
	 * Strings are hardcoded (rather than built from self::CPT_CONFIG) because
	 * __()/sprintf(__()) require literal string and text-domain arguments to
	 * be picked up by WordPress.org's translation string extraction tooling.
	 * The values below must stay in sync with self::CPT_CONFIG['singular'],
	 * ['plural'], and ['menu_name'].
	 *
	 * @return array Post type labels, keyed per register_post_type()'s "labels" arg.
	 */
	private function build_labels(): array {
		/* translators: %s: Singular label for the "Table" post type. */
		$add_new_item = sprintf( __( 'Add New %s', 'table-builder-block' ), __( 'Table', 'table-builder-block' ) );
		/* translators: %s: Singular label for the "Table" post type. */
		$new_item = sprintf( __( 'New %s', 'table-builder-block' ), __( 'Table', 'table-builder-block' ) );
		/* translators: %s: Singular label for the "Table" post type. */
		$edit_item = sprintf( __( 'Edit %s', 'table-builder-block' ), __( 'Table', 'table-builder-block' ) );
		/* translators: %s: Singular label for the "Table" post type. */
		$view_item = sprintf( __( 'View %s', 'table-builder-block' ), __( 'Table', 'table-builder-block' ) );
		/* translators: %s: Plural label for the "Tables" post type. */
		$all_items = sprintf( __( 'All %s', 'table-builder-block' ), __( 'Tables', 'table-builder-block' ) );
		/* translators: %s: Plural label for the "Tables" post type. */
		$search_items = sprintf( __( 'Search %s', 'table-builder-block' ), __( 'Tables', 'table-builder-block' ) );
		/* translators: %s: Lowercase plural label for the "Tables" post type. */
		$filter_items_list = sprintf( __( 'Filter %s list', 'table-builder-block' ), strtolower( __( 'Tables', 'table-builder-block' ) ) );
		/* translators: %s: Plural label for the "Tables" post type. */
		$items_list_navigation = sprintf( __( '%s list navigation', 'table-builder-block' ), __( 'Tables', 'table-builder-block' ) );
		/* translators: %s: Plural label for the "Tables" post type. */
		$items_list = sprintf( __( '%s list', 'table-builder-block' ), __( 'Tables', 'table-builder-block' ) );

		return array(
			'name'                  => __( 'Tables', 'table-builder-block' ),
			'singular_name'         => __( 'Table', 'table-builder-block' ),
			'menu_name'             => __( 'Tables', 'table-builder-block' ),
			'name_admin_bar'        => __( 'Table', 'table-builder-block' ),
			'add_new'               => __( 'Add New', 'table-builder-block' ),
			'add_new_item'          => $add_new_item,
			'new_item'              => $new_item,
			'edit_item'             => $edit_item,
			'view_item'             => $view_item,
			'all_items'             => $all_items,
			'search_items'          => $search_items,
			'not_found'             => __( 'No tables found.', 'table-builder-block' ),
			'not_found_in_trash'    => __( 'No tables found in Trash.', 'table-builder-block' ),
			'filter_items_list'     => $filter_items_list,
			'items_list_navigation' => $items_list_navigation,
			'items_list'            => $items_list,
		);
	}
}
