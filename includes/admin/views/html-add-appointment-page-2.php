<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$GLOBALS['post']    = get_post( $product->get_id() ); #Deposits integration.
$GLOBALS['product'] = $product; #Add-ons integration.
?>
<div class="wrap woocommerce">
	<h2><?php esc_html_e( 'Add New Appointment', 'woocommerce-appointments' ); ?></h2>

	<?php $this->show_errors(); ?>

	<form method="POST" class="wc-appointments-appointment-form-wrap cart">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Appointment Data', 'woocommerce-appointments' ); ?></label>
					</th>
					<td>
						<div class="wc-appointments-appointment-form">
							<div class="wc-appointments-appointment-hook wc-appointments-appointment-hook-before"><?php do_action( 'woocommerce_before_appointment_form_output', 'before', $appointment_form->product ); ?></div>
							<?php $appointment_form->output(); ?>
							<div class="wc-appointments-appointment-hook wc-appointments-appointment-hook-after"><?php do_action( 'woocommerce_after_appointment_form_output', 'after', $appointment_form->product ); ?></div>
							<div class="wc-appointments-appointment-cost"></div>
						</div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">&nbsp;</th>
					<td>
						<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>
						<?php
						// Show quantity only when maximum qty is larger than 1 ... duuuuuuh
						if ( $product->get_qty() > 1 && $product->get_qty_max() > 1 ) {
							woocommerce_quantity_input(
								array(
									'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_qty_min(), $product ),
									'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_qty_max(), $product ),
									'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( $_POST['quantity'] ) : 1,
								)
							);
						}
						?>
						<input type="submit" name="add_appointment_2" class="button-primary wc-appointments-appointment-form-button single_add_to_cart_button disabled" value="<?php esc_html_e( 'Add New Appointment', 'woocommerce-appointments' ); ?>" />
						<input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer_id ); ?>" />
						<input type="hidden" name="appointable_product_id" value="<?php echo esc_attr( $appointable_product_id ); ?>" />
						<input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $appointable_product_id ); ?>" />
						<input type="hidden" name="appointment_order" value="<?php echo esc_attr( $appointment_order ); ?>" />
						<?php wp_nonce_field( 'add_appointment_notification' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</div>
