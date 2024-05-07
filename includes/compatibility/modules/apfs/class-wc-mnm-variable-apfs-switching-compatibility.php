<?php
/**
 * All Products for Subscriptions - Handles subscription contents switching
 *
 * @package  WooCommerce Mix and Match Products/Compatibility
 * @since    1.0.0
 * @version  1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Main WC_MNM_Variable_APFS_Switching_Compatibility class
 **/
if ( ! class_exists( 'WC_MNM_Variable_APFS_Switching_Compatibility' ) ) :

	class WC_MNM_Variable_APFS_Switching_Compatibility {

		/**
		 * Runtime cache.
		 *
		 * @var    array
		 */
		private static $cache = array();

		/**
		 * Hooks for MNM support.
		 */
		public static function add_hooks() {
			
			// Add variations to switch link.
			add_filter( 'wc_mnm_get_posted_container_form_data', array( __CLASS__, 'get_posted_container_form_data' ), 10, 3 );

			// Add current variation ID to switch link.
			add_filter( 'woocommerce_subscriptions_switch_url', array( __CLASS__, 'container_type_switch_configuration_url' ), 10, 4 );
			// Remove subscription options from variation data only when editing.
			add_action( 'wc_ajax_mnm_get_edit_container_order_item_form', array( __CLASS__, 'remove_variable_subscription_options' ), 1 );

		}


		/**
		 * Add attributes to variation switch link.
		 *
		 * @param  array       $form_data The params that will be used to build switch link
		 * @param  array       $configuration The container configuration
		 * @param  WC_Product  $product The container product
		 * @return boolean
		 */
		public static function get_posted_container_form_data( $form_data, $configuration, $container ) {

			if ( $container && $container->is_type( 'mix-and-match-variation' ) ) {

				$attributes = array_filter( $container->get_variation_attributes(), 'wc_array_filter_default_attributes' );

				if ( ! empty( $attributes ) ) {
					$form_data = array_merge( $form_data, $attributes );
				}

			}
		
			return $form_data;
		}


		/**
		 * Remove subscription options
		 * 
		 * Simple subscription options aren't added because `woocommerce_before_add_to_cart_button` doesn't exist in edit in `variable-edit-container.php` template.
		 * But we need to remove the filter that adds them to the variation data when in the ajax editing context.
		 */
		public static function remove_variable_subscription_options() {
			remove_filter( 'woocommerce_available_variation', array( 'WCS_ATT_Display_Product', 'add_subscription_options_to_variation_data' ), 1, 3 );
		}
	
		/**
		 * Add variation ID to switch link.
		 *
		 * @param  string           $url
		 * @param  int              $item_id
		 * @param  WC_Order_Item    $item
		 * @param  WC_Subscription  $subscription
		 * @return string
		 */
		public static function container_type_switch_configuration_url( $url, $item_id, $item, $subscription ) {

			if ( wc_mnm_is_container_order_item( $item, $subscription ) ) {

				$configuration = WC_Mix_and_Match_Order::get_current_container_configuration( $item, $subscription );

				if ( ! empty( $configuration ) ) {

					$variation_id = $item->get_variation_id();

					if ( $variation_id ) {
						$url = add_query_arg( 'variation_id', $variation_id, $url );
					}

				}

			}

			return $url;
		}


	} // End class: do not remove or there will be no more guacamole for you.

endif; // End class_exists check.

WC_MNM_Variable_APFS_Switching_Compatibility::add_hooks();
