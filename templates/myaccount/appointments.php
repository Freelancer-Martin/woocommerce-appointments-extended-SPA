<?php
/**
 * My Appointments
 *
 * Shows customer appointments on the My Account > Appointments page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/appointments.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     4.8.1
 * @since       3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$count = 0;

if ( ! empty( $tables ) ) : ?>

	<?php foreach ( $tables as $table_id => $table ) : ?>

		<h3><?php echo esc_html( $table['header'] ); ?></h3>

		<table class="shop_table shop_table_responsive my_account_appointments <?php echo esc_html( $table_id ) . '_appointments'; ?>">
			<thead>
				<tr>
					<th scope="col" class="appointment-id"><span class="nobr"><?php esc_html_e( 'Appointment', 'woocommerce-appointments' ); ?></span></th>
					<th scope="col" class="appointment-when"><span class="nobr"><?php esc_html_e( 'When', 'woocommerce-appointments' ); ?></span></th>
					<th scope="col" class="scheduled-product"><span class="nobr"><?php esc_html_e( 'Scheduled', 'woocommerce-appointments' ); ?></span></th>
					<th scope="col" class="appointment-status"><span class="nobr"><?php esc_html_e( 'Status', 'woocommerce-appointments' ); ?></span></th>
					<th scope="col" class="appointment-actions"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $table['appointments'] as $appointment ) : ?>
					<?php $count++; ?>
					<tr>
						<td class="appointment-id anowrap" data-title="<?php esc_html_e( 'Appointment', 'woocommerce-appointments' ); ?>">
							<?php
							printf(
								/* translators: 1: Appointment ID */
								esc_html__( 'ID #%d', 'woocommerce-appointments' ),
								esc_attr( $appointment->get_id() )
							);
							if ( $appointment->get_order() ) :
								if ( 'pending-confirmation' === $appointment->get_status() ) :
									printf(
										/* translators: 1: Order number */
										esc_html__( 'Order', 'woocommerce-appointments' ),
										esc_attr( $appointment->get_order()->get_order_number() )
									);
								else :
									printf(
										/* translators: 1: Order number */
										'<a href="%s" class="adesc">' . esc_html__( 'Order', 'woocommerce-appointments' ) . '</a>',
										esc_url( $appointment->get_order()->get_view_order_url() ),
										esc_attr( $appointment->get_order()->get_order_number() )
									);
								endif;
							endif;
							?>
						</td>
						<td class="appointment-when anowrap" data-title="<?php esc_html_e( 'When', 'woocommerce-appointments' ); ?>">
							<?php esc_attr_e( $appointment->get_start_date() ); ?>
							<span class="adesc"><?php esc_attr_e( $appointment->get_duration() ); ?></span>
						</td>
						<td class="scheduled-product" data-title="<?php esc_html_e( 'Scheduled', 'woocommerce-appointments' ); ?>">
							<?php if ( $appointment->get_product() && $appointment->get_product()->is_type( 'appointment' ) ) : ?>
							<a href="<?php echo esc_url( get_permalink( $appointment->get_product_id() ) ); ?>">
								<?php echo esc_html( $appointment->get_product_name() ); ?>
							</a>
							<?php endif; ?>
						</td>
						<td class="appointment-status" data-title="<?php esc_html_e( 'Status', 'woocommerce-appointments' ); ?>">
							<?php echo esc_html( wc_appointments_get_status_label( $appointment->get_status() ) ); ?>
						</td>
						<td class="appointment-actions">
							<?php if ( 'cancelled' !== $appointment->get_status() && 'completed' !== $appointment->get_status() && ! $appointment->passed_cancel_day() ) : ?>
							<a href="<?php echo esc_url( $appointment->get_cancel_url() ); ?>" class="button cancel"><?php esc_html_e( 'Cancel', 'woocommerce-appointments' ); ?></a>
							<?php endif ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php do_action( 'woocommerce_before_account_appointments_pagination' ); ?>

		<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
			<?php if ( 1 !== $page ) : ?>
				<a href="<?php echo esc_url( wc_get_endpoint_url( $endpoint, $page - 1 ) ); ?>" class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button"><?php esc_html_e( 'Previous', 'woocommerce-appointments' ); ?></a>
			<?php endif; ?>

			<?php if ( $count >= $appointments_per_page ) : ?>
				<a href="<?php echo esc_url( wc_get_endpoint_url( $endpoint, $page + 1 ) ); ?>" class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button"><?php esc_html_e( 'Next', 'woocommerce-appointments' ); ?></a>
			<?php endif; ?>
		</div>

		<?php do_action( 'woocommerce_after_account_appointments_pagination' ); ?>

	<?php endforeach; ?>

<?php else : ?>
	<div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
		<a class="woocommerce-Button button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">
			<?php esc_html_e( 'Book', 'woocommerce-appointments' ); ?>
		</a>
		<?php esc_html_e( 'No appointments scheduled yet.', 'woocommerce-appointments' ); ?>
	</div>
<?php endif; ?>
