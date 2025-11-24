<?php
/**
 * When ever user is created/updated perform action to notify governing site.
 *
 * @package OneAccess
 */

namespace OneAccess;

use OneAccess\Plugin_Configs\DB;
use OneAccess\Traits\Singleton;

/**
 * Class Actions
 */
class Actions {

	/**
	 * Use Singleton Trait
	 */
	use Singleton;

	/**
	 * Constructor
	 */
	protected function __construct() {
		$this->setup_hooks();
	}

	/**
	 * Setup hooks
	 *
	 * @return void
	 */
	protected function setup_hooks(): void {

		// oneaccess_add_deduplicated_users to add users to deduplicated users table.
		add_action( 'oneaccess_add_deduplicated_users', array( $this, 'handle_deduplicated_users' ), 10, 1 );
	}

	/**
	 * Handle adding deduplicated user to the database.
	 *
	 * @param array $users_data Users data to be added.
	 * @return void
	 */
	public function handle_deduplicated_users( array $users_data ): void {
		DB::add_deduplicated_users( $users_data );
	}
}
