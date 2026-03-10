/**
 * Calculate the total quantity given any config of ID=>quantity pairs.
 *
 * @param obj    config {
 *               98 => 1,
 *               99 => 2,
 *               }
 * @param config
 */
export const calcTotalQuantity = ( config ) => {
	return Object.values( config ).reduce(
		( total, qty ) => {
			const numQty = Number( qty );
			return total + ( isNaN( numQty ) ? 0 : numQty );
		},
		0
	);
};

/**
 * Quantity total message builder.
 *
 * @param int qty
 * @param qty
 */
export const selectQuantityMessage = function ( qty ) {
	const message =
		qty === 1
			? WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_qty_message_single
			: WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_qty_message;
	return message.replace( '%s', qty );
};

/**
 * Merge base child items with variation-specific overrides.
 *
 * This function takes the static base child items data (images, names, etc.)
 * and merges it with variation-specific quantity constraints (min_qty, max_qty, step_qty).
 *
 * @param {Array}  baseChildItems - Array of base child item objects with static data.
 * @param {Object} container      - The variation container object with overrides.
 * @return {Array} Merged child items array with complete data.
 */
export const mergeChildItemsWithOverrides = ( baseChildItems, container ) => {
	// If no base child items or container, return empty array.
	if ( ! baseChildItems || ! Array.isArray( baseChildItems ) || baseChildItems.length === 0 ) {
		// Fall back to checking for child_items directly in the container (backward compatibility).
		if (
			container &&
			container.extensions?.mix_and_match?.child_items
		) {
			return container.extensions.mix_and_match.child_items;
		}
		return [];
	}

	// Get the variation-specific overrides.
	const overrides = container?.child_item_overrides ?? {};

	// Merge base items with overrides.
	return baseChildItems.map( ( baseItem ) => {
		const childId = baseItem.child_id;
		const itemOverrides = overrides[ childId ] ?? {};

		// Parse quantity values, ensuring they're valid numbers or use defaults.
		const minQty = parseInt( itemOverrides.min_qty, 10 );
		const maxQty = itemOverrides.max_qty === '' || itemOverrides.max_qty === undefined 
			? '' 
			: parseInt( itemOverrides.max_qty, 10 );
		const stepQty = parseInt( itemOverrides.step_qty, 10 );
		const qty = itemOverrides.qty === '' || itemOverrides.qty === undefined 
			? '' 
			: parseInt( itemOverrides.qty, 10 );

		return {
			...baseItem,
			// Apply variation-specific quantity constraints with NaN protection.
			min_qty: isNaN( minQty ) ? 0 : minQty,
			max_qty: maxQty === '' || isNaN( maxQty ) ? '' : maxQty,
			step_qty: isNaN( stepQty ) ? 1 : stepQty,
			qty: qty === '' || isNaN( qty ) ? '' : qty,
		};
	} );
};
