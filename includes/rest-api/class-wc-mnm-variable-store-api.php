<?php
/**
 * WooCommerce Store API
 *
 * Adds Mix and Match Variation Product data to the WooCommerce Store API.
 *
 * @package  WooCommerce Variable Mix and Match/Store API
 * @since    1.0.0
 * @version  2.2.4
 */

use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_MNM_Variable_Store_API Class.
 *
 * Adds WooCommerce Mix and Match variation data to WC Store API.
 */
class WC_MNM_Variable_Store_API {

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	const IDENTIFIER = 'variable_mix_and_match';

	/**
	 * Setup API class.
	 */
	public static function init() {

		// Add all variations to response.
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => ProductSchema::IDENTIFIER,
				'namespace'       => self::IDENTIFIER,
				'data_callback'   => array( __CLASS__, 'extend_product_data' ),
				'schema_callback' => array( __CLASS__, 'extend_product_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		// Preload REST Responses.
		add_action( 'woocommerce_variable-mix-and-match_add_to_cart', [ __CLASS__, 'preload_response' ] );
		add_action( is_admin() ? 'admin_print_footer_scripts' : 'wp_print_footer_scripts', array( __CLASS__, 'enqueue_asset_data' ), 0 );
	}

	/**
	 * Register parent/child product data into cart/items endpoint.
	 *
	 * @param WC_Product  $product
	 * @return array $item_data
	 */
	public static function extend_product_data( $product ) {

		$key       = 'extend_product_data_' . $product->get_id();
		$item_data = \WC_MNM_Cache::get( $key );

		if ( ! empty( $item_data ) ) {
			return $item_data;
		}

		$item_data = [ 'variations' => [] ];

		if ( $product->is_type( 'variable-mix-and-match' ) ) {

			$request = new \WP_REST_Request( 'GET', '/wc/store/v1/products' );

			$request->set_query_params(
				array(
					'type'     => 'variation',
					'per_page' => 0,
					'include'  => $product->get_visible_children(),
					'order'    => 'asc',
					'orderby'  => 'id',
				)
			);

			$response = rest_do_request( $request );

			// Make an internal request to load complete variation data.
			if ( 200 === $response->get_status() ) {
				$item_data['variations'] = (array) $response->get_data();
			}
		}

		return $item_data;
	}

	/**
	 * Register variations schema into product endpoint. @todo - what goes here?
	 *
	 * @return array Registered schema.
	 */
	public static function extend_product_schema() {
		return array(
			'variations' => array(
				'description' => __( 'Cart item key of mix and match product that contains this item.', 'wc-mnm-variable' ),
				'type'        => array( 'string', 'null' ),
				'context'     => array( 'view' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 *--------------------------------------------------------------------------
	 * Preloading
	 *--------------------------------------------------------------------------
	 */

	/**
	 * Stash product ID for lazy preloading.
	 *
	 * @return object
	 */
	public static function preload_response() {

		global $product;

		$preloads = \WC_MNM_Helpers::cache_get( 'wcMNMVariablePreloads' );

		if ( is_array( $preloads ) ) {
			$preloads[] = $product->get_id();
			\WC_MNM_Helpers::cache_set( 'wcMNMVariablePreloads', $preloads );
		} elseif ( null === $preloads ) {
			$preloads = [ $product->get_id() ];
		}

		\WC_MNM_Helpers::cache_set( 'wcMNMVariablePreloads', $preloads );
	}

	/**
	 * Preload all variations into WC settings.
	 *
	 * @return object
	 */
	public static function enqueue_asset_data() {

		$preloads = \WC_MNM_Helpers::cache_get( 'wcMNMVariablePreloads' );

		if ( ! empty( $preloads ) && is_array( $preloads ) ) {

			$data = [];

			$assets = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class );

			foreach ( $preloads as $product_id ) {

				$rest_route = '/wc/store/v1/products/' . $product_id;

				$assets->hydrate_api_request( $rest_route );

				$rest_preload_api_requests = rest_preload_api_request( [], $rest_route );

				$variation_data = $rest_preload_api_requests[ $rest_route ]['body']['extensions']->variable_mix_and_match['variations'] ?? [];

				$data = array_merge( $data, $variation_data );

			}

			$assets->add( 'wcMNMVariableSettings', $data );

		}
	}
}
WC_MNM_Variable_Store_API::init();
