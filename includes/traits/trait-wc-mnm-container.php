<?php
/**
 * This ongoing trait will have shared calculation logic between WC_Product_Mix_and_Match and WC_Product_Mix_and_Match_Variation classes.
 *
 * @package WooCommerce Mix and Match Products\Traits
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait WC_MNM_Container.
 *
 * @since 1.0.0
 */
trait WC_MNM_Container {

	/**
	 * Price-specific data, used to calculate min/max product prices for display and min/max prices incl/excl tax.
	 *
	 * @var array
	 */
	private $pricing_data;

	/**
	 * Array of container price data for consumption by the front-end script.
	 *
	 * @var array
	 */
	private $container_price_data = array();

	/**
	 * Array of child item objects.
	 *
	 * @var null|WC_MNM_Child_Item[]
	 */
	private $child_items = null;

	/**
	 * Child items that need deleting are stored here.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	protected $child_items_to_delete = array();

	/**
	 * Indicates whether child items need saving.
	 *
	 * @var array
	 */
	private $child_items_changed = false;

	/**
	 * In per-product pricing mode, the sale status of the product is defined by the children.
	 *
	 * @var bool
	 */
	private $on_sale;

	/**
	 * True if product is NYP enabled.
	 *
	 * @var null|bool
	 */
	private $is_nyp = null;

	/**
	 * True if product data is in sync with children.
	 *
	 * @var bool
	 */
	private $is_synced = false;

	/**
	 * Runtime cache for calculated prices.
	 *
	 * @var array
	 */
	private $container_price_cache = array();

	/**
	 *  Define type-specific properties.
	 *
	 * @var array
	 */
	protected $container_props = array(
		'min_raw_price'             => '',
		'min_raw_regular_price'     => '',
		'max_raw_price'             => '',
		'max_raw_regular_price'     => '',
		'min_container_size'        => 0,
		'max_container_size'        => null,
		'layout_override'           => false,
		'layout'                    => 'tabular',
		'add_to_cart_form_location' => 'default',
		'discount'                  => 0,
		'content_source'            => 'products',
		'child_category_ids'        => array(),
		'child_items_stock_status'  => 'outofstock', // 'instock' | 'onbackorder' | 'outofstock' - This prop is not saved as meta.
	);


	/*
	|--------------------------------------------------------------------------
	| Getters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Checks if a product is virtual (has no shipping).
	 *
	 * @return bool
	 */
	public function is_virtual() {
		return apply_filters( 'woocommerce_is_virtual', in_array( $this->get_packing_mode(), array( 'virtual', 'separate' ) ), $this );
	}


	/**
	 * Returns the base active price of the MnM container.
	 *
	 * @param  string $context
	 * @return mixed
	 */
	public function get_price( $context = 'view' ) {
		$value = $this->get_prop( 'price', $context );
		return in_array( $context, array( 'view', 'sync' ) ) && $this->is_priced_per_product() ? (float) $value : $value;
	}


	/**
	 * Returns the base regular price of the MnM container.
	 *
	 * @param  string $context
	 * @return mixed
	 */
	public function get_regular_price( $context = 'view' ) {
		$value = $this->get_prop( 'regular_price', $context );
		return in_array( $context, array( 'view', 'sync' ) ) && $this->is_priced_per_product() ? (float) $value : $value;
	}


	/**
	 * Returns the base sale price of the MnM container.
	 *
	 * @param  string  $context
	 * @return mixed
	 */
	public function get_sale_price( $context = 'view' ) {
		$value = $this->get_prop( 'sale_price', $context );
		return in_array( $context, array( 'view', 'sync' ) ) && $this->is_priced_per_product() && '' !== $value ? (float) $value : $value;
	}


	/**
	 * Minimum raw MnM container price getter.
	 *
	 * @param  string  $context
	 * @return string
	 */
	public function get_min_raw_price( $context = 'view' ) {
		$this->sync();
		$value = $this->get_prop( 'min_raw_price', $context );
		return in_array( $context, array( 'view', 'sync' ) ) && $this->is_priced_per_product() && '' !== $value ? (float) $value : $value;
	}


	/**
	 * Minimum raw regular MnM container price getter.
	 *
	 * @param  string  $context
	 * @return string
	 */
	public function get_min_raw_regular_price( $context = 'view' ) {
		$this->sync();
		$value = $this->get_prop( 'min_raw_regular_price', $context );
		return in_array( $context, array( 'view', 'sync' ) ) && $this->is_priced_per_product() && '' !== $value ? (float) $value : $value;
	}


	/**
	 * Minimum raw MnM container price getter.
	 *
	 * @param  string  $context
	 * @return string
	 */
	public function get_max_raw_price( $context = 'view' ) {
		$this->sync();
		$value = $this->get_prop( 'max_raw_price', $context );
		$value = 'edit' !== $context && $this->get_max_container_size() && $this->is_priced_per_product() && '' !== $value ? (float) $value : $value;
		$value = 'edit' === $context && '' === $value ? 9999999999.0 : $value;
		return $value;
	}


	/**
	 * Minimum raw regular MnM container price getter.
	 *
	 * @param  string  $context
	 * @return string
	 */
	public function get_max_raw_regular_price( $context = 'view' ) {
		$this->sync();
		$value = $this->get_prop( 'max_raw_regular_price', $context );
		$value = 'edit' !== $context && $this->get_max_container_size() && $this->is_priced_per_product() && '' !== $value ? (float) $value : $value;
		$value = 'edit' === $context && '' === $value ? 9999999999.0 : $value;
		return $value;
	}


	/**
	 * Per-Item Pricing getter.
	 *
	 * @param  string $context
	 * @return bool
	 */
	public function get_priced_per_product( $context = 'view' ) {
		return $this->get_prop( 'priced_per_product', $context );
	}


	/**
	 * Per-Item Discount getter.
	 *
	 * @since  1.4.0
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_discount( $context = 'view' ) {
		$value = $this->get_prop( 'discount', $context );

		if ( 'edit' !== $context ) {
			$value = floatval( $this->is_priced_per_product() ? $value : 0 );
		}
		return $value;
	}

	/**
	 * Packing Mode getter.
	 *
	 * @param  string $context
	 * @return bool
	 */
	public function get_packing_mode( $context = 'view' ) {
		return $this->get_prop( 'packing_mode', $context );
	}

	/**
	 * Shipping weight cumulative getter.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_weight_cumulative( $context = 'view' ) {
		return $this->get_prop( 'weight_cumulative', $context );
	}


	/**
	 * Return the product's minimum size limit.
	 *
	 * @param  string $context
	 * @return int
	 */
	public function get_min_container_size( $context = 'view' ) {
		$value = $this->get_prop( 'min_container_size', 'edit' );

		/**
		 * Container minimum size.
		 *
		 * @param  str                $size
		 * @param  obj WC_Product     $product
		*/
		return 'view' === $context ? apply_filters( 'wc_mnm_container_min_size', $value, $this ) : $value;
	}

	/**
	 * Return the product's maximum size limit.
	 *
	 * @param  string $context
	 * @return mixed | string or int
	 */
	public function get_max_container_size( $context = 'view' ) {
		$value = $this->get_prop( 'max_container_size', 'edit' );

		/**
		 * Container maximum size.
		 *
		 * @param  mixed              $size
		 * @param  obj WC_Product     $product
		*/
		return 'view' === $context ? apply_filters( 'wc_mnm_container_max_size', $value, $this ) : $value;
	}


	/*
	|--------------------------------------------------------------------------
	| Setters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Minimum raw price setter.
	 *
	 * @param string $price Min Raw Price.
	 */
	public function set_min_raw_price( $price ) {
		$this->set_prop( 'min_raw_price', wc_format_decimal( $price ) );
	}


	/**
	 * Minimum raw regular price setter.
	 *
	 * @param string $price Min Raw Regular Price.
	 */
	public function set_min_raw_regular_price( $price ) {
		$this->set_prop( 'min_raw_regular_price', wc_format_decimal( $price ) );
	}


	/**
	 * Maximum raw price setter.
	 *
	 * @param string $price Max Raw Price.
	 */
	public function set_max_raw_price( $price ) {
		$this->set_prop( 'max_raw_price', wc_format_decimal( min( $price, 9999999999 ) ) );
	}


	/**
	 * Maximum raw regular price setter.
	 *
	 * @param string $price Max Raw Regular Price.
	 */
	public function set_max_raw_regular_price( $price ) {
		$this->set_prop( 'max_raw_regular_price', wc_format_decimal( min( $price, 9999999999 ) ) );
	}


	/**
	 * Per-Item Pricing setter.
	 *
	 * @param  string  $value
	 */
	public function set_priced_per_product( $value ) {
		$this->set_prop( 'priced_per_product', wc_string_to_bool( $value ) );
	}


	/**
	 * Per-Item Pricing Discount setter.
	 *
	 * @param  string  $value
	 */
	public function set_discount( $value ) {
		$this->set_prop( 'discount', wc_format_decimal( $value ) );
	}


	/**
	 * Packing Mode setter.
	 *
	 * @param  string  $value 'virtual' | 'together' | 'separate' | 'separate_plus'
	 *    'virtual'       - Everything is virtual.
	 *    'together'      - Packed as a single unit.
	 *    'separate'      - Packed separately, no physical container.
	 *    'separate_plus' - Packed separately, with physical container.
	 */
	public function set_packing_mode( $value ) {
		$value = $value && in_array( $value, array( 'virtual', 'together', 'separate', 'separate_plus' ) ) ? $value : 'together';
		$this->set_prop( 'packing_mode', $value );
	}


	/**
	 * Shipping weight calculation setter.
	 *
	 * @param  string $value
	 */
	public function set_weight_cumulative( $value ) {
		$this->set_prop( 'weight_cumulative', wc_string_to_bool( $value ) );
	}

	/**
	 * Set the product's minimum size limit.
	 *
	 * @param  string  $value
	 */
	public function set_min_container_size( $value ) {
		$this->set_prop( 'min_container_size', '' !== $value ? absint( $value ) : 0 );
	}


	/**
	 * Set the product's maximum size limit.
	 *
	 * @param  string  $value
	 */
	public function set_max_container_size( $value ) {
		$this->set_prop( 'max_container_size', '' !== $value ? absint( $value ) : '' );
	}

	/**
	 * Set child items stock status.
	 *
	 * @param string  $status - 'instock' | 'onbackorder' | 'outofstock'
	 *    'instock'     - Child items stock can fill all slots.
	 *    'onbackorder' - Child items stock must be backordered to fill all slots.
	 *    'outofstock'  - Child items do not have enough stock to fill all slots.
	 */
	public function set_child_items_stock_status( $status = '' ) {
		$status = in_array( $status, array( 'instock', 'outofstock', 'onbackorder' ) ) ? $status : 'instock';
		$this->set_prop( 'child_items_stock_status', $status );
	}

	/*
	|--------------------------------------------------------------------------
	| Conditionals
	|--------------------------------------------------------------------------
	*/


	/**
	 * Is this a NYP product?
	 *
	 * @return bool
	 */
	public function is_nyp() {
		if ( is_null( $this->is_nyp ) ) {
			$this->is_nyp = WC_Mix_and_Match()->compatibility->is_nyp( $this );
		}
		return $this->is_nyp;
	}


	/**
	 * A MnM product must contain children and have a price in static mode only.
	 *
	 * @return bool
	 */
	public function is_purchasable() {

		$is_purchasable = true;

		// Not purchasable while updating DB.
		if ( defined( 'WC_MNM_UPDATING' ) ) {
			$is_purchasable = false;

			// Products must exist of course.
		} elseif ( ! $this->exists() ) {
			$is_purchasable = false;

			// When priced statically a price needs to be set.
		} elseif ( false === $this->is_priced_per_product() && '' === $this->get_price() ) {

			$is_purchasable = false;

			// Check the product is published.
		} elseif ( $this->get_status() !== 'publish' && ! current_user_can( 'edit_post', $this->get_id() ) ) {

			$is_purchasable = false;

		} elseif ( ! $this->has_child_items() ) {

			$is_purchasable = false;

		}

		/**
		 * WooCommerce product is purchasable.
		 *
		 * @param  str $is_purchasable
		 * @param  obj WC_Product_Mix_and_Match $this
		 */
		return apply_filters( 'woocommerce_is_purchasable', $is_purchasable, $this );
	}


	/**
	 * Returns whether or not the product container's price is based on the included items.
	 *
	 * @param string $context
	 * @return bool
	 */
	public function is_priced_per_product( $context = 'view' ) {

		$is_priced_per_product = (bool) $this->get_priced_per_product();

		/**
		 * `wc_mnm_container_is_priced_per_product` filter
		 *
		 * @param  bool $is_purchasable
		 * @param  obj WC_Product_Mix_and_Match $this
		 */
		return 'view' === $context ? (bool) apply_filters( 'wc_mnm_container_is_priced_per_product', $is_priced_per_product, $this ) : $is_priced_per_product;
	}


	/**
	 * Returns whether or not the product container's price is based on the included items.
	 *
	 * @since  1.4.0
	 *
	 * @param string $context
	 * @return bool
	 */
	public function has_discount( $context = 'view' ) {

		$has_discount = $this->get_priced_per_product() && $this->get_discount() > 0;

		/**
		 * `wc_mnm_container_has_discount` filter
		 *
		 * @param  bool $has_discount
		 * @param  obj WC_Product_Mix_and_Match $this
		 */
		return 'view' === $context ? apply_filters( 'wc_mnm_container_has_discount', $has_discount, $this ) : $has_discount;
	}


	/**
	 * Returns whether or not the child products are shipped as a single unit.
	 *
	 * @since 2.0.0
	 *
	 * @param  string  $context
	 * @return bool
	 */
	public function is_packed_together( $context = 'view' ) {

		$packed_together = in_array( $this->get_packing_mode( $context ), array( 'virtual', 'together' ) );

		if ( 'view' === $context && has_filter( 'woocommerce_mnm_shipped_per_product' ) ) {

			wc_deprecated_function( 'woocommerce_mnm_shipped_per_product', '2.0.0', 'wc_mnm_container_is_packed_together (NB: packed_together is the opposite of shipped_per_product)' );

			/**
			 * @param  bool $is_shipped_per_product
			 * @param  obj WC_Product_Mix_and_Match $this
			 */
			$packed_together = ! apply_filters( 'woocommerce_mnm_shipped_per_product', ! $packed_together, $this );
		}

		/**
		 * 'wc_mnm_container_is_packed_together' filter.
		 *
		 * @param  bool $is_packed_together
		 * @param  obj WC_Product_Mix_and_Match $this
		 */
		return 'view' === $context ? apply_filters( 'wc_mnm_container_is_packed_together', $packed_together, $this ) : $packed_together;
	}


	/**
	 * Returns whether or not the product container's shipping weight is cumulative.
	 *
	 * @param  string  $context
	 * @return bool
	 */
	public function is_weight_cumulative( $context = 'view' ) {

		$is_weight_cumulative = $this->needs_shipping() && $this->is_packed_together() && $this->get_weight_cumulative();
		/**
		 * 'wc_mnm_container_is_weight_cumulative' filter.
		 *
		 * @param  bool $is_weight_cumulative
		 * @param  obj WC_Product_Mix_and_Match $this
		 */
		return 'view' === $context ? apply_filters( 'wc_mnm_container_is_weight_cumulative', $is_weight_cumulative, $this ) : $is_weight_cumulative;
	}


	/**
	 * Returns whether container is in stock
	 *
	 * NB: Child items stock is only checked for the child items on the frontend.
	 *
	 * @return bool
	 */
	public function is_in_stock() {

		$is_in_stock = parent::is_in_stock();

		if ( ! is_admin() ) {

			$this->sync();

			if ( $is_in_stock && 'outofstock' === $this->get_child_items_stock_status() ) {
				$is_in_stock = false;
			}
		}

		return apply_filters( 'wc_mnm_container_is_in_stock', $is_in_stock, $this );
	}


	/**
	 * Override on_sale status of mnm product. In per-product-pricing mode, true if has discount or if there is a base sale price defined.
	 *
	 * @param  string  $context
	 * @return bool
	 */
	public function is_on_sale( $context = 'view' ) {

		$is_on_sale = false;

		if ( 'update-price' !== $context && $this->is_priced_per_product() ) {
			$is_on_sale = parent::is_on_sale( $context ) || ( $this->has_discount( $context ) && $this->get_min_raw_regular_price( $context ) > 0 );
		} else {
			$is_on_sale = parent::is_on_sale( $context );
		}

		/**
		 * `wc_mnm_container_is_on_sale` filter
		 *
		 * @param  str $is_on_sale
		 * @param  obj WC_Product_Mix_and_Match $this
		 */
		return 'view' === $context ? apply_filters( 'wc_mnm_container_is_on_sale', $is_on_sale, $this ) : $is_on_sale;
	}


	/**
	 * Stock of container is synced to allowed child items.
	 *
	 * @return bool
	 */
	public function is_synced() {
		return $this->is_synced;
	}


	/**
	 *
	 * Is this product ID in the allowed contents.
	 *
	 * @param  mixed WC_Product|int  $id | product or variation ID
	 *
	 * @return bool
	 */
	public function is_allowed_child_product( $id ) {
		$id = $id instanceof WC_Product ? $id->get_id() : intval( $id );
		return false !== $this->get_child_item_by_product_id( $id );
	}


	/**
	 * Is the configuration valid for this container?
	 *
	 * @param  string  $context
	 * @return bool
	 */
	public function is_config_valid( $config ) {
	}


	/*
	|--------------------------------------------------------------------------
	| Non-CRUD Getters
	|--------------------------------------------------------------------------
	*/

	/**
	 * Container stock status, taking child item stock limitations into account.
	 *
	 * @param  string  $context
	 * @return string
	 */
	public function get_container_stock_status( $context = 'view' ) {

		$stock_status = $this->get_stock_status( $context );

		if ( 'instock' === $stock_status ) {

			$child_status = $this->get_child_items_stock_status( $context );

			if ( 'outofstock' === $child_status ) {
				$stock_status = 'insufficientstock';
			} else {
				$stock_status = $child_status;
			}
		}

		return $stock_status;
	}

	/**
	 * Get child items stock status.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_child_items_stock_status( $context = 'view' ) {

		if ( ! is_admin() ) {
			$this->sync();
		}

		return $this->get_prop( 'child_items_stock_status', $context );
	}


	/**
	 * Adds container configuration data to the URL.
	 *
	 * @since 2.0.0
	 *
	 * @param  array|null $item_object item array If a cart or order item is passed, we can get a link containing the exact attributes selected for the variation, rather than the default attributes.
	 * @return string
	 */
	public function get_cart_edit_link( $item_object = null ) {

		$edit_link = get_permalink( $this->get_id() );

		if ( is_array( $item_object ) && isset( $item_object['mnm_config'] ) && is_array( $item_object['mnm_config'] ) ) {

			$qty_args = WC_Mix_and_Match()->cart->rebuild_posted_container_form_data( $item_object['mnm_config'], $this );

			if ( ! empty( $qty_args ) ) {
				$args      = array_merge(
					$qty_args,
					array(
						'quantity'         => isset( $item_object['quantity'] ) ? intval( $item_object['quantity'] ) : 0,
						'update-container' => isset( $item_object['key'] ) ? $item_object['key'] : '',
					)
				);
				$edit_link = add_query_arg( $args, $edit_link );
			}
		}

		return $edit_link;
	}

	/**
	 * Returns range style html price string without min and max.
	 *
	 * @param  mixed    $price    default price
	 * @return string             overridden html price string (old style)
	 */
	public function get_price_html( $price = '' ) {

		if ( ! $this->is_purchasable() ) {
			/**
			 * Empty price html.
			 *
			 * @param  str $empty_price
			 * @param  obj WC_Product_Mix_and_Match $this
			 */
			return apply_filters( 'wc_mnm_container_empty_price_html', '', $this );
		}

		if ( $this->is_priced_per_product() ) {

			$this->sync();

			// Get the price string.
			if ( $this->get_container_price( 'min' ) === '' ) {
				$price = apply_filters( 'wc_mnm_container_empty_price_html', '', $this );
			} elseif ( $this->get_max_container_size() && 0 === $this->get_container_price( 'min' ) && 0 === $this->get_container_price( 'max' ) ) {

				/**
				 * Free string.
				 *
				 * @param  str $free_string
				 * @param  obj WC_Product_Mix_and_Match $this
				 */
				$free_string = apply_filters( 'wc_mnm_container_show_free_string', false, $this ) ? _x( 'Free!', '[Frontend]', 'wc-mnm-variable' ) : $price;

				/**
				 * Free price html.
				 *
				 * @param  str $free_price
				 * @param  obj WC_Product_Mix_and_Match $this
				 */
				$price = apply_filters( 'wc_mnm_container_free_price_html', $free_string, $this );

			} elseif ( $this->is_on_sale() || $this->has_discount() ) {

				if ( $this->get_container_price( 'min' ) === $this->get_container_price( 'max' ) ) {
					$price = wc_format_sale_price( $this->get_container_regular_price( 'min' ), $this->get_container_price( 'min' ) );
				} elseif ( $this->get_max_container_size() ) {

					$show_discounted_ranges = apply_filters( 'wc_mnm_container_show_discounted_range_price', ! is_admin(), $this );

					if ( $show_discounted_ranges ) {
						$price  = '<del aria-hidden="true">' . wc_format_price_range( $this->get_container_regular_price( 'min' ), $this->get_container_regular_price( 'max' ) ) . '</del>';
						$price .= ' <ins>' . wc_format_price_range( $this->get_container_price( 'min' ), $this->get_container_price( 'max' ) ) . '</ins>';
					} else {
						$price = wc_format_price_range( $this->get_container_price( 'min' ), $this->get_container_price( 'max' ) );
					}
				} else {
					$price = sprintf(
						// translators: %s is a formatted price, ex:  Starting at $99
						_x( 'Starting at %s', '[Frontend]Price range, ex:  Starting at $99', 'wc-mnm-variable' ),
						wc_format_sale_price( $this->get_container_regular_price( 'min' ), $this->get_container_price( 'min' ) )
					);
				}

				$price .= $this->get_price_suffix();

				/**
				 * Sale price html.
				 *
				 * @param  str $sale_price
				 * @param  obj WC_Product_Mix_and_Match $this
				 */
				$price = apply_filters( 'wc_mnm_sale_price_html', $price, $this );

			} elseif ( $this->get_container_price( 'min' ) === $this->get_container_price( 'max' ) ) {

				$price = wc_price( $this->get_container_price( 'min' ) ) . $this->get_price_suffix();

			} else {

				// A range price.
				if ( $this->get_max_container_size() ) {
					$price = wc_format_price_range( $this->get_container_price( 'min' ), $this->get_container_price( 'max' ) );
				} else {
					$price = sprintf(
						_x( 'Starting at %s', '[Frontend]Price range, ex:  Starting at $99', 'wc-mnm-variable' ),
						wc_price( $this->get_container_price( 'min' ) )
					);
				}

				$price .= $this->get_price_suffix();

			}

			/**
			 * Mix and Match specific price html.
			 *
			 * @param  str $price
			 * @param  obj WC_Product_Mix_and_Match $this
			 */
			$price = apply_filters( 'wc_mnm_container_get_price_html', $price, $this );

			/**
			 * WooCommerce price html.
			 *
			 * @param  str $price
			 * @param  obj WC_Product_Mix_and_Match $this
			 */
			return apply_filters( 'woocommerce_get_price_html', $price, $this );

		} else {

			return parent::get_price_html();
		}
	}


	/**
	 * Prices incl. or excl. tax are calculated based on the child products prices, so get_price_suffix() must be overridden to return the correct field in per-product pricing mode.
	 *
	 * @param  mixed    $price  price string
	 * @param  mixed    $qty  item quantity
	 * @return string    modified price html suffix
	 */
	public function get_price_suffix( $price = '', $qty = 1 ) {

		if ( $this->is_priced_per_product() ) {

			$price_suffix = get_option( 'woocommerce_price_display_suffix' );

			if ( $price_suffix ) {
				$price_suffix = ' <small class="woocommerce-price-suffix">' . $price_suffix . '</small>';

				if ( false !== strpos( $price_suffix, '{price_including_tax}' ) ) {
					$price_suffix = str_replace( '{price_including_tax}', wc_price( $this->get_container_price_including_tax() * $qty ), $price_suffix );
				}

				if ( false !== strpos( $price_suffix, '{price_excluding_tax}' ) ) {
					$price_suffix = str_replace( '{price_excluding_tax}', wc_price( $this->get_container_price_excluding_tax() * $qty ), $price_suffix );
				}
			}

			/**
			 * WooCommerce price suffix.
			 *
			 * @param  str $price_suffix
			 * @param  obj WC_Product_Mix_and_Match $this
			 * @param  mixed              $price
			 * @param  int                $qty
			 */
			return apply_filters( 'woocommerce_get_price_suffix', $price_suffix, $this, $price, $qty );

		} else {

			return parent::get_price_suffix();
		}
	}


	/**
	 * Get availability of container.
	 *
	 * @return array
	 */
	public function get_availability() {

		$availability = parent::get_availability();

		if ( ! is_admin() && parent::is_in_stock() ) {

			$container_stock_status = $this->get_container_stock_status();

			// If a child does not have enough stock, let people know.
			if ( 'insufficientstock' === $container_stock_status ) {

				$availability['availability'] = esc_html_x( 'Insufficient stock', '[Frontend]', 'wc-mnm-variable' );
				$availability['class']        = 'insufficientstock out-of-stock';

				// If a child is on backorder, the parent should appear to be on backorder, too.
			} elseif ( 'onbackorder' === $container_stock_status ) {

				$availability['availability'] = esc_html_x( 'Available on backorder', '[Frontend]', 'wc-mnm-variable' );
				$availability['class']        = 'available-on-backorder';

			}
		}

		/**
		 * 'wc_mnm_container_get_availability' filter.
		 *
		 * @param  array                     $availability
		 * @param  WC_Product_Mix_and_Match  $this
		 */
		return apply_filters( 'wc_mnm_container_get_availability', $availability, $this );
	}


	/**
	 * Get min/max container price.
	 *
	 * @param  string $min_or_max
	 * @return mixed
	 */
	public function get_container_price( $min_or_max = 'min', $display = false ) {
		return $this->calculate_price(
			array(
				'min_or_max' => $min_or_max,
				'calc'       => $display ? 'display' : '',
				'prop'       => 'price',
			)
		);
	}


	/**
	 * Get min/max container regular price.
	 *
	 * @param  string $min_or_max
	 * @return mixed
	 */
	public function get_container_regular_price( $min_or_max = 'min', $display = false ) {
		return $this->calculate_price(
			array(
				'min_or_max' => $min_or_max,
				'calc'       => $display ? 'display' : '',
				'prop'       => 'regular_price',
				'strict'     => true,
			)
		);
	}


	/**
	 * Get min/max container price excl tax.
	 *
	 * @return mixed
	 */
	public function get_container_price_including_tax( $min_or_max = 'min', $qty = 1 ) {
		return $this->calculate_price(
			array(
				'min_or_max' => $min_or_max,
				'qty'        => $qty,
				'calc'       => 'incl_tax',
				'prop'       => 'price',
			)
		);
	}


	/**
	 * Get min/max container price excl tax.
	 *
	 * @return mixed
	 */
	public function get_container_price_excluding_tax( $min_or_max = 'min', $qty = 1 ) {
		return $this->calculate_price(
			array(
				'min_or_max' => $min_or_max,
				'qty'        => $qty,
				'calc'       => 'excl_tax',
				'prop'       => 'price',
			)
		);
	}


	/**
	 * Calculates container prices.
	 *
	 * @param  array  $args
	 * @return mixed
	 */
	public function calculate_price( $args ) {

		$min_or_max = isset( $args['min_or_max'] ) && in_array( $args['min_or_max'], array( 'min', 'max' ) ) ? $args['min_or_max'] : 'min';
		$qty        = isset( $args['qty'] ) ? absint( $args['qty'] ) : 1;
		$price_prop = isset( $args['prop'] ) && in_array( $args['prop'], array( 'price', 'regular_price' ) ) ? $args['prop'] : 'price';
		$price_calc = isset( $args['calc'] ) && in_array( $args['calc'], array( 'incl_tax', 'excl_tax', 'display', '' ) ) ? $args['calc'] : '';

		if ( $this->is_priced_per_product() ) {

			$this->sync();

			$cache_key = md5(
				json_encode(
					apply_filters(
						'wc_mnm_container_prices_hash',
						array(
							'prop'       => $price_prop,
							'min_or_max' => $min_or_max,
							'calc'       => $price_calc,
							'qty'        => $qty,
						),
						$this
					)
				)
			);

			if ( isset( $this->container_price_cache[ $cache_key ] ) ) {
				$price = $this->container_price_cache[ $cache_key ];
			} else {

				$raw_price_fn = 'get_' . $min_or_max . '_raw_' . $price_prop;

				if ( '' === $this->$raw_price_fn() || INF === $this->$raw_price_fn() ) {
					$price = '';
				} else {

					$price_fn = 'get_' . $price_prop;

					$price = wc_format_decimal(
						WC_MNM_Product_Prices::get_product_price(
							$this,
							array(
								'price' => $this->$price_fn(),
								'qty'   => $qty,
								'calc'  => $price_calc,
							)
						),
						wc_get_price_decimals()
					);

					if ( ! empty( $this->pricing_data ) ) {
						foreach ( $this->pricing_data as $child_item_id => $data ) {

							$item_qty = $qty * $data[ 'slots_filled_' . $min_or_max ];

							if ( $item_qty ) {
								$child_item = $this->get_child_item( $child_item_id );
								if ( $child_item ) {

									$price += wc_format_decimal(
										WC_MNM_Product_Prices::get_product_price(
											$child_item->get_product(),
											array(
												'price' => $data[ $price_prop ],
												'qty'   => $item_qty,
												'calc'  => $price_calc,
											)
										),
										wc_get_price_decimals()
									);
								}
							}
						}
					}
				}

				$this->container_price_cache[ $cache_key ] = $price;
			}
		} else {

			$price_fn = 'get_' . $price_prop;
			$price    = WC_MNM_Product_Prices::get_product_price(
				$this,
				array(
					'price' => $this->$price_fn(),
					'qty'   => $qty,
					'calc'  => $price_calc,
				)
			);
		}

		return $price;
	}


	/**
	 * Gets price data array. Contains localized strings and price data passed to JS.
	 *
	 * @since  1.4.0
	 * @return array
	 */
	public function get_container_price_data() {

		$this->sync();

		if ( empty( $this->container_price_data ) ) {

			$container_price_data = array();

			$raw_container_price_min         = $this->get_container_price( 'min', true );
			$raw_container_price_max         = $this->get_container_price( 'max', true );
			$raw_container_regular_price_min = $this->get_container_regular_price( 'min', true );
			$raw_container_regular_price_max = $this->get_container_regular_price( 'max', true );

			$container_price_data['per_product_pricing'] = $this->is_priced_per_product() ? 'yes' : 'no';

			$container_price_data['raw_container_price_min'] = (float) $raw_container_price_min;
			$container_price_data['raw_container_price_max'] = '' === $raw_container_price_max ? '' : (float) $raw_container_price_max;

			// Deprecated data keys.
			$container_price_data['raw_container_min_price']         = $container_price_data['raw_container_price_min'];
			$container_price_data['raw_container_price']             = $container_price_data['raw_container_price_max'];
			$container_price_data['raw_container_min_regular_price'] = (float) $raw_container_regular_price_min;
			$container_price_data['raw_container_regular_price']     = '' === $raw_container_regular_price_max ? '' : (float) $raw_container_regular_price_max;

			$container_price_data['price_string']   = '%s';
			$container_price_data['is_purchasable'] = $this->is_purchasable() ? 'yes' : 'no';
			$container_price_data['is_in_stock']    = $this->is_in_stock() ? 'yes' : 'no';

			$container_price_data['show_free_string'] = ( $this->is_priced_per_product() ? apply_filters( 'wc_mnm_show_free_string', false, $this ) : true ) ? 'yes' : 'no';

			$container_price_data['prices']         = array();
			$container_price_data['regular_prices'] = array();

			$container_price_data['prices_tax'] = array();

			$container_price_data['quantities'] = array();

			$container_price_data['product_ids'] = array();

			$container_price_data['is_sold_individually'] = array();

			$container_price_data['base_price']         = $this->get_price();
			$container_price_data['base_regular_price'] = $this->get_regular_price();
			$container_price_data['base_price_tax']     = WC_MNM_Product_Prices::get_tax_ratios( $this );

			$container_price_data['price']         = $container_price_data['base_price'];
			$container_price_data['regular_price'] = $container_price_data['base_regular_price'];
			$container_price_data['price_tax']     = $container_price_data['base_price_tax'];

			$totals = new stdClass();

			$totals->price          = 0.0;
			$totals->regular_price  = 0.0;
			$totals->price_incl_tax = 0.0;
			$totals->price_excl_tax = 0.0;

			$container_price_data['base_price_subtotals'] = $totals;
			$container_price_data['base_price_totals']    = $totals;

			$container_price_data['addons_totals'] = $totals;

			$container_price_data['subtotals'] = $totals;
			$container_price_data['totals']    = $totals;

			$child_items = $this->get_child_items();

			if ( empty( $child_items ) ) {
				return;
			}

			foreach ( $child_items as $child_item_id => $child_item ) {

				$child_product    = $child_item->get_product();
				$child_product_id = $child_product->get_id();

				// Skip any product that isn't purchasable.
				if ( ! $child_product->is_purchasable() ) {
					continue;
				}

				$container_price_data['is_sold_individually'][ $child_product_id ] = $child_product->is_sold_individually() ? 'yes' : 'no';
				$container_price_data['product_ids'][ $child_product_id ]          = $child_product_id;
				$container_price_data['prices'][ $child_product_id ]               = $child_product->get_price();
				$container_price_data['regular_prices'][ $child_product_id ]       = $child_product->get_regular_price();
				$container_price_data['prices_tax'][ $child_product_id ]           = WC_MNM_Product_Prices::get_tax_ratios( $child_product );
				$container_price_data['quantities'][ $child_product_id ]           = 0;
				$container_price_data['child_item_subtotals'][ $child_product_id ] = $totals;
				$container_price_data['child_item_totals'][ $child_product_id ]    = $totals;

			}

			$this->container_price_data = apply_filters( 'wc_mnm_container_price_data', $container_price_data, $this );

		}

		return $this->container_price_data;
	}

	/**
	 * Get the data attributes
	 *
	 * @param array $args
	 * @return string
	 */
	public function get_data_attributes( $args = array() ) {

		$attributes = wp_parse_args(
			$args,
			array(
				'per_product_pricing' => $this->is_priced_per_product() ? 'true' : 'false',
				'container_id'        => $this->get_id(),
				'min_container_size'  => $this->get_min_container_size(),
				'max_container_size'  => $this->get_max_container_size(),
				'base_price'          => wc_get_price_to_display( $this, array( 'price' => $this->get_price() ) ),
				'base_regular_price'  => wc_get_price_to_display( $this, array( 'price' => $this->get_regular_price() ) ),
				'price_data'          => json_encode( $this->get_container_price_data() ),
				'input_name'          => wc_mnm_get_child_input_name( $this->get_id() ),
			)
		);

		/**
		 * `wc_mnm_container_data_attributes` Data attribues filter.
		 *
		 * @param  array $attributes
		 * @param  obj WC_Product_Mix_and_Match $this
		 */
		$attributes = (array) apply_filters( 'wc_mnm_container_data_attributes', wp_parse_args( $args, $attributes ), $this );

		return wc_mnm_prefix_data_attribute_keys( $attributes );
	}


	/**
	 * Get the min/max/step quantity of a child.
	 *
	 * @param  string $value options: 'min' | 'max' | 'step'
	 * @param  int $child_id
	 * @return int
	 */
	public function get_child_quantity( $value, $child_id ) {

		wc_deprecated_function( __METHOD__ . '()', '2.0.0', 'Handled at the item level. See: WC_MNM_Child_Item::get_quantity()' );

		$qty = '';

		$child_item = $this->get_child_item_by_product_id( $child_id );

		if ( $child_item ) {
			$qty = $child_item->get_quantity( $value );
		}

		return $qty;
	}


	/**
	 * Get the availability message of a child, taking its purchasable status into account.
	 *
	 * @param  int $child_id
	 * @return string
	 */
	public function get_child_availability_html( $child_id ) {

		wc_deprecated_function( __METHOD__ . '()', '2.0.0', 'Handled at the item level. See: WC_MNM_Child_Item::get_availability_html()' );

		$availability_html = '';

		$child_item = $this->get_child_item_by_product_id( $child_id );

		if ( $child_item ) {
			$availability_html = $child_item->get_availability_html();
		}

		return $availability_html;
	}


	/*
	|--------------------------------------------------------------------------
	| Sync with children.
	|--------------------------------------------------------------------------
	*/


	/**
	 * Sync child data such as price, availability, etc.
	 */
	public function sync() {

		if ( $this->is_synced() ) {
			return false;
		}

		$this->set_child_items_stock_status( 'instock' );

		$this->is_synced = true;
	}
}
