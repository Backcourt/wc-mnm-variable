<?php
/**
 * Compatibility - setup backcompatibility and extension compatibility
 *
 * @package  WooCommerce Mix and Match Variable Products/Compatibility
 * @since    1.0.0
 * @version  2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_MNM_Variable_Compatibility Class.
 *
 * Load classes for making Mix and Match compatible with other plugins|woocommerce|legacy.
 */
class WC_MNM_Variable_Compatibility {

	public static function init() {

		// Declare Features compatibility.
		add_action( 'before_woocommerce_init', [ __CLASS__, 'declare_features_compatibility' ] );

		add_filter( 'wc_mnm_compatibility_modules', [ __CLASS__, 'load_modules' ] );
	}

	/**
	 * Declare WooCommerce Features compatibility.
	 */
	public static function declare_features_compatibility() {

		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		// HPOS (Custom Order tables) compatibility.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WC_MNM_Variable::get_instance()->get_plugin_basename(), true );

		// Cart/Checkout Blocks compatibility.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WC_MNM_Variable::get_instance()->get_plugin_basename(), true );
	}

	/**
	 * Init compatibility classes.
	 */
	public static function load_modules( $module_paths ) {

		if ( array_key_exists( 'apfs-pricing', $module_paths ) ) {
			$module_paths['variable-apfs'] = WC_MNM_Variable::get_instance()->get_plugin_path() . 'includes/compatibility/modules/apfs/class-wc-mnm-variable-apfs-compatibility.php';
		}

		if ( array_key_exists( 'apfs-switching', $module_paths ) ) {
			$module_paths['variable-apfs-switching'] = WC_MNM_Variable::get_instance()->get_plugin_path() . 'includes/compatibility/modules/apfs/class-wc-mnm-variable-apfs-switching-compatibility.php';
		}

		return $module_paths;
	}
}
WC_MNM_Variable_Compatibility::init();
