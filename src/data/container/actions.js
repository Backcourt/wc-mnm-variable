/**
 * External dependencies
 */
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import TYPES from './action-types';
const { SET_CONTAINER_ID, HYDRATE_CONTAINER, RESET_CONFIG, SET_CONTEXT, SET_CONFIG, UPDATE_QTY, VALIDATE } =
	TYPES;

/**
 * Set the container ID.
 *
 * Because this happens whenever the variation change is detected in Woo, it's our proxy for variation changed events.
 */
export const setContainerId =
	( containerId ) =>
	( { select, dispatch } ) => {

		// If we are switching the variation, we will clear the config - except on first load.
		if ( null !== select.getContainerId() && containerId !== select.getContainerId() && select.hasConfiguration() ) {

			dispatch( { type: RESET_CONFIG } );

			// Notify users.
			window.alert( WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_form_cleared );
		}

		dispatch( { type: SET_CONTAINER_ID, payload: { containerId } } );

		// Conditionally take actions if we have already resolved a container.
		if ( select.hasContainer() ) {

			// The resolver only dispatches HYPDATE (and therefore VALIDATE) on first resolution and we need to re-validate/update messaging on every switch.
			dispatch( { type: VALIDATE } );
		}

	};

// Set the product.
export const hydrateContainer =
	( container ) =>
	( { dispatch } ) => {
		dispatch( { type: HYDRATE_CONTAINER, payload: { container } } );
		dispatch( { type: VALIDATE } );
	};

// Clear the config.
export const resetConfig =
	() =>
	( { dispatch } ) => {
		dispatch( { type: RESET_CONFIG } );
		dispatch( { type: VALIDATE } );
	};

// Set the validation context.
export const setContext =
	(context) => {
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
