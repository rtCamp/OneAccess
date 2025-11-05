/**
 * WordPress dependencies
 */
import {
	__experimentalVStack as VStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	__experimentalHStack as HStack, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	TextControl,
	Button,
	Icon,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getStrengthColor, strengthWidths } from '../js/utils';

const PasswordComponent = ( ( {
	password,
	showPassword,
	setPassword,
	passwordRef,
	setShowPassword,
	passwordStrength,
	fetchStrongPassword,
} ) => {
	return (
		<VStack spacing="2" style={ { gap: '0px' } }>
			<HStack alignment="left" spacing="2" style={ { alignItems: 'flex-start' } }>
				<TextControl
					label={ __( 'Password*', 'oneaccess' ) }
					type={ showPassword ? 'text' : 'password' }
					value={ password }
					onChange={ ( value ) => {
						setPassword( value );
						passwordRef.current = value;
					} }
					required
					help={ __( 'Password must be at least 8 characters long. Use a mix of letters (upper & lower case), numbers, and special characters for better security.', 'oneaccess' ) }
					style={ { flex: 1 } }
				/>
				<Button
					onClick={ () => setShowPassword( ! showPassword ) }
					aria-label={ showPassword ? __( 'Hide password', 'oneaccess' ) : __( 'Show password', 'oneaccess' ) }
					style={ {
						marginTop: '1.5rem',
						height: '2rem',
						minWidth: 'auto',
					} }
					variant="secondary"
				>
					<Icon
						icon={ showPassword ? 'visibility' : 'hidden' }
						size={ 20 }
					/>
				</Button>
			</HStack>
			{ password && (
				<div style={ { marginBottom: '12px' } }>
					<div style={ { fontSize: '12px', color: '#6c757d' } }>
						{ __( 'Password Strength:', 'oneaccess' ) }{ ' ' }
						<span style={ { color: getStrengthColor( passwordStrength ), fontWeight: '500' } }>
							{ passwordStrength
								? passwordStrength.replace( '-', ' ' ).toUpperCase()
								: '' }
						</span>
					</div>
					<div
						style={ {
							height: '4px',
							width: '100%',
							backgroundColor: '#e1e5e9',
							borderRadius: '2px',
							overflow: 'hidden',
						} }
					>
						<div
							style={ {
								height: '100%',
								width: strengthWidths[ passwordStrength ] || strengthWidths.default,
								backgroundColor: getStrengthColor( passwordStrength ),
								transition: 'width 0.3s ease-in-out',
							} }
						/>
					</div>
				</div>
			) }
			<Button
				variant="secondary"
				onClick={ () => {
					fetchStrongPassword();
				} }
				style={ { width: 'fit-content' } }
			>
				{ __( 'Generate strong password', 'oneaccess' ) }
			</Button>
		</VStack>
	);
} );

export default PasswordComponent;
