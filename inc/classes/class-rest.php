<?php
/**
 * Register All OneAccess related REST API endpoints.
 *
 * @package OneAccess
 */

namespace OneAccess;

use OneAccess\REST\{ Basic_Options, Brand_Site, Users, Actions };
use OneAccess\Traits\Singleton;

/**
 * Class REST
 */
class REST {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * REST constructor.
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks.
	 *
	 * @return void
	 */
	public function setup_hooks(): void {
		Basic_Options::get_instance();
		Users::get_instance();
		Brand_Site::get_instance();
		Actions::get_instance();

		// fix cors headers for REST API requests.
		add_filter( 'rest_pre_serve_request', array( $this, 'add_cors_headers' ), PHP_INT_MAX - 20, 4 );
	}

	/**
	 * Add CORS headers to REST API responses.
	 *
	 * @param bool $served Whether the request has been served.
	 * @return bool
	 */
	public function add_cors_headers( $served ): bool {
		header( 'Access-Control-Allow-Headers: X-OneAccess-Token, Content-Type, Authorization', false );
		return $served;
	}
}
