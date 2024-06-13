/**
 * External dependencies
 */
import { _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { default as ChildItem } from './child-item';
import { ChildProvider } from '@context';
import { generateLoopClass } from './utils';

const ChildItems = ( { childItems } ) => {
	const numColumns = WC_MNM_ADD_TO_CART_VARIATION_PARAMS.num_columns;

	return (

			<ul
				className={ `wc-mnm-variation__child-items mnm_child_products products grid has-flex columns-${ numColumns }` }
			>
				{ childItems.map( ( childItem, index ) => {
					return (
						<ChildProvider
							key={ childItem.child_id }
							childItem={ { childItem } }
						>
							<ChildItem
								loopClass={ generateLoopClass( index ) }
							/>
						</ChildProvider>
					);
				} ) }
			</ul>

	);
};
export default ChildItems;
