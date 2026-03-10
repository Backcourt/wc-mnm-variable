/**
 * External dependencies
 */
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import TYPES from './action-types';
const {
	SET_CONTAINER_ID,
	SET_VARIATION_META,
	HYDRATE_CONTAINER,
	HYDRATE_BASE_CHILD_ITEMS,
	HYDRATE_CHILD_CATEGORIES,
	RESET_CONFIG,
	SET_CONTEXT,
	SET_CONFIG,
	UPDATE_QTY,
	VALIDATE,
} = TYPES;

/**
 * Set the container ID.
 *
 * Because this happens whenever the variation change is detected in Woo, it's our proxy for variation changed events.
 */
export const setContainerId =
	( containerId ) =>
	( { select, dispatch } ) => {

		dispatch( { type: SET_CONTAINER_ID, payload: { containerId } } );

		// Conditionally take actions if we have already resolved a container.
		if ( select.hasContainer() ) {
			// The resolver only dispatches HYPDATE (and therefore VALIDATE) on first resolution and we need to re-validate/update messaging on every switch.
			dispatch( { type: VALIDATE } );
		}
	};

/**
 * Set variation meta from WooCommerce's found_variation event.
 *
 * This merges WooCommerce variation data (is_purchasable, is_in_stock, display_price, etc.)
 * directly into the container, avoiding the need to duplicate this in the Store API.
 *
 * @param {Object} variationData - The variation object from WooCommerce's found_variation event.
 */
export const setVariationMeta =
	( variationData ) =>
	( { dispatch } ) => {
		dispatch( { type: SET_VARIATION_META, payload: { variationData } } );
	};

// Set the product.
export const hydrateContainer =
	( container ) =>
	( { dispatch } ) => {
		dispatch( { type: HYDRATE_CONTAINER, payload: { container } } );
		dispatch( { type: VALIDATE } );
	};

// Set the base child items (shared across all variations).
export const hydrateBaseChildItems = ( baseChildItems ) => {
	return {
		type: HYDRATE_BASE_CHILD_ITEMS,
		payload: { baseChildItems },
	};
};

// Set the child categories (shared across all variations).
export const hydrateChildCategories = ( childCategories ) => {
	return {
		type: HYDRATE_CHILD_CATEGORIES,
		payload: { childCategories },
	};
};

// Clear the config.
export const resetConfig =
	() =>
	( { dispatch } ) => {
		dispatch( { type: RESET_CONFIG } );
		dispatch( { type: VALIDATE } );
	};

// Set the validation context.
export const setContext = ( context ) => {
	return {
		type: SET_CONTEXT,
		payload: {
			context,
		},
	};
};

// Update the entire config at once.
export const setConfig =
	( config ) =>
	( { select, dispatch } ) => {
		dispatch( { type: SET_CONFIG, payload: { config } } );

		// Conditionally take actions if we have already resolved a container.
		if ( select.hasContainer() ) {
			dispatch( { type: VALIDATE } );
		}
	};

// Update the config when a single quantity is changed.
export const updateQty =
	( { item, qty } ) =>
	( { select, dispatch } ) => {
		dispatch( {
			type: UPDATE_QTY,
			payload: {
				item,
				qty,
			},
		} );
		dispatch( { type: VALIDATE } );
	};

// Validate the container after it has been updated.
export const validate = () => {
	return {
		type: VALIDATE,
	};
};
