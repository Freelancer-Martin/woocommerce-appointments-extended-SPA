<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Avaiablity Filter Widget and related functions.
 *
 * Generates from/to date picker to filter products by date.
 *
 * @version 3.4.0
 * @since 3.4.0
 */
class WC_Appointments_Widget_Availability extends WC_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wp_locale;

		$this->widget_cssclass    = 'woocommerce widget_availability_filter';
		$this->widget_description = __( 'Filter products in your store by availability.', 'woocommerce-appointments' );
		$this->widget_id          = 'woocommerce_availability_filter';
		$this->widget_name        = __( 'Filter Products by Availability', 'woocommerce-appointments' );
		$this->settings           = array(
			'title' => array(
				'type'  => 'text',
				'std'   => __( 'Filter by availability', 'woocommerce-appointments' ),
				'label' => __( 'Title', 'woocommerce-appointments' ),
			),
		);

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_script( 'wc-appointments-availability-filter', WC_APPOINTMENTS_PLUGIN_URL . '/assets/js/availability-filter' . $suffix . '.js', array( 'jquery-ui-datepicker', 'underscore' ), WC_APPOINTMENTS_VERSION, true );
		wp_localize_script(
			'wc-appointments-availability-filter',
			'wc_appointments_availability_filter_params',
			array(
				'closeText'       => __( 'Close', 'woocommerce-appointments' ),
				'currentText'     => __( 'Today', 'woocommerce-appointments' ),
				'prevText'        => __( 'Previous', 'woocommerce-appointments' ),
				'nextText'        => __( 'Next', 'woocommerce-appointments' ),
				'monthNames'      => array_values( $wp_locale->month ),
				'monthNamesShort' => array_values( $wp_locale->month_abbrev ),
				'dayNames'        => array_values( $wp_locale->weekday ),
				'dayNamesShort'   => array_values( $wp_locale->weekday_abbrev ),
				'dayNamesMin'     => array_values( $wp_locale->weekday_initial ),
				'firstDay'        => get_option( 'start_of_week' ),
				'isRTL'           => is_rtl(),
			)
		);

		if ( is_customize_preview() ) {
			wp_enqueue_script( 'wc-appointments-availability-filter' );
		}

		add_filter( 'loop_shop_post_in', array( $this, 'filter_products_by_availability' ) );

		parent::__construct();
	}

	/**
	 * Output widget.
	 *
	 * @see WP_Widget
	 *
	 * @param array $args     Arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		global $wp;

		if ( ! is_shop() && ! is_product_taxonomy() ) {
			return;
		}

		if ( ! wc()->query->get_main_query()->post_count ) {
			return;
		}

		// Add date picker JS.
		wp_enqueue_script( 'wc-appointments-availability-filter' );

		$min_date = isset( $_GET['min_date'] ) ? wc_clean( wp_unslash( $_GET['min_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$max_date = isset( $_GET['max_date'] ) ? wc_clean( wp_unslash( $_GET['max_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		$this->widget_start( $args, $instance );

		if ( '' === get_option( 'permalink_structure' ) ) {
			$form_action = remove_query_arg( array( 'page', 'paged', 'product-page' ), add_query_arg( $wp->query_string, '', home_url( $wp->request ) ) );
		} else {
			$form_action = preg_replace( '%\/page/[0-9]+%', '', home_url( trailingslashit( $wp->request ) ) );
		}

		echo '<form method="get" action="' . esc_url( $form_action ) . '">
			<div class="date_picker_wrapper">
				<label for="min_date">' . esc_html__( 'Start Date:', 'woocommerce-appointments' ) . '</label>
				<input type="text" id="min_date" class="date-picker" name="min_date" value="' . esc_attr( $min_date ) . '" autocomplete="off" readonly="readonly" />
				<label for="max_date">' . esc_html__( 'End Date:', 'woocommerce-appointments' ) . '</label>
				<input type="text" id="max_date" class="date-picker" name="max_date" value="' . esc_attr( $max_date ) . '" autocomplete="off" readonly="readonly" />
				<button type="submit" class="button">' . esc_html__( 'Filter', 'woocommerce-appointments' ) . '</button>
				' . wc_query_string_form_fields( null, array( 'min_date', 'max_date' ), '', true ) . '
				<div class="clear"></div>
			</div>
		</form>'; // WPCS: XSS ok.

		$this->widget_end( $args );
	}

	/**
	 * Get filtered min date for current products.
	 *
	 * @return int
	 */
	public function filter_products_by_availability() {
		$min_date = isset( $_GET['min_date'] ) ? wc_clean( wp_unslash( $_GET['min_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$max_date = isset( $_GET['max_date'] ) ? wc_clean( wp_unslash( $_GET['max_date'] ) ) : ''; // WPCS: input var ok, CSRF ok.

		// Get available appointments.
        if ( $this->is_valid_date( $min_date ) && $this->is_valid_date( $max_date ) ) {
            $matches = $this->get_available_products( $min_date, $max_date );

			if ( $matches ) {
				return $matches;
			}
        }
	}

	/**
     * Get all available appointment products
     *
     * @param string $start_date_raw YYYY-MM-DD format
     * @param string $end_date_raw YYYY-MM-DD format
     * @param int $quantity Number of people to appoint
     * @param string $behavior Whether to return exact matches
     * @return array Available post IDs
     */
    protected function get_available_products( $start_date_raw, $end_date_raw, $quantity = 1, $behavior = 'default' ) {
        $matches = array();

        // Separate dates from times.
        $start_date = explode( ' ', $start_date_raw );
        $end_date   = explode( ' ', $end_date_raw );

        // If time wasn't passed, define defaults.
        if ( ! isset( $start_date[1] ) ) {
            $start_date[1] = '00:00';
        }

        $start = explode( '-', $start_date[0] );

		// Timezone.
		$tzstring = isset( $_COOKIE['appointments_time_zone'] ) ? $_COOKIE['appointments_time_zone'] : '';
		$tzstring = isset( $posted['wc_appointments_field_timezone'] ) ? $posted['wc_appointments_field_timezone'] : $tzstring;

        $args = array(
			'quantity'                               => $quantity,
            'wc_appointments_field_staff'            => 0,
            'wc_appointments_field_duration'         => 1,
			'wc_appointments_field_timezone'         => $tzstring,
            'wc_appointments_field_start_date_year'  => $start[0],
            'wc_appointments_field_start_date_month' => $start[1],
            'wc_appointments_field_start_date_day'   => $start[2],
        );

		$appointable_product_ids = WC_Data_Store::load( 'product-appointment' )->get_appointable_product_ids( true );

        // Loop through all posts
        foreach ( $appointable_product_ids as $appointable_product_id ) {
			// Get product object.
			$appointable_product = get_wc_product_appointment( $appointable_product_id );

            // Get product duration unit.
            $duration_unit = $appointable_product->get_duration_unit();

            // Support time
            if ( in_array( $duration_unit, array( 'minute', 'hour' ) ) ) {
                if ( ! empty( $start_date[1] ) ) {
                    $args['wc_appointments_field_start_date_time'] = $start_date[1];
                }
            }

            $posted_data = wc_appointments_get_posted_data( $args, $appointable_product );

            // All slots are available (exact match).
            if ( true === $appointable_product->is_appointable( $posted_data ) ) {
                $matches[] = $appointable_product->get_id();

			// Any slot between the given dates are available.
			} else {
				$appointment_form = new WC_Appointment_Form( $appointable_product );
                $from             = strtotime( $start_date_raw );
                $to               = strtotime( $end_date_raw );
                $slots_in_range   = $appointment_form->product->get_slots_in_range( $from, $to );
                $available_slots  = $appointment_form->product->get_available_slots(
					array(
	                    'slots' => $slots_in_range,
	                    'from'  => $from,
	                    'to'    => $to,
	                )
				);

                foreach ( $available_slots as $check ) {
                    if ( true === $appointment_form->product->check_availability_rules_against_date( $check, '' ) ) {
                        $matches[] = $appointable_product->get_id();
                        break; #check passed
                    }
                }
            }
        }

		return $matches;
    }

	/**
     * Validate date input
     *
     * @requires PHP 5.3+
     */
    protected function is_valid_date( $date ) {
        if ( empty( $date ) ) {
            return false;
        } elseif ( 10 === strlen( $date ) ) {
            $d = DateTime::createFromFormat( 'Y-m-d', $date );
            return $d && $d->format( 'Y-m-d' ) === $date;
        } elseif ( 16 === strlen( $date ) ) {
            $d = DateTime::createFromFormat( 'Y-m-d H:i', $date );
            return $d && $d->format( 'Y-m-d H:i' ) === $date;
        }

        return false;
    }

}

/**
 * Register Widgets.
 *
 * @since 3.4.0
 */
function wc_appointments_register_widgets() {
	register_widget( 'WC_Appointments_Widget_Availability' );
}

add_action( 'widgets_init', 'wc_appointments_register_widgets' );
