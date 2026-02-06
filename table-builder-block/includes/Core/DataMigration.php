<?php

namespace TableBuilder\Core;

defined('ABSPATH') || exit;

/**
 * Enqueue registrar.
 *
 * @since 1.0.0
 * @access public
 */
class DataMigration
{
	/**
	 * Class constructor.
	 * private for singleton
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __construct()
	{
		add_action('enqueue_block_editor_assets', array($this, 'editor_assets'));
		add_action('upgrader_process_complete', array($this, 'migrate_old_blocks'), 10, 2);
	}

	/**
	 * Enqueues necessary scripts and localizes data for the admin area.
	 *
	 * @param string $hook The current page.
	 * @return void
	 * @since 1.0.0
	 */
	public function editor_assets($hook)
	{
		if (!in_array('auto-block-recovery/auto-block-recovery.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			wp_enqueue_script(
				'auto-block-recovery',
				TABLE_BUILDER_BLOCK_PLUGIN_URL . 'assets/js/auto-block-recovery.js',
				array('wp-blocks', 'wp-data', 'wp-dom-ready', 'wp-i18n'),
				TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
				true
			);
		}
	}

	/**
	 * Migrate old blocks to new format.
	 *
	 * @param object $upgrader_object The upgrader object.
	 * @param array  $options        The options passed to the upgrader.
	 * @return void
	 */
	public function migrate_old_blocks($upgrader_object, $options)
	{
		$our_plugin = 'table-builder-block/table-builder-block.php';
		if (!empty($options['plugins']) && $options['action'] == 'update' && $options['type'] == 'plugin') {
			foreach ($options['plugins'] as $plugin) {
				if ($plugin == $our_plugin) {
					if (!get_transient('table_builder_block_migrate')) {
						$this->table_builder_block_migrate_old_blocks();
						set_transient('table_builder_block_migrate', TABLE_BUILDER_BLOCK_PLUGIN_VERSION);
					}
				}
			}
		}
	}

	/**
	 * Migrate old blocks to new format.
	 *
	 * @return void
	 */
	public function table_builder_block_migrate_old_blocks()
	{
		global $wpdb;

		$posts = $wpdb->get_results("
			SELECT ID, post_content
			FROM {$wpdb->posts}
			WHERE post_type IN ('post', 'page', 'your_custom_post_type')
			AND post_status IN ('publish', 'draft', 'pending', 'private')
			AND post_content LIKE '%gutenkit/%'
		");

		foreach ($posts as $post) {
			$updated_content = str_replace(
				[
					'wp:gutenkit/table-builder',
					'wp:gutenkit/table-builder-row',
					'wp:gutenkit/table-builder-item',
					'gutenkit/table-builder',
					'gutenkit/table-builder-row',
					'gutenkit/table-builder-item',
				],
				[
					'wp:tablebuilder/table-builder',
					'wp:tablebuilder/table-builder-row',
					'wp:tablebuilder/table-builder-item',
					'tablebuilder/table-builder',
					'tablebuilder/table-builder-row',
					'tablebuilder/table-builder-item',
				],
				$post->post_content
			);

			if ($updated_content !== $post->post_content) {
				wp_update_post([
					'ID' => $post->ID,
					'post_content' => $updated_content,
				]);
			}
		}
	}
}
