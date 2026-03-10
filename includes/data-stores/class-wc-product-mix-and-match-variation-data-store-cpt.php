<?php
/**
 * Class WC_Product_Mix_and_Match_Variation_Data_Store_CPT file.
 *
 * @package WooCommerce Mix and Match Products/Data
 * @since    1.0.0
 * @version  2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mix and Match Variation Product Data Store: Stored in CPT.
 */
class WC_Product_Mix_and_Match_Variation_Data_Store_CPT extends WC_Product_Variation_Data_Store_CPT {

	use WC_MNM_Container_Data_Store;

	/**
	 * Data stored in meta keys, but not considered "meta" for the MnM type.
	 *
	 * @var array
	 */
	protected $extended_internal_meta_keys = array(
		'_mnm_base_price',
		'_mnm_base_regular_price',
		'_mnm_base_sale_price',
		'_mnm_max_price',
		'_mnm_max_regular_price',
		'_mnm_min_container_size',
		'_mnm_max_container_size',
		'_mnm_per_product_discount',
	);


	/**
	 * Maps extended properties to meta keys.
	 *
	 * @var array
	 */
	protected $props_to_meta_keys = array(
		'min_raw_price'         => '_price',
		'min_raw_regular_price' => '_regular_price',
		'max_raw_price'         => '_mnm_max_price',
		'max_raw_regular_price' => '_mnm_max_regular_price',
		'price'                 => '_mnm_base_price',
		'regular_price'         => '_mnm_base_regular_price',
		'sale_price'            => '_mnm_base_sale_price',
		'min_container_size'    => '_mnm_min_container_size',
		'max_container_size'    => '_mnm_max_container_size',
		'discount'              => '_mnm_per_product_discount',
	);


	/**
	 * Maps extended parent_data to meta keys.
	 *
	 * @var array
	 */
	protected $parent_props_to_meta_keys = array(
		'layout'                    => '', // Always globally inherited for variations.
		'add_to_cart_form_location' => '', // Always globally inherited for variations.
		'priced_per_product'        => '_mnm_per_product_pricing',
		'packing_mode'              => '_mnm_packing_mode',
		'weight_cumulative'         => '_mnm_weight_cumulative',
		'share_content'             => '_mnm_share_content',
		'content_source'            => '_mnm_content_source',
		'child_category_ids'        => '_mnm_child_category_ids',
	);


	/**
	 * __construct function.
	 */
	public function __construct() {
		$this->extended_internal_meta_keys = array_merge( $this->shared_extended_internal_meta_keys, $this->extended_internal_meta_keys, $this->parent_props_to_meta_keys );
		$this->props_to_meta_keys          = array_merge( $this->shared_props_to_meta_keys, $this->props_to_meta_keys );
	}


	/**
	 * Always read certain data from parent product.
	 *
	 * @param WC_Product_Variation $product Product object.
	 * @throws WC_Data_Exception If WC_Product::set_tax_status() is called with an invalid tax status.
	 */
	protected function read_product_data( &$product ) {

		$parent_id = $product->get_parent_id();

		// NB: This must happen before WC_Product_Variation_Data_Store_CPT::read_product_data().
		// $product->is_on_sale() is called there (Which calls $product->get_priced_per_product() ) and the parent_data array won't be set yet.
		foreach ( $this->parent_props_to_meta_keys as $property => $meta_key ) {

			// Get meta value.
			$function = 'set_' . $property;

			if ( is_callable( array( $product, $function ) ) ) {

				// Get a global value for layout/location props.
				if ( $this->is_global_prop( $product, $property ) ) {
					$value = get_option( $this->global_props[ $property ] );
				} else {
					$value = get_post_meta( $parent_id, $meta_key, true );
				}

				$product->{$function}( $value );
			}
		}

		// Now we can read the core data.
		parent::read_product_data( $product );
	}


	/**
	 * Reads all MnM-specific post meta.
	 *
	 * @param  WC_Product_Mix_and_Match  $product
	 */
	protected function read_extra_data( &$product ) {

		foreach ( $this->get_props_to_meta_keys() as $property => $meta_key ) {

			// Get meta value.
			$function = 'set_' . $property;

			if ( is_callable( array( $product, $function ) ) ) {
				$value = get_post_meta( $product->get_id(), $meta_key, true );
				$product->{$function}( $value );
			}
		}

		// Add content source/category IDs as additional parent data.
		$extended_parent_data = [];
		$parent_id            = $product->get_parent_id();

		// Need content_source and cat IDs in the parent data.
		foreach ( $this->parent_props_to_meta_keys as $property => $meta_key ) {

			// Get a global value for layout/location props.
			if ( $this->is_global_prop( $product, $property ) ) {
				$value = get_option( $this->global_props[ $property ] );
			} else {
				$value = get_post_meta( $parent_id, $meta_key, true );
			}

			$extended_parent_data[ $property ] = $value;

		}

		// Use our own setter to merge parent_data with defaults.
		$product->set_extended_parent_data( $extended_parent_data );

		// Base prices are overridden by NYP min price.
		if ( ! $product->is_priced_per_product() && $product->is_nyp() ) {
			$min_price = $product->get_meta( '_min_price', true, 'edit' );
			$product->set_price( $min_price );
			$product->set_regular_price( $min_price );
			$product->set_sale_price( '' );
		}
	}


	/**
	 * Writes all MnM-specific post meta. @todo - the trait it messing up the price and we're currently only doing static prices anyway.
	 *
	 * @param  WC_Product_Mix_and_Match_Variation  $product
	 * @param  bool                   $force
	 */
	public function update_post_meta( &$product, $force = false ) {

		parent::update_post_meta( $product, $force );

		$id = $product->get_id();

		/**
		 * @todo- While per-item pricing is not, supported we can set the min/max prices manually as they are the same as the base price.
		 */

		// $meta_keys_to_props = array_flip( array_diff_key( $this->get_props_to_meta_keys(), array( 'price' => 1, 'min_raw_price' => 1, 'min_raw_regular_price' => 1 ) ) );
		$meta_keys_to_props = array_flip( $this->get_props_to_meta_keys() );

		$min_raw_price         = $product->get_price( 'sync' );
		$max_raw_price         = $product->get_price( 'sync' );
		$min_raw_regular_price = $product->get_regular_price( 'sync' );
		$max_raw_regular_price = $product->get_regular_price( 'sync' );

		$product->set_min_raw_price( $min_raw_price );
		$product->set_min_raw_regular_price( $min_raw_regular_price );
		$product->set_max_raw_price( $max_raw_price );
		$product->set_max_raw_regular_price( $max_raw_regular_price );

		// End manual price setting, eventually this should be synced somehow.

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
}
