<?php
/**
 * Admin notice for an outdated TableKit Pro
 *
 * @package TableKit
 */

namespace TableBuilder\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Tells admins to update TableKit Pro when it is older than the version that
 * works with this plugin. Shown on the Plugins, TableKit and editor screens
 * for as long as Pro is outdated; it goes away once Pro is updated.
 */
class ProUpdateNotice {

	use \TableBuilder\Traits\Singleton;

	/**
	 * First TableKit Pro version that works with this plugin version.
	 */
	const MIN_PRO_VERSION = '1.1.0';

	/**
	 * Hooks the notice.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Whether an older TableKit Pro is active.
	 *
	 * @return bool
	 */
	private function is_pro_outdated(): bool {
		return defined( 'TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION' )
			&& version_compare( TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION, self::MIN_PRO_VERSION, '<' );
	}

	/**
	 * Whether the current admin screen should show the notice.
	 *
	 * @return bool
	 */
	private function is_notice_screen(): bool {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		// Base "post" covers the edit screen of every post type, including TableKit's own.
		return $screen && ( in_array( $screen->id, array( 'plugins', 'toplevel_page_tablebuilder' ), true ) || 'post' === $screen->base );
	}

	/**
	 * Prints the notice.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'update_plugins' ) || ! $this->is_pro_outdated() || ! $this->is_notice_screen() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( 'TableKit Pro needs an update.', 'table-builder-block' ),
			esc_html(
				sprintf(
					/* translators: 1: installed TableKit Pro version, 2: TableKit (free) version, 3: minimum TableKit Pro version. */
					__( 'TableKit Pro %1$s is not compatible with TableKit %2$s. Please update TableKit Pro to version %3$s or newer, otherwise Pro features may not work correctly.', 'table-builder-block' ),
					TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION,
					TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
					self::MIN_PRO_VERSION
				)
			)
		);
	}
}
