<?php
/**
 * Settings class.
 * This class handles the settings page for the OneAccess plugin,
 *
 * @package OneAccess
 */

namespace OneAccess\Modules\Settings;

use OneAccess\Contracts\Interfaces\Registrable;
use OneAccess\Modules\Core\Assets;
use OneAccess\Modules\Core\User_Roles;
/**
 * Class Settings
 */
class Admin implements Registrable {
	/**
	 * The menu slug for the admin menu.
	 *
	 * @todo replace with a cross-plugin menu.
	 */
	public const MENU_SLUG = 'oneaccess';

	/**
	 * The screen ID for the settings page.
	 */
	public const SCREEN_ID = self::MENU_SLUG . '-settings';

	/**
	 * Path to the SVG logo for the menu.
	 *
	 * @todo Replace with actual logo.
	 * @var string
	 */
	private const SVG_LOGO_PATH = '';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_menu', [ $this, 'manage_users' ], 15 );
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 ); // 20 priority to make sure settings page respect its position.
		add_action( 'admin_menu', [ $this, 'remove_default_submenu' ], 999 );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );
		add_action( 'admin_footer', [ $this, 'inject_site_selection_modal' ] );

		add_filter( 'plugin_action_links_' . ONEACCESS_PLUGIN_BASENAME, [ $this, 'add_action_links' ], 2 );
		add_filter( 'admin_body_class', [ $this, 'add_body_classes' ] );
	}

	/**
	 * Add a settings page.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'OneAccess', 'oneaccess' ),
			__( 'OneAccess', 'oneaccess' ),
			'manage_options',
			self::MENU_SLUG,
			'__return_null',
			self::SVG_LOGO_PATH,
			2
		);
	}

	/**
	 * Register the settings page.
	 */
	public function add_submenu(): void {

		// Add the settings submenu page.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'oneaccess' ),
			__( 'Settings', 'oneaccess' ),
			'manage_options',
			self::SCREEN_ID,
			[ $this, 'screen_callback' ],
			999
		);
	}

	/**
	 * Add the Manage Users submenu page for governing sites.
	 */
	public function manage_users(): void {
		if ( ! Settings::is_governing_site() ) {
			return;
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Manage Users', 'oneaccess' ),
			'<span class="oneaccess-manage-user-page">' . __( 'Manage Users', 'oneaccess' ) . '</span>',
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_user_manager' ],
			2
		);
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_user_manager(): void {
		// Check if the user has permission to manage options.
		$current_user = wp_get_current_user();
		if ( ! current_user_can( 'manage_options' ) || ! Settings::is_governing_site() || ! in_array( User_Roles::NETWORK_ADMIN, $current_user->roles, true ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'oneaccess' ) );
		}
		?>
		<div class="wrap">
			<h1 class="oneaccess-heading"><?php esc_html_e( 'Manage Users', 'oneaccess' ); ?></h1>
			<div id="oneaccess-manage-user"></div>
		</div>
		<?php
	}

	/**
	 * Remove the default submenu added by WordPress.
	 */
	public function remove_default_submenu(): void {
		if ( Settings::is_governing_site() ) {
			return;
		}
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Admin page content callback.
	 */
	public function screen_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'oneaccess' ); ?></h1>
			<div id="oneaccess-settings-page"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen ) {
			return;
		}

		if ( ( 'plugins.php' === $hook || str_contains( $hook, 'plugins' ) || str_contains( $hook, 'oneaccess' ) ) && 'plugins-network' !== $current_screen->id ) {
			// Enqueue the onboarding modal.
			$this->enqueue_onboarding_scripts();
		}

		if ( strpos( $hook, 'oneaccess-settings' ) !== false ) {
			$this->enqueue_settings_scripts();
		}

		if ( strpos( $hook, 'toplevel_page_oneaccess' ) !== false ) {
			$this->enqueue_manage_users_scripts();
		}

		// @todo Move other scripts from Assets to here.
	}

	/**
	 * Inject site selection modal into the admin footer.
	 */
	public function inject_site_selection_modal(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return;
		}

		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return;
		}

		?>
		<div class="wrap">
			<div id="oneaccess-site-selection-modal" class="oneaccess-modal"></div>
		</div>
		<?php
	}

	/**
	 * Add action links to the settings on the plugins page.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function add_action_links( $links ): array {
		// Defense against other plugins.
		if ( ! is_array( $links ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Expected an array.', 'oneaccess' ), '1.0.0' );

			$links = [];
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ) ),
			__( 'Settings', 'oneaccess' )
		);

		return $links;
	}

	/**
	 * Add body classes for the admin area.
	 *
	 * @param string $classes Existing body classes.
	 */
	public function add_body_classes( $classes ): string {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return $classes;
		}

		// Cast to string in case it's null.
		$classes = $this->add_body_class_for_modal( (string) $classes, $current_screen );
		$classes = $this->add_body_class_for_missing_sites( (string) $classes, $current_screen );

		return $classes;
	}

	/**
	 * Enqueue the scripts and styles for the settings screen.
	 */
	public function enqueue_settings_scripts(): void {
		wp_localize_script(
			Assets::SETTINGS_SCRIPT_HANDLE,
			'OneAccessSettings',
			Assets::get_localized_data(),
		);

		wp_enqueue_script( Assets::SETTINGS_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::SETTINGS_SCRIPT_HANDLE );
	}

	/**
	 * Enqueue scripts and styles for the onboarding modal.
	 */
	private function enqueue_onboarding_scripts(): void {
		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return;
		}

		wp_localize_script(
			Assets::ONBOARDING_SCRIPT_HANDLE,
			'OneAccessSettings',
			[
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'setup_url' => admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ),
				'site_type' => Settings::get_site_type(), // @todo We can probably remove this.
			]
		);

		wp_enqueue_script( Assets::ONBOARDING_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::ONBOARDING_SCRIPT_HANDLE );
	}

	/**
	 * Enqueue scripts and styles for the Manage Users screen.
	 */
	private function enqueue_manage_users_scripts(): void {

		$user_roles = wp_roles()->get_names();

			// remove network admin role from available roles.
		if ( isset( $user_roles[ User_Roles::NETWORK_ADMIN ] ) ) {
			unset( $user_roles[ User_Roles::NETWORK_ADMIN ] );
		}

			// add brand admin role if not exists.
		if ( ! isset( $user_roles[ User_Roles::BRAND_ADMIN ] ) ) {
			$user_roles[ User_Roles::BRAND_ADMIN ] = __( 'Brand Admin', 'oneaccess' );
		}

		wp_localize_script(
			Assets::MANAGE_USERS_SCRIPT_HANDLE,
			'OneAccess',
			array_merge(
				Assets::get_localized_data(),
				[
					'availableRoles' => $user_roles,
				]
			)
		);

		wp_enqueue_script( Assets::MANAGE_USERS_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::MANAGE_USERS_SCRIPT_HANDLE );
	}

	/**
	 * Add body class if the modal is going to be shown.
	 *
	 * @param string     $classes        Existing body classes.
	 * @param \WP_Screen $current_screen Current screen object.
	 */
	private function add_body_class_for_modal( string $classes, \WP_Screen $current_screen ): string {
		if ( 'plugins' !== $current_screen->base ) {
			return $classes;
		}

		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return $classes;
		}

		// Add oneaccess-site-selection-modal class to body.
		$classes .= ' oneaccess-site-selection-modal ';
		return $classes;
	}

	/**
	 * Add body class for missing sites.
	 *
	 * @param string     $classes Existing body classes.
	 * @param \WP_Screen $current_screen Current screen object.
	 */
	private function add_body_class_for_missing_sites( string $classes, \WP_Screen $current_screen ): string {
		// Bail if the shared sites are already set.
		$shared_sites = Settings::get_shared_sites();
		if ( ! empty( $shared_sites ) ) {
			return $classes;
		}

		$classes .= ' oneaccess-missing-brand-sites ';
		return $classes;
	}
}
