<?php
/**
 * Enqueue assets for OneAccess plugin.
 *
 * @package OneAccess
 */

namespace OneAccess;

use OneAccess\Plugin_Configs\Constants;
use OneAccess\Traits\Singleton;

/**
 * Enqueue assets for OneAccess.
 */
class Assets {

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
		// Enqueue Admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 99 );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Admin page name.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook_suffix ): void {

		if ( strpos( $hook_suffix, 'oneaccess-settings' ) !== false ) {
			remove_all_actions( 'admin_notices' );
			$this->register_script(
				'oneaccess-settings-script',
				'js/settings.js',
			);

			wp_localize_script(
				'oneaccess-settings-script',
				'OneAccessSettings',
				array(
					'restUrl'   => esc_url( home_url( '/wp-json' ) ),
					'apiKey'    => get_option( Constants::ONEACCESS_API_KEY, 'default_api_key' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
				)
			);

			wp_enqueue_script( 'oneaccess-settings-script' );

		}

		if ( strpos( $hook_suffix, 'toplevel_page_oneaccess' ) !== false ) {
			// remove all admin notices.
			remove_all_actions( 'admin_notices' );

			$this->register_script(
				'oneaccess-manage-user-script',
				'js/manage-user.js',
			);

			$user_roles = wp_roles()->get_names();

			// remove network admin role from available roles.
			if ( isset( $user_roles['network_admin'] ) ) {
				unset( $user_roles['network_admin'] );
			}

			// add brand admin role if not exists.
			if ( ! isset( $user_roles['brand_admin'] ) ) {
				$user_roles['brand_admin'] = __( 'Brand Admin', 'oneaccess' );
			}

			wp_localize_script(
				'oneaccess-manage-user-script',
				'OneAccess',
				array(
					'restUrl'        => esc_url( home_url( '/wp-json' ) ),
					'apiKey'         => get_option( Constants::ONEACCESS_API_KEY, 'default_api_key' ),
					'restNonce'      => wp_create_nonce( 'wp_rest' ),
					'availableRoles' => $user_roles,
				)
			);

			wp_enqueue_script( 'oneaccess-manage-user-script' );

			// enqueue user manager styles.
			$this->register_style( 'oneaccess-manage-user-style', 'css/manage-user.css' );
			wp_enqueue_style( 'oneaccess-manage-user-style' );

		}

		if ( strpos( $hook_suffix, 'plugins' ) !== false ) {
			remove_all_actions( 'admin_notices' );
			$this->register_script(
				'oneaccess-setup-script',
				'js/plugin.js',
			);

			wp_localize_script(
				'oneaccess-setup-script',
				'OneAccessSettings',
				array(
					'restUrl'   => esc_url( home_url( '/wp-json' ) ),
					'apiKey'    => get_option( Constants::ONEACCESS_API_KEY, 'default_api_key' ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'setupUrl'  => admin_url( 'admin.php?page=oneaccess-settings' ),
				)
			);

			wp_enqueue_script( 'oneaccess-setup-script' );

		}

		// load users.php specific styles.
		if ( Utils::is_brand_site() && strpos( $hook_suffix, 'users.php' ) !== false ) {
			$this->register_style(
				'oneaccess-admin-user-style',
				'css/admin-user.css'
			);
			wp_enqueue_style( 'oneaccess-admin-user-style' );
		}

		// load script to user profile & edit user -> profile.php & user-edit.php.
		if ( Utils::is_brand_site() && ( ( strpos( $hook_suffix, 'profile.php' ) !== false ) || ( strpos( $hook_suffix, 'user-edit.php' ) !== false ) ) ) {
			$this->register_script(
				'oneaccess-user-profile-script',
				'js/user-profile.js',
			);

			$current_user_profile_request = Utils::get_users_profile_request_data();
			$current_user                 = isset( $_GET['user_id'] ) ? filter_input( INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT ) : get_current_user_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is to know on which user profile page we are.      
			if ( ! is_array( $current_user_profile_request ) ) {
				$current_user_profile_request = array();
			}
			$current_user_request = isset( $current_user_profile_request[ $current_user ] ) ? $current_user_profile_request[ $current_user ] : array();

			wp_localize_script(
				'oneaccess-user-profile-script',
				'OneAccessProfile',
				array(
					'restUrl'   => esc_url( home_url( '/wp-json' ) ),
					'restNonce' => wp_create_nonce( 'wp_rest' ),
					'userId'    => $current_user,
					'request'   => $current_user_request,
				)
			);

			wp_enqueue_script( 'oneaccess-user-profile-script' );

			$this->register_style(
				'oneaccess-admin-user-style',
				'css/admin-user.css'
			);
			wp_enqueue_style( 'oneaccess-admin-user-style' );
		}

		// load admin styles.
		$this->register_style( 'oneaccess-admin-style', 'css/admin.css' );
		wp_enqueue_style( 'oneaccess-admin-style' );
	}

	/**
	 * Get asset dependencies and version info from {handle}.asset.php if exists.
	 *
	 * @param string $file File name.
	 * @param array  $deps Script dependencies to merge with.
	 * @param string $ver  Asset version string.
	 *
	 * @return array
	 */
	public function get_asset_meta( $file, $deps = array(), $ver = false ): mixed {
		$asset_meta_file = sprintf( '%s/js/%s.asset.php', untrailingslashit( ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/assets/build' ), basename( $file, '.' . pathinfo( $file )['extension'] ) );
		$asset_meta      = is_readable( $asset_meta_file )
			? require $asset_meta_file
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $file, $ver ),
			);

		$asset_meta['dependencies'] = array_merge( $deps, $asset_meta['dependencies'] );

		return $asset_meta;
	}

	/**
	 * Register a new script.
	 *
	 * @param string           $handle    Name of the script. Should be unique.
	 * @param string|bool      $file       script file, path of the script relative to the assets/build/ directory.
	 * @param array            $deps      Optional. An array of registered script handles this script depends on. Default empty array.
	 * @param string|bool|null $ver       Optional. String specifying script version number, if not set, filetime will be used as version number.
	 * @param bool             $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 *                                    Default 'false'.
	 * @return bool Whether the script has been registered. True on success, false on failure.
	 */
	public function register_script( $handle, $file, $deps = array(), $ver = false, $in_footer = true ): bool {

		$file_path = sprintf( '%s/%s', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/assets/build', $file );

		if ( ! \file_exists( $file_path ) ) {
			return false;
		}

		$src        = sprintf( ONEACCESS_PLUGIN_LOADER_FEATURES_URL . '/assets/build/%s', $file );
		$asset_meta = $this->get_asset_meta( $file, $deps );

		// register each dependency styles.
		if ( ! empty( $asset_meta['dependencies'] ) ) {
			foreach ( $asset_meta['dependencies'] as $dependency ) {
				wp_enqueue_style( $dependency );
			}
		}

		return wp_register_script( $handle, $src, $asset_meta['dependencies'], $asset_meta['version'], $in_footer );
	}

	/**
	 * Register a CSS stylesheet.
	 *
	 * @param string           $handle Name of the stylesheet. Should be unique.
	 * @param string|bool      $file    style file, path of the script relative to the assets/build/ directory.
	 * @param array            $deps   Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
	 * @param string|bool|null $ver    Optional. String specifying script version number, if not set, filetime will be used as version number.
	 * @param string           $media  Optional. The media for which this stylesheet has been defined.
	 *                                 Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
	 *                                 '(orientation: portrait)' and '(max-width: 640px)'.
	 *
	 * @return bool Whether the style has been registered. True on success, false on failure.
	 */
	public function register_style( $handle, $file, $deps = array(), $ver = false, $media = 'all' ): bool {

		$file_path = sprintf( '%s/%s', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/assets/build', $file );

		if ( ! \file_exists( $file_path ) ) {
			return false;
		}

		$src     = sprintf( ONEACCESS_PLUGIN_LOADER_FEATURES_URL . '/assets/build/%s', $file );
		$version = $this->get_file_version( $file, $ver );

		return wp_register_style( $handle, $src, $deps, $version, $media );
	}

	/**
	 * Get file version.
	 *
	 * @param string             $file File path.
	 * @param int|string|boolean $ver  File version.
	 *
	 * @return bool|false|int
	 */
	public function get_file_version( $file, $ver = false ): bool|int|string {
		if ( ! empty( $ver ) ) {
			return $ver;
		}

		$file_path = sprintf( '%s/%s', ONEACCESS_PLUGIN_LOADER_FEATURES_PATH . '/assets/build', $file );

		return file_exists( $file_path ) ? filemtime( $file_path ) : false;
	}
}
