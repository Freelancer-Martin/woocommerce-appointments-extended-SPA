<?php
/**
 * The template for displaying an appointment summary to customers.
 * It will display in three places:
 * - After checkout,
 * - In the order confirmation email, and
 * - When customer reviews order in My Account > Orders.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/appointment-display.php.
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
			<div class="wc-appointment-summary-name" style="white-space: nowrap; padding: 0;">
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
		</div>
		<?php
	}
}
