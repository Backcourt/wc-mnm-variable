<?php
/**
 * Variable Mix and Match
 *
 * The WooCommerce product class handles individual product data.
 *
 * @package Mix and Match Products\Classes
 * @since 1.0.0
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class WC_Product_Variable_Mix_and_Match extends WC_Product_Variable {

	use WC_MNM_Product;
	use WC_MNM_Container_Child_Items;

	/**
	 *  Define type-specific properties.
	 *
	 * @var array
	 */
	protected $extended_data = array(
		'layout_override' => false,
		'share_content'   => true,
	);


	/**
	 * __construct function.
	 *
	 * @param  mixed $product
	 */
	public function __construct( $product ) {
		$this->data = array_merge( $this->data, $this->extended_data, $this->shared_props, $this->contents_props );
		parent::__construct( $product );
	}


	/*
	|--------------------------------------------------------------------------
	| Getters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'variable-mix-and-match';
	}

	/**
	 * Share content getter.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_share_content( $context = 'view' ) {
		return true;
	}

	/**
	 * Returns the price in html format.
	 *
	 * Note: Variable prices do not show suffixes like other product types. This
	 * is due to some things like tax classes being set at variation level which
	 * could differ from the parent price. The only way to show accurate prices
	 * would be to load the variation and get it's price, which adds extra
	 * overhead and still has edge cases where the values would be inaccurate.
	 *
	 * Additionally, ranges of prices no longer show 'striked out' sale prices
	 * due to the strings being very long and unclear/confusing. A single range
	 * is shown instead.
	 *
	 * @param string $price Price (default: '').
	 * @return string
	 */
	public function get_price_html( $price = '' ) {

		if ( $this->is_priced_per_product() ) {

			/**
			 * WooCommerce price html.
			 *
			 * @param  str $price
			 * @param  obj WC_Product_Mix_and_Match $this
			 */
			return apply_filters( 'woocommerce_get_price_html', '', $this );

		} else {

			return parent::get_price_html();

		}
	}

	/**
	 * Get the add to cart button text for the single page.
	 *
	 * @return string
	 */
	public function single_add_to_cart_text() {

		$text = _x( 'Add to cart', '[Frontend]', 'wc-mnm-variable' );

		if ( isset( $_GET['update-container'] ) ) {

			$updating_cart_key = wc_clean( wp_unslash( $_GET['update-container'] ) );

			if ( isset( WC()->cart->cart_contents[ $updating_cart_key ] ) ) {
				$text = _x( 'Update cart', '[Frontend]', 'wc-mnm-variable' );
			}
		}

		/** WC core filter. */
		return apply_filters( 'woocommerce_product_single_add_to_cart_text', $text, $this );
	}

	/**
	 *--------------------------------------------------------------------------
	 * Setters
	 *--------------------------------------------------------------------------
	 */

	/**
	 * Shared contents setter.
	 *
	 * @param  string $value
	 * @return string
	 */
	public function set_share_content( $value ) {
		$this->set_prop( 'share_content', wc_string_to_bool( $value ) );
	}

	/**
	 *--------------------------------------------------------------------------
	 * Conditionals
	 *--------------------------------------------------------------------------
	 */

	/**
	 * Checks the product type to see if it is either this product's type or the parent's product type.
	 *
	 * @param mixed $type Array or string of types
	 * @return bool
	 */
	public function is_type( $type ) {
		if ( 'variable' === $type || ( is_array( $type ) && in_array( 'variable', $type ) ) ) {
			return true;
		} else {
			return parent::is_type( $type );
		}
	}

	/**
	 * Returns whether or not the product shares allowed contents or has variation-specific allowed contetns
	 *
	 * @param string $context
	 * @return bool
	 */
	public function is_sharing_content( $context = 'view' ) {
		return $this->get_share_content( $context );
	}
}
