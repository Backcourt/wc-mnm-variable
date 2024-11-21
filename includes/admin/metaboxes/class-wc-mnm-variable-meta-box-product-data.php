<?php
/**
 * Variable Mix and Match Product Data Metabox Class
 *
 * @package  WooCommerce Mix and Match Products/Admin/Meta-Boxes/Product
 * @since    1.0.0
 * @version  2.0.5
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_MNM_Variable_Meta_Box_Variable_Product_Data Class.
 *
 * Adds and save product meta.
 */
class WC_MNM_Variable_Meta_Box_Variable_Product_Data {

	/**
	 * Bootstraps the class and hooks required.
	 */
	public static function init() {

		add_filter( 'product_type_options', [ __CLASS__, 'product_type_options' ], 20 );

		// Creates the MnM panel tab.
		add_filter( 'woocommerce_product_data_tabs', [ __CLASS__, 'product_data_tab' ], 20 );

		// Creates the panel for selecting product options.
		add_action( 'woocommerce_product_data_panels', [ __CLASS__, 'product_data_panel' ] );

		// Fix the packing mode option display.
		add_filter( '_wc_mnm_backcompat_product_get_packing_mode', [ __CLASS__, 'fix_packing_mode' ] );

		// Adds the vmnm product options.
		// add_action( 'wc_mnm_admin_variable_product_options', [ __CLASS__, 'share_content_options' ], 20, 2 );
		add_action( 'wc_mnm_admin_variable_product_options', [ __CLASS__, 'allowed_contents_options' ], 30, 2 );
		// add_action( 'wc_mnm_admin_variable_product_options', [ __CLASS__, 'pricing_options' ], 40, 2 );

		// Add fields to variation.
		add_action( 'woocommerce_product_after_variable_attributes', [ __CLASS__, 'add_to_variations' ], 10, 3 );

		// add_action( 'wc_mnm_variation_options', [ __CLASS__, 'variation_discount_options' ], 10, 3 );
		add_action( 'wc_mnm_variation_options', [ __CLASS__, 'variation_size_options' ], 20, 3 );
		// add_action( 'wc_mnm_variation_options', [ __CLASS__, 'variation_content_options' ], 30, 3 );

		// Save handlers.
		add_action( 'woocommerce_admin_process_product_object', [ __CLASS__, 'save_product' ] );
		add_action( 'woocommerce_admin_process_variation_object', [ __CLASS__, 'save_variation' ], 30, 2 );
	}


	/**
	 * ---------------------------------------------------------------------------------
	 * Write Panel / metaobox
	 * ---------------------------------------------------------------------------------
	 */

	/**
	 * Hide NYP checkbox
	 *
	 * @param array $options
	 * @return array
	 */
	public static function product_type_options( $options ) {

		if ( isset( $options['nyp'] ) ) {
			$options['nyp']['wrapper_class'] .= ' hide_if_variable-mix-and-match';
		}

		return $options;
	}


	/**
	 * Adds the MnM Product write panel tabs.
	 *
	 * @param  array $tabs
	 * @return array
	 */
	public static function product_data_tab( $tabs ) {

		global $post, $product_object, $vmnm_product_object;

		// Force variations tab to show.
		$tabs['variations']['class'][] = 'show_if_variable-mix-and-match';
		$tabs['inventory']['class'][]  = 'show_if_variable-mix-and-match'; // Cannot add same to shipping tab as it hide shipping on simple products. Use JS instead.

		/*
		 * Create a global type object to use for populating fields.
		 */
		$post_id = $post->ID;

		if ( empty( $product_object ) || false === $product_object->is_type( 'variable-mix-and-match' ) ) {
			$vmnm_product_object = $post_id ? new WC_Product_Variable_Mix_and_Match( $post_id ) : new WC_Product_Variable_Mix_and_Match();
		} else {
			$vmnm_product_object = $product_object;
		}

		$tabs['vmnm_options'] = array(
			'label'    => __( 'Mix and Match', 'wc-mnm-variable' ),
			'target'   => 'variable_mix_and_match_product_data',
			'class'    => array( 'show_if_variable-mix-and-match', 'vmnm_product_tab', 'vmnm_product_options', 'mnm_product_options' ),
			'priority' => 46,
		);

		return $tabs;
	}

	/**
	 * Fix the value of the packing mode. 
	 * It's pulled from core MNM product object.
	 */
	public static function fix_packing_mode( $mode ) {
		global 	$vmnm_product_object;
		if ( $vmnm_product_object->get_id() ) {
			$mode = $vmnm_product_object->get_packing_mode( 'edit' );
		};
		return $mode;
	}

	/**
	 * Write panel.
	 */
	public static function product_data_panel() {
		global $post, $vmnm_product_object;

		?>
		<div id="variable_mix_and_match_product_data" class="vmnm_panel panel woocommerce_options_panel wc-metaboxes-wrapper hidden">
			<div class="options_group variable_mix_and_match">

				<?php

				/**
				 * Add Mix and Match Product Options.
				 *
				 * @param int $post_id
				 *
				 * @see self::share_content_options() - 20
				 * @see self::allowed_contents_options() - 30
				 * @see self::pricing_options() - 40
				 */
				do_action( 'wc_mnm_admin_variable_product_options', $post->ID, $vmnm_product_object );
				?>

			</div> <!-- options group -->
		</div>

		<?php
	}


	/**
	 * Render Shared contents options on 'wc_mnm_admin_variable_product_options'.
	 *
	 * @param  int $post_id
	 * @param  WC_Product_Variable_Mix_and_Match  $vmnm_product_object
	 */
	public static function share_content_options( $post_id, $vmnm_product_object ) {

		// Override option.
		wc_mnm_wp_toggle(
			array(
				'id'            => 'wc_mnm_variable_share_content',
				'wrapper_class' => 'show_if_variable-mix-and-match wc_mnm_display_toggle',
				'value'         => wc_bool_to_string( $vmnm_product_object->is_sharing_content( 'edit' ) ),
				'label'         => esc_html__( 'Share contents across variations', 'wc-mnm-variable' ),
			)
		);
	}

	/**
	 * Adds allowed contents select2 writepanel options.
	 *
	 * @param int $post_id
	 * @param  WC_Product_Variable_Mix_and_Match  $vmnm_product_object
	 */
	public static function allowed_contents_options( $post_id, $vmnm_product_object ) {

		?>

		<div class="form-row form-row-full">

			<?php

			woocommerce_wp_radio(
				array(
					'id'            => 'wc_mnm_variable_content_source',
					'class'         => 'select short wc_mnm_content_source',
					'wrapper_class' => 'wc_mnm_display_toggle',
					'label'         => __( 'Allowed content', 'wc-mnm-variable' ),
					'value'         => 'categories' === $vmnm_product_object->get_content_source( 'edit' ) ? 'categories' : 'products',
					'options'       => array(
						'products'   => __( 'Select individual products', 'wc-mnm-variable' ),
						'categories' => __( 'Select product categories', 'wc-mnm-variable' ),
					),
				)
			);

			// Generate some data for the select2 input.
			$child_items = 'products' === $vmnm_product_object->get_content_source( 'edit' ) ? $vmnm_product_object->get_child_items( 'edit' ) : array();

			// Exclude all but simple and variation products.
			$product_types = wc_get_product_types();
			unset( $product_types['simple'] );
			unset( $product_types['variation'] );
			$product_types = array_keys( $product_types );

			$values = array();
			foreach ( $child_items as $child_item ) {
				if ( $child_item->get_product() ) {
					$values[ $child_item->get_product()->get_id() ] = $child_item->get_product()->get_formatted_name();
				}
			}

			// Search args.
			$args = array(
				'id'                => 'wc_mnm_variable_allowed_products',
				'name'              => 'wc_mnm_variable_allowed_products[]',
				'class'             => 'wc-product-search wc-mnm-enhanced-select',
				'wrapper_class'     => 'form-field wc_mnm_source_products_field show_if_wc_mnm_variable_content_source_products hide_if_wc_mnm_variable_content_source_categories',
				'label'             => __( 'Select products', 'wc-mnm-variable' ),
				'value'             => $values,
				'style'             => 'width: 400px',
				'custom_attributes' => array(
					'multiple'          => 'multiple',
					'data-sortable'     => 'sortable',
					'data-placeholder'  => __( 'Search for a product&hellip;', 'wc-mnm-variable' ),
					'data-action'       => 'woocommerce_json_search_products_and_variations',
					'data-exclude_type' => join( ',', $product_types ),
				),
			);

			// Products search.
			wc_mnm_wp_enhanced_select( $args );

			// Generate some data for the select2 input.
			$selected_cats = $vmnm_product_object->get_child_category_ids( 'edit' );

			$values = [];

			foreach ( $selected_cats as $cat_id ) {
				$current_cat = get_term_by( 'term_id', $cat_id, 'product_cat' );

				if ( $current_cat instanceof WP_Term ) {
					$values[ $current_cat->term_id ] = $current_cat->name;
				}
			}

			// Search args.
			$args = array(
				'id'                => 'wc_mnm_variable_allowed_categories',
				'name'              => 'wc_mnm_variable_allowed_categories[]',
				'class'             => 'wc-mnm-enhanced-select wc-mnm-category-search',
				'wrapper_class'     => 'form-field wc_mnm_source_categories_field show_if_wc_mnm_variable_content_source_categories hide_if_wc_mnm_variable_content_source_products',
				'label'             => __( 'Select categories', 'wc-mnm-variable' ),
				'value'             => $values,
				'style'             => 'width: 400px',
				'custom_attributes' => array(
					'multiple'         => 'multiple',
					'data-sortable'    => 'sortable',
					'data-placeholder' => __( 'Search for a category&hellip;', 'wc-mnm-variable' ),
					'data-action'      => 'woocommerce_json_search_categories',
					'data-allow_clear' => true,
					'data-return_id'   => true,
				),
			);

			// Categories search.
			wc_mnm_wp_enhanced_select( $args );

			?>
		</div>
		<?php
	}


	/**
	 * Adds the MnM per-item pricing option.
	 *
	 * @param int $post_id
	 * @param  WC_Product_Variable_Mix_and_Match  $vmnm_product_object
	 */
	public static function pricing_options( $post_id, $vmnm_product_object ) {

		// Per-Item Pricing.
		woocommerce_wp_radio(
			array(
				'id'            => 'wc_mnm_variable_per_product_pricing',
				'class'         => 'wc_mnm_per_product_pricing wc_mnm_variable_per_product_pricing',
				'wrapper_class' => 'wc_mnm_display_toggle',
				'label'         => esc_html__( 'Pricing mode', 'wc-mnm-variable' ),
				'value'         => $vmnm_product_object->get_priced_per_product( 'edit' ) ? 'yes' : 'no',
				'options'       => array(
					'no'  => esc_html__( 'Fixed &mdash; the price never changes', 'wc-mnm-variable' ),
					'yes' => esc_html__( 'Per-item &mdash; the price depends on the selections', 'wc-mnm-variable' ),
				),
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Variations
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add inputs to each variation
	 *
	 * @param string  $loop
	 * @param array   $variation_data
	 * @param WP_Post $variation
	 * @return print HTML
	 */
	public static function add_to_variations( $loop, $variation_data, $variation ) {

		$variation_object = $variation->ID ? new WC_Product_Mix_and_Match_Variation( $variation->ID ) : new WC_Product_Mix_and_Match_Variation();

		?>

		<div class="wc_mnm_variation_options options_group show_if_variable-mix-and-match hide_if_not_variable-mix-and-match">

		<?php
			/**
			 * Add Mix and Match Variation Options.
			 *
			 * @see self::variation_discount_options - 10
			 * @see self::variation_size_options     - 20
			 * @see self::variation_content_options  - 30
			 */

			do_action( 'wc_mnm_variation_options', $variation_object, $loop );
		?>

		</div>

		<?php
	}


	/**
	 * Add container discount option to discount.
	 *
	 * @param  object WC_Product_Mix_and_Match_Variation $variation_object
	 * @param  mixed int   $loop - for use in variations
	 */
	public static function variation_discount_options( $variation_object, $loop ) {

		// Per-Item Discount.
		woocommerce_wp_text_input(
			array(
				'id'            => 'wc_mnm_variation_per_product_discount[' . $loop . ']',
				'wrapper_class' => 'show_if_wc_mnm_variable_per_product_pricing_yes hide_if_wc_mnm_variable_per_product_pricing_no',
				'label'         => __( 'Per-Item Discount (%)', 'wc-mnm-variable' ),
				'value'         => $variation_object->get_discount( 'edit' ),
				'description'   => __( 'Discount applied to each item when in per-item pricing mode. This discount applies only to this variation and whenever the quantity restrictions are satisfied.', 'wc-mnm-variable' ),
				'desc_tip'      => true,
				'data_type'     => 'decimal',
			)
		);
	}


	/**
	 * Add custom size inputs to each variation
	 *
	 * @param  object WC_Product_Mix_and_Match_Variation $variation_object
	 * @param  mixed int   $loop
	 */
	public static function variation_size_options( $variation_object, $loop ) {

		woocommerce_wp_text_input(
			array(
				'id'            => 'wc_mnm_variation_min_container_size[' . $loop . ']',
				'label'         => __( 'Container Size', 'wc-mnm-variable' ),
				'wrapper_class' => 'mnm_container_size_options form-row form-row-first',
				'description'   => __( 'Required quantity for Mix and Match containers.', 'wc-mnm-variable' ),
				'type'          => 'number',
				'value'         => $variation_object->get_min_container_size( 'edit' ),
				'desc_tip'      => true,
			)
		);

	}

	/**
	 * Adds allowed contents select2 writepanel options.
	 *
	 * @param  object WC_Product_Mix_and_Match_Variation $variation_object
	 * @param  int   $loop
	 */
	public static function variation_content_options( $variation_object, $loop ) {

		?>

		<div class="form-row form-row-full show_if_wc_mnm_variable_share_content_no hide_if_wc_mnm_variable_share_content_yes">

		<?php

		woocommerce_wp_radio(
			array(
				'id'            => 'wc_mnm_variation_content_source[' . $loop . ']',
				'class'         => 'select short wc_mnm_content_source',
				'wrapper_class' => 'wc_mnm_display_toggle',
				'label'         => __( 'Allowed content', 'wc-mnm-variable' ),
				'value'         => 'categories' === $variation_object->get_content_source( 'edit' ) ? 'categories' : 'products',
				'options'       => array(
					'products'   => __( 'Select individual products', 'wc-mnm-variable' ),
					'categories' => __( 'Select product categories', 'wc-mnm-variable' ),
				),
			)
		);

		// Generate some data for the select2 input.
		$child_items = ! $variation_object->is_sharing_content( 'edit' ) && 'products' === $variation_object->get_content_source( 'edit' ) ? $variation_object->get_child_items( 'edit' ) : [];

		// Exclude all but simple and variation products.
		$product_types = wc_get_product_types();
		unset( $product_types['simple'] );
		unset( $product_types['variation'] );
		$product_types = array_keys( $product_types );

		$values = array();
		foreach ( $child_items as $child_item ) {
			if ( $child_item->get_product() ) {
				$values[ $child_item->get_product()->get_id() ] = $child_item->get_product()->get_formatted_name();
			}
		}

		// Search args.
		$args = array(
			'id'                => 'wc_mnm_variation_allowed_products[' . $loop . ']',
			'name'              => 'wc_mnm_variation_allowed_products[' . $loop . '][]',
			'class'             => 'wc-product-search wc-mnm-enhanced-select',
			'wrapper_class'     => 'form-field wc_mnm_variation_allowed_products_field show_if_wc_mnm_variation_content_source_products hide_if_wc_mnm_variation_content_source_categories',
			'label'             => __( 'Select products', 'wc-mnm-variable' ),
			'value'             => $values,
			'style'             => 'width: 400px',
			'custom_attributes' => array(
				'multiple'          => 'multiple',
				'data-sortable'     => 'sortable',
				'data-placeholder'  => __( 'Search for a product&hellip;', 'wc-mnm-variable' ),
				'data-action'       => 'woocommerce_json_search_products_and_variations',
				'data-exclude_type' => join( ',', $product_types ),
			),
		);

		// Products search.
		wc_mnm_wp_enhanced_select( $args );

		// Generate some data for the select2 input.
		$selected_cats = ! $variation_object->is_sharing_content( 'edit' ) ? $variation_object->get_child_category_ids( 'edit' ) : [];

		$values = array();

		foreach ( $selected_cats as $cat_id ) {
			$current_cat = get_term_by( 'term_id', $cat_id, 'product_cat' );

			if ( $current_cat instanceof WP_Term ) {
				$values[ $current_cat->term_id ] = $current_cat->name;
			}
		}

		// Search args.
		$args = array(
			'id'                => 'wc_mnm_variation_allowed_categories[' . $loop . ']',
			'name'              => 'wc_mnm_variation_allowed_categories[' . $loop . '][]',
			'class'             => 'wc-mnm-enhanced-select wc-mnm-category-search',
			'wrapper_class'     => 'form-field wc_mnm_variable_allowed_categories_field show_if_wc_mnm_variation_content_source_categories hide_if_wc_mnm_variation_content_source_products',
			'label'             => __( 'Select categories', 'wc-mnm-variable' ),
			'value'             => $values,
			'style'             => 'width: 400px',
			'custom_attributes' => array(
				'multiple'         => 'multiple',
				'data-sortable'    => 'sortable',
				'data-placeholder' => __( 'Search for a category&hellip;', 'wc-mnm-variable' ),
				'data-action'      => 'woocommerce_json_search_categories',
				'data-allow_clear' => true,
				'data-return_id'   => true,
			),
		);

		// Categories search.
		wc_mnm_wp_enhanced_select( $args );

		?>
		</div>

		<?php
	}



	/*
	|--------------------------------------------------------------------------
	| Save.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Saves data for variable product
	 *
	 * @param  WC_Product_New_Variable  $product
	 */
	public static function save_product( $product ) {

		if ( $product->is_type( 'variable-mix-and-match' ) ) {

			$props = array(
				'share_content'          => true,
				// 'share_content'             => isset( $_POST['wc_mnm_variable_share_content'] ) && 'yes' === wc_clean( $_POST['wc_mnm_variable_share_content'] ),
				// 'priced_per_product'        => isset( $_POST['wc_mnm_variable_per_product_pricing'] ) && 'yes' === wc_clean( $_POST['wc_mnm_variable_per_product_pricing'] ),
					'priced_per_product' => false,
				'packing_mode'           => 'together',
				'weight_cumulative'      => isset( $_POST['wc_mnm_weight_cumulative'] ) && 'cumulative' === wc_clean( $_POST['wc_mnm_weight_cumulative'] ),
				'content_source'         => isset( $_POST['wc_mnm_variable_content_source'] ) ? wc_clean( $_POST['wc_mnm_variable_content_source'] ) : 'products',
				'child_items'            => [],
				'child_category_ids'     => isset( $_POST['wc_mnm_variable_allowed_categories'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['wc_mnm_variable_allowed_categories'] ) ) : [],
			);

			// Packing mode.
			if ( ! empty( $_POST['wc_mnm_packing_mode'] ) ) {
				$mode                  = wc_clean( $_POST['wc_mnm_packing_mode'] );
				$mode                  = 'separate' === $mode && isset( $_POST['wc_mnm_has_physical_container'] ) ? 'separate_plus' : $mode;
				$props['packing_mode'] = $mode;
			}

			if ( ! defined( 'WC_MNM_UPDATING' ) && ! defined( 'WC_MNM_NEEDS_DB_UPDATE' ) ) {

				// Set child items.
				$props['child_items'] = WC_MNM_Meta_Box_Product_Data::process_child_items_data( $product, ! empty( $_POST['wc_mnm_variable_allowed_products'] ) ? $_POST['wc_mnm_variable_allowed_products'] : [] );

				// Show a notice if the user hasn't selected any items for the container.
				if ( 'yes' === $props['share_content'] && apply_filters( 'wc_mnm_display_empty_container_error', true, $product ) ) {

					if ( 'categories' === $props['content_source'] && empty( $props['child_category_ids'] ) ) {
						WC_Admin_Meta_Boxes::add_error( __( 'Please select at least one category to use for this Variable Mix and Match product.', 'wc-mnm-variable' ) );
					} elseif ( 'products' === $props['content_source'] && empty( $props['child_items'] ) ) {
						WC_Admin_Meta_Boxes::add_error( __( 'Please select at least one product to use for this Variable Mix and Match product.', 'wc-mnm-variable' ) );
					}
				}

				// Finally, set the properties for saving.
				$product->set_props( $props );

			} else {
				WC_Admin_Meta_Boxes::add_error( __( 'Your changes have not been saved &ndash; please wait for the <strong>WooCommerce Mix and Match Data Update</strong> routine to complete before creating new Mix and Match products or making changes to existing ones.', 'wc-mnm-variable' ) );
			}
		}
	}

	/**
	 * Save data for variations
	 *
	 * @param WC_Product_Variation $variation
	 * @param int $i
	 */
	public static function save_variation( $variation, $i ) {

		$props = [
			'min_container_size' => 0,
			'max_container_size' => '',
			'discount'           => '',
			'content_source'     => 'products',
			'child_category_ids' => [],
			'child_items'        => [],
		];

		// Set the min container size.
		if ( ! empty( $_POST['wc_mnm_variation_min_container_size'] ) && ! empty( $_POST['wc_mnm_variation_min_container_size'][ $i ] ) ) {
			$props['min_container_size'] = absint( wc_clean( $_POST['wc_mnm_variation_min_container_size'][ $i ] ) );
		}

		// Set the max container size.
		// if ( ! empty( $_POST['wc_mnm_variation_max_container_size'] ) && ! empty( $_POST['wc_mnm_variation_max_container_size'][$i] ) ) {
		// $props['max_container_size'] = absint( wc_clean( $_POST['wc_mnm_variation_max_container_size'][$i] ) );
		// }
		// For now, in the absence of per-item pricing support, keep container size fixed.
		$props['max_container_size'] = $props['min_container_size'];

		// Make sure the max container size is not smaller than the min size.
		if ( $props['max_container_size'] > 0 && $props['max_container_size'] < $props['min_container_size'] ) {
			$props['max_container_size'] = $props['min_container_size'];
		}

		// Set the per-item discount.
		if ( ! empty( $_POST['wc_mnm_variation_per_product_discount'] ) && ! empty( $_POST['wc_mnm_variation_per_product_discount'][ $i ] ) ) {
			$props['discount'] = wc_clean( wp_unslash( $_POST['wc_mnm_variation_per_product_discount'][ $i ] ) );
		}

		// Set the content source.
		if ( ! empty( $_POST['wc_mnm_variation_content_source'] ) && ! empty( $_POST['wc_mnm_variation_content_source'][ $i ] ) ) {
			$props['content_source'] = wc_clean( $_POST['wc_mnm_variation_content_source'][ $i ] );
		}

		// Set the child category IDs.
		if ( ! empty( $_POST['wc_mnm_variation_allowed_categories'] ) && ! empty( $_POST['wc_mnm_variation_allowed_categories'][ $i ] ) ) {
			$props['child_category_ids'] = wc_clean( wp_unslash( $_POST['wc_mnm_variation_allowed_categories'][ $i ] ) );
		}

		// Set the child items.
		if ( ! empty( $_POST['wc_mnm_variation_allowed_products'] ) && ! empty( $_POST['wc_mnm_variation_allowed_products'][ $i ] ) ) {
			$props['child_items'] = WC_MNM_Meta_Box_Product_Data::process_child_items_data( $variation, $_POST['wc_mnm_variation_allowed_products'][ $i ] );
		}

		$variation->set_props( $props );
	}
}

// Launch the admin class.
WC_MNM_Variable_Meta_Box_Variable_Product_Data::init();
