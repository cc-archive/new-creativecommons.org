<?php
/*
 * @package   GFP_Google_Analytics_Ecommerce
 * @copyright 2015 gravity+
 * @license   GPL-2.0+
 * @since     1.0.0
 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class GFP_Google_Analytics_Ecommerce_Addon
 *
 * Adds form feed and sends ecommerce info to Google Analytics after Gravity Forms submission
 *
 * @since  1.0.0
 *
 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
 */
class GFP_Google_Analytics_Ecommerce_Addon extends GFFeedAddOn {

	/**
	 * @var string Version number of the Add-On
	 */
	protected $_version;

	/**
	 * @var string Gravity Forms minimum version requirement
	 */
	protected $_min_gravityforms_version;

	/**
	 * @var string URL-friendly identifier used for form settings, add-on settings, text domain localization...
	 */
	protected $_slug;

	/**
	 * @var string Relative path to the plugin from the plugins folder
	 */
	protected $_path;

	/**
	 * @var string Full path to the plugin. Example: __FILE__
	 */
	protected $_full_path;

	/**
	 * @var string URL to the App website.
	 */
	protected $_url;

	/**
	 * @var string Title of the plugin to be used on the settings page, form settings and plugins page.
	 */
	protected $_title;

	/**
	 * @var string Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
	 */
	protected $_short_title;

	/**
	 * @var array Members plugin integration. List of capabilities to add to roles.
	 */
	protected $_capabilities = array();

	// ------------ Permissions -----------
	/**
	 * @var string|array A string or an array of capabilities or roles that have access to the settings page
	 */
	protected $_capabilities_settings_page = array();

	/**
	 * @var string|array A string or an array of capabilities or roles that have access to the form settings
	 */
	protected $_capabilities_form_settings = array();

	/**
	 * @var string|array A string or an array of capabilities or roles that can uninstall the plugin
	 */
	protected $_capabilities_uninstall = array();

	function __construct( $args ) {
		$this->_version                    = $args[ 'version' ];
		$this->_slug                       = $args[ 'plugin_slug' ];
		$this->_min_gravityforms_version   = $args[ 'min_gf_version' ];
		$this->_path                       = $args[ 'path' ];
		$this->_full_path                  = $args[ 'full_path' ];
		$this->_url                        = $args[ 'url' ];
		$this->_title                      = $args[ 'title' ];
		$this->_short_title                = $args[ 'short_title' ];
		$this->_capabilities               = $args[ 'capabilities' ];
		$this->_capabilities_settings_page = $args[ 'capabilities_settings_page' ];
		$this->_capabilities_form_settings = $args[ 'capabilities_form_settings' ];
		$this->_capabilities_uninstall     = $args[ 'capabilities_uninstall' ];

		parent::__construct();
	}

	/**
	 * Output enable ecommerce tracking instructions section
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @return string
	 */
	private function section_description_enable_ecommerce_tracking() {

		$instructions_img = trailingslashit( GFP_GOOGLE_ANALYTICS_ECOMMERCE_URL ) . 'includes/images/1-enable-ecommerce-tracking.gif';

		ob_start();

		include( trailingslashit( GFP_GOOGLE_ANALYTICS_ECOMMERCE_PATH ) . 'includes/views/plugin-settings-enable-ecommerce-tracking.php' );
		$enable_ecommerce_tracking_instructions = ob_get_contents();

		ob_end_clean();

		return $enable_ecommerce_tracking_instructions;

	}

	/**
	 * Output tracking ID section
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @return string
	 */
	private function section_description_tracking_id() {

		$tracking_id_img = trailingslashit( GFP_GOOGLE_ANALYTICS_ECOMMERCE_URL ) . 'includes/images/2-get-tracking-id.png';

		ob_start();

		include( trailingslashit( GFP_GOOGLE_ANALYTICS_ECOMMERCE_PATH ) . 'includes/views/plugin-settings-tracking-id.php' );
		$enter_tracking_id_instructions = ob_get_contents();

		ob_end_clean();

		return $enter_tracking_id_instructions;

	}

	public function plugin_settings_fields() {

		$section_enable_commerce_tracking = array(
			'title'       => __( '1. Enable Google Analytics Ecommerce Tracking', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'description' => $this->section_description_enable_ecommerce_tracking(),
			'fields'      => array()
		);

		$section_tracking_id = array(
			'title'       => __( '2. Tracking ID', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'description' => $this->section_description_tracking_id(),
			'fields'      => array(
				array(
					'name'    => 'tracking_id',
					'tooltip' => __( 'Enter your Google Analytics Tracking ID.', 'gravityplus-software-delivery' ),
					'label'   => __( 'Tracking ID', 'gravityplus-software-delivery' ),
					'type'    => 'text'
				)
			)
		);

		return array(
			$section_enable_commerce_tracking,
			$section_tracking_id
		);
	}

	public function feed_settings_fields() {

		$feed_field_name = array(
			'label'   => __( 'Name', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'type'    => 'text',
			'name'    => 'feedName',
			'tooltip' => __( 'Name for this feed', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'class'   => 'medium'
		);

		$feed_field_affiliation = array(
			'label'    => __( 'Affiliation', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'type'     => 'field_select',
			'name'     => 'affiliation_field',
			'tooltip'  => __( 'Select the field that holds the affiliation/store name', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'required' => false
		);

		$feed_field_tax = array(
			'label'    => __( 'Tax', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'type'     => 'select',
			'name'     => 'tax_field',
			'choices'  => $this->get_product_choices(),
			'tooltip'  => __( 'Select the field that holds the tax amount', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'required' => false
		);

		$feed_field_currency = array(
			'label'    => __( 'Currency Code', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'type'     => 'field_select',
			'name'     => 'currency_code_field',
			'tooltip'  => __( 'Select the field that holds the 3-letter currency code', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'required' => false
		);

		$feed_field_item = array(
			'label'    => __( 'Item', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'type'     => 'select',
			'name'     => 'item_field',
			'choices'  => $this->get_product_choices(),
			'tooltip'  => __( 'Select the field that holds the item for this transaction', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'required' => true
		);

		$feed_field_item_code = array(
			'label'    => __( 'Item Code/SKU', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'type'     => 'field_select',
			'name'     => 'item_code_field',
			'tooltip'  => __( 'Select the field that holds the item code/SKU', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'required' => false
		);

		$feed_field_item_variation = array(
			'label'    => __( 'Item Variation/Category', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'type'     => 'field_select',
			'name'     => 'item_variation_field',
			'tooltip'  => __( 'Select the field that holds the item variation/category', 'gravityplus-google-analytics-ecommerce-tracking' ),
			'required' => false
		);

		$sections = array(
			array(
				'title'  => __( 'Feed Name', 'gravityplus-google-analytics-ecommerce-tracking' ),
				'fields' => array(
					$feed_field_name
				)
			),
			array(
				'title'  => __( 'Transaction', 'gravityplus-google-analytics-ecommerce-tracking' ),
				'fields' => array(
					$feed_field_affiliation,
					$feed_field_tax,
					$feed_field_currency
				)
			),
			array(
				'title'  => __( 'Item', 'gravityplus-google-analytics-ecommerce-tracking' ),
				'fields' => array(
					$feed_field_item,
					$feed_field_item_code,
					$feed_field_item_variation
				)
			)
		);

		return $sections;
	}

	public function feed_list_columns() {

		return array(
			'feedName' => __( 'Name', 'gravityplus-google-analytics-ecommerce-tracking' )
		);

	}

	/**
	 * Get product fields for the form and format for select field
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @return array
	 */
	public function get_product_choices() {

		$form   = $this->get_current_form();
		$fields = GFCommon::get_fields_by_type( $form, array( 'product' ) );

		$choices = array(
			array( 'label' => __( 'Select a product field', 'gravityforms' ), 'value' => '' ),
		);

		foreach ( $fields as $field ) {

			$field_id    = $field[ 'id' ];
			$field_label = RGFormsModel::get_label( $field );
			$choices[ ]  = array( 'value' => $field_id, 'label' => $field_label );

		}

		return $choices;
	}


	/**
	 * Send product data to Google Analytics after form submission
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 */
	public function process_feed( $feed, $entry, $form ) {

		$settings = $this->get_plugin_settings();

		if ( empty( $settings[ 'tracking_id' ] ) ) {

			$this->log_error( __( 'Unable to send tracking data: missing UA Tracking ID.', 'gravityplus-google-analytics-ecommerce-tracking' ) );

			return;
		}

		$ecommerce_data = $this->get_order_data( $feed, $entry, $form );

		$ecommerce_data[ 'transaction' ][ 'tid' ] = $ecommerce_data[ 'item' ][ 'tid' ] = $settings[ 'tracking_id' ];

		$api             = new GFP_Google_Analytics_Ecommerce_API();
		$transaction_hit = $api->send_hit( 'transaction', $ecommerce_data[ 'transaction' ] );

		if ( 200 <= $transaction_hit && 300 > $transaction_hit ) {

			$this->log_debug( 'Transaction sent.' );

			$item_hit = $api->send_hit( 'item', $ecommerce_data[ 'item' ] );
		} else {

			$this->log_debug( 'Transaction unsuccessful.' );

		}

	}

	/**
	 * Get order data from form submission for ecommerce tracking
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @param $feed
	 * @param $entry
	 * @param $form
	 *
	 * @return array
	 */
	private function get_order_data( $feed, $entry, $form ) {

		$products = GFCommon::get_product_fields( $form, $entry );

		$product_field_id = $feed[ 'meta' ][ 'item_field' ];

		$tax_field_id = $feed[ 'meta' ][ 'tax_field' ];


		$transaction[ 'ti' ] = $item[ 'ti' ] = $entry[ 'id' ];
		$transaction[ 'ta' ] = $this->get_mapped_field_value( 'affiliation_field', $form, $entry, $feed[ 'meta' ] );

		$product = $products[ 'products' ][ $product_field_id ];

		$transaction[ 'tr' ] = GFCommon::to_number( $product[ 'price' ] );

		if ( ! empty( $products[ 'shipping' ][ 'name' ] ) ) {
			$transaction[ 'ts' ] = GFCommon::to_number( $products[ 'shipping' ][ 'price' ] );
		}

		if ( ! empty( $tax_field_id ) ) {
			$transaction[ 'tt' ] = GFCommon::to_number( $products[ 'products' ][ $tax_field_id ][ 'price' ] );
		}

		$transaction[ 'cu' ] = $item[ 'cu' ] = strtoupper( $this->get_mapped_field_value( 'currency_code_field', $form, $entry, $feed[ 'meta' ] ) );

		$item[ 'in' ] = $product[ 'name' ];
		$item[ 'iq' ] = $product[ 'quantity' ] ? (int) $product[ 'quantity' ] : 1;

		$product_price = GFCommon::to_number( $product[ 'price' ] );

		$options = array();

		if ( is_array( rgar( $product, 'options' ) ) ) {

			foreach ( $product[ 'options' ] as $option ) {

				$options[ ] = $option[ 'option_name' ];
				$product_price += $option[ 'price' ];
			}

		}

		$item[ 'ip' ] = $product_price;
		$item[ 'ic' ] = $this->get_mapped_field_value( 'item_code_field', $form, $entry, $feed[ 'meta' ] );
		$item[ 'iv' ] = $this->get_mapped_field_value( 'item_variation_field', $form, $entry, $feed[ 'meta' ] );


		$description = '';
		if ( ! empty( $options ) ) {
			$description = __( 'options: ', 'gravityforms' ) . ' ' . implode( ', ', $options );
		}

		return array( 'transaction' => array_filter( $transaction ), 'item' => array_filter( $item ) );

	}

}