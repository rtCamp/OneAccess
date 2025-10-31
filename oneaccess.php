<?php
/**
 * Plugin Name: OneAccess
 * Plugin URI: https://github.com/rtCamp/OneAccess/
 * Description: OneAccess is plugin to manage user accounts across multiple sites in a WordPress.
 * Version: 1.0.0
 * Author: Utsav Patel, rtCamp
 * Author URI: https://rtcamp.com
 * Text Domain: oneaccess
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Tested up to: 6.8.2
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package OneAccess
 */

use OneAccess\User_Roles;
use OneAccess\Plugin_Configs\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ONEACCESS_PLUGIN_LOADER_VERSION', '1.0.0' );
define( 'ONEACCESS_PLUGIN_LOADER_FEATURES_PATH',  untrailingslashit(plugin_dir_path( __FILE__ )) );
define( 'ONEACCESS_PLUGIN_LOADER_RELATIVE_PATH', dirname( plugin_basename( __FILE__ ) ) );
define( 'ONEACCESS_PLUGIN_LOADER_FEATURES_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'ONEACCESS_PLUGIN_LOADER_BUILD_PATH', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/assets/build' );
define( 'ONEACCESS_PLUGIN_LOADER_SRC_PATH', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/assets/src' );
define( 'ONEACCESS_PLUGIN_LOADER_BUILD_URI', untrailingslashit( plugin_dir_url( __FILE__ ) ) . '/assets/build' );
define( 'ONEACCESS_PLUGIN_LOADER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ONEACCESS_PLUGIN_LOADER_SLUG', 'oneaccess' );
define( 'ONEACCESS_PLUGIN_TEMPLATES_PATH', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/inc/templates' );


if( ! file_exists( ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/vendor/autoload.php' ) ) {
	// load template-functions file to use oneaccess_get_template_content function.
	require_once ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/inc/helpers/template-functions.php';
	
	echo oneaccess_get_template_content( 'notices/no-assets' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- we are escaping the output in the template file.
	return;
}

// Load the Composer autoloader.
require_once ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/vendor/autoload.php';

/**
 * Load the plugin.
 * 
 * @return void
 */
function oneaccess_plugin_loader(): void {

	\OneAccess\Plugin::get_instance();

	// load plugin text domain.
	load_plugin_textdomain( 'oneaccess', false, ONEACCESS_PLUGIN_LOADER_RELATIVE_PATH . '/languages/' );

	// if woocommerce action schedular is present into vendor then load it's file.
	if( file_exists( ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/vendor/woocommerce/action-scheduler/action-scheduler.php' ) ){
		require_once ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
	}
}

add_action( 'plugins_loaded', 'oneaccess_plugin_loader', -10 ); // added -10 to make sure action scheduler is loaded on 0 priority.

/**
 * Activation hook to add roles.
 */
register_activation_hook(
	ONEACCESS_PLUGIN_LOADER_PLUGIN_BASENAME,
	function (): void {

		if ( ! class_exists( User_Roles::class ) && ! class_exists( DB::class ) ) {
			return;
		}

		User_Roles::create_brand_admin_role();
		User_Roles::create_network_admin_role();

		// Update user role on activation.
		User_Roles::update_user_role_on_activation();

		// Create database tables on activation.
		DB::create_deduplicated_users_table();
		DB::create_profile_requests_table();
	}
);

/**
 * Deactivation hook to remove roles and change user roles.
 */
register_deactivation_hook(
	ONEACCESS_PLUGIN_LOADER_PLUGIN_BASENAME,
	function (): void {

		if ( ! class_exists( User_Roles::class ) ) {
			return;
		}

		User_Roles::remove_roles_and_change_user_roles();
	}
);
