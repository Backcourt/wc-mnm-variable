/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { getSetting } from '@woocommerce/settings';

/**
 * Fetch a product object from the Store API.
 *
 * The new data structure contains:
 * - base_child_items: Shared static data (images, names, etc.) - hydrated once
 * - child_categories: Shared category data - hydrated once
 * - variations: Array of variation-specific overrides (min/max/step quantities)
 *
 * @param {number} containerId Id of the product|variation to retrieve.
 */
export function getContainerById( containerId ) {
	return async ( { dispatch, select } ) => {
		try {
			// Only attempt to resolve if there's a product ID here.
			if ( containerId ) {
				const preloadedVariableData = getSetting(
					'wcMNMVariableSettings',
					{}
				);

				// Hydrate base child items only once (they're shared across variations).
				const currentBaseChildItems = select.getBaseChildItems();
				if (
					( ! currentBaseChildItems || currentBaseChildItems.length === 0 ) &&
					preloadedVariableData.base_child_items &&
					preloadedVariableData.base_child_items.length > 0
				) {
					dispatch.hydrateBaseChildItems( preloadedVariableData.base_child_items );
				}

				// Hydrate child categories only once (they're shared across variations).
				const currentCategories = select.getCategories();
				if (
					( ! currentCategories || currentCategories.length === 0 ) &&
					preloadedVariableData.child_categories &&
					preloadedVariableData.child_categories.length > 0
				) {
					dispatch.hydrateChildCategories( preloadedVariableData.child_categories );
				}

				// Look for variation overrides in preloaded data.
				const variations = preloadedVariableData.variations ?? [];
				let container = variations.find(
					( obj ) => obj.id === containerId
				);

				// If not found in new structure, try legacy format (backward compatibility).
				if ( typeof container !== 'object' ) {
					// Legacy: preloaded data was an array of full container objects.
					if ( Array.isArray( preloadedVariableData ) ) {
						container = preloadedVariableData.find(
							( obj ) => obj.id === containerId
						);
					}
				}

				// If still not found, fetch from API.
				if ( typeof container !== 'object' ) {
					container = await apiFetch( {
						path: `/wc/store/v1/products/${ containerId }`,
					} );
				}

				dispatch.hydrateContainer( container );

				// Check for WooCommerce variation data stored by jQuery (handles race condition).
				// This includes price_html, display_price, etc. that aren't in the Store API.
				if ( typeof window !== 'undefined' && window.wcMnmVariationData?.[ containerId ] ) {
					dispatch.setVariationMeta( window.wcMnmVariationData[ containerId ] );
				}

				return container;
			}
		} catch ( error ) {
			// @todo: Handle an error here eventually.
			console.error( error );
			return {};
		}
	};
}
