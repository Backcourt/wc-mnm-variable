/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState, useRef } from '@wordpress/element';
import { sprintf, _x } from '@wordpress/i18n';
import { useDebouncedCallback } from 'use-debounce';
import { Interweave } from 'interweave';

/**
 * Internal dependencies
 */
import { useChild } from '@context';
import { CONTAINER_STORE_KEY } from '@data';

import ProductStockStatus from './product-stock-status';

const ProductQty = ( {
	disabled = false,
	min = 0,
	max = '',
	step = 1,
	element = 'div',
} ) => {
	const { childItem } = useChild();

	const Element = element;

	const { containerQty, isInStock, maxContainerSize, quantity, isFull } =
		useSelect( ( select ) => {
			return {
				containerQty: select( CONTAINER_STORE_KEY ).getTotalQuantity(),
				isInStock: select( CONTAINER_STORE_KEY ).isInStock(),
				maxContainerSize:
					select( CONTAINER_STORE_KEY ).getMaxContainerSize(),
				quantity: select( CONTAINER_STORE_KEY ).getQty(
					childItem.child_id
				),
				isFull:
					select( CONTAINER_STORE_KEY ).getMaxContainerSize() &&
					select( CONTAINER_STORE_KEY ).getTotalQuantity() >=
						select( CONTAINER_STORE_KEY ).getMaxContainerSize(),
			};
		} );

	// Update the quantity in the store.
	const { updateQty } = useDispatch( CONTAINER_STORE_KEY, [ quantity ] );

	// Ensure quantity is a valid number or empty string.
	const safeQuantity = quantity === '' || quantity === undefined || isNaN( quantity ) ? '' : quantity;

	// Track previous quantity so we can reset input value when not valid.
	const [ prevQty, setPrevQty ] = useState( 0 );
	const [ localQty, setLocalQty ] = useState( safeQuantity );

	/**
	 * Sync the local state quantity to changes from the data store.
	 */
	useEffect( () => {
		const safeQty = quantity === '' || quantity === undefined || isNaN( quantity ) ? '' : quantity;
		setPrevQty( safeQty );
		setLocalQty( safeQty );
	}, [ quantity ] );

	/**
	 * Handle the child product quantity change event.
	 *
	 * @param qty Get the item quantity.
	 */
	const handleQuantityChange = ( qty ) => {
		const newQty = validateQuantity( qty );

		// Only commit to data store if the value has changed.
		if ( newQty !== parseFloat( prevQty ) ) {
			// Store the new version locally.
			setLocalQty( newQty );

			// Store the quantity as prevQty for next update.
			setPrevQty( newQty );

			updateQty( {
				item: childItem,
				qty: newQty,
			} );
		}
	};

	// Individual quantity messages are temporary so don't need to be stored in the data store.
	const [ validationMessages, setValidationMessages ] = useState( [] );

	// Create a ref for a specific DOM element
	const errorRef = useRef( null );

	// Should the item show quantity input.
	const isSelectable =
		isInStock && childItem.purchasable && childItem.in_stock;

	const isTabular =
		'tabular' === WC_MNM_ADD_TO_CART_VARIATION_PARAMS.display_layout;

	// Listen for changes to the validation errors and display.
	useEffect( () => {
		if ( validationMessages.length ) {
			errorRef.current.innerHTML =
				'<span>' + validationMessages[ 0 ] + '</span>';
			errorRef.current.classList.add( 'show' );

			// Show the messages briefly and then clear. Relay the "this" object into the setTimeout anonymous function as "self".
			setTimeout( function () {
				errorRef.current.classList.remove( 'show' );
				setValidationMessages( [] );
			}, 2000 );
		}
	}, [ validationMessages ] );

	/**
	 * Handle checkbox change event.
	 *
	 * @param event Get the event object.
	 */
	const handleCheckboxChange = ( event ) => {
		if ( event.target.checked ) {
			handleQuantityChange( event.target.value );
		} else {
			handleQuantityChange( 0 );
		}
	};

	/**
	 * Handle the minus button event.
	 *
	 * @param e Get the event object.
	 */
	const handleMinusClick = ( e ) => {
		const newQty = Number( quantity ) - Number( step );

		if ( newQty >= min ) {
			handleQuantityChange( newQty );
		}

		// Conditionally disable buttons.
		e.target.disabled = newQty <= min;

		// Select the next sibling element.
		e.target.nextElementSibling.disabled = newQty >= max || isFull;
	};

	/**
	 * Handle the plus button event.
	 *
	 * @param e
	 */
	const handlePlusClick = ( e ) => {
		const newQty = Number( quantity ) + Number( step );

		if ( newQty <= max ) {
			handleQuantityChange( newQty );
		}

		// Conditionally disable buttons.
		e.target.disabled = ( max && newQty >= max ) || isFull;

		// Select the previous sibling element.
		e.target.previousElementSibling.disabled = newQty <= min;
	};

	/**
	 * The goal of this function is to normalize what was inserted,
	 * but after the customer has stopped typing.
	 *
	 * It's important to wait before validating/normalizing or we end up with
	 * a frustrating experience, for example, if the minimum is 2 and
	 * the customer is trying to type "10", premature normalizing would
	 * always kick in at "1" and turn that into 2.
	 */
	const normalizeQuantity = useDebouncedCallback( ( qty ) => {
		handleQuantityChange( qty );
	}, 300 );

	/**
	 * Validate the quantity against the individual input AND the total container size.
	 *
	 * @param int qty The attempted new quantity.
	 * @param qty
	 * @return int The accepted new quantity.
	 */
	const validateQuantity = ( qty ) => {
		// Restrict to min/max limits.
		const currentQty = isNaN( parseFloat( qty ) ) ? 0 : parseFloat( qty );
		let newQty = currentQty;

		const potentialQty = containerQty + ( currentQty - prevQty );

		// Max can't be higher than the container size.
		if ( maxContainerSize > 0 ) {
			max = Math.min( max, maxContainerSize );
		}

		const isDecreasing = newQty < prevQty;

		// Validation.
		switch ( true ) {
			// Prevent over-filling container.
			case maxContainerSize > 0 && potentialQty > maxContainerSize:
				// Handle overfull container.
				if ( containerQty > maxContainerSize ) {
					if ( ! isDecreasing ) {
						newQty = Math.min(
							prevQty - ( containerQty - maxContainerSize ),
							max
						);
					}

					newQty = newQty > 0 ? newQty : 0;

					// Space left to fill.
				} else if ( containerQty < maxContainerSize ) {
					newQty = Math.min( maxContainerSize - containerQty, max );

					// No space left in container, reset to previous.
				} else {
					newQty = prevQty;
				}

				// If the new quantity is the individual max, re-use the item-specific error message.
				if ( max === newQty ) {
					setValidationMessages( [
						WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_child_item_max_qty_message.replace(
							'%d',
							max
						),
					] );
				} else {
					setValidationMessages( [
						WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_child_item_max_container_qty_message.replace(
							'%d',
							maxContainerSize
						),
					] );
				}

				break;

			// Check the item quantity is not below min.
			case min >= 0 && currentQty < min:
				newQty = min;
				setValidationMessages( [
					WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_child_item_min_qty_message.replace(
						'%d',
						min
					),
				] );

				break;

			// Check the item quantity it not below it's max.
			case max > 0 && currentQty > max:
				if ( ! isDecreasing ) {
					newQty = max;
				}

				setValidationMessages( [
					WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_child_item_max_qty_message.replace(
						'%d',
						max
					),
				] );

				break;

			// Check the item quantity has correct step.
			case step > 1 && currentQty % step:
				newQty = currentQty - ( currentQty % step );
				setValidationMessages( [
					WC_MNM_ADD_TO_CART_VARIATION_PARAMS.i18n_child_item_step_qty_message.replace(
						'%d',
						step
					),
				] );

				break;
		}

		return newQty;
	};

	// If out of stock or not purchasable we do not show a quantity input.
	if ( ! isSelectable ) {
		if ( isTabular ) {
			return (
				<Element className="wc-mnm-variation__child-item-quantity product-quantity">
					<ProductStockStatus
						status={ childItem.availability.class }
						availability={ childItem.availability.availability }
					/>
				</Element>
			);
		} else {
			return;
		}
	}

	// Required Hidden Quantity.
	if ( max && min === max ) {
		/* translators: %1$d: Quantity, %2$s: Product name. */
		const requiredText = sprintf(
			_x(
				'&times;%1d <span class="screen-reader-text">%2$s</span>',
				'[Frontend]',
				'wc-mnm-variable'
			),
			max,
			childItem.name
		);
		return (
			<Element className="wc-mnm-variation__child-item-quantity product-quantity">
				<p className="required-quantity child_item__quantity">
					<Interweave tagName="span" content={ requiredText } />
					<input
						type="hidden"
						data-required={ true }
						data-title={ childItem.name }
						data-id={ childItem.child_id }
						className={
							'child_item__quantity_input qty mnm-quantity input-text'
						}
						name={ `mnm_quantity[${ childItem.child_id }]` }
						value={ max }
					/>
				</p>
			</Element>
		);
	}

	/**
	 * Show a checkbox.
	 */
	if ( max && step === max ) {
		/* translators: %1$d: Quantity, %2$s: Product name. */
		const checkboxLabel = sprintf(
			_x(
				'Add %1d <span class="screen-reader-text">%2$s</span>',
				'[Frontend]',
				'wc-mnm-variable'
			),
			max,
			childItem.name
		);

		return (
			<Element className="wc-mnm-variation__child-item-quantity product-quantity ">
				<div className="quantity mnm-checkbox-qty">
					<input
						checked={ quantity === max }
						className="qty mnm-quantity wc-mnm-variation__child-item-quantity_input"
						data-required={ false }
						data-title={ childItem.name }
						disabled={
							isFull || max + containerQty > maxContainerSize
						}
						type="checkbox"
						name={ `mnm_quantity[${ childItem.child_id }]` }
						value={ max }
						onChange={ handleCheckboxChange }
					/>
					<label
						htmlFor={ `mnm_quantity[${ childItem.child_id }]` }
						className={ 'mnm-checkbox-qty-label' }
					>
						<Interweave tagName="span" content={ checkboxLabel } />
					</label>
					<div
						className="wc_mnm_child_item_error"
						aria-live="polite"
					></div>
				</div>
			</Element>
		);
	}

	// Otherwise show the quantity input.
	// Ensure we never pass NaN to the input value.
	const inputValue = localQty === '' || localQty === undefined || isNaN( localQty ) ? '' : localQty;
	
	return (
		<Element className="wc-mnm-variation__child-item-quantity product-quantity">
			<div className="quantity">
				<input
					className="wc-mnm-variation__child-item-quantity_input qty mnm-quantity input-text qty text"
					type="number"
					value={ inputValue }
					min={ min }
					max={ max }
					step={ step }
					hidden={ max === 1 }
					disabled={ disabled }
					onChange={ ( e ) => {
						// Immedately set the state.
						setLocalQty( e.target.value.trim() );

						// Validate after typing finished.
						normalizeQuantity( Number( e.target.value ) );
					} }
					placeholder="0"
					data-title={ childItem.name }
					name={ `mnm_quantity[${ childItem.child_id }]` }
					data-required={ false }
					data-id={ childItem.child_id }
				/>

				{ WC_MNM_ADD_TO_CART_VARIATION_PARAMS.display_plus_minus_buttons && (
					<button
						onClick={ handleMinusClick }
						disabled={ inputValue === '' || inputValue <= min }
						type="button"
						tabIndex="-1"
						aria-label="{ _x( 'Reduce quantity', '[Frontend]', 'wc-mnm-variable' ) }"
						className="button button--minus wp-element-button"
					>
						－
					</button>
				) }

				{ WC_MNM_ADD_TO_CART_VARIATION_PARAMS.display_plus_minus_buttons && (
					<button
						onClick={ handlePlusClick }
						disabled={ isFull }
						type="button"
						tabIndex="-1"
						aria-label="{ _x( 'Increase quantity', '[Frontend]', 'wc-mnm-variable' ) }"
						className="button button--plus wp-element-button"
					>
						＋
					</button>
				) }
				<div
					className="wc-mnm-variation__child-item-error wc_mnm_child_item_error"
					aria-live="polite"
					ref={ errorRef }
				></div>
			</div>
		</Element>
	);
};
export default ProductQty;
