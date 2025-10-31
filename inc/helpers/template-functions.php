<?php
/**
 * Output custom template content.
 *
 * @package OneAccess
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Return oneaccess template content.
 *
 * @param string $slug Template path.
 * @param array  $vars Template variables.
 *
 * @return string Template markup.
 */
function oneaccess_get_template_content( string $slug, array $vars = array() ): string {
	ob_start();

	$template = sprintf( '%s.php', $slug );

	$located_template = '';
	if ( file_exists( ONEACCESS_PLUGIN_TEMPLATES_PATH . '/' . $template ) ) {
		$located_template = ONEACCESS_PLUGIN_TEMPLATES_PATH . '/' . $template;
	}
    
	if ( '' === $located_template ) {
		return '';
	}

	// Ensure vars is an array.
	if ( ! is_array( $vars ) ) {
		$vars = array();
	}

	require_once $located_template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

	$markup = ob_get_clean();

	return $markup;
}
