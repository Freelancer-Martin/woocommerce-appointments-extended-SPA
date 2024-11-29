<?php
/**
 * PLAIN Customer appointment confirmed email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/customer-appointment-confirmed.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.8.0
 * @since       3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

echo '= ' . $email_heading . " =\n\n";

if ( $appointment->get_order() ) {
	/* translators: 1: billing first name */
	echo sprintf( __( 'Hello %s', 'woocommerce-appointments' ), ( is_callable( array( $appointment->get_order(), 'get_billing_first_name' ) ) ? $appointment->get_order()->get_billing_first_name() : $appointment->get_order()->billing_first_name ) ) . "\n\n";
}

echo __( 'Your appointment for has been confirmed. The details of your appointment are shown below.', 'woocommerce-appointments' ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: 1: appointment product title */
echo sprintf( __( 'Scheduled Product: %s', 'woocommerce-appointments' ), $appointment->get_product_name() ) . "\n";
/* translators: 1: appointment ID */
echo sprintf( __( 'Appointment ID: %s', 'woocommerce-appointments' ), $appointment->get_id() ) . "\n";
/* translators: 1: appointment start date */
echo sprintf( __( 'Appointment Date: %s', 'woocommerce-appointments' ), $appointment->get_start_date() ) . "\n";
/* translators: 1: appointment duration */
echo sprintf( __( 'Appointment Duration: %s', 'woocommerce-appointments' ), $appointment->get_duration() ) . "\n";

$staff = $appointment->get_staff_members( true );
if ( $appointment->has_staff() && $staff ) {
	/* translators: 1: appointment staff names */
	echo sprintf( __( 'Appointment Providers: %s', 'woocommerce-appointments' ), $staff ) . "\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

$wc_order = $appointment->get_order();
if ( $wc_order ) {
	if ( 'pending' === $wc_order->get_status() ) {
		/* translators: 1: checkout payment url */
		echo sprintf( __( 'To pay for this appointment please use the following link: %s', 'woocommerce-appointments' ), $wc_order->get_checkout_payment_url() ) . "\n\n";
	}

	do_action( 'woocommerce_email_before_order_table', $wc_order, $sent_to_admin, $plain_text, $email );

	$pre_wc_30 = version_compare( WC_VERSION, '3.0', '<' );

	if ( $pre_wc_30 ) {
		$order_date = $wc_order->order_date;
	} else {
		$order_date = $wc_order->get_date_created() ? $wc_order->get_date_created()->date( 'Y-m-d H:i:s' ) : '';
	}

	/* translators: 1: order number */
	echo sprintf( __( 'Order number: %s', 'woocommerce-appointments'), $wc_order->get_order_number() ) . "\n";
	/* translators: 1: order date */
	echo sprintf( __( 'Order date: %s', 'woocommerce-appointments'), date_i18n( wc_date_format(), strtotime( $order_date ) ) ) . "\n";

	do_action( 'woocommerce_email_order_meta', $wc_order, $sent_to_admin, $plain_text, $email );

	echo "\n";

	switch ( $wc_order->get_status() ) {
		case 'completed':
			echo $pre_wc_30 ? $wc_order->email_order_items_table(
				array(
					'show_sku'   => false,
					'plain_text' => true,
				) ) : wc_get_email_order_items(
				$wc_order,
				array(
					'show_sku'   => false,
					'plain_text' => true,
				) );
			break;
		case 'processing':
		default:
			echo $pre_wc_30 ? $wc_order->email_order_items_table(
				array(
					'show_sku'   => true,
					'plain_text' => true,
				) ) : wc_get_email_order_items(
				$wc_order,
				array(
					'show_sku'   => true,
					'plain_text' => true,
				) );
			break;
	}

	echo "==========\n\n";

	$order_totals = $wc_order->get_order_item_totals();
	if ( $order_totals ) {
		foreach ( $order_totals as $order_total ) {
			echo $order_total['label'] . "\t " . $order_total['value'] . "\n";
		}
	}

	echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

	do_action( 'woocommerce_email_after_order_table', $wc_order, $sent_to_admin, $plain_text, $email );
}

/**
 * Show user-defined additonal content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
