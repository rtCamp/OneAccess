<?php
/**
 * Class Brand_Site -- this is to create user columns & filters for brand site.
 *
 * @package OneAccess
 */

namespace OneAccess\Settings;

use OneAccess\Utils;
use OneAccess\Traits\Singleton;


/**
 * Class Brand_Site
 */
class Brand_Site {

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

		// early return if this is not a brand site.
		if ( ! Utils::is_brand_site() ) {
			return;
		}

		// get current user.
		$current_user = wp_get_current_user();

		// check if its brand admin else return.
		if ( ! in_array( 'brand_admin', $current_user->roles, true ) ) {
			return;
		}

		// add custom user column to indicate profile request status.
		add_filter( 'manage_users_columns', array( $this, 'add_profile_request_status_column' ) );

		// add custom user column content.
		add_filter( 'manage_users_custom_column', array( $this, 'render_profile_request_status_column' ), 10, 3 );
	}

	/**
	 * Add custom user column to indicate profile request status.
	 *
	 * @param array $columns Existing user columns.
	 *
	 * @return array Modified user columns.
	 */
	public function add_profile_request_status_column( $columns ): array {
		// Add a new column for profile request status.
		$columns['profile_request_status'] = __( 'Profile Request Status', 'oneaccess' );
		return $columns;
	}

	/**
	 * Render the content for the profile request status column.
	 *
	 * @param string $value The current value of the column.
	 * @param string $column_name The name of the column.
	 * @param int    $user_id The ID of the user.
	 *
	 * @return string HTML content for the column.
	 */
	public function render_profile_request_status_column( $value, $column_name, $user_id ): string {
		if ( 'profile_request_status' === $column_name ) {
			// Get the user's profile request status.
			$profile_update_requests = Utils::get_users_profile_request_data();
			if ( ! isset( $profile_update_requests[ $user_id ] ) ) {
				return '<span class="oneaccess-pill oneaccess-pill--no-request">' . __( 'No Request', 'oneaccess' ) . '</span>';
			} else {
				$status = $profile_update_requests[ $user_id ]['status'] ?? 'pending';
				switch ( $status ) {
					case 'rejected':
						return '<span class="oneaccess-pill oneaccess-pill--rejected">' . __( 'Rejected', 'oneaccess' ) . '</span>';
					default:
						return '<span class="oneaccess-pill oneaccess-pill--pending">' . __( 'Pending', 'oneaccess' ) . '</span>';
				}
			}
		}
		return $value;
	}
}
