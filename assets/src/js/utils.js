const isURL = ( str ) => {
	const pattern = new RegExp(
		'^(https?:\\/\\/)?' +
			'(([a-z\\d]([a-z\\d-]*[a-z\\d])*):([a-z\\d-]*[a-z\\d])*@)?' +
			'((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.?)+[a-z]{2,}|' +
			'((\\d{1,3}\\.){3}\\d{1,3}))' +
			'(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*' +
			'(\\?[;&a-z\\d%_.~+=-]*)?' +
			'(\\#[-a-z\\d_]*)?$', 'i',
	);
	return pattern.test( str );
};

const isValidUrl = ( url ) => {
	try {
		const parsedUrl = new URL( url );
		return isURL( parsedUrl.href );
	} catch ( e ) {
		return false;
	}
};

const isValidEmail = ( email ) => {
	const pattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
	return pattern.test( email );
};

export {
	isURL,
	isValidUrl,
	isValidEmail,
};
