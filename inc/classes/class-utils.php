<?php
/**
 * Class Utils -- this is utils class to have common functions.
 *
 * @package OneAccess
 */

namespace OneAccess;

use OneAccess\Plugin_Configs\Constants;
use OneAccess\Traits\Singleton;

/**
 * Class Utils
 */
class Utils {
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
		// Add any hooks if needed in the future.
	}

	/**
	 * Get the current site type.
	 *
	 * @return string
	 */
	public static function get_current_site_type(): string {
		$oneaccess_site_type = get_option( Constants::ONEACCESS_SITE_TYPE, '' );
		return $oneaccess_site_type;
	}

	/**
	 * Check if the current site is a brand site.
	 *
	 * @return bool
	 */
	public static function is_brand_site(): bool {
		return hash_equals( 'brand-site', self::get_current_site_type() );
	}

	/**
	 * Check if the current site is a governing site.
	 *
	 * @return bool
	 */
	public static function is_governing_site(): bool {
		return hash_equals( 'governing-site', self::get_current_site_type() );
	}

	/**
	 * Get users profile request data.
	 *
	 * @return array
	 */
	public static function get_users_profile_request_data(): array {
		$profile_request_data = get_option( Constants::ONEACCESS_PROFILE_UPDATE_REQUESTS, array() );
		if ( ! is_array( $profile_request_data ) ) {
			$profile_request_data = array();
		}
		return $profile_request_data;
	}

	/**
	 * Get new users data.
	 *
	 * @return array
	 */
	public static function get_new_users_data(): array {
		$new_users_data = get_option( Constants::ONEACCESS_NEW_USERS, array() );
		if ( ! is_array( $new_users_data ) ) {
			$new_users_data = array();
		}
		return $new_users_data;
	}

	/**
	 * Check if two URLs belong to the same domain.
	 *
	 * @param string $url1 First URL.
	 * @param string $url2 Second URL.
	 *
	 * @return bool True if both URLs belong to the same domain, false otherwise.
	 */
	public static function is_same_domain( string $url1, string $url2 ): bool {
		$parsed_url1 = wp_parse_url( $url1 );
		$parsed_url2 = wp_parse_url( $url2 );

		if ( ! isset( $parsed_url1['host'] ) || ! isset( $parsed_url2['host'] ) ) {
			return false;
		}
		return hash_equals( $parsed_url1['host'], $parsed_url2['host'] );
	}
}
