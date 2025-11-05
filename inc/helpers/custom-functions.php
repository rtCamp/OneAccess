<?php
/**
 * Custom functions for the plugin.
 *
 * @package oneaccess
 */

// if accessed directly, exit.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OneAccess\Plugin_Configs\Constants;
use OneAccess\Utils;
use ParagonIE\Sodium\Core\Util;

/**
 * Validate API key for general request.
 *
 * @return bool
 */
function oneaccess_validate_api_key(): bool {
	return oneaccess_key_validation( false );
}

/**
 * Validate API key for health check.
 *
 * @return bool
 */
function oneaccess_validate_api_key_health_check(): bool {
	return oneaccess_key_validation( true );
}

/**
 * Validate API key.
 *
 * @param bool $is_health_check Whether the request is for health check or not.
 *
 * @return bool
 */
function oneaccess_key_validation( $is_health_check ): bool {
	// check if the request is from same site.
	if ( Utils::is_governing_site() ) {
		return current_user_can( 'manage_options' );
	}

	// check X-oneaccess-Token header.
	if ( isset( $_SERVER['HTTP_X_ONEACCESS_TOKEN'] ) && ! empty( $_SERVER['HTTP_X_ONEACCESS_TOKEN'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ONEACCESS_TOKEN'] ) );
		// Get the api key from options.
		$api_key = get_option( Constants::ONEACCESS_API_KEY, 'default_api_key' );

		// governing site url.
		$governing_site_url = get_option( Constants::ONEACCESS_GOVERNING_SITE_URL, '' );

		// check if governing site is set and matches with request origin.
		$request_origin   = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
		$current_site_url = get_site_url();
		$user_agent       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__ -- this is to know requesting user domain for request which are generated from server.
		$is_token_valid   = hash_equals( $token, $api_key );
		$is_same_domain   = ! empty( $request_origin ) && Utils::is_same_domain( $current_site_url, $request_origin );

		// if token is valid and from same domain return true.
		if ( Utils::is_brand_site() && $is_same_domain && $is_token_valid ) {
			return true;
		}

		// if token is valid and request is from different domain then save it as governing site.
		if ( Utils::is_brand_site() && ! $is_same_domain && $is_token_valid && empty( $governing_site_url ) && $is_health_check ) {
			$is_updated = update_option( Constants::ONEACCESS_GOVERNING_SITE_URL, $request_origin, false );

			if ( $is_updated ) {
				/**
				 * Action triggered when governing site is configured for the brand site.
				 * 
				 * @hook oneaccess_governing_site_configured
				 */
				if ( function_exists( 'as_enqueue_async_action' ) ) {
					as_enqueue_async_action( 
						'oneaccess_governing_site_configured', 
						array(), 
						'oneaccess' 
					);
				}           
			}

			return true;
		}

		// if token is valid and request is from different domain then check if it matches governing site url.
		if ( Utils::is_brand_site() && ! $is_same_domain && $is_token_valid && ! empty( $governing_site_url ) && ( Utils::is_same_domain( $governing_site_url, $request_origin ) || false !== strpos( $user_agent, $governing_site_url ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Permission check for brand site to governing site requests.
 *
 * @return bool
 */
function oneaccess_brand_site_to_governing_site_request_permission_check(): bool {

	// check X-oneaccess-Token header.
	if ( isset( $_SERVER['HTTP_X_ONEACCESS_TOKEN'] ) && ! empty( $_SERVER['HTTP_X_ONEACCESS_TOKEN'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ONEACCESS_TOKEN'] ) );
		
		// check if governing site is set and matches with request origin.
		$request_origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? untrailingslashit( sanitize_url( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) ) : '';
		$user_agent     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__ -- this is to know requesting user domain for request which are generated from server.

		// get connected sites.
		$connected_sites = Utils::get_connected_sites();
		
		foreach ( $connected_sites as $site ) {
			$site_url = isset( $site['siteUrl'] ) ? untrailingslashit( esc_url_raw( $site['siteUrl'] ) ) : '';

			if ( Utils::is_same_domain( $site_url, $request_origin ) || false !== strpos( $user_agent, $site_url ) ) {
				$api_key = isset( $site['apiKey'] ) ? sanitize_text_field( $site['apiKey'] ) : '';

				if ( hash_equals( $token, $api_key ) ) {
					return true;
				}
			}
		}   
	}

	return false;
}
