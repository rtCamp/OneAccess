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
    public function setup_hooks():void{
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
        KEY email (email)
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
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}