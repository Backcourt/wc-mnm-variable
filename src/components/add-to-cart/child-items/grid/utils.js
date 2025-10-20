/**
 * Temporary fix to get first|last grid classes.
 *
 * @param index
 */
export const generateLoopClass = ( index ) => {
	const numColumns = WC_MNM_ADD_TO_CART_VARIATION_PARAMS.num_columns;

	if ( index % numColumns === 0 || numColumns === 1 ) {
		return 'first';
	}

	if ( ( index + 1 ) % numColumns === 0 ) {
		return 'last';
	}

	return '';
};
