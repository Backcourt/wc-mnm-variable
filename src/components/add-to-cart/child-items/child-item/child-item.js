/**
 * Internal dependencies
 */
import { default as GridItem } from '.././grid/child-item';
import { default as TabularItem } from '.././tabular/child-item';

const ChildItem = ( { loopClass } ) => {

	const isGridLayout =
		WC_MNM_ADD_TO_CART_VARIATION_PARAMS.display_layout === 'grid';

	return isGridLayout ? (
		<GridItem loopClass={loopClass} />
	) : (
		<TabularItem />
	);
};
export default ChildItem;
