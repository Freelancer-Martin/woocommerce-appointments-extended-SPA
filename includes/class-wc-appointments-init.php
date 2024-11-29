<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Initializes appointments.
 *
 * @since 4.3.4
 */
class WC_Appointments_Init {
	/**
	 * Constructor.
	 *
	 * @since 4.3.4
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_post_types' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'appointment_form_styles' ), 99 ); #front
		add_action( 'wp_enqueue_scripts', array( $this, 'appointment_form_scripts' ), 99 ); #front
		add_action( 'admin_enqueue_scripts', array( $this, 'appointment_form_scripts' ), 99 ); #admin
		add_filter( 'woocommerce_data_stores', array( $this, 'register_data_stores' ) );
		add_filter( 'woocommerce_product_class', array( $this, 'woocommerce_product_class' ), 10, 2 );
		add_filter( 'woocommerce_locate_template', array( $this, 'woocommerce_locate_template' ), 10, 3 ); #For backward compatibility only.

		// Load payment gateway name.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'include_gateway' ) );

		// Default date and time formats must not be empty.
		add_filter( 'woocommerce_date_format', array( $this, 'default_date_format' ) );
		add_filter( 'woocommerce_time_format', array( $this, 'default_time_format' ) );

		// Disable marketplace suggestions.
		add_filter( 'woocommerce_allow_marketplace_suggestions', '__return_false' );
	}

	/**
	 * Init post types
	 */
	public function init_post_types() {
		register_post_type(
			'wc_appointment',
			apply_filters(
				'woocommerce_register_post_type_wc_appointment',
				array(
					'label'               => __( 'Appointment', 'woocommerce-appointments' ),
					'labels'              => array(
						'name'               => __( 'Appointments', 'woocommerce-appointments' ),
						'singular_name'      => __( 'Appointment', 'woocommerce-appointments' ),
						'add_new'            => __( 'Add New', 'woocommerce-appointments' ),
						'add_new_item'       => __( 'Add New Appointment', 'woocommerce-appointments' ),
						'edit'               => __( 'Edit', 'woocommerce-appointments' ),
						'edit_item'          => __( 'Edit Appointment', 'woocommerce-appointments' ),
						'new_item'           => __( 'New Appointment', 'woocommerce-appointments' ),
						'view'               => __( 'View Appointment', 'woocommerce-appointments' ),
						'view_item'          => __( 'View Appointment', 'woocommerce-appointments' ),
						'search_items'       => __( 'Search Appointments', 'woocommerce-appointments' ),
						'not_found'          => __( 'No Appointments found', 'woocommerce-appointments' ),
						'not_found_in_trash' => __( 'No Appointments found in trash', 'woocommerce-appointments' ),
						'parent'             => __( 'Parent Appointments', 'woocommerce-appointments' ),
						'menu_name'          => _x( 'Appointments', 'Admin menu name', 'woocommerce-appointments' ),
						'all_items'          => __( 'All Appointments', 'woocommerce-appointments' ),
					),
					'description'         => __( 'This is where appointments are stored.', 'woocommerce-appointments' ),
					'public'              => false,
					'show_ui'             => true,
					'capability_type'     => 'appointment',
					//'menu_icon'           => 'dashicons-backup',
					'menu_icon'           => 'dashicons-clock',
					'map_meta_cap'        => true,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'show_in_menu'        => true,
					'hierarchical'        => false,
					'show_in_nav_menus'   => false,
					'rewrite'             => false,
					'query_var'           => false,
					'supports'            => array( '' ),
					'has_archive'         => false,
				)
			)
		);

		/**
		 * Post status
		 */
		register_post_status(
			'complete',
			array(
				'label'                     => '<span class="status-complete tips" data-tip="' . _x( 'Complete', 'woocommerce-appointments', 'woocommerce-appointments' ) . '">' . _x( 'Complete', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: 1: count, 2: count */
				'label_count'               => _n_noop( 'Complete <span class="count">(%s)</span>', 'Complete <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'paid',
			array(
				'label'                     => '<span class="status-paid tips" data-tip="' . _x( 'Paid &amp; Confirmed', 'woocommerce-appointments', 'woocommerce-appointments' ) . '">' . _x( 'Paid &amp; Confirmed', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: 1: count, 2: count */
				'label_count'               => _n_noop( 'Paid &amp; Confirmed <span class="count">(%s)</span>', 'Paid &amp; Confirmed <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'confirmed',
			array(
				'label'                     => '<span class="status-confirmed tips" data-tip="' . _x( 'Confirmed', 'woocommerce-appointments', 'woocommerce-appointments' ) . '">' . _x( 'Confirmed', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: 1: count, 2: count */
				'label_count'               => _n_noop( 'Confirmed <span class="count">(%s)</span>', 'Confirmed <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'unpaid',
			array(
				'label'                     => '<span class="status-unpaid tips" data-tip="' . _x( 'Un-paid', 'woocommerce-appointments', 'woocommerce-appointments' ) . '">' . _x( 'Un-paid', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: 1: count, 2: count */
				'label_count'               => _n_noop( 'Un-paid <span class="count">(%s)</span>', 'Un-paid <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'pending-confirmation',
			array(
				'label'                     => '<span class="status-pending tips" data-tip="' . _x( 'Pending Confirmation', 'woocommerce-appointments', 'woocommerce-appointments' ) . '">' . _x( 'Pending Confirmation', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: 1: count, 2: count */
				'label_count'               => _n_noop( 'Pending Confirmation <span class="count">(%s)</span>', 'Pending Confirmation <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'cancelled',
			array(
				'label'                     => '<span class="status-cancelled tips" data-tip="' . _x( 'Cancelled', 'woocommerce-appointments', 'woocommerce-appointments' ) . '">' . _x( 'Cancelled', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: 1: count, 2: count */
				'label_count'               => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'in-cart',
			array(
				'label'                     => '<span class="status-incart tips" data-tip="' . _x( 'In Cart', 'woocommerce-appointments', 'woocommerce-appointments' ) . '">' . _x( 'In Cart', 'woocommerce-appointments', 'woocommerce-appointments' ) . '</span>',
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				/* translators: 1: count, 2: count */
				'label_count'               => _n_noop( 'In Cart <span class="count">(%s)</span>', 'In Cart <span class="count">(%s)</span>', 'woocommerce-appointments' ),
			)
		);
		register_post_status(
			'was-in-cart',
			array(
				'label'                     => false,
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
				'label_count'               => false,
			)
		);

	}

	/**
	 * Register data stores for appointments.
	 *
	 * @param  array  $data_stores
	 * @return array
	 */
	public function register_data_stores( $data_stores = array() ) {
		$data_stores['appointment']               = 'WC_Appointment_Data_Store';
		$data_stores['appointments-availability'] = 'WC_Appointments_Availability_Data_Store';
		$data_stores['product-appointment']       = 'WC_Product_Appointment_Data_Store_CPT';

		return $data_stores;
	}

	/**
	 * Checks the classname being used for a appointable product type to see if it should be an appointable product
	 * and if so, returns this as the class which should be instantiated (instead of the default
	 * WC_Product_Simple class).
	 *
	 * @return string $classname The name of the WC_Product_* class which should be instantiated to create an instance of this product.
	 * @since 3.9.4
	 */
	public function woocommerce_product_class( $classname, $product_type ) {
	    if ( 'appointment' === $product_type ) {
	        $classname = 'WC_Product_Appointment';
	    }

	    return $classname;
	}

	/**
	 * Backdrop to deprecated templates inside 'woocommerce-appointments' theme folder
	 *
	 * Will be removed in later versions
	 *
	 * @deprecated
	 */
	public function woocommerce_locate_template( $template, $template_name, $template_path ) {
		$deprecated_template_path = 'woocommerce-appointments';

		$deprecated_template = locate_template(
			array(
				trailingslashit( $deprecated_template_path ) . $template_name,
				$template_name,
			)
		);

		if ( $deprecated_template ) {
			return $deprecated_template;
		}

		return $template;
	}

	/**
	 * Add a custom payment gateway
	 * This gateway works with appointment that requires confirmation
	 */
	public function include_gateway( $gateways ) {
		$gateways[] = 'WC_Appointments_Gateway';

		return $gateways;
	}

	/**
	 * Date format should not be zero.
	 *
	 * @since 4.2.7
	 */
	public function default_date_format( $format ) {
		if ( '' === $format ) {
			return 'F j, Y';
		}

		return $format;
	}

	/**
	 * Time format should not be zero.
	 *
	 * @since 4.2.7
	 */
	public function default_time_format( $format ) {
		if ( '' === $format ) {
			return 'g:i a';
		}

		return $format;
	}

	/**
	 * Appointment form styles
	 */
	public static function appointment_form_styles() {
		// Register styles.
		if ( ! wp_style_is( 'select2', 'registered' ) ) {
			wp_register_style( 'select2', WC_APPOINTMENTS_PLUGIN_URL . '/assets/css/select2.css', null, WC_APPOINTMENTS_VERSION );
		}
		if ( ! wp_style_is( 'woocommerce-addons-css', 'registered' ) ) {
			wp_register_style( 'woocommerce-addons-css', WC_APPOINTMENTS_PLUGIN_URL . '/includes/integrations/woocommerce-product-addons/assets/css/frontend.css', null, WC_APPOINTMENTS_VERSION );
		}
		wp_register_style( 'wc-appointments-styles', WC_APPOINTMENTS_PLUGIN_URL . '/assets/css/frontend.css', array( 'woocommerce-addons-css' ), WC_APPOINTMENTS_VERSION );

		// Enqueue styles.
		wp_enqueue_style( 'select2' );
		wp_enqueue_style( 'wc-appointments-styles' );
	}

	/**
	 * Appointment form scripts
	 */
	public static function appointment_form_scripts() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'wc-appointments-appointment-form', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/appointment-form' . $suffix . '.js', array( 'jquery', 'jquery-blockui' ), WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc-appointments-month-picker', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/month-picker' . $suffix . '.js', array( 'wc-appointments-appointment-form' ), WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc-appointments-date-picker', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/date-picker' . $suffix . '.js', array( 'wc-appointments-appointment-form', 'wc-appointments-rrule', 'wc-appointments-moment', 'jquery-ui-datepicker', 'underscore' ), WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc-appointments-time-picker', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/time-picker' . $suffix . '.js', array( 'wc-appointments-appointment-form' ), WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc-appointments-timezone-picker', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/timezone-picker' . $suffix . '.js', array( 'wc-appointments-appointment-form' ), WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc-appointments-staff-picker', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/staff-picker' . $suffix . '.js', array( 'wc-appointments-appointment-form' ), WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc-appointments-moment', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/moment/moment-with-locales' . $suffix . '.js', array(), WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc-appointments-moment-timezone', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/moment/moment-timezone-with-data' . $suffix . '.js', array(), WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc-appointments-rrule', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/rrule/rrule' . $suffix . '.js', array(), WC_APPOINTMENTS_VERSION, true );
		wp_register_script( 'wc-appointments-rrule-tz', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/rrule/rrule-tz' . $suffix . '.js', array(), WC_APPOINTMENTS_VERSION, true );

		// Register Select2 script.
		if ( ! wp_script_is( 'select2', 'registered' ) ) {
			wp_register_script( 'select2', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/select2' . $suffix . '.js', array( 'wc-appointments-appointment-form' ), WC_APPOINTMENTS_VERSION, true );
        }

		// Get $wp_locale.
		$wp_locale = new WP_Locale();

		// Variables for JS scripts
		$appointment_form_params = array(
			'nonce_find_day_slots'          => wp_create_nonce( 'find-scheduled-day-slots' ),
			'nonce_set_timezone_cookie'     => wp_create_nonce( 'set-timezone-cookie' ),
			'nonce_staff_html'              => wp_create_nonce( 'appointable-staff-html' ),
			'closeText'                     => __( 'Close', 'woocommerce-appointments' ),
			'currentText'                   => __( 'Today', 'woocommerce-appointments' ),
			'prevText'                      => __( 'Previous', 'woocommerce-appointments' ),
			'nextText'                      => __( 'Next', 'woocommerce-appointments' ),
			'monthNames'                    => array_values( $wp_locale->month ),
			'monthNamesShort'               => array_values( $wp_locale->month_abbrev ),
			'dayNames'                      => array_values( $wp_locale->weekday ),
			'dayNamesShort'                 => array_values( $wp_locale->weekday_abbrev ),
			'dayNamesMin'                   => array_values( $wp_locale->weekday_initial ),
			'firstDay'                      => get_option( 'start_of_week' ),
			'current_time'                  => date( 'Ymd', current_time( 'timestamp' ) ),
			'showWeek'                      => false,
			'showOn'                        => false,
			'numberOfMonths'                => 1,
			'showButtonPanel'               => false,
			'showOtherMonths'               => true,
			'selectOtherMonths'             => true,
			'gotoCurrent'                   => true,
			'changeMonth'                   => false,
			'changeYear'                    => false,
			'duration_changed'              => false,
			'default_availability'          => false,
			'costs_changed'                 => false,
			'cache_ajax_requests'           => 'false',
			'ajax_url'                      => admin_url( 'admin-ajax.php' ),
			'i18n_date_unavailable'         => __( 'Selected date is unavailable', 'woocommerce-appointments' ),
			'i18n_date_invalid'             => __( 'Selected date is not valid', 'woocommerce-appointments' ),
			'i18n_time_unavailable'         => __( 'Selected time is unavailable', 'woocommerce-appointments' ),
			'i18n_date_fully_scheduled'     => __( 'Selected date is fully scheduled and unavailable', 'woocommerce-appointments' ),
			'i18n_date_partially_scheduled' => __( 'Selected date is partially scheduled - but appointments still remain', 'woocommerce-appointments' ),
			'i18n_date_available'           => __( 'Selected date is available', 'woocommerce-appointments' ),
			'i18n_start_date'               => __( 'Choose a Start Date', 'woocommerce-appointments' ),
			'i18n_end_date'                 => __( 'Choose an End Date', 'woocommerce-appointments' ),
			'i18n_dates'                    => __( 'Dates', 'woocommerce-appointments' ),
			'i18n_clear_date_selection'     => __( 'To clear selection, pick a new start date', 'woocommerce-appointments' ),
			'is_admin'                      => is_admin(),
			'isRTL'                         => is_rtl(),
			'server_timezone'               => wc_appointment_get_timezone_string(),
			// 'server_time_format'            => wc_appointments_convert_to_moment_format( get_option( 'time_format' ) ),
			// 'product_id'                    => $this->product->get_id(),
		);

		$wc_appointments_date_picker_args = array(
			'ajax_url' => WC_Ajax_Compat::get_endpoint( 'wc_appointments_find_scheduled_day_slots' ),
		);

		$wc_appointments_timezone_picker_args = array(
			'ajax_url' => WC_Ajax_Compat::get_endpoint( 'wc_appointments_set_timezone_cookie' ),
		);

		wp_localize_script( 'wc-appointments-appointment-form', 'wc_appointment_form_params', apply_filters( 'wc_appointment_form_params', $appointment_form_params ) );
		wp_localize_script( 'wc-appointments-date-picker', 'wc_appointments_date_picker_args', $wc_appointments_date_picker_args );
		wp_localize_script( 'wc-appointments-timezone-picker', 'wc_appointments_timezone_picker_args', $wc_appointments_timezone_picker_args );
	}

	/**
	 * Attempt to convert a date formatting string from PHP to Moment
	 *
	 * @param string $format
	 * @return string
	 */
	protected function convert_to_moment_format( $format ) {
		wc_deprecated_function( __METHOD__, '4.7.0', 'wc_appointments_convert_to_moment_format' );
		return wc_appointments_convert_to_moment_format( $format );
	}

}

new WC_Appointments_Init();
