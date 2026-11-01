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
	 * Shared Wpmet registry of plugins installed through an onboarding flow, keyed by plugin file.
	 */
	private const ONBOARDED_PLUGINS_OPTION = 'wpmet_onboarded_plugins';

	/**
	 * Onboarding data of the plugins offered on the Website Powerup step, keyed by slug.
	 *
	 * `file` is the key used in the shared registry. `option` is that plugin's own
	 * onboarding status option, set so it does not start its own onboarding after
	 * TableKit installed it (empty when the plugin has none). Matches GutenKit's
	 * InstallTracker::ONBOARD_STATUS_MAP.
	 */
	private const ONBOARD_STATUS_MAP = array(
		'gutenkit-blocks-addon' => array(
			'file'   => 'gutenkit-blocks-addon/gutenkit-blocks-addon.php',
			'option' => 'gutenkit_onboard_status',
		),
		'popup-builder-block'   => array(
			'file'   => 'popup-builder-block/popup-builder-block.php',
			'option' => 'popupkit_onboard_status',
		),
		'elementskit-lite'      => array(
			'file'   => 'elementskit-lite/elementskit-lite.php',
			'option' => 'elements_kit_onboard_status',
		),
		'metform'               => array(
			'file'   => 'metform/metform.php',
			'option' => 'met_form_onboard_status',
		),
		'getgenie'              => array(
			'file'   => 'getgenie/getgenie.php',
			'option' => 'getgenie_onboard_status',
		),
		'shopengine'            => array(
			'file'   => 'shopengine/shopengine.php',
			'option' => 'shopengine_onboard_status',
		),
		'blocks-for-shopengine' => array(
			'file'   => 'blocks-for-shopengine/shopengine-gutenberg-addon.php',
			'option' => '',
		),
		'wp-social'             => array(
			'file'   => 'wp-social/wp-social.php',
			'option' => '',
		),
		'wp-ultimate-review'    => array(
			'file'   => 'wp-ultimate-review/wp-ultimate-review.php',
			'option' => '',
		),
	);

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
	 * @return bool True when the endpoint answered with a 2xx status.
	 */
	private function send_email_subscribe_data( string $email ): bool {
		$response = wp_remote_post(
			self::PLUGIN_SUBSCRIBE_URL,
			array(
				'method'  => 'POST',
				'timeout' => 15,
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

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return $code >= 200 && $code < 300;
	}

	/**
	 * Stores the subscribed email, for TableKit and in the options shared by
	 * the Wpmet plugins so they know an address was already collected.
	 *
	 * @param string $email Subscribed email.
	 * @return void
	 */
	private function store_subscription( string $email ): void {
		update_option( 'tablebuilder_onboard_email', 'subscribed', false );
		update_option( 'tablebuilder_onboard_email_id', $email, false );
		update_option( 'wpmet_onboard_email_collected', true, false );
		update_option( 'wpmet_onboard_collected_email', $email, false );
	}

	/**
	 * Records every plugin the user opted into in the shared Wpmet registry and
	 * completes that plugin's own onboarding status if it is not set yet.
	 *
	 * Existing registry entries and status options are never overwritten.
	 *
	 * @param mixed $plugin_permission Permission map from the onboarding payload, slug => granted.
	 * @return string[] The tracked plugin files.
	 */
	private function track_plugins( $plugin_permission ): array {
		if ( ! is_array( $plugin_permission ) ) {
			return array();
		}

		$slugs = array();

		foreach ( $plugin_permission as $slug => $granted ) {
			$slug = sanitize_key( $slug );

			if ( empty( $slug ) || ! rest_sanitize_boolean( $granted ) ) {
				continue;
			}

			$slugs[] = $slug;

			// The ShopEngine blocks plugin is installed together with ShopEngine.
			if ( 'blocks-for-shopengine' === $slug ) {
				$slugs[] = 'shopengine';
			}
		}

		$registry = (array) get_option( self::ONBOARDED_PLUGINS_OPTION, array() );
		$changed  = false;
		$tracked  = array();

		foreach ( array_unique( $slugs ) as $slug ) {
			if ( ! isset( self::ONBOARD_STATUS_MAP[ $slug ] ) ) {
				continue;
			}

			$plugin_file = self::ONBOARD_STATUS_MAP[ $slug ]['file'];
			$option      = self::ONBOARD_STATUS_MAP[ $slug ]['option'];

			if ( ! isset( $registry[ $plugin_file ] ) ) {
				$registry[ $plugin_file ] = array(
					'installed_by' => 'tablekit',
					'installed_at' => time(),
				);
				$changed = true;
			}

			if ( ! empty( $option ) && ! get_option( $option ) ) {
				update_option( $option, 'onboarded', false );
			}

			$tracked[] = $plugin_file;
		}

		if ( $changed ) {
			update_option( self::ONBOARDED_PLUGINS_OPTION, $registry, false );
		}

		return $tracked;
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
	 * @return array{status:string,onboard:array{completed:bool,completedAt:string,email:string,consent:string}}
	 */
	public function action_get_onboard( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- required by the register_rest_route() "callback" signature; not needed in the body.
		return array(
			'status'  => 'success',
			'onboard' => array(
				'completed'   => (bool) get_option( 'tablebuilder_onboard_completed', false ),
				'completedAt' => get_option( 'tablebuilder_onboard_completed_at', '' ),
				'email'       => get_option( 'tablebuilder_onboard_email', '' ),
				'consent'     => get_option( 'tablebuilder_user_consent', '' ),
			),
		);
	}

	/**
	 * REST callback: mark onboarding completed/incomplete. When marking it
	 * completed, also saves the diagnostic data consent, tracks the plugins
	 * the user opted into and subscribes the given email.
	 *
	 * @param \WP_REST_Request $request Request with "completed" (bool) and optional "userMail",
	 *                                  "dataSharePermission" (bool) and "pluginPermission"
	 *                                  (slug => bool) params.
	 * @return array{status:string,subscribed:bool,tracked:string[],onboard:array{completed:bool,completedAt:string}}
	 */
	public function action_update_onboard( $request ) {
		$completed  = rest_sanitize_boolean( $request->get_param( 'completed' ) );
		$user_mail  = sanitize_email( wp_unslash( (string) $request->get_param( 'userMail' ) ) );
		$subscribed = false;
		$tracked    = array();

		update_option( 'tablebuilder_onboard_completed', $completed ? 1 : 0 );

		if ( $completed ) {
			if ( $request->has_param( 'dataSharePermission' ) ) {
				$consent = rest_sanitize_boolean( $request->get_param( 'dataSharePermission' ) );
				update_option( 'tablebuilder_user_consent', $consent ? 'yes' : 'no' );
			}

			$tracked = $this->track_plugins( $request->get_param( 'pluginPermission' ) );

			if ( ! empty( $user_mail ) && is_email( $user_mail ) ) {
				$subscribed = $this->send_email_subscribe_data( $user_mail );

				if ( $subscribed ) {
					$this->store_subscription( $user_mail );
				}
			}

			update_option( 'tablebuilder_onboard_completed_at', current_time( 'mysql' ) );
			delete_transient( 'tablebuilder_show_onboard' );
		}

		return array(
			'status'     => 'success',
			'subscribed' => $subscribed,
			'tracked'    => $tracked,
			'onboard'    => array(
				'completed'   => $completed,
				'completedAt' => get_option( 'tablebuilder_onboard_completed_at', '' ),
			),
		);
	}
}
