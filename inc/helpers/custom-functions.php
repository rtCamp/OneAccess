<?php
/**
 * Custom functions for the plugin.
 *
 * @package oneaccess
 */

use OneAccess\Plugin_Configs\Constants;
use OneAccess\Utils;

/**
 * Get plugin template.
 *
 * @param string $template  Name or path of the template within /templates folder without php extension.
 * @param array  $variables pass an array of variables you want to use in template.
 * @param bool   $is_echo      Whether to echo out the template content or not.
 *
 * @return string|void Template markup.
 */
function oneaccess_features_template( $template, $variables = array(), $is_echo = false ) {

	$template_file = sprintf( '%1$s/templates/%2$s.php', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH, $template );

	if ( ! file_exists( $template_file ) ) {
		return '';
	}

	if ( ! empty( $variables ) && is_array( $variables ) ) {
		extract( $variables, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Used as an exception as there is no better alternative.
	}

	ob_start();

	include $template_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

	$markup = ob_get_clean();

	if ( ! $is_echo ) {
		return $markup;
	}

	echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped already in template.
}

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
		return current_user_can( 'manage_options' ) ? true : false;
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
			update_option( Constants::ONEACCESS_GOVERNING_SITE_URL, $request_origin, false );
			return true;
		}

		// if token is valid and request is from different domain then check if it matches governing site url.
		if ( Utils::is_brand_site() && ! $is_same_domain && $is_token_valid && ! empty( $governing_site_url ) && ( Utils::is_same_domain( $governing_site_url, $request_origin ) || false !== strpos( $user_agent, $governing_site_url ) ) ) {
			return true;
		}
	}
	return false;
}
