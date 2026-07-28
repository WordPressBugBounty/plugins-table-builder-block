<?php
/**
 * Frontend and admin asset enqueue registrar
 *
 * @package TableKit
 */

namespace TableBuilder\Core;

defined( 'ABSPATH' ) || exit;

use TableBuilder\Helpers\Utils;

/**
 * Enqueue registrar.
 *
 * @since 1.0.0
 * @access public
 */
class Enqueue {

	use \TableBuilder\Traits\Singleton;

	/**
	 * Class constructor.
	 * private for singleton
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_deactivation_popup_scripts' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'blocks_editor_scripts' ) );
		add_action( 'wp_head', array( $this, 'print_device_script_for_window' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Gets the WP filesystem instance, loading the API if needed.
	 *
	 * @return void
	 */
	protected function get_filesystem() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
	}

	/**
	 * Enqueues necessary scripts and localizes data for the admin area.
	 *
	 * @param string $hook The current page.
	 * @return void
	 * @since 1.0.0
	 */
	public function admin_scripts( $hook ) {
		// Only load heavy data on editor screens to improve performance.
		$is_editor_screen = in_array( $hook, array( 'post.php', 'post-new.php', 'site-editor.php', 'widgets.php' ), true );

		wp_localize_script(
			'wp-block-editor',
			'tableBuilder',
			array(
				'plugin_url'         => TABLE_BUILDER_BLOCK_PLUGIN_URL,
				'screen'             => $hook,
				'api_url'            => TABLE_BUILDER_BLOCK_PLUGIN_URL . 'api/',
				'root_url'           => esc_url( home_url( '/' ) ),
				'rest_url'           => esc_url_raw( rest_url() ),
				'rest_nonce'         => wp_create_nonce( 'wp_rest' ),
				'version'            => TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
				'generalSettingsUrl' => admin_url( 'options-general.php' ),
				'activeTheme'        => wp_get_theme()->get( 'Name' ),
				'assetUrl'           => TABLE_BUILDER_BLOCK_PLUGIN_URL . 'assets/images/',
				'has_pro'            => defined( 'TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION' ),
			)
		);

		// Localize tablekitEssential only on editor screens and if tablekit-essential plugin is not active.
		if ( $is_editor_screen && ! $this->is_tablekit_essential_active() ) {
			wp_localize_script( 'wp-block-editor', 'tablekitEssential', RestApi::get_localized_data() );
		}

		if ( 'toplevel_page_tablebuilder' === $hook ) {
			$assets = include TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/admin/dashboard/index.asset.php';
			if ( isset( $assets['version'] ) ) {

				// Enqueue the JavaScript.
				wp_enqueue_script(
					'tablebuilder-admin-dashboard',
					TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/admin/dashboard/index.js',
					$assets['dependencies'],
					$assets['version'],
					true
				);

				// Enqueue the stylesheet.
				wp_enqueue_style(
					'tablebuilder-admin-dashboard',
					TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/admin/dashboard/index.css',
					array(),
					$assets['version']
				);

				wp_set_script_translations( 'tablebuilder-admin-dashboard', 'table-builder-block', TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'languages' );

				wp_localize_script(
					'tablebuilder-admin-dashboard',
					'tableBuilder',
					array(
						'plugin_url'   => TABLE_BUILDER_BLOCK_PLUGIN_URL,
						'screen'       => $hook,
						'adminUrl'     => esc_url( admin_url( '/' ) ),
						'pluginStatus' => $this->get_onboard_plugin_status(),
						'has_pro'      => defined( 'TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION' ),
						'version'      => TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
						'pro_version'  => defined( 'TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION' ) ? TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION : '1.0.0',
					)
				);
			}
		}
	}

	/**
	 * Enqueues the deactivation feedback popup on the plugins.php screen.
	 *
	 * @param string $hook The current admin page.
	 * @return void
	 */
	public function enqueue_deactivation_popup_scripts( $hook ) {
		if ( 'plugins.php' !== $hook ) {
			return;
		}

		$asset_file = TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/admin/deactivation-popup/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$assets = include $asset_file;

		wp_enqueue_script(
			'tablebuilder-deactivation-popup',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/admin/deactivation-popup/index.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		wp_set_script_translations(
			'tablebuilder-deactivation-popup',
			'table-builder-block',
			TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'languages'
		);

		wp_localize_script(
			'tablebuilder-deactivation-popup',
			'tableBuilderDeactivation',
			array(
				'pluginUrl' => TABLE_BUILDER_BLOCK_PLUGIN_URL,
			)
		);

		wp_enqueue_style(
			'tablebuilder-deactivation-popup',
			TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/admin/deactivation-popup/index.css',
			array(),
			$assets['version']
		);
	}

	/**
	 * Checks if tablekit-essential is active.
	 *
	 * @return bool
	 */
	private function is_tablekit_essential_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'tablekit-essential/tablekit-essential.php' );
	}

	/**
	 * Builds a plugin status map for PopupKit-style onboarding steps.
	 *
	 * @return array<string,string> Status ('notInstalled'/'inactive'/'active') keyed by plugin slug.
	 */
	private function get_onboard_plugin_status() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = array(
			'getgenie'              => 'getgenie/getgenie.php',
			'gutenkit-blocks-addon' => 'gutenkit-blocks-addon/gutenkit-blocks-addon.php',
			'elementskit-lite'      => 'elementskit-lite/elementskit-lite.php',
			'metform'               => 'metform/metform.php',
			'shopengine'            => 'shopengine/shopengine.php',
			'blocks-for-shopengine' => 'blocks-for-shopengine/shopengine-gutenberg-addon.php',
			'popup-builder-block'   => 'popup-builder-block/popup-builder-block.php',
			'wp-social'             => 'wp-social/wp-social.php',
			'wp-ultimate-review'    => 'wp-ultimate-review/wp-ultimate-review.php',
		);

		$status = array();

		foreach ( $plugins as $slug => $plugin_file ) {
			$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

			if ( ! file_exists( $plugin_path ) ) {
				$status[ $slug ] = 'notInstalled';
				continue;
			}

			$status[ $slug ] = is_plugin_active( $plugin_file ) ? 'active' : 'inactive';
		}

		return $status;
	}

	/**
	 * Enqueues block editor assets: shared component/helper scripts (with
	 * translations attached if already registered) and the global editor script.
	 *
	 * @return void
	 */
	public function blocks_editor_scripts() {
		$scripts = array(
			'components' => 'gkit-components',
			'helper'     => 'gkit-helpers',
			'global'     => 'tablekit-blocks-editor-global',
		);

		$shared_handles = array( 'gkit-components', 'gkit-helpers' );

		foreach ( $scripts as $name => $handle ) {
			if ( in_array( $handle, $shared_handles, true ) && wp_script_is( $handle, 'registered' ) ) {
				wp_set_script_translations( $handle, 'table-builder-block', TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'languages' );
				continue;
			}

			$asset_path = TABLE_BUILDER_BLOCK_PLUGIN_DIR . "build/tablebuilder/{$name}.asset.php";
			$this->enqueue_assets( $asset_path, $handle, "{$name}.js" );
		}
	}


	/**
	 * Enqueues a build script from its `.asset.php` dependency manifest, and
	 * attach script translations.
	 *
	 * @param string $asset_file  Absolute path to the `*.asset.php` manifest.
	 * @param string $handle      Script handle to register.
	 * @param string $script_file Script filename relative to `build/tablebuilder/`.
	 * @return void
	 */
	private function enqueue_assets( $asset_file, $handle, $script_file ) {
		$this->get_filesystem();
		global $wp_filesystem;

		if ( ! isset( $wp_filesystem ) || ! $wp_filesystem->exists( $asset_file ) ) {
			return;
		}

		// Check if asset file exists.
		if ( $wp_filesystem->exists( $asset_file ) ) {
			$asset_data = include $asset_file;

			// Ensure asset data is valid.
			if ( ! empty( $asset_data['dependencies'] ) && is_array( $asset_data['dependencies'] ) && isset( $asset_data['version'] ) ) {
				wp_enqueue_script(
					$handle,
					TABLE_BUILDER_BLOCK_PLUGIN_URL . "build/tablebuilder/{$script_file}",
					$asset_data['dependencies'],
					$asset_data['version'],
					true
				);

				wp_set_script_translations( $handle, 'table-builder-block', TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'languages' );
			}
		}
	}

	/**
	 * Converts custom properties to CSS rules for global presets.
	 *
	 * @param array $custom_properties The array of custom CSS properties.
	 * @return string The generated CSS rules.
	 */
	public function convert_custom_properties( $custom_properties ) {
		if ( empty( $custom_properties ) ) {
			return '';
		}

		$css = array_map(
			function ( $key, $value ) {
				return "--table-builder-global-{$key}: {$value};";
			},
			array_keys( $custom_properties ),
			$custom_properties
		);

		return 'body {' . implode( ' ', $css ) . '}';
	}

	/**
	 * Prints device script in the header for responsive behavior.
	 *
	 * @return void
	 */
	public function print_device_script_for_window() {
		if ( ! is_admin() ) {
			$devices = Utils::get_device_list();
			wp_add_inline_script( 'wp-block-editor', 'var breakpoints = ' . wp_json_encode( $devices ) . ';', 'before' );
		}
	}

	/**
	 * Adds a tableBuilder class to the admin/editor body.
	 *
	 * @param string[]|string $classes Existing body classes (array for "body_class", string for "admin_body_class").
	 * @return string[]|string Modified classes, same type as received.
	 */
	public function add_body_class( $classes ) {
		if ( is_array( $classes ) ) {
			$classes[] = 'tableBuilder-frontend';
		} else {
			$classes .= ' tableBuilder';
		}
		return $classes;
	}
}
