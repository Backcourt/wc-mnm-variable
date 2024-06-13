<?php
/**
 * Product Export Class
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
 * WC_MNM_Variable_Product_Export Class.
 *
 * Add support for MNM products to WooCommerce product export.
 */
class WC_MNM_Variable_Product_Export {

	/**
	 * Hook in.
	 */
	public static function init() {

		// Register and query mix and match variations.
		add_filter( 'woocommerce_exporter_product_types', array( __CLASS__, 'register_mix_and_match_variation_type' ) );
		add_filter( 'woocommerce_product_export_product_query_args', array( __CLASS__, 'filter_export_query' ) );

		// Add CSV columns for exporting container data.
		add_filter( 'woocommerce_product_export_column_names', array( __CLASS__, 'add_columns' ) );
		add_filter( 'woocommerce_product_export_product_default_columns', array( __CLASS__, 'add_columns' ) );

		// Repurpose some columns from Mix and Match core, after core runs.
		add_filter( 'woocommerce_product_export_product_column_wc_mnm_content_source', array( __CLASS__, 'export_content_source' ), 20, 2 );
		add_filter( 'woocommerce_product_export_product_column_wc_mnm_child_category_ids', array( __CLASS__, 'export_child_category_ids' ), 20, 2 );
		add_filter( 'woocommerce_product_export_product_column_wc_mnm_child_items', array( __CLASS__, 'export_child_items' ), 20, 2 );
		add_filter( 'woocommerce_product_export_product_column_wc_mnm_packing_mode', array( __CLASS__, 'export_packing_mode' ), 20, 2 );
		add_filter( 'woocommerce_product_export_product_column_wc_mnm_weight_cumulative', array( __CLASS__, 'export_weight_cumulative' ), 20, 2 );

		// "Variable MnM Items" column data.
		add_filter( 'woocommerce_product_export_product_column_wc_mnm_variable_share_content', array( __CLASS__, 'export_share_content' ), 10, 2 );

		// "MnM Variations Items" column data - Remove data that is inherited from parent
		add_filter( 'woocommerce_product_export_product_column_wc_mnm_content_source', array( __CLASS__, 'remove_inherited_content' ), 20, 2 );
		add_filter( 'woocommerce_product_export_product_column_wc_mnm_child_category_ids', array( __CLASS__, 'remove_inherited_content' ), 20, 2 );
		add_filter( 'woocommerce_product_export_product_column_wc_mnm_child_items', array( __CLASS__, 'remove_inherited_content' ), 20, 2 );
	}

	/**
	 * Registers the mix and match variation product type with the exporter.
	 *
	 * @props WooCommerce Subscriptions
	 *
	 * @param array $types The product type keys and labels.
	 * @return array $types
	 */
	public static function register_mix_and_match_variation_type( $types ) {
		$types['mix-and-match-variation'] = __( 'Mix and Match variations', 'wc-mnm-variable' );
		return $types;
	}

	/**
	 * Filters the product export query args to separate standard variations and mix and match variations.
	 *
	 * In the database mix and match variations appear exactly the same as standard product variations. To
	 * enforce this distinction when exporting mix and match variations, we exclude products with a standard variable product as a parent and vice versa.
	 *
	 * @props WooCommerce Subscriptions
	 *
	 * @param array $args The product export query args.
	 * @return array
	 */
	public static function filter_export_query( $args ) {
		if ( ! isset( $args['type'] ) || empty( $args['type'] ) || ! is_array( $args['type'] ) ) {
			return $args;
		}

		$export_mix_and_match_variations = false;
		$export_variations               = false;

		foreach ( $args['type'] as $index => $product_type ) {
			if ( 'mix-and-match-variation' === $product_type ) {
				$export_mix_and_match_variations = true;

				// All variation products are exported with the 'variation' key so remove the unneeded `mix-and-match-variation`.
				// Further filtering by product type will be handled by the query args (see below).
				unset( $args['type'][ $index ] );
			} elseif ( 'variation' === $product_type ) {
				$export_variations = true;
			}
		}

		// Exporting mix and match variations but not standard variations. Exclude child variations of variable products.
		if ( $export_mix_and_match_variations && ! $export_variations ) {

			// For now, there's no other way to exclude variations of other variable-ish types.
			// This does mean our variations will export when attempting to export subscription_variation products
			$args['parent_exclude'] = wc_get_products(
				array(
					'type'   => [ 'variable', 'variable-subscription' ],
					'limit'  => -1,
					'return' => 'ids',
				)
			);

			$args['type'][] = 'variation';
			// Exporting standard product variations but not mix and match variations. Exclude child variations of variable mix and match products.
		} elseif ( $export_variations && ! $export_mix_and_match_variations ) {
			$args['parent_exclude'] = wc_get_products(
				array(
					'type'   => 'variable-mix-and-match',
					'limit'  => -1,
					'return' => 'ids',
				)
			);
		}

		return $args;
	}

	/**
	 * Add CSV columns for exporting container data.
	 *
	 * @param  array  $columns
	 * @return array  $columns
	 */
	public static function add_columns( $columns ) {

		$columns['wc_mnm_variable_share_content'] = __( 'Variable MnM Share Content', 'wc-mnm-variable' );

		/**
		 * Mix and Match Export columns.
		 *
		 * @param  array $columns
		 */
		return apply_filters( 'wc_mnm_variable_variable_export_column_names', $columns );
	}

	/**
	 * "Container shares allowed contents across variations" column content.
	 *
	 * @param  mixed       $value
	 * @param  WC_Product  $product
	 * @return mixed       $value
	 */
	public static function export_share_content( $value, $product ) {

		if ( $product->is_type( 'variable-mix-and-match' ) ) {
			$value = $product->is_sharing_content( 'edit' ) ? 1 : 0;
		}

		return $value;
	}

	/**
	 * "Contents source" column content.
	 *
	 * @param  mixed       $value
	 * @param  WC_Product  $product
	 * @return mixed       $value
	 */
	public static function export_content_source( $value, $product ) {

		if ( $product->is_type( 'variable-mix-and-match' ) && $product->is_sharing_content( 'edit' ) ) {
			$value = $product->get_content_source( 'edit' );
		}

		return $value;
	}

	/**
	 * "Child Category Ids" column content.
	 *
	 * @param  mixed       $value
	 * @param  WC_Product  $product
	 * @return mixed       $value
	 */
	public static function export_child_category_ids( $value, $product ) {

		if ( $product->is_type( 'variable-mix-and-match' ) && $product->is_sharing_content( 'edit' ) ) {
			// Use the WC_Product_CSV_Exporter formatting for term IDs.
			$exporter = new WC_Product_CSV_Exporter();
			$value    = WC_MNM_Product_Export::prepare_child_category_ids_for_export( $product );
		}

		return $value;
	}


	/**
	 * MnM child items data column content.
	 *
	 * @param  mixed       $value
	 * @param  WC_Product  $product
	 * @return mixed       $value
	 */
	public static function export_child_items( $value, $product ) {

		if ( $product->is_type( 'variable-mix-and-match' ) && $product->is_sharing_content( 'edit' ) ) {
			$value = WC_MNM_Product_Export::prepare_child_items_for_export( $product );
		}

		return $value;
	}

	/**
	 * "Container packing mode" column content.
	 *
	 * @param  mixed       $value
	 * @param  WC_Product  $product
	 * @return mixed       $value
	 */
	public static function export_packing_mode( $value, $product ) {

		if ( $product->is_type( 'variable-mix-and-match' ) ) {
			$value = $product->get_packing_mode( 'edit' );
		}

		return $value;
	}

	/**
	 * "Container Weight Cumulative" column content.
	 *
	 * @param  mixed       $value
	 * @param  WC_Product  $product
	 * @return mixed       $value
	 */
	public static function export_weight_cumulative( $value, $product ) {

		if ( $product->is_type( 'variable-mix-and-match' ) ) {
			$value = $product->get_weight_cumulative( 'edit' ) ? 1 : 0;
		}

		return $value;
	}

	/**
	 *--------------------------------------------------------------------------
	 * Variation-specific columns  
	 *--------------------------------------------------------------------------
	 */

	/**
	 * Inherited contents only need to be exported at the parent level.
	 *
	 * @param  mixed       $value
	 * @param  WC_Product  $product
	 * @return mixed       $value
	 */
	public static function remove_inherited_content( $value, $product ) {

		if ( $product->is_type( 'mix-and-match-variation' ) && $product->is_sharing_content( 'edit' ) ) {
			$value = ''; // Intentionally remove data for variations that inherit from parent.
		}

		return $value;
	}
}
WC_MNM_Variable_Product_Export::init();
