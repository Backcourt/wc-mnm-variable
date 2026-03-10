/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { CONTAINER_STORE_KEY } from '@data/container';

const ProductPrice = () => {
	const { container } = useSelect( ( select ) => {
		return {
			container: select( CONTAINER_STORE_KEY ).getContainer(),
		};
	} );

	// Use raw prices with wc.priceFormat.
	if ( container?.display_price !== undefined && typeof wc?.priceFormat?.formatPrice === 'function' ) {
		const price = container.display_price;
		const regularPrice = container.display_regular_price;

		// Show sale price with strikethrough if on sale.
		if ( regularPrice && regularPrice !== price ) {
			return (
				<span className="price-amount">
					<del aria-hidden="true">
						<span className="woocommerce-Price-amount amount">
							<bdi>{ wc.priceFormat.formatPrice( regularPrice ) }</bdi>
						</span>
					</del>
					<ins>
						<span className="woocommerce-Price-amount amount">
							<bdi>{ wc.priceFormat.formatPrice( price ) }</bdi>
						</span>
					</ins>
				</span>
			);
		}

		return (
			<span className="price-amount">
				<span className="woocommerce-Price-amount amount">
					<bdi>{ wc.priceFormat.formatPrice( price ) }</bdi>
				</span>
			</span>
		);
	}

	return null;
};

export default ProductPrice;
