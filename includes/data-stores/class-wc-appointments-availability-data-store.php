<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Appointments_Availability_Data_Store
 *
 * @package Woocommerce/Appointments
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC Availability Data Store: Stored in Custom table.
 * @todo When 2.6 support is dropped, implement WC_Object_Data_Store_Interface
 */
class WC_Appointments_Availability_Data_Store extends WC_Data_Store_WP {

	const TABLE_NAME       = 'wc_appointments_availability';
	const CACHE_GROUP      = 'wc-appointments-availability';
	const DEFAULT_MIN_DATE = '0000-00-00';
	const DEFAULT_MAX_DATE = '9999-99-99';

	/**
	 * Create a new availability in the database.
	 *
	 * @param WC_Appointments_Availability $availability WC_Appointments_Availability instance.
	 */
	public function create( &$availability ) {
		global $wpdb;

		$availability->apply_changes();

		$data = array(
			'kind'          => $availability->get_kind( 'edit' ),
			'kind_id'       => $availability->get_kind_id( 'edit' ),
			'event_id'      => $availability->get_event_id( 'edit' ),
			'title'         => $availability->get_title( 'edit' ),
			'range_type'    => $availability->get_range_type( 'edit' ),
			'from_date'     => $availability->get_from_date( 'edit' ),
			'to_date'       => $availability->get_to_date( 'edit' ),
			'from_range'    => $availability->get_from_range( 'edit' ),
			'to_range'      => $availability->get_to_range( 'edit' ),
			'appointable'   => $availability->get_appointable( 'edit' ),
			'priority'      => $availability->get_priority( 'edit' ),
			'qty'           => $availability->get_qty( 'edit' ),
			'ordering'      => $availability->get_ordering( 'edit' ),
			'rrule'         => $availability->get_rrule( 'edit' ),
			'date_created'  => current_time( 'mysql' ),
			'date_modified' => current_time( 'mysql' ),
		);

		$wpdb->insert( $wpdb->prefix . self::TABLE_NAME, $data );
		$availability->set_id( $wpdb->insert_id );
		WC_Cache_Helper::incr_cache_prefix( self::CACHE_GROUP );
		WC_Appointments_Cache::delete_appointment_slots_transient();
	}

	/**
	 * Read availability from the database.
	 *
	 * @param  WC_Appointments_Availability $availability Instance.
	 * @throws Exception When webhook is invalid.
	 */
	public function read( &$availability ) {
		global $wpdb;

		$data = wp_cache_get( $availability->get_id(), self::CACHE_GROUP );

		if ( false === $data ) {
			$data = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT
							ID as id,
							kind,
							kind_id,
							event_id,
							title,
							range_type,
							from_date,
							to_date,
							from_range,
							to_range,
							appointable,
							priority,
							qty,
							ordering,
							date_created,
							date_modified,
							rrule
						FROM ' . $wpdb->prefix . self::TABLE_NAME .
						' WHERE ID = %d LIMIT 1;',
					$availability->get_id()
				),
				ARRAY_A
			); // WPCS: unprepared SQL ok.

			wp_cache_add( $availability->get_id(), $data, self::CACHE_GROUP );
		}

		if ( is_array( $data ) ) {
			$availability->set_props( $data );
			$availability->set_object_read( true );
		}
	}

	/**
	 * Update a webhook.
	 *
	 * @param WC_Appointments_Availability $availability Instance.
	 */
	public function update( &$availability ) {
		global $wpdb;

		$changes = $availability->get_changes();

		$changes['date_modified'] = current_time( 'mysql' );

		$wpdb->update(
			$wpdb->prefix . self::TABLE_NAME,
			$changes,
			array(
				'ID' => $availability->get_id(),
			)
		);

		$availability->apply_changes();

		wp_cache_delete( $availability->get_id(), self::CACHE_GROUP );
		WC_Cache_Helper::incr_cache_prefix( self::CACHE_GROUP );
		WC_Appointments_Cache::delete_appointment_slots_transient();
	}

	/**
	 * Remove a webhook from the database.
	 *
	 * @param WC_Appointments_Availability $availability Instance.
	 * @param array           $options      Options array.
	 */
	public function delete( &$availability, $options = array() ) {
		global $wpdb;

		do_action( 'woocommerce_appointments_before_delete_appointment_availability', $availability, $this ); // WC_Data::delete does not trigger an action like save() so we have to do it here.

		$wpdb->delete(
			$wpdb->prefix . self::TABLE_NAME,
			array(
				'ID' => $availability->get_id(),
			),
			array( '%d' )
		);
		wp_cache_delete( $availability->get_id(), self::CACHE_GROUP );
		WC_Cache_Helper::incr_cache_prefix( self::CACHE_GROUP );
		WC_Appointments_Cache::delete_appointment_slots_transient();
	}

	/**
	 * Get all availabilties defined in the database as objetcs.
	 *
	 * @param array  $filters { @see self::build_query() }.
	 * @param string $min_date { @see self::build_query() }.
	 * @param string $max_date { @see self::build_query() }.
	 *
	 * @return WC_Appointments_Availability[]
	 * @throws Exception Validation fails.
	 */
	public function get_all( $filters = array(), $min_date = self::DEFAULT_MIN_DATE, $max_date = self::DEFAULT_MAX_DATE ) {

		$data = $this->get_all_as_array( $filters, $min_date, $max_date );

		$availabilities = array();
		foreach ( $data as $row ) {
			$availability = get_wc_appointments_availability();
			$availability->set_object_read( false );
			$availability->set_props( $row );
			$availability->set_object_read( true );
			$availabilities[] = $availability;
		}

		return apply_filters( 'woocommerce_appointments_get_all_availabilities', $availabilities );
	}

	/**
	 * Get global availability as array.
	 *
	 * @param array  $filters { @see self::build_query() }.
	 * @param string $min_date { @see self::build_query() }.
	 * @param string $max_date { @see self::build_query() }.
	 *
	 * @return array|null|object
	 */
	public function get_all_as_array( $filters = array(), $min_date = self::DEFAULT_MIN_DATE, $max_date = self::DEFAULT_MAX_DATE ) {
		global $wpdb;

		if ( ! is_array( $filters ) ) {
			$filters = array(); // WC_Data_Store uses call_user_func_array to call this function so the default parameter is not used.
		}

		$sql = $this->build_query( $filters, $min_date, $max_date );

		$cache_key = WC_Cache_Helper::get_cache_prefix( self::CACHE_GROUP ) . 'get_all:' . md5( $sql );
		$array     = wp_cache_get( $cache_key, self::CACHE_GROUP );

		#echo '<pre>' . var_export( $array, true ) . '</pre>';

		if ( false === $array ) {
			$array = $wpdb->get_results( $sql, ARRAY_A ); // WPCS: unprepared SQL ok.

			foreach ( $array as &$row ) {
				// Set BC keys.
				$row['type'] = $row['range_type'];
				$row['to']   = $row['to_range'];
				$row['from'] = $row['from_range'];
			}

			wp_cache_add( $cache_key, $array, self::CACHE_GROUP );
		}

		return $array;
	}

	/**
	 * Builds query string for availability.
	 *
	 * @param array $filters { @see self::build_query() }.
	 * @param string $min_date Minimum date to select intersecting availability entries for (yyyy-mm-dd format).
	 * @param string $max_date Maximum date to select intersecting availability entries for (yyyy-mm-dd format).
	 *
	 * @return string
	 */
	private function build_query( $filters, $min_date, $max_date ) {
		global $wpdb;

		/*
		 * Build list of fields with virtual fields 'start_date' and 'end_date'.
		 * 'start_date' shall be '0000-00-00' for recurring events.
		 * 'end_date' shall be '9999-99-99' for recurring events.
		 */
		$fields = array(
			'ID',
			'kind',
			'kind_id',
			'event_id',
			'title',
			'range_type',
			'from_date',
			'to_date',
			'from_range',
			'to_range',
			'rrule',
			'appointable',
			'priority',
			'qty',
			'ordering',
			'date_created',
			'date_modified',
			'(CASE
				WHEN range_type = \'custom\' THEN from_range
				WHEN range_type = \'time:range\' THEN from_date
				WHEN range_type = \'custom:daterange\' THEN from_date
				ELSE \'0000-00-00\'
			END) AS start_date',
			'(CASE
				WHEN range_type = \'custom\' THEN to_range
				WHEN range_type = \'time:range\' THEN to_date
				WHEN range_type = \'custom:daterange\' THEN to_date
				ELSE \'9999-99-99\'
			END) AS end_date',
		);

		// Identity for WHERE clause.
		$where = array( '1' );

		// Parse WHERE for SQL.
		foreach ( $filters as $filter ) {
			$compare = $this->validate_compare( $filter['compare'] );

			switch ( $compare ) {
				case 'IN':
				case 'NOT IN':
					$key     = esc_sql( $filter['key'] );
					$value   = implode( "','", array_map( 'esc_sql', $filter['value'] ) );
					$where[] = "`{$key}` {$compare} ('{$value}')";
					break;
				case 'BETWEEN':
				case 'NOT BETWEEN':
					$key     = esc_sql( $filter['key'] );
					$value   = implode( "' AND '", array_map( 'esc_sql', $filter['value'] ) );
					$where[] = "(`{$key}` {$compare} '{$value}')";
					break;
				default:
					$key     = esc_sql( $filter['key'] );
					$value   = esc_sql( $filter['value'] );
					$where[] = "`{$key}` {$compare} '{$value}'";
					break;
			}
		}

		// Query for dates that intersect with the min and max.
		if ( self::DEFAULT_MIN_DATE !== $min_date || self::DEFAULT_MAX_DATE !== $max_date ) {
			$min_max_dates       = array( esc_sql( $min_date ), esc_sql( $max_date ) );
			$date_intersect_or   = array();
			$date_intersect_or[] = vsprintf( "( start_date BETWEEN '%s' AND '%s' )", $min_max_dates );
			$date_intersect_or[] = vsprintf( "( end_date BETWEEN '%s' AND '%s' )", $min_max_dates );
			$date_intersect_or[] = vsprintf( "( start_date <= '%s' AND end_date >= '%s' )", $min_max_dates );
			$where[]             = sprintf( "( %s )", implode( ' OR ', $date_intersect_or ) );
		}
		sort( $where );

		return sprintf(
			'SELECT * FROM ( SELECT %s FROM %s ) AS a_data WHERE %s ORDER BY ordering ASC',
			implode( ', ', $fields ),
			$wpdb->prefix . self::TABLE_NAME,
			implode( ' AND ', $where )
		);
	}

	/**
	 * Validates query filter comparison (defaults to '=')
	 *
	 * @param string $compare Raw compare string.
	 * @return string Validated compare string.
	 */
	private function validate_compare( $compare ) {

		$compare = strtoupper( $compare );

		if ( ! in_array(
			$compare,
			array(
				'=',
				'!=',
				'>',
				'>=',
				'<',
				'<=',
				'LIKE',
				'NOT LIKE',
				'IN',
				'NOT IN',
				'BETWEEN',
				'NOT BETWEEN',
			)
		) ) {
			$compare = '=';
		}

		return $compare;
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
	 * @param bool    $filters
	 *
	 * @return array Appointments and Availabilities (merged in one array)
	 */
	public static function get_events_in_date_range( $start_date, $end_date, $product_id = 0, $staff_id = 0, $check_in_cart = true, $filters = array() ) {
		$appointments = WC_Appointment_Data_Store::get_appointments_in_date_range( $start_date, $end_date, $product_id, $staff_id, $check_in_cart, $filters, true );
		$min_date     = date( 'Y-m-d', $start_date );
		$max_date     = date( 'Y-m-d', $end_date );

		// Filter only for events synced from Google Calendar.
		$availability_filters = array(
			array(
				'key'     => 'kind',
				'compare' => '=',
				'value'   => 'availability#global',
			),
			array(
				'key'     => 'event_id',
				'compare' => '!=',
				'value'   => '',
			),
		);

		$global_availabilities = WC_Data_Store::load( 'appointments-availability' )->get_all( $availability_filters, $min_date, $max_date );

		return array_merge( $appointments, $global_availabilities );
	}

	/**
	 * Return an array global_availability_rules
	 *
	 * @since 4.4.0
	 *
	 * @param  int  $start_date
	 * @param  int  $end_date
	 *
	 * @return array Global availability rules
	 */
	public static function get_global_availability_in_date_range( $start_date, $end_date ) {
		// Filter only for events from Global availability.
		$filters = array(
			array(
				'key'     => 'kind',
				'compare' => '=',
				'value'   => 'availability#global',
			),
			array(
				'key'     => 'event_id',
				'compare' => '==',
				'value'   => '',
			),
		);

		$min_date = date( 'Y-m-d', $start_date );
		$max_date = date( 'Y-m-d', $end_date );

		return WC_Data_Store::load( 'appointments-availability' )->get_all( $filters, $min_date, $max_date );
	}

	/**
	 * Get global availability rules.
	 *
	 * @param  bool $with_gcal
	 * @return array
	 */
	public static function get_global_availability( $with_gcal = true ) {
		if ( $with_gcal ) {
			$global_rules = WC_Data_Store::load( 'appointments-availability' )->get_all_as_array(
				array(
					array(
						'key'     => 'kind',
						'compare' => 'IN',
						'value'   => array( 'availability#global' ),
					),
				)
			);
		} else {
			$global_rules = WC_Data_Store::load( 'appointments-availability' )->get_all_as_array(
				array(
					array(
						'key'     => 'kind',
						'compare' => '=',
						'value'   => 'availability#global',
					),
					array(
						'key'     => 'event_id',
						'compare' => '==',
						'value'   => '',
					),
				)
			);
		}

		return apply_filters( 'wc_appointments_global_availability', $global_rules );
	}

	/**
	 * Get staff availability rules.
	 *
	 * @param  array $staff_ids
	 * @return array
	 */
	public static function get_staff_availability( $staff_ids = array() ) {
		if ( $staff_ids & ! empty( $staff_ids ) ) {
			$staff_rules = WC_Data_Store::load( 'appointments-availability' )->get_all_as_array(
				array(
					array(
						'key'     => 'kind',
						'compare' => '=',
						'value'   => 'availability#staff',
					),
					array(
						'key'     => 'kind_id',
						'compare' => 'IN',
						'value'   => $staff_ids,
					),
				)
			);
		} else {
			$staff_rules = WC_Data_Store::load( 'appointments-availability' )->get_all_as_array(
				array(
					array(
						'key'     => 'kind',
						'compare' => '=',
						'value'   => 'availability#staff',
					),
				)
			);
		}

		return apply_filters( 'wc_appointments_all_staff_availability', $staff_rules );
	}

}
