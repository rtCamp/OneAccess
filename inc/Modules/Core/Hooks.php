<?php
/**
 * Hooks related to governing site & brand sites.
 *
 * @package OneAccess
 */

namespace OneAccess\Modules\Core;

use OneAccess\Contracts\Interfaces\Registrable;
use OneAccess\Modules\Rest\Actions_Controller;
use OneAccess\Modules\Settings\Settings;

/**
 * Class Hooks
 */
class Hooks implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {

		// early return if this is not a brand site.
		if ( ! Settings::is_consumer_site() ) {
			return;
		}

		$actions_controller = new Actions_Controller();
		// trigger oneaccess_governing_site_configured action to send uses data to governing site.
		add_action( 'oneaccess_governing_site_configured', [ $actions_controller, 'send_users_for_deduplication' ] );

		// when user is created then send it to governing site.
		add_action( 'user_register', [ $actions_controller, 'send_single_user_for_deduplication' ] );
	}
}
