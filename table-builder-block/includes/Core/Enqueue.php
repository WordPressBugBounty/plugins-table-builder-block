<?php
namespace TableBuilder\Core;

defined('ABSPATH') || exit;

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
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('enqueue_block_editor_assets', array($this, 'blocks_editor_scripts'), 5);
        add_action('wp_head', array($this, 'print_device_script_for_window'));
    }

	/**
	 * Get WP filesystem
	 *
	 * @return void
	 */
	protected function get_filesystem() {
		if (!function_exists('WP_Filesystem')) {
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
    public function admin_scripts($hook) {
        wp_localize_script(
            'wp-block-editor',
            'tableBuilder',
            array(
                'plugin_url'    => TABLE_BUILDER_BLOCK_PLUGIN_URL,
                'screen'        => $hook,
                'api_url'       => TABLE_BUILDER_BLOCK_PLUGIN_URL . 'api/',
                'root_url'      => esc_url(home_url('/')),
                'rest_url'      => esc_url_raw(rest_url()),
                'rest_nonce'    => wp_create_nonce('wp_rest'),
                'version'       => TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
                'generalSettingsUrl' => admin_url('options-general.php'),
                'activeTheme'   => wp_get_theme()->get('Name'),
                'assetUrl' => TABLE_BUILDER_BLOCK_PLUGIN_URL . 'assets/images/',
                'has_pro' => defined('TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION'),
            )
        );

        if($hook == 'toplevel_page_tablebuilder') {
			$assets = include TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/admin/dashboard/index.asset.php';
            if ( isset( $assets['version'] ) ) {
                

				// Enqueue the JavaScript
				wp_enqueue_script(
					'tablebuilder-admin-dashboard',
                    TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/admin/dashboard/index.js',
                    $assets['dependencies'],
                    $assets['version'],
                    true
				);

                // Enqueue the stylesheet
                wp_enqueue_style(
                    'tablebuilder-admin-dashboard',
                    TABLE_BUILDER_BLOCK_PLUGIN_URL . 'build/admin/dashboard/index.css',
                    array(),
                    $assets['version']
                );

                wp_localize_script(
                    'tablebuilder-admin-dashboard',
                    'tableBuilder',
                    array(
                        'plugin_url' => TABLE_BUILDER_BLOCK_PLUGIN_URL,
                        'screen' => $hook,
                        'adminUrl' => esc_url(admin_url('/')),
                        'has_pro' => defined('TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION'),
                        'version' => TABLE_BUILDER_BLOCK_PLUGIN_VERSION,
                        'pro_version' => defined('TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION') ? TABLE_BUILDER_BLOCK_PRO_PLUGIN_VERSION : '1.0.0',
                    )
                );
            }
		}
    }

    /**
     * Enqueue block editor assets.
     *
     * @return void
     * @since 1.0.0
     */
    public function blocks_editor_scripts() {
        $assets = [
            'components' => TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/tablebuilder/components.asset.php',
            'helper'     => TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/tablebuilder/helper.asset.php',
            'global'     => TABLE_BUILDER_BLOCK_PLUGIN_DIR . 'build/tablebuilder/global.asset.php',
        ];

        foreach ($assets as $handle => $asset_path) {
            $this->enqueue_assets($asset_path, "table_builder-block-editor-{$handle}", "{$handle}.js");
        }
    }

    private function enqueue_assets($asset_file, $handle, $script_file) {
		$this->get_filesystem();
		global $wp_filesystem;

        if (!isset($wp_filesystem) || !$wp_filesystem->exists($asset_file)) {
            return;
        }

        // Check if asset file exists
        if ($wp_filesystem->exists($asset_file)) {
            $asset_data = include $asset_file;

            // Ensure asset data is valid
            if (!empty($asset_data['dependencies']) && is_array($asset_data['dependencies']) && isset($asset_data['version'])) {
                wp_enqueue_script(
                    $handle,
                    TABLE_BUILDER_BLOCK_PLUGIN_URL . "build/tablebuilder/{$script_file}",
                    $asset_data['dependencies'],
                    $asset_data['version'],
                    true
                );
            }
        }
    }

    /**
     * Converts custom properties to CSS rules for global presets.
     *
     * @param array $custom_properties The array of custom CSS properties.
     * @return string The generated CSS rules.
     */
    public function convert_custom_properties($custom_properties) {
        if (empty($custom_properties)) {
            return '';
        }

        $css = array_map(function ($key, $value) {
            return "--table-builder-global-{$key}: {$value};";
        }, array_keys($custom_properties), $custom_properties);

        return "body {" . implode(' ', $css) . "}";
    }

    /**
     * Print device script in the header for responsive behavior.
     */
    public function print_device_script_for_window() {
        if (!is_admin()) {
            $devices = Utils::get_device_list();
            wp_add_inline_script('wp-block-editor', 'var breakpoints = ' . wp_json_encode($devices) . ';', 'before');
        }
    }
}
