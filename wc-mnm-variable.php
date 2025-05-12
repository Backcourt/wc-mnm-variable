<?php
/**
 * Plugin Name: WooCommerce Mix and Match - Variable Mix and Match
 * Plugin URI: https://github.com/backcourt/wc-mnm-variable
 * Description: Variable mix and match product type
 * Version: 2.1.0
 * Author: Kathy Darling
 * Author URI: http://kathyisawesome.com/
 * Text Domain: wc-mnm-variable
 * Domain Path: /languages
 *
 * Update URI: backcourt/wc-mnm-variable
 * 
 * Requires PHP: 8.0
 *
 * Requires at least: 6.6.0
 * Tested up to: 6.7.0
 * 
 * WC requires at least: 9.4.0
 * WC tested up to: 9.6.0
 *
 * Copyright: © 2025 Kathy Darling
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use \Automattic\Jetpack\Constants;
use \Backcourt\MNMVariable\Vendor\Fragen;

 /**
  * Add Git Updater Lite
  */
if ( file_exists( __DIR__ . '/packages/autoload.php' ) ) {
	require_once __DIR__ . '/packages/autoload.php';
	( new Fragen\Git_Updater\Lite( __FILE__ ) )->run();
}

class WC_MNM_Variable {

	const VERSION         = '2.0.0';
	const REQ_WC_VERSION  = '8.0.0';
	const REQ_MNM_VERSION = '2.6.0';

	/**
	 * The single instance of the class.
	 *
	 * @var obj The WC_MNM_Variable object
	 */
	protected static $_instance = null;

	/**
	 * var int $current_variation_id
	 */
	private $current_variation_id = 0;

	/**
	 * Main WC_MNM_Variable instance.
	 *
	 * Ensures only one instance of WC_MNM_Variable is loaded or can be loaded.
	 *
	 * @return WC_MNM_Variable Single instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Attach hooks and filters
	 */
	public function attach_hooks_and_filters() {

		// Quietly quit if Woo is not active or below required version.
		if ( ! function_exists( 'wc' ) || version_compare( wc()->version, self::REQ_WC_VERSION ) < 0 ) {
			return false;
		}

		// Quietly quit if Mix and Match is not active.
		if ( ! function_exists( 'wc_mix_and_match' ) || version_compare( wc_mix_and_match()->version, self::REQ_MNM_VERSION ) < 0 ) {
			return false;
		}

		$this->includes();

		// Load template actions/functions later.
		add_action( 'template_redirect', [ $this, 'template_includes' ], -1 );

		// Include admin class to handle all back-end functions.
		if ( is_admin() ) {
			$this->admin_includes();
		}

		// Load translation files.
		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );

		/**
		 * Admin hooks
		 */

		// Install product type term.
		add_action( 'admin_init', [ $this, 'maybe_install' ] );

		// Allows the selection of the new product type
		add_filter( 'product_type_selector', [ $this, 'product_selector_filter' ] );

		// Admin.
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ], 20 );

		// Product data stores.
		add_filter( 'woocommerce_data_stores', [ $this, 'data_stores' ] );

		// Set and cache the type, which in turn loads the correct class.
		add_filter( 'woocommerce_product_type_query', [ $this, 'product_type_query' ], 10, 2 );

		// Set and cache the correct product class for mix and match variations.
		add_filter( 'woocommerce_product_class', [ $this, 'set_variation_class' ], 10, 4 );

		/**
		 * Front end
		 */
		// Register Scripts and Styles.
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ], 20 );

		// Display Scripts.
		add_action( 'woocommerce_variable-mix-and-match_add_to_cart', [ $this, 'load_scripts' ] );

		// QuickView support.
		add_action( 'wc_quick_view_enqueue_scripts', [ $this, 'load_scripts' ] );

		/**
		 * Cart
		 */
		// Register mix-and-match-variation as supported type.
		add_filter( 'wc_mnm_product_container_types', [ $this, 'register_container_type' ] );

		// Use the default variable product handler.
		add_filter( 'woocommerce_add_to_cart_handler', [ $this, 'add_to_cart_handler' ], 10, 2 );

		/**
		 * Ajax handlers
		 */
		add_action( 'wc_ajax_mnm_get_variation_container_form', array( $this, 'get_container_form' ) );

		/**
		 * Edit in admin
		 */
		// Load scripts in admin context.
		add_action( 'wc_mnm_container_editing_enqueue_scripts', [ $this, 'load_edit_scripts' ] );

		// Tells core that the Variable product is also editable.
		add_filter( 'wc_mnm_is_container_order_item_editable', [ $this, 'variable_is_editable' ], 10, 2 );

		// Force table/dropdowns layout of attributes when editing in admin.
		add_action( 'wc_mnm_edit_container_order_item_in_shop_order', array( __CLASS__, 'force_edit_container_styles' ), 0, 4 );
		add_action( 'wc_mnm_edit_container_order_item_in_shop_subscription', array( __CLASS__, 'force_edit_container_styles' ), 0, 4 );

		// Admin order style tweaks for variable mix and match.
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_inline_styles' ], 20 );

		// Handle change variation.
		add_filter( 'wc_mnm_get_product_from_edit_order_item', [ $this, 'switch_variation' ], 10, 4 );
	}

	/**
	 * Include files.
	 */
	public function includes() {
		// Shared functionality traits for product classes.
		include_once 'includes/traits/trait-wc-mnm-container-data-store.php';
		include_once 'includes/traits/trait-wc-mnm-product.php';
		include_once 'includes/traits/trait-wc-mnm-container.php';
		include_once 'includes/traits/trait-wc-mnm-container-child-items.php';

		// Product classes.
		include_once 'includes/class-wc-product-variable-mix-and-match.php';
		include_once 'includes/class-wc-product-mix-and-match-variation.php';

		// Data stores.
		include_once 'includes/data-stores/class-wc-product-variable-mix-and-match-data-store-cpt.php';
		include_once 'includes/data-stores/class-wc-product-mix-and-match-variation-data-store-cpt.php';

		// Compatibility.
		include_once 'includes/compatibility/class-wc-mnm-variable-compatibility.php';

		// REST API.
		include_once 'includes/rest-api/class-wc-mnm-variable-rest-api.php';
		include_once 'includes/rest-api/class-wc-mnm-variable-store-api.php';
	}

	/**
	 * Template files.
	 */
	public function template_includes() {
		include_once 'includes/wc-mnm-variable-template-functions.php';
		include_once 'includes/wc-mnm-variable-template-hooks.php';
	}


	/**
	 * Include admin files.
	 */
	public function admin_includes() {
		include_once 'includes/admin/metaboxes/class-wc-mnm-variable-meta-box-product-data.php';

		// Product Import/Export.
		if ( WC_MNM_Core_Compatibility::is_wc_version_gte( '3.1' ) ) {
			include_once 'includes/admin/export/class-wc-mnm-variable-product-export.php';
			include_once 'includes/admin/import/class-wc-mnm-variable-product-import.php';
		}
	}


	/*
	|--------------------------------------------------------------------------
	| Localization.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Make the plugin translation ready
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'wc-mnm-variable', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/*
	|--------------------------------------------------------------------------
	| Install.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Add new product type term.
	 */
	public function maybe_install() {
		if ( ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) && ! defined( 'IFRAME_REQUEST' ) ) {
			$term_exists = \get_term_by( 'slug', 'variable-mix-and-match', 'product_type' );
			if ( null === $term_exists ) {
				wp_insert_term( __( 'Variable Mix and Match', 'wc-mnm-variable' ), 'product_type', array( 'slug' => 'variable-mix-and-match' ) );
			}
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Admin.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Register the new product type
	 *
	 * @param array $product_types
	 * @return array
	 */
	public function product_selector_filter( $product_types ) {
		$product_types['variable-mix-and-match'] = __( 'Variable Mix and Match', 'wc-mnm-variable' );
		return $product_types;
	}


	/**
	 * Add custom javascripts to product page.
	 */
	public function admin_scripts() {

		// Get admin screen id
		$screen = get_current_screen();

		// WooCommerce product admin page
		if ( 'product' == $screen->id && 'post' == $screen->base ) {
			$suffix      = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			$script_path = 'assets/js/admin/wc-mnm-variable-mix-and-match-metabox' . $suffix . '.js';

			wp_enqueue_script( 'wc-mnm-variable-mix-and-match-metabox', plugins_url( $script_path, __FILE__ ), array( 'wc-admin-variation-meta-boxes' ), WC_Mix_and_Match()->get_file_version( $this->get_plugin_path() . '/' . $script_path, self::VERSION ), true );
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Product classes and data.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Use Variable product data store.
	 *
	 * @param array $stores key/value pairs of store name => class name
	 * @return array
	 */
	public function data_stores( $stores ) {
		$stores['product-variable-mix-and-match']  = 'WC_Product_Variable_Mix_and_Match_Data_Store_CPT';
		$stores['product-mix-and-match-variation'] = 'WC_Product_Mix_and_Match_Variation_Data_Store_CPT';
		return $stores;
	}



	/**
	 * Switch variation type.
	 *
	 * Checks the classname being used for a product variation to see if it should be a mix and match product
	 * variation, and if so, returns this as the class which should be instantiated (instead of the default
	 * WC_Product_Variation class).
	 *
	 * @param  mixed false|string $product_type Product type.
	 * @param int $product_id
	 * @return string $type Will be mapped to the name of the WC_Product_* class which should be instantiated to create an instance of this product.
	 */
	public function product_type_query( $product_type, $product_id ) {

		$cache_key    = WC_Cache_Helper::get_cache_prefix( 'product_' . $product_id ) . '_type_' . $product_id;
		$product_type = wp_cache_get( $cache_key, 'products' );

		if ( $product_type ) {
			return $product_type;
		}

		$post = get_post( $product_id );

		if ( $post instanceof WP_Post ) {

			if ( 'product_variation' === $post->post_type ) {

				$terms = get_the_terms( $post->post_parent, 'product_type' );

				$parent_product_type = ! empty( $terms ) && ! is_wp_error( $terms ) ? sanitize_title( current( $terms )->name ) : 'simple';

				if ( 'variable-mix-and-match' === $parent_product_type ) {
					$product_type = 'mix-and-match-variation';
					wp_cache_set( $cache_key, $product_type, 'products' );
				}
			}
		}

		return $product_type;
	}


	/**
	 * Switch variation class.
	 *
	 * Checks the classname being used for a product variation to see if it should be a mix and match product
	 * variation, and if so, returns this as the class which should be instantiated (instead of the default
	 * WC_Product_Variation class).
	 *
	 * @param  string $classname - The class for this product type.
	 * @param  string $product_type Product type.
	 * @param string $post_type
	 * @param int $product_id
	 * @return string $classname The name of the WC_Product_* class which should be instantiated to create an instance of this product.
	 */
	public function set_variation_class( $classname, $product_type, $post_type, $product_id ) {

		$cache_key           = WC_Cache_Helper::get_cache_prefix( 'product_' . $product_id ) . '_type_' . $product_id;
		$cached_product_type = wp_cache_get( $cache_key, 'products' );

		if ( ! $cached_product_type ) {

			$post = get_post( $product_id );

			if ( $post instanceof WP_Post ) {

				if ( 'product_variation' === $post->post_type ) {

					$terms = get_the_terms( $post->post_parent, 'product_type' );

					$parent_product_type = ! empty( $terms ) && ! is_wp_error( $terms ) ? sanitize_title( current( $terms )->name ) : 'simple';

					if ( 'variable-mix-and-match' === $parent_product_type ) {
						$cached_product_type = 'mix-and-match-variation';
						wp_cache_set( $cache_key, $cached_product_type, 'products' );
					}
				}
			}
		}

		if ( 'mix-and-match-variation' === $cached_product_type ) {
			$classname = 'WC_Product_Mix_and_Match_Variation';
		}

		return $classname;
	}


	/*
	|--------------------------------------------------------------------------
	| Scripts and Styles.
	|--------------------------------------------------------------------------
	*/


	/**
	 * Register scripts
	 */
	public function frontend_scripts( $auto_enqueue = false ) {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		$style_path    = 'assets/dist/frontend/style-variable-mnm.css';
		$style_url     = $this->get_plugin_url() . $style_path;
		$style_version = WC_Mix_and_Match()->get_file_version( $this->get_plugin_path() . $style_path, self::VERSION );

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'wc-mnm-variable-frontend', $style_url, [ 'wc-mnm-frontend' ], $style_version );

		wp_style_add_data( 'wc-mnm-variable-frontend', 'rtl', 'replace' );

		// We need a core script and it isn't registered in the admin. A bit hacky, but no other way to do this.
		if ( is_admin() ) {
			$script_url = apply_filters( 'woocommerce_get_asset_url', plugins_url( 'assets/js/frontend/add-to-cart-variation' . $suffix . '.js', WC_PLUGIN_FILE ), 'assets/js/frontend/add-to-cart-variation' );
			wp_register_script( 'wc-add-to-cart-variation', $script_url, [ 'jquery', 'wp-util', 'jquery-blockui' ], Constants::get_constant( 'WC_VERSION' ) );
			// We also need the wp.template for this script :).
			wc_get_template( 'single-product/add-to-cart/variation.php' );

			$params = array(
				'wc_ajax_url'                      => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'i18n_no_matching_variations_text' => esc_attr__( 'Sorry, no products matched your selection. Please choose a different combination.', 'wc-mnm-variable' ),
				'i18n_make_a_selection_text'       => esc_attr__( 'Please select some product options before adding this product to your cart.', 'wc-mnm-variable' ),
				'i18n_unavailable_text'            => esc_attr__( 'Sorry, this product is unavailable. Please choose a different combination.', 'wc-mnm-variable' ),
			);

			wp_localize_script( 'wc-add-to-cart-variation', 'wc_add_to_cart_variation_params', $params );
		}

		$script_path    = 'assets/js/frontend/wc-mnm-add-to-cart-variation' . $suffix . '.js';
		$script_url     = $this->get_plugin_url() . $script_path;
		$script_version = WC_Mix_and_Match()->get_file_version( $this->get_plugin_path() . $script_path, self::VERSION );

		wp_register_script( 'wc-mnm-add-to-cart-variation', $script_url, [ 'wc-add-to-cart-variation' ], $script_version, true );

		// Grab localization strings from core MNM.
		$mnm_params = WC_Mix_and_Match()->display::get_add_to_cart_parameters();

		$params = array(
			'wc_ajax_url'                    => \WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'form_nonce'                     => wp_create_nonce( 'wc_mnm_container_form' ),
			'i18n_form_error'                => __( 'Failed to initialize form. If this issue persists, please reload the page and try again.', 'wc-mnm-variable' ),
			'i18n_form_cleared'              => __( 'Your chosen container size has changed so your selections have been reset.', 'wc-mnm-variable' ),
			'i18n_selection_prompt'          => __( 'Choose %d selections', 'wc-mnm-variable' ),
			'i18n_selection_prompt_singular' => __( 'Choose %d selection', 'wc-mnm-variable' ),
			'display_thumbnails'             => wc_string_to_bool( get_option( 'wc_mnm_display_thumbnail', 'yes' ) ),
			'display_short_description'      => wc_string_to_bool( get_option( 'wc_mnm_display_short_description', 'no' ) ),
			'display_plus_minus_buttons'     => wc_string_to_bool( get_option( 'wc_mnm_display_plus_minus_buttons', 'no' ) ),
			'display_layout'                 => is_admin() ? 'tabular' : get_option( 'wc_mnm_layout', 'tabular' ),
			'num_columns'                    => (int) apply_filters( 'wc_mnm_grid_layout_columns', get_option( 'wc_mnm_number_columns', 3 ) ),
			'__experimental_link_target'     => '_self', // Default link target. acceptable values: _self, _blank
		);

		$params = apply_filters( 'wc_mnm_variable_add_to_cart_script_parameters', wp_parse_args( $params, $mnm_params ) );

		wp_localize_script( 'wc-mnm-add-to-cart-variation', 'WC_MNM_ADD_TO_CART_VARIATION_PARAMS', $params );

		// React script.
		$script_path = 'assets/dist/frontend/variable-mnm.js';
		$script_url  = untrailingslashit( $this->get_plugin_url() ) . '/' . $script_path;

		$script_asset_path = $this->get_plugin_path() . '/assets/dist/frontend/variable-mnm.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => WC_Mix_and_Match()->get_file_version( $this->get_plugin_path() . '/' . $script_path ),
			);

		$dependencies = array_merge( $script_asset['dependencies'], [ 'wc-price-format' ] );

		wp_register_script(
			'wc-mnm-add-to-cart-reactified',
			$script_url,
			$dependencies,
			$script_asset['version'],
			true
		);

		if ( $auto_enqueue ) {
			$this->load_scripts();
		}
	}


	/**
	 * Load the script anywhere the MNN add to cart button is displayed
	 */
	public function load_scripts() {
		wp_enqueue_script( 'wc-add-to-cart-variation' );
		wp_enqueue_script( 'wc-mnm-add-to-cart-variation' );
		wp_enqueue_script( 'wc-mnm-add-to-cart-reactified' );
	}

	/**
	 * Force form location to be default
	 *
	 * @param  string $value
	 * @return string
	 */
	public function force_form_location( $value ) {
		return 'default';
	}


	/*
	|--------------------------------------------------------------------------
	| Cart.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Register variation as supported container type
	 *
	 * @param  array  $types
	 * @return array
	 */
	public function register_container_type( $types ) {
		$types[] = 'mix-and-match-variation';
		return $types;
	}


	/**
	 * Validates that all MnM items chosen can be added-to-cart before actually starting to add items.
	 *
	 * @param  bool $passed_validation
	 * @param  int  $product_id
	 * @param  int  $quantity
	 * @param  int  $variation_id
	 * @param array $variation - selected attribues
	 * @param array $cart_item_data - data from session
	 * @return bool
	 */
	public function add_to_cart_validation( $passed_validation, $product_id, $quantity, $variation_id = '', $variations = array(), $cart_item_data = array() ) {

		if ( ! $passed_validation ) {
			return false;
		}

		/**
		 * Prevent child items from getting validated when re-ordering after cart session data has been loaded:
		 * They will be added by the container item on 'woocommerce_add_to_cart'.
		 */
		if ( WC_Mix_and_Match()->cart->is_cart_session_loaded() ) {
			if ( isset( $cart_item_data['is_order_again_mnm_item'] ) ) {
				return false;
			}
		}

		$product_type = WC_Product_Factory::get_product_type( $product_id );

		if ( 'variable-mix-and-match' === $product_type ) {

			// Validate the variation as a container.
			$container = wc_get_product( $variation_id );

			$passed_validation = WC_Mix_and_Match()->cart->validate_container_add_to_cart( $container, $quantity, $cart_item_data );

		}

		return $passed_validation;
	}


	/**
	 * Use the default variable product add to cart handler.
	 *
	 * @param  string  $product_type
	 * @param  WC_Product  $product
	 * @return string
	 */
	public function add_to_cart_handler( $product_type, $product ) {

		if ( 'variable-mix-and-match' === $product_type ) {
			$product_type = 'variable';
		}

		return $product_type;
	}


	/*
	|--------------------------------------------------------------------------
	| Ajax callbacks.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Return the specific MNM variation template
	 *
	 * @param  mixed int|WC_Product $product
	 * @return string
	 */
	public function get_variation_template_html( $product ) {

		if ( is_numeric( $product ) ) {
			$product = wc_get_product( intval( $product ) );
		}

		$html = '';

		if ( $product && $product->is_type( 'mix-and-match-variation' ) ) {
			ob_start();
			do_action( 'wc_mnm_variation_add_to_cart', $product );
			$html = ob_get_clean();
		}

		return $html;
	}

	/**
	 * Form content used to populate variation.
	 */
	public function get_container_form() {

		$variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;

		/**
		 * `wc_mnm_get_ajax_product_variation` filter for editing variation object.
		 *
		 * @param obj WC_Product_Variation $product
		 */
		$product = apply_filters( 'wc_mnm_get_ajax_product_variation', wc_get_product( $variation_id ) );

		if ( ! $product ) {
			$error = esc_html__( 'This product does not exist and so can not be configured', 'wc-mnm-variable' );
			wp_send_json_error( $error );
		}

		/*
		 * `wc_mnm_container_form_fragments` filter
		 *
		 * @param  array $fragments
		 * @param  $product WC_Product
		 */
		$response = apply_filters( 'wc_mnm_container_form_fragments', array( 'div.wc-mnm-container-form' => self::get_instance()->get_variation_template_html( $product ) ), $product );

		wp_send_json_success( $response );
	}

	/*
	|--------------------------------------------------------------------------
	| Admin Edit.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Load the scripts required for order editing.
	 *
	 * @param int $item_id The subscription line item ID.
	 * @param WC_Order_Item|array $item The subscription line item.
	 * @param WC_Subscription $subscription The subscription.
	 */
	public function load_edit_scripts() {
		$this->frontend_scripts( true );
	}

	/**
	 * Enable admin editing for Variable Mix and Match
	 *
	 * @param bool $is_editable
	 * @param WC_Product
	 * @return bool
	 */
	public function variable_is_editable( $is_editable, $product ) {

		if ( $product && $product->is_type( 'variable-mix-and-match' ) ) {
			$is_editable = true;
		}

		return $is_editable;
	}

	/**
	 * Force default tabular attributes layout.
	 *
	 * @param  $product  WC_Product_Mix_and_Match_Variation
	 * @param  $order_item WC_Order_Item
	 * @param  $order      WC_Order
	 * @param  string $source The originating source loading this template
	 */
	public static function force_edit_container_styles( $product, $order_item, $order, $source ) {
		if ( 'metabox' === $source ) {
			add_filter( 'wc_mnm_variation_swatches_threshold', '__return_zero' );
		}
	}

	/**
	 * Register scripts
	 */
	public function admin_inline_styles() {

		// Inline styles.
		$custom_css = '
			.wc-mnm-backbone-modal form.edit_container > .variations th {
				text-align: left;
			}
			.rtl .wc-mnm-backbone-modal form.edit_container > .variations th {
				text-align: right;
			}
			.wc-mnm-backbone-modal form.edit_container > .single_variation_wrap {
				margin-left: 0 !important;
				margin-right: 0 !important;
			}
			.wc-mnm-backbone-modal form.edit_container .single_variation_wrap .single_variation {
				padding: 1rem;
			}
			.wc-mnm-backbone-modal form.edit_container .single_variation_wrap .wc-mnm-variation > *:not(.mnm_reset):not(.mnm_status) {
				padding: 0 1rem 1rem 1rem;
			}
			.wc-mnm-backbone-modal form.edit_container .single_variation_wrap .wc-mnm-variation table.mnm_child_products {
				padding: 0;
			}
			.wc-mnm-backbone-modal form.edit_container .single_variation_wrap .wc-mnm-variation .mnm_reset {
				margin-left: 1rem;
				margin-right: 1rem;
			}
			.wc-mnm-backbone-modal form.edit_container .blockUI.blockOverlay::before { border: none; }
		';
		wp_add_inline_style( 'wc-mnm-admin-order-style', $custom_css );
	}

	/**
	 * Switch the product object if variation.
	 *
	 * @param obj WC_Product $product
	 * @param obj WC_Order_Item
	 * @param obj WC_Order
	 * @param  string $source The originating source loading this template
	 * @return WC_Product
	 */
	public static function switch_variation( $product, $container_item, $subscription, $source ) {

		// Detect a variation switch.
		if ( ! empty( $_POST['variation_id'] ) && intval( $_POST['variation_id'] ) !== $container_item->get_variation_id() ) {
			$product = wc_get_product( intval( $_POST['variation_id'] ) );
		}

		return $product;
	}


	/*
	|--------------------------------------------------------------------------
	| Helpers.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return trailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) );
	}


	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	public function get_plugin_path() {
		return trailingslashit( plugin_dir_path( __FILE__ ) );
	}


	/**
	 * Get the plugin basename.
	 *
	 * @return string
	 */
	public function get_plugin_basename() {
		return plugin_basename( __FILE__ );
	}
}

/*
|--------------------------------------------------------------------------
| Launch the whole plugin.
|--------------------------------------------------------------------------
*/
add_action( 'plugins_loaded', [ WC_MNM_Variable::get_instance(), 'attach_hooks_and_filters' ], 20 );
