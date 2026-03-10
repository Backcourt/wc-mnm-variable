<?php
/**
 * WooCommerce Store API
 *
 * Adds Mix and Match Variation Product data to the WooCommerce Store API.
 *
 * @package  WooCommerce Variable Mix and Match/Store API
 * @since    1.0.0
 * @version  2.3.0
 */

use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_MNM_Variable_Store_API Class.
 *
 * Adds WooCommerce Mix and Match variation data to WC Store API.
 */
class WC_MNM_Variable_Store_API {

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	const IDENTIFIER = 'variable_mix_and_match';

	/**
	 * Setup API class.
	 */
	public static function init() {

		// Add all variations to response.
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => ProductSchema::IDENTIFIER,
				'namespace'       => self::IDENTIFIER,
				'data_callback'   => array( __CLASS__, 'extend_product_data' ),
				'schema_callback' => array( __CLASS__, 'extend_product_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		// Preload REST Responses.
		add_action( 'woocommerce_variable-mix-and-match_add_to_cart', [ __CLASS__, 'preload_response' ] );
		add_action( is_admin() ? 'admin_print_footer_scripts' : 'wp_print_footer_scripts', array( __CLASS__, 'enqueue_asset_data' ), 0 );
	}

	/**
	 * Register child items product data into product endpoint.
	 *
	 * For variable mix-and-match products, we optimize by:
	 * 1. Serving base_child_items at the parent level (static data without quantities)
	 * 2. Serving only variation_overrides per variation (min/max/step quantities, container sizes)
	 * 
	 * This reduces redundant data transfer since child item images, names, etc. are the same across variations.
	 *
	 * @param WC_Product  $product
	 * @return array $item_data
	 */
	public static function extend_product_data( $product, $request = null ) {
		
		$item_data = [ 
			'variations'        => [],
			'base_child_items'  => [],
			'child_categories'  => [],
		];

		if ( $product->is_type( 'variable-mix-and-match' ) ) {

			$item_data['variations']       = array();
			$item_data['base_child_items'] = array();
			$item_data['child_categories'] = array();

			// Get base child items from the first available variation (all variations share the same child items).
			$variation_ids = $product->get_visible_children();

			if ( ! empty( $variation_ids ) ) {
				$first_variation = \wc_get_product( $variation_ids[0] );

				if ( $first_variation && \wc_mnm_is_product_container_type( $first_variation ) ) {
					$item_data['base_child_items'] = self::prepare_base_child_items( $first_variation );

					// Get child categories (same across all variations).
					$content_source = $first_variation->get_content_source();
					if ( 'products' !== $content_source ) {
						$item_data['child_categories'] = get_terms(
							array(
								'taxonomy' => 'product_cat',
								'include'  => $first_variation->get_child_category_ids(),
								'orderby'  => 'include',
							)
						);
					}
				}
			}

			// Get variation-specific overrides only.
			foreach ( $variation_ids as $variation_id ) {
				$variation = \wc_get_product( $variation_id );
				if ( $variation ) {
					$item_data['variations'][] = self::prepare_variation_overrides( $variation );
				}
			}
		}

		return $item_data;
	}

	/**
	 * Prepare base child items data (static data without quantities).
	 * 
	 * This data is the same across all variations:
	 * - images, names, descriptions, permalinks, category_ids, etc.
	 *
	 * @param WC_Product $variation A variation product to get child items from.
	 * @return array Base child items without variation-specific quantities.
	 */
	private static function prepare_base_child_items( $variation ) {
		$child_items    = $variation->get_child_items();
		$response_items = array();

		// Check if we're preloading for the single product page render.
		// In that context, we need images. Otherwise, exclude them to reduce payload.
		$is_preloading_for_render = \WC_MNM_Helpers::cache_get( 'wcMNMVariablePreloadingForRender' );

		/**
		 * Filter to exclude images from the Store API response.
		 * 
		 * When true, images will not be included in the response, reducing payload size.
		 * Images are included by default only when preloading for the single product page render.
		 *
		 * @since 2.4.0
		 *
		 * @param bool $exclude_images Whether to exclude images. Default true (excluded) except during single product page preloading.
		 * @param WC_Product $variation The variation product.
		 */
		$exclude_images = apply_filters( 'wc_mnm_variable_store_api_exclude_images', ! $is_preloading_for_render, $variation );

		foreach ( $child_items as $child_item ) {
			$item_data = array(
				'child_item_id'      => $child_item->get_child_item_id(),
				'child_id'           => $child_item->get_the_id(),
				'product_id'         => $child_item->get_product_id(),
				'variation_id'       => $child_item->get_variation_id(),
				// Static data that doesn't change between variations.
				'availability'       => $child_item->get_availability(),
				'purchasable'        => $child_item->get_product()->is_purchasable(),
				'in_stock'           => $child_item->get_product()->is_in_stock(),
				'stock_status'       => $child_item->get_product()->get_stock_status(),
				'price_html'         => $child_item->get_product()->get_price_html(),
				'catalog_visibility' => $child_item->get_product()->get_catalog_visibility(),
				'name'               => $child_item->get_product()->get_name(),
				'permalink'          => $child_item->get_product()->get_permalink(),
				'short_description'  => $child_item->get_product()->get_short_description(),
				'category_ids'       => $child_item->get_product()->get_category_ids(),
			);

			// Only include images if not excluded.
			if ( ! $exclude_images ) {
				$item_data['images'] = self::get_child_item_images( $child_item, $variation );
			}

			$response_items[] = $item_data;
		}

		return $response_items;
	}

	/**
	 * Get the images for a child item's product.
	 *
	 * @since 2.4.0
	 *
	 * @param WC_MNM_Child_Item $child_item The child item.
	 * @param WC_Product $container_product The container product.
	 * @return array
	 */
	private static function get_child_item_images( $child_item, $container_product ) {
		$product = $child_item->get_product();

		/**
		 * Child item image sizes.
		 *
		 * @since 2.4.0
		 *
		 * @param string[] $sizes Image sizes.
		 * @param WC_MNM_Child_Item $child_item Child item.
		 * @param WC_Product $container_product Container product.
		 */
		$image_sizes = (array) apply_filters( 'wc_mnm_variable_api_child_item_thumbnail_sizes', array( 'woocommerce_thumbnail' ), $child_item, $container_product );

		$images         = array();
		$attachment_ids = array();

		// Add featured image.
		if ( $product->get_image_id() ) {
			$attachment_ids[] = $product->get_image_id();
		}

		// Build image data.
		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_post = get_post( $attachment_id );
			if ( is_null( $attachment_post ) ) {
				continue;
			}

			foreach ( $image_sizes as $image_size ) {
				$attachment = wp_get_attachment_image_src( $attachment_id, $image_size );
				if ( ! is_array( $attachment ) ) {
					continue;
				}

				$images[] = array(
					'id'   => (int) $attachment_id,
					'size' => $image_size,
					'src'  => current( $attachment ),
					'name' => get_the_title( $attachment_id ),
					'alt'  => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				);
			}
		}

		// Set a placeholder image if the product has no images set.
		if ( empty( $images ) ) {
			$images[] = array(
				'id'   => 0,
				'src'  => wc_placeholder_img_src(),
				'name' => __( 'Placeholder', 'wc-mnm-variable' ),
				'alt'  => __( 'Placeholder', 'wc-mnm-variable' ),
			);
		}

		return $images;
	}

	/**
	 * Prepare variation-specific overrides.
	 * 
	 * Only includes data that can differ between variations:
	 * - min/max container sizes
	 * - child item quantity constraints (min_qty, max_qty, step_qty)
	 *
	 * @param WC_Product $variation The variation product.
	 * @return array Variation-specific data.
	 */
	private static function prepare_variation_overrides( $variation ) {
		$overrides = array(
			'id'                 => $variation->get_id(),
			'parent'             => $variation->get_parent_id(),
			'type'               => $variation->get_type(),
			'is_purchasable'     => $variation->is_purchasable(),
			'is_in_stock'        => $variation->is_in_stock(),
			'min_container_size' => $variation->get_min_container_size(),
			'max_container_size' => $variation->get_max_container_size(),
			'priced_per_product' => $variation->is_priced_per_product(),
			'discount'           => $variation->get_discount(),
			// Child item quantity overrides keyed by child_id.
			'child_item_overrides' => array(),
		);

		$child_items = $variation->get_child_items();

		foreach ( $child_items as $child_item ) {
			$overrides['child_item_overrides'][ $child_item->get_the_id() ] = array(
				'min_qty'  => $child_item->get_quantity( 'min' ),
				'max_qty'  => $child_item->get_quantity( 'max' ),
				'step_qty' => $child_item->get_quantity( 'step' ),
				'qty'      => $child_item->get_quantity(),
			);
		}

		return $overrides;
	}

	/**
	 * Register variations schema into product endpoint.
	 * 
	 * The new optimized schema includes:
	 * - base_child_items: Static child item data shared across all variations
	 * - variations: Variation-specific overrides only (min/max/step quantities)
	 *
	 * @return array Registered schema.
	 */
	public static function extend_product_schema() {
		return array(
			'base_child_items' => array(
				'description' => __( 'Base child items data shared across all variations (static data like images, names, etc.).', 'wc-mnm-variable' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'child_item_id' => array(
							'type'        => 'string',
							'description' => __( 'Unique identifier for child item', 'wc-mnm-variable' ),
						),
						'child_id' => array(
							'type'        => 'integer',
							'description' => __( 'Child ID number', 'wc-mnm-variable' ),
						),
						'product_id' => array(
							'type'        => 'integer',
							'description' => __( 'Product ID number', 'wc-mnm-variable' ),
						),
						'variation_id' => array(
							'type'        => 'integer',
							'description' => __( 'Variation ID (0 if no variation)', 'wc-mnm-variable' ),
						),
						'availability' => array(
							'type'       => 'object',
							'properties' => array(
								'availability' => array(
									'type'        => 'string',
									'description' => __( 'Availability text', 'wc-mnm-variable' ),
								),
								'class' => array(
									'type'        => 'string',
									'description' => __( 'CSS class for availability status', 'wc-mnm-variable' ),
								),
							),
							'description' => __( 'Product availability', 'wc-mnm-variable' ),
						),
						'purchasable' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether item can be purchased', 'wc-mnm-variable' ),
						),
						'in_stock' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether item is in stock', 'wc-mnm-variable' ),
						),
						'stock_status' => array(
							'type'        => 'string',
							'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
							'description' => __( 'Stock status', 'wc-mnm-variable' ),
						),
						'price_html' => array(
							'type'        => 'string',
							'description' => __( 'HTML formatted price', 'wc-mnm-variable' ),
						),
						'catalog_visibility' => array(
							'type'        => 'string',
							'enum'        => array( 'visible', 'catalog', 'search', 'hidden' ),
							'description' => __( 'Catalog visibility setting', 'wc-mnm-variable' ),
						),
						'images' => array(
							'type'        => 'array',
							'description' => __( 'Array of product images (optional, may be excluded for performance)', 'wc-mnm-variable' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id' => array(
										'type'        => 'integer',
										'description' => __( 'Image ID', 'wc-mnm-variable' ),
									),
									'size' => array(
										'type'        => 'string',
										'description' => __( 'Image size identifier', 'wc-mnm-variable' ),
									),
									'src' => array(
										'type'        => 'string',
										'format'      => 'uri',
										'description' => __( 'Image source URL', 'wc-mnm-variable' ),
									),
									'name' => array(
										'type'        => 'string',
										'description' => __( 'Image file name', 'wc-mnm-variable' ),
									),
									'alt' => array(
										'type'        => 'string',
										'description' => __( 'Image alt text', 'wc-mnm-variable' ),
									),
								),
							),
						),
						'name' => array(
							'type'        => 'string',
							'description' => __( 'Product name', 'wc-mnm-variable' ),
						),
						'permalink' => array(
							'type'        => 'string',
							'format'      => 'uri',
							'description' => __( 'Product permalink URL', 'wc-mnm-variable' ),
						),
						'short_description' => array(
							'type'        => 'string',
							'description' => __( 'Short product description', 'wc-mnm-variable' ),
						),
						'category_ids' => array(
							'type'        => 'array',
							'items'       => array(
								'type' => 'integer',
							),
							'description' => __( 'Array of category IDs', 'wc-mnm-variable' ),
						),
					),
				),
			),
			'variations' => array(
				'description' => __( 'Variation-specific overrides (only data that differs between variations).', 'wc-mnm-variable' ),
				'type'        => 'array',
				'context'     => array( 'view' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Variation ID', 'wc-mnm-variable' ),
						),
						'type' => array(
							'type'        => 'string',
							'description' => __( 'Product type', 'wc-mnm-variable' ),
						),
						'min_container_size' => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Minimum number of items in container', 'wc-mnm-variable' ),
						),
						'max_container_size' => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Maximum number of items in container', 'wc-mnm-variable' ),
						),
						'priced_per_product' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether pricing is per individual product', 'wc-mnm-variable' ),
						),
						'discount' => array(
							'type'        => 'number',
							'minimum'     => 0,
							'description' => __( 'Discount amount or percentage', 'wc-mnm-variable' ),
						),
						'child_item_overrides' => array(
							'type'        => 'object',
							'description' => __( 'Child item quantity overrides keyed by child_id', 'wc-mnm-variable' ),
							'additionalProperties' => array(
								'type'       => 'object',
								'properties' => array(
									'min_qty' => array(
										'type'        => 'integer',
										'minimum'     => 0,
										'description' => __( 'Minimum quantity allowed', 'wc-mnm-variable' ),
									),
									'max_qty' => array(
										'type'        => array( 'integer', 'string' ),
										'description' => __( 'Maximum quantity allowed', 'wc-mnm-variable' ),
									),
									'step_qty' => array(
										'type'        => 'integer',
										'minimum'     => 1,
										'description' => __( 'Quantity increment step', 'wc-mnm-variable' ),
									),
									'qty' => array(
										'type'        => array( 'integer', 'string' ),
										'description' => __( 'Current/default quantity', 'wc-mnm-variable' ),
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 *--------------------------------------------------------------------------
	 * Preloading
	 *--------------------------------------------------------------------------
	 */

	/**
	 * Stash product ID for lazy preloading.
	 *
	 * @return object
	 */
	public static function preload_response() {

		global $product;

		$preloads = \WC_MNM_Helpers::cache_get( 'wcMNMVariablePreloads' );

		if ( is_array( $preloads ) ) {
			$preloads[] = $product->get_id();
			\WC_MNM_Helpers::cache_set( 'wcMNMVariablePreloads', $preloads );
		} elseif ( null === $preloads ) {
			$preloads = [ $product->get_id() ];
		}

		\WC_MNM_Helpers::cache_set( 'wcMNMVariablePreloads', $preloads );
	}

	/**
	 * Preload all variations into WC settings.
	 * 
	 * Preloads the new optimized data structure:
	 * - base_child_items: Shared static data for all variations
	 * - child_categories: Shared category data for all variations
	 * - variations: Variation-specific overrides only
	 *
	 * @return void
	 */
	public static function enqueue_asset_data() {

		$preloads = \WC_MNM_Helpers::cache_get( 'wcMNMVariablePreloads' );

		if ( ! empty( $preloads ) && is_array( $preloads ) ) {

			$data = array(
				'base_child_items'  => array(),
				'child_categories'  => array(),
				'variations'        => array(),
			);

			$assets = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Assets\AssetDataRegistry::class );

			// Set flag to indicate we're preloading for rendering (include images).
			\WC_MNM_Helpers::cache_set( 'wcMNMVariablePreloadingForRender', true );

			foreach ( $preloads as $product_id ) {

				$rest_route = '/wc/store/v1/products/' . $product_id;

				$assets->hydrate_api_request( $rest_route );

				$rest_preload_api_requests = rest_preload_api_request( [], $rest_route );

				$variable_data = $rest_preload_api_requests[ $rest_route ]['body']['extensions']->variable_mix_and_match ?? [];

				// Merge base child items (should be the same, but in case of multiple products).
				if ( ! empty( $variable_data['base_child_items'] ) && empty( $data['base_child_items'] ) ) {
					$data['base_child_items'] = $variable_data['base_child_items'];
				}

				// Merge child categories (should be the same, but in case of multiple products).
				if ( ! empty( $variable_data['child_categories'] ) && empty( $data['child_categories'] ) ) {
					$data['child_categories'] = $variable_data['child_categories'];
				}

				// Merge variation overrides.
				if ( ! empty( $variable_data['variations'] ) ) {
					$data['variations'] = array_merge( $data['variations'], $variable_data['variations'] );
				}

			}

			$assets->add( 'wcMNMVariableSettings', $data );

			// Clear the preloading flag.
			\WC_MNM_Helpers::cache_set( 'wcMNMVariablePreloadingForRender', false );

		}
	}
}
WC_MNM_Variable_Store_API::init();
