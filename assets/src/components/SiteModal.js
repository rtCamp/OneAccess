import { useState, useMemo } from '@wordpress/element';
import {
	Modal,
	TextControl,
	TextareaControl,
	Button,
	Notice,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { isValidUrl } from '../js/utils';

const SiteModal = ( { formData, setFormData, onSubmit, onClose, editing, originalData = {} } ) => {
	const [ errors, setErrors ] = useState( {
		siteName: '',
		siteUrl: '',
		apiKey: '',
		message: '',
	} );
	const [ showNotice, setShowNotice ] = useState( false );
	const [ isProcessing, setIsProcessing ] = useState( false );

	// Check if form data has changed from original data (only for editing mode)
	const hasChanges = useMemo( () => {
		if ( ! editing ) {
			return true;
		} // Always allow submission for new sites

		return (
			formData.siteName !== originalData.siteName ||
			formData.siteUrl !== originalData.siteUrl ||
			formData.apiKey !== originalData.apiKey
		);
	}, [ editing, formData, originalData ] );

	const handleSubmit = async () => {
		// Validate inputs
		let siteUrlError = '';
		if ( ! formData.siteUrl.trim() ) {
			siteUrlError = __( 'Site URL is required.', 'oneaccess' );
		} else if ( ! isValidUrl( formData.siteUrl ) ) {
			siteUrlError = __( 'Enter a valid URL (must start with http or https).', 'oneaccess' );
		}

		const newErrors = {
			siteName: ! formData.siteName.trim() ? __( 'Site Name is required.', 'oneaccess' ) : '',
			siteUrl: siteUrlError,
			apiKey: ! formData.apiKey.trim() ? __( 'API Key is required.', 'oneaccess' ) : '',
			message: '',
		};

		// make sure site name is under 20 characters
		if ( formData.siteName.length > 20 ) {
			newErrors.siteName = __( 'Site Name must be under 20 characters.', 'oneaccess' );
		}

		setErrors( newErrors );
		const hasErrors = Object.values( newErrors ).some( ( err ) => err );

		if ( hasErrors ) {
			setShowNotice( true );
			return;
		}

		// Start processing
		setIsProcessing( true );
		setShowNotice( false );

		try {
			// Perform health-check
			const healthCheck = await fetch(
				`${ formData.siteUrl }/wp-json/oneaccess/v1/health-check`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-OneAccess-Token': formData.apiKey,
					},
				},
			);

			const healthCheckData = await healthCheck.json();
			if ( ! healthCheckData.success ) {
				setErrors( {
					...newErrors,
					message: __( 'Health check failed, please verify API key and make sure there\'s no governing site connected.', 'oneaccess' ),
				} );
				setShowNotice( true );
				setIsProcessing( false );
				return;
			}

			setShowNotice( false );
			const submitResponse = await onSubmit();

			if ( ! submitResponse.ok ) {
				const errorData = await submitResponse.json();
				setErrors( {
					...newErrors,
					message: errorData.message || __( 'An error occurred while saving the site. Please try again.', 'oneaccess' ),
				} );
				setShowNotice( true );
			}
			if ( submitResponse?.data?.status === 400 ) {
				setErrors( {
					...newErrors,
					message: submitResponse?.message || __( 'An error occurred while saving the site. Please try again.', 'oneaccess' ),
				} );
				setShowNotice( true );
			}
		} catch ( error ) {
			setErrors( {
				...newErrors,
				message: __( 'An unexpected error occurred. Please try again.', 'oneaccess' ),
			} );
			setShowNotice( true );
			setIsProcessing( false );
			return;
		}

		setIsProcessing( false );
	};

	// Button should be disabled if:
	// 1. Currently processing, OR
	// 2. Required fields are empty, OR
	// 3. In editing mode and no changes have been made
	const isButtonDisabled = isProcessing ||
		! formData.siteName ||
		! formData.siteUrl ||
		! formData.apiKey ||
		( editing && ! hasChanges );

	return (
		<Modal
			title={ editing ? __( 'Edit Brand Site', 'oneaccess' ) : __( 'Add Brand Site', 'oneaccess' ) }
			onRequestClose={ onClose }
			size="medium"
			shouldCloseOnClickOutside={ true }
		>
			{ showNotice && (
				<Notice
					status="error"
					isDismissible={ true }
					onRemove={ () => setShowNotice( false ) }
				>
					{ errors.message || errors.siteName || errors.siteUrl || errors.apiKey }
				</Notice>
			) }

			<TextControl
				label={ __( 'Site Name*', 'oneaccess' ) }
				value={ formData.siteName }
				onChange={ ( value ) => setFormData( { ...formData, siteName: value } ) }
				error={ errors.siteName }
				help={ __( 'This is the name of the site that will be registered.', 'oneaccess' ) }
			/>
			<TextControl
				label={ __( 'Site URL*', 'oneaccess' ) }
				value={ formData.siteUrl }
				onChange={ ( value ) => setFormData( { ...formData, siteUrl: value } ) }
				error={ errors.siteUrl }
				help={ __( 'It must start with http or https and end with /, like: https://oneaccess.com/', 'oneaccess' ) }
			/>
			<TextareaControl
				label={ __( 'API Key*', 'oneaccess' ) }
				value={ formData.apiKey }
				onChange={ ( value ) => setFormData( { ...formData, apiKey: value } ) }
				error={ errors.apiKey }
				help={ __( 'This is the api key that will be used to authenticate the site for OneAccess.', 'oneaccess' ) }
			/>

			<Button
				variant="primary"
				onClick={ handleSubmit }
				className={ isProcessing ? 'is-busy' : '' }
				disabled={ isButtonDisabled }
				style={ { marginTop: '12px' } }
			>
				{ (
					editing ? __( 'Update Site', 'oneaccess' ) : __( 'Add Site', 'oneaccess' )
				) }
			</Button>
		</Modal>
	);
};

export default SiteModal;
