<?php

namespace TableBuilder\Config\CPT;

defined('ABSPATH') || exit;

use TableBuilder\Shortcode\ShortcodeUtils;
use WP_Post;
use WP_Query;

class InlineTableScanner
{
	private const BLOCK_NAMESPACE = 'tablebuilder/';
	private const CACHE_GROUP = 'tablekit_blocks';
	private const CACHE_KEY = 'inline_tables_v2';
	private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	public function get_inline_table_source_options(): array
	{
		$inline_tables = $this->get_all_inline_block_tables();

		if (empty($inline_tables)) {
			return array();
		}

		$options = array();
		$grouped = $this->group_inline_tables_by_source($inline_tables);

		foreach (array_values($grouped) as $group) {
			$block_count = count($group['blocks']);

			/* translators: %2$s = source post title. */
			$label = sprintf(
				_n('Table in: %2$s', 'Tables in: %2$s', $block_count, 'table-builder-block'),
				$block_count,
				$group['source_title']
			);

			$options[(string) $group['source_id']] = $label;
		}

		return $options;
	}

	public function get_all_inline_block_tables(): array
	{
		$cached = wp_cache_get(self::CACHE_KEY, self::CACHE_GROUP);

		if (false !== $cached) {
			return (array) $cached;
		}

		$results    = array();
		$post_types = $this->get_scannable_post_types();

		if (empty($post_types)) {
			$this->store_cache($results);
			return $results;
		}

		$query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => array('publish', 'draft', 'pending', 'private'),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				's'              => self::BLOCK_NAMESPACE,
				'search_columns' => array('post_content'),
			)
		);

		if (! $query->have_posts()) {
			$this->store_cache($results);
			return $results;
		}

		foreach ($query->posts as $post) {
			$found = $this->extract_table_blocks(parse_blocks($post->post_content));

			if (empty($found)) {
				continue;
			}

			$author_name = (string) get_the_author_meta('display_name', $post->post_author);

			foreach ($found as $block) {
				$attrs    = $block['attrs'] ?? array();
				$block_id = (string) ($attrs['blockID'] ?? '');

				$results[] = array(
					'source_id'     => (int) $post->ID,
					'source_title'  => (string) get_the_title($post),
					'source_type'   => (string) $post->post_type,
					'source_author' => $author_name,
					'source_date'   => (string) $post->post_date,
					'block_name'    => (string) $block['blockName'],
					'block_id'      => $block_id,
					'shortcode_id'  => $this->resolve_shortcode_id($attrs, $block_id),
					'attrs'         => $attrs,
				);
			}
		}

		$this->store_cache($results);

		return $results;
	}

	public function get_scannable_post_types(): array
	{
		static $cached = null;
		if (is_array($cached)) {
			return $cached;
		}

		$post_types = get_post_types(array('show_in_rest' => true), 'names');
		unset($post_types[TableCPT::POST_TYPE]);
		$cached = (array) apply_filters('tablekit_scannable_post_types', array_values($post_types));

		return $cached;
	}

	public function extract_table_blocks(array $blocks): array
	{
		return ShortcodeUtils::filter_table_blocks($blocks);
	}

	public function maybe_invalidate_cache(int $post_id, ?WP_Post $post = null): void
	{
		if (null === $post) {
			$post = get_post($post_id);
		}

		if (! ($post instanceof WP_Post)) {
			return;
		}

		if (in_array($post->post_type, $this->get_scannable_post_types(), true)) {
			$this->invalidate_cache();
		}
	}

	public function invalidate_cache(): void
	{
		wp_cache_delete(self::CACHE_KEY, self::CACHE_GROUP);
	}

	public function resolve_shortcode_id(array $attrs, string $block_id): string
	{
		$shortcode_id = (string) ($attrs['shortId'] ?? '');

		if ('' !== $shortcode_id) {
			return $shortcode_id;
		}

		if ('' === $block_id) {
			return '';
		}

		$hash = 0;

		foreach (str_split($block_id) as $char) {
			$hash = (($hash * 31) + ord($char)) & 0x7FFFFFFF;
		}

		return str_pad((string) ($hash % 1000000), 6, '0', STR_PAD_LEFT);
	}

	private function group_inline_tables_by_source(array $inline_tables): array
	{
		$grouped = array();

		foreach ($inline_tables as $table) {
			$sid = (int) $table['source_id'];

			if (! isset($grouped[$sid])) {
				$grouped[$sid] = array(
					'source_id'     => $sid,
					'source_title'  => $table['source_title'],
					'source_type'   => $table['source_type'],
					'source_author' => $table['source_author'],
					'source_date'   => $table['source_date'],
					'blocks'        => array(),
				);
			}

			$grouped[$sid]['blocks'][] = array(
				'block_name'   => $table['block_name'],
				'block_id'     => $table['block_id'],
				'shortcode_id' => $table['shortcode_id'],
			);
		}

		return $grouped;
	}

	private function store_cache(array $results): void
	{
		wp_cache_set(self::CACHE_KEY, $results, self::CACHE_GROUP, self::CACHE_TTL);
	}
}
