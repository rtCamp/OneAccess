<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package OneAccess
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'oneaccess_plugin_sync_deactivate' ) ) {

	/**
	 * Function to deactivate the plugin and clean up options.
	 */
	function oneaccess_plugin_sync_deactivate() {
		// Remove oneaccess_child_site_api_key option.
		delete_option( 'oneaccess_child_site_api_key' );
		// Remove the shared sites option.
		delete_option( 'oneaccess_shared_sites' );
		// Remove the site type option.
		delete_option( 'oneaccess_site_type' );
		// Remove oneaccess_profile_update_requests option.
		delete_option( 'oneaccess_profile_update_requests' );
		// Remove oneaccess_new_users option.
		delete_option( 'oneaccess_new_users' );
		// Remove oneaccess_site_type_transient transient.
		delete_transient( 'oneaccess_site_type_transient' );
		// Remove oneaccess_governing_site_url option.
		delete_option( 'oneaccess_governing_site_url' );
	}
}
/**
 * Uninstall the plugin and clean up options.
 */
oneaccess_plugin_sync_deactivate();
