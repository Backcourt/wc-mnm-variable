/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { sprintf, _nx, _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { CONTAINER_STORE_KEY } from '@data/container';

const Counter = () => {
    const { maxContainerSize, messages, passesValidation, totalQuantity } = useSelect( ( select ) => {
		return {
			maxContainerSize: select( CONTAINER_STORE_KEY ).getMaxContainerSize(),
			messages: select( CONTAINER_STORE_KEY ).passesValidation()
				? select( CONTAINER_STORE_KEY ).getMessages( 'status' )
				: select( CONTAINER_STORE_KEY ).getMessages( 'errors' ),
			passesValidation: select( CONTAINER_STORE_KEY ).passesValidation(),
			totalQuantity: select( CONTAINER_STORE_KEY ).getTotalQuantity(),
		};
	} );

    if ( ! maxContainerSize ) {
		maxContainerSize = _x( '∞', '[Frontend] - Infinity symbol', 'wc-mnm-variable' )
	}

    return (
        <span className="mnm_counter">
            { sprintf(
                _nx(
                    '(%1$s/%2$s) item',
                    '(%1$s/%2$s) items',
                    maxContainerSize, // Number to check for plural
                    '[Frontend] Formatted total ex (2/8). %1$s is the current total and %2$s is the container maximum.',
                    'wc-mnm-variable'
                ),
                totalQuantity,
                maxContainerSize
            )}
        </span>
    );
};

export default Counter;
