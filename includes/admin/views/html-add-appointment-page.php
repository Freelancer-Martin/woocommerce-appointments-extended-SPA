<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap woocommerce">
	<h2><?php esc_html_e( 'Add New Appointment', 'woocommerce-appointments' ); ?></h2>

	<p><?php esc_html_e( 'You can add a new appointment for a customer here. Created orders will be marked as pending payment.', 'woocommerce-appointments' ); ?></p>

	<?php $this->show_errors(); ?>

	<form method="POST">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="customer_id"><?php esc_html_e( 'Customer', 'woocommerce-appointments' ); ?></label>
					</th>
					<td>
						<?php if ( version_compare( WC_VERSION, '3.0', '<' ) ) : ?>
							<input type="hidden" name="customer_id" id="customer_id" class="wc-customer-search" style="width: 300px;" data-allow_clear="true" data-placeholder="<?php esc_html_e( 'Guest', 'woocommerce-appointments' ); ?>" />
						<?php else : ?>
							<select name="customer_id" id="customer_id" class="wc-customer-search" data-placeholder="<?php esc_html_e( 'Guest', 'woocommerce-appointments' ); ?>" data-allow_clear="true">
							</select>
						<?php endif; ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="appointable_product_id"><?php esc_html_e( 'Product', 'woocommerce-appointments' ); ?></label>
					</th>
					<td>
						<select id="appointable_product_id" name="appointable_product_id" class="wc-product-search" style="width: 300px;" data-allow_clear="true" data-placeholder="<?php esc_html_e( 'Select an appointable product...', 'woocommerce-appointments' ); ?>" data-action="woocommerce_json_search_appointable_products"></select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="create_order"><?php esc_html_e( 'Order', 'woocommerce-appointments' ); ?></label>
					</th>
					<?php
					wc_enqueue_js( "
						$( '#appointment_order_id' ).filter( ':not(.enhanced)' ).each( function() {
							var select2_args = {
								allowClear:  true,
								placeholder: $( this ).data( 'placeholder' ),
								minimumInputLength: 1,
								escapeMarkup: function( m ) {
									return m;
								},
								ajax: {
									url:         '" . admin_url( 'admin-ajax.php' ) . "',
									dataType:    'json',
									quietMillis: 250,
									data: function( params ) {
										return {
											term:     params.term,
											action:   'wc_appointments_json_search_order',
											security: '" . wp_create_nonce( 'search-appointment-order' ) . "'
										};
									},
									processResults: function( data ) {
										var terms = [];
										if ( data ) {
											$.each( data, function( id, text ) {
												terms.push({
													id: id,
													text: text
												});
											});
										}
										return {
											results: terms
										};
									},
									cache: true
								},
								multiple: false
							};
							$( this ).select2( select2_args ).addClass( 'enhanced' );
						});
						$( function() {
							var order_label_selector = $( '.appointment-order-label-select' );
							$( '.appointment-order-selector' ).change( function() {
								var order_selector = $( this ).val();
								if ( 'existing' === order_selector ) {
									order_label_selector.show();
								} else {
									order_label_selector.hide();
								}
							});
						});
					" );
					?>
					<td>
						<p>
							<label>
								<input type="radio" name="appointment_order" value="new" class="checkbox appointment-order-selector" checked="checked" />
								<?php esc_html_e( 'Create a new order', 'woocommerce-appointments' ); ?>
								<?php echo wc_help_tip( esc_html__( 'Please note - appointment won\'t be active until order is processed/completed.', 'woocommerce-appointments' ) ); // WPCS: XSS ok. ?>
							</label>
						</p>
						<p>
							<label>
								<input type="radio" name="appointment_order" value="existing" class="checkbox appointment-order-selector" />
								<?php esc_html_e( 'Assign to an existing order', 'woocommerce-appointments' ); ?>
								<div class="appointment-order-label-select">
									<select name="appointment_order_id" id="appointment_order_id" data-placeholder="<?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?>" data-allow_clear="true"></select>
								</div>
							</label>
						</p>
						<!--
						<p>
							<label>
								<input type="radio" name="appointment_order" value="" class="checkbox" checked="checked" />
								<?php esc_html_e( 'Don\'t create an order for this appointment.', 'woocommerce-appointments' ); ?>
							</label>
						</p>
						-->
					</td>
				</tr>
				<?php do_action( 'woocommerce_appointments_after_create_appointment_page' ); ?>
				<tr valign="top">
					<th scope="row">&nbsp;</th>
					<td>
						<input type="submit" name="add_appointment" class="button-primary" value="<?php esc_html_e( 'Next', 'woocommerce-appointments' ); ?>" />
						<?php wp_nonce_field( 'add_appointment_notification' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</div>
