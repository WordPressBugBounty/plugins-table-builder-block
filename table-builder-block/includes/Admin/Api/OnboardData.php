<?php
/**
 * REST endpoints for the plugin's onboarding flow
 *
 * @package TableKit
 */

namespace TableBuilder\Admin\Api;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and serves the tablebuilder/v1/onboard REST routes.
 */
class OnboardData {
	private const PLUGIN_SUBSCRIBE_URL = 'https://api.wpmet.com/public/plugin-subscribe/';

	/**
	 * Hooks onboarding REST route registration into WordPress.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Sends the user's email to Wpmet's subscribe endpoint when onboarding completes.
	 *
	 * @param string $email User email to subscribe.
	 * @return void
	 */
	private function send_email_subscribe_data( string $email ): void {
		wp_remote_post(
			self::PLUGIN_SUBSCRIBE_URL,
			array(
				'method'  => 'POST',
				'headers' => array(
					'Accept'       => '*/*',
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'email' => $email,
						'slug'  => 'tablekit',
					)
				),
			)
		);
	}

	/**
	 * Registers the tablebuilder/v1/onboard GET/POST REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'tablebuilder/v1',
			'onboard',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'action_get_onboard' ),
				'permission_callback' => array( $this, 'is_request_allowed' ),
			)
		);

		register_rest_route(
			'tablebuilder/v1',
			'onboard',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'action_update_onboard' ),
				'permission_callback' => array( $this, 'is_request_allowed' ),
			)
		);
	}

	/**
	 * Verifies nonce and capability. Used as permission_callback so
	 * WordPress returns a proper 401/403 status on failure.
	 *
	 * @param \WP_REST_Request $request Request; only its nonce header is used.
	 * @return true|\WP_Error
	 */
	public function is_request_allowed( $request ) {
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
	 * REST callback: get current onboarding completion status.
	 *
	 * @param \WP_REST_Request $request Unused; no params required.
	 * @return array{status:string,onboard:array{completed:bool,completedAt:string}}
	 */
	public function action_get_onboard( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the register_rest_route() "callback" signature; not needed in the body.
		return array(
			'status'  => 'success',
			'onboard' => array(
				'completed'   => (bool) get_option( 'tablebuilder_onboard_completed', false ),
				'completedAt' => get_option( 'tablebuilder_onboard_completed_at', '' ),
			),
		);
	}

	/**
	 * REST callback: mark onboarding completed/incomplete, optionally
	 * subscribing the given email when marking it completed.
	 *
	 * @param \WP_REST_Request $request Request with "completed" (bool) and optional "userMail" params.
	 * @return array{status:string,onboard:array{completed:bool,completedAt:string}}
	 */
	public function action_update_onboard( $request ) {
		$completed = (bool) $request->get_param( 'completed' );
		$user_mail = sanitize_email( wp_unslash( (string) $request->get_param( 'userMail' ) ) );

		update_option( 'tablebuilder_onboard_completed', $completed ? 1 : 0 );

		if ( $completed ) {
			if ( ! empty( $user_mail ) && is_email( $user_mail ) ) {
				$this->send_email_subscribe_data( $user_mail );
			}

			update_option( 'tablebuilder_onboard_completed_at', current_time( 'mysql' ) );
			delete_transient( 'tablebuilder_show_onboard' );
		}

		return array(
			'status'  => 'success',
			'onboard' => array(
				'completed'   => $completed,
				'completedAt' => get_option( 'tablebuilder_onboard_completed_at', '' ),
			),
		);
	}
}
