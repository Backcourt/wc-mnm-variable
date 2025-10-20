/**
 * External dependencies
 */
import { _x } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { CONTAINER_STORE_KEY } from '@data';
import ChildItems from './child-items';

const OutOfStock = () => {
	// Get the child Items from the data store.
	const { childItems, categories } = useSelect( ( select ) => {
		return {
			childItems: select( CONTAINER_STORE_KEY ).getChildItems(),
			categories: select( CONTAINER_STORE_KEY ).getCategories(),
		};
	} );

	return (
		<>
			<ChildItems
				childItems={ childItems }
				childCategories={ categories }
			/>

			<div className="wc-block-components-product-add-to-cart-unavailable outofstock">
				{ _x( 'Out of stock', '[Frontend]', 'wc-mnm-variable' ) }
			</div>
		</>
	);
};

export default OutOfStock;
