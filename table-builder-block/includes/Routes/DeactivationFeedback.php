<?php
/**
 * Receives deactivation-feedback data from the plugins-page modal
 *
 * @package TableKit
 */

namespace TableBuilder\Routes;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the REST route that records why a user deactivated the plugin.
 */
class DeactivationFeedback {

	/**
	 * Hooks the deactivation-feedback REST route registration into WordPress.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the tablekit/v1/deactivation-feedback REST route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'tablekit/v1',
			'/deactivation-feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_feedback' ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => array(
					'reason_key'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'reason_label' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'message'      => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);
	}

	/**
	 * REST permission callback: require "manage_options" capability.
	 *
	 * @return bool
	 */
	public function permission_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST callback: send the plugin's deactivation feedback (reason, environment,
	 * and anonymous usage stats) to Wpmet's unsubscribe endpoint (fire-and-forget).
	 *
	 * @param \WP_REST_Request $request Request with "reason_key" (required), "reason_label"
	 *                                  (required), and "message" (optional) params.
	 * @return \WP_REST_Response Always a 200 success response.
	 */
	public function handle_feedback( $request ) {
		// Use get_param() (not get_json_params()) so the sanitize_callback
		// declared in register_routes() above is actually applied.
		$data = array(
			'plugin_slug'    => 'table-builder-block',
			'plugin_name'    => 'TableKit',
			'plugin_version' => defined( 'TABLE_BUILDER_BLOCK_PLUGIN_VERSION' ) ? TABLE_BUILDER_BLOCK_PLUGIN_VERSION : '',
			'user'           => array(
				'email' => wp_get_current_user()->user_email,
			),
			'feedback'       => array(
				'reason_key'   => (string) $request->get_param( 'reason_key' ),
				'reason_label' => (string) $request->get_param( 'reason_label' ),
				'message'      => (string) $request->get_param( 'message' ),
			),
			'usage'          => array(
				'active_widgets' => $this->get_active_widgets(),
				'user_type'      => $this->get_user_type(),
				'active_days'    => $this->get_days_active(),
			),
			'environment'    => array(
				'multisite_status' => is_multisite(),
				'wp_version'       => get_bloginfo( 'version' ),
				'php_version'      => PHP_VERSION,
				'site_url'         => get_site_url(),
			),
		);

		wp_remote_post(
			'https://api.wpmet.com/public/plugin-unsubscribe/',
			array(
				'method'   => 'POST',
				'timeout'  => 20,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( $data ),
			)
		);

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Gets the slugs of the blocks registered by TableKit.
	 *
	 * @return array
	 */
	public function get_active_widgets() {
		if ( ! class_exists( '\TableBuilder\Config\BlockList' ) ) {
			return array();
		}

		$blocks = \TableBuilder\Config\BlockList::get_block_list();

		return is_array( $blocks ) ? array_keys( $blocks ) : array();
	}

	/**
	 * Determines the user type based on the pro plugin and license status.
	 *
	 * @return string One of 'pro_valid', 'pro' or 'free'.
	 */
	public function get_user_type() {
		if ( ! $this->is_pro_installed() ) {
			return 'free';
		}

		$is_licensed = class_exists( '\TableBuilder\Helpers\Utils' )
			&& \TableBuilder\Helpers\Utils::status() === 'valid';

		return $is_licensed ? 'pro_valid' : 'pro';
	}

	/**
	 * Checks whether the TableKit Pro plugin is installed (regardless of active state).
	 *
	 * @return bool
	 */
	protected function is_pro_installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return array_key_exists( 'table-builder-block-pro/table-builder-block-pro.php', get_plugins() );
	}

	/**
	 * Gets the number of days the plugin has been active since install.
	 *
	 * @return int
	 */
	public function get_days_active() {
		$installed_time = (int) get_option( 'tablebuilder_installed_time', 0 );

		if ( empty( $installed_time ) ) {
			return 0;
		}

		return (int) floor( ( time() - $installed_time ) / DAY_IN_SECONDS );
	}
}
