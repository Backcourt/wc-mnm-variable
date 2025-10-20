/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { CONTAINER_STORE_KEY } from '@data/container';

const ProductPrice = () => {
	const { totalPrice } = useSelect( ( select ) => {
		return {
			totalPrice: select( CONTAINER_STORE_KEY ).getTotal(),
		};
	} );

	// Generate a strikethrough for sale price.
	if ( totalPrice.regular_price !== totalPrice.price ) {
		return (
			<span className="price">
				<del aria-hidden="true">
					<span className="woocommerce-Price-amount amount">
						<bdi>
							{ wc.priceFormat.formatPrice(
								totalPrice.regular_price
							) }
						</bdi>
					</span>
				</del>
				<ins>
					<span className="woocommerce-Price-amount amount">
						<bdi>
							{ wc.priceFormat.formatPrice( totalPrice.price ) }
						</bdi>
					</span>
				</ins>
			</span>
		);
	}

	return (
		<span className="price">
			<span className="woocommerce-Price-amount amount">
				<bdi>{ wc.priceFormat.formatPrice( totalPrice.price ) }</bdi>
			</span>
		</span>
	);
};

export default ProductPrice;
