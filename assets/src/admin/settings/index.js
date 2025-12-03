/**
 * WordPress dependencies
 */
import { useState, useEffect, createRoot } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, Snackbar } from '@wordpress/components';

/**
 * Internal dependencies
 */
import SiteTable from '../../components/SiteTable';
import SiteModal from '../../components/SiteModal';
import SiteSettings from '../../components/SiteSettings';

const API_NAMESPACE = OneAccessSettings.restUrl + '/oneaccess/v1';
const NONCE = OneAccessSettings.restNonce;
const API_KEY = OneAccessSettings.api_key;

const OneAccessSettingsPage = () => {
	const [ siteType, setSiteType ] = useState( '' );
	const [ showModal, setShowModal ] = useState( false );
	const [ editingIndex, setEditingIndex ] = useState( null );
	const [ sites, setSites ] = useState( [] );
	const [ formData, setFormData ] = useState( { name: '', url: '', api_key: '' } );
	const [ notice, setNotice ] = useState( {
		type: 'success',
		message: '',
	} );

	useEffect( () => {
		const token = ( NONCE );

		const fetchData = async () => {
			try {
				const [ siteTypeRes, sitesRes ] = await Promise.all( [
					fetch( `${ API_NAMESPACE }/site-type`, {
						headers: {
							'Content-Type': 'application/json',
							'X-WP-NONCE': token,
							'X-OneAccess-Token': API_KEY,
						},
					} ),
					fetch( `${ API_NAMESPACE }/shared-sites`, {
						headers: {
							'Content-Type': 'application/json',
							'X-WP-NONCE': token,
							'X-OneAccess-Token': API_KEY,
						},
					} ),
				] );

				const siteTypeData = await siteTypeRes.json();
				const sitesData = await sitesRes.json();

				if ( siteTypeData?.site_type ) {
					setSiteType( siteTypeData.site_type );
				}
				if ( Array.isArray( sitesData?.sites_data ) ) {
					setSites( sitesData?.sites_data );
				}
			} catch {
				setNotice( {
					type: 'error',
					message: __( 'Error fetching site type or Brand sites.', 'oneaccess' ),
				} );
			}
		};

		fetchData();
	}, [] );

	useEffect( () => {
		if ( siteType === 'governing-site' && sites.length > 0 ) {
			document.body.classList.remove( 'oneaccess-missing-brand-sites' );
		}
	}, [ sites, siteType ] );

	const handleFormSubmit = async () => {
		const updated = editingIndex !== null
			? sites.map( ( item, i ) => ( i === editingIndex ? formData : item ) )
			: [ ...sites, formData ];

		const token = ( NONCE );
		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
					'X-OneAccess-Token': API_KEY,
				},
				body: JSON.stringify( { sites_data: updated } ),
			} );

			const result = await response.json();

			if ( ! response.ok ) {
				console.error( 'Error saving Brand site:', response.statusText ); // eslint-disable-line no-console
				return response;
			}

			const sitesData = result.sites_data || [];
			setSites( sitesData );

			if ( sitesData.length === 0 ) {
				window.location.reload();
			}

			setNotice( {
				type: 'success',
				message: __( 'Brand Site saved successfully.', 'oneaccess' ),
			} );
			return response;
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error saving Brand site. Please try again later.', 'oneaccess' ),
			} );
		} finally {
			setFormData( { name: '', url: '', api_key: '' } );
			setShowModal( false );
			setEditingIndex( null );
		}
	};

	const handleDelete = async ( index ) => {
		const updated = sites.filter( ( _, i ) => i !== index );
		const token = ( NONCE );

		try {
			const response = await fetch( `${ API_NAMESPACE }/shared-sites`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-NONCE': token,
					'X-OneAccess-Token': API_KEY,
				},
				body: JSON.stringify( { sites_data: updated } ),
			} );
			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __( 'Failed to delete Brand site. Please try again.', 'oneaccess' ),
				} );
				return;
			}
			setNotice( {
				type: 'success',
				message: __( 'Brand Site deleted successfully.', 'oneaccess' ),
			} );
			setSites( updated );
			if ( updated.length === 0 ) {
				window.location.reload();
			} else {
				document.body.classList.remove( 'oneaccess-missing-brand-sites' );
			}
		} catch {
			setNotice( {
				type: 'error',
				message: __( 'Error deleting Brand site. Please try again later.', 'oneaccess' ),
			} );
		}
	};

	return (
		<>
			<>
				<Notice
					status="info"
					isDismissible={ false }
				>
					{ __( 'Note: To use OneAccess plugin, your role must be Network Admin on Governing site and Brand Admin on Brand site.', 'oneaccess' ) }
				</Notice>
			</>
			<>
				{ notice?.message?.length > 0 &&
					<Snackbar
						status={ notice?.type ?? 'success' }
						isDismissible={ true }
						onRemove={ () => setNotice( null ) }
						className={ notice?.type === 'error' ? 'oneaccess-error-notice' : 'oneaccess-success-notice' }
					>
						{ notice?.message }
					</Snackbar>
				}
			</>

			{
				siteType === 'brand-site' && (
					<SiteSettings />
				)
			}

			{ siteType === 'governing-site' && (
				<SiteTable sites={ sites } onEdit={ setEditingIndex } onDelete={ handleDelete } setFormData={ setFormData } setShowModal={ setShowModal } />
			) }

			{ showModal && (
				<SiteModal
					formData={ formData }
					setFormData={ setFormData }
					onSubmit={ handleFormSubmit }
					onClose={ () => {
						setShowModal( false );
						setEditingIndex( null );
						setFormData( { name: '', url: '', api_key: '' } );
					} }
					editing={ editingIndex !== null }
					originalData={ sites[ editingIndex ] }
				/>
			) }
		</>
	);
};

// Render to Gutenberg admin page with ID: oneaccess-settings-page
const target = document.getElementById( 'oneaccess-settings-page' );
if ( target ) {
	const root = createRoot( target );
	root.render( <OneAccessSettingsPage /> );
}
