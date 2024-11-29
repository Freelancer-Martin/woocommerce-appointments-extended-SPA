<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class for extending WooCommerce Product Exports and Imports.
 */
class WC_Appointments_Admin_Product_Export_Import {
	/**
	 * The data properties we'd like to include in the export.
	 *
	 * @var array
	 */
	protected $properties = array();

	/**
	 * Merges appointment product data into the parent object.
	 *
	 * @param int|WC_Product|object $product Product to init.
	 */
	public function __construct( $product = 0 ) {
		$this->set_default_properties();
		add_filter( 'woocommerce_product_export_product_default_columns', array( $this, 'add_column_names' ), 20, 2 );
		add_filter( 'woocommerce_csv_product_import_mapping_options', array( $this, 'add_mapped_fields' ), 20, 2 );
	}
	/**
 	 * Setup the appointable properties that should also be exported.
	 *
	 * @TODO Add translations to labels.
 	 *
 	 * @since 3.6.0
 	 */
	public function set_default_properties(){
		$this->properties = array(
			'has_price_label'         => 'Has price label option',
			'price_label'             => 'Price label',
			'has_pricing'             => 'Has pricing option',
			'pricing'                 => 'Pricing',
			'qty'                     => 'Qty',
			'qty_min'                 => 'Qty min',
			'qty_max'                 => 'Qty max',
			'duration_unit'           => 'Duration unit',
			'duration'                => 'Duration',
			'interval_unit'           => 'Interval unit',
			'interval'                => 'Interval',
			'padding_duration_unit'   => 'Padding duration unit',
			'padding_duration'        => 'Padding duration',
			'min_date_unit'           => 'Min date unit',
			'min_date'                => 'Min date',
			'max_date_unit'           => 'Max date unit',
			'max_date'                => 'Max date',
			'user_can_cancel'         => 'User can cancel option',
			'cancel_limit_unit'       => 'Cancel limit unit',
			'cancel_limit'            => 'Cancel limit',
			'cal_color'               => 'Calendar color',
			'requires_confirmation'   => 'Requires confirmation option',
			'availability_span'       => 'Availability span selection',
			'availability_autoselect' => 'Availability autoselect option',
			'has_restricted_days'     => 'Has restricted days option',
			'restricted_days'         => 'Restricted days options',
			'availability'            => 'Availability rules',
			'staff_label'             => 'Staff label',
			'staff_assignment'        => 'Staff assignment selection',
			'staff_nopref'            => 'Staff no preference option',
			'staff_id'                => 'Staff ID',
			'staff_ids'               => 'Staff IDs',
			'staff_base_costs'        => 'Staff base costs',
			'staff_qtys'              => 'Staff quantities',
		);
	}

	/**
	 * When exporting  WC core uses columns to decided which properties to export.
	 * Add the appointments properties  to this list to ensure that we export those values.
	 *
	 * @since 3.6.0
	 *
	 * @param array $default_columns
	 *
	 * @return array
	 */
	public function add_column_names( $default_columns ) {
		return $default_columns + $this->properties;
	}

	/**
	 * When importing WC core uses maps to link data to properties.
	 * Add the appointments mappings to this list.
	 *
	 * @since 3.6.0
	 *
	 * @param array $mappings
	 *
	 * @return array
	 */
	public function add_mapped_fields( $mappings ) {
		return $mappings + array_flip( $this->properties );
	}
}

new WC_Appointments_Admin_Product_Export_Import();
