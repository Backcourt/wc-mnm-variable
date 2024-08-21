/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { _x, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { useChild } from '@context';
import { CONTAINER_STORE_KEY } from '@data';
import ProductTitle from './product-title';
import ProductDescription from './product-description';
import ProductStockStatus from './product-stock-status';
import ProductPrice from './product-price';

const ProductDetails = ({
	element = 'div',
}) => {
	const { container, isInStock } = useSelect( ( select ) => {
		return {
			container: select( CONTAINER_STORE_KEY ).getContainer(),
			isInStock: select( CONTAINER_STORE_KEY ).isInStock(),
		};
	} );

	const Element = element;

	const isTabular = 'tabular' === WC_MNM_ADD_TO_CART_VARIATION_PARAMS.display_layout;

	const { childItem } = useChild();

	const {
		name,
		catalog_visibility,
		permalink,
		short_description,
		price_html,
	} = childItem;

	const isProductHiddenFromCatalog =
		catalog_visibility === 'hidden' || catalog_visibility === 'search'; // @todo: Need a way to toggle this off in admin.

	const isSelectable = isInStock && childItem.purchasable && childItem.in_stock;

	const target = WC_MNM_ADD_TO_CART_VARIATION_PARAMS?.__experimental_link_target === '_blank' ? '_blank' : '_self';

	return (
		<Element className="wc-mnm-variation__child-item-details product-details">
			{ isProductHiddenFromCatalog ? (
				<ProductTitle title={ name } />
			) : (
				<a href={ permalink } aria-label={ sprintf( _x( 'View product page for %s', 'wc-mnm-variable' ), name ) } target={target} >
					<ProductTitle title={ name } />
				</a>
			) }

			{ WC_MNM_ADD_TO_CART_VARIATION_PARAMS.display_short_description && (
				<ProductDescription shortDescription={ short_description } />
			) }

			{ ! isSelectable && ! isTabular && (
				<ProductStockStatus
					status={ childItem.availability.class }
					availability={ childItem.availability.availability }
				/>
			) }

			{ container.priced_per_product && (
				<ProductPrice priceString={ price_html } />
			) }
		</Element>
	);
};
export default ProductDetails;
