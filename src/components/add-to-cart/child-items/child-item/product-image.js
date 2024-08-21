/**
 * External dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { _x } from '@wordpress/i18n';
import { PLACEHOLDER_IMG_SRC } from '@woocommerce/settings';

const ProductImage = ( {
	image = {},
	fallbackAlt = '',
	permalink,
	element = 'div',
} ) => {
	const imageSrc = image.src ? image.src : PLACEHOLDER_IMG_SRC;

	const Element = element;

	const imageProps = image.src
		? {
				src: image.src,
				alt:
					decodeEntities( image.alt ) ||
					fallbackAlt ||
					_x(
						'Product Image',
						'[Frontend]',
						'wc-mnm-variable'
					),
				className:
					'attachment-woocommerce_thumbnail size-woocommerce_thumbnail',
				'data-large_image': imageSrc,
				loading: 'lazy',
		  }
		: {
				src: PLACEHOLDER_IMG_SRC,
				alt: '',
		  };
		
	return (
		<Element className="wc-mnm-variation__child-item-thumbnail product-thumbnail">
			<div className="wc-mnm-variation__image-wrap mnm_child_product_images mnm_image">
				<figure className="mwc-mnm-variation__image mnm_child_product_image woocommerce-product-gallery__image">
					{permalink !== false ? (
						<a href={permalink} tabIndex="-1" >
							<img
							className={`wc-block-components-product-image wp-image-${image.id}`}
							{...imageProps}
							alt={imageProps.alt}
							/>
						</a>
					) : (
						<img
							className={`wc-block-components-product-image wp-image-${image.id}`}
							{...imageProps}
							alt={imageProps.alt}
						/>
					)}
				</figure>
			</div>
		</Element>
	);
};

export default ProductImage;
