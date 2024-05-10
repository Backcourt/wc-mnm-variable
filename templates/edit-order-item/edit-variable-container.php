<?php
/**
 * Mix and Match Product Edit Variable Container
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/edit-order-item/edit-variable-container.php.
 *
 * HOWEVER, on occasion WooCommerce Mix and Match will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce Mix and Match/Templates
 * @since   1.0.0
 * @version 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;

$attribute_keys  = array_keys( $attributes );

/**
 * wc_mnm_before_edit_container_form hook.
 */
do_action( 'wc_mnm_before_edit_container_order_item_form', $product, $order_item, $order, $source );
?>
<form
    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
    data-product_type="<?php echo esc_attr( $product->get_type() ); ?>"
    action="<?php echo esc_url( apply_filters( 'wc_mnm_edit_container_order_item_form_action', '' ) ); ?>"
    method="post"
    enctype="multipart/form-data"
	data-container_config="<?php echo wc_esc_json( wp_json_encode( $configuration ) ); ?>"
	data-container_id="<?php echo esc_attr( $order_item->get_variation_id() ? $order_item->get_variation_id() : $order_item->get_product_id() ); ?>"
    data-product_id="<?php echo absint( $product->get_id() ); ?>"
    data-product_variations="<?php echo wc_esc_json( wp_json_encode( $available_variations ) ); ?>"
	data-source="<?php echo esc_attr( $source ); ?>"
	data-validation_context="<?php echo esc_attr( $context ); ?>"
>
	<?php do_action( 'wc_mnm_edit_container_order_item_before_variations_form', $product, $order_item, $order, $source ); ?>

	<?php if ( empty( $available_variations ) && false !== $available_variations ) : ?>
		<p class="stock out-of-stock"><?php echo esc_html( apply_filters( 'woocommerce_out_of_stock_message', __( 'This product is currently out of stock and unavailable.', 'wc-mnm-variable' ) ) ); ?></p>
	<?php else : ?>
		<?php if ( count( $attributes ) === 1 && ! empty( $available_variations ) && count( $available_variations ) <= apply_filters( 'wc_mnm_variation_swatches_threshold', 3, $product ) ) : ?>

			<?php
			// Working with a single attribute here.
			$attribute = key( $attributes );
			
			wc_mnm_template_variation_attribute_options(
				array(
					'attribute' => $attribute,
					'product'   => $product,
				)				
			);

			?>

		<?php else: ?>
			<table class="variations" cellspacing="0" role="presentation">
				<tbody>
					<?php foreach ( $attributes as $attribute_name => $options ) : ?>
						<tr>
							<th class="label"><label for="<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>"><?php echo wc_attribute_label( $attribute_name ); // WPCS: XSS ok. ?></label></th>
							<td class="value">
								<?php
									wc_dropdown_variation_attribute_options(
										array(
											'options'   => $options,
											'attribute' => $attribute_name,
											'product'   => $product,
										)
									);
									echo end( $attribute_keys ) === $attribute_name ? wp_kses_post( apply_filters( 'woocommerce_reset_variations_link', '<a class="reset_variations" href="#">' . esc_html__( 'Clear', 'wc-mnm-variable' ) . '</a>' ) ) : '';
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			
			<?php do_action( 'wc_mnm_edit_container_order_item_after_variations_table', $product, $order_item, $order, $source ); ?>

		<?php endif; ?>


	<?php endif; ?>

	<div class="single_variation_wrap">
	<?php
		/**
		 * Hook: wc_mnm_edit_container_order_item_before_single_variation.
		 */
		do_action( 'wc_mnm_edit_container_order_item_before_single_variation', $product, $order_item, $order, $source );

		/**
		 * Hook: wc_mnm_edit_container_order_item_single_variation. Used to output the cart button and placeholder for variation data.
		 *
		 * @hooked woocommerce_single_variation - 10 Empty div for variation data.
		 * @hooked wc_mnm_template_edit_single_variation - 20 Empty div for mnm variation data.
		 */
		do_action( 'wc_mnm_edit_container_order_item_single_variation', $product, $order_item, $order, $source );

		/**
		 * Hook: wc_mnm_edit_container_order_item_after_single_variation.
		 */
		do_action( 'wc_mnm_edit_container_order_item_after_single_variation', $product, $order_item, $order, $source );

	?>
	</div>

	<?php do_action( 'wc_mnm_edit_container_order_item_after_variations_form', $product, $order_item, $order, $source ); ?>

</form>

<?php
/**
 * wc_mnm_after_edit_container_order_item_form hook.
 */
do_action( 'wc_mnm_after_edit_container_order_item_form', $product, $order_item, $order, $source );
?>
