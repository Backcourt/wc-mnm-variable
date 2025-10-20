/**
 * External dependencies
 */
import { useMutationObserver } from '@react-hooks-library/core';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import AddToCart from '@components/add-to-cart';
import Loading from '@components/add-to-cart/loading';
import OutOfStock from '@components/add-to-cart/outofstock';
import Unavailable from '@components/add-to-cart/unavailable';
import { CONTAINER_STORE_KEY } from '@data';

const MixAndMatch = ( { target } ) => {
	const { setContext, setContainerId, setConfig } =
		useDispatch( CONTAINER_STORE_KEY );

	const [ runOnce, setRunOnce ] = useState( false );

	// Set the validation context right away.
	useEffect( () => {
		const Form = target.closest( 'form' );

		if ( Form ) {
			const context = Form.getAttribute( 'data-validation_context' );
			if ( context ) {
				setContext( context );
			}
		}
	}, [] );

	// Watch for variation changes.
	useMutationObserver(
		target,
		( mutations ) => {
			for ( const mutation of mutations ) {
				if ( mutation.type === 'attributes' ) {
					let variationId =
						mutation.target.getAttribute( 'data-variation_id' );
					variationId =
						'' === variationId || isNaN( variationId )
							? 0
							: parseInt( variationId, 10 );
					setContainerId( variationId );
				}
			}
		},
		{ attributes: true }
	);

	// Get container from the store.
	const { container, isLoading, hasContainer, isPurchasable, isInStock } =
		useSelect( ( select ) => {
			const { getContainerId, getContainerById, hasContainer } =
				select( CONTAINER_STORE_KEY );

			const containerId = getContainerId();

			return {
				container: getContainerById( containerId ),
				isInStock: select( CONTAINER_STORE_KEY ).isInStock(),
				isLoading: ! select(
					CONTAINER_STORE_KEY
				).hasFinishedResolution( 'getContainerById', [ containerId ] ),
				isPurchasable: select( CONTAINER_STORE_KEY ).isPurchasable(),
				hasContainer: hasContainer(),
			};
		} );

	// Update some store data one time. We need to check for instance of pre-filled config, ie: editing|$_POST etc.
	// So this is the best component for that to limit re-renders, but we also need to wait for the container to resolve since setConfig is validated against the allowed child items.
	useEffect( () => {
		const Form = target.closest( 'form' );

		if ( ! runOnce && hasContainer && Form ) {
			// Read the config from either the URL or the data-attributes.
			let initConfig = Form.getAttribute( 'data-container_config' );
			const parsedJson =
				'undefined' !== typeof initConfig
					? JSON.parse( initConfig )
					: '';
			const hasConfig =
				typeof parsedJson === 'object' &&
				parsedJson !== null &&
				! Array.isArray( parsedJson ) &&
				Object.keys( parsedJson ).length > 0;

			// If nothing in the data-attributes, check the URL params.
			if ( ! hasConfig ) {
				// Create a URLSearchParams object from the query string
				const params = new URLSearchParams( window.location.search );

				// Initialize an object to store parsed values.
				initConfig = {};

				// Iterate over the parameters.
				params.forEach( ( value, key ) => {
					if ( key.startsWith( 'mnm_quantity' ) ) {
						// Currently we only support `mnm_quantity` input names.

						// Using regular expression to extract the number
						const match = key.match( /\[(\d+)\]/ );

						// Check if there is a match and extract the number
						const productId = match
							? parseFloat( match[ 1 ], 10 )
							: null;

						// Store the value in the parsed object
						initConfig[ productId ] = value;
					}
				} );
			}

			if ( Object.keys( initConfig ).length > 0 ) {
				setConfig( initConfig );
			}

			setRunOnce( true );
		}
	}, [ hasContainer, runOnce ] );

	// Loading state.
	if ( isLoading ) {
		return <Loading />;
	}

	// Finally load the app when the container is ready.
	if ( hasContainer ) {
		if ( ! isPurchasable ) {
			return <Unavailable />;
		}

		if ( ! isInStock ) {
			return (
				<OutOfStock
					reason={ _x(
						'This product is currently out of stock and cannot be purchased.',
						'[Frontend]',
						'wc-mnm-variable'
					) }
				/>
			);
		}

		return <AddToCart />;
	}
};
export default MixAndMatch;
