<?php
/**
 * One-off migration of legacy block markup to TableKit's current namespace
 *
 * @package TableKit
 */

namespace TableBuilder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Handles one-off migration of legacy block markup after a plugin update.
 *
 * @since 1.0.0
 * @access public
 */
class DataMigration {

	/**
	 * Hooks the editor-recovery script and the post-upgrade block migration into WordPress.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
		add_action( 'upgrader_process_complete', array( $this, 'migrate_old_blocks' ), 10, 2 );
	}

	/**
	 * Enqueues necessary scripts and localizes data for the admin area.
	 *
	 * @param string $hook The current page.
	 * @return void
	 * @since 1.0.0
	 */
	public function editor_assets( $hook ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the "enqueue_block_editor_assets" action signature; not needed in the body.
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- "active_plugins" is a WordPress core filter, not defined by this plugin.

		if ( ! in_array( 'auto-block-recovery/auto-block-recovery.php', $active_plugins, true ) ) {
			wp_enqueue_script(
				'auto-block-recovery',
				TABLE_BUILDER_BLOCK_PLUGIN_URL . 'assets/js/auto-block-recovery.js',
				array( 'wp-blocks', 'wp-data', 'wp-dom-ready', 'wp-i18n' ),
				TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
				true
			);
		}
	}

	/**
	 * Migrates old blocks to new format.
	 *
	 * @param object $upgrader_object The upgrader object.
	 * @param array  $options        The options passed to the upgrader.
	 * @return void
	 */
	public function migrate_old_blocks( $upgrader_object, $options ) {
		$our_plugin = 'table-builder-block/table-builder-block.php';
		if ( ! empty( $options['plugins'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			foreach ( $options['plugins'] as $plugin ) {
				if ( $our_plugin === $plugin ) {
					if ( ! get_transient( 'table_builder_block_migrate' ) ) {
						$this->table_builder_block_migrate_old_blocks();
						set_transient( 'table_builder_block_migrate', TABLE_BUILDER_BLOCK_PLUGIN_VERSION );
					}
				}
			}
		}
	}

	/**
	 * Rewrites legacy "gutenkit/table-builder*" block markup to the current
	 * "tablebuilder/table-builder*" namespace across all matching posts.
	 *
	 * @return void
	 */
	public function table_builder_block_migrate_old_blocks() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off migration run at most once per plugin upgrade (see migrate_old_blocks() above), not a per-request query; caching would add complexity for no benefit here.
		$posts = $wpdb->get_results(
			"
			SELECT ID, post_content
			FROM {$wpdb->posts}
			WHERE post_type IN ('post', 'page', 'your_custom_post_type')
			AND post_status IN ('publish', 'draft', 'pending', 'private')
			AND post_content LIKE '%gutenkit/%'
		"
		);

		foreach ( $posts as $post ) {
			$updated_content = str_replace(
				array(
					'wp:gutenkit/table-builder',
					'wp:gutenkit/table-builder-row',
					'wp:gutenkit/table-builder-item',
					'gutenkit/table-builder',
					'gutenkit/table-builder-row',
					'gutenkit/table-builder-item',
				),
				array(
					'wp:tablebuilder/table-builder',
					'wp:tablebuilder/table-builder-row',
					'wp:tablebuilder/table-builder-item',
					'tablebuilder/table-builder',
					'tablebuilder/table-builder-row',
					'tablebuilder/table-builder-item',
				),
				$post->post_content
			);

			if ( $updated_content !== $post->post_content ) {
				wp_update_post(
					array(
						'ID'           => $post->ID,
						'post_content' => $updated_content,
					)
				);
			}
		}
	}
}
