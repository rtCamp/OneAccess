<?php
/**
 * This file is to create db tables for storing de-duplicated users.
 *
 * @package OneAccess
 */

namespace OneAccess\Plugin_Configs;

use OneAccess\Traits\Singleton;

/**
 * Class DB
 */
class DB {
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
	 * Setup hooks.
	 *
	 * @return void
	 */
	public function setup_hooks(): void {
		// add required actions/filters.
	}

	/**
	 * Create database tables for storing de-duplicated users.
	 *
	 * @return void
	 */
	public static function create_deduplicated_users_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . Constants::ONEACCESS_DEDUPLICATED_USERS_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        sites_info JSON NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email (email)
    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create table to store user profile requests changes.
	 *
	 * @return void
	 */
	public static function create_profile_requests_table(): void {
		global $wpdb;
		$table_name      = $wpdb->prefix . Constants::ONEACCESS_PROFILE_REQUESTS_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        request_data JSON NOT NULL,
        status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        comment TEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Add deduplicated users to the database.
	 *
	 * @param array $user_data Users data to be added.
	 *
	 * @return void
	 */
	public static function add_deduplicated_users( array $user_data ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_DEDUPLICATED_USERS_TABLE;

		// check if user with same email already exists then into sites_info add site information on which its present.
		foreach ( $user_data as $user ) {
			$user_email = $user['email'] ?? '';
			if ( empty( $user_email ) ) {
				continue;
			}

			// check if same email user already exists or not.
			$existing_user = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM $table_name WHERE email = %s",
					$user_email
				)
			);

			if ( $existing_user ) {

				// get existing sites_info and decode it.
				$existing_sites_info = json_decode( $existing_user->sites_info, true );
				if ( ! is_array( $existing_sites_info ) ) {
					$existing_sites_info = array();
				}

				// check if same site info already exists or not.
				$new_site_info = array(
					'site_name' => $user['site_name'] ?? '',
					'site_url'  => $user['site_url'] ?? '',
					'user_id'   => $user['user_id'] ?? '',
					'roles'     => $user['roles'] ?? array(),
				);

				$site_exists = false;
				foreach ( $existing_sites_info as $site_info ) {
					if ( trailingslashit( $site_info['site_url'] ) === trailingslashit( $new_site_info['site_url'] ) ) {
						$site_exists = true;
						break;
					}
				}

				if ( ! $site_exists ) {
					$existing_sites_info[] = $new_site_info;

					// update the existing user record.
					$wpdb->update(
						$table_name,
						array(
							'sites_info' => wp_json_encode( $existing_sites_info ),
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => $existing_user->id ),
						array(
							'%s',
							'%s',
						),
						array( '%d' )
					);
				} else {
					// update site_info with latest information.
					foreach ( $existing_sites_info as $site_info ) {
						if ( trailingslashit( $site_info['site_url'] ) === trailingslashit( $new_site_info['site_url'] ) ) {
							$site_info['site_name'] = $new_site_info['site_name'];
							$site_info['user_id']   = $new_site_info['user_id'];
							$site_info['roles']     = $new_site_info['roles'];
							break;
						}
					}
					// update the existing user record.
					$wpdb->update(
						$table_name,
						array(
							'sites_info' => wp_json_encode( $existing_sites_info ),
							'updated_at' => current_time( 'mysql' ),
						),
						array( 'id' => $existing_user->id ),
						array(
							'%s',
							'%s',
						),
						array( '%d' )
					);
				}
			} else {
				// insert new user record.
				$sites_info = array(
					array(
						'site_name' => $user['site_name'] ?? '',
						'site_url'  => $user['site_url'] ?? '',
						'user_id'   => $user['user_id'] ?? '',
						'roles'     => $user['roles'] ?? array(),
					),
				);

				$wpdb->insert(
					$table_name,
					array(
						'email'      => $user_email,
						'first_name' => $user['first_name'] ?? '',
						'last_name'  => $user['last_name'] ?? '',
						'sites_info' => wp_json_encode( $sites_info ),
						'created_at' => current_time( 'mysql' ),
						'updated_at' => current_time( 'mysql' ),
					),
					array(
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
					)
				);
			}
		}
	}

	/**
	 * Add profile request to the database.
	 *
	 * @param int    $user_id User ID for whom profile request is made.
	 * @param array  $request_data Profile request data.
	 * @param string $status Status of the profile request.
	 *
	 * @return void
	 */
	public static function add_profile_request( int $user_id, array $request_data, string $status = 'pending' ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_PROFILE_REQUESTS_TABLE;

		$wpdb->insert(
			$table_name,
			array(
				'user_id'      => $user_id,
				'request_data' => wp_json_encode( $request_data ),
				'status'       => $status,
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Update profile request status.
	 *
	 * @param int    $request_id Profile request ID.
	 * @param string $status New status of the profile request.
	 *
	 * @return void
	 */
	public static function update_profile_request_status( int $request_id, string $status ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_PROFILE_REQUESTS_TABLE;

		$wpdb->update(
			$table_name,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $request_id ),
			array(
				'%s',
				'%s',
			),
			array( '%d' )
		);
	}

	/**
	 * Get pending profile requests by user ID.
	 *
	 * At a time at max only one pending request per user is allowed.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array|null
	 */
	public static function get_pending_profile_request_by_user_id( int $user_id ): ?array {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_PROFILE_REQUESTS_TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND status = %s ORDER BY created_at DESC LIMIT 1",
				$user_id,
				'pending'
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		// decode request_data.
		$row['request_data'] = json_decode( $row['request_data'], true );

		return $row;
	}

	/**
	 * Get rejected profile requests by user ID.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array|null
	 */
	public static function get_rejected_profile_request_by_user_id( int $user_id ): ?array {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_PROFILE_REQUESTS_TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE user_id = %d AND status = %s ORDER BY created_at DESC LIMIT 1",
				$user_id,
				'rejected'
			),
			ARRAY_A
		);

		if ( null === $row ) {
			return null;
		}

		// decode request_data.
		$row['request_data'] = json_decode( $row['request_data'], true );

		return $row;
	}

	/**
	 * Check pending and rejected profile request to know which is latest.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array|null
	 */
	public static function get_latest_profile_request_by_user_id( int $user_id ): ?array {
		$pending_request  = self::get_pending_profile_request_by_user_id( $user_id );
		$rejected_request = self::get_rejected_profile_request_by_user_id( $user_id );

		if ( null === $pending_request && null === $rejected_request ) {
			return null;
		}

		if ( null !== $pending_request && null === $rejected_request ) {
			return $pending_request;
		}

		if ( null === $pending_request && null !== $rejected_request ) {
			return $rejected_request;
		}

		// both are not null, compare created_at to know which is latest.
		$pending_created_at  = strtotime( $pending_request['created_at'] );
		$rejected_created_at = strtotime( $rejected_request['created_at'] );

		if ( $pending_created_at >= $rejected_created_at ) {
			return $pending_request;
		} else {
			return $rejected_request;
		}
	}

	/**
	 * Get profile request by request ID.
	 *
	 * @param int $request_id Request ID.
	 *
	 * @return array|null
	 */
	public static function get_profile_request_by_id( int $request_id ): ?array {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_PROFILE_REQUESTS_TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE id = %d",
				$request_id
			),
			ARRAY_A
		);
		if ( null === $row ) {
			return null;
		}

		// decode request_data.
		$row['request_data'] = json_decode( $row['request_data'], true );

		return $row;
	}

	/**
	 * Approve profile request by request ID.
	 *
	 * @param int $request_id Request ID.
	 *
	 * @return void
	 */
	public static function approve_profile_request_by_id( int $request_id ): void {
		self::update_profile_request_status( $request_id, 'approved' );
	}

	/**
	 * Reject profile request by request ID.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $rejection_comment Rejection comment.
	 *
	 * @return void
	 */
	public static function reject_profile_request_by_id( int $request_id, string $rejection_comment ): void {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_PROFILE_REQUESTS_TABLE;

		$wpdb->update(
			$table_name,
			array(
				'status'     => 'rejected',
				'comment'    => $rejection_comment,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $request_id ),
			array(
				'%s',
				'%s',
				'%s',
			),
			array( '%d' )
		);
	}

	/**
	 * Delete user from deduplicated users table.
	 *
	 * @param string $email User email.
	 * @param string $site_url Site URL to remove from sites_info.
	 *
	 * @return int|false
	 */
	public static function delete_user_from_deduplicated_users( string $email, string $site_url ): int|false {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_DEDUPLICATED_USERS_TABLE;

		$response = false;

		// Get existing user record.
		$existing_user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE email = %s",
				$email
			)
		);

		if ( $existing_user ) {
			// Decode existing sites_info.
			$existing_sites_info = json_decode( $existing_user->sites_info, true );
			if ( ! is_array( $existing_sites_info ) ) {
				$existing_sites_info = array();
			}

			// Filter out the site info to be removed.
			$updated_sites_info = array_filter(
				$existing_sites_info,
				function ( $site_info ) use ( $site_url ) {
					return trailingslashit( $site_info['site_url'] ) !== trailingslashit( $site_url );
				}
			);

			if ( empty( $updated_sites_info ) ) {
				// If no sites left, delete the user record.
				$response = $wpdb->delete(
					$table_name,
					array( 'id' => $existing_user->id ),
					array( '%d' )
				);
			} else {
				// Update the user record with updated sites_info.
				$response = $wpdb->update(
					$table_name,
					array(
						'sites_info' => wp_json_encode( array_values( $updated_sites_info ) ),
						'updated_at' => current_time( 'mysql' ),
					),
					array( 'id' => $existing_user->id ),
					array(
						'%s',
						'%s',
					),
					array( '%d' )
				);
			}
		}

		return $response;
	}

	/**
	 * Update user role in deduplicated users table.
	 *
	 * @param string $email User email.
	 * @param string $new_role New role to be assigned.
	 * @param string $site_url Site URL where role needs to be updated.
	 *
	 * @return int|false
	 */
	public static function update_user_role_in_deduplicated_users( string $email, string $new_role, string $site_url ): int|false {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_DEDUPLICATED_USERS_TABLE;

		$response = false;
		// Get existing user record.
		$existing_user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE email = %s",
				$email
			)
		);

		if ( $existing_user ) {
			// Decode existing sites_info.
			$existing_sites_info = json_decode( $existing_user->sites_info, true );
			if ( ! is_array( $existing_sites_info ) ) {
				$existing_sites_info = array();
			}

			// Update the role for the specified site.
			foreach ( $existing_sites_info as &$site_info ) {
				if ( trailingslashit( $site_info['site_url'] ) === trailingslashit( $site_url ) ) {
					$site_info['roles'] = array( $new_role );
					break;
				}
			}

			// Update the user record with updated sites_info.
			$response = $wpdb->update(
				$table_name,
				array(
					'sites_info' => wp_json_encode( $existing_sites_info ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => $existing_user->id ),
				array(
					'%s',
					'%s',
				),
				array( '%d' )
			);
		}

		return $response;
	}
}
