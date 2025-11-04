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
	 * @param array $users_data Users data to be added.
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
					if ( $site_info['site_url'] === $new_site_info['site_url'] ) {
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
						if ( $site_info['site_url'] === $new_site_info['site_url'] ) {
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
	 * Insert or update deduplicated user record.
	 *
	 * @param int|string $user_id User ID.
	 * @param string     $fullname Full name of the user.
	 * @param array      $sites_info Sites information array.
	 *
	 * @return int|false|null
	 */
	public static function insert_or_update_deduplicated_user( string $user_email, string $fullname, array $site_info ): int|false|null {
		global $wpdb;
		$table_name = $wpdb->prefix . Constants::ONEACCESS_DEDUPLICATED_USERS_TABLE;

		$result = null;

		// Sanitize inputs (basic validation)
		$user_email = sanitize_email( $user_email );
		if ( ! is_email( $user_email ) ) {
			return false;
		}
		$fullname = sanitize_text_field( $fullname );

		// split fullname into first and last name.
		$first_name = '';
		$last_name  = '';
		$name_parts = explode( ' ', $fullname, 2 );
		if ( count( $name_parts ) === 2 ) {
			$first_name = sanitize_text_field( $name_parts[0] );
			$last_name  = sanitize_text_field( $name_parts[1] );
		} else {
			$first_name = $fullname;
		}

		// Ensure $site_info is a single site entry (assoc array)
		if ( ! isset( $site_info['user_id'] ) || ! isset( $site_info['site_url'] ) || ! isset( $site_info['site_name'] ) || ! isset( $site_info['roles'] ) ) {
			return false; // Invalid site_info structure
		}

		// check if user with same email already exists.
		$existing_user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE email = %s",
				$user_email
			),
			ARRAY_A
		);

		$current_time = current_time( 'mysql' );

		if ( $existing_user ) {
			// Decode existing sites_info (always an array)
			$existing_sites = json_decode( $existing_user['sites_info'], true ) ?: array();

			// Check if this exact site/user_id combo already exists (to avoid duplicates)
			$site_exists = false;
			foreach ( $existing_sites as $existing_site ) {
				if ( isset( $existing_site['user_id'] ) && $existing_site['user_id'] == $site_info['user_id'] &&
				isset( $existing_site['site_url'] ) && $existing_site['site_url'] == $site_info['site_url'] ) {
					$site_exists = true;
					break;
				}
			}

			if ( ! $site_exists ) {
				// Append new site to array
				$existing_sites[] = $site_info;
			}

			// Re-encode as array
			$sites_info_json = wp_json_encode( $existing_sites );

			// Always update (names or new site)
			$result = $wpdb->update(
				$table_name,
				array(
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'sites_info' => $sites_info_json,
					'updated_at' => $current_time,
				),
				array( 'email' => $user_email ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%s' )
			);
		} else {
			// Insert new record: Wrap single site_info in array
			$sites_info_json = wp_json_encode( array( $site_info ) );

			$result = $wpdb->insert(
				$table_name,
				array(
					'email'      => $user_email,
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'sites_info' => $sites_info_json,
					'created_at' => $current_time,
					'updated_at' => $current_time,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);
		}

		return $result;
	}
}
