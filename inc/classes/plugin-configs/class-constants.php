<?php
/**
 * Class Constants -- this is to define plugin constants.
 *
 * @package OneAccess
 */

namespace OneAccess\Plugin_Configs;

use OneAccess\Traits\Singleton;

/**
 * Class Constants
 */
class Constants {

	/**
	 * Plugin constant variables.
	 *
	 * @var array $constants
	 */
	public static $constants;

	/**
	 * Child site api key.
	 *
	 * @var string
	 */
	public const ONEACCESS_API_KEY = 'oneaccess_child_site_api_key';

	/**
	 * Shared sites.
	 *
	 * @var string
	 */
	public const ONEACCESS_SHARED_SITES = 'oneaccess_shared_sites';

	/**
	 * Site type.
	 *
	 * @var string
	 */
	public const ONEACCESS_SITE_TYPE = 'oneaccess_site_type';

	/**
	 * Profile update requests.
	 *
	 * @var string
	 */
	public const ONEACCESS_PROFILE_UPDATE_REQUESTS = 'oneaccess_profile_update_requests';

	/**
	 * New users.
	 *
	 * @var string
	 */
	public const ONEACCESS_NEW_USERS = 'oneaccess_new_users';

	/**
	 * Site type transient.
	 *
	 * @var string
	 */
	public const ONEACCESS_SITE_TYPE_TRANSIENT = 'oneaccess_site_type_transient';

	/**
	 * Time zone string.
	 *
	 * @var string
	 */
	public const ONEACCESS_TIMEZONE_STRING = 'timezone_string';

	/**
	 * Governing site request origin url.
	 *
	 * @var string
	 */
	public const ONEACCESS_GOVERNING_SITE_URL = 'oneaccess_governing_site_url';

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 */
	protected function __construct() {
		$this->define_constants();
	}

	/**
	 * Define plugin constants
	 */
	private function define_constants(): void {
		// future constants can be defined here.
	}
}
