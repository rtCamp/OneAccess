<?php
/**
 * Class Brand_Site -- this is to have REST API endpoints for brand sites.
 *
 * @package OneAccess
 */

namespace OneAccess\REST;

use OneAccess\Plugin_Configs\Constants;
use OneAccess\Traits\Singleton;
use OneAccess\Utils;

/**
 * Class Brand_Site
 */
class Brand_Site {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'oneaccess/v1';

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup WordPress hooks
	 */
	public function setup_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {

		/**
		 * Register a route to get user profile request data across all brand sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/all-profile-requests',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'all_profile_requests' ),
					'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
				),
			)
		);

		/**
		 * Register a route to approve profile request.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/approve-profile-request',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'approve_profile_request' ),
					'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
					'args'                => array(
						'site_name'  => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The name of the site where the profile request is made.', 'oneaccess' ),
						),
						'user_email' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The email of the user whose profile request is being approved.', 'oneaccess' ),
						),
						'user_id'    => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The ID of the user whose profile request is being approved.', 'oneaccess' ),
						),
					),
				),
			)
		);

		/**
		 * Register a route to reject profile request.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/reject-profile-request',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'reject_profile_request' ),
					'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
					'args'                => array(
						'site_name'         => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The name of the site where the profile request is made.', 'oneaccess' ),
						),
						'user_email'        => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The email of the user whose profile request is being rejected.', 'oneaccess' ),
						),
						'rejection_comment' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'The comment explaining why the profile request is being rejected.', 'oneaccess' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Reject user profile request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function reject_profile_request( \WP_REST_Request $request ): \WP_REST_Response {
		$site_name         = sanitize_text_field( $request->get_param( 'site_name' ) );
		$user_email        = sanitize_email( $request->get_param( 'user_email' ) );
		$rejection_comment = sanitize_text_field( $request->get_param( 'rejection_comment' ) );
		$request_id        = sanitize_text_field( $request->get_param( 'request_id' ) );

		if ( empty( $site_name ) || empty( $user_email ) || empty( $rejection_comment ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Site name, user email, and rejection comment are required.', 'oneaccess' ),
				),
				400
			);
		}

		// Get the site URL and API key from the global oneaccess_sites.
		$oneaccess_sites = $GLOBALS['oneaccess_sites'] ?? array();
		$site_url        = $oneaccess_sites[ $site_name ]['siteUrl'] ?? '';
		$api_key         = $oneaccess_sites[ $site_name ]['apiKey'] ?? '';

		$response = wp_safe_remote_post(
			$site_url . '/wp-json/' . self::NAMESPACE . '/reject-profile',
			array(
				'headers' => array(
					'X-OneAccess-Token' => $api_key,
				),
				'body'    => array(
					'user_email'        => $user_email,
					'rejection_comment' => $rejection_comment,
					'request_id'        => $request_id,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to reject profile request.', 'oneaccess' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success'           => true,
				'message'           => __( 'Profile request rejected successfully.', 'oneaccess' ),
				'site_name'         => $site_name,
				'user_email'        => $user_email,
				'rejection_comment' => $rejection_comment,
			),
			200
		);
	}

	/**
	 * Approve user profile request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function approve_profile_request( \WP_REST_Request $request ): \WP_REST_Response {
		$site_name  = sanitize_text_field( $request->get_param( 'site_name' ) );
		$user_email = sanitize_email( $request->get_param( 'user_email' ) );
		$user_id    = absint( $request->get_param( 'user_id' ) );
		$request_id = sanitize_text_field( $request->get_param( 'request_id' ) );

		if ( empty( $site_name ) || empty( $user_email ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Site name and user email are required.', 'oneaccess' ),
				),
				400
			);
		}

		// Get the site URL and API key from the global oneaccess_sites.
		$oneaccess_sites = $GLOBALS['oneaccess_sites'] ?? array();
		$site_url        = $oneaccess_sites[ $site_name ]['siteUrl'] ?? '';
		$api_key         = $oneaccess_sites[ $site_name ]['apiKey'] ?? '';

		$reponse = wp_safe_remote_post(
			$site_url . '/wp-json/' . self::NAMESPACE . '/approve-profile',
			array(
				'headers' => array(
					'X-OneAccess-Token' => $api_key,
				),
				'body'    => array(
					'user_id'    => $user_id,
					'user_email' => $user_email,
					'request_id' => $request_id,
				),
			)
		);

		if ( is_wp_error( $reponse ) || 200 !== wp_remote_retrieve_response_code( $reponse ) ) {
			return new \WP_REST_Response(
				array(
					'success'  => false,
					'message'  => __( 'Failed to approve profile request.', 'oneaccess' ),
					'response' => $reponse,
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Profile request approved successfully.', 'oneaccess' ),
				'user_id' => $user_id,
			),
			200
		);
	}

	/**
	 * Get user profile request data across all brand sites.
	 *
	 * @return \WP_REST_Response
	 */
	public function all_profile_requests(): \WP_REST_Response {
		// get all sites data.
		$oneaccess_sites_info = $GLOBALS['oneaccess_sites'] ?? array();

		if ( ! is_array( $oneaccess_sites_info ) ) {
			$oneaccess_sites_info = array();
		}

		$all_profile_requests = array();
		$fetched_sites        = array();
		$pending_requests     = 0;

		foreach ( $oneaccess_sites_info as $site ) {

			if ( in_array( $site['siteUrl'], $fetched_sites, true ) ) {
				continue; // Skip if already fetched.
			}
			$fetched_sites[] = $site['siteUrl'];
			$timezone        = get_option( Constants::ONEACCESS_TIMEZONE_STRING ) ?? 'UTC';
			$request_url     = $site['siteUrl'] . '/wp-json/' . self::NAMESPACE . '/profile-requests?' . wp_date( 'd-m-Y H:i:s', null, $timezone );
			$api_key         = $site['apiKey'] ?? '';

			$response = wp_safe_remote_get(
				$request_url,
				array(
					'headers' => array(
						'X-OneAccess-Token' => $api_key,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				continue;
			}

			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );
			if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {

				// add site name to each data.
				foreach ( $data['data'] as &$request ) {
					$request['site_name'] = $site['siteName'] ?? 'Unknown Site';
					if ( isset( $request['status'] ) && 'pending' === $request['status'] ) {
						++$pending_requests;
					}
				}

				$all_profile_requests = array_merge( $all_profile_requests, $data['data'] );
			}
		}

		// sort all profile requests by requested_at time.
		usort(
			$all_profile_requests,
			function ( $a, $b ) {
				return strtotime( $b['requested_at'] ) - strtotime( $a['requested_at'] );
			}
		);

		return new \WP_REST_Response(
			array(
				'success'          => true,
				'profile_requests' => $all_profile_requests,
				'count'            => $pending_requests,
			),
			200
		);
	}
}
