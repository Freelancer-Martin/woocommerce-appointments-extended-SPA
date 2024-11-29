<?php
/**
 * The Template for displaying upload field.
 *
 * @version 3.0.0
 */

$field_name      = ! empty( $addon['field_name'] ) ? $addon['field_name'] : '';
$addon_key       = 'addon-' . sanitize_title( $field_name );
$current_value   = isset( $_POST[ $addon_key ] ) && isset( $_POST[ $addon_key ] ) ? $_POST[ $addon_key ] : '';
$max             = ! empty( $addon['max'] ) ? $addon['max'] : '';
$attr            = ! empty( $max ) ? 'maxlength="' . esc_attr( $max ) . '"' : '';
$adjust_price    = ! empty( $addon['adjust_price'] ) ? $addon['adjust_price'] : '';
$price           = ! empty( $addon['price'] ) ? $addon['price'] : '';
$price_type      = ! empty( $addon['price_type'] ) ? $addon['price_type'] : '';
$price_raw       = apply_filters( 'woocommerce_product_addons_price_raw', '1' == $adjust_price && $price ? $price : '', $addon );
$adjust_duration = ! empty( $addon['adjust_duration'] ) ? $addon['adjust_duration'] : '';
$duration        = ! empty( $addon['duration'] ) ? absint( $addon['duration'] ) : '';
$duration_type   = ! empty( $addon['duration_type'] ) ? $addon['duration_type'] : '';
$duration_raw    = apply_filters( 'woocommerce_product_addons_duration_raw', '1' == $adjust_duration && $duration ? $duration : '', $addon );

$price_display = apply_filters(
	'woocommerce_product_addons_price',
	'1' == $adjust_price && $price_raw ? WC_Product_Addons_Helper::get_product_addon_price_for_display( $price_raw ) : '',
	$addon,
	0,
	$addon,
	'file_upload'
);

$duration_display = apply_filters(
	'woocommerce_product_addons_duration',
	'1' == $adjust_duration && $duration_raw ? ' ' . wc_appointment_pretty_addon_duration( $duration_raw ) : '',
	$addon,
	0,
	$addon,
	'file_upload'
);

if ( 'percentage_based' === $price_type ) {
	$price_display = $price_raw;
}
?>

<p class="form-row form-row-wide wc-pao-addon-wrap wc-pao-addon-<?php echo sanitize_title( $field_name ); ?>">
	<input
		type="file"
		class="wc-pao-addon-file-upload input-text wc-pao-addon-field"
		data-raw-price="<?php echo esc_attr( $price_raw ); ?>"
		data-price="<?php echo esc_attr( $price_display ); ?>"
		data-price-type="<?php echo esc_attr( $price_type ); ?>"
		data-raw-duration="<?php echo esc_attr( $duration_raw ); ?>"
		data-duration="<?php echo esc_attr( $duration_display ); ?>"
		data-duration-type="<?php echo esc_attr( $duration_type ); ?>"
		name="addon-<?php echo sanitize_title( $field_name ); ?>"
		<?php if ( WC_Product_Addons_Helper::is_addon_required( $addon ) ) { echo 'required'; } ?>
	/> <small><?php echo sprintf( __( '(max file size %s)', 'woocommerce-appointments' ), $max_size ) ?></small>
</p>
