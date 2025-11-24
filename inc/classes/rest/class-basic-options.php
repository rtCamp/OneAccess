<?php
/**
 * Class Basic_Options which contains basic rest routes for the plugin.
 *
 * @package OneAccess
 */

namespace OneAccess\REST;

use OneAccess\Traits\Singleton;
use OneAccess\Plugin_Configs\{ Constants, Secret_Key };
use OneAccess\Utils;
use OneAccess\User_Roles;
use WP_REST_Server;

/**
 * Class Basic_Options
 */
class Basic_Options {

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
		 * Register a route to get site type and set site type.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/site-type',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_site_type' ),
					'permission_callback' => array( self::class, 'check_user_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_site_type' ),
					'permission_callback' => array( self::class, 'check_user_permissions' ),
					'args'                => array(
						'site_type' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		/**
		 * Register a route to get oneaccess_child_site_api_key option.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/secret-key',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( Secret_Key::class, 'get_secret_key' ),
					'permission_callback' => array( self::class, 'check_user_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( Secret_Key::class, 'regenerate_secret_key' ),
					'permission_callback' => array( self::class, 'check_user_permissions' ),
				),
			)
		);

		/**
		 * Register a route which will store array of sites data like site name, site url, its GitHub repo and api key.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/shared-sites',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_shared_sites' ),
					'permission_callback' => array( self::class, 'check_user_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_shared_sites' ),
					'permission_callback' => array( self::class, 'check_user_permissions' ),
					'args'                => array(
						'sites_data' => array(
							'required'          => true,
							'type'              => 'array',
							'sanitize_callback' => function ( $value ) {
								return is_array( $value );
							},
						),
					),
				),
			)
		);

		/**
		 * Register a route for health-check.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/health-check',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health_check' ),
				'permission_callback' => '\oneaccess_validate_api_key_health_check',
			)
		);

		/**
		 * Register a route to manage governing site.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/governing-site',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_governing_site' ),
					'permission_callback' => array( self::class, 'check_user_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_governing_site' ),
					'permission_callback' => array( self::class, 'check_user_permissions' ),
				),
			),
		);
	}

	/**
	 * Permission callback to check user capabilities.
	 *
	 * @return bool
	 */
	public static function check_user_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_governing_site(): \WP_REST_Response|\WP_Error {
		$governing_site_url = get_option( Constants::ONEACCESS_GOVERNING_SITE_URL, '' );
		return new \WP_REST_Response(
			array(
				'success'            => true,
				'governing_site_url' => $governing_site_url,
			)
		);
	}

	/**
	 * Remove governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function remove_governing_site(): \WP_REST_Response|\WP_Error {
		update_option( Constants::ONEACCESS_GOVERNING_SITE_URL, '', false );
		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Governing site removed successfully.', 'oneaccess' ),
			)
		);
	}

	/**
	 * Health check endpoint.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function health_check(): \WP_REST_Response|\WP_Error {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Health check passed successfully.', 'oneaccess' ),
			)
		);
	}

	/**
	 * Get the site type.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_site_type(): \WP_REST_Response|\WP_Error {

		$site_type = Utils::get_current_site_type();

		return new \WP_REST_Response(
			array(
				'success'   => true,
				'site_type' => $site_type,
			)
		);
	}

	/**
	 * Set the site type.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_site_type( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$site_type = sanitize_text_field( $request->get_param( 'site_type' ) );

		update_option( Constants::ONEACCESS_SITE_TYPE, $site_type, false );

		// Create user roles based on site type.
		User_Roles::create_brand_admin_role();
		User_Roles::create_network_admin_role();

		// if current site type is brand then update current users to brand admin.
		if ( Utils::is_brand_site() ) {
			$current_user = wp_get_current_user();
			// add brand_admin role to current user if not already has it.
			if ( ! in_array( 'brand_admin', (array) $current_user->roles, true ) ) {
				$current_user->add_role( 'brand_admin' );
			}
			// if current user has network_admin role then remove it.
			if ( in_array( 'network_admin', (array) $current_user->roles, true ) ) {
				$current_user->remove_role( 'network_admin' );
			}
		}

		// if current site type is governing then update current users to network admin.
		if ( Utils::is_governing_site() ) {
			$current_user = wp_get_current_user();
			// add network_admin role to current user if not already has it.
			if ( ! in_array( 'network_admin', (array) $current_user->roles, true ) ) {
				$current_user->add_role( 'network_admin' );
			}
			// if current user has brand_admin role then remove it.
			if ( in_array( 'brand_admin', (array) $current_user->roles, true ) ) {
				$current_user->remove_role( 'brand_admin' );
			}
		}

		return new \WP_REST_Response(
			array(
				'success'   => true,
				'site_type' => $site_type,
			)
		);
	}

	/**
	 * Get shared sites data.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_shared_sites(): \WP_REST_Response|\WP_Error {
		$shared_sites = get_option( Constants::ONEACCESS_SHARED_SITES, array() );
		return new \WP_REST_Response(
			array(
				'success'      => true,
				'shared_sites' => $shared_sites,
			)
		);
	}

	/**
	 * Set shared sites data.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_shared_sites( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {

		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$sites_data   = $decoded_body['sites_data'] ?? array();

		// check if same url exists more than once or not.
		$urls = array();
		foreach ( $sites_data as $site ) {
			if ( isset( $site['siteUrl'] ) && in_array( $site['siteUrl'], $urls, true ) ) {
				return new \WP_Error( 'duplicate_site_url', __( 'Brand Site already exists.', 'oneaccess' ), array( 'status' => 400 ) );
			}
			$urls[] = $site['siteUrl'] ?? '';
		}

		update_option( Constants::ONEACCESS_SHARED_SITES, $sites_data, false );

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'sites_data' => $sites_data,
			)
		);
	}
}
