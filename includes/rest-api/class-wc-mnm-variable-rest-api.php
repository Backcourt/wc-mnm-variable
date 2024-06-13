<?php
/**
 * WooCommerce REST API
 *
 * Adds Mix and Match Variation Product data to the WooCommerce REST API.
 *
 * @package  WooCommerce Variable Mix and Match/REST API
 * @since    1.0.0
 * @version  1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_MNM_Variable_REST_API Class.
 *
 * Adds WooCommerce Mix and Match variation data to WC REST API.
 */
class WC_MNM_Variable_REST_API {

	/**
	 * Setup API class.
	 */
	public static function init() {

		// Register WP REST API custom variation fields.
		add_filter( 'woocommerce_rest_product_variation_schema', array( 'WC_Mix_and_Match_REST_API', 'filter_product_schema' ) );
		add_filter( 'woocommerce_rest_prepare_product_variation_object', array( 'WC_Mix_and_Match_REST_API', 'prepare_product_response' ), 10, 3 );
	}
}
WC_MNM_Variable_REST_API::init();
