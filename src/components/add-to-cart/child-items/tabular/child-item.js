/**
 * Internal dependencies
 */
import { useChild } from '@context';

import ProductImage from '../child-item/product-image';
import ProductDetails from '../child-item/product-details';
import ProductQty from '../child-item/product-qty';

const ChildItem = () => {
	const { childItem } = useChild();

	const { name, images, catalog_visibility, in_stock } = childItem;

	const firstImage = images.length ? images[ 0 ] : {};
	const permalink =
		catalog_visibility === 'hidden' || catalog_visibility === 'search'
			? false
			: childItem.permalink;

	return (
		<tr
			className={ `wc-mnm-variation__child-item mnm_item product type-product first post-${ childItem.child_id }` }
		>
			{ WC_MNM_ADD_TO_CART_VARIATION_PARAMS.display_thumbnails && (
				<ProductImage
					element={ 'td' }
					image={ firstImage }
					fallbackAlt={ name }
					permalink={ permalink }
				/>
			) }
			<ProductDetails element={ 'td' } />
			<ProductQty
				element={ 'td' }
				min={ childItem.min_qty }
				max={ childItem.max_qty }
				step={ childItem.step_qty }
			/>
		</tr>
	);
};
export default ChildItem;
