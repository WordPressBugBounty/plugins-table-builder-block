<?php

namespace TableBuilder\Config\CPT;

defined('ABSPATH') || exit;

use TableBuilder\Traits\Singleton;
use WP_Post;
use WP_Query;

class TableCPT
{
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
		'supports'      => array('title', 'editor', 'revisions', 'author'),
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

	private InlineTableScanner $scanner;
	private VirtualPostBuilder $virtual_posts;
	private TableCPTAdmin $admin;

	public static function get_table_blocks(): array
	{
		return self::TABLE_BLOCKS;
	}

	public static function get_block_label(string $block_name): string
	{
		return self::BLOCK_LABELS[$block_name] ?? $block_name;
	}

	protected function __construct()
	{
		$this->scanner       = new InlineTableScanner();
		$this->virtual_posts = new VirtualPostBuilder($this->scanner);
		$this->admin         = new TableCPTAdmin($this->scanner, $this->virtual_posts);

		add_action('init', array($this, 'register_post_type'));
		add_filter('the_posts', array($this, 'inject_inline_block_tables'), 10, 2);
		add_action('save_post', array($this, 'maybe_invalidate_cache'), 10, 2);
		add_action('delete_post', array($this, 'maybe_invalidate_cache'), 10, 2);

		add_action('load-edit.php', array($this->admin, 'prepare_bulk_post_ids'));
		add_action('admin_notices', array($this->admin, 'print_recommendation_notice'));
		add_action('admin_enqueue_scripts', array($this->admin, 'enqueue_admin_assets'));
		add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this->admin, 'add_list_columns'));
		add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this->admin, 'render_list_column'), 10, 2);
		add_filter('wp_list_table_show_post_checkbox', array($this->admin, 'show_list_table_checkbox'), 10, 2);
		add_filter('post_row_actions', array($this->admin, 'filter_row_actions'), 10, 2);
	}

	public function register_post_type(): void
	{
		$config = self::CPT_CONFIG;
		$labels = $this->build_labels(
			$config['singular'],
			$config['plural'],
			$config['menu_name'],
			$config['text_domain']
		);

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
				'rewrite'             => array('slug' => $config['slug']),
				'show_in_admin_bar'   => false,
			)
		);
	}

	public function inject_inline_block_tables(array $posts, WP_Query $query): array
	{
		return $this->virtual_posts->inject_inline_block_tables($posts, $query);
	}

	public function get_inline_table_source_options(): array
	{
		return $this->scanner->get_inline_table_source_options();
	}

	public function maybe_invalidate_cache(int $post_id, ?WP_Post $post = null): void
	{
		$this->scanner->maybe_invalidate_cache($post_id, $post);
	}

	public function invalidate_cache(): void
	{
		$this->scanner->invalidate_cache();
	}

	private function build_labels(string $singular, string $plural, string $menu_name, string $domain): array
	{
		return array(
			'name'                  => __($plural, $domain),
			'singular_name'         => __($singular, $domain),
			'menu_name'             => __($menu_name, $domain),
			'name_admin_bar'        => __($singular, $domain),
			'add_new'               => __('Add New', $domain),
			'add_new_item'          => sprintf(__('Add New %s', $domain), $singular),
			'new_item'              => sprintf(__('New %s', $domain), $singular),
			'edit_item'             => sprintf(__('Edit %s', $domain), $singular),
			'view_item'             => sprintf(__('View %s', $domain), $singular),
			'all_items'             => sprintf(__('All %s', $domain), $plural),
			'search_items'          => sprintf(__('Search %s', $domain), $plural),
			'not_found'             => __('No tables found.', $domain),
			'not_found_in_trash'    => __('No tables found in Trash.', $domain),
			'filter_items_list'     => sprintf(__('Filter %s list', $domain), strtolower($plural)),
			'items_list_navigation' => sprintf(__('%s list navigation', $domain), $plural),
			'items_list'            => sprintf(__('%s list', $domain), $plural),
		);
	}
}
