<?php
/**
 * Add actions and filters for OneAccess plugin.
 *
 * @package OneAccess
 */

namespace OneAccess;

use OneAccess\Plugin_Configs\Constants;
use OneAccess\Traits\Singleton;

/**
 * Class Hooks initializes the actions and filters.
 */
class Hooks {


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
		// create global variable called oneaccess_sites which has info like site name, site url, site id, gh_repo, etc.
		add_action( 'init', array( $this, 'create_global_oneaccess_sites' ), -1 );

		// add setup page link to plugins page.
		add_filter( 'plugin_action_links_' . ONEACCESS_PLUGIN_LOADER_PLUGIN_BASENAME, array( $this, 'add_setup_page_link' ) );

		// add container for modal for site selection on activation.
		add_action( 'admin_footer', array( $this, 'add_site_selection_modal' ) );

		// add body class for site selection modal.
		add_filter( 'admin_body_class', array( $this, 'add_body_class_for_modal' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class_for_missing_sites' ) );
	}

	/**
	 * Create global variable oneaccess_sites with site info.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string
	 */
	public function add_body_class_for_modal( $classes ): string {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return $classes;
		}

		if ( Utils::is_site_type_set() ) {
			return $classes;
		}

		// add oneaccess-site-selection-modal class to body.
		$classes .= ' oneaccess-site-selection-modal ';
		return $classes;
	}

	/**
	 * Add site selection modal to admin footer.
	 *
	 * @return void
	 */
	public function add_site_selection_modal(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return;
		}
		if ( ! defined( 'ONEACCESS_PLUGIN_LOADER_SLUG' ) ) {
			return;
		}

		if ( Utils::is_site_type_set() ) {
			return;
		}

		?>
		<div class="wrap">
			<div id="oneaccess-site-selection-modal" class="oneaccess-modal"></div>
		</div>
		<?php
	}

	/**
	 * Create global variable oneaccess_sites with site info.
	 *
	 * @return void
	 */
	public function create_global_oneaccess_sites(): void {
		if ( ! defined( 'ONEACCESS_PLUGIN_LOADER_SLUG' ) ) {
			return;
		}

		$sites = get_option( Constants::ONEACCESS_SHARED_SITES, array() );

		if ( ! empty( $sites ) && is_array( $sites ) ) {
			$oneaccess_sites = array();
			foreach ( $sites as $site ) {
				$oneaccess_sites[ $site['siteUrl'] ]  = array(
					'siteName' => $site['siteName'],
					'siteUrl'  => $site['siteUrl'],
					'apiKey'   => $site['apiKey'],
				);
				$oneaccess_sites[ $site['siteName'] ] = array(
					'siteName' => $site['siteName'],
					'siteUrl'  => $site['siteUrl'],
					'apiKey'   => $site['apiKey'],
				);
			}

			// Set it in GLOBALS.
			$GLOBALS['oneaccess_sites'] = $oneaccess_sites;
		}
	}

	/**
	 * Add setup page link to plugins page.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_setup_page_link( $links ): array {
		$setup_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=oneaccess-settings' ) ),
			__( 'Settings', 'oneaccess' )
		);
		array_unshift( $links, $setup_link );
		return $links;
	}

	/**
	 * Add body class for missing sites.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string
	 */
	public function add_body_class_for_missing_sites( $classes ): string {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return $classes;
		}

		// get oneaccess_shared_sites option.
		$shared_sites = get_option( Constants::ONEACCESS_SHARED_SITES, array() );

		// if shared_sites is empty or not an array, return the classes.
		if ( empty( $shared_sites ) || ! is_array( $shared_sites ) ) {
			$classes .= ' oneaccess-missing-brand-sites ';

			// remove plugin manager submenu.
			remove_submenu_page( 'oneaccess', 'oneaccess' );
			return $classes;
		}

		return $classes;
	}
}
