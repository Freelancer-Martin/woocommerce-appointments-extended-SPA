<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Polylang for WooCommerce integration class.
 *
 * Last compatibility check: Polylang for WooCommerce 1.0.1
 */
class WC_Appointments_Integration_Polylang {
	private $switched_locale;

	/**
	 * Constructor
	 *
	 * @since 0.6
	 */
	public function __construct() {
		// Post types
		add_filter( 'pll_get_post_types', array( $this, 'translate_types' ), 10, 2 );

		if ( PLL() instanceof PLL_admin ) {
			add_action( 'wp_loaded', array( $this, 'custom_columns' ), 20 );
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 20 );
		}

		// Statuses
		$appointments_statuses = array(
			'unpaid',
			'pending-confirmation',
			'confirmed',
			'paid',
			'cancelled',
			'complete',
			'in-cart',
			'was-in-cart',
		);

		foreach ( $appointments_statuses as $status ) {
			add_action( 'woocommerce_appointment_' . $status, array( $this, 'before_appointment_metabox_save' ) );
		}

		add_action( 'woocommerce_appointment_process_meta', array( $this, 'after_appointment_metabox_save' ) );

		// Create appointment
		add_action( 'woocommerce_new_appointment', array( $this, 'new_appointment' ), 1 );

		// Appointment language user has switched between "added to cart" and "completed checkout"
		add_action( 'woocommerce_appointment_in-cart_to_unpaid', array( $this, 'set_appointment_language_at_checkout' ) );
		add_action( 'woocommerce_appointment_in-cart_to_pending-confirmation', array( $this, 'set_appointment_language_at_checkout' ) );

		// Products
		add_action( 'wp_ajax_woocommerce_remove_appointable_staff', array( $this, 'remove_appointable_staff' ), 5 ); // Before WooCommerce Appointments

		add_action( 'pll_save_post', array( $this, 'save_post' ), 10, 3 );
		add_filter( 'update_post_metadata', array( $this, 'update_post_metadata' ), 99, 4 ); // After Yoast SEO which returns null at priority 10 See https://github.com/Yoast/wordpress-seo/pull/6902
		add_filter( 'get_post_metadata', array( $this, 'get_post_metadata' ), 10, 4 );
		add_filter( 'pll_copy_post_metas', array( $this, 'copy_post_metas' ), 10, 5 );
		add_filter( 'pll_translate_post_meta', array( $this, 'translate_post_meta' ), 10, 3 );

		// Cart
		add_filter( 'pllwc_translate_cart_item', array( $this, 'translate_cart_item' ) );
		add_filter( 'pllwc_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );

		// Emails
		$actions = array(
			// Cancelled appointment
			'woocommerce_appointment_pending-confirmation_to_cancelled_notification',
			'woocommerce_appointment_confirmed_to_cancelled_notification',
			'woocommerce_appointment_paid_to_cancelled_notification',
			// Appointment confirmed
			'wc-appointment-confirmed',
			// Reminder
			'wc-appointment-reminder',
			// Reminder
			'wc-appointment-follow-up',
			// New appointment
			'woocommerce_admin_new_appointment_notification',
		);

		foreach ( $actions as $action ) {
			add_action( $action, array( PLLWC()->emails, 'before_order_email' ), 1 ); // Switch the language for the email
			add_action( $action, array( PLLWC()->emails, 'after_email' ), 999 ); // Switch the language back after the email has been sent
		}

		add_action( 'change_locale', array( $this, 'change_locale' ) );
		add_action( 'parse_query', array( $this, 'filter_appointments_notifications' ) );

		// Endpoints in emails
		if ( isset( PLL()->translate_slugs ) ) {
			add_action( 'pllwc_email_language', array( PLL()->translate_slugs->slugs_model, 'init_translated_slugs' ) );
		}

		// Appointments endpoint
		add_filter( 'pll_translation_url', array( $this, 'pll_translation_url' ), 10, 2 );
		add_filter( 'pllwc_endpoints_query_vars', array( $this, 'pllwc_endpoints_query_vars' ), 10, 3 );

		if ( PLL() instanceof PLL_Frontend ) {
			add_action( 'parse_query', array( $this, 'parse_query' ), 3 ); // Before Polylang (for orders)
		}
	}

	/**
	 * Language and translation management for custom post types
	 *
	 * @since 0.6
	 *
	 * @param array $types List of post type names for which Polylang manages language and translations
	 * @param bool  $hide  True when displaying the list in Polylang settings
	 * @return array List of post type names for which Polylang manages language and translations
	 */
	public function translate_types( $types, $hide ) {
		$wc_appointments_types = array(
			'wc_appointment',
		);

		return $hide ? array_diff( $types, $wc_appointments_types ) : array_merge( $types, $wc_appointments_types );
	}

	/**
	 * Removes the standard languages columns for appointments
	 * and replace them with one unique column as for orders
	 *
	 * @since 0.6
	 */
	public function custom_columns() {
		remove_filter( 'manage_edit-wc_appointment_columns', array( PLL()->filters_columns, 'add_post_column' ), 100 );
		remove_action( 'manage_wc_appointment_posts_custom_column', array( PLL()->filters_columns, 'post_column' ), 10, 2 );

		add_filter( 'manage_edit-wc_appointment_columns', array( PLLWC()->admin_orders, 'add_order_column' ), 100 );
		add_action( 'manage_wc_appointment_posts_custom_column', array( PLLWC()->admin_orders, 'order_column' ), 10, 2 );

		// FIXME add a filter in PLLWC for position of the column?
	}

	/**
	 * Removes the language metabox for appointments
	 *
	 * @since 0.6
	 *
	 * @param string $post_type
	 */
	public function add_meta_boxes( $post_type ) {
		if ( 'wc_appointment' === $post_type ) {
			remove_meta_box( 'ml_box', $post_type, 'side' ); // Remove Polylang metabox
		}
	}

	/**
	 * Reload Appointments translations
	 * Used for emails and the workaround localized appointments meta keys
	 *
	 * @since 1.0
	 */
	public function change_locale() {
		$GLOBALS['wc_appointments']->load_plugin_textdomain();
	}

	/**
	 * Reloads the WooCommerce Appointments and WP text domains to workaround localized appointments meta
	 *
	 * @since 0.6
	 *
	 * @param int $post_id
	 */
	public function before_appointment_metabox_save( $post_id ) {
		if ( isset( $_POST['post_type'], $_POST['wc_appointments_details_meta_box_nonce'] ) && 'wc_appointment' === $_POST['post_type'] ) { // Make sure we are on the right page
			$appointment_locale = pll_get_post_language( $post_id, 'locale' );
			$this->switched_locale = switch_to_locale( $appointment_locale );
		}
	}

	/**
	 * Reloads the WooCommerce Appointments and WP text domains to workaround localized appointments meta
	 * Part of the workaround for localized appointments meta keys
	 *
	 * @since 0.6
	 */
	public function after_appointment_metabox_save() {
		if ( ! empty( $this->switched_locale ) ) {
			unset( $this->switched_locale );
			restore_previous_locale();
		}
	}

	/**
	 * Assigns the appointment and order languages when creating a new appointment from admin
	 *
	 * @since 0.6
	 *
	 * @param int $appointment_id
	 */
	public function new_appointment( $appointment_id ) {
		$appointment = get_wc_appointment( $appointment_id );

		$data_store = PLLWC_Data_Store::load( 'product_language' );
		$lang = $data_store->get_language( $appointment->product_id );

		pll_set_post_language( $appointment->id, $lang );

		if ( ! empty( $appointment->order_id ) ) {
			$data_store = PLLWC_Data_Store::load( 'order_language' );
			$data_store->set_language( $appointment->order_id, $lang );
		}
	}

	/**
	 * Assigns the appointment language in case a visitor adds the product to cart in a language
	 * and then switches the language when before he completes the checkout
	 *
	 * @since 0.7.3
	 *
	 * @param int $appointment_id
	 */
	public function set_appointment_language_at_checkout( $appointment_id ) {
		$lang = pll_current_language();

		if ( pll_get_post_language( $appointment_id ) !== $lang ) {
			pll_set_post_language( $appointment_id, $lang );
		}
	}

	/**
	 * Copy or synchronize appointable posts (staff)
	 *
	 * @since 0.6
	 *
	 * @param array  $post Appointable post to copy (staff)
	 * @param int    $to   id of the product to which we paste informations
	 * @param string $lang Language slug
	 * @param bool   $sync True if it is synchronization, false if it is a copy, defaults to false
	 * @return int Translated appointable post
	 */
	protected function copy_appointable_post( $post, $to, $lang, $sync ) {
		$id = $post['ID'];
		$tr_id = pll_get_post( $id, $lang );

		if ( $tr_id ) {
			// If the translation already exists, make sure it has the right post_parent
			$post = get_post( $tr_id );
			if ( $post->post_parent !== $to ) {
				wp_update_post( array( 'ID' => $tr_id, 'post_parent' => $to ) );
			}
		}

		// Synchronize metas
		PLL()->sync->post_metas->copy( $id, $tr_id, $lang );

		return $tr_id;
	}

	/**
	 * Copy or synchronize providers
	 *
	 * @since 0.6
	 *
	 * @param int    $from id of the product from which we copy informations
	 * @param int    $to   id of the product to which we paste informations
	 * @param string $lang language slug
	 * @param bool   $sync true if it is synchronization, false if it is a copy, defaults to false
	 */
	public function copy_providers( $from, $to, $lang, $sync = false ) {
		global $wpdb;

		$relationships = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wc_appointment_relationships WHERE product_id = %d", $from ), ARRAY_A );

		foreach ( $relationships as $relationship ) {
			$tr_staff_id = $relationship['staff_id'];
			$tr_sort_order = $relationship['sort_order'];
			if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_appointment_relationships WHERE product_id = %d AND staff_id = %d", $to, $tr_staff_id ) ) ) {
				unset( $relationship['ID'] );
				$relationship['product_id'] = $to;
				$wpdb->insert( "{$wpdb->prefix}wc_appointment_relationships", $relationship );
			} else {
				$wpdb->update(
					"{$wpdb->prefix}wc_appointment_relationships",
					array(
						'sort_order'  => $tr_sort_order,
					),
					array(
						'product_id'  => $to,
						'staff_id' => $tr_staff_id,
					)
				);
			}
		}
	}

	/**
	 * Remove providers in translated products when a staff is removed in Ajax
	 *
	 * @since 0.6
	 */
	public function remove_appointable_staff() {
		global $wpdb;

		check_ajax_referer( 'delete-appointable-staff', 'security' );

		$product_id = absint( $_POST['post_id'] );
		$staff_id   = absint( $_POST['staff_id'] );

		$data_store = PLLWC_Data_Store::load( 'product_language' );

		foreach ( $data_store->get_translations( $product_id ) as $lang => $tr_id ) {
			if ( $tr_id !== $product_id ) { // Let WooCommerce delete the current relationship
				$tr_staff_id = pll_get_post( $staff_id, $lang );

				$wpdb->delete(
					"{$wpdb->prefix}wc_appointment_relationships",
					array(
						'product_id' => $tr_id,
						'staff_id'   => $tr_staff_id,
					)
				);
			}
		}
	}

	/**
	 * Add appointments metas when creating a new product or staff
	 *
	 * @since 0.9.3
	 *
	 * @param int    $post_id      New product or staff
	 * @param array  $translations Existing product or staff translations
	 * @param string $meta_key     Meta to add to the appointment
	 */
	protected function add_metas_to_appointment( $post_id, $translations, $meta_key ) {
		global $wpdb;

		$query = new WP_Query( array(
			'fields'     => 'ids',
			'post_type'  => 'wc_appointment',
			'lang'       => '',
			'meta_query' => array(
				array(
					'key'     => $meta_key,
					'value'   => $translations,
					'compare' => 'IN',
				),
				array(
					'key'     => $meta_key,
					'value'   => array( $post_id ),
					'compare' => 'NOT IN',
				),
			),
		) );

		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $appointment ) {
				$values[] = $wpdb->prepare( '( %d, %s, %d )', $appointment, $meta_key, $post_id );
			}

			$wpdb->query( "INSERT INTO {$wpdb->postmeta} ( post_id, meta_key, meta_value ) VALUES " . implode( ',', $values ) ); // WPCS: unprepared SQL ok.
		}
	}

	/**
	 * Update appointments associated to translated products (or staff)
	 * when creating a new product (or staff translation)
	 *
	 * @since 0.9.3
	 *
	 * @param int    $post_id      post id
	 * @param object $post         post object
	 * @param array  $translations post translations
	 */
	public function save_post( $post_id, $post, $translations ) {
		$translations = array_diff( $translations, array( $post_id ) );

		if ( 'product' === $post->post_type ) {
			$this->add_metas_to_appointment( $post_id, $translations, '_appointment_product_id' );
		}
	}

	/**
	 * Allows to associate several products or providers to an appointment
	 *
	 * @since 0.6
	 *
	 * @param null|bool  $r          Returned value (null by default)
	 * @param int        $post_id    Appointment id
	 * @param string     $meta_key
	 * @param int|string $meta_value
	 * return null|bool
	 */
	public function update_post_metadata( $r, $post_id, $meta_key, $meta_value ) {
		static $once = false;

		if ( in_array( $meta_key, array( '_appointment_product_id', '_appointment_staff_id' ) ) && ! empty( $meta_value ) && ! $once ) {
			$once = true;
			$r = $this->update_post_meta( $post_id, $meta_key, $meta_value );
		}
		$once = false;

		return $r;
	}

	/**
	 * Associates all products in a translation group to an appointment
	 *
	 * @since 0.6
	 *
	 * @param int    $post_id    Appointment id
	 * @param string $meta_key
	 * @param int    $meta_value Product id
	 * return bool
	 */
	protected function update_post_meta( $post_id, $meta_key, $meta_value ) {
		$values = get_post_meta( $post_id, $meta_key );

		if ( empty( $values ) ) {
			foreach ( pll_get_post_translations( $meta_value ) as $id ) {
				add_post_meta( $post_id, $meta_key, $id );
			}
		} else {
			$to_keep = array_intersect( $values, pll_get_post_translations( $meta_value ) );
			$olds = array_values( array_diff( $values, $to_keep ) );
			$news = array_values( array_diff( pll_get_post_translations( $meta_value ), $to_keep ) );
			foreach ( $olds as $k => $old ) {
				update_post_meta( $post_id, $meta_key, $news[ $k ], $old );
			}
		}

		return true;
	}

	/**
	 * Allows to get the appointment's associated product and staff in the current language
	 *
	 * @since 0.6
	 *
	 * @param null|bool $r          Returned value (null by default)
	 * @param int       $post_id    Appointment id
	 * @param string    $meta_key
	 * @param bool      $single
	 * return null|bool
	 */
	public function get_post_metadata( $r, $post_id, $meta_key, $single ) {
		static $once = false;

		if ( ! $once && $single ) {
			switch ( $meta_key ) {
				case '_appointment_product_id':
				case '_appointment_staff_id':
					$once = true;
					$value = get_post_meta( $post_id, $meta_key, true );
					$language = PLL() instanceof PLL_Frontend ? pll_current_language() : pll_get_post_language( $post_id );
					$once = false;
					return pll_get_post( $value, $language );
			}
		}

		if ( ! $once && empty( $meta_key ) && 'wc_appointment' === get_post_type( $post_id ) ) {
			$once = true;
			$value = get_post_meta( $post_id );
			$language = PLL() instanceof PLL_Frontend ? pll_current_language() : pll_get_post_language( $post_id );

			foreach ( array( '_appointment_product_id', '_appointment_staff_id' ) as $key ) {
				if ( ! empty( $value[ $key ] ) ) {
					$value[ $key ] = array( pll_get_post( reset( $value[ $key ] ), $language ) );
				}
			}

			$once = false;
			return $value;
		}

		return $r;
	}

	/**
	 * Adds metas to synchronize when saving a product or staff
	 *
	 * @since 0.6
	 *
	 * @param array  $metas List of custom fields names
	 * @param bool   $sync  True if it is synchronization, false if it is a copy
	 * @param int    $from  Id of the post from which we copy informations, optional, defaults to null
	 * @param int    $to    Id of the post to which we paste informations, optional, defaults to null
	 * @param string $lang  Language slug, optional, defaults to null
	 * @return array
	 */
	function copy_post_metas( $metas, $sync, $from, $to, $lang ) {
		$to_sync = array(
			/*'_wc_appointment_has_price_label',*/
			/*'_wc_appointment_price_label',*/
			'_wc_appointment_has_pricing',
			'_wc_appointment_pricing',
			'_wc_appointment_qty',
			'_wc_appointment_qty_min',
			'_wc_appointment_qty_max',
			'_wc_appointment_staff_assignment',
			'_wc_appointment_duration',
			'_wc_appointment_duration_unit',
			'_wc_appointment_interval',
			'_wc_appointment_interval_unit',
			'_wc_appointment_min_date',
			'_wc_appointment_min_date_unit',
			'_wc_appointment_max_date',
			'_wc_appointment_max_date_unit',
			'_wc_appointment_padding_duration',
			'_wc_appointment_padding_duration_unit',
			'_wc_appointment_user_can_cancel',
			'_wc_appointment_cancel_limit',
			'_wc_appointment_cancel_limit_unit',
			'_wc_appointment_customer_timezones',
			'_wc_appointment_cal_color',
			'_wc_appointment_requires_confirmation',
			'_wc_appointment_availability_span',
			'_wc_appointment_availability_autoselect',
			'_wc_appointment_has_restricted_days',
			'_wc_appointment_restricted_days',
			/*'_wc_appointment_availability',*/
			/*'_wc_appointment_staff_label',*/
			'_wc_appointment_staff_assignment',
			'_staff_base_costs', // To translate
			'_staff_qtys', // To translate
		);

		return array_merge( $metas, $to_sync );
	}

	/**
	 * Translate a product meta before it is copied or synchronized
	 *
	 * @since 1.0
	 *
	 * @param mixed  $value Meta value
	 * @param string $key   Meta key
	 * @param string $lang  Language of target
	 * @return mixed
	 */
	public function translate_post_meta( $value, $key, $lang ) {
		if ( in_array( $key, array( '_staff_base_costs', '_staff_qtys' ) ) ) {
			$tr_value = array();
			foreach ( $value as $post_id => $cost ) {
				if ( $tr_id = pll_get_post( $post_id, $lang ) ) {
					$tr_value[ $tr_id ] = $cost;
				}
			}
			$value = $tr_value;
		}
		return $value;
	}

	/**
	 * Translates appointments items in cart
	 * See WC_Appointment_Form::get_posted_data()
	 *
	 * @since 0.6
	 *
	 * @param array $item Cart item
	 * return array
	 */
	public function translate_cart_item( $item ) {
		if ( ! empty( $item['appointment'] ) ) {
			$appointment = &$item['appointment'];

			// Translate date
			if ( ! empty( $appointment['date'] ) && ! empty( $appointment['_date'] ) ) {
				$appointment['date'] = date_i18n( wc_date_format(), strtotime( $appointment['_date'] ) );
			}

			// Translate time
			if ( ! empty( $appointment['time'] ) && ! empty( $appointment['_time'] ) ) {
				$appointment['time'] = date_i18n( get_option( 'time_format' ), strtotime( "{$appointment['_year']}-{$appointment['_month']}-{$appointment['_day']} {$appointment['_time']}" ) );
			}

			// We need to set the price
			if ( ! empty( $item['data'] ) && ! empty( $appointment['_cost'] ) ) {
				$item['data']->set_price( $appointment['_cost'] );
			}
		}

		return $item;
	}

	/**
	 * Adds appointment to cart item data when translating the cart
	 *
	 * @since 0.7.4
	 *
	 * @param array $cart_item_data Cart item data
	 * @param array $item           Cart item
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $item ) {
		if ( isset( $item['appointment'] ) ) {
			$cart_item_data['appointment'] = $item['appointment'];
		}
		return $cart_item_data;
	}

	/**
	 * Filters appointments when sending notifications to get only appointments in the same language as the chosen product
	 *
	 * @since 0.6
	 *
	 * @param object $query
	 */
	public function filter_appointments_notifications( $query ) {
		$qvars = &$query->query_vars;

		if ( function_exists( 'get_current_screen' ) && ( $screen = get_current_screen() ) && 'wc_appointment_page_appointment_notification' === $screen->id && 'wc_appointment' === $qvars['post_type'] ) {
			$meta_query = reset( $qvars['meta_query'] );
			$query->set( 'lang', pll_get_post_language( $meta_query['value'] ) );
		}
	}

	/**
	 * Returns the translation of the appointments endpoint url
	 *
	 * @since 0.6
	 *
	 * @param string $url
	 * @param string $lang language slug
	 * @return string
	 */
	public function pll_translation_url( $url, $lang ) {
		global $wp;

		$endpoint = apply_filters( 'woocommerce_appointments_account_endpoint', 'appointments' );

		if ( isset( PLL()->translate_slugs->slugs_model, $wp->query_vars[ $endpoint ] ) ) {
			$language = PLL()->model->get_language( $lang );
			$url = wc_get_endpoint_url( $endpoint, '', $url );
			$url = PLL()->translate_slugs->slugs_model->switch_translated_slug( $url, $language, 'wc_appointments' );
		}

		return $url;
	}

	/**
	 * Adds Appointment endpoint to the list of endpoints to translate
	 *
	 * @since 0.6
	 *
	 * @param array $slugs Endpoints slugs
	 * @return array
	 */
	public function pllwc_endpoints_query_vars( $slugs ) {
		$slugs[] = apply_filters( 'woocommerce_appointments_account_endpoint', 'appointments' );
		return $slugs;
	}

	/**
	 * Disables the languages filter for a customer to see all appointments whatever the languages
	 *
	 * @since 0.6
	 *
	 * @param object $query WP_Query object
	 */
	public function parse_query( $query ) {
		$qvars = $query->query_vars;

		// Customers should see all their orders whatever the language
		if ( isset( $qvars['post_type'] ) && ( 'wc_appointment' === $qvars['post_type'] || ( is_array( $qvars['post_type'] ) && in_array( 'wc_appointment', $qvars['post_type'] ) ) ) ) {
			$query->set( 'lang', 0 );
		}
	}
}

new WC_Appointments_Integration_Polylang();
