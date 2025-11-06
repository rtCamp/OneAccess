<?php
/**
 * This will have code related to action scheduler required API.
 *
 * @package OneAccess
 */

namespace OneAccess\REST;

use OneAccess\Plugin_Configs\Constants;
use OneAccess\Traits\Singleton;
use OneAccess\Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class Actions
 */
class Actions {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'oneaccess/v1';

	/**
	 * Batch size for user processing
	 *
	 * @var int
	 */
	private const BATCH_SIZE = 10;

	/**
	 * Results limit per request
	 *
	 * @var int
	 */
	private const RESULTS_LIMIT = 20;

	/**
	 * Use Singleton Trait
	 */
	use Singleton;

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		/**
		 * Route to add users to oneaccess_deduplicated_users table.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/add-deduplicated-users',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_deduplicated_users' ),
				'permission_callback' => 'oneaccess_brand_site_to_governing_site_request_permission_check',
				'args'                => array(
					'users' => array(
						'required'          => true,
						'type'              => 'array',
						'description'       => __( 'Array of user data to be added as deduplicated users.', 'oneaccess' ),
						'validate_callback' => array( $this, 'validate_users_array' ),
					),
				),
			)
		);

		/**
		 * Route to send users in batch for deduplication.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/send-users-for-deduplication',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_users_for_deduplication' ),
				'permission_callback' => 'oneaccess_validate_api_key',
			)
		);

		/**
		 * Shared args for profile requests endpoints.
		 */
		$profile_request_args = array(
			'site'         => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Name of the brand site to get profile requests from.', 'oneaccess' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'       => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Status of the profile requests to filter by (e.g., pending, approved, rejected).', 'oneaccess' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'search_query' => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Search query to filter profile requests by user email or name.', 'oneaccess' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'cursor'       => array(
				'required'          => false,
				'type'              => 'string',
				'description'       => __( 'Cursor for pagination.', 'oneaccess' ),
				'sanitize_callback' => 'absint',
			),
		);

		/**
		 * Route to get aggregated profile requests from all brand sites
		 */
		register_rest_route(
			self::NAMESPACE,
			'/get-profile-requests',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_profile_requests' ),
				'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
				'args'                => $profile_request_args,
			)
		);

		/**
		 * Route to get profile requests from current brand site.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/get-brand-site-profile-requests',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_brand_site_profile_requests' ),
				'permission_callback' => 'oneaccess_validate_api_key',
				'args'                => $profile_request_args,
			)
		);

		/**
		 * Route to clean up disconnected sites users from deduplicated users table
		 */
		register_rest_route(
			self::NAMESPACE,
			'/cleanup-deduplicated-users',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cleanup_deduplicated_users' ),
				'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
			),
		);

		/**
		 * Route to rebuild deduplicated users index
		 */
		register_rest_route(
			self::NAMESPACE,
			'/rebuild-deduplicated-users-index',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rebuild_deduplicated_users_index' ),
				'permission_callback' => array( Basic_Options::class, 'check_user_permissions' ),
			),
		);

		/**
		 * Route to rebuild index for brand sites
		 */
		register_rest_route(
			self::NAMESPACE,
			'/rebuild-brand-sites-index',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rebuild_brand_sites_index' ),
				'permission_callback' => 'oneaccess_validate_api_key',
			),
		);
	}

	/**
	 * Validate users array
	 *
	 * @param mixed $value The value to validate.
	 * @return bool
	 */
	public function validate_users_array( $value ): bool {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( $value as $user ) {
			if ( ! is_array( $user ) || empty( $user['email'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize user data
	 *
	 * @param array $user Raw user data.
	 *
	 * @return array|null Sanitized user data or null if invalid.
	 */
	private function sanitize_user_data( array $user ): ?array {

		// Skip invalid users.
		if ( empty( $user['email'] ) ) {
			return null;
		}

		// Get roles with proper null checking.
		$user_roles = array();
		if ( isset( $user['roles'] ) && is_array( $user['roles'] ) ) {
			$user_roles = array_map( 'sanitize_text_field', $user['roles'] );
		}

		$sanitized_user = array(
			'user_id'    => isset( $user['user_id'] ) ? absint( $user['user_id'] ) : 0,
			'email'      => sanitize_email( $user['email'] ?? '' ),
			'first_name' => sanitize_text_field( $user['first_name'] ?? '' ),
			'last_name'  => sanitize_text_field( $user['last_name'] ?? '' ),
			'roles'      => $user_roles,
			'site_name'  => sanitize_text_field( $user['site_name'] ?? '' ),
			'site_url'   => trailingslashit( esc_url_raw( $user['site_url'] ?? '' ) ),
		);

		// Only return if email is valid.
		if ( empty( $sanitized_user['email'] ) || ! is_email( $sanitized_user['email'] ) ) {
			return null;
		}

		return $sanitized_user;
	}

	/**
	 * Callback to add deduplicated users.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function add_deduplicated_users( WP_REST_Request $request ): WP_REST_Response {

		$users           = $request->get_param( 'users' );
		$sanitized_users = array();

		foreach ( $users as $user ) {
			$sanitized_user = $this->sanitize_user_data( $user );
			if ( null !== $sanitized_user ) {
				$sanitized_users[] = $sanitized_user;
			}
		}

		$action_id = null;

		// Schedule governing site action to add deduplicated users.
		if ( ! empty( $sanitized_users ) && function_exists( 'as_enqueue_async_action' ) ) {
			$action_id = as_enqueue_async_action(
				'oneaccess_add_deduplicated_users',
				array( 'users' => $sanitized_users ),
				'oneaccess',
				false,
				5
			);
		}

		return new WP_REST_Response(
			array(
				'success'         => ! empty( $sanitized_users ),
				'action_id'       => $action_id,
				'users_processed' => count( $sanitized_users ),
			),
			200
		);
	}

	/**
	 * Prepare user data for batch processing
	 *
	 * @param \WP_User $user User object.
	 * @param string   $site_name Site name.
	 * @param string   $site_url Site URL.
	 *
	 * @return array
	 */
	private function prepare_user_data( \WP_User $user, string $site_name, string $site_url ): array {
		return array(
			'user_id'    => $user->ID,
			'email'      => $user->user_email,
			'first_name' => get_user_meta( $user->ID, 'first_name', true ),
			'last_name'  => get_user_meta( $user->ID, 'last_name', true ),
			'roles'      => $user->roles,
			'site_name'  => $site_name,
			'site_url'   => $site_url,
		);
	}

	/**
	 * Send batch of users to governing site
	 *
	 * @param array  $users_batch Users to send.
	 * @param string $governing_site_url Governing site URL.
	 *
	 * @return array|WP_Error Response or error.
	 */
	private function send_users_batch( array $users_batch, string $governing_site_url ): array|WP_Error {
		return wp_safe_remote_post(
			trailingslashit( esc_url_raw( $governing_site_url ) ) . 'wp-json/' . self::NAMESPACE . '/add-deduplicated-users',
			array(
				'body'    => wp_json_encode( array( 'users' => $users_batch ) ),
				'headers' => array(
					'Content-Type'      => 'application/json',
					'X-OneAccess-Token' => get_option( Constants::ONEACCESS_API_KEY, '' ),
				),
				'timeout' => 30,
			)
		);
	}

	/**
	 * Callback to send users in batch for deduplication.
	 *
	 * @return WP_REST_Response
	 */
	public function send_users_for_deduplication(): WP_REST_Response {

		$paged       = 1;
		$users_batch = array();
		$site_name   = get_option( 'blogname' );
		$site_url    = get_site_url();

		$governing_site_url = Utils::get_governing_site_url();
		$total_users_sent   = 0;
		$errors             = array();
		$responses          = array();

		while ( true ) {
			$user_query = new \WP_User_Query(
				array(
					'number' => self::BATCH_SIZE,
					'paged'  => $paged,
				)
			);

			$users = $user_query->get_results();

			if ( empty( $users ) ) {
				break;
			}

			foreach ( $users as $user ) {
				$users_batch[] = $this->prepare_user_data( $user, $site_name, $site_url );
			}

			// Send the batch to add-deduplicated-users endpoint.
			$response = $this->send_users_batch( $users_batch, $governing_site_url );

			if ( is_wp_error( $response ) ) {
				$errors[] = array(
					'batch' => $paged,
					'error' => $response->get_error_message(),
				);
			} else {
				$responses[]       = array(
					'batch' => $paged,
					'code'  => wp_remote_retrieve_response_code( $response ),
				);
				$total_users_sent += count( $users_batch );
			}

			// Reset batch for next iteration.
			$users_batch = array();
			++$paged;
		}

		return new WP_REST_Response(
			array(
				'success'            => empty( $errors ),
				'total_users_sent'   => $total_users_sent,
				'total_batches_sent' => $paged - 1,
				'batch_size'         => self::BATCH_SIZE,
				'errors'             => $errors,
				'responses'          => $responses,
			),
			empty( $errors ) ? 200 : 207 // 207 Multi-Status if there are errors.
		);
	}

	/**
	 * Make remote request to brand site
	 *
	 * @param string $site_url Site URL.
	 * @param string $api_key API key.
	 * @param array  $query_params Query parameters.
	 *
	 * @return array|WP_Error Response data or error.
	 */
	private function make_brand_site_request( string $site_url, string $api_key, array $query_params ) {

		$request_url = trailingslashit( $site_url ) . 'wp-json/' . self::NAMESPACE . '/get-brand-site-profile-requests?' . http_build_query( $query_params );

		$args = array(
			'headers' => array(
				'X-OneAccess-Token' => $api_key,
			),
			'timeout' => 15,
		);

		$response = wp_safe_remote_get( $request_url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error(
				'invalid_response',
				sprintf( 'Site %s returned status code %d', $site_url, $response_code ),
				array( 'status' => $response_code )
			);
		}

		$response_body = wp_remote_retrieve_body( $response );
		$decoded_body  = json_decode( $response_body, true );

		if ( ! isset( $decoded_body['profile_requests'] ) || ! is_array( $decoded_body['profile_requests'] ) ) {
			return new WP_Error(
				'invalid_data',
				sprintf(
						/* translators: 1: site URL */
					'Invalid response format from site: %s',
					$site_url
				),
			);
		}

		return $decoded_body;
	}

	/**
	 * Get users profile request data from all brand sites.
	 *
	 * @param WP_REST_Request $request Request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_profile_requests( WP_REST_Request $request ): WP_REST_Response {

		// Get and sanitize parameters.
		$site = $request->get_param( 'site' );
		$site = ! empty( $site ) ? sanitize_text_field( $site ) : '';

		$status = $request->get_param( 'status' );
		$status = ! empty( $status ) ? sanitize_text_field( $status ) : '';

		$search_query = $request->get_param( 'search_query' );
		$search_query = ! empty( $search_query ) ? sanitize_text_field( $search_query ) : '';

		$cursor = $request->get_param( 'cursor' );
		$offset = ! empty( $cursor ) ? absint( $cursor ) : 0;

		$full_sites             = $GLOBALS['oneaccess_sites'] ?? array();
		$oneaccess_sites        = $full_sites; // Start with full list.
		$processed_sites        = array();
		$error_log              = array();
		$all_profile_requests   = array();
		$site_wise_total_counts = array();

		// Compute available sites from full list (always all sites for dropdown).
		$available_sites = array_map(
			function ( $site_config ) {
				$site_name = $site_config['siteName'] ?? __( 'Unknown Site', 'oneaccess' );
				return array(
					'label' => $site_name,
					'value' => $site_name,
				);
			},
			$full_sites
		);

		// Compute total_pending_count (global, all sites, ignoring current filters except status='pending').
		$total_pending_count = 0;
		$temp_processed      = array();
		foreach ( $full_sites as $site_config ) {
			$site_url = $site_config['siteUrl'] ?? '';
			$api_key  = $site_config['apiKey'] ?? '';

			if ( empty( $site_url ) || in_array( $site_url, $temp_processed, true ) ) {
				if ( ! empty( $site_url ) ) {
					$error_log[] =
						sprintf(
							/* translators: 1: site URL */
							'Duplicate site URL skipped for pending count: %s',
							$site_url
						);
				}
				continue;
			}

			$temp_processed[] = $site_url;

			// Quick call: status='pending', no search, cursor=0 (just for total_count).
			$pending_data = $this->make_brand_site_request(
				$site_url,
				$api_key,
				array(
					'status'       => 'pending',
					'search_query' => '',
					'cursor'       => 0,
				)
			);

			if ( is_wp_error( $pending_data ) ) {
				$error_log[] = sprintf( 'Failed to fetch pending count from %s: %s', $site_url, $pending_data->get_error_message() );
				continue;
			}

			$total_pending_count += intval( $pending_data['pagination']['total_count'] ?? 0 );
		}

		// Apply site filter for main query.
		if ( ! empty( $site ) ) {
			$oneaccess_sites = array_filter(
				$full_sites,
				function ( $s ) use ( $site ) {
					return ( ( $s['siteName'] ?? '' ) === $site );
				}
			);
		}

		// Fetch FULL results for current filter from each site.
		foreach ( $oneaccess_sites as $site_config ) {
			$site_url  = $site_config['siteUrl'] ?? '';
			$site_name = $site_config['siteName'] ?? __( 'Unknown Site', 'oneaccess' );
			$api_key   = $site_config['apiKey'] ?? '';

			// Skip duplicate or invalid sites.
			if ( empty( $site_url ) || in_array( $site_url, $processed_sites, true ) ) {
				if ( ! empty( $site_url ) ) {
					$error_log[] =
						sprintf(
							/* translators: 1: site URL */
							'Duplicate site URL skipped for profile requests: %s',
							$site_url
						);
				}
				continue;
			}

			$processed_sites[] = $site_url;

			// Paginate through ALL pages for this site.
			$site_requests    = array();
			$current_cursor   = 0;
			$site_total_count = 0;

			do {
				$query_params = array(
					'status'       => $status,
					'search_query' => $search_query,
					'cursor'       => $current_cursor,
				);

				$site_data = $this->make_brand_site_request( $site_url, $api_key, $query_params );

				if ( is_wp_error( $site_data ) ) {
					$error_log[] =
						sprintf(
							/* translators: 1: site URL, 2: error message */
							'Failed to fetch from %s: %s',
							$site_url,
							$site_data->get_error_message()
						);
					break;
				}

				// Add site_name to each request.
				foreach ( $site_data['profile_requests'] as &$req ) {
					$req['site_name'] = $site_name;
				}

				$site_requests = array_merge( $site_requests, $site_data['profile_requests'] );

				$pagination     = $site_data['pagination'];
				$current_cursor = $pagination['next_cursor'] ?? null;
				$has_more       = $pagination['has_more'] ?? false;

				// Set total on first fetch.
				if ( 0 === $site_total_count ) {
					$site_total_count = intval( $pagination['total_count'] ?? 0 );
				}
			} while ( $has_more && null !== $current_cursor );

			$site_wise_total_counts[ $site_name ] = $site_total_count;
			$all_profile_requests                 = array_merge( $all_profile_requests, $site_requests );
		}

		// Sort ALL results by created_at DESC.
		usort(
			$all_profile_requests,
			function ( $a, $b ) {
				$time_a = isset( $a['created_at'] ) ? strtotime( $a['created_at'] ) : 0;
				$time_b = isset( $b['created_at'] ) ? strtotime( $b['created_at'] ) : 0;
				return $time_b - $time_a;
			}
		);

		// Total count from full aggregate.
		$total_count = count( $all_profile_requests );

		// Apply pagination AFTER sorting.
		$paginated_results = array_slice( $all_profile_requests, $offset, self::RESULTS_LIMIT );

		// Calculate pagination info.
		$has_more     = $total_count > ( $offset + self::RESULTS_LIMIT );
		$next_cursor  = $has_more ? $offset + self::RESULTS_LIMIT : null;
		$total_pages  = (int) ceil( $total_count / self::RESULTS_LIMIT );
		$current_page = (int) floor( $offset / self::RESULTS_LIMIT ) + 1;

		// Count results per site in current page.
		$site_result_counts = array();
		foreach ( $paginated_results as $req ) {
			$req_site_name                        = $req['site_name'] ?? __( 'Unknown Site', 'oneaccess' );
			$site_result_counts[ $req_site_name ] = ( $site_result_counts[ $req_site_name ] ?? 0 ) + 1;
		}

		return new WP_REST_Response(
			array(
				'success'                => true,
				'profile_requests'       => $paginated_results,
				'pagination'             => array(
					'total_count'   => $total_count,
					'current_count' => count( $paginated_results ),
					'offset'        => $offset,
					'limit'         => self::RESULTS_LIMIT,
					'has_more'      => $has_more,
					'next_cursor'   => $next_cursor,
					'total_pages'   => $total_pages,
					'current_page'  => $current_page,
				),
				'total_pending_count'    => $total_pending_count,
				'site_result_counts'     => $site_result_counts,
				'site_wise_total_counts' => $site_wise_total_counts,
				'sites'                  => $available_sites,
				'sites_queried'          => count( $processed_sites ),
				'errors'                 => $error_log,
			),
			200
		);
	}

	/**
	 * Build WHERE clause for profile requests query
	 *
	 * @param string $status Status filter.
	 * @param string $search_query Search query.
	 * @return array Array with 'sql' and 'params' keys.
	 */
	private function build_profile_requests_where_clause( string $status, string $search_query ): array {

		global $wpdb;

		$where_clauses = array( '1=1' );
		$query_params  = array();

		if ( ! empty( $status ) ) {
			$where_clauses[] = 'status = %s';
			$query_params[]  = $status;
		}

		if ( ! empty( $search_query ) ) {
			$where_clauses[] = 'request_data LIKE %s';
			$query_params[]  = '%' . $wpdb->esc_like( $search_query ) . '%';
		}

		return array(
			'sql'    => implode( ' AND ', $where_clauses ),
			'params' => $query_params,
		);
	}

	/**
	 * Get brand site profile requests from current site database.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_brand_site_profile_requests( WP_REST_Request $request ): WP_REST_Response {

		global $wpdb;

		// Sanitize and validate input parameters.
		$status = $request->get_param( 'status' );
		$status = ! empty( $status ) ? sanitize_text_field( $status ) : '';

		$search_query = $request->get_param( 'search_query' );
		$search_query = ! empty( $search_query ) ? sanitize_text_field( $search_query ) : '';

		$cursor = $request->get_param( 'cursor' );
		$offset = ! empty( $cursor ) ? absint( $cursor ) : 0;

		$table_name = $wpdb->prefix . Constants::ONEACCESS_PROFILE_REQUESTS_TABLE;

		// Build WHERE clause.
		$where_clause = $this->build_profile_requests_where_clause( $status, $search_query );

		// Get total count for pagination.
		$count_query = sprintf(
			'SELECT COUNT(*) FROM `%s` WHERE %s',
			$table_name,
			$where_clause['sql']
		);

		// Then prepare with params.
		$count_query = $wpdb->prepare( $count_query, $where_clause['params'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- parameters are prepared.
		$total_count = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- parameters are already prepared.

		// Prepare and execute main query with ORDER BY created_at DESC.
		$params = array_merge( $where_clause['params'], array( self::RESULTS_LIMIT, $offset ) );
		$query  = $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- parameters are correct used spread on array.
			"SELECT * FROM `{$table_name}` WHERE {$where_clause['sql']} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			...$params
		);

		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query is already prepared.

		// Decode request_data JSON field.
		foreach ( $results as &$result ) {
			if ( isset( $result['request_data'] ) && is_string( $result['request_data'] ) ) {
				$result['request_data'] = json_decode( $result['request_data'], true );
			}
		}

		// Calculate pagination info.
		$has_more    = $total_count > ( $offset + self::RESULTS_LIMIT );
		$next_cursor = $has_more ? $offset + self::RESULTS_LIMIT : null;

		return new WP_REST_Response(
			array(
				'success'          => true,
				'profile_requests' => $results,
				'pagination'       => array(
					'total_count'   => $total_count,
					'current_count' => count( $results ),
					'offset'        => $offset,
					'limit'         => self::RESULTS_LIMIT,
					'has_more'      => $has_more,
					'next_cursor'   => $next_cursor,
				),
			),
			200
		);
	}

	/**
	 * Send single user to governing site for deduplication.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 */
	public function send_single_user_for_deduplication( int $user_id ): void {

		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return;
		}

		$site_name = get_option( 'blogname' );
		$site_url  = get_site_url();
		$user_data = $this->prepare_user_data( $user, $site_name, $site_url );

		$governing_site_url = Utils::get_governing_site_url();
		$this->send_users_batch( array( $user_data ), $governing_site_url );
	}

	/**
	 * Cleanup deduplicated users from disconnected sites.
	 *
	 * @return WP_REST_Response
	 */
	public function cleanup_deduplicated_users(): WP_REST_Response {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_DEDUPLICATED_USERS_TABLE;

		// Get global oneaccess_sites variable.
		$oneaccess_sites     = $GLOBALS['oneaccess_sites'] ?? array();
		$processed_sites     = array();
		$connected_site_urls = array();

		// Build list of connected site URLs.
		foreach ( $oneaccess_sites as $site_config ) {

			// Skip duplicate or invalid sites.
			if ( empty( $site_config['siteUrl'] ) || in_array( $site_config['siteUrl'], $processed_sites, true ) ) {
				if ( ! empty( $site_config['siteUrl'] ) ) {
					$processed_sites[] = $site_config['siteUrl'];
				}
				continue;
			}

			if ( ! empty( $site_config['siteUrl'] ) ) {
				$connected_site_urls[] = trailingslashit( esc_url_raw( $site_config['siteUrl'] ) );
			}
		}

		// If no connected sites, we can't proceed safely.
		if ( empty( $connected_site_urls ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No connected sites found in global configuration.', 'oneaccess' ),
				),
				400
			);
		}

		// Batch processing configuration.
		$batch_size    = 100; // Process 100 users at a time.
		$offset        = 0;
		$total_updated = 0;
		$total_deleted = 0;

		do {
			// Get batch of users.
			$users = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, sites_info FROM {$table_name} ORDER BY id LIMIT %d OFFSET %d",
					$batch_size,
					$offset
				),
				ARRAY_A
			);

			if ( empty( $users ) ) {
				break;
			}

			$updated_count = 0;
			$deleted_count = 0;

			foreach ( $users as $user ) {
				$user_id    = $user['id'];
				$sites_info = json_decode( $user['sites_info'], true );

				if ( ! is_array( $sites_info ) ) {
					continue;
				}

				// Filter sites_info to keep only connected sites.
				$filtered_sites = array_filter(
					$sites_info,
					function ( $site ) use ( $connected_site_urls ): bool {
						$site_url = trailingslashit( $site['site_url'] ?? '' );
						return in_array( $site_url, $connected_site_urls, true );
					}
				);

				// Re-index array to avoid gaps in JSON array.
				$filtered_sites = array_values( $filtered_sites );

				// If no sites remain, delete the user row.
				if ( empty( $filtered_sites ) ) {
					$wpdb->delete(
						$table_name,
						array( 'id' => $user_id ),
						array( '%d' )
					);
					++$deleted_count;
				} elseif ( count( $filtered_sites ) !== count( $sites_info ) ) {
					// Sites were removed, update the row.
					$wpdb->update(
						$table_name,
						array(
							'sites_info' => wp_json_encode( $filtered_sites ),
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => $user_id ),
						array( '%s', '%s' ),
						array( '%d' )
					);
					++$updated_count;
				}
			}

			$total_updated += $updated_count;
			$total_deleted += $deleted_count;
			$offset        += $batch_size;

			// Optional: Add a small delay to prevent overwhelming the database.
			usleep( 100000 ); // 0.1 seconds.

			// count of users processed in this batch.
			$users_processed = count( $users );

		} while ( $users_processed === $batch_size );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Cleanup completed successfully.', 'oneaccess' ),
				'data'    => array(
					'users_updated'     => $total_updated,
					'users_deleted'     => $total_deleted,
					'connected_sites'   => count( $connected_site_urls ),
					'batches_processed' => ceil( $offset / $batch_size ),
				),
			),
			200
		);
	}

	/**
	 * Rebuild deduplicated users index.
	 *
	 * @return WP_REST_Response
	 */
	public function rebuild_deduplicated_users_index(): WP_REST_Response {

		// get oneaccess_sites from global variable.
		$oneaccess_sites = $GLOBALS['oneaccess_sites'] ?? array();

		// for each site fire action to rebuild index.
		$results         = array();
		$error_log       = array();
		$processed_sites = array();

		foreach ( $oneaccess_sites as $site_config ) {
			$site_url = $site_config['siteUrl'] ?? '';
			$api_key  = $site_config['apiKey'] ?? '';

			// Skip duplicate or invalid sites.
			if ( empty( $site_url ) || in_array( $site_url, $processed_sites, true ) ) {
				if ( ! empty( $site_url ) ) {
					$processed_sites[] = $site_url;
				}
				continue;
			}

			if ( empty( $site_url ) ) {
				continue;
			}

			// Make remote request to rebuild index.
			$response = wp_safe_remote_post(
				trailingslashit( esc_url_raw( $site_url ) ) . 'wp-json/' . self::NAMESPACE . '/rebuild-brand-sites-index',
				array(
					'headers' => array(
						'X-OneAccess-Token' => $api_key,
					),
					'timeout' => 30,
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_log[] = $response;
				continue;
			}

			$response_code = wp_remote_retrieve_response_code( $response );

			if ( 200 !== $response_code ) {
				$error_log[] = new WP_Error(
					'invalid_response',
					sprintf( 'Site %s returned status code %d', $site_url, $response_code ),
					array( 'status' => $response_code )
				);
				continue;
			}

			$results[] = array(
				'site_url' => $site_url,
				'status'   => __( 'Rebuild initiated', 'oneaccess' ),
			);

		}
		return new WP_REST_Response(
			array(
				'success' => true,
				'results' => $results,
				'errors'  => $error_log,
			),
			200
		);
	}

	/**
	 * Rebuild brand sites index on current site.
	 *
	 * @return WP_REST_Response
	 */
	public function rebuild_brand_sites_index(): WP_REST_Response {

		// Trigger action to rebuild brand sites index.
		do_action( 'oneaccess_governing_site_configured' );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Brand sites index rebuild initiated successfully.', 'oneaccess' ),
			),
			200
		);
	}
}
