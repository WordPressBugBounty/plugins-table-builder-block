<?php
namespace TableBuilder\Core;

defined('ABSPATH') || exit;

/**
 * REST API fallback when tablekit-essential is not active.
 * Mimics tablekit-essential behavior for compatibility.
 *
 * @since 1.0.0
 */
class RestApi {
    use \TableBuilder\Traits\Singleton;

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('activated_plugin', array($this, 'clear_plugin_cache'));
        add_action('deactivated_plugin', array($this, 'clear_plugin_cache'));
    }

    // Clear plugin cache when plugins change.
    public function clear_plugin_cache() {
        delete_transient('tablekit_plugin_data');
    }

    // Register REST routes only if tablekit-essential is not active.
    public function register_routes() {
        if ($this->is_tablekit_essential_active()) {
            return;
        }

        register_rest_route('tablekit/v1', '/table-manager-api/check-plugins', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'check_plugins_status'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('tablekit/v1', '/table-manager-api/activate-plugins', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'activate_plugins'),
            'permission_callback' => function() { return current_user_can('activate_plugins'); },
        ));

        register_rest_route('tablekit/v1', '/table-manager-api/install-plugins', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'install_plugins'),
            'permission_callback' => function() { return current_user_can('install_plugins'); },
        ));

        register_rest_route('tablekit/v1', '/table-manager-api/check-pro-license', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'check_pro_license'),
            'permission_callback' => function() { return current_user_can('activate_plugins'); },
        ));
    }

    private function is_tablekit_essential_active() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('tablekit-essential/tablekit-essential.php');
    }

    public function check_plugins_status($request) {
        $plugins = $request->get_json_params()['plugins'] ?? array();
        if (empty($plugins)) {
            return new \WP_REST_Response(array('status' => 'error', 'message' => 'No plugins specified.'), 400);
        }

        // Clear plugin cache to ensure fresh data (critical for detecting deactivated plugins on servers)
        wp_cache_delete('plugins', 'plugins');
 

        if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $status = array();

        foreach ($plugins as $slug) {
            $slug = sanitize_text_field($slug);
            if (empty($slug)) continue;

            $plugin_file = null;
            foreach ($all_plugins as $plugin_path => $plugin_data) {
                if (strpos($plugin_path, $slug . '/') === 0 || $plugin_path === $slug . '.php') {
                    $plugin_file = $plugin_path;
                    break;
                }
            }

            $is_installed = $plugin_file !== null;
            $is_active = $is_installed && is_plugin_active($plugin_file);

            $status[$slug] = array(
                'installed' => $is_installed,
                'active' => $is_active,
                'state' => $is_installed ? ($is_active ? 'installed_active' : 'installed_inactive') : 'not_installed',
                'plugin_file' => $plugin_file,
            );
        }

        return new \WP_REST_Response(array('status' => 'success', 'plugins' => $status), 200);
    }

    // Activate plugins.
    public function activate_plugins($request) {
        $plugins = $request->get_json_params()['plugins'] ?? array();
        if (empty($plugins)) {
            return new \WP_REST_Response(array('status' => 'error', 'message' => 'No plugins specified.'), 400);
        }

        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        wp_cache_delete('plugins', 'plugins');

        // Set transient to prevent redirect during programmatic activation
        set_transient('tablekit_skip_activation_redirect', true, 60);

        $all_plugins = get_plugins();
        $activated = array();
        $failed = array();

        foreach ($plugins as $slug) {
            $slug = sanitize_text_field($slug);
            if (empty($slug)) continue;

            $plugin_file = null;
            foreach ($all_plugins as $plugin_path => $plugin_data) {
                if (strpos($plugin_path, $slug . '/') === 0 || $plugin_path === $slug . '.php') {
                    $plugin_file = $plugin_path;
                    break;
                }
            }

            if (!$plugin_file) {
                $failed[] = array('slug' => $slug, 'error' => 'Plugin not found');
                continue;
            }

            if (is_plugin_active($plugin_file)) {
                $activated[] = $slug;
                continue;
            }

            $result = activate_plugin($plugin_file, '', false, true); // Silent activation
            if (is_wp_error($result)) {
                $failed[] = array('slug' => $slug, 'error' => $result->get_error_message());
            } else {
                $activated[] = $slug;
            }
        }

        // Clean up transient
        delete_transient('tablekit_skip_activation_redirect');

        // Clear plugin cache after activation
        $this->clear_plugin_cache();

        return new \WP_REST_Response(array(
            'status' => empty($failed) ? 'success' : 'partial',
            'activated' => $activated,
            'failed' => $failed,
        ), 200);
    }

    // Install plugins.
    public function install_plugins($request) {
        $plugins = $request->get_json_params()['plugins'] ?? array();
        if (empty($plugins)) {
            return new \WP_REST_Response(array('status' => 'error', 'message' => 'No plugins specified.'), 400);
        }

        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $failed = array();

        foreach ($plugins as $slug) {
            $slug = sanitize_text_field($slug);
            if (empty($slug)) continue;

            // Check if pro plugin
            if (strpos($slug, '-pro') !== false) {
                $failed[] = array('slug' => $slug, 'error' => 'Pro plugins must be purchased manually');
                continue;
            }

            // Check if already installed
            $all_plugins = get_plugins();
            $plugin_file = null;
            foreach ($all_plugins as $plugin_path => $plugin_data) {
                if (strpos($plugin_path, $slug . '/') === 0) {
                    $plugin_file = $plugin_path;
                    break;
                }
            }

            if ($plugin_file) {
                // Already installed, just activate
                if (!is_plugin_active($plugin_file)) {
                    set_transient('tablekit_skip_activation_redirect', true, 60); // Set transient to prevent redirect
                    activate_plugin($plugin_file, '', false, true); // Silent activation
                    delete_transient('tablekit_skip_activation_redirect');
                }
                continue;
            }

            // Install plugin
            $api = plugins_api('plugin_information', array('slug' => $slug, 'fields' => array('sections' => false)));
            
            if (is_wp_error($api)) {
                $failed[] = array('slug' => $slug, 'error' => $api->get_error_message());
                continue;
            }

            $upgrader = new \Plugin_Upgrader(new \WP_Ajax_Upgrader_Skin());
            $result = $upgrader->install($api->download_link);

            if (is_wp_error($result)) {
                $failed[] = array('slug' => $slug, 'error' => $result->get_error_message());
            } elseif ($result === false) {
                $failed[] = array('slug' => $slug, 'error' => 'Installation failed');
            }
        }

        // Clear plugin cache after installation
        $this->clear_plugin_cache();

        return new \WP_REST_Response(array(
            'status' => empty($failed) ? 'success' : 'partial',
            'failed' => $failed,
        ), 200);
    }

    /**
     * Check pro plugin license.
     */
    public function check_pro_license($request) {
        $plugins = $request->get_json_params()['plugins'] ?? array();
        if (empty($plugins)) {
            return new \WP_REST_Response(array('status' => 'error', 'message' => 'No plugins specified.'), 400);
        }

        // Check if this is gutenkit-blocks-addon-pro
        $is_gutenkit_pro = false;
        foreach ($plugins as $slug) {
            if (strpos($slug, 'gutenkit-blocks-addon-pro') !== false) {
                $is_gutenkit_pro = true;
                break;
            }
        }

        if (!$is_gutenkit_pro) {
            return new \WP_REST_Response(array(
                'status' => 'error',
                'license_valid' => false,
                'message' => 'License check is only available for GutenKit Pro.',
            ), 400);
        }

        // Check GutenKit license status
        // GutenKit uses these options to store license info
        $oppai = get_option('__gutenkit_oppai__', '');
        $key = get_option('__gutenkit_license_key__', '');

        $license_valid = !empty($oppai) && !empty($key);

        if (!$license_valid) {
            return new \WP_REST_Response(array(
                'status' => 'error',
                'license_valid' => false,
                'message' => 'Pro plugin license is not active. Please activate your license at GutenKit → Settings → License to use pro features.',
            ), 200);
        }

        return new \WP_REST_Response(array(
            'status' => 'success',
            'license_valid' => true,
            'message' => 'License is valid.',
        ), 200);
    }

    /**
     * Get localized data - mimics tablekit-essential format.
     * Now with caching to improve performance.
     */
    public static function get_localized_data() {
        // Check cache first (expires after 12 hours)
        $cached_data = get_transient('tablekit_plugin_data');
        if (false !== $cached_data && is_array($cached_data)) {
            // Update dynamic nonces
            $cached_data['restNonce'] = wp_create_nonce('wp_rest');
            $cached_data['ajaxNonce'] = wp_create_nonce('tablekit_activate_nonce');
            return $cached_data;
        }

        if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = array();
        $installed_plugins = array();

        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $slug = explode('/', $plugin_path)[0];
            $installed_plugins[] = $slug;
            
            if (is_plugin_active($plugin_path)) {
                $active_plugins[] = $slug;
            }
        }

        $data = array(
            'apiUrl'            => rest_url('tablekit/v1/'),
            'restNonce'         => wp_create_nonce('wp_rest'),
            'ajaxNonce'         => wp_create_nonce('tablekit_activate_nonce'),
            'activePlugins'     => array_unique($active_plugins),
            'installedPlugins'  => array_unique($installed_plugins),
            'requiredPlugins'   => array('gutenkit-blocks-addon', 'gutenkit-blocks-addon-pro'),
        );

        // Cache for 12 hours
        set_transient('tablekit_plugin_data', $data, 12 * HOUR_IN_SECONDS);

        return $data;
    }
}
