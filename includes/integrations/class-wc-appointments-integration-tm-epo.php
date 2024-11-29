<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce TM Extra Product Options integration class.
 *
 * Last compatibility check:  WooCommerce TM Extra Product Options 4.8.4
 */
class WC_Appointments_Integration_TM_EPO {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'wcml_cart_contents_not_changed', array( $this, 'filter_bundled_product_in_cart_contents' ), 9999, 3 );
	}

	/**
	 * Compatibility with WPML multicurrency.
	 */
	public function filter_bundled_product_in_cart_contents( $cart_item, $key, $current_language ) {
		global $woocommerce_wpml;

		if ( defined( 'WCML_MULTI_CURRENCIES_INDEPENDENT' ) && $cart_item['data'] instanceof WC_Product_Appointment && isset( $cart_item['appointment'] ) ) {
			$current_id      = apply_filters( 'translate_object_id', $cart_item['product_id'], 'product', true, $current_language );
			$cart_product_id = $cart_item['product_id'];

			if ( WCML_MULTI_CURRENCIES_INDEPENDENT == $woocommerce_wpml->settings['enable_multi_currency'] || $current_id != $cart_product_id ) {
				$tm_epo_options_prices = floatval( $cart_item['tm_epo_options_prices'] );
				$current_cost          = floatval( $cart_item['data']->get_price() );

				$cart_item['data']->set_price( $current_cost + $tm_epo_options_prices );
			}
		}

		return $cart_item;
	}

}

$GLOBALS['wc_appointments_integration_tm_epo'] = new WC_Appointments_Integration_TM_EPO();
