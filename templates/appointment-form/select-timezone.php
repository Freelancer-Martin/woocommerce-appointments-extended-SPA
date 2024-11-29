<?php
/**
 * SELECT appointment form timezone field
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/appointment-form/select-timezone.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     1.3.0
 * @since       4.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

wp_enqueue_script( 'select2' );
wp_enqueue_script( 'wc-appointments-moment' );
wp_enqueue_script( 'wc-appointments-moment-timezone' );
wp_enqueue_script( 'wc-appointments-timezone-picker' );

// Fields located inside includes\appointment-form\class-wc-appointment-form.php
$class    = $field['class'];
$label    = $field['label'];
$name     = $field['name'];
$selected = $field['selected'];
?>
<p class="form-field form-field-wide <?php echo esc_attr( implode( ' ', $class ) ); ?>">
	<label for="<?php echo esc_html( $name ); ?>"><?php echo $label; // WPCS: XSS ok. ?></label>
	<select name="<?php echo esc_html( $name ); ?>" id="<?php echo esc_html( $name ); ?>">
		<?php echo wp_timezone_choice( $selected, get_user_locale() ); // WPCS: XSS ok. ?>
	</select>
</p>
