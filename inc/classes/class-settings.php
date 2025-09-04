<?php
/**
 * Create OneAccess settings page.
 *
 * @package OneAccess
 */

namespace OneAccess;

use OneAccess\Settings\{ Shared_Sites, Brand_Site };
use OneAccess\Traits\Singleton;

/**
 * Class Settings
 */
class Settings {

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
		Shared_Sites::get_instance();
		Brand_Site::get_instance();
	}
}
