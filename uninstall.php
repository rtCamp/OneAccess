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

if ( ! function_exists( 'oneaccess_plugin_sync_deactivate' ) ) {

	/**
	 * Function to deactivate the plugin and clean up options.
	 */
	function oneaccess_plugin_sync_deactivate(): void {

		// list of actions to be cleared on uninstall.
		$actions_to_clear = array(
			'oneaccess_governing_site_configured',
			'oneaccess_add_deduplicated_users',
		);

		// Clear scheduled actions.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( $actions_to_clear as $action ) {
				// check if action is scheduled then clear it.
				if ( as_next_scheduled_action( $action ) ) {
					as_unschedule_all_actions( $action );
				}
			}
		}

		$options_to_delete = array(
			'oneaccess_child_site_api_key',
			'oneaccess_shared_sites',
			'oneaccess_site_type',
			'oneaccess_profile_update_requests',
			'oneaccess_new_users',
			'oneaccess_governing_site_url',
		);

		foreach ( $options_to_delete as $option ) {
			delete_option( $option );
		}

		// Remove oneaccess_site_type_transient transient.
		delete_transient( 'oneaccess_site_type_transient' );

		// Drop custom tables created by the OneAccess.
		$tables_to_drop = array(
			'oneaccess_deduplicated_users',
			'oneaccess_profile_requests',
		);

		global $wpdb;
		foreach ( $tables_to_drop as $table ) {
			$full_table_name = $wpdb->prefix . $table;
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $full_table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this is to drop table on uninstall
		}
	}
}
/**
 * Uninstall the plugin and clean up options.
 */
oneaccess_plugin_sync_deactivate();
