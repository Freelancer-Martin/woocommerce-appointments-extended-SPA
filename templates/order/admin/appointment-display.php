<?php
/**
 * The template for displaying an appointment summary in the admin.
 * It will display in one place:
 * - When reviewing a customer order.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/admin/appointment-display.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @version 4.8.1
 * @since   3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( $appointment_ids ) {
	foreach ( $appointment_ids as $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );
		?>
		<div class="wc-appointment-summary" style="margin-top: 10px; margin-bottom: 10px;">
			<div class="wc-appointment-summary-name" style="white-space: nowrap;">
				<strong<?php if ( isset( $is_rtl ) && 'right' === $is_rtl ) : ?> dir="rtl"<?php endif; ?>>
					<?php
					/* translators: 1: appointment ID */
					echo esc_html( sprintf( __( 'Appointment #%s', 'woocommerce-appointments' ), (string) $appointment->get_id() ) );
					?>
				</strong>
				&mdash;
				<small class="status-<?php echo esc_attr( $appointment->get_status() ); ?>">
					<?php echo esc_attr( wc_appointments_get_status_label( $appointment->get_status() ) ); ?>
				</small>
			</div>
			<?php wc_appointments_get_summary_list( $appointment ); ?>
			<div class="wc-appointment-summary-actions">
				<?php if ( $appointment->has_status( array( 'pending-confirmation' ) ) ) : ?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-ajax.php?action=wc-appointment-confirm&appointment_id=' . $appointment_id ), 'wc-appointment-confirm' ) ); ?>"><?php esc_html_e( 'Confirm appointment', 'woocommerce-appointments' ); ?></a>
				<?php endif; ?>
				<?php if ( $appointment_id ) : ?>
					<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $appointment_id ) . '&action=edit' ) ); ?>"><?php esc_html_e( 'Edit Appointment', 'woocommerce-appointments' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
