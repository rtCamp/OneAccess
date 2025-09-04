<?php
/**
 * Rudimentary plugin file.
 *
 * @package OneAccess
 */

namespace OneAccess;

use OneAccess\Plugin_Configs\{Constants, Secret_Key };
use OneAccess\Traits\Singleton;
use OneAccess\User\{ Profile_Request, Notice };

/**
 * Main plugin class which initializes the plugin.
 */
class Plugin {

	/**
	 * Use Singleton trait.
	 */
	use Singleton;

	/**
	 * Protected class constructor
	 */
	protected function __construct() {
		$this->load_plugin_classes();
		$this->load_plugin_configs();
		$this->load_user_configs();
	}

	/**
	 * Load plugin classes
	 */
	public function load_plugin_classes(): void {
		Assets::get_instance();
		Hooks::get_instance();
		Settings::get_instance();
		REST::get_instance();
		User_Roles::get_instance();
	}

	/**
	 * Load plugin configs
	 */
	public function load_plugin_configs(): void {
		Secret_Key::get_instance();
		Constants::get_instance();
	}

	/**
	 * Load user configs
	 */
	public function load_user_configs(): void {
		Profile_Request::get_instance();
		Notice::get_instance();
	}
}
