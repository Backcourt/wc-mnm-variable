<?php
/**
 * Compatibility - setup backcompatibility and extension compatibility
 *
 * @package  WooCommerce Mix and Match Variable Products/Compatibility
 * @since    1.0.0
 * @version  2.2.0
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
		add_filter( 'wc_mnm_compatibility_modules', [ __CLASS__, 'load_modules' ] );
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
