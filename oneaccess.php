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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ONEACCESS_PLUGIN_LOADER_VERSION', '1.0.0' );
define( 'ONEACCESS_PLUGIN_LOADER_FEATURES_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'ONEACCESS_PLUGIN_LOADER_RELATIVE_PATH', dirname( plugin_basename( __FILE__ ) ) );
define( 'ONEACCESS_PLUGIN_LOADER_FEATURES_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'ONEACCESS_PLUGIN_LOADER_BUILD_PATH', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/assets/build' );
define( 'ONEACCESS_PLUGIN_LOADER_SRC_PATH', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/assets/src' );
define( 'ONEACCESS_PLUGIN_LOADER_BUILD_URI', untrailingslashit( plugin_dir_url( __FILE__ ) ) . '/assets/build' );
define( 'ONEACCESS_PLUGIN_LOADER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'ONEACCESS_PLUGIN_LOADER_SLUG', 'oneaccess' );
define( 'ONEACCESS_PLUGIN_TEMPLATES_PATH', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/inc/templates' );


if ( ! file_exists( ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/vendor/autoload.php' ) ) {

	$message  = '<p>';
	$message .= sprintf(
		/* translators: %s is the plugin name. */
		esc_html__( 'You are running the %s plugin from the GitHub repository. Please build the assets and install composer dependencies to use the plugin.', 'oneaccess' ),
		'<strong>' . esc_html__( 'OneAccess', 'oneaccess' ) . '</strong>'
	);
	$message .= '</p><p>';
	$message .= sprintf(
		/* translators: %s is the command to run. */
		esc_html__( 'Run the following commands in the plugin directory: %s', 'oneaccess' ),
		'<code>composer install && npm install && npm run build:prod</code>'
	);
	$message .= '</p><p>';
	$message .= sprintf(
		/* translators: %s is the plugin name. */
		esc_html__( 'Please refer to the %s for more information.', 'oneaccess' ),
		sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( 'https://github.com/rtCamp/OneAccess' ),
			esc_html__( 'OneAccess GitHub repository', 'oneaccess' )
		)
	);
	$message .= '</p>';

	wp_admin_notice(
		$message,
		array(
			'type'        => 'error',
			'dismissible' => false,
		)
	);

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
}

add_action( 'plugins_loaded', 'oneaccess_plugin_loader', -10 ); // added -10 to make sure action scheduler is loaded on 0 priority.

/**
 * Activation hook to add roles.
 */
register_activation_hook(
	ONEACCESS_PLUGIN_LOADER_PLUGIN_BASENAME,
	function (): void {

		if ( ! class_exists( '\OneAccess\User_Roles' ) && ! class_exists( '\OneAccess\Plugin_Configs\DB' ) ) {
			return;
		}

		\OneAccess\User_Roles::create_brand_admin_role();
		\OneAccess\User_Roles::create_network_admin_role();

		// Update user role on activation.
		\OneAccess\User_Roles::update_user_role_on_activation();

		// Create database tables on activation.
		\OneAccess\Plugin_Configs\DB::maybe_create_tables();
	}
);

/**
 * Deactivation hook to remove roles and change user roles.
 */
register_deactivation_hook(
	ONEACCESS_PLUGIN_LOADER_PLUGIN_BASENAME,
	function (): void {

		if ( ! class_exists( '\OneAccess\User_Roles' ) ) {
			return;
		}

		\OneAccess\User_Roles::remove_roles_and_change_user_roles();
	}
);
