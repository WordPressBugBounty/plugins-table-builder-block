<?php
/**
 * Registry of the blocks TableKit provides
 *
 * @package TableKit
 */

namespace TableBuilder\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Provides the filterable list of block definitions TableKit registers.
 */
class BlockList {


	/**
	 * Gets the list of blocks TableKit registers, filterable so the Pro
	 * add-on (and third parties) can extend it with additional block definitions.
	 *
	 * @return array<string, array{slug:string,title:string,package:string,category:string,status:string,parent?:string,badge?:string[]}>
	 *               Block definitions keyed by block key.
	 */
	public static function get_block_list() {
		/**
		 * Filters the list of block definitions TableKit registers.
		 *
		 * @since Unknown
		 *
		 * @param array $list Block definitions keyed by block key; see get_block_list() for the shape.
		 */
		$list = apply_filters(
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-namespaced hook name is this plugin's established public API (used by the Pro add-on); renaming would be a breaking change.
			'tablebuilder/blocks/list',
			array(
				'table-builder'      => array(
					'slug'     => 'table-builder',
					'title'    => 'Table Builder',
					'package'  => 'free',
					'category' => 'general',
					'status'   => 'active',
					'badge'    => array( 'freemium', 'new', 'beta' ),
				),
				'table-builder-row'  => array(
					'slug'     => 'table-builder-row',
					'title'    => 'Table Row',
					'package'  => 'free',
					'category' => 'general',
					'parent'   => 'table-builder',
					'status'   => 'active',
				),
				'table-builder-item' => array(
					'slug'     => 'table-builder-item',
					'title'    => 'Table Column',
					'package'  => 'free',
					'category' => 'general',
					'parent'   => 'table-builder-row',
					'status'   => 'active',
				),
				'data-table'         => array(
					'slug'     => 'data-table',
					'title'    => 'Data Table',
					'package'  => 'pro',
					'category' => 'general',
					'status'   => 'active',
				),
				'post-table'         => array(
					'slug'     => 'post-table',
					'title'    => 'Post Table',
					'package'  => 'pro',
					'category' => 'general',
					'status'   => 'active',
				),
			)
		);

		return $list;
	}
}
