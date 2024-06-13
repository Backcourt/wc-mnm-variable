<?php
/**
 * Product Import Class
 *
 * @package  WooCommerce Mix and Match Variable Products/Admin/Import
 * @since    1.0.0
 * @version  1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_MNM_Variable_Product_Import Class.
 *
 * Add support for MNM products to WooCommerce product import.
 */
class WC_MNM_Variable_Product_Import {

	/**
	 * Hook in.
	 */
	public static function init() {

		// Handle Mix and Match variations.
		add_filter( 'woocommerce_product_import_process_item_data', array( __CLASS__, 'import_as_variation' ) );
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( __CLASS__, 'restore_variation_type' ), 0, 2 );

		// Map custom column titles.
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( __CLASS__, 'map_columns' ) );
		add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( __CLASS__, 'add_columns_to_mapping_screen' ) );

		// Parse columns.
		add_filter( 'woocommerce_product_importer_formatting_callbacks', array( __CLASS__, 'append_formatting_callbacks' ), 20, 2 );

		// Set Variable MnM-type props.
		add_filter( 'woocommerce_product_import_pre_insert_product_object', array( __CLASS__, 'set_variable_mnm_props' ), 10, 2 );

		// Remove props for variations.
		add_filter( 'wc_mnm_import_set_props', array( __CLASS__, 'unset_variation_props' ), 10, 2 );
	}

	/**
	 * Filters product import data so mix and match variations are imported correctly (as variations).
	 *
	 * Mix and Match variations are the exact same as standard variations. What sets them apart is the fact they are linked
	 * to a variable mix and match parent rather than a standard variable product. With that in mind, we need to import them just
	 * like a variation.
	 *
	 * @props WooCommerce Subscriptions
	 * @see WC_Product_Importer::get_product_object()
	 *
	 * @param array $data The product's import data.
	 * @return array $data
	 */
	public static function import_as_variation( $data ) {
		if ( isset( $data['type'] ) && 'mix-and-match-variation' === $data['type'] ) {
			$data['type']                    = 'variation';
			$data['mix-and-match-variation'] = true; // Store original type for later.
		}

		return $data;
	}

	/**
	 * After Woo Core has updated the post type, switch the product object back to WC_Product_Mix_and_Match_Variation
	 *
	 * @param WC_Product
	 * @param  array $data     Item data.
	 * @return WC_Product|WP_Error
	 */
	public static function restore_variation_type( $product, $data ) {

		if ( ! empty( $data['mix-and-match-variation'] ) ) {
			try {

				$old_props = array_replace_recursive( $product->get_data(), $product->get_changes() );

				// Switch product type to Mix and Match variation product object.
				$product = wc_get_product_object( 'mix-and-match-variation', $product->get_id() );

				$product->set_props( $old_props );

			} catch ( WC_Data_Exception $e ) {
				return new WP_Error( 'woocommerce_product_csv_importer_' . $e->getErrorCode(), $e->getMessage(), array( 'status' => 401 ) );
			}
		}

		return $product;
	}

	/**
	 * Register the 'Custom' columns in the importer.
	 *
	 * @param  array  $columns
	 * @return array  $columns
	 */
	public static function map_columns( $columns ) {

		$columns['variable-mix-and-match'] = array(
			'name'    => __( 'Variable Mix and Match Products', 'wc-mnm-variable' ),
			'options' => array(
				'wc_mnm_variable_share_content' => __( 'Variable MnM Share Content', 'wc-mnm-variable' ),
			),
		);

		return apply_filters( 'wc_mnm_variable_csv_product_import_mapping_options', $columns );
	}

	/**
	 * Add automatic mapping support for custom columns.
	 *
	 * @param  array  $columns
	 * @return array  $columns
	 */
	public static function add_columns_to_mapping_screen( $columns ) {

		$columns[ __( 'Variable MnM Share Content', 'wc-mnm-variable' ) ] = 'wc_mnm_variable_share_content';

		// Always add English mappings.
		$columns['Variable MnM Share Content'] = 'wc_mnm_variable_share_content';

		return apply_filters( 'wc_mnm_variable_csv_product_import_mapping_default_columns', $columns );
	}

	/**
	 * Set formatting (decoding) callback for data.
	 *
	 * @param  array                    $callbacks
	 * @param  WC_Product_CSV_Importer  $importer
	 * @return array
	 */
	public static function append_formatting_callbacks( $callbacks, $importer ) {

		$mnm_callbacks = array(
			'wc_mnm_variable_share_content' => array( $importer, 'parse_bool_field' ),
		);

		$mapped_keys_reverse = array_flip( $importer->get_mapped_keys() );

		// Add all our callbacks by array index.
		foreach ( $mnm_callbacks as $mnm_key => $mnm_callback ) {
			if ( isset( $mapped_keys_reverse[ $mnm_key ] ) ) {
				$callbacks[ $mapped_keys_reverse[ $mnm_key ] ] = $mnm_callback;
			}
		}

		return $callbacks;
	}

	/**
	 * Set container-type props.
	 *
	 * @param WC_Product
	 * @param  array  $data
	 * @return array
	 */
	public static function set_variable_mnm_props( $product, $data ) {

		if ( $product instanceof WC_Product && $product->is_type( 'variable-mix-and-match' ) ) {

			// Inherit some props.
			$props = WC_MNM_Product_Import::get_parsed_props( $data, $product );

			// Add the unique variable mix and match props.
			if ( isset( $data['wc_mnm_variable_share_content'] ) ) {
				$props['share_content'] = $data['wc_mnm_variable_share_content'];
			}

			/**
			 * Filter container-type props.
			 *
			 * @param  array  $props - Container props.
			 * @param  WC_Product - The product object.
			 * @param  array $data - imported data.
			 * @return array
			 */

			$props = (array) apply_filters( 'wc_mnm_variable_import_set_props', $props, $product, $data );

			if ( ! empty( $props ) ) {
				$product->set_props( $props );
			}
		}

		return $product;
	}

	/**
	 * Remove inherited props.
	 *
	 * @param  array  $props
	 * @param WC_Product
	 * @return array
	 */
	public static function unset_variation_props( $props, $product ) {

		if ( $product instanceof WC_Product && $product->is_type( 'mix-and-match-variation' ) ) {
			unset( $props['packing_mode'] );
			unset( $props['weight_cumulative'] );
			unset( $props['priced_per_product'] );
			unset( $props['discount'] );
		}

		return $props;
	}
}
WC_MNM_Variable_Product_Import::init();
