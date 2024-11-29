<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

class WC_Appointments_Admin_Staff_Profile {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'add_staff_meta_fields' ), 20 );
		add_action( 'edit_user_profile', array( $this, 'add_staff_meta_fields' ), 20 );

		add_action( 'personal_options_update', array( $this, 'save_staff_meta_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_staff_meta_fields' ) );

		add_action( 'delete_user', array( $this, 'delete_staff' ), 11 );
	}

	/**
	 * Show meta box
	 */
	public function add_staff_meta_fields( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		// Check roles if user is shop staff.
		if ( isset( $user->roles ) && ! in_array( 'shop_staff', (array) $user->roles ) ) {
			return;
		}

		wp_enqueue_script( 'wc_appointments_writepanel_js' );
		?>
		<style type="text/css">
			#minor-publishing-actions, #visibility { display:none }
		</style>
		<h3 id="staff-gcal"><?php esc_html_e( 'Google Calendar Sync', 'woocommerce-appointments' ); ?></h3>
		<table class="form-table">
			<?php
			// Run Gcal class.
			$gcal_integration_class = wc_appointments_gcal();
			$gcal_integration_class->set_user_id( $user->ID );

			// Get gcal data.
			$access_token  = $gcal_integration_class->get_access_token();
			$client_id     = $gcal_integration_class->get_client_id();
			$client_secret = $gcal_integration_class->get_client_secret();
			$get_calendars = $gcal_integration_class->get_calendars();

			// Calendar ID.
			$calendar_id = get_user_meta( $user->ID, 'wc_appointments_gcal_calendar_id', true );
			$calendar_id = $calendar_id ? $calendar_id : '';

			// Two way sync.
			$two_way = get_user_meta( $user->ID, 'wc_appointments_gcal_twoway', true );
			$two_way = 'one_way' !== $two_way ? 'two_way' : 'one_way';
			if ( 'two_way' === $two_way ) {
				$gcal_integration_class->set_twoway( 'two_way' );
			}

			#print '<pre>'; print_r( $get_calendars ); print '</pre>';
			?>
			<tr>
				<th><label><?php esc_html_e( 'Authorization', 'woocommerce-appointments' ); ?></label></th>
				<td>
					<?php if ( ! $access_token && $client_id && $client_secret ) : ?>
						<button type="button" class="button oauth_redirect" data-staff="<?php echo esc_attr( absint( $user->ID ) ); ?>" data-logout="0"><?php esc_html_e( 'Connect with Google', 'woocommerce-appointments' ); ?></button>
					<?php elseif ( $access_token ) : ?>
						<p style="color:green;"><?php esc_html_e( 'Successfully authenticated.', 'woocommerce-appointments' ); ?></p>
						<p class="submit">
							<button type="button" class="button oauth_redirect" data-staff="<?php echo esc_attr( absint( $user->ID ) ); ?>" data-logout="1"><?php esc_html_e( 'Disconnect', 'woocommerce-appointments' ); ?></button>
						</p>
					<?php else : ?>
						<p>
						<?php
						/* translators: 1: link to google calendar sync settings */
						printf( __( 'Please configure <a href="%s">Google Calendar Sync settings</a> first.', 'woocommerce-appointments' ), esc_url( admin_url( 'admin.php?page=wc-settings&tab=appointments&section=gcal' ) ) );
						?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $access_token ) : ?>
				<tr>
					<th><label for="wc_appointments_gcal_calendar_id"><?php esc_html_e( 'Calendar ID', 'woocommerce-appointments' ); ?></label></th>
					<td>
						<?php if ( $get_calendars ) : ?>
							<select id="wc_appointments_gcal_calendar_id" name="wc_appointments_gcal_calendar_id" class="wc-enhanced-select" style="width:25em;">
								<option value=""><?php esc_html_e( 'N/A', 'woocommerce-appointments' ); ?></option>
								<?php
								foreach ( $get_calendars as $cal_id => $cal_name ) {
								?>
									<option value="<?php echo esc_attr( $cal_id ); ?>" <?php selected( $calendar_id, $cal_id ); ?>><?php echo esc_attr( $cal_name ); ?></option>
								<?php
								}
								?>
							</select>
						<?php else : ?>
							<input type="text" class="regular-text" name="wc_appointments_gcal_calendar_id" id="wc_appointments_gcal_calendar_id" value="<?php echo esc_attr( $calendar_id ); ?>">
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Sync Preference', 'woocommerce-appointments' ); ?></th>
					<td>
						<select id="wc_appointments_gcal_twoway" name="wc_appointments_gcal_twoway" class="wc-enhanced-select" style="width:25em;">
							<option value="one_way" <?php selected( $two_way, 'one_way' ); ?>><?php esc_html_e( 'One way - from Store to Google', 'woocommerce-appointments' ); ?></option>
							<option value="two_way" <?php selected( $two_way, 'two_way' ); ?>><?php esc_html_e( 'Two way - between Store and Google', 'woocommerce-appointments' ); ?></option>
						</select>
					</td>
				</tr>
				<?php if ( $calendar_id && 'two_way' === $two_way ) : ?>
					<tr>
						<th><label><?php esc_html_e( 'Last Sync', 'woocommerce-appointments' ); ?></label></th>
						<td>
							<?php
							$last_synced = get_user_meta( $user->ID, 'wc_appointments_gcal_availability_last_synced', true );
							$last_synced = $last_synced ? $last_synced : '';
							if ( $last_synced ) {
								$ls_timestamp = isset( $last_synced[0] ) && $last_synced[0] ? absint( $last_synced[0] ) : absint( current_time( 'timestamp' ) );
								/* translators: 1: date format, 2: time format */
								$ls_message = sprintf( __( '%1$s, %2$s', 'woocommerce-appointments' ), date_i18n( wc_date_format(), $ls_timestamp ), date_i18n( wc_time_format(), $ls_timestamp ) );
							?>
								<p class="last_synced"><?php echo esc_attr( $ls_message ); ?></p>
							<?php } else { ?>
								<p class="last_synced"><?php esc_html_e( 'No synced rules.', 'woocommerce-appointments' ); ?></p>
							<?php } ?>
							<p class="submit">
								<button type="button" class="button manual_sync" data-staff="<?php echo esc_attr( absint( $user->ID ) ); ?>"><?php esc_html_e( 'Sync Manually', 'woocommerce-appointments' ); ?></button>
							</p>
						</td>
					</tr>
				<?php endif; ?>
			<?php endif; ?>
		</table>
		<h3 id="staff-details"><?php esc_html_e( 'Staff details', 'woocommerce-appointments' ); ?></h3>
		<?php
		// Current view.
		$view = isset( $_REQUEST['view'] ) ? $_REQUEST['view'] : 'staff';

		// Get availabilites.
		if ( 'synced' === $view ) {
			$availability_args = array(
				array(
					'key'     => 'kind',
					'compare' => '=',
					'value'   => 'availability#staff',
				),
				array(
					'key'     => 'kind_id',
					'compare' => '=',
					'value'   => $user->ID,
				),
				array(
					'key'     => 'event_id',
					'compare' => '!=',
					'value'   => '',
				),
			);
		} else {
			$availability_args = array(
				array(
					'key'     => 'kind',
					'compare' => '=',
					'value'   => 'availability#staff',
				),
				array(
					'key'     => 'kind_id',
					'compare' => '=',
					'value'   => $user->ID,
				),
				array(
					'key'     => 'event_id',
					'compare' => '==',
					'value'   => '',
				),
			);
		}

		$staff_availabilities = WC_Data_Store::load( 'appointments-availability' )->get_all( $availability_args );
		$show_title           = true;
		?>
		<table class="form-table">
			<tr class="staff-availability">
				<th><label><?php esc_html_e( 'Custom Availability', 'woocommerce-appointments' ); ?></label></th>
				<td>
					<div class="woocommerce">
						<div class="panel-wrap" id="appointments_availability">
							<div class="table_grid">
								<nav class="wca-nav-wrapper">
									<a class="wca-nav<?php echo ( 'staff' === $view ) ? ' wca-nav-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'staff#staff-details' ) ); ?>">
										<?php esc_html_e( 'Staff rules', 'woocommerce-appointments' ); ?>
									</a>
									<a class="wca-nav<?php echo ( 'synced' === $view ) ? ' wca-nav-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'view', 'synced#staff-details' ) ); ?>">
										<?php esc_html_e( 'Synced rules', 'woocommerce-appointments' ); ?>
									</a>
								</nav>
								<table class="widefat">
									<thead>
										<tr>
											<th class="sort" width="1%">&nbsp;</th>
											<th class="range_type"><?php esc_html_e( 'Type', 'woocommerce-appointments' ); ?></th>
											<th class="range_name"><?php esc_html_e( 'Range', 'woocommerce-appointments' ); ?></th>
											<th class="range_name2"></th>
											<th class="range_title"><?php esc_html_e( 'Title', 'woocommerce-appointments' ); ?></th>
											<th class="range_priority"><?php esc_html_e( 'Priority', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'Rules with lower priority numbers will override rules with a higher priority (e.g. 9 overrides 10 ). By using priority numbers you can execute rules in different orders for all three levels: Global, Product and Staff rules.', 'woocommerce-appointments' ) ); ?></th>
											<th class="range_appointable"><?php esc_html_e( 'Available', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'If not available, users won\'t be able to choose slots in this range for their appointment.', 'woocommerce-appointments' ) ); ?></th>
											<th class="remove" width="1%">&nbsp;</th>
										</tr>
									</thead>
									<?php if ( 'synced' !== $view ) : ?>
										<tfoot>
											<tr>
												<th colspan="8">
													<a href="#" class="button add_grid_row" data-row="<?php
														ob_start();
														include 'views/html-appointment-availability-fields.php';
														$html = ob_get_clean();
														echo esc_attr( $html );
													?>"><?php esc_html_e( 'Add Rule', 'woocommerce-appointments' ); ?></a>
													<span class="description"><?php esc_html_e( get_wc_appointment_rules_explanation() ); ?></span>
												</th>
											</tr>
										</tfoot>
									<?php endif; ?>
									<tbody id="availability_rows">
										<?php
										if ( ! empty( $staff_availabilities ) && is_array( $staff_availabilities ) ) {
											foreach ( $staff_availabilities as $availability ) {
												if ( $availability->has_past() ) {
													continue;
												}
												include 'views/html-appointment-availability-fields.php';
											}
										} elseif ( ! $staff_availabilities && 'synced' === $view ) {
											?>
											<tr>
												<td colspan="8">
													<center><?php esc_html_e( 'No synced rules.', 'woocommerce-appointments' ); ?></center>
												</td>
											</tr>
											<?php
										}
										?>
									</tbody>
								</table>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</td>
				<input type="hidden" name="wc_appointment_availability_deleted" value="" class="wc-appointment-availability-deleted" />
			</tr>
			<?php if ( ! class_exists( 'SitePress' ) && ! class_exists( 'woocommerce_wpml' ) && ! class_exists( 'WPML_Element_Translation_Package' ) && apply_filters( 'woocommerce_appointments_staff_product_assignement', true ) ) { ?>
			<tr class="staff-products">
				<th><label><?php esc_html_e( 'Assigned Products', 'woocommerce-appointments' ); ?></label></th>
				<td>
					<div class="woocommerce">
						<div id="appointments_products" class="panel-wrap">
							<div class="table_grid">
								<table class="widefat">
									<thead>
										<tr>
											<th class="sort" width="1%">&nbsp;</th>
											<th class="staff_product"><?php esc_html_e( 'Product', 'woocommerce-appointments' ); ?></th>
											<th class="product_cost"><?php esc_html_e( 'Price', 'woocommerce-appointments' ); ?></th>
											<th class="product_qty"><?php esc_html_e( 'Quantity', 'woocommerce-appointments' ); ?></th>
											<th class="staff_cost"><?php esc_html_e( 'Additional Cost', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'Additional cost for this staff, which will be calculated into overall product cost.', 'woocommerce-appointments' ) ); ?></th>
											<th class="staff_qty"><?php esc_html_e( 'Staff Quantity', 'woocommerce-appointments' ); ?><?php echo wc_help_tip( esc_html__( 'The maximum number of appointments per slot. Overrides product quantity.', 'woocommerce-appointments' ) ); ?></th>
											<th class="remove" width="1%">&nbsp;</th>
										</tr>
									</thead>
									<tbody id="product_rows" class="woocommerce_staff_products">
										<?php
										$user_product_ids         = WC_Data_Store::load( 'product-appointment' )->get_appointable_product_ids_for_staff( $user->ID );
										$user_product_ids_comma   = ! empty( $user_product_ids ) && is_array( $user_product_ids ) ? implode( ',', $user_product_ids ) : '';
										if ( ! empty( $user_product_ids ) && is_array( $user_product_ids ) ) {
											foreach ( $user_product_ids as $user_product_id ) {
												$user_id = $user->ID;
												include 'views/html-appointment-staff-fields.php';
											}
										}
										?>
										<input type="hidden" id="wc_appointments_staff_product_ids" name="wc_appointments_staff_product_ids" value="<?php echo esc_attr( $user_product_ids_comma ); ?>" />
									</tbody>
									<tfoot>
										<tr>
											<th colspan="7">
												<div class="toolbar">
													<button type="button" class="button add_product"><?php esc_html_e( 'Assign Product', 'woocommerce-appointments' ); ?></button>
													<select id="add_product_id" name="add_product_id" class="wc-product-search" style="width: 300px;" data-allow_clear="true" data-placeholder="<?php esc_html_e( 'Select an appointable product...', 'woocommerce-appointments' ); ?>" data-action="woocommerce_json_search_appointable_products"></select>
												</div>
											</th>
										</tr>
									</tfoot>
								</table>
							</div>
							<div class="clear"></div>
						</div>
					</div>
				</td>
			</tr>
			<?php } ?>
		</table>
		<?php
	}

	/**
	 * Save handler
	 */
	public function save_staff_meta_fields( $user_id ) {
		$user_meta = get_userdata( $user_id );

		// Check roles if user is shop staff.
		if ( isset( $user_meta->roles ) && ! in_array( 'shop_staff', (array) $user_meta->roles ) ) {
			return;
		}

		// Delete.
		if ( ! empty( $_POST['wc_appointment_availability_deleted'] ) ) {
			$deleted_ids = array_filter( explode( ',', wc_clean( wp_unslash( $_POST['wc_appointment_availability_deleted'] ) ) ) );

			foreach ( $deleted_ids as $delete_id ) {
				$availability_object = get_wc_appointments_availability( $delete_id );
				$availability_object->delete();
			}
		}

		// Save.
		$types    = isset( $_POST['wc_appointment_availability_type'] ) ? wc_clean( wp_unslash( $_POST['wc_appointment_availability_type'] ) ) : array();
		$row_size = count( $types );

		for ( $i = 0; $i < $row_size; $i ++ ) {
			if ( isset( $_POST['wc_appointment_availability_id'][ $i ] ) ) {
				$current_id = intval( $_POST['wc_appointment_availability_id'][ $i ] );
			} else {
				$current_id = 0;
			}

			$availability = get_wc_appointments_availability( $current_id );
			$availability->set_ordering( $i );
			$availability->set_range_type( $types[ $i ] );
			$availability->set_kind( 'availability#staff' );
			$availability->set_kind_id( $user_id );

			if ( isset( $_POST['wc_appointment_availability_appointable'][ $i ] ) ) {
				$availability->set_appointable( wc_clean( wp_unslash( $_POST['wc_appointment_availability_appointable'][ $i ] ) ) );
			}

			if ( isset( $_POST['wc_appointment_availability_title'][ $i ] ) ) {
				$availability->set_title( sanitize_text_field( wp_unslash( $_POST['wc_appointment_availability_title'][ $i ] ) ) );
			}

			if ( isset( $_POST['wc_appointment_availability_qty'][ $i ] ) ) {
				$availability->set_qty( intval( $_POST['wc_appointment_availability_qty'][ $i ] ) );
			}

			if ( isset( $_POST['wc_appointment_availability_priority'][ $i ] ) ) {
				$availability->set_priority( intval( $_POST['wc_appointment_availability_priority'][ $i ] ) );
			}

			switch ( $availability->get_range_type() ) {
				case 'custom':
					if ( isset( $_POST['wc_appointment_availability_from_date'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) {
						$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_date'][ $i ] ) ) );
						$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) );
					}
					break;
				case 'months':
					if ( isset( $_POST['wc_appointment_availability_from_month'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_month'][ $i ] ) ) {
						$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_month'][ $i ] ) ) );
						$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_month'][ $i ] ) ) );
					}
					break;
				case 'weeks':
					if ( isset( $_POST['wc_appointment_availability_from_week'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_week'][ $i ] ) ) {
						$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_week'][ $i ] ) ) );
						$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_week'][ $i ] ) ) );
					}
					break;
				case 'days':
					if ( isset( $_POST['wc_appointment_availability_from_day_of_week'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_day_of_week'][ $i ] ) ) {
						$availability->set_from_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_day_of_week'][ $i ] ) ) );
						$availability->set_to_range( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_day_of_week'][ $i ] ) ) );
					}
					break;
				case 'rrule':
					// Do nothing rrules are read only for now.
					break;
				case 'time':
				case 'time:1':
				case 'time:2':
				case 'time:3':
				case 'time:4':
				case 'time:5':
				case 'time:6':
				case 'time:7':
					if ( isset( $_POST['wc_appointment_availability_from_time'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) {
						$availability->set_from_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_from_time'][ $i ] ) ) );
						$availability->set_to_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) );
					}
					break;
				case 'time:range':
				case 'custom:daterange':
					if ( isset( $_POST['wc_appointment_availability_from_time'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) {
						$availability->set_from_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_from_time'][ $i ] ) ) );
						$availability->set_to_range( wc_appointment_sanitize_time( wp_unslash( $_POST['wc_appointment_availability_to_time'][ $i ] ) ) );
					}
					if ( isset( $_POST['wc_appointment_availability_from_date'][ $i ] ) && isset( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) {
						$availability->set_from_date( wc_clean( wp_unslash( $_POST['wc_appointment_availability_from_date'][ $i ] ) ) );
						$availability->set_to_date( wc_clean( wp_unslash( $_POST['wc_appointment_availability_to_date'][ $i ] ) ) );
					}
					break;
			}

			$availability->save();
		}

		// Assigned Products.
		$staff_products   = isset( $_POST['staff_product_id'] ) ? $_POST['staff_product_id'] : '';
		$staff_base_costs = isset( $_POST['staff_base_costs'] ) ? $_POST['staff_base_costs'] : '';
		$staff_qtys       = isset( $_POST['staff_qtys'] ) ? $_POST['staff_qtys'] : '';

		if ( $staff_products && ! empty( $staff_products ) ) {
			foreach ( $staff_products as $staff_product_id ) {
				$appointable_product = get_wc_product_appointment( $staff_product_id );
				if ( ! is_wc_appointment_product( $appointable_product ) ) {
					continue;
				}

				// Asssign staff to product.
				$staff_ids = $appointable_product->get_staff_ids();
				if ( ! in_array( $user_id, $staff_ids ) ) {
					$staff_ids[] = $user_id;
				}
				$appointable_product->set_staff_ids( $staff_ids );

				// Add staff base costs to product.
				$product_staff_base_costs             = $appointable_product->get_staff_base_costs();
				$product_staff_base_costs[ $user_id ] = floatval( $staff_base_costs[ $staff_product_id ] );
				$appointable_product->set_staff_base_costs( $product_staff_base_costs );

				// Add staff base costs to product.
				$product_staff_qtys             = $appointable_product->get_staff_qtys();
				$product_staff_qtys[ $user_id ] = intval( $staff_qtys[ $staff_product_id ] );
				$appointable_product->set_staff_qtys( $product_staff_qtys );

				$appointable_product->save();
			}
		}

		// Calendar ID.
		$calendar_id = isset( $_POST['wc_appointments_gcal_calendar_id'] ) ? $_POST['wc_appointments_gcal_calendar_id'] : '';
		update_user_meta( $user_id, 'wc_appointments_gcal_calendar_id', $calendar_id );

		// Two way sync.
		$two_way = isset( $_POST['wc_appointments_gcal_twoway'] ) ? $_POST['wc_appointments_gcal_twoway'] : 'one_way';
		update_user_meta( $user_id, 'wc_appointments_gcal_twoway', $two_way );
	}

	/**
	 * Actions to be done when staff is deleted
	 */
	public function delete_staff( $user_id ) {
		$user_meta = get_userdata( $user_id );

		// Check roles if user is shop staff.
		if ( in_array( 'shop_staff', (array) $user_meta->roles ) ) {
			// Get all staff appointments and remove staff from them.
			$appointments_args = array(
				'status'      => get_wc_appointment_statuses( 'validate' ),
				'object_id'   => absint( $user_id ),
				'object_type' => 'staff',
			);
			$staff_appointments = WC_Appointment_Data_Store::get_appointment_ids_by( $customer_appointments_args );
			if ( ! empty( $staff_appointments ) ) {
				foreach ( $staff_appointments as $staff_appointment ) {
					delete_post_meta( $staff_appointment->id, '_appointment_staff_id' );
				}
			}

			// Get all products that current staff is assigned to and remove him/her from product (revert the relational db table and post meta logic in class-wc-appointments-admin.php on line 559-593)
			$staff_product_ids = WC_Data_Store::load( 'product-appointment' )->get_appointable_product_ids_for_staff( $user_id );
			if ( ! empty( $staff_product_ids ) ) {
				foreach ( $staff_product_ids as $staff_product_id ) {
					WC_Data_Store::load( 'product-appointment' )->remove_staff_from_product( $user_id, $staff_product_id );
				}
			}

		// Check roles if user is shop customer.
		} elseif ( in_array( 'customer', (array) $user_meta->roles ) ) {
			$customer_appointments_args = array(
				'status'      => get_wc_appointment_statuses( 'user' ),
				'object_id'   => absint( $user_id ),
				'object_type' => 'customer',
			);
			$customer_appointments = WC_Appointment_Data_Store::get_appointment_ids_by( $customer_appointments_args );
			if ( ! empty( $customer_appointments ) ) {
				foreach ( $customer_appointments as $customer_appointment ) {
					delete_post_meta( $customer_appointment->id, '_appointment_customer_id' );
				}
			}
		}
	}
}

return new WC_Appointments_Admin_Staff_Profile();
