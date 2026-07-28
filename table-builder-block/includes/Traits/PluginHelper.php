<?php
/**
 * Shared plugin-status helper methods
 *
 * @package TableKit
 */

namespace TableBuilder\Traits;

/**
 * Trait for making singleton instance
 * This is a factory singleton
 *
 * @package TableBuilder\Traits
 */
trait PluginHelper {
	/**
	 * Checks whether a plugin is active, site-wide or network-wide.
	 *
	 * @param string $plugin Plugin basename, e.g. "table-builder-block-pro/table-builder-block-pro.php".
	 * @return bool True if the plugin is active on this site or network-wide.
	 */
	public static function is_plugin_active( $plugin ) {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) || self::is_plugin_active_for_network( $plugin );
	}

	/**
	 * Checks whether a plugin is network-activated on a multisite install.
	 *
	 * @param string $plugin Plugin basename, e.g. "table-builder-block-pro/table-builder-block-pro.php".
	 * @return bool True if the plugin is network-active; always false on non-multisite.
	 */
	public static function is_plugin_active_for_network( $plugin ) {
		if ( ! is_multisite() ) {
			return false;
		}

		$plugins = get_site_option( 'active_sitewide_plugins' );
		if ( isset( $plugins[ $plugin ] ) ) {
			return true;
		}

		return false;
	}
}
