<?php
/**
 * Variable Mix and Match Product Data Store
 *
 * @package  WooCommerce Mix and Match Products/Data
 * @since    1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Product_MNM_Data_Store_CPT Class: Stored in CPT.
 *
 * @version 1.0.0
 */
class WC_Product_Variable_Mix_and_Match_Data_Store_CPT extends WC_Product_Variable_Data_Store_CPT {

	use WC_MNM_Container_Data_Store;

	/**
	 * Data stored in meta keys, but not considered "meta" for the MnM type.
	 *
	 * @var array
	 */
	protected $extended_internal_meta_keys = array(
		'_mnm_layout_style',
		'_mnm_add_to_cart_form_location',
		'_mnm_per_product_pricing',
		'_mnm_packing_mode',
		'_mnm_weight_cumulative',
		'_mnm_share_content',
	);

	/**
	 * Maps extended properties to meta keys.
	 *
	 * @var array
	 */
	protected $props_to_meta_keys = array(
		'layout'                    => '_mnm_layout_style',
		'add_to_cart_form_location' => '_mnm_add_to_cart_form_location',
		'priced_per_product'        => '_mnm_per_product_pricing',
		'packing_mode'              => '_mnm_packing_mode',
		'weight_cumulative'         => '_mnm_weight_cumulative',
		'share_content'             => '_mnm_share_content',
	);

	/**
	 * __construct function.
	 */
	public function __construct() {
		$this->extended_internal_meta_keys = array_merge( $this->shared_extended_internal_meta_keys, $this->extended_internal_meta_keys );
		$this->props_to_meta_keys          = array_merge( $this->shared_props_to_meta_keys, $this->props_to_meta_keys );
	}

	/**
	 * Method to read a product from the database.
	 *
	 * @param WC_Product_Variable_MNM $product Product object.
	 * @throws Exception If invalid product.
	 */
	public function read( &$product ) {
		parent::read( $product );
	}

	/**
	 * Callback to exclude MnM-specific meta data.
	 *
	 * @param  object  $meta
	 * @return bool
	 */
	protected function exclude_internal_meta_keys( $meta ) {
		return parent::exclude_internal_meta_keys( $meta ) && ! in_array( $meta->meta_key, $this->extended_internal_meta_keys );
	}

	/**
	 * Reads all MnM-specific post meta.
	 *
	 * @param  WC_Product_Variable_Mix_and_Match  $product
	 */
	protected function read_extra_data( &$product ) {

		foreach ( $this->get_props_to_meta_keys() as $property => $meta_key ) {

			// Get meta value.
			$function = 'set_' . $property;

			if ( is_callable( array( $product, $function ) ) ) {

				// Get a global value for layout/location props (always use global options in customizer).
				if ( array_key_exists( $property, $this->global_props ) ) {
					$value = get_option( $this->global_props[ $property ] );
				} else {
					$value = get_post_meta( $product->get_id(), $meta_key, true );
				}

				$product->{$function}( $value );
			}
		}
	}

	/**
	 * Writes all Variable MnM-specific post meta.
	 *
	 * @param  WC_Product_Variable_Mix_and_Match  $product
	 * @param  bool                   $force
	 */
	protected function update_post_meta( &$product, $force = false ) {

		parent::update_post_meta( $product, $force );

		$id                 = $product->get_id();
		$meta_keys_to_props = array_flip( $this->get_props_to_meta_keys() );

		$props_to_update = $force ? $meta_keys_to_props : $this->get_props_to_update( $product, $meta_keys_to_props );

		foreach ( $props_to_update as $meta_key => $property ) {

			$property_get_fn = 'get_' . $property;

			// Get meta value.
			$meta_value = $product->$property_get_fn( 'edit' );

			// Sanitize bool for storage.
			if ( is_bool( $meta_value ) ) {
				$meta_value = wc_bool_to_string( $meta_value );
			}

			if ( update_post_meta( $id, $meta_key, $meta_value ) && ! in_array( $property, $this->updated_props ) ) {
				$this->updated_props[] = $meta_key;
			}
		}
	}

	/**
	 * Gets props to meta keys pairs
	 *
	 * @return  array
	 */
	public function get_props_to_meta_keys() {
		return $this->props_to_meta_keys;
	}
}
