<?php
/**
 * Template hooks
 *
 * @package  WooCommerce Mix and Match Variable\Functions
 */

defined( 'ABSPATH' ) || exit;

// Add to cart template.
add_action( 'woocommerce_variable-mix-and-match_add_to_cart', 'wc_setup_loop', 0 );
add_action( 'woocommerce_variable-mix-and-match_add_to_cart', 'wc_mnm_variable_template_add_to_cart' );

// Single product template for Mix and Match. Form location: After summary.
add_action( 'woocommerce_after_single_product_summary', 'wc_mnm_variable_template_add_to_cart_after_summary', -1000 );

// Display MNM variation contents.
add_action( 'woocommerce_single_variation', 'wc_mnm_template_single_variation', 15 );

/**
 *--------------------------------------------------------------------------
 * Edit template hooks
 *--------------------------------------------------------------------------
 */

// Edit container form - stripped down add to cart form.
add_action( 'wc_mnm_edit_container_order_item_in_shop_order', 'wc_mnm_template_edit_variable_container_order_item', 10, 4 );
add_action( 'wc_mnm_edit_container_order_item_in_shop_subscription', 'wc_mnm_template_edit_variable_container_order_item', 10, 4 );

// Port variation add to cart elements.
add_action( 'wc_mnm_edit_container_order_item_single_variation', 'woocommerce_single_variation', 10 );
add_action( 'wc_mnm_edit_container_order_item_single_variation', 'wc_mnm_template_single_variation', 15, 3 );
