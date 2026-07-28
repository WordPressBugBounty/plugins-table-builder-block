<?php
/**
 * REST endpoints for the plugin's settings
 *
 * @package TableKit
 */

namespace TableBuilder\Admin\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and serves the tablebuilder/v1/settings REST routes.
 */
class SettingsData {
	/**
	 * Unused option-name prefix, reserved for future use.
	 *
	 * @var string
	 */
	public $prefix = '';

	/**
	 * Unused request-param key, reserved for future use.
	 *
	 * @var string
	 */
	public $param = '';

	/**
	 * Unused cached request reference, reserved for future use.
	 *
	 * @var \WP_REST_Request|null
	 */
	public $request = null;

	/**
	 * Registers the tablebuilder/v1/settings GET/POST REST routes.
	 */
	public function __construct() {
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'tablebuilder/v1',
					'settings',
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( $this, 'action_get_settings' ),
						'permission_callback' => array( $this, 'check_permission' ),
					)
				);
			}
		);

		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'tablebuilder/v1',
					'settings',
					array(
						'methods'             => \WP_REST_Server::EDITABLE,
						'callback'            => array( $this, 'action_edit_settings' ),
						'permission_callback' => array( $this, 'check_permission' ),
					)
				);
			}
		);
	}

	/**
	 * Verifies nonce and capability before either callback runs.
	 * Doing this in permission_callback (rather than inside the action)
	 * ensures WordPress returns a proper 401/403 status on failure.
	 *
	 * @param \WP_REST_Request $request The incoming request.
	 * @return true|\WP_Error True if allowed, otherwise a 403 WP_Error.
	 */
	public function check_permission( $request ) {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new \WP_Error(
				'rest_cookie_invalid_nonce',
				__( 'Nonce mismatch.', 'table-builder-block' ),
				array( 'status' => 403 )
			);
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Access denied.', 'table-builder-block' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * REST callback: fetch the stored "gutenkit_settings_list" option.
	 *
	 * @param \WP_REST_Request $request Unused; no params required.
	 * @return array{status:string,settings:mixed,message:string[]}
	 */
	public function action_get_settings( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the register_rest_route() "callback" signature; not needed in the body.
		$result_data = get_option( 'gutenkit_settings_list' );

		return array(
			'status'   => 'success',
			'settings' => $result_data,
			'message'  => array( __( 'Settings list has been fetched successfully.', 'table-builder-block' ) ),
		);
	}

	/**
	 * REST callback: overwrite the "gutenkit_settings_list" option.
	 *
	 * @param \WP_REST_Request $request Request with a "settings" param.
	 * @return array{status:string,settings?:mixed,message:string[]}
	 */
	public function action_edit_settings( $request ) {
		$req_data = $request->get_params();

		if ( array_key_exists( 'settings', $req_data ) ) {
			$data      = $req_data['settings'];
			$array_get = update_option( 'gutenkit_settings_list', $data );

			return array(
				'status'   => 'success',
				'settings' => $array_get,
				'message'  => array( __( 'Settings list has been updated successfully.', 'table-builder-block' ) ),
			);
		} else {
			return array(
				'status'  => 'fail',
				'message' => array( __( 'Something went wrong.', 'table-builder-block' ) ),
			);
		}
	}
}
