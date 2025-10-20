/**
 * External dependencies
 */
import { createRoot } from '@wordpress/element';
import { addAction, doAction } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import MixAndMatch from './mix-and-match';

// Attach the event listener to the init event.
addAction(
	'wc.mnm.initialize.variable-mix-and-match',
	'wc-mix-and-match',
	function () {
		const targets = document.querySelectorAll( '.wc-mix-and-match-root' );

		targets.forEach( function ( target ) {
			createRoot( target ).render( <MixAndMatch target={ target } /> );
		} );
	}
);

// Trigger the page on load.
doAction( 'wc.mnm.initialize.variable-mix-and-match' );
