/**
 * External dependencies
 */
import { _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { mergeChildItemsWithOverrides } from './utils';

/**
 * Get the categories for the container.
 * Categories are shared across all variations and stored at state level.
 *
 * @param {obj} state The current state.
 * @return [array]
 */
export const getCategories = ( state ) => {
	// New format: categories stored at state level.
	if ( state.childCategories && state.childCategories.length > 0 ) {
		return state.childCategories;
	}
	// Fallback for backward compatibility.
	const container = getContainer( state );
	return container?.extensions?.mix_and_match?.child_categories ?? [];
};

/**
 * Get the base child items (shared across all variations).
 *
 * @param {obj} state The current state.
 * @return {Array} Base child items array.
 */
export const getBaseChildItems = ( state ) => {
	return state.baseChildItems ?? [];
};

/**
 * Get the container's child items.
 *
 * This merges the base child items (static data) with variation-specific
 * quantity overrides (min_qty, max_qty, step_qty) from the current container.
 *
 * @param {obj} state The current state.
 * @return []{obj} An array of child item objects with merged data.
 */
export const getChildItems = ( state ) => {
	const container = getContainer( state );
	const baseChildItems = state.baseChildItems ?? [];

	// If we have base child items, merge with variation overrides.
	if ( baseChildItems.length > 0 ) {
		return mergeChildItemsWithOverrides( baseChildItems, container );
	}

	// Fallback for backward compatibility (old data structure).
	return container?.extensions?.mix_and_match?.child_items ?? [];
};

/**
 * Retrieves container configuration from state.
 *
 * @param {obj} state The current state.
 * @return {obj} The object of selected item ids => quantity pairs.
 */
export const getConfiguration = ( state ) => {
	return state.config;
};

/**
 * Get the current container object
 *
 * @param {obj} state The current state.
 * @return {obj}
 */
export const getContainer = ( state ) => {
	return state.containers.hasOwnProperty( state.containerId )
		? state.containers[ state.containerId ]
		: {};
};

/**
 * Get a container object by ID
 *
 * @param {obj} state The current state.
 * @param int The container ID
 * @return {obj}
 */
export const getContainerById = ( state, id ) => {
	return state.containers[ id ];
};

/**
 * Get a current container's ID
 *
 * @param {obj} state The current state.
 * @return int
 */
export const getContainerId = ( state ) => {
	return state.containerId;
};

/**
 * Get a current container's context
 *
 * @param {obj} state The current state.
 * @return int
 */
export const getContext = ( state ) => {
	return state.context;
};

/**
 * Retrieves container error messages.
 *
 * @param {obj} state The current state.
 * @return [string] Array of messages.
 */
export const getErrorMessages = ( state ) => {
	return state.messages.errors;
};

/**
 * Max container size
 *
 * @param {obj} state The current state.
 * @return mixed int|string
 */
export const getMaxContainerSize = ( state ) => {
	const container = getContainer( state );
	// New format: direct property on container.
	if ( container?.max_container_size !== undefined ) {
		return container.max_container_size;
	}
	// Fallback for backward compatibility.
	return container?.extensions?.mix_and_match?.max_container_size ?? '';
};

/**
 * Retrieves all types of container validation messages.
 *
 * @param {obj} state The current state.
 * @param string The type of message to return if you only want some. 'errors'|'status'
 * @return {obj} The object of selected item ids => quantity pairs.
 */
export const getMessages = ( state, type ) => {
	if ( type === 'errors' ) {
		return state.messages.errors;
	} else if ( type === 'status' ) {
		return state.messages.status;
	}
	return state.messages; // Return all messages.
};

/**
 * Min container size.
 *
 * @param {obj} state The current state.
 * @return mixed int|string
 */
export const getMinContainerSize = ( state ) => {
	const container = getContainer( state );
	// New format: direct property on container.
	if ( container?.min_container_size !== undefined ) {
		return container.min_container_size;
	}
	// Fallback for backward compatibility.
	return container?.extensions?.mix_and_match?.min_container_size ?? 0;
};

/**
 * Retrieves quantity of specific child.
 *
 * @param {obj} state The current state.
 * @param int The child ID
 * @return string|int the quantity
 */
export const getQty = ( state, childId ) => {
	const { config } = state;
	return config.hasOwnProperty( childId ) ? config[ childId ] : '';
};

/**
 * Get the current Selections - an array of all selected child items
 *
 * @param {obj} state The current state.
 * @return [array] Array of select item objects.
 */
export const getSelections = ( state ) => {
	return state.selections;
};

/**
 * Get a current subtotal
 *
 * NB: Currently Variable MNM does not support per-item pricing.
 * The subtotal is derived from the current container's display price.
 *
 * @param {obj} state The current state.
 * @return {obj} Price object with price and regular_price properties.
 */
export const getSubTotal = ( state ) => {
	// For variable MNM without per-item pricing, subtotal equals total.
	return getTotal( state );
};

/**
 * Get a current Total
 *
 * NB: Currently Variable MNM does not support per-item pricing.
 * The total is derived from the current container's display price.
 *
 * @param {obj} state The current state.
 * @return {obj} Price object with price and regular_price properties.
 */
export const getTotal = ( state ) => {
	const container = getContainer( state );

	// Get price from container (set by variation meta or Store API).
	const price = container?.display_price ?? state.total?.price ?? 0;
	const regularPrice = container?.display_regular_price ?? state.total?.regular_price ?? price;

	return {
		price,
		regular_price: regularPrice,
	};
};

/**
 * Retrieves container status messages.
 *
 * @param {obj} state The current state.
 * @return [string] Array of messages.
 */
export const getStatusMessages = ( state ) => {
	return state.messages.status;
};

/**
 * Retrieves quantity of total container's configuration.
 *
 * @param {obj} state The current state.
 * @return int the total quantity
 */
export const getTotalQuantity = ( state ) => {
	return state.totalQuantity;
};

/**
 * Does the container have child items?
 *
 * @param {obj} state The current state.
 * @return bool
 */
export const hasChildItems = ( state ) => {
	return getChildItems( state ).length > 0;
};

/**
 * Does the state have any configuration set.
 *
 * @param {obj} state The current state.
 * @return bool
 */
export const hasConfiguration = ( state ) => {
	return Object.entries( state.config ).length !== 0;
};

/**
 * Is a container resolved yet?
 *
 * @param {obj} state The current state.
 * @return bool
 */
export const hasContainer = ( state ) => {
	const container = getContainer( state );
	return container?.id > 0 ?? false;
};

/**
 * Is the container in stock
 *
 * @param {obj} state The current state.
 * @return bool
 */
export const isInStock = ( state ) => {
	const container = getContainer( state );
	return hasContainer( state ) && container.is_in_stock;
};

/**
 * Is the app resolving a container?
 *
 * @param {obj} state The current state.
 * @return int
 */
export const isLoading = ( state ) => {
	return state.loading;
};

/**
 * Is the container purchasable
 *
 * @param {obj} state The current state.
 * @return bool
 */
export const isPurchasable = ( state ) => {
	const container = getContainer( state );
	return hasContainer( state ) && container.is_purchasable;
};

/**
 * Does the container have a valid config?
 *
 * @param {obj} state The current state.
 * @return bool
 */
export const passesValidation = ( state ) => {
	return true === state.passesValidation;
};
