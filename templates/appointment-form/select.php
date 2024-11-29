<?php
/**
 * SELECT appointment form field
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/appointment-form/select.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see         https://docs.woocommerce.com/document/template-structure/
 * @version     1.2.0
 * @since       3.4.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

$class   = $field['class'];
$label   = $field['label'];
$name    = $field['name'];
$options = $field['options'];
?>
<p class="form-field form-field-wide <?php echo esc_attr( implode( ' ', $class ) ); ?>">
	<label for="<?php echo esc_html( $name ); ?>"><?php echo esc_html( $label ); ?>:</label>
	<select name="<?php echo esc_html( $name ); ?>" id="<?php echo esc_html( $name ); ?>">
		<?php foreach ( $options as $key => $value ) : ?>
			<option value="<?php echo esc_html( $key ); ?>"><?php echo esc_html( $value ); ?></option>
		<?php endforeach; ?>
	</select>
</p>
