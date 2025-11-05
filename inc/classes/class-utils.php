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

	/**
	 * Get governing site URL.
	 *
	 * @return string Governing site URL.
	 */
	public static function get_governing_site_url(): string {
		$governing_site_url = get_option( Constants::ONEACCESS_GOVERNING_SITE_URL, '' );
		return esc_url_raw( trailingslashit( $governing_site_url ) );
	}

	/**
	 * Check if site type is set.
	 *
	 * @return bool
	 */
	public static function is_site_type_set(): bool {
		$site_type = get_option( Constants::ONEACCESS_SITE_TYPE, '' );
		return ! empty( $site_type );
	}

	/**
	 * Get all available connected sites.
	 * 
	 * @return array List of connected site URLs.
	 */
	public static function get_connected_sites(): array {
		$connected_sites = get_option( Constants::ONEACCESS_SHARED_SITES, array() );
		if ( is_array( $connected_sites ) ) {
			return $connected_sites;
		}
		return array();
	}

	/**
	 * Get current screen object.
	 * 
	 * @return \WP_Screen|null Current screen object or null if not available.
	 */
	public static function get_current_screen(): ?\WP_Screen {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return null;
		}
		$screen = get_current_screen();
		if ( is_a( $screen, '\WP_Screen' ) ) {
			return $screen;
		}
		return null;
	}
}
