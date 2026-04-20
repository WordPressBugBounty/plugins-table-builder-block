<?php

namespace TableBuilder\Config;

defined('ABSPATH') || exit;

class BlockList
{

	public static function get_block_list()
	{
		$list = apply_filters(
			'tablebuilder/blocks/list',
			array(
				'table-builder' => array(
					'slug'     => 'table-builder',
					'title'    => 'Table Builder',
					'package'  => 'free',
					'category' => 'general',
					'status'   => 'active',
					'badge'    => ['freemium', 'new', 'beta'],
				),
				'table-builder-row' => array(
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
				'data-table' => array(
					'slug'     => 'data-table',
					'title'    => 'Data Table',
					'package'  => 'pro',
					'category' => 'general',
					'status'   => 'active',
				),
				'post-table' => array(
					'slug'     => 'post-table',
					'title'    => 'Post Table',
					'package'  => 'pro',
					'category' => 'general',
					'status'   => 'active',
				)
			)
		);

		return $list;
	}
}
