/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { getSetting } from '@woocommerce/settings';

/**
 * Fetch a product object from the Store API.
 *
 * @param {number} containerId Id of the product|variation to retrieve.
 */
export function getContainerById( containerId ) {
	return async ( { dispatch } ) => {
		try {

			// Only attempt to resolve if there's a product ID here.
			if ( containerId ) {

				const preloadedVariableData = getSetting(
					'wcMNMVariableSettings',
					[]
				);

				let container = preloadedVariableData.find(
					( obj ) => obj.id === containerId
				);

				if ( typeof container !== 'object' ) {
					container = await apiFetch( {
						path: `/wc/store/v1/products/${ containerId }`,
					} );

				}

				dispatch.hydrateContainer( container );

				return container;

			}
		} catch ( error ) {
			// @todo: Handle an error here eventually.
			console.error( error );
			return {};
		}
	};
}
