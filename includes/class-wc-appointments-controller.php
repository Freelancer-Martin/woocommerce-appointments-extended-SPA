<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Gets appointments
 */
class WC_Appointments_Controller {

	/**
	 * Return an array of un-appointable padding days
	 * @since 2.0.0
	 *
	 * @param  WC_Product_Appointment|int $appointable_product
	 * @return array Days that are padding days and therefor should be un-appointable
	 */
	public static function find_padding_day_slots( $appointable_product ) {
		if ( is_int( $appointable_product ) ) {
			$appointable_product = wc_get_product( $appointable_product );
		}
		if ( ! is_wc_appointment_product( $appointable_product ) ) {
			return array();
		}

		$scheduled = self::find_scheduled_day_slots( $appointable_product );

		return self::get_padding_day_slots_for_scheduled_days( $appointable_product, $scheduled['fully_scheduled_days'] );
	}

	/**
	 * Return an array of un-appointable padding days
	 * @since 3.3.0
	 *
	 * @param  WC_Product_Appointment|int $appointable_product
	 * @return array Days that are padding days and therefor should be un-appointable
	 */
	public static function get_padding_day_slots_for_scheduled_days( $appointable_product, $fully_scheduled_days ) {
		if ( is_int( $appointable_product ) ) {
			$appointable_product = wc_get_product( $appointable_product );
		}
		if ( ! is_wc_appointment_product( $appointable_product ) ) {
			return array();
		}

		$padding_duration = $appointable_product->get_padding_duration();
		$padding_days     = array();

		foreach ( $fully_scheduled_days as $date => $data ) {
			$next_day = strtotime( '+1 day', strtotime( $date ) );

			if ( array_key_exists( date( 'Y-n-j', $next_day ), $fully_scheduled_days ) ) {
				continue;
			}

			// x days after
			for ( $i = 1; $i < $padding_duration + 1; $i++ ) {
				$padding_day                  = date( 'Y-n-j', strtotime( "+{$i} day", strtotime( $date ) ) );
				$padding_days[ $padding_day ] = $padding_day;
			}
		}

		#if ( $appointable_product->get_apply_adjacent_padding() ) {
			foreach ( $fully_scheduled_days as $date => $data ) {
				$previous_day = strtotime( '-1 day', strtotime( $date ) );

				if ( array_key_exists( date( 'Y-n-j', $previous_day ), $fully_scheduled_days ) ) {
					continue;
				}

				// x days before
				for ( $i = 1; $i < $padding_duration + 1; $i++ ) {
					$padding_day                  = date( 'Y-n-j', strtotime( "-{$i} day", strtotime( $date ) ) );
					$padding_days[ $padding_day ] = $padding_day;
				}
			}
		#}
		return $padding_days;
	}

	/**
	 * For hour appointments types check that the appointment is past midnight and before start time.
	 * This only works for the very next day after appointment start.
	 *
	 * @since 1.10.7
	 *
	 * @param WC_Appointment $appointment
	 * @param WC_Bookable_Product $product
	 * @param string $check_date
	 * @return boolean;
	 */
	private static function is_appointment_past_midnight_and_before_start_time( $appointment, $product, $check_date ) {
		// This handles appointments overlapping midnight when slots only start
		// from a specific hour.
		return (
			'hour' === $product->get_duration_unit()
			&& date( 'md', $appointment['end'] ) === ( date( 'md', $check_date ) )
		);
	}

	/**
	 * Finds months which are fully scheduled.
	 *
	 * @param  WC_Product_Appointment|int $appointable_product
	 *
	 * @return array( 'fully_scheduled_months' )
	 */
	public static function find_scheduled_month_slots( $appointable_product ) {
		$scheduled_day_slots = self::find_scheduled_day_slots( $appointable_product, 0, 0, 'Y-n' );

		$scheduled_month_slots = array(
			'fully_scheduled_months' => $scheduled_day_slots['fully_scheduled_days'],
		);

		/**
		 * Filter the scheduled month slots calculated per project.
		 * @since 3.7.4
		 *
		 * @param array $scheduled_month_slots {
		 *  @type array $fully_scheduled_months
		 * }
		 * @param WC_Product $appointable_product
		 */
		return apply_filters( 'woocommerce_appointments_scheduled_month_slots', $scheduled_month_slots, $appointable_product );
	}

	/**
	 * Finds days which are partially scheduled & fully scheduled already.
	 *
	 * This function will get a general min/max Appointment date, which initially is [today, today + 1 year]
	 * Based on the Appointments retrieved from that date, it will shrink the range to the [Appointments_min, Appointments_max]
	 * For the newly generated range, it will determine availability of dates by calling `wc_appointments_get_time_slots` on it.
	 *
	 * Depending on the data returned from it we set:
	 * Fully scheduled days     - for those dates that there are no more slot available
	 * Partially scheduled days - for those dates that there are some slots available
	 *
	 * @param  WC_Product_Appointment|int $appointable_product
	 * @param  int                $min_date
	 * @param  int                $max_date
	 * @return array( 'partially_scheduled_days', 'remaining_scheduled_days', 'fully_scheduled_days', 'unavailable_days' )
	 */
	public static function find_scheduled_day_slots( $appointable_product, $min_date = 0, $max_date = 0, $default_date_format = 'Y-n-j', $timezone_offset = 0, $staff_ids = array() ) {
		$scheduled_day_slots = array(
			'partially_scheduled_days' => array(),
			'remaining_scheduled_days' => array(),
			'fully_scheduled_days'     => array(),
			'unavailable_days'         => array(),
		);

		$timezone_offset = $timezone_offset * HOUR_IN_SECONDS;

		if ( is_int( $appointable_product ) ) {
			$appointable_product = wc_get_product( $appointable_product );
		}

		if ( ! is_wc_appointment_product( $appointable_product ) ) {
			return $scheduled_day_slots;
		}

		// Get existing appointments and go through them to set partial/fully scheduled days
		$existing_appointments = WC_Appointment_Data_Store::get_all_existing_appointments( $appointable_product, $min_date, $max_date, $staff_ids );

		if ( empty( $existing_appointments ) ) {
			return $scheduled_day_slots;
		}

		$min_appointment_date = INF;
		$max_appointment_date = -INF;
		$appointments         = array();
		$day_format           = 1 === $appointable_product->get_available_qty() ? 'unavailable_days' : 'partially_scheduled_days';

		// Find the minimum and maximum appointment dates and store the appointment data in an array for further processing.
		foreach ( $existing_appointments as $existing_appointment ) {
			#print '<pre>'; print_r( $existing_appointment->get_id() ); print '</pre>';

			// Check appointment start and end times.
			$check_date    = strtotime( 'midnight', $existing_appointment->get_start() + $timezone_offset );
			$check_date_to = strtotime( 'midnight', $existing_appointment->get_end() + $timezone_offset - 1 ); #make sure midnight drops to same day

			#print '<pre>'; print_r( date( 'Y-m-d H:i', $check_date ) ); print '</pre>';
			#print '<pre>'; print_r( date( 'Y-m-d H:i', $check_date_to ) ); print '</pre>';

			// Get staff IDs. If non exist, make it zero (applies to all).
			$existing_staff_ids = $existing_appointment->get_staff_ids();
			$existing_staff_ids = ! is_array( $existing_staff_ids ) ? array( $existing_staff_ids ) : $existing_staff_ids;
			$existing_staff_ids = empty( $existing_staff_ids ) ? array( 0 ) : $existing_staff_ids;

			if ( ! empty( $staff_ids ) && ! array_intersect( $existing_staff_ids, $staff_ids ) ) {
				continue;
			}

			// If it's an appointment on the same day, move it before the end of the current day
			if ( $check_date_to === $check_date ) {
				$check_date_to = strtotime( '+1 day', $check_date ) - 1;
			}

			$min_appointment_date = min( $min_appointment_date, $check_date );
			$max_appointment_date = max( $max_appointment_date, $check_date_to );

			// If the appointment duration is day, make sure we add the (duration) days to unavailable days.
			// This will mark them as white on the calendar, since they are not fully scheduled, but rather
			// unavailable. The difference is that an appointment extending to those days is allowed.
			if ( 1 < $appointable_product->get_duration() && 'day' === $appointable_product->get_duration_unit() ) {
				$check_new_date = strtotime( '-' . ( $appointable_product->get_duration() - 1 ) . ' days', $min_appointment_date );

				// Mark the days between the fake appointment and the actual appointment as unavailable.
				while ( $check_new_date < $min_appointment_date ) {
					$date_format = date( $default_date_format, $check_new_date );
					foreach ( $existing_staff_ids as $existing_staff_id ) {
						$scheduled_day_slots[ $day_format ][ $date_format ][ $existing_staff_id ] = 1;
					}
					$check_new_date = strtotime( '+1 day', $check_new_date );
				}
			}

			$appointments[] = array(
				'start'          => $check_date,
				'end'            => $check_date_to,
				'staff'          => $existing_staff_ids,
				'get_staff_ids'  => $existing_appointment->get_staff_ids(),
				'get_start'      => $existing_appointment->get_start(),
				'get_end'        => $existing_appointment->get_end(),
				'get_qty'        => $existing_appointment->get_qty(),
				'get_id'         => $existing_appointment->get_id(),
				'get_product_id' => $existing_appointment->get_product_id(),
				'is_all_day'     => $existing_appointment->is_all_day(),
			);
		}

		$max_appointment_date = strtotime( '+1 day', $max_appointment_date );

		// Call these for the whole chunk range for the appointments since they're expensive
		$slots_a           = array();
		$appointment_staff = 0;
		$slots             = $appointable_product->get_slots_in_range( $min_appointment_date, $max_appointment_date );
		$available_slots   = $appointable_product->get_time_slots(
			array(
				'slots'        => $slots,
				'staff_id'     => $staff_ids,
				'from'         => $min_appointment_date,
				'to'           => $max_appointment_date,
				'appointments' => $appointments,
			)
		);
		$available_slots_a = array();

		// Available slots for the days.
		foreach ( $available_slots as $slot => $quantity ) {
			$available = $quantity['available']; #overall availability
			foreach ( $quantity['staff'] as $staff_id => $availability ) {
				if ( ! empty( $staff_ids ) && ! in_array( $staff_id, $staff_ids ) ) {
					continue;
				}
				if ( 0 < $availability && 0 < $available ) {
					$available_slots_a[ $staff_id ][] = date( $default_date_format, $slot );
				}
			}
		}

		// All available slots for the days.
		foreach ( $slots as $a_slot ) {
			$slots_a[] = date( $default_date_format, $a_slot );
		}

		#print '<pre>'; print_r( $slots ); print '</pre>';
		#print '<pre>'; print_r( $slots_a ); print '</pre>';
		#print '<pre>'; print_r( $appointments ); print '</pre>';
		#print '<pre>'; print_r( $available_slots ); print '</pre>';
		#print '<pre>'; print_r( $available_slots_a ); print '</pre>';

		// Go through [start, end] of each of the appointments by chunking it in days: [start, start + 1d, start + 2d, ..., end]
		// For each of the chunk check the available slots. If there are no slots, it is fully scheduled, otherwise partially scheduled.
		foreach ( $appointments as $appointment ) {
			$check_date = $appointment['start'];

			#print '<pre>'; print_r( date( 'Y-m-d', $check_date ) ); print '</pre>';

			while ( $check_date <= $appointment['end'] ) {
				$date_format     = date( $default_date_format, $check_date );
				$count_all_slots = is_array( $slots_a ) ? count( array_keys( $slots_a, $date_format ) ) : 0;

				// Remaining scheduled, when no staff selected.
				if ( $appointable_product->has_staff() && isset( $available_slots_a[0] ) && in_array( $date_format, $available_slots_a[0] ) ) {
					$count_available_slots = absint( count( array_keys( $available_slots_a[0], $date_format ) ) );

					$count_s = absint( $count_all_slots );
					$count_a = isset( $count_s ) && 0 !== $count_s ? $count_s : 1;
					$count_r = absint( round( ( $count_available_slots / $count_a ) * 10 ) );
					$count_r = ( 10 === $count_r ) ? 9 : $count_r;
					$count_r = ( 0 === $count_r ) ? 1 : $count_r;

					$scheduled_day_slots['remaining_scheduled_days'][ $date_format ][0] = $count_r;
				}

				foreach ( $appointment['staff'] as $existing_staff_id ) {
					$appointment_type     = isset( $available_slots_a[ $existing_staff_id ] ) && in_array( $date_format, $available_slots_a[ $existing_staff_id ] ) ? 'partially_scheduled_days' : 'fully_scheduled_days';
					$appointment_type_all = isset( $available_slots_a[0] ) && in_array( $date_format, $available_slots_a[0] ) ? 'partially_scheduled_days' : 'fully_scheduled_days';
					#print '<pre>'; print_r( $date_format ); print '</pre>';
					#print '<pre>'; print_r( $existing_staff_id ); print '</pre>';
					#print '<pre>'; print_r( $appointment_type ); print '</pre>';
					#print '<pre>'; print_r( $available_slots_a ); print '</pre>';
					$scheduled_day_slots[ $appointment_type_all ][ $date_format ][0]                = 1;
					$scheduled_day_slots[ $appointment_type ][ $date_format ][ $existing_staff_id ] = 1;
					// Remainging scheduled, when staff is selected.
					if ( 'partially_scheduled_days' === $appointment_type ) {
						$count_available_slots = count( array_keys( $available_slots_a[ $existing_staff_id ], $date_format ) );

						$count_s = absint( $count_all_slots );
						$count_a = isset( $count_s ) && 0 !== $count_s ? $count_s : 1;
						$count_b = absint( $count_available_slots );
						$count_r = absint( round( ( $count_b / $count_a ) * 10 ) );
						$count_r = ( 10 === $count_r ) ? 9 : $count_r;
						$count_r = ( 0 === $count_r ) ? 1 : $count_r;

						$scheduled_day_slots['remaining_scheduled_days'][ $date_format ][ $existing_staff_id ] = $count_r;
					}
				}

				$check_date = strtotime( '+1 day', $check_date );
			}
		}

		#print '<pre>'; print_r( $scheduled_day_slots ); print '</pre>';

		/**
		 * Filter the scheduled day slots calculated per project.
		 * @since 3.3.0
		 *
		 * @param array $scheduled_day_slots {staff
		 *  @type array $partially_scheduled_days
		 *  @type array $fully_scheduled_days
		 * }
		 * @param WC_Product $appointable_product
		 */
		return apply_filters( 'woocommerce_appointments_scheduled_day_slots', $scheduled_day_slots, $appointable_product );
	}

	/**
	 * Finds existing appointments for a product and its tied staff.
	 *
	 * @param  WC_Product_Appointment $appointable_product
	 * @param  int                $min_date
	 * @param  int                $max_date
	 * @return array
	 */
	public static function get_all_existing_appointments( $appointable_product, $min_date = 0, $max_date = 0, $staff_ids = array() ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointment_Data_Store::get_all_existing_appointments()' );
		return WC_Appointment_Data_Store::get_all_existing_appointments( $appointable_product, $min_date, $max_date, $staff_ids );
	}

	/**
	 * Return all appointments for a product and/or staff in a given range
	 * @param integer $start_date
	 * @param integer $end_date
	 * @param integer $product_id
	 * @param integer $staff_id
	 * @param bool    $check_in_cart
	 *
	 * @return array
	 */
	public static function get_appointments_in_date_range( $start_date, $end_date, $product_id = 0, $staff_id = 0, $check_in_cart = true, $filters = array(), $strict = false ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointment_Data_Store::get_appointments_in_date_range()' );
		return WC_Appointment_Data_Store::get_appointments_in_date_range( $start_date, $end_date, $product_id, $staff_id, $check_in_cart, $filters, $strict );
	}

	/**
	 * Return all appointments and blocked availability for a product and/or staff in a given range.
	 *
	 * @since 4.4.0
	 *
	 * @param integer $start_date
	 * @param integer $end_date
	 * @param integer $product_id
	 * @param integer $staff_id
	 * @param bool    $check_in_cart
	 *
	 * @return array
	 */
	public static function get_events_in_date_range( $start_date, $end_date, $product_id = 0, $staff_id = 0, $check_in_cart = true, $filters = array() ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointments_Availability_Data_Store::get_events_in_date_range()' );
		return WC_Appointments_Availability_Data_Store::get_events_in_date_range( $start_date, $end_date, $product_id, $staff_id, $check_in_cart, $filters );
	}

	/**
	 * Return an array global_availability_rules
	 *
	 * @since 4.4.0
	 *
	 * @param  int   $start_date
	 * @param  int . $end_date
	 *
	 * @return array Global availability rules
	 */
	public static function get_global_availability_in_date_range( $start_date, $end_date ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointments_Availability_Data_Store::get_global_availability_in_date_range()' );
		return WC_Appointments_Availability_Data_Store::get_global_availability_in_date_range( $start_date, $end_date );
	}

	/**
	 * Gets appointments for product ids and staff ids
	 * @param  array  $ids
	 * @param  array  $status
	 * @return array of WC_Appointment objects
	 */
	public static function get_appointments_for_objects( $product_ids = array(), $staff_ids = array(), $status = array(), $date_from = 0, $date_to = 0 ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointment_Data_Store::get_appointments_for_objects()' );
		return WC_Appointment_Data_Store::get_appointments_for_objects( $product_ids, $staff_ids, $status, $date_from, $date_to );
	}

	/**
	 * Gets appointments for product ids and staff ids
	 * @param  array  $ids
	 * @param  array  $status
	 * @param  integer  $date_from
	 * @param  integer  $date_to
	 * @return array of WC_Appointment objects
	 */
	public static function get_appointments_for_objects_query( $product_ids, $staff_ids, $status, $date_from = 0, $date_to = 0 ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointment_Data_Store::get_appointments_for_objects_query()' );
		return WC_Appointment_Data_Store::get_appointments_for_objects_query( $product_ids, $staff_ids, $status, $date_from, $date_to );
	}

	/**
	 * Gets appointments for a product by ID
	 *
	 * @param int $product_id The id of the product that we want appointments for
	 * @return array of WC_Appointment objects
	 */
	public static function get_appointments_for_product( $product_id, $status = array( 'confirmed', 'paid' ) ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointment_Data_Store::get_appointments_for_product()' );
		return WC_Appointment_Data_Store::get_appointments_for_product( $product_id, $status );
	}

	/**
	 * Gets appointments for a user by ID
	 *
	 * @param  int   $user_id    The id of the user that we want appointments for
	 * @param  array $query_args The query arguments used to get appointment IDs
	 * @return array             Array of WC_Appointment objects
	 */
	public static function get_appointments_for_user( $user_id, $query_args = null ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointment_Data_Store::get_appointments_for_user()' );
		return WC_Appointment_Data_Store::get_appointments_for_user( $user_id, $query_args );
	}

	/**
	 * Gets appointments for a customer by ID
	 *
	 * @deprecated 2.4.9
	 * @deprecated Use get_appointments()
	 * @see get_appointments()
	 *
	 * @param  int   $customer_id    The id of the customer that we want appointments for
	 * @param  array $query_args     The query arguments used to get appointment IDs
	 * @return array                 Array of WC_Appointment objects
	 */
	public static function get_appointments_for_customer( $customer_id, $query_args = null ) {
		wc_deprecated_function( __METHOD__, '2.4.9' );
		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_by(
			array(
				'status'      => get_wc_appointment_statuses( 'customer' ),
				'object_id'   => $customer_id,
				'object_type' => 'customer',
			)
		);

		return array_map( 'get_wc_appointment', $appointment_ids );
	}

	/**
	 * Return all appointments for a product in a given range - the query part (no cache)
	 *
	 * @param  integer $start_date
	 * @param  integer $end_date
	 * @param  int $product_id
	 * @param  int $staff_id
	 * @param  bool $check_in_cart
	 * @param  array $filters
	 * @return array of appointment ids
	 */
	private static function get_appointments_in_date_range_query( $start_date, $end_date, $product_id, $staff_id, $check_in_cart, $filters, $strict ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'WC_Appointment_Data_Store::get_appointments_in_date_range_query()' );
		return WC_Appointment_Data_Store::get_appointments_in_date_range_query( $start_date, $end_date, $product_id, $staff_id, $check_in_cart, $filters, $strict );
	}

	/**
	 * Get latest appointments
	 *
	 * @deprecated 2.4.9
	 * @deprecated Use get_appointments()
	 * @see get_appointments()
	 *
     * @param int $numberitems Number of objects returned (default to unlimited)
     * @param int $offset The number of objects to skip (as a query offset)
     * @param bool/array $status array of statuses for which to return appointments (defaults to all)
	 */
    public static function get_latest_appointments( $numberitems = -1, $offset = 0, $status = false ) {
		wc_deprecated_function( __METHOD__, '2.4.9' );
    }

	/**
	 * Get appointments
	 *
	 * @param array $arg array of arguments for the get_posts query
	 * @return array of WC_Appointment objects
	 */
	public static function get_appointments( $number_of_items = 10, $offset = 0 ) {
		wc_deprecated_function( __METHOD__, '4.7.0' );
	}

	/**
	 * Gets appointments for a staff
	 *
	 * @param  int $staff_id ID
	 * @param  array  $status
	 * @return array of WC_Appointment objects
	 */
	public static function get_appointments_for_staff( $staff_id, $status = array( 'confirmed', 'paid' ) ) {
		wc_deprecated_function( __METHOD__, '4.2.0' );
		$appointment_ids = WC_Appointment_Data_Store::get_appointment_ids_by(
			array(
				'object_id'   => $staff_id,
				'object_type' => 'staff',
				'status'      => $status,
			)
		);
		return array_map( 'get_wc_appointment', $appointment_ids );
	}

	/**
	 * Loop through given appointments to find those that are on or over lap the given date.
	 *
	 * @since 2.3.1
	 * @param  array $appointments
	 * @param  string $date
	 *
	 * @return array of appointment ids
	 */
	public static function filter_appointments_on_date( $appointments, $date, $product ) {
		wc_deprecated_function( __METHOD__, '4.2.0' );
		$appointments_on_date = array();
 		foreach ( $appointments as $appointment ) {
 			// Does the date we want to check fall on one of the days in the appointment?
 			if ( $appointment->get_start() <= $date && $appointment->get_end() >= $date ) {
 				$appointments_on_date[] = $appointment->get_qty();
 			}
 		}
 		return $appointments_on_date;
 	}
}
