/**
 * External dependencies
 */
import { _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { default as ChildItem } from './child-item';
import { ChildProvider } from '@context';

const ChildItems = ( { childItems } ) => {

	return (
		<table
			cellSpacing="0"
			className="wc-mnm-variation__child-items mnm_child_products products tabular mnm_table shop_table"
		>
			<tbody>
				{ childItems.map( ( childItem, index ) => (
					<ChildProvider
						key={ childItem.child_id }
						childItem={ { childItem } }
					>
						<ChildItem />
					</ChildProvider>
				) ) }
			</tbody>
		</table>
	);

};
export default ChildItems;
