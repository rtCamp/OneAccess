<?php
/**
 * This file is to create admin page.
 *
 * @package OneAccess
 */

namespace OneAccess\Settings;

use OneAccess\Traits\Singleton;
use OneAccess\Utils;

/**
 * Class Shared_Sites
 */
class Shared_Sites {

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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu under media
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {

		// only show menu if newtwork admin or brand admin.
		$current_user = wp_get_current_user();
		if ( ! in_array( 'network_admin', $current_user->roles, true ) && ! in_array( 'brand_admin', $current_user->roles, true ) ) {
			return;
		}

		add_menu_page(
			__( 'OneAccess', 'oneaccess' ),
			__( 'OneAccess', 'oneaccess' ),
			'manage_options',
			'oneaccess',
			'__return_null',
			'',
			2
		);

		// Add sub menu under forms inspector - this will rename the first submenu item.
		if ( Utils::is_governing_site() ) {
			add_submenu_page(
				'oneaccess',
				__( 'Manage Users', 'oneaccess' ),
				'<span class="oneaccess-manage-user-page">' . __( 'Manage Users', 'oneaccess' ) . '</span>',
				'manage_options',
				'oneaccess',
				array( $this, 'render_oneaccess_user_manager' )
			);
		}

		// Add your other submenu page.
		add_submenu_page(
			'oneaccess',
			__( 'Settings', 'oneaccess' ),
			__( 'Settings', 'oneaccess' ),
			'manage_options',
			'oneaccess-settings',
			array( $this, 'render_oneaccess_settings_page' )
		);

		// Remove the duplicate top-level menu item.
		if ( Utils::is_brand_site() ) {
			remove_submenu_page( 'oneaccess', 'oneaccess' );
		}
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_oneaccess_user_manager(): void {
		// Check if the user has permission to manage options.
		$current_user = wp_get_current_user();
		if ( ! current_user_can( 'manage_options' ) || ! Utils::is_governing_site() || ! in_array( 'network_admin', $current_user->roles, true ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'oneaccess' ) );
		}
		?>
		<div class="wrap">
			<h1 class="oneaccess-heading"><?php esc_html_e( 'Manage Users', 'oneaccess' ); ?></h1>
			<div id="oneaccess-manage-user"></div>
		</div>
		<?php
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_oneaccess_settings_page(): void {

		// Check if the user has permission to manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'oneaccess' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'oneaccess' ); ?></h1>
			<div id="oneaccess-settings-page"></div>
		</div>
		<?php
	}
}
