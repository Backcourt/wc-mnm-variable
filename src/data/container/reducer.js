/**
 * External dependencies
 */
import { _x } from '@wordpress/i18n';
import { doAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import DEFAULT_STATE from './default-state';
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

import { calcTotalQuantity, selectQuantityMessage, mergeChildItemsWithOverrides } from './utils';

/**
 * Data store reducer
 *
 * @param object state
 * @param object payload
 * @return object the updated state
 */
const reducer = ( state = DEFAULT_STATE, { type, payload } ) => {
	// Get merged child items from base + variation overrides.
	const childItems = mergeChildItemsWithOverrides(
		state.baseChildItems,
		state.containers[ state.containerId ]
	);

	switch ( type ) {
		case SET_CONTAINER_ID:
			return {
				...state,
				containerId: payload.containerId,
			};

		case HYDRATE_CONTAINER: {
			const containerId = payload.container.id;
			// Merge with existing container data (preserves WooCommerce variation meta).
			const existingContainer = state.containers[ containerId ] || {};

			return {
				...state,
				containers: {
					...state.containers,
					[ containerId ]: {
						...existingContainer,
						...payload.container,
					},
				},
			};
		}

		// Merge WooCommerce variation meta (all variation data) into the container.
		case SET_VARIATION_META: {
			const { variationData } = payload;
			const variationId = variationData.variation_id;

			// Get existing container data or create a minimal one.
			const existingContainer = state.containers[ variationId ] || { id: variationId };

			return {
				...state,
				containers: {
					...state.containers,
					[ variationId ]: {
						...existingContainer,
						// Spread all variation data from WooCommerce.
						...variationData,
						// Ensure id is set (variation_id -> id for consistency).
						id: variationId,
						// Map WooCommerce variation keys to our expected format for key selectors.
						is_purchasable: variationData.is_purchasable,
						is_in_stock: variationData.is_in_stock,
					},
				},
			};
		}

		case HYDRATE_BASE_CHILD_ITEMS:
			return {
				...state,
				baseChildItems: payload.baseChildItems,
			};

		case HYDRATE_CHILD_CATEGORIES:
			return {
				...state,
				childCategories: payload.childCategories,
			};

		case RESET_CONFIG:
			return {
				...state,
				config: {},
				selections: [],
			};

		case SET_CONTEXT:
			return {
				...state,
				context: payload.context,
			};

		case SET_CONFIG:
			let payloadConfig = payload.config;
			let newConfig = {};
			let newSelections = [];

			// Attempt to parse JSON strings (used by data attributes when editing the container in admin).
			if ( typeof payloadConfig === 'string' ) {
				// Parse the JSON string into a JavaScript object
				let dataObject = JSON.parse( payloadConfig );

				// Check if parsing was successful
				if ( dataObject && typeof dataObject === 'object' ) {
					payloadConfig = dataObject;
				}
			}

			// Cast any null to empty object.
			payloadConfig = Object( payloadConfig );

			// Verify each payloadConfig id is a valid child item:
			for ( let item of childItems ) {
				// Check if child item is in the config.
				if ( payloadConfig[ item.child_id ] !== undefined ) {
					let newQty = parseFloat( payloadConfig[ item.child_id ] );

					// Store new qty in config object.
					newConfig[ item.child_id ] = newQty;

					// Push child item into selections the required number of times.
					for ( let i = 0; i < newQty; i++ ) {
						newSelections.push( item );
					}
				}
			}

			return {
				...state,
				config: newConfig,
				selections: newSelections,
			};

		case UPDATE_QTY:
			const child_id = payload.item.child_id;
			const currentQty = state.config.hasOwnProperty( child_id )
				? state.config[ child_id ]
				: 0;

			const updatedSelections = state.selections;
			const updatedConfig = state.config;

			const payloadQty = parseFloat( payload.qty );

			// Check if the ID is a valid child item ID.
			if ( childItems.some( ( obj ) => obj.child_id === child_id ) ) {
				// If increasing.
				if ( payloadQty > currentQty ) {
					updatedSelections.push( payload.item );
				} else if ( payloadQty < currentQty ) {
					// if decreasing this needs to remove the last instance of this item.
					for ( let i = updatedSelections.length - 1; i >= 0; i-- ) {
						if ( updatedSelections[ i ].child_id === child_id ) {
							const newLocal = 1;
							updatedSelections.splice( i, newLocal );
							break; // Stop the loop after removing the last matching object.
						}
					}
				}

				// Update the quantity in the config object.
				updatedConfig[ child_id ] = payloadQty;
			}

			return {
				...state,
				config: updatedConfig,
				selections: updatedSelections,
			};

		case VALIDATE:
			const messages = {
				status: [],
				errors: [],
			};
			const totalQuantity = calcTotalQuantity( state.config );

			if (
				state.containers.hasOwnProperty( state.containerId ) &&
				state.containers[ state.containerId ].hasOwnProperty(
					'type'
				) &&
				state.containers[ state.containerId ].type ===
					'mix-and-match-variation'
			) {
				const validationContext = state.context;
				const container = state.containers[ state.containerId ];

				// Support new format (direct properties) with backward compatibility (extensions.mix_and_match).
				const minContainerSize =
					container.min_container_size ??
					container.extensions?.mix_and_match?.min_container_size ??
					0;
				const maxContainerSize =
					container.max_container_size ??
					container.extensions?.mix_and_match?.max_container_size ??
					0;
				const qtyMessage = selectQuantityMessage( totalQuantity ); // "Selected X total".

				let errorMessage = '';
				let validMessage = '';

				// Validation.
				switch ( true ) {
					// Validate a fixed size container.
					case minContainerSize === maxContainerSize:
						validMessage =
							typeof WC_MNM_ADD_TO_CART_VARIATION_PARAMS[
								'i18n_' +
									validationContext +
									'_valid_fixed_message'
							] !== 'undefined'
								? WC_MNM_ADD_TO_CART_VARIATION_PARAMS[
										'i18n_' +
											validationContext +
											'_valid_fixed_message'
								  ]
								: WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_valid_fixed_message;

						if ( totalQuantity !== minContainerSize ) {
							errorMessage =
								minContainerSize === 1
									? WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_qty_error_single
									: WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_qty_error;
							errorMessage = errorMessage.replace(
								'%s',
								minContainerSize
							);
							messages.errors.push(
								errorMessage.replace( '%v', qtyMessage )
							);
						}

						break;

					// Validate that a container has fewer than the maximum number of items.
					case maxContainerSize > 0 && minContainerSize === 0:
						validMessage =
							typeof WC_MNM_ADD_TO_CART_VARIATION_PARAMS[
								'i18n_' +
									validationContext +
									'_valid_max_message'
							] !== 'undefined'
								? WC_MNM_ADD_TO_CART_VARIATION_PARAMS[
										'i18n_' +
											validationContext +
											'_valid_max_message'
								  ]
								: WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_valid_max_message;

						if ( totalQuantity > maxContainerSize ) {
							errorMessage =
								maxContainerSize > 1
									? WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_max_qty_error
									: WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_max_qty_error_singular;
							errorMessage = errorMessage
								.replace( '%max', maxContainerSize )
								.replace( '%v', qtyMessage );
							messages.errors.push( errorMessage );
						}

						break;

					// Validate a range.
					case maxContainerSize > 0 && minContainerSize > 0:
						validMessage =
							typeof WC_MNM_ADD_TO_CART_VARIATION_PARAMS[
								'i18n_' +
									validationContext +
									'_valid_range_message'
							] !== 'undefined'
								? WC_MNM_ADD_TO_CART_VARIATION_PARAMS[
										'i18n_' +
											validationContext +
											'_valid_range_message'
								  ]
								: WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_valid_range_message;

						if (
							totalQuantity < minContainerSize ||
							totalQuantity > maxContainerSize
						) {
							errorMessage =
								WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_min_max_qty_error;
							errorMessage = errorMessage
								.replace( '%max', maxContainerSize )
								.replace( '%min', minContainerSize )
								.replace( '%v', qtyMessage );
							messages.errors.push( errorMessage );
						}
						break;

					// Validate that a container has minimum number of items.
					case minContainerSize >= 0:
						validMessage =
							typeof WC_MNM_ADD_TO_CART_VARIATION_PARAMS[
								'i18n_' +
									validationContext +
									'_valid_min_message'
							] !== 'undefined'
								? WC_MNM_ADD_TO_CART_VARIATION_PARAMS[
										'i18n_' +
											validationContext +
											'_valid_min_message'
								  ]
								: WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_valid_min_message;

						if ( totalQuantity < minContainerSize ) {
							errorMessage =
								minContainerSize > 1
									? WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_min_qty_error
									: WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_min_qty_error_singular;
							errorMessage = errorMessage
								.replace( '%min', minContainerSize )
								.replace( '%v', qtyMessage );
							messages.errors.push( errorMessage );
						}

						break;
				}

				if ( ! messages.errors.length && validMessage !== '' ) {
					validMessage = validMessage
						.replace( '%max', maxContainerSize )
						.replace( '%min', minContainerSize );
					messages.status.push(
						validMessage.replace( '%v', qtyMessage )
					);
				}
			} else {
				messages.errors.push(
					_x(
						'This is not a valid Mix and Match variation and cannot be purchased.',
						'[Frontend]',
						'wc-mnm-variable'
					)
				);
			}

			// @todo - calculate per-price totals.
			const basePrice = state.containers[ state.containerId ].prices;
			const subTotal = basePrice;
			const total = basePrice;

			const validatedState = {
				...state,
				basePrice,
				container: state.containers[ state.containerId ],
				messages,
				passesValidation: messages.errors.length === 0,
				subTotal,
				total,
				totalQuantity,
			};

			/**
			 * Dispatch an action when validated.
			 *
			 * @param object validated state
			 */
			doAction( 'wc.mnm.container.container-updated', validatedState );

			return validatedState;

		default:
			return state;
	}
};

export default reducer;
