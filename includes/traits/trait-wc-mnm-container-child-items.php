<?php
/**
 * This ongoing trait will have shared logic between WC_Product_Mix_and_Match and WC_Product_Mix_and_Match_Variation classes.
 *
 * @package WooCommerce Mix and Match Products\Traits
 * @since 1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait WC_MNM_Container_Child_Items.
 */
trait WC_MNM_Container_Child_Items {

	/**
	 * Array of child item objects.
	 *
	 * @var null|WC_MNM_Child_Item[]
	 */
	private $child_items = null;

	/**
	 * Child items that need deleting are stored here.
	 *
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
	 *  Define type-specific properties.
	 *
	 * @var array
	 */
	protected $contents_props = array(
		'content_source'           => 'products',
		'child_category_ids'       => array(),
		'child_items_stock_status' => 'outofstock', // 'instock' | 'onbackorder' | 'outofstock' - This prop is not saved as meta.
	);

	/**
	 * Load property and runtime cache defaults to trigger a re-sync.
	 */
	public function load_defaults( $reset_child_items = false ) {

		$this->is_synced             = false;
		$this->container_price_data  = array();
		$this->container_price_cache = array();

		if ( $reset_child_items ) {
			$this->child_items = null;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Getters.
	|--------------------------------------------------------------------------
	*/

	/**
	 * Child items content source getter.
	 *
	 * @param  string $context
	 * @return string
	 */
	public function get_content_source( $context = 'view' ) {
		return $this->get_prop( 'content_source', $context );
	}

	/**
	 * Category contents getter.
	 *
	 * @param  string $context
	 * @return array
	 */
	public function get_child_category_ids( $context = 'view' ) {
		return $this->get_prop( 'child_category_ids', $context );
	}

	/**
	 * Return array of allowed child product IDs
	 *
	 * @return array[] array of child item ID => product|variation ID
	 */
	public function get_child_product_ids( $context = 'view' ) {

		$child_product_ids = WC_MNM_Helpers::cache_get( $this->get_id(), 'child_product_ids' );

		if ( null === $child_product_ids ) {

			$child_product_ids = array();

			foreach ( $this->get_child_items( $context ) as $item_key => $child_item ) {
				$child_product_ids[ $child_item->get_child_item_id() ] = $child_item->get_variation_id() ? $child_item->get_variation_id() : $child_item->get_product_id();
			}

			WC_Mix_and_Match_Helpers::cache_set( $this->get_id(), $child_product_ids, 'child_product_ids' );

		}

		/**
		 * 'wc_mnm_child_product_ids' filter.
		 *
		 * @param  array                     $child_product_ids
		 * @param  WC_Product_Mix_and_Match  $this
		 */
		return 'view' === $context ? apply_filters( 'wc_mnm_child_product_ids', $child_product_ids, $this ) : $child_product_ids;
	}

	/**
	 * Return all child items
	 * these are the items that are allowed to be in the container
	 *
	 * @return WC_MNM_Child_Item[]
	 */
	public function get_child_items( $context = 'view' ) {

		if ( $this->get_id() && ! $this->has_child_item_changes() ) {
			$this->child_items = WC_MNM_Helpers::cache_get( $this->get_id(), 'child_items' );
		}

		if ( null === $this->child_items ) {

			$this->child_items = [];

			$child_items = $this->data_store->read_child_items( $this );

			// Sanity check that the products do exist.
			foreach ( $child_items as $item_key => $child_item ) {

				if ( $child_item && $child_item->exists() ) {

					if ( ! $child_item->is_visible() ) {
						continue;
					}

					$this->child_items[ $item_key ] = $child_item;

				}
			}

			WC_Mix_and_Match_Helpers::cache_set( $this->get_id(), $this->child_items, 'child_items' );

		}

		/**
		 * 'wc_mnm_child_items' filter.
		 *
		 * @param  WC_MNM_Child_Item[]       $child_items
		 * @param  WC_Product_Mix_and_Match  $this
		 */
		return 'view' === $context ? apply_filters( 'wc_mnm_child_items', $this->child_items, $this ) : $this->child_items;
	}


	/**
	 * Gets a specific child item.
	 *
	 * @param  int  $child_item_id
	 * @param  string $context
	 * @return false|WC_MNM_Child_Item
	 */
	public function get_child_item( $child_item_id, $context = 'view' ) {
		$child_items = $this->get_child_items( $context );
		return ! empty( $child_items ) && array_key_exists( $child_item_id, $child_items ) ? $child_items[ $child_item_id ] : false;
	}


	/**
	 * Return a specific child item by product|variation ID.
	 *
	 * @return WC_MNM_Child_Item|false
	 */
	public function get_child_item_by_product_id( $child_product_id, $context = 'view' ) {

		$child_items_by_product = WC_MNM_Helpers::cache_get( $this->get_id(), 'child_items_by_product' );

		if ( null === $child_items_by_product ) {

			$child_items_by_product = array();

			foreach ( $this->get_child_items( $context ) as $child_item ) {
				$child_items_by_product[ $child_item->get_variation_id() ? $child_item->get_variation_id() : $child_item->get_product_id() ] = $child_item;
			}

			WC_Mix_and_Match_Helpers::cache_set( $this->get_id(), $child_items_by_product, 'child_items_by_product' );

		}

		return ! empty( $child_items_by_product ) && array_key_exists( $child_product_id, $child_items_by_product ) ? $child_items_by_product[ $child_product_id ] : false;
	}


	/*
	|--------------------------------------------------------------------------
	| Setters.
	|--------------------------------------------------------------------------
	*/


	/**
	 * Child items content source setter.
	 *
	 * @param  string $value - 'products' | 'categories'
	 */
	public function set_content_source( $value ) {
		return $this->set_prop( 'content_source', in_array( $value, array( 'products', 'categories' ) ) ? $value : 'products' );
	}


	/**
	 * Category contents setter.
	 *
	 * @param  int[] $value
	 */
	public function set_child_category_ids( $value ) {
		$this->set_prop( 'child_category_ids', is_array( $value ) ? array_filter( array_unique( array_map( 'intval', $value ) ), 'term_exists' ) : [] );
	}


	/**
	 * Child items/product IDs setter.
	 *
	 * @param  mixed WC_MNM_Child_Item[] | array[]  $data {
	 *     @type  int  $product_id     Child product id.
	 *     @type  int  $variation_id   Child variation id.
	 * }
	 */
	public function set_child_items( array $data ) {

		// Reindex the existing items by product|variation ID, for easier comparison.
		$current_items = array();
		foreach ( $this->get_child_items( 'edit' ) as $child_item ) {
			$current_items[ $child_item->get_variation_id() ? $child_item->get_variation_id() : $child_item->get_product_id() ] = $child_item;
		}

		$incoming_ids = array();
		$new_items    = array();

		// Step 1 - Set all new/updated child items.
		foreach ( $data as $data_item ) {
			if ( $data_item instanceof WC_MNM_Child_Item ) {
				$new_item = $data_item;
				$new_item->set_container_id( $this->get_id() );
				$incoming_id = $data_item->get_variation_id() ? $data_item->get_variation_id() : $data_item->get_product_id();
			} else {
				$props                 = wp_parse_args(
					(array) $data_item,
					array(
						'product_id'   => 0,
						'variation_id' => 0,
					)
				);
				$props['container_id'] = $this->get_id();
				$new_item              = new WC_MNM_Child_Item( $props, $this );
				$incoming_id           = $props['variation_id'] ? $props['variation_id'] : $props['product_id'];
			}

			$incoming_ids[] = $incoming_id; // Store for later comparison.

			// An existing item.
			if ( isset( $current_items[ $incoming_id ] ) ) {
				$new_items[] = $current_items[ $incoming_id ];
			} else {
				$new_items[] = $new_item;
			}
		}

		$this->child_items         = $new_items;
		$this->child_items_changed = true;
		$this->load_defaults();

		// Step 2 - Queue any items to delete.
		foreach ( array_diff( array_keys( $current_items ), $incoming_ids ) as $product_id_to_delete ) {
			$this->child_items_to_delete[] = $current_items[ $product_id_to_delete ];
		}
	}


	/*
	|--------------------------------------------------------------------------
	| Conditionals
	|--------------------------------------------------------------------------
	*/

	/**
	 * Equivalent of 'get_changes', but boolean and for child items only.
	 *
	 * @return boolean
	 */
	public function has_child_item_changes() {
		return $this->child_items_changed;
	}

	/**
	 * Returns whether or not the product container has any visible child items.
	 *
	 * @param string $context
	 * @return bool
	 */
	public function has_child_items( $context = 'view' ) {
		return sizeof( $this->get_child_items( $context ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Save child items.
	--------------------------------------------------------------------------
	*/

	/**
	 * Do any extra processing needed after the actual product save
	 * (but before triggering the 'woocommerce_after_..._object_save' action)
	 *
	 * @param mixed $state The state object that was returned by before_data_store_save_or_update.
	 */
	protected function after_data_store_save_or_update( $state ) {
		parent::after_data_store_save_or_update( $state );

		if ( $this->has_child_item_changes() ) {
			$this->save_child_items();
		}
	}

	/**
	 * Save all child items which are part of this product.
	 */
	protected function save_child_items() {

		wc_transaction_query();

		try {

			// Delete items in the delete queue.
			foreach ( $this->child_items_to_delete as $child_item ) {
				$child_item->delete();
			}
			$this->child_items_to_delete = array();

			// Add/save items.
			if ( is_array( $this->child_items ) ) {
				$menu_order  = 0;
				$child_items = array_filter( $this->child_items );
				foreach ( $child_items as $item_key => $child_item ) {

					$child_item->set_container_id( $this->get_id() );
					$child_item->set_menu_order( $menu_order );

					$child_item_id = $child_item->save();

					// If ID changed (new item saved to DB)...
					if ( $child_item_id !== $child_item_id ) {
						$this->child_items[ $child_item_id ] = $child_item;
						unset( $this->child_items[ $item_key ] );
					}

					++$menu_order;
				}
			}

			// Commit all the changes
			wc_transaction_query( 'commit' );

			$this->load_defaults();

			WC_MNM_Helpers::cache_delete( $this->get_id(), 'child_items' );

		} catch ( Exception $e ) {
			wc_get_logger()->error(
				esc_html__( 'Error saving Mix and Match product child items.', 'wc-mnm-variable' ),
				array(
					'source'  => 'wc-mix-and-match-product-save',
					'product' => $this,
					'error'   => $e,
				)
			);
			wc_transaction_query( 'rollback' );
		}
	}
}
