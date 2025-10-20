/**
 * Internal dependencies
 */
import { useChild } from '@context';

import ProductImage from '../child-item/product-image';
import ProductDetails from '../child-item/product-details';
import ProductQty from '../child-item/product-qty';

const ChildItem = ( { loopClass } ) => {
	const { childItem } = useChild();

	const { name, images, catalog_visibility, in_stock } = childItem;

	const firstImage = images.length ? images[ 0 ] : {};
	const permalink =
		catalog_visibility === 'hidden' || catalog_visibility === 'search'
			? false
			: childItem.permalink;

	// Get extra classes safely.
	const extraClasses = Array.isArray( WC_MNM_ADD_TO_CART_VARIATION_PARAMS?.__experimental_grid_column_classes )
		? WC_MNM_ADD_TO_CART_VARIATION_PARAMS.__experimental_grid_column_classes.join( ' ' )
		: WC_MNM_ADD_TO_CART_VARIATION_PARAMS?.__experimental_grid_column_classes || '';

	return (
		<li
			className={ `
				wc-mnm-variation__child-item
				mnm_item
				product type-product
				${ loopClass }
				${ in_stock ? 'instock' : 'outofstock' }
				post-${ childItem.child_id }
				${ extraClasses }
			`.trim().replace( /\s+/g, ' ' ) } // Normalize spacing.
		>
			{ WC_MNM_ADD_TO_CART_VARIATION_PARAMS.display_thumbnails && (
				<ProductImage
					element={ 'div' }
					image={ firstImage }
					fallbackAlt={ name }
					permalink={ permalink }
				/>
			) }
			<ProductDetails element={ 'div' } />
			<ProductQty
				min={ childItem.min_qty }
				max={ childItem.max_qty }
				step={ childItem.step_qty }
				element={ 'div' }
			/>
		</li>
	);
};
export default ChildItem;
