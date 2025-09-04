<?php
/**
 * Class User_Roles -- this is to create new user roles and remove existing roles capabilities.
 *
 * @package OneAccess
 */

namespace OneAccess;

use OneAccess\Traits\Singleton;

/**
 * Class User_Roles
 */
class User_Roles {

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

		// modify user caps to core user roles.
		add_action( 'user_has_cap', array( $this, 'modify_user_caps' ), 10, 4 );
	}

	/**
	 * Modify user capabilities based on the site type.
	 *
	 * @param array $allcaps All capabilities for the user.
	 *
	 * @return array Modified capabilities.
	 */
	public function modify_user_caps( $allcaps ): array {
		// if this is branch site.
		if ( Utils::is_brand_site() ) {
			// remove user creation, deletion and promotion caps.
			$caps_to_remove = array(
				'create_users',
				'delete_users',
				'promote_users',
			);

			foreach ( $caps_to_remove as $cap ) {
				if ( isset( $allcaps[ $cap ] ) ) {
					unset( $allcaps[ $cap ] );
				}
			}

			$current_user = wp_get_current_user();

			if ( ! in_array( 'brand_admin', $current_user->roles, true ) ) {
				$allcaps['edit_users'] = false;
			}
		}

		return $allcaps;
	}

	/**
	 * Create the brand admin role.
	 */
	public static function create_network_admin_role(): void {

		if ( ! Utils::is_governing_site() ) {
			return;
		}

		$admin_role = get_role( 'administrator' );
		if ( ! $admin_role ) {
			return;
		}

		$network_admin_role = add_role( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.custom_role_add_role -- all sites are not VIP sites.
			'network_admin',
			__( 'Network Admin', 'oneaccess' ),
			$admin_role->capabilities
		);

		if ( ! $network_admin_role ) {
			return;
		}

		// Add additional capabilities to the network admin role.
		$additional_caps = array(
			'oneaccess_manage_users',
			'oneaccess_manage_requests',
			'oneaccess_manage_sites',
		);

		foreach ( $additional_caps as $cap ) {
			$network_admin_role->add_cap( $cap );
		}
	}

	/**
	 * Create the brand admin role.
	 */
	public static function create_brand_admin_role(): void {
		if ( ! Utils::is_brand_site() ) {
			return;
		}

		$admin_role = get_role( 'administrator' );

		if ( ! $admin_role ) {
			return;
		}

		add_role( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.custom_role_add_role -- all sites are not VIP sites.
			'brand_admin',
			__( 'Brand Admin', 'oneaccess' ),
			$admin_role->capabilities
		);
	}

	/**
	 * Remove roles and change user roles to administrator.
	 */
	public static function remove_roles_and_change_user_roles(): void {
		// remove network_admin role if it exists.
		if ( get_role( 'network_admin' ) ) {

			// before removing the role change all network_admin users to administrator role.
			$network_admin_users = get_users( array( 'role' => 'network_admin' ) );
			foreach ( $network_admin_users as $user ) {
				$user->set_role( 'administrator' );
			}

			remove_role( 'network_admin' );
		}

		// remove brand_admin role if it exists.
		if ( get_role( 'brand_admin' ) ) {

			// before removing the role change all brand_admin users to administrator role.
			$brand_admin_users = get_users( array( 'role' => 'brand_admin' ) );
			foreach ( $brand_admin_users as $user ) {
				$user->set_role( 'administrator' );
			}

			remove_role( 'brand_admin' );
		}
	}

	/**
	 * Update user role on plugin activation.
	 *
	 * @return void
	 */
	public static function update_user_role_on_activation(): void {
		$current_user = wp_get_current_user();
		if ( Utils::is_brand_site() && in_array( 'administrator', (array) $current_user->roles, true ) ) {
			$current_user->add_role( 'brand_admin' );
		}
		if ( Utils::is_governing_site() && in_array( 'administrator', (array) $current_user->roles, true ) ) {
			$current_user->add_role( 'network_admin' );
		}
	}
}
