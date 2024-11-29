<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Files.FileName

/**
 * WooCommerce Create Customer on Order integration class.
 * https://codecanyon.net/item/create-customer-on-order-for-woocommerce/6395319
 *
 * Last compatibility check: WooCommerce Create Customer on Order 1.33
 */
class WC_Appointments_Integration_WC_CXCCOO {

	/**
	 * Constructor
	 */
	public function __construct() {
		$wc_create_customer_on_order = WC_Create_Customer_On_Order::get_instance();

		// Add new Appointment page in admin.
		add_action( 'woocommerce_appointments_after_create_appointment_page', array( $wc_create_customer_on_order, 'create_customer_on_order_form' ) );
		add_filter( 'cxccoo_customer_search_inputs', array( $this, 'add_search_input_selectors' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_inline_css' ), 10 );
	}

	/**
	 * Add enhanced search input selectors.
	 *
 	 * @since 1.0.0
 	 * @return array
 	 */
	public function add_search_input_selectors( $selectors ) {
		$selectors[] = '.post-type-wc_appointment .form-table input.wc-customer-search';
		$selectors[] = '.post-type-wc_appointment .form-table select.wc-customer-search';
		$selectors[] = '.post-type-wc_appointment #appointment_data input.wc-customer-search';
		$selectors[] = '.post-type-wc_appointment #appointment_data select.wc-customer-search';

		return $selectors;
	}

	/**
	 * Add CSS in <head> for styles.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_inline_css() {
		$wc_cxccoo_styles = '.wc_appointment_page_add_appointment .cxccoo-button.cxccoo-create-user-main-button {max-width: 400px; box-sizing: border-box;}';

		wp_add_inline_style( 'wc_appointments_admin_styles', $wc_cxccoo_styles );
	}

}

new WC_Appointments_Integration_WC_CXCCOO();
