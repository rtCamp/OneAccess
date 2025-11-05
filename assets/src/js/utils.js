import DOMPurify from 'dompurify';

/**
 * Helper function to extract initials from a name.
 *
 * @param {string} name - The name to extract initials from.
 * @return {string} The extracted initials (up to 2 characters).
 */
const getInitials = ( name ) => {
	// Handle empty or invalid names
	if ( ! name || typeof name !== 'string' ) {
		return '?';
	}

	// Trim the name and convert to proper case
	const trimmedName = name.trim();
	if ( ! trimmedName ) {
		return '?';
	}

	// Split the name by spaces and other separators
	const parts = trimmedName
		.split( /[\s-_,.]+/ )
		.filter( ( part ) => part.length > 0 );

	// For single word names
	if ( parts.length === 1 ) {
		// If name is a single character, return that character
		if ( parts[ 0 ].length === 1 ) {
			return parts[ 0 ].toUpperCase();
		}
		// Otherwise return first two characters
		return parts[ 0 ].substring( 0, 2 ).toUpperCase();
	}

	// For multi-word names, take first letter of first two parts
	return (
		parts[ 0 ].charAt( 0 ) + ( parts[ 1 ] ? parts[ 1 ].charAt( 0 ) : '' )
	).toUpperCase();
};

/**
 * Helper function to validate if a string is a well-formed URL.
 *
 * @param {string} str - The string to validate as a URL.
 *
 * @return {boolean} True if the string is a valid URL, false otherwise.
 */
const isURL = ( str ) => {
	const pattern = new RegExp(
		'^https?:\\/\\/' +
		'(?:[a-z\\d](?:[a-z\\d-]*[a-z\\d])?\\.)?' +
		'[a-z\\d](?:[a-z\\d-]*[a-z\\d])?\\.' +
		'[a-z]{2,}' +
		'(?::\\d+)?' +
		'(?:\\/[^\\s]*)?' +
		'$', 'i',
	);
	return pattern.test( str );
};

/**
 * Validates if a given string is a valid URL.
 *
 * @param {string} url - The URL string to validate.
 *
 * @return {boolean} True if the URL is valid, false otherwise.
 */
const isValidUrl = ( url ) => {
	try {
		const parsedUrl = new URL( url );
		return isURL( parsedUrl.href );
	} catch ( e ) {
		return false;
	}
};

/**
 * Sanitizes a given string by removing all HTML tags.
 *
 * @param {string} item - The string to sanitize.
 *
 * @return {string} The sanitized string with all HTML tags removed.
 */
const PurifyElement = ( item ) => {
	return DOMPurify.sanitize( item, { ALLOWED_TAGS: [] } );
};

/**
 * Validates if a given string is a valid email address.
 *
 * @param {string} email - The email string to validate.
 *
 * @return {boolean} True if the email is valid, false otherwise.
 */
const isValidEmail = ( email ) => {
	const pattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
	return pattern.test( email );
};

/**
 * Checks the strength of a given password.
 *
 * @param {string} password - The password to check.
 *
 * @return {string} The strength of the password: 'very-weak', 'weak', 'medium', or 'strong'.
 */

const checkPasswordStrength = ( password ) => {
	let strength = 'weak';
	if ( password.length >= 12 && /[A-Z]/.test( password ) && /[0-9]/.test( password ) && /[^A-Za-z0-9]/.test( password ) ) {
		strength = 'strong';
	} else if ( password.length >= 8 && /[A-Z]/.test( password ) && /[a-z]/.test( password ) && /[0-9]/.test( password ) ) {
		strength = 'medium';
	} else if ( password.length >= 8 ) {
		strength = 'weak';
	} else if ( password.length > 0 ) {
		strength = 'very-weak';
	}
	return strength;
};

/**
 * Mapping of password strength levels to their corresponding width percentages.
 *
 * @return {Object} An object mapping password strength levels to width percentages.
 *
 */
const strengthWidths = {
	'very-weak': '25%',
	weak: '50%',
	medium: '75%',
	strong: '100%',
	default: '0%',
};

/**
 * Gets the color associated with a given password strength.
 *
 * @param {string} passwordStrength - The strength of the password.
 *
 * @return {string} The color code associated with the password strength.
 */

const getStrengthColor = ( passwordStrength ) => {
	switch ( passwordStrength ) {
		case 'very-weak':
			return '#dc3545';
		case 'weak':
			return '#ff6b6b';
		case 'medium':
			return '#ffc107';
		case 'strong':
			return '#28a745';
		default:
			return '#e1e5e9';
	}
};

export {
	isValidEmail,
	getInitials,
	isURL,
	isValidUrl,
	PurifyElement,
	checkPasswordStrength,
	strengthWidths,
	getStrengthColor,
};
