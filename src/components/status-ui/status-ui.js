/**
 * External dependencies
 */
import { _x } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { CONTAINER_STORE_KEY } from '@data/container';
import ProductPrice from './product-price';
import Counter from './counter';

const StatusUI = () => {
	const { messages, passesValidation } = useSelect( ( select ) => {
		return {
			messages: select( CONTAINER_STORE_KEY ).passesValidation()
				? select( CONTAINER_STORE_KEY ).getMessages( 'status' )
				: select( CONTAINER_STORE_KEY ).getMessages( 'errors' ),
			passesValidation: select( CONTAINER_STORE_KEY ).passesValidation(),
		};
	} );

	return (
		<div
			className={ `wc-mnm-variation__status mnm_status ${
				passesValidation ? 'passes_validation' : 'fails_validation'
			}` }
		>
			<p className="wc-mnm-variation__status-content mnm_price">
				<span className="price">
					<span className="total">
						{ _x(
							'Total:',
							'[Frontend] "Total" price refers to the sum price of the container. Preceeds formatted price in local currency."',
							'wc-mnm-mobile-styles'
						) }
					</span>
					<ProductPrice />
				</span>
				<Counter />
			</p>

			<div
				aria-live="polite"
				role="status"
				className={ `wc-mnm-variation__message mnm_message woocommerce-message ${
					! passesValidation ? 'woocommerce-error' : ''
				}` }
				style={ { display: 'block' } }
			>
				<ul className="wc-mnm-variation__message-content msg mnm_message_content">
					{ messages.map( ( message, index ) => (
						<li key={ index }>{ message }</li>
					) ) }
				</ul>
			</div>
		</div>
	);
};

export default StatusUI;
