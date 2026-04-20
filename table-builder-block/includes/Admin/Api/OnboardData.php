<?php

namespace TableBuilder\Admin\Api;

defined( 'ABSPATH' ) || exit;

class OnboardData {
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'tablebuilder/v1',
			'onboard',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'action_get_onboard' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'tablebuilder/v1',
			'onboard',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'action_update_onboard' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	private function is_request_allowed( $request ): bool {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return false;
		}

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return true;
	}

	public function action_get_onboard( $request ) {
		if ( ! $this->is_request_allowed( $request ) ) {
			return [
				'status'  => 'fail',
				'message' => [ __( 'Access denied.', 'table-builder-block' ) ],
			];
		}

		return [
			'status'  => 'success',
			'onboard' => [
				'completed'   => (bool) get_option( 'tablebuilder_onboard_completed', false ),
				'completedAt' => get_option( 'tablebuilder_onboard_completed_at', '' ),
			],
		];
	}

	public function action_update_onboard( $request ) {
		if ( ! $this->is_request_allowed( $request ) ) {
			return [
				'status'  => 'fail',
				'message' => [ __( 'Access denied.', 'table-builder-block' ) ],
			];
		}

		$completed = (bool) $request->get_param( 'completed' );

		update_option( 'tablebuilder_onboard_completed', $completed ? 1 : 0 );

		if ( $completed ) {
			update_option( 'tablebuilder_onboard_completed_at', current_time( 'mysql' ) );
			delete_transient( 'tablebuilder_show_onboard' );
		}

		return [
			'status'  => 'success',
			'onboard' => [
				'completed'   => $completed,
				'completedAt' => get_option( 'tablebuilder_onboard_completed_at', '' ),
			],
		];
	}
}