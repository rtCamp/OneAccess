/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import SettingsPage from './page';

export type SiteType = 'governing-site' | 'brand-site' | '';

interface OneAccessSettingsType {
	restUrl: string;
	restNonce: string;
	api_key: string;
	settingsLink: string;
	siteType: SiteType;
}

declare global {
	interface Window {
		OneAccessSettings: OneAccessSettingsType;
	}
}

// Render to Gutenberg admin page with ID: oneaccess-settings-page
const target = document.getElementById( 'oneaccess-settings-page' );
if ( target ) {
	const root = createRoot( target );
	root.render( <SettingsPage /> );
}
