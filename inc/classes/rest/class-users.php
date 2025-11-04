<?php
/**
 * Class Users -- contains REST API routes for user management.
 *
 * @package OneAccess
 */

namespace OneAccess\REST;

use OneAccess\Plugin_Configs\Constants;
use OneAccess\Plugin_Configs\DB;
use OneAccess\Traits\Singleton;
use OneAccess\Utils;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Class Users
 */
class Users {

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
		 * Register a route to create user.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/new-users',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_user' ),
					'permission_callback' => 'oneaccess_validate_api_key',
					'args'                => array(
						'username'  => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Username for the new user.', 'oneaccess' ),
						),
						'email'     => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Email address for the new user.', 'oneaccess' ),
						),
						'password'  => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Password for the new user.', 'oneaccess' ),
						),
						'full_name' => array(
							'required'    => false,
							'type'        => 'string',
							'description' => __( 'Full name of the new user.', 'oneaccess' ),
						),
						'role'      => array(
							'required'    => false,
							'type'        => 'string',
							'default'     => 'subscriber',
							'description' => __( 'Role for the new user.', 'oneaccess' ),
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_users' ),
					'permission_callback' => '__return_true', // TODO -- add proper validation
					'args'                => array(
						'paged'        => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page'     => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 20,
							'sanitize_callback' => 'absint',
						),
						'search_query' => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'role'         => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'site'         => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		/**
		 * Register a route to generate strong password.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/generate-strong-password',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'generate_strong_password' ),
				'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
			)
		);

		/**
		 * Register a route to create users for sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/create-user-for-sites',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_users_for_sites' ),
				'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
				'args'                => array(
					'userdata' => array(
						'required' => true,
						'type'     => 'object',
					),
					'sites'    => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);

		/**
		 * Register a route to update user roles for multiple sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/update-user-roles',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_user_roles_for_sites' ),
				'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'username' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'roles'    => array(
						'required' => true,
						'type'     => 'object',
					),
				),
			)
		);

		/**
		 * Register a route to update user role.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/update-user',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_user' ),
				'permission_callback' => 'oneaccess_validate_api_key',
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'username' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'role'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		/**
		 * Register a route to add user to multiple sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/add-user-to-sites',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_user_to_sites' ),
				'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'username' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'fullName' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'password' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sites'    => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);

		/**
		 * Register a route to approve user profile request.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/approve-profile',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'approve_profile' ),
				'permission_callback' => 'oneaccess_validate_api_key',
				'args'                => array(
					'user_id' => array(
						'required'    => true,
						'type'        => 'string',
						'description' => __( 'The email of the user whose profile request is being approved.', 'oneaccess' ),
					),
				),
			)
		);

		/**
		 * Register a route to reject user profile request.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/reject-profile',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'reject_profile' ),
				'permission_callback' => 'oneaccess_validate_api_key',
				'args'                => array(
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
			)
		);

		/**
		 * Register a route to delete user from multiple sites.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/delete-user-from-sites',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_user_from_sites' ),
				'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'username' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'sites'    => array(
						'required' => true,
						'type'     => 'array',
					),
				),
			)
		);

		/**
		 * Registe a route to delete user.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/delete-user',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_user' ),
				'permission_callback' => 'oneaccess_validate_api_key',
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'username' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Delete user.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function delete_user( \WP_REST_Request $request ): \WP_REST_Response {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$username = sanitize_text_field( $request->get_param( 'username' ) );

		if ( empty( $email ) || empty( $username ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Email and username are required.', 'oneaccess' ),
				),
				400
			);
		}

		// Get the user by email or login.
		$user = get_user_by( 'login', $username );
		if ( ! $user ) {
			$user = get_user_by( 'email', $email );
		}
		if ( ! $user ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'User not found.', 'oneaccess' ),
				),
				404
			);
		}

		// cleanup profile request data.
		$profile_requests_data = Utils::get_users_profile_request_data();
		if ( isset( $profile_requests_data[ $user->ID ] ) ) {
			unset( $profile_requests_data[ $user->ID ] );
			update_option( Constants::ONEACCESS_PROFILE_UPDATE_REQUESTS, $profile_requests_data, false );
		}

		// if function wp_delete_user is not available then include user.php file.
		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		wp_delete_user( $user->ID, '0' );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'User deleted successfully.', 'oneaccess' ),
			),
			200
		);
	}

	/**
	 * Delete user from multiple sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function delete_user_from_sites( \WP_REST_Request $request ): \WP_REST_Response {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$username = sanitize_text_field( $request->get_param( 'username' ) );
		$sites    = $request->get_param( 'sites' );

		if ( empty( $email ) || empty( $username ) || empty( $sites ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Email, username and sites are required.', 'oneaccess' ),
				),
				400
			);
		}

		// Validate sites.
		if ( ! is_array( $sites ) || empty( $sites ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid sites data.', 'oneaccess' ),
				),
				400
			);
		}

		$response_data        = array();
		$oneaccess_sites_info = $GLOBALS['oneaccess_sites'] ?? array();
		$error_log            = array();

		foreach ( $sites as $site ) {
			$request_url = $site['site_url'] . '/wp-json/' . self::NAMESPACE . '/delete-user';
			$api_key     = $oneaccess_sites_info[ $site['site_url'] ]['apiKey'] ?? '';
			$response    = wp_safe_remote_request(
				$request_url,
				array(
					'method'  => 'DELETE',
					'body'    => array(
						'username' => $username,
						'email'    => $email,
					),
					'headers' => array(
						'X-OneAccess-Token' => $api_key,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_log[] = array(
					'site_name' => $site['site_url'] ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Error deleting user from site %s.', 'oneaccess' ),
						esc_html( $site['site_url'] ?? '' )
					),
				);
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				$error_log[] = array(
					'site_name' => $site['site_url'] ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Failed to delete user from site %s.', 'oneaccess' ),
						esc_html( $site['site_url'] ?? '' )
					),
				);
				$error_log[] = $response;
				continue;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! $response_body['success'] ) {
				$error_log[] = array(
					'site_name' => $site['site_url'] ?? '',
					'message'   => $response_body['message'] ?? __( 'Failed to delete user from site.', 'oneaccess' ),
				);
				continue;
			}

			$response_data[] = array(
				'site'    => $site['site_url'],
				'message' => $response_body['message'] ?? __( 'User deleted successfully.', 'oneaccess' ),
			);

			// get oneaccess_new_users option.
			$oneaccess_new_users = get_option( Constants::ONEACCESS_NEW_USERS, array() );

			$updated_new_users = array();
			foreach ( $oneaccess_new_users as $key => $user ) {
				if ( $user['username'] === $username || $user['email'] === $email ) {
					$site_info = $user['sites'] ?? array();
					// Remove the site from user's sites array.
					$site_info = array_filter(
						$site_info,
						function ( $s ) use ( $site ) {
							return $s['site_url'] !== $site['site_url'];
						}
					);

					// if sites array is empty then skip adding this user to updated array.
					if ( empty( $site_info ) ) {
						continue;
					}

					$user['sites'] = array_values( $site_info );

				}
				$updated_new_users[] = $user;
			}

			// Update oneaccess_new_users option.
			update_option( Constants::ONEACCESS_NEW_USERS, $updated_new_users, false );
		}

		return new \WP_REST_Response(
			array(
				'success' => count( $error_log ) === 0,
				'message' => __( 'User deleted from sites successfully.', 'oneaccess' ),
				'data'    => array(
					'email'         => $email,
					'username'      => $username,
					'sites'         => $sites,
					'response_data' => $response_data,
					'error_log'     => $error_log,
				),
			),
			200
		);
	}

	/**
	 * Reject user profile request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response|
	 */
	public function reject_profile( \WP_REST_Request $request ): \WP_REST_Response {
		$user_email        = sanitize_email( $request->get_param( 'user_email' ) );
		$rejection_comment = sanitize_text_field( $request->get_param( 'rejection_comment' ) );
		$request_id        = absint( $request->get_param( 'request_id' ) );

		if ( empty( $user_email ) || empty( $rejection_comment ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'User email and rejection comment are required.', 'oneaccess' ),
				),
				400
			);
		}

		// Get the user by email.
		$user = get_user_by( 'email', $user_email );
		if ( ! $user ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'User not found.', 'oneaccess' ),
				),
				404
			);
		}

		// get user profile request data.
		$profile_requests_data = DB::get_profile_request_by_id( $request_id );

		if ( ! $profile_requests_data || 'pending' !== $profile_requests_data['status'] ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No pending profile request found for this user.', 'oneaccess' ),
				),
				404
			);
		}

		// Update the profile request status to rejected with comment.
		DB::reject_profile_request_by_id( $request_id, $rejection_comment );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Profile request rejected successfully.', 'oneaccess' ),
			),
			200
		);
	}

	/**
	 * Approve user profile request.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function approve_profile( \WP_REST_Request $request ): \WP_REST_Response {

		$user_id    = absint( $request->get_param( 'user_id' ) );
		$user_email = sanitize_email( $request->get_param( 'user_email' ) );
		$request_id = sanitize_text_field( $request->get_param( 'request_id' ) );

		if ( empty( $user_id ) || empty( $request_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'User ID and request ID are required.', 'oneaccess' ),
					'data'    => array(
						'user_id'    => $user_id,
						'request_id' => $request_id,
					),
				),
				400
			);
		}

		// Get the user by user_login.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'User not found.', 'oneaccess' ),
				),
				404
			);
		}

		// get user profile request data.
		$profile_requests_data = DB::get_profile_request_by_id( $request_id );

		if ( ! $profile_requests_data || 'pending' !== $profile_requests_data['status'] ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No pending profile request found for this user.', 'oneaccess' ),
				),
				404
			);
		}

		// Update the profile request status to approved.
		DB::approve_profile_request_by_id( $request_id );

		// Update user meta with the new profile data.
		if ( ! empty( $profile_requests_data['request_data'] ) ) {
			$user_data = $profile_requests_data['request_data']['data'] ?? array();

			foreach ( $user_data as $key => $value ) {
				wp_update_user(
					array(
						'ID' => $user->ID,
						$key => $value['new'],
					)
				);
			}

			$user_metadata = $profile_requests_data['request_data']['metadata'] ?? array();

			foreach ( $user_metadata as $meta_key => $meta_value ) {
				update_user_meta( $user->ID, $meta_key, $meta_value['new'] );
			}
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Profile request approved successfully.', 'oneaccess' ),
			),
			200
		);
	}

	/**
	 * Add user to multiple sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function add_user_to_sites( \WP_REST_Request $request ): \WP_REST_Response {
		$email     = sanitize_email( $request->get_param( 'email' ) );
		$username  = sanitize_text_field( $request->get_param( 'username' ) );
		$full_name = sanitize_text_field( $request->get_param( 'fullName' ) );
		$password  = sanitize_text_field( $request->get_param( 'password' ) );
		$sites     = $request->get_param( 'sites' );

		if ( empty( $email ) || empty( $username ) || empty( $full_name ) || empty( $password ) || empty( $sites ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Email, username, full name, password and sites are required.', 'oneaccess' ),
				),
				400
			);
		}

		// Validate sites.
		if ( ! is_array( $sites ) || empty( $sites ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid sites data.', 'oneaccess' ),
				),
				400
			);
		}

		$response_data        = array();
		$oneaccess_sites_info = $GLOBALS['oneaccess_sites'] ?? array();
		$error_log            = array();

		foreach ( $sites as $site_url ) {
			if ( ! isset( $oneaccess_sites_info[ $site_url['siteUrl'] ] ) ) {
				$error_log[] = array(
					'site_name' => $site_url['siteUrl'] ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Site %s not found in OneAccess sites.', 'oneaccess' ),
						esc_html( $site_url['siteUrl'] ?? '' )
					),
				);
				continue;
			}

			$api_key     = $oneaccess_sites_info[ $site_url['siteUrl'] ]['apiKey'] ?? '';
			$user_role   = $site_url['role'] ?? 'subscriber';
			$request_url = $site_url['siteUrl'] . '/wp-json/' . self::NAMESPACE . '/new-users';

			$response = wp_safe_remote_post(
				$request_url,
				array(
					'method'  => 'POST',
					'body'    => array(
						'username'  => $username,
						'email'     => $email,
						'password'  => $password,
						'full_name' => $full_name,
						'role'      => $user_role,
					),
					'headers' => array(
						'X-OneAccess-Token' => $api_key,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_log[] = array(
					'site_name' => $site_url['siteUrl'] ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Error adding user to site %s.', 'oneaccess' ),
						esc_html( $site_url['siteUrl'] ?? '' )
					),
				);
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 201 !== $response_code ) {
				$error_log[] = array(
					'site_name' => $site_url['siteUrl'] ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Failed to add user to site %s.', 'oneaccess' ),
						esc_html( $site_url['siteUrl'] ?? '' )
					),
				);
				continue;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! $response_body['success'] ) {
				$error_log[] = array(
					'site_name' => $site_url['siteUrl'] ?? '',
					'message'   => $response_body['message'] ?? __( 'Failed to add user to site.', 'oneaccess' ),
				);
				continue;
			}

			$response_data[] = array(
				'site'    => $oneaccess_sites_info[ $site_url['siteUrl'] ]['siteName'] ?? $site_url['siteUrl'],
				'status'  => 'success',
				'message' => __( 'User added successfully.', 'oneaccess' ),
			);

			// Update the user in options.
			$existing_users = get_option( Constants::ONEACCESS_NEW_USERS, array() );
			foreach ( $existing_users as $key => $user ) {
				if ( $user['username'] === $username || $user['email'] === $email ) {
					// Update the sites for the existing user.
					$existing_users[ $key ]['sites'][] = array(
						'site_url'  => $site_url['siteUrl'],
						'role'      => $user_role,
						'site_name' => $oneaccess_sites_info[ $site_url['siteUrl'] ]['siteName'] ?? '',
					);
					break;
				}
			}
			update_option( Constants::ONEACCESS_NEW_USERS, $existing_users, false );

		}

		return new \WP_REST_Response(
			array(
				'success' => count( $error_log ) === 0,
				'message' => __( 'User added to sites successfully.', 'oneaccess' ),
				'data'    => array(
					'email'         => $email,
					'username'      => $username,
					'full_name'     => $full_name,
					'sites'         => $sites,
					'response_data' => $response_data,
					'error_log'     => $error_log,
				),
			),
			200
		);
	}

	/**
	 * Update user.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_user( \WP_REST_Request $request ): \WP_REST_Response {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$username = sanitize_text_field( $request->get_param( 'username' ) );
		$role     = sanitize_text_field( $request->get_param( 'role' ) );

		if ( empty( $email ) || empty( $username ) || empty( $role ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Email, username and role are required.', 'oneaccess' ),
				),
				400
			);
		}

		// Update user role.
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'User not found.', 'oneaccess' ),
				),
				404
			);
		}

		// get all user roles.
		$all_roles = wp_roles()->roles;
		if ( ! array_key_exists( $role, $all_roles ) ) {
			$role = 'subscriber';
		}

		// Update user role.
		wp_update_user(
			array(
				'ID'   => $user->ID,
				'role' => $role,
			)
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'User role updated successfully.', 'oneaccess' ),
			),
			200
		);
	}

	/**
	 * Update user roles for multiple sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function update_user_roles_for_sites( \WP_REST_Request $request ): \WP_REST_Response {
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$username = sanitize_text_field( $request->get_param( 'username' ) );
		$roles    = $request->get_param( 'roles' );

		if ( empty( $email ) || empty( $username ) || empty( $roles ) || ! is_array( $roles ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Email, username and roles are required.', 'oneaccess' ),
				),
				400
			);
		}

		$response_data        = array();
		$oneaccess_sites_info = $GLOBALS['oneaccess_sites'] ?? array();
		$error_log            = array();

		foreach ( $roles as $key => $value ) {
			$site_url = $oneaccess_sites_info[ $key ]['siteUrl'] ?? '';
			$api_key  = $oneaccess_sites_info[ $key ]['apiKey'] ?? '';
			$new_role = $value;

			$request_url = $site_url . '/wp-json/' . self::NAMESPACE . '/update-user';

			$response = wp_safe_remote_post(
				$request_url,
				array(
					'method'  => 'POST',
					'body'    => array(
						'username' => $username,
						'email'    => $email,
						'role'     => $new_role,
					),
					'headers' => array(
						'X-OneAccess-Token' => $api_key,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_log[] = array(
					'site_name' => $site_url ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Error updating user role on site %s.', 'oneaccess' ),
						esc_html( $site_url ?? '' )
					),
				);
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $response_code ) {
				$error_log[] = array(
					'site_name' => $site_url ?? '',
					'message'   => sprintf(
						/* translators: %s is the site URL */
						__( 'Failed to update user role on site %s.', 'oneaccess' ),
						esc_html( $site_url ?? '' )
					),
				);
				continue;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! $response_body['success'] ) {
				$error_log[] = array(
					'site_name' => $site_url ?? '',
					'message'   => $response_body['message'] ?? __( 'Failed to update user role on site.', 'oneaccess' ),
				);
				continue;
			}

			$response_data[] = array(
				'site'    => $oneaccess_sites_info[ $key ]['siteName'] ?? $site_url,
				'status'  => 'success',
				'message' => __( 'User role updated successfully.', 'oneaccess' ),
			);

			// update the user role into options.
			$existing_users = get_option( Constants::ONEACCESS_NEW_USERS, array() );

			foreach ( $existing_users as $user_key => $user ) {
				if ( $user['username'] === $username || $user['email'] === $email ) {
					$sites = $user['sites'] ?? array();
					foreach ( $sites as $site_key => $site ) {
						if ( $site['site_url'] === $site_url ) {
							// Update the original array using references.
							$existing_users[ $user_key ]['sites'][ $site_key ]['role'] = $new_role;
						}
					}
				}
			}

			update_option( Constants::ONEACCESS_NEW_USERS, $existing_users, false );

		}

		return new \WP_REST_Response(
			array(
				'success' => count( $error_log ) === 0,
				'message' => __( 'User roles updated successfully.', 'oneaccess' ),
				'data'    => array(
					'email'         => $email,
					'username'      => $username,
					'roles'         => $roles,
					'response_data' => $response_data,
					'error_log'     => $error_log,
				),
			),
			200
		);
	}

	/**
	 * Create a new user.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function create_user( \WP_REST_Request $request ): \WP_REST_Response {
		$username  = sanitize_user( $request->get_param( 'username' ) );
		$email     = sanitize_email( $request->get_param( 'email' ) );
		$password  = sanitize_text_field( $request->get_param( 'password' ) );
		$full_name = sanitize_text_field( $request->get_param( 'full_name' ) );
		$role      = sanitize_text_field( $request->get_param( 'role' ) );

		if ( empty( $username ) || empty( $email ) || empty( $password ) || empty( $full_name ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Username, email, full name and password are required.', 'oneaccess' ),
				),
				400
			);
		}

		if ( ! is_email( $email ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid email address.', 'oneaccess' ),
				),
				400
			);
		}

		// Check if user already exists.
		if ( username_exists( $username ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Username already exists, please user different username.', 'oneaccess' ),
				),
				400
			);
		}

		// Check if email already exists.
		if ( email_exists( $email ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Email already exists, please user different email.', 'oneaccess' ),
				),
				400
			);
		}

		// Create the user.
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $user_id->get_error_message(),
				),
				500
			);
		}

		// get all user roles.
		$all_roles = wp_roles()->roles;
		if ( ! array_key_exists( $role, $all_roles ) ) {
			$role = 'subscriber';
		}

		// Set the user's full name and role.
		wp_update_user(
			array(
				'ID'            => $user_id,
				'display_name'  => $full_name ?? $username,
				'user_nicename' => sanitize_title( $full_name ?? $username ),
				'first_name'    => explode( ' ', $full_name )[0] ?? '',
				'last_name'     => explode( ' ', $full_name )[1] ?? '',
				'role'          => $role ?? 'subscriber',
			)
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'User created successfully.', 'oneaccess' ),
				'data'    => array(
					'user_id'   => $user_id,
					'username'  => $username,
					'email'     => $email,
					'full_name' => $full_name,
					'role'      => $role,
				),
			),
			201
		);
	}

	/**
	 * Get users.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_users( WP_REST_Request $request ): \WP_REST_Response {

		global $wpdb;

		$paged        = intval( $request->get_param( 'paged' ) ) ?: 1;
		$per_page     = intval( $request->get_param( 'per_page' ) ) ?: 20;
		$search_query = sanitize_text_field( $request->get_param( 'search_query' ) ) ?? '';
		$role         = sanitize_text_field( $request->get_param( 'role' ) ) ?? '';
		$site         = sanitize_text_field( $request->get_param( 'site' ) ) ?? '';

		// Calculate offset
		$offset = ( $paged - 1 ) * $per_page;

		// Table name
		$table_name = $wpdb->prefix . Constants::ONEACCESS_DEDUPLICATED_USERS_TABLE;

		// Build WHERE conditions
		$where_conditions = array();
		$prepare_values   = array();

		// Search query - search in email, first_name, last_name
		if ( ! empty( $search_query ) ) {
			$where_conditions[] = '(email LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
			$search_like        = '%' . $wpdb->esc_like( $search_query ) . '%';
			$prepare_values[]   = $search_like;
			$prepare_values[]   = $search_like;
			$prepare_values[]   = $search_like;
		}

		// Role filter - search within sites_info JSON
		if ( ! empty( $role ) ) {
			$where_conditions[] = "JSON_SEARCH(sites_info, 'one', %s, NULL, '$[*].roles[*]') IS NOT NULL";
			$prepare_values[]   = $role;
		}

		// Site filter - search by site_url or site_name in sites_info JSON
		if ( ! empty( $site ) ) {
			$where_conditions[] = "(JSON_SEARCH(sites_info, 'one', %s, NULL, '$[*].site_url') IS NOT NULL OR JSON_SEARCH(sites_info, 'one', %s, NULL, '$[*].site_name') IS NOT NULL)";
			$site_like          = '%' . $wpdb->esc_like( $site ) . '%';
			$prepare_values[]   = $site_like;
			$prepare_values[]   = $site_like;
		}

		// Combine WHERE conditions
		$where_clause = '';
		if ( ! empty( $where_conditions ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
		}

		// Build count query
		$count_query = "SELECT COUNT(*) FROM $table_name $where_clause";

		// Prepare count query if there are values
		if ( ! empty( $prepare_values ) ) {
			$count_query = $wpdb->prepare( $count_query, $prepare_values );
		}

		$total_users = (int) $wpdb->get_var( $count_query );

		// Build main query
		$query = "SELECT * FROM $table_name $where_clause ORDER BY updated_at DESC LIMIT %d OFFSET %d";

		// Add pagination values to prepare array
		$prepare_values[] = $per_page;
		$prepare_values[] = $offset;

		// Prepare and execute query
		$prepared_query = $wpdb->prepare( $query, $prepare_values );
		$users          = $wpdb->get_results( $prepared_query );

		// Process users data - decode sites_info JSON
		$processed_users = array();
		foreach ( $users as $user ) {
			$user_data = array(
				'id'         => $user->id,
				'email'      => $user->email,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
				'sites_info' => json_decode( $user->sites_info, true ),
				'created_at' => $user->created_at,
				'updated_at' => $user->updated_at,
			);

			// Apply post-processing filters if needed
			// Filter by role in PHP if JSON_SEARCH didn't work properly
			if ( ! empty( $role ) ) {
				$has_role = false;
				foreach ( $user_data['sites_info'] as $site_info ) {
					if ( isset( $site_info['roles'] ) && in_array( $role, $site_info['roles'], true ) ) {
						$has_role = true;
						break;
					}
				}
				if ( ! $has_role ) {
					continue; // Skip this user
				}
			}

			$processed_users[] = $user_data;
		}

		// Calculate total pages
		$total_pages = ceil( $total_users / $per_page );

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'users'      => $processed_users,
				'pagination' => array(
					'total_users'  => $total_users,
					'total_pages'  => $total_pages,
					'current_page' => $paged,
					'per_page'     => $per_page,
					'has_more'     => $paged < $total_pages,
				),
				'filters'    => array(
					'search_query' => $search_query,
					'role'         => $role,
					'site'         => $site,
					'paged'        => $paged,
					'per_page'     => $per_page,
				),
			),
			200
		);
	}

	/**
	 * Generate a strong password.
	 *
	 * @return \WP_REST_Response
	 */
	public function generate_strong_password(): \WP_REST_Response {
		$password = wp_generate_password( 32, true, true );

		// at random position add 0-9 any digit to make sure password always contains a digit.
		$random_position              = wp_rand( 0, 31 );
		$password[ $random_position ] = wp_rand( 0, 9 );

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'password' => $password,
			),
			200
		);
	}

	/**
	 * Create users for multiple sites.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function create_users_for_sites( \WP_REST_Request $request ): \WP_REST_Response {
		$userdata = $request->get_param( 'userdata' );
		$sites    = $request->get_param( 'sites' );
		if ( empty( $userdata ) || empty( $sites ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'User data and sites are required.', 'oneaccess' ),
				),
				400
			);
		}

		// validate userdate.
		if ( ! is_array( $userdata ) || empty( $userdata ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid user data.', 'oneaccess' ),
				),
				400
			);
		}

		// validate sites.
		if ( ! is_array( $sites ) || empty( $sites ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid sites data.', 'oneaccess' ),
				),
				400
			);
		}

		// validate userinfo.
		if ( ! isset( $userdata['username'] ) || empty( $userdata['username'] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Username is required.', 'oneaccess' ),
				),
				400
			);
		}
		if ( ! isset( $userdata['email'] ) || empty( $userdata['email'] ) || ! is_email( $userdata['email'] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Please enter valid email id.', 'oneaccess' ),
				),
				400
			);
		}
		if ( ! isset( $userdata['password'] ) || empty( $userdata['password'] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Password is required.', 'oneaccess' ),
				),
				400
			);
		}
		if ( ! isset( $userdata['fullName'] ) || empty( $userdata['fullName'] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Full name is required.', 'oneaccess' ),
				),
				400
			);
		}

		$oneaccess_sites_info = $GLOBALS['oneaccess_sites'] ?? array();
		$response_data        = array();
		$error_log            = array();
		foreach ( $sites as $site ) {
			$site_url  = $site['siteUrl'] ?? '';
			$api_key   = $oneaccess_sites_info[ $site_url ]['apiKey'] ?? '';
			$site_name = $oneaccess_sites_info[ $site_url ]['siteName'] ?? '';
			if ( empty( $api_key ) ) {
				$error_log[] = array(
					'site_name' => $site_name,
					'message'   => sprintf(
						/* translators: %s is the site name */
						__( 'API key not found for site %s.', 'oneaccess' ),
						$site_name
					),
				);
				continue;
			}
			$request_url = $oneaccess_sites_info[ $site_url ]['siteUrl'] . '/wp-json/' . self::NAMESPACE . '/new-users';

			$response = wp_safe_remote_post(
				$request_url,
				array(
					'method'  => 'POST',
					'body'    => array(
						'username'  => $userdata['username'],
						'email'     => $userdata['email'],
						'password'  => $userdata['password'],
						'full_name' => $userdata['fullName'],
						'role'      => $userdata['role'] ?? 'subscriber',
					),
					'headers' => array(
						'X-OneAccess-Token' => $api_key,
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_log[] = array(
					'site_name' => $site_name,
					'message'   => sprintf(
						/* translators: %s is the site name */
						__( 'Error creating user on site %s.', 'oneaccess' ),
						$site_name
					),
					'response'  => $response,
				);
				continue;
			}

			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! $response_body['success'] ) {
				$error_log[] = array(
					'site_name'     => $site_name,
					'message'       => $response_body['message'] ?? sprintf(
						/* translators: %s is the site name */
						__( 'Failed to create user on site %s.', 'oneaccess' ),
						$site_name
					),
					'response'      => $response,
					'response_body' => $response_body,
				);
				continue;
			}

			$response_data[] = array(
				'site'    => $site_name,
				'status'  => 'success',
				'message' => __( 'User created successfully.', 'oneaccess' ),
			);

			// store created user into deduplicated users table.
			$error_log[] = DB::insert_or_update_deduplicated_user(
				$userdata['email'],
				$userdata['fullName'],
				array(
					'site_url'  => $site_url,
					'site_name' => $site_name,
					'roles'     => array( $userdata['role'] ?? 'subscriber' ),
					'user_id'   => $response_body['data']['user_id'] ?? 0,
				)
			);

		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'User creation process completed.', 'oneaccess' ),
				'data'    => array(
					'response_data' => $response_data,
					'error_log'     => $error_log,
				),
			),
			200
		);
	}
}
