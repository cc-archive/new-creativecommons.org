<?php

GFForms::include_payment_addon_framework();

/**
 * Gravity Forms Stripe Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2016, Rocketgenius
 */
class GFStripe extends GFPaymentAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @access private
	 * @var    object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Stripe Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_version Contains the version, defined from stripe.php
	 */
	protected $_version = GF_STRIPE_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '1.9.14.17';

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsstripe';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsstripe/stripe.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'http://www.gravityforms.com';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_title The title of the Add-On.
	 */
	protected $_title = 'Gravity Forms Stripe Add-On';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Stripe';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines if user will not be able to create feeds for a form until a credit card field has been added.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_requires_credit_card = true;

	/**
	 * Defines if callbacks/webhooks/IPN will be enabled and the appropriate database table will be created.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_supports_callbacks = true;

	/**
	 * Stripe requires monetary amounts to be formatted as the smallest unit for the currency being used e.g. cents.
	 *
	 * @var bool
	 */
	protected $_requires_smallest_unit = true;

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_stripe';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_stripe';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_stripe_uninstall';

	/**
	 * Defines the capabilities needed for the Stripe Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_stripe', 'gravityforms_stripe_uninstall' );

	/**
	 * Get an instance of this class.
	 *
	 * @return GFStripe
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new GFStripe();
		}

		return self::$_instance;

	}

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {

		$scripts = array(
			array(
				'handle'  => 'stripe.js',
				'src'     => 'https://js.stripe.com/v2/',
				'version' => $this->_version,
				'deps'    => array(),
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => array( $this->_slug, $this->get_short_title() ),
					),
				),
			),
			array(
				'handle'    => 'gforms_stripe_frontend',
				'src'       => $this->get_base_url() . '/js/frontend.js',
				'version'   => $this->_version,
				'deps'      => array( 'jquery', 'stripe.js', 'gform_json' ),
				'in_footer' => false,
				'enqueue'   => array(
					array( $this, 'frontend_script_callback' ),
				),
			),
			array(
				'handle'    => 'gforms_stripe_admin',
				'src'       => $this->get_base_url() . '/js/admin.js',
				'version'   => $this->_version,
				'deps'      => array( 'jquery' ),
				'in_footer' => false,
				'enqueue'   => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => array( $this->_slug, $this->get_short_title() ),
					),
				),
				'strings'   => array(
					'spinner'          => GFCommon::get_base_url() . '/images/spinner.gif',
					'validation_error' => esc_html__( 'Error validating this key. Please try again later.', 'gravityformsstripe' ),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );

	}





	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Initialize the AJAX hooks.
	 */
	public function init_ajax() {

		parent::init_ajax();

		add_action( 'wp_ajax_gf_validate_secret_key', array( $this, 'ajax_validate_secret_key' ) );
	}

	/**
	 * Handler for the gf_validate_secret_key AJAX request.
	 */
	public function ajax_validate_secret_key() {

		// Get the API key name.
		$key_name = rgpost( 'keyName' );

		// If no cache or if new value provided, do a fresh validation.
		$this->include_stripe_api();
		\Stripe\Stripe::setApiKey( rgpost( 'key' ) );

		// Initialize validatity state.
		$is_valid = true;

		try {

			// Attempt to retrieve account details.
			\Stripe\Account::retrieve();

		} catch ( \Stripe\Error\Authentication $e ) {

			// Set validity state to false.
			$is_valid = false;

			// Log that key validation failed.
			$this->log_error( __METHOD__ . "(): {$key_name}: " . $e->getMessage() );

		}

		// Prepare response.
		$response = $is_valid ? 'valid' : 'invalid';

		// Send API key validation response.
		die( $response );

	}

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		return array(
			array(
				'title'  => esc_html__( 'Stripe API', 'gravityformsstripe' ),
				'fields' => $this->api_settings_fields(),
			),
			array(
				'title'       => esc_html__( 'Stripe Webhooks', 'gravityformsstripe' ),
				'description' => $this->get_webhooks_section_description(),
				'fields'      => array(
					array(
						'name'       => 'webhooks_enabled',
						'label'      => esc_html__( 'Webhooks Enabled?', 'gravityformsstripe' ),
						'type'       => 'checkbox',
						'horizontal' => true,
						'required'   => 1,
						'choices'    => array(
							array(
								'label' => esc_html__( 'I have enabled the Gravity Forms webhook URL in my Stripe account.', 'gravityformsstripe' ),
								'value' => 1,
								'name'  => 'webhooks_enabled',
							),
						),
					),
					array(
						'type'     => 'save',
						'messages' => array( 'success' => esc_html__( 'Settings updated successfully', 'gravityformsstripe' ) ),
					),
				),
			),
		);

	}

	/**
	 * Define the settings which appear in the Stripe API section.
	 *
	 * @return array
	 */
	public function api_settings_fields() {

		return array(
			array(
				'name'          => 'api_mode',
				'label'         => esc_html__( 'API', 'gravityformsstripe' ),
				'type'          => 'radio',
				'default_value' => 'live',
				'choices'       => array(
					array(
						'label' => esc_html__( 'Live', 'gravityformsstripe' ),
						'value' => 'live',
					),
					array(
						'label'    => esc_html__( 'Test', 'gravityformsstripe' ),
						'value'    => 'test',
						'selected' => true,
					),
				),
				'horizontal'    => true,
			),
			array(
				'name'     => 'test_secret_key',
				'label'    => esc_html__( 'Test Secret Key', 'gravityformsstripe' ),
				'type'     => 'text',
				'class'    => 'medium',
				'onchange' => "GFStripeAdmin.validateKey('test_secret_key', this.value);",
			),
			array(
				'name'     => 'test_publishable_key',
				'label'    => esc_html__( 'Test Publishable Key', 'gravityformsstripe' ),
				'type'     => 'text',
				'class'    => 'medium',
				'onchange' => "GFStripeAdmin.validateKey('test_publishable_key', this.value);",
			),
			array(
				'name'     => 'live_secret_key',
				'label'    => esc_html__( 'Live Secret Key', 'gravityformsstripe' ),
				'type'     => 'text',
				'class'    => 'medium',
				'onchange' => "GFStripeAdmin.validateKey('live_secret_key', this.value);",
			),
			array(
				'name'     => 'live_publishable_key',
				'label'    => esc_html__( 'Live Publishable Key', 'gravityformsstripe' ),
				'type'     => 'text',
				'class'    => 'medium',
				'onchange' => "GFStripeAdmin.validateKey('live_publishable_key', this.value);",
			),
			array(
				'label' => 'hidden',
				'name'  => 'live_publishable_key_is_valid',
				'type'  => 'hidden',
			),
			array(
				'label' => 'hidden',
				'name'  => 'live_secret_key_is_valid',
				'type'  => 'hidden',
			),
			array(
				'label' => 'hidden',
				'name'  => 'test_publishable_key_is_valid',
				'type'  => 'hidden',
			),
			array(
				'label' => 'hidden',
				'name'  => 'test_secret_key_is_valid',
				'type'  => 'hidden',
			),
		);

	}

	/**
	 * Define the markup to be displayed for the webhooks section description.
	 *
	 * @return string
	 */
	public function get_webhooks_section_description() {
		ob_start();
		?>

		<?php esc_html_e( 'Gravity Forms requires the following URL to be added to your Stripe account\'s list of Webhooks.', 'gravityformsstripe' ); ?>
		<a href="javascript:return false;"
		   onclick="jQuery('#stripe-webhooks-instructions').slideToggle();"><?php esc_html_e( 'View Instructions', 'gravityformsstripe' ); ?></a>

		<div id="stripe-webhooks-instructions" style="display:none;">

			<ol>
				<li>
					<?php esc_html_e( 'Click the following link and log in to access your Stripe Webhooks management page:', 'gravityformsstripe' ); ?>
					<br/>
					<a href="https://dashboard.stripe.com/account/webhooks" target="_blank">https://dashboard.stripe.com/account/webhooks</a>
				</li>
				<li><?php esc_html_e( 'Click the "Add Endpoint" button above the list of Webhook URLs.', 'gravityformsstripe' ); ?></li>
				<li>
					<?php esc_html_e( 'Enter the following URL in the "URL" field:', 'gravityformsstripe' ); ?>
					<code><?php echo $this->get_webhook_url(); ?></code>
				</li>
				<li><?php esc_html_e( 'Select "Live" from the "Mode" drop down.', 'gravityformsstripe' ); ?></li>
				<li><?php esc_html_e( 'Click the "Create Endpoint" button.', 'gravityformsstripe' ); ?></li>
			</ol>

		</div>

		<?php
		return ob_get_clean();
	}





	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @return array The feed settings.
	 */
	public function feed_settings_fields() {

		// Get default payment feed settings fields.
		$default_settings = parent::feed_settings_fields();

		// Prepare customer information fields.
		$customer_info_field = array(
			'name'       => 'customerInformation',
			'label'      => esc_html__( 'Customer Information', 'gravityformsstripe' ),
			'type'       => 'field_map',
			'dependency' => array(
				'field'  => 'transactionType',
				'values' => array( 'subscription' ),
			),
			'field_map'  => array(
				array(
					'name'       => 'email',
					'label'      => esc_html__( 'Email', 'gravityformsstripe' ),
					'required'   => true,
					'field_type' => array( 'email', 'hidden' ),
				),
				array(
					'name'     => 'description',
					'label'    => esc_html__( 'Description', 'gravityformsstripe' ),
					'required' => false,
				),
				array(
					'name'       => 'coupon',
					'label'      => esc_html__( 'Coupon', 'gravityformsstripe' ),
					'required'   => false,
					'field_type' => array( 'coupon', 'text' ),
					'tooltip'    => '<h6>' . esc_html__( 'Coupon', 'gravityformsstripe' ) . '</h6>' . esc_html__( 'Select which field contains the coupon code to be applied to the recurring charge(s). The coupon must also exist in your Stripe Dashboard.', 'gravityformsstripe' ),
				),
			),
		);

		// Replace default billing information fields with customer information fields.
		$default_settings = $this->replace_field( 'billingInformation', $customer_info_field, $default_settings );

		// Define end of Metadata tooltip based on transaction type.
		if ( 'subscription' === $this->get_setting( 'transactionType' ) ) {
			$info = esc_html__( 'You will see this data when viewing a customer page.', 'gravityformsstripe' );
		} else {
			$info = esc_html__( 'You will see this data when viewing a payment page.', 'gravityformsstripe' );
		}

		// Prepare meta data field.
		$custom_meta = array(
			array(
				'name'                => 'metaData',
				'label'               => esc_html__( 'Metadata', 'gravityformsstripe' ),
				'type'                => 'dynamic_field_map',
				'limit'				  => 20,
				'exclude_field_types' => 'creditcard',
				'tooltip'             => '<h6>' . esc_html__( 'Metadata', 'gravityformsstripe' ) . '</h6>' . esc_html__( 'You may send custom meta information to Stripe. A maximum of 20 custom keys may be sent. The key name must be 40 characters or less, and the mapped data will be truncated to 500 characters per requirements by Stripe. ' . $info , 'gravityformsstripe' ),
				'validation_callback' => array( $this, 'validate_custom_meta' ),
			),
		);

		// Add meta data field.
		$default_settings = $this->add_field_after( 'customerInformation', $custom_meta, $default_settings );

		// Remove subscription recurring times setting due to lack of Stripe support.
		$default_settings = $this->remove_field( 'recurringTimes', $default_settings );

		// Prepare trial period field.
		$trial_period_field = array(
			'name'                => 'trialPeriod',
			'label'               => esc_html__( 'Trial Period', 'gravityformsstripe' ),
			'style'               => 'width:40px;text-align:center;',
			'type'                => 'trial_period',
			'after_input'         => '&nbsp;' . esc_html__( 'days', 'gravityformsstripe' ),
			'validation_callback' => array( $this, 'validate_trial_period' ),
		);

		// Add trial period field.
		$default_settings = $this->add_field_after( 'trial', $trial_period_field, $default_settings );

		// Add receipt field if the feed transaction type is a product.
		if ( 'product' === $this->get_setting( 'transactionType' ) ) {

			$receipt_settings = array(
				'name'    => 'receipt',
				'label'   => 'Stripe Receipt',
				'type'    => 'receipt',
				'tooltip' => '<h6>' . esc_html__( 'Stripe Receipt', 'gravityformsstripe' ) . '</h6>' . esc_html__( 'Stripe can send a receipt via email upon payment. Select an email field to enable this feature.', 'gravityformsstripe' ),
			);

			$default_settings = $this->add_field_before( 'conditionalLogic', $receipt_settings, $default_settings );

		}

		return $default_settings;

	}

	/**
	 * Prevent feeds being listed or created if the API keys aren't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		// Get plugin settings and API mode.
		$settings = $this->get_plugin_settings();
		$api_mode = $this->get_api_mode( $settings );

		// Return valid key state based on API mode.
		if ( 'live' === $api_mode ) {
			return rgar( $settings, 'live_publishable_key_is_valid' ) && rgar( $settings, 'live_secret_key_is_valid' ) && $this->is_webhook_enabled();
		} else {
			return rgar( $settings, 'test_publishable_key_is_valid' ) && rgar( $settings, 'test_secret_key_is_valid' ) && $this->is_webhook_enabled();
		}

	}

	/**
	 * Enable feed duplication on feed list page and during form duplication.
	 *
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {

		return false;

	}

	/**
	 * Define the markup for the field_map setting table header.
	 *
	 * @return string
	 */
	public function field_map_table_header() {
		return '<thead>
					<tr>
						<th></th>
						<th></th>
					</tr>
				</thead>';
	}

	/**
	 * Define the markup for the receipt type field.
	 *
	 * @param array     $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_receipt( $field, $echo = true ) {

		// Prepare first field choice and get form fields as choices.
		$first_choice = array( 'label' => esc_html__( 'Do not send receipt', 'gravityformsstripe' ), 'value' => '' );
		$fields       = $this->get_form_fields_as_choices( $this->get_current_form(), array( 'input_types' => array( 'email', 'hidden' ) ) );

		// Add first choice to the beginning of the fields array.
		array_unshift( $fields, $first_choice );

		// Prepare select field settings.
		$select = array(
			'name'    => 'receipt_field',
			'choices' => $fields,
		);

		// Get select markup.
		$html = $this->settings_select( $select, false );

		// Echo setting markup, if enabled.
		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Define the markup for the setup_fee type field.
	 *
	 * @param array     $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_setup_fee( $field, $echo = true ) {

		// Prepare checkbox field settings.
		$enabled_field = array(
			'name'       => $field['name'] . '_checkbox',
			'type'       => 'checkbox',
			'horizontal' => true,
			'choices'    => array(
				array(
					'label'    => esc_html__( 'Enabled', 'gravityformsstripe' ),
					'name'     => $field['name'] . '_enabled',
					'value'    => '1',
					'onchange' => "if(jQuery(this).prop('checked')){
						jQuery('#{$field['name']}_product').show('slow');
						jQuery('#gaddon-setting-row-trial, #gaddon-setting-row-trialPeriod').hide('slow');
						jQuery('#trial_enabled').prop( 'checked', false );
						jQuery('#trialPeriod').val( '' );
					} else {
						jQuery('#{$field['name']}_product').hide('slow');
						jQuery('#gaddon-setting-row-trial').show('slow');
					}",
				),
			),
		);

		// Get checkbox field markup.
		$html = $this->settings_checkbox( $enabled_field, false );

		// Get current form.
		$form = $this->get_current_form();

		// Get enabled state.
		$is_enabled = $this->get_setting( "{$field['name']}_enabled" );

		// Prepare setup fee select field settings.
		$product_field = array(
			'name'    => $field['name'] . '_product',
			'type'    => 'select',
			'class'   => $is_enabled ? '' : 'hidden',
			'choices' => $this->get_payment_choices( $form ),
		);

		// Add select field markup to checkbox field markup.
		$html .= '&nbsp' . $this->settings_select( $product_field, false );

		// Echo setting markup, if enabled.
		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Define the markup for the trial type field.
	 *
	 * @param array     $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_trial( $field, $echo = true ) {

		// Prepare enabled field settings.
		$enabled_field = array(
			'name'       => $field['name'] . '_checkbox',
			'type'       => 'checkbox',
			'horizontal' => true,
			'choices'    => array(
				array(
					'label'    => esc_html__( 'Enabled', 'gravityformsstripe' ),
					'name'     => $field['name'] . '_enabled',
					'value'    => '1',
					'onchange' => "if(jQuery(this).prop('checked')){
						jQuery('#gaddon-setting-row-trialPeriod').show('slow');
					} else {
						jQuery('#gaddon-setting-row-trialPeriod').hide('slow');
						jQuery('#trialPeriod').val( '' );
					}",
				),
			),
		);

		// Get checkbox markup.
		$html = $this->settings_checkbox( $enabled_field, false );

		// Echo setting markup, if enabled.
		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Define the markup for the trial_period type field.
	 *
	 * @param array     $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_trial_period( $field, $echo = true ) {

		// Get text input markup.
		$html = $this->settings_text( $field, false );

		// Prepare validation placeholder name.
		$validation_placeholder = array( 'name' => 'trialValidationPlaceholder' );

		// Add validation indicator.
		if ( $this->field_failed_validation( $validation_placeholder ) ) {
			$html .= '&nbsp;' . $this->get_error_icon( $validation_placeholder );
		}

		// If trial is not enabled and setup fee is enabled, hide field.
		$html .= '
			<script type="text/javascript">
			if( ! jQuery( "#trial_enabled" ).is( ":checked" ) || jQuery( "#setupFee_enabled" ).is( ":checked" ) ) {
				jQuery( "#trial_enabled" ).prop( "checked", false );
				jQuery( "#gaddon-setting-row-trialPeriod" ).hide();
			}
			</script>';

		// Echo setting markup, if enabled.
		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Validate the trial_period type field.
	 *
	 * @param array $field The field properties.
	 */
	public function validate_trial_period( $field ) {

		// Get posted settings.
		$settings = $this->get_posted_settings();

		// If trial period is not numeric, set field error.
		if ( $settings['trial_enabled'] && ( empty( $settings['trialPeriod'] ) || ! ctype_digit( $settings['trialPeriod'] ) ) ) {
			$this->set_field_error( array( 'name' => 'trialValidationPlaceholder' ), esc_html__( 'Please enter a valid number of days.', 'gravityformsstripe' ) );
		}

	}

	/**
	 * Validate the custom_meta type field.
	 *
	 * @param array $field The field properties.
	 */
	public function validate_custom_meta( $field ) {

		/*
		 * Number of keys is limited to 20.
		 * Interface should control this, validating just in case.
		 * Key names have maximum length of 40 characters.
		 */

		// Get metadata from posted settings.
		$settings  = $this->get_posted_settings();
		$meta_data = $settings['metaData'];

		// If metadata is not defined, return.
		if ( empty( $meta_data ) ) {
			return;
		}

		// Get number of metadata items.
		$meta_count = count( $meta_data );

		// If there are more than 20 metadata keys, set field error.
		if ( $meta_count > 20 ) {
			$this->set_field_error( array( esc_html__( 'You may only have 20 custom keys.' ), 'gravityformsstripe' ) );
			return;
		}

		// Loop through metadata and check the key name length (custom_key).
		foreach ( $meta_data as $meta ) {
			if ( empty( $meta['custom_key'] ) && ! empty( $meta['value'] ) ) {
				$this->set_field_error( array( 'name' => 'metaData' ), esc_html__( "A field has been mapped to a custom key without a name. Please enter a name for the custom key, remove the metadata item, or return the corresponding drop down to 'Select a Field'.", 'gravityformsstripe' ) );
				break;
			} else if ( strlen( $meta['custom_key'] ) > 40 ) {
				$this->set_field_error( array( 'name' => 'metaData' ), sprintf( esc_html__( 'The name of custom key %s is too long. Please shorten this to 40 characters or less.', 'gravityformsstripe' ), $meta['custom_key'] ) );
				break;
			}
		}

	}

	/**
	 * Define the choices available in the billing cycle dropdowns.
	 *
	 * @return array
	 */
	public function supported_billing_intervals() {

		return array(
			'day'   => array( 'label' => esc_html__( 'day(s)', 'gravityformsstripe' ),   'min' => 1, 'max' => 365 ),
			'week'  => array( 'label' => esc_html__( 'week(s)', 'gravityformsstripe' ),  'min' => 1, 'max' => 12 ),
			'month' => array( 'label' => esc_html__( 'month(s)', 'gravityformsstripe' ), 'min' => 1, 'max' => 12 ),
			'year'  => array( 'label' => esc_html__( 'year(s)', 'gravityformsstripe' ),  'min' => 1, 'max' => 1 ),
		);

	}

	/**
	 * Prevent the 'options' checkboxes setting being included on the feed.
	 *
	 * @return bool
	 */
	public function option_choices() {
		return false;
	}



	// # FORM SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Add supported notification events.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array
	 */
	public function supported_notification_events( $form ) {

		// If this form does not have a Stripe feed, return false.
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		// Return Stripe notification events.
		return array(
			'complete_payment'          => esc_html__( 'Payment Completed', 'gravityformsstripe' ),
			'refund_payment'            => esc_html__( 'Payment Refunded', 'gravityformsstripe' ),
			'fail_payment'              => esc_html__( 'Payment Failed', 'gravityformsstripe' ),
			'create_subscription'       => esc_html__( 'Subscription Created', 'gravityformsstripe' ),
			'cancel_subscription'       => esc_html__( 'Subscription Canceled', 'gravityformsstripe' ),
			'add_subscription_payment'  => esc_html__( 'Subscription Payment Added', 'gravityformsstripe' ),
			'fail_subscription_payment' => esc_html__( 'Subscription Payment Failed', 'gravityformsstripe' ),
		);

	}





	// # FRONTEND ------------------------------------------------------------------------------------------------------

	/**
	 * Initialize the frontend hooks.
	 */
	public function init() {

		add_filter( 'gform_register_init_scripts', array( $this, 'register_init_scripts' ), 10, 3 );
		add_filter( 'gform_field_content', array( $this, 'add_stripe_inputs' ), 10, 5 );
		add_filter( 'gform_field_validation', array( $this, 'pre_validation' ), 10, 4 );
		add_filter( 'gform_pre_submission', array( $this, 'populate_credit_card_last_four' ) );

		parent::init();

	}

	/**
	 * Register Stripe script when displaying form.
	 *
	 * @param array $form Form object.
	 * @param array $field_values Current field values.
	 * @param bool  $is_ajax If form is being submitted via AJAX.
	 */
	public function register_init_scripts( $form, $field_values, $is_ajax ) {

		// If form does not have a Stripe feed and does not have a credit card field, exit.
		if ( ! $this->has_feed( $form['id'] ) ) {
			return;
		}

		$cc_field = $this->get_credit_card_field( $form );

		if ( ! $cc_field ) {
			return;
		}

		// Prepare Stripe Javascript arguments.
		$args = array(
			'apiKey'     => $this->get_publishable_api_key(),
			'formId'     => $form['id'],
			'ccFieldId'  => $cc_field->id,
			'ccPage'     => $cc_field->pageNumber,
			'isAjax'     => $is_ajax,
			'cardLabels' => $this->get_card_labels(),
		);

		// Initialize Stripe script.
		$script = 'new GFStripe( ' . json_encode( $args ) . ' );';

		// Add Stripe script to form scripts.
		GFFormDisplay::add_init_script( $form['id'], 'stripe', GFFormDisplay::ON_PAGE_RENDER, $script );

	}

	/**
	 * Check if the form has an active Stripe feed and a credit card field.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return bool
	 */
	public function frontend_script_callback( $form ) {

		return $form && $this->has_feed( $form['id'] ) && $this->has_credit_card_field( $form );

	}

	/**
	 * Add required Stripe inputs to form.
	 *
	 * @param string  $content The field content to be filtered.
	 * @param object  $field The field that this input tag applies to.
	 * @param string  $value The default/initial value that the field should be pre-populated with.
	 * @param integer $lead_id When executed from the entry detail screen, $lead_id will be populated with the Entry ID.
	 * @param integer $form_id The current Form ID.
	 *
	 * @return string
	 */
	public function add_stripe_inputs( $content, $field, $value, $lead_id, $form_id ) {

		// If this form does not have a Stripe feed or if this is not a credit card field, return field content.
		if ( ! $this->has_feed( $form_id ) || 'creditcard' !== $field->get_input_type() ) {
			return $content;
		}

		// If a Stripe response exists, populate it to a hidden field.
		if ( $this->get_stripe_js_response() ) {
			$content .= '<input type=\'hidden\' name=\'stripe_response\' id=\'gf_stripe_response\' value=\'' . rgpost( 'stripe_response' ) . '\' />';
		}

		// If the last four credit card digits are provided by Stripe, populate it to a hidden field.
		if ( rgpost( 'stripe_credit_card_last_four' ) ) {
			$content .= '<input type="hidden" name="stripe_credit_card_last_four" id="gf_stripe_credit_card_last_four" value="' . rgpost( 'stripe_credit_card_last_four' ) . '" />';
		}

		// If the  credit card type is provided by Stripe, populate it to a hidden field.
		if ( rgpost( 'stripe_credit_card_type' ) ) {
			$content .= '<input type="hidden" name="stripe_credit_card_type" id="stripe_credit_card_type" value="' . rgpost( 'stripe_credit_card_type' ) . '" />';
		}

		// Remove name attribute from credit card field inputs for security.
		// Removes: name='input_2.1', name='input_2.2[]', name='input_2.3', name='input_2.5', where 2 is the credit card field id.
		$content = preg_replace( "/name=\'input_{$field->id}\.([135]|2\[\])\'/", '', $content );

		return $content;

	}

	/**
	 * Validate the card type and prevent the field from failing required validation, Stripe.js will handle the required validation.
	 *
	 * The card field inputs are erased on submit, this will cause two issues:
	 * 1. The field will fail standard validation if marked as required.
	 * 2. The card type validation will not be performed.
	 *
	 * @param array    $result The field validation result and message.
	 * @param mixed    $value The field input values; empty for the credit card field as they are cleared by frontend.js.
	 * @param array    $form The Form currently being processed.
	 * @param GF_Field $field The field currently being processed.
	 *
	 * @return array
	 */
	public function pre_validation( $result, $value, $form, $field ) {

		// If this is a credit card field and the last four credit card digits are defined, validate.
		if ( $field->type == 'creditcard' && rgpost( 'stripe_credit_card_last_four' ) ) {

			// Get card slug.
			$card_type = rgpost( 'stripe_credit_card_type' );
			$card_slug = $this->get_card_slug( $card_type );

			// If credit card type is not supported, mark field as invalid.
			if ( ! $field->is_card_supported( $card_slug ) ) {
				$result['is_valid'] = false;
				$result['message']  = $card_type . ' ' . esc_html__( 'is not supported. Please enter one of the supported credit cards.', 'gravityforms' );
			} else {
				$result['is_valid'] = true;
				$result['message']  = '';
			}

		}

		return $result;

	}





	// # STRIPE TRANSACTIONS -------------------------------------------------------------------------------------------

	/**
	 * Initialize authorizing the transaction for the product & services type feed or return the Stripe.js error.
	 *
	 * @param array $feed The feed object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $form The form object currently being processed.
	 * @param array $entry The entry object currently being processed.
	 *
	 * @return array
	 */
	public function authorize( $feed, $submission_data, $form, $entry ) {

		// Include Stripe API library.
		$this->include_stripe_api();

		// If there was an error when retrieving the Stripe.js token, return an authorization error.
		if ( $this->get_stripe_js_error() ) {
			return $this->authorization_error( $this->get_stripe_js_error() );
		}

		// Authorize product.
		return $this->authorize_product( $feed, $submission_data, $form, $entry );

	}

	/**
	 * Create the Stripe charge authorization and return any authorization errors which occur.
	 *
	 * @param array $feed The feed object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $form The form object currently being processed.
	 * @param array $entry The entry object currently being processed.
	 *
	 * @return array
	 */
	public function authorize_product( $feed, $submission_data, $form, $entry ) {

		try {

			// Get Stripe.js token.
			$stripe_response = $this->get_stripe_js_response();

			// Prepare Stripe charge meta.
			$charge_meta = array(
				'amount'      => $this->get_amount_export( $submission_data['payment_amount'], rgar( $entry, 'currency' ) ),
				'currency'    => rgar( $entry, 'currency' ),
				'description' => $this->get_payment_description( $entry, $submission_data, $feed ),
				'capture'     => false,
			);

			$customer = $this->get_customer( '', $feed, $entry, $form );

			if ( $customer ) {
				// Update the customer source with the Stripe token.
				$customer->source = $stripe_response->id;
				$customer->save();

				// Add the customer id to the charge meta.
				$charge_meta['customer'] = $customer->id;
			} else {
				// Add the Stripe token to the charge meta.
				$charge_meta['source'] = $stripe_response->id;
			}

			// If receipt field is defined, add receipt email address to charge meta.
			$receipt_field = rgars( $feed, 'meta/receipt_field' );
			if ( ! empty( $receipt_field ) && strtolower( $receipt_field ) !== 'do not send receipt' ) {
				$charge_meta['receipt_email'] = $this->get_field_value( $form, $entry, $receipt_field );
			}

			// Get Stripe metadata for feed.
			$metadata = $this->get_stripe_meta_data( $feed, $entry, $form );

			// If metadata was defined, add it to charge meta.
			if ( ! empty( $metadata ) ) {
				$charge_meta['metadata'] = $metadata;
			}

			// Log the charge we're about to process.
			$this->log_debug( __METHOD__ . '(): Charge meta to be created => ' . print_r( $charge_meta, 1 ) );

			// Charge customer.
			$charge = \Stripe\Charge::create( $charge_meta );

			// Get authorization data from charge.
			$auth = array(
				'is_authorized'  => true,
				'transaction_id' => $charge['id'],
			);

		} catch ( \Exception $e ) {

			// Set authorization data to error.
			$auth = $this->authorization_error( $e->getMessage() );

		}

		return $auth;

	}

	/**
	 * Handle cancelling the subscription from the entry detail page.
	 *
	 * @param array $entry The entry object currently being processed.
	 * @param array $feed The feed object currently being processed.
	 *
	 * @return bool
	 */
	public function cancel( $entry, $feed ) {

		// Include Stripe API library.
		$this->include_stripe_api();

		try {

			// Get customer ID.
			$customer_id = gform_get_meta( $entry['id'], 'stripe_customer_id' );

			if ( ! $customer_id ) {
				return false;
			}

			// Get Stripe Customer object.
			$customer = $this->get_customer( $customer_id );

			if ( ! $customer ) {
				return false;
			}

			$params = array();

			/**
			 * Allow the cancellation of the subscription to be delayed until the end of the current period.
			 *
			 * @param bool $at_period_end Defaults to false, subscription will be cancelled immediately.
			 * @param array $entry The entry from which the subscription was created.
			 * @param array $feed The feed object which processed the current entry.
			 *
			 * @since 2.1.0
			 */
			$params['at_period_end'] = apply_filters( 'gform_stripe_subscription_cancel_at_period_end', false, $entry, $feed );

			if ( $params['at_period_end'] ) {
				$this->log_debug( __METHOD__ . '(): The gform_stripe_subscription_cancel_at_period_end filter was used; cancelling subscription at period end.' );
			}

			// Cancel customer subscription.
			$customer->cancelSubscription( $params );

			return true;

		} catch ( \Exception $e ) {

			// Log error.
			$this->log_error( __METHOD__ . '(): Unable to cancel subscription; ' . $e->getMessage() );

			return false;

		}

	}

	/**
	 * Capture the Stripe charge which was authorized during validation.
	 *
	 * @param array $auth Contains the result of the authorize() function.
	 * @param array $feed The feed object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $form The form object currently being processed.
	 * @param array $entry The entry object currently being processed.
	 *
	 * @return array
	 */
	public function capture( $auth, $feed, $submission_data, $form, $entry ) {

		// Get Stripe charge from authorization.
		$charge = \Stripe\Charge::retrieve( $auth['transaction_id'] );

		try {

			// Set charge description and metadata.
			$charge->description = $this->get_payment_description( $entry, $submission_data, $feed );

			$metadata = $this->get_stripe_meta_data( $feed, $entry, $form );
			if ( ! empty( $metadata ) ) {
				$charge->metadata = $metadata;
			}

			// Save charge.
			$charge->save();

			/**
			 * Allow authorization only transactions by preventing the capture request from being made after the entry has been saved.
			 *
			 * @param bool $authorization_only Defaults to false, return true to prevent payment being captured.
			 * @param array $feed The feed object currently being processed.
			 * @param array $submission_data The customer and transaction data.
			 * @param array $form The form object currently being processed.
			 * @param array $entry The entry object currently being processed.
			 *
			 * @since 2.1.0
			 */
			$authorization_only = apply_filters( 'gform_stripe_charge_authorization_only', false, $feed, $submission_data, $form, $entry );

			if ( $authorization_only ) {
				$this->log_debug( __METHOD__ . '(): The gform_stripe_charge_authorization_only filter was used to prevent capture.' );

				return array();
			}

			// Capture the charge.
			$charge = $charge->capture();

			// Prepare payment details.
			$payment = array(
				'is_success'     => true,
				'transaction_id' => $charge->id,
				'amount'         => $this->get_amount_import( $charge->amount, $entry['currency'] ),
				'payment_method' => rgpost( 'stripe_credit_card_type' ),
			);

		} catch ( \Exception $e ) {

			// Log that charge could not be captured.
			$this->log_error( __METHOD__ . '(): Unable to capture charge; ' . $e->getMessage() );

			// Prepare payment details.
			$payment = array(
				'is_success'    => false,
				'error_message' => $e->getMessage(),
			);

		}

		return $payment;
	}

	/**
	 * Update the entry meta with the Stripe Customer ID.
	 *
	 * @param array $authorization Contains the result of the subscribe() function.
	 * @param array $feed The feed object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $form The form object currently being processed.
	 * @param array $entry The entry object currently being processed.
	 *
	 * @return array The entry object.
	 */
	public function process_subscription( $authorization, $feed, $submission_data, $form, $entry ) {

		// Update customer ID for entry.
		gform_update_meta( $entry['id'], 'stripe_customer_id', $authorization['subscription']['customer_id'] );

		$metadata = $this->get_stripe_meta_data( $feed, $entry, $form );
		if ( ! empty( $metadata ) ) {

			// Update to user meta post entry creation so entry ID is available.
			try {

				// Get customer.
				$customer = $this->get_customer( $authorization['subscription']['customer_id'] );

				// Update customer metadata.
				$customer->metadata = $metadata;

				// Save customer.
				$customer->save();

			} catch ( \Exception $e ) {

				// Log that we could not save customer.
				$this->log_error( __METHOD__ . '(): Unable to save customer; ' . $e->getMessage() );

			}

		}

		return parent::process_subscription( $authorization, $feed, $submission_data, $form, $entry );

	}

	/**
	 * Subscribe the user to a Stripe plan. This process works like so:
	 *
	 * 1 - Get existing plan or create new plan (plan ID generated by feed name, id and recurring amount).
	 * 2 - Create new customer.
	 * 3 - Create new subscription by subscribing customer to plan.
	 *
	 * @param array $feed The feed object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $form The form object currently being processed.
	 * @param array $entry The entry object currently being processed.
	 *
	 * @return array
	 */
	public function subscribe( $feed, $submission_data, $form, $entry ) {

		// Include Stripe API library.
		$this->include_stripe_api();

		// If there was an error when retrieving the Stripe.js token, return an authorization error.
		if ( $this->get_stripe_js_error() ) {
			return $this->authorization_error( $this->get_stripe_js_error() );
		}

		// Prepare payment amount and trial period data.
		$payment_amount        = $submission_data['payment_amount'];
		$single_payment_amount = $submission_data['setup_fee'];
		$trial_period_days     = rgars( $feed, 'meta/trialPeriod' ) ? $submission_data['trial'] : null;
		$currency              = rgar( $entry, 'currency' );

		// Get Stripe plan for feed.
		$plan_id = $this->get_subscription_plan_id( $feed, $payment_amount, $trial_period_days );
		$plan    = $this->get_plan( $plan_id );

		// If error was returned when retrieving plan, return plan.
		if ( rgar( $plan, 'error_message' ) ) {
			return $plan;
		}

		try {

			// If plan does not exist, create it.
			if ( ! $plan ) {
				$plan = $this->create_plan( $plan_id, $feed, $payment_amount, $trial_period_days, $currency );
			}

			// Get Stripe.js token.
			$stripe_response = $this->get_stripe_js_response();

			$customer = $this->get_customer( '', $feed, $entry, $form );

			if ( $customer ) {

				$this->log_debug( __METHOD__ . '(): Updating existing customer.' );

				// Update the customer source with the Stripe token.
				$customer->source = $stripe_response->id;
				$customer->save();

				// If a setup fee is required, add an invoice item.
				if ( $single_payment_amount ) {
					$setup_fee = array(
						'amount'   => $this->get_amount_export( $single_payment_amount, $currency ),
						'currency' => $currency,
					);
					$customer->addInvoiceItem( $setup_fee );
				}

				// Add subscription to customer.
				$subscription = $customer->updateSubscription( array( 'plan' => $plan->id ) );

				// Define subscription ID.
				$subscription_id = $subscription->id;

			} else {

				// Prepare customer metadata.
				$customer_meta = array(
					'description'     => $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'customerInformation_description' ) ),
					'email'           => $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'customerInformation_email' ) ),
					'source'          => $stripe_response->id,
					'account_balance' => $this->get_amount_export( $single_payment_amount, $currency ),
					'metadata'        => $this->get_stripe_meta_data( $feed, $entry, $form ),
				);

				// Get coupon for feed.
				$coupon_field_id = rgar( $feed['meta'], 'customerInformation_coupon' );
				$coupon          = $this->maybe_override_field_value( rgar( $entry, $coupon_field_id ), $form, $entry, $coupon_field_id );

				// If coupon is set, add it to customer metadata.
				if ( $coupon ) {
					$customer_meta['coupon'] = $coupon;
				}

				$has_filter = has_filter( 'gform_stripe_customer_after_create' );

				if ( ! $has_filter ) {
					// If filter is not being used add the plan to customer metadata; resolves a currency issue.
					$customer_meta['plan'] = $plan;
				}

				$customer = $this->create_customer( $customer_meta, $feed, $entry, $form );

				if ( $has_filter ) {
					// Add subscription to customer.
					$subscription = $customer->updateSubscription( array( 'plan' => $plan->id ) );

					// Define subscription ID.
					$subscription_id = $subscription->id;
				} else {
					// Define subscription ID.
					$subscription_id = $customer->subscriptions->data[0]->id;
				}

			}

		} catch ( \Exception $e ) {

			// Return authorization error.
			return $this->authorization_error( $e->getMessage() );

		}

		// Return subscription data.
		return array(
			'is_success'      => true,
			'subscription_id' => $subscription_id,
			'customer_id'     => $customer->id,
			'amount'          => $payment_amount,
		);

	}





	// # STRIPE HELPER FUNCTIONS ---------------------------------------------------------------------------------------

	/**
	 * Retrieve a specific customer from Stripe.
	 *
	 * @param string $customer_id The identifier of the customer to be retrieved.
	 * @param array $feed The feed currently being processed.
	 * @param array $entry The entry currently being processed.
	 * @param array $form The which created the current entry.
	 *
	 * @return bool|\Stripe\Customer
	 */
	public function get_customer( $customer_id, $feed = array(), $entry = array(), $form = array() ) {
		if ( empty( $customer_id ) && has_filter( 'gform_stripe_customer_id' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_stripe_customer_id.' );

			/**
			 * Allow an existing customer ID to be specified for use when processing the submission.
			 *
			 * @param string $customer_id The identifier of the customer to be retrieved. Default is empty string.
			 * @param array $feed The feed currently being processed.
			 * @param array $entry The entry currently being processed.
			 * @param array $form The form which created the current entry.
			 *
			 * @since 2.1.0
			 */
			$customer_id = apply_filters( 'gform_stripe_customer_id', $customer_id, $feed, $entry, $form );
		}

		if ( $customer_id ) {
			$this->log_debug( __METHOD__ . '(): Retrieving customer id => ' . print_r( $customer_id, 1 ) );
			$customer = \Stripe\Customer::retrieve( $customer_id );

			return $customer;
		}

		return false;
	}

	/**
	 * Create and return a Stripe customer with the specified properties.
	 *
	 * @param array $customer_meta The customer properties.
	 * @param array $feed The feed currently being processed.
	 * @param array $entry The entry currently being processed.
	 * @param array $form The form which created the current entry.
	 *
	 * @return \Stripe\Customer
	 */
	public function create_customer( $customer_meta, $feed, $entry, $form ) {

		// Log the customer to be created.
		$this->log_debug( __METHOD__ . '(): Customer meta to be created => ' . print_r( $customer_meta, 1 ) );

		// Create customer.
		$customer = \Stripe\Customer::create( $customer_meta );

		if ( has_filter( 'gform_stripe_customer_after_create' ) ) {
			// Log that filter will be executed.
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_stripe_customer_after_create.' );

			/**
			 * Allow custom actions to be performed between the customer being created and subscribed to the plan.
			 *
			 * @param Stripe\Customer $customer The Stripe customer object.
			 * @param array $feed The feed currently being processed.
			 * @param array $entry The entry currently being processed.
			 * @param array $form The form currently being processed.
			 *
			 * @since 2.0.1
			 */
			do_action( 'gform_stripe_customer_after_create', $customer, $feed, $entry, $form );
		}

		return $customer;
	}

	/**
	 * Try and retrieve the plan if a plan with the matching id has previously been created.
	 *
	 * @param string $plan_id The subscription plan id.
	 *
	 * @return array|bool
	 */
	public function get_plan( $plan_id ) {

		try {

			// Get Stripe plan.
			$plan = \Stripe\Plan::retrieve( $plan_id );

		} catch ( \Exception $e ) {

			/**
			 * There is no error type specific to failing to retrieve a subscription when an invalid plan ID is passed. We assume here
			 * that any 'invalid_request_error' means that the subscription does not exist even though other errors (like providing
			 * incorrect API keys) will also generate the 'invalid_request_error'. There is no way to differentiate these requests
			 * without relying on the error message which is more likely to change and not reliable.
			 */

			// Get error response.
			$response = $e->getJsonBody();

			// If error is an invalid request error, return error message.
			if ( rgars( $response, 'error/type' ) !== 'invalid_request_error' ) {
				$plan = $this->authorization_error( $e->getMessage() );
			} else {
				$plan = false;
			}

		}

		return $plan;
	}

	/**
	 * Create and return a Stripe plan with the specified properties.
	 *
	 * @param string $plan_id The plan ID.
	 * @param array $feed The feed currently being processed.
	 * @param float|int $payment_amount The recurring amount.
	 * @param int $trial_period_days The number of days the trial should last.
	 * @param string $currency The currency code for the entry being processed.
	 *
	 * @return \Stripe\Plan
	 */
	public function create_plan( $plan_id, $feed, $payment_amount, $trial_period_days, $currency ) {
		// Prepare plan metadata.
		$plan_meta = array(
			'interval'          => $feed['meta']['billingCycle_unit'],
			'interval_count'    => $feed['meta']['billingCycle_length'],
			'name'              => $feed['meta']['feedName'],
			'currency'          => $currency,
			'id'                => $plan_id,
			'amount'            => $this->get_amount_export( $payment_amount, $currency ),
			'trial_period_days' => $trial_period_days,
		);

		// Log the plan to be created.
		$this->log_debug( __METHOD__ . '(): Plan to be created => ' . print_r( $plan_meta, 1 ) );

		// Create Stripe plan.
		$plan = \Stripe\Plan::create( $plan_meta );

		return $plan;
	}

	/**
	 * Retrieve the specified Stripe Event.
	 *
	 * @param string $event_id Stripe Event ID.
	 *
	 * @return mixed
	 */
	public function get_stripe_event( $event_id ) {

		// Include Stripe API library.
		$this->include_stripe_api();

		// Get Stripe event.
		$event = \Stripe\Event::retrieve( $event_id );

		return $event;

	}

	/**
	 * If custom meta data has been configured on the feed retrieve the mapped field values.
	 *
	 * @param array $feed The feed object currently being processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return array
	 */
	public function get_stripe_meta_data( $feed, $entry, $form ) {

		// Initialize metadata array.
		$metadata = array();

		// Find feed metadata.
		$custom_meta = rgars( $feed, 'meta/metaData' );

		if ( is_array( $custom_meta ) ) {

			// Loop through custom meta and add to metadata for stripe.
			foreach ( $custom_meta as $meta ) {

				// If custom key or value are empty, skip meta.
				if ( empty( $meta['custom_key'] ) || empty( $meta['value'] ) ) {
					continue;
				}

				// Get field value for meta key.
				$field_value = $this->get_field_value( $form, $entry, $meta['value'] );

				if ( ! empty( $field_value ) ) {

					// Trim to 500 characters, per Stripe requirement.
					$field_value = substr( $field_value, 0, 500 );

					// Add to metadata array.
					$metadata[ $meta['custom_key'] ] = $field_value;

				}

			}

		}

		return $metadata;

	}

	/**
	 * Check if a Stripe.js has an error or is missing the ID and then return the appropriate message.
	 *
	 * @return bool|string
	 */
	public function get_stripe_js_error() {

		// Get Stripe.js response.
		$response = $this->get_stripe_js_response();

		// If an error message is provided, return error message.
		if ( isset( $response->error ) ) {
			return $response->error->message;
		} else if ( empty( $response->id ) ) {
			return esc_html__( 'Unable to authorize card. No response from Stripe.js.', 'gravityformsstripe' );
		}

		return false;

	}

	/**
	 * Response from Stripe.js is posted to the server as 'stripe_response'.
	 *
	 * @return \Stripe\Token|void A valid Stripe response object or null
	 */
	public function get_stripe_js_response() {

		return json_decode( rgpost( 'stripe_response' ) );

	}

	/**
	 * Include the Stripe API and set the current API key.
	 */
	public function include_stripe_api() {

		// If Stripe class does not exist, load Stripe API library.
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			require_once( $this->get_base_path() . '/includes/stripe-php/init.php' );
		}

		require_once( $this->get_base_path() . '/includes/deprecated.php' );

		// Set Stripe API key.
		\Stripe\Stripe::setApiKey( $this->get_secret_api_key() );

		// Run post Stripe API initialization action.
		do_action( 'gform_stripe_post_include_api' );

	}





	// # WEBHOOKS ------------------------------------------------------------------------------------------------------

	/**
	 * If the Stripe webhook belongs to a valid entry process the raw response into a standard Gravity Forms $action.
	 *
	 * @return array|bool|WP_Error Return a valid GF $action or if the webhook can't be processed a WP_Error object or false.
	 */
	public function callback() {

		// Get webhook request contents.
		$body = @file_get_contents( 'php://input' );

		// Decoded request.
		$response = json_decode( $body, true );

		// If response is empty, attempt to retrieve it from post data.
		if ( empty( $response ) ) {

			if ( strpos( $body, 'ipn_is_json' ) !== false ) {
				$response = json_decode( $_POST, true );
			}

			if ( empty( $response ) ) {
				return false;
			}

		}

		// Handling test webhooks.
		if ( 'evt_00000000000000' === $response['id'] ) {
			return new WP_Error( 'test_webhook_succeeded', __( 'Test webhook succeeded. Your Stripe Account and Stripe Add-On are configured correctly to process webhooks.', 'gravityformsstripe' ), array( 'status_header' => 200 ) );
		}

		// Get API mode.
		$settings = $this->get_plugin_settings();
		$mode     = $this->get_api_mode( $settings );

		// If API is in production mode and this is a test request, return an error.
		if ( false === $response['livemode'] && 'live' === $mode ) {
			return new WP_Error( 'invalid_request', __( 'Webhook from test transaction. Bypassed.', 'gravityformsstripe' ) );
		}

		try {

			// Verify this is a Stripe request by retrieving the Stripe event (based on the ID in the response).
			$event = $this->get_stripe_event( $response['id'] );

		} catch ( \Exception $e ) {

			// Log that event could not be retrieved.
			$this->log_error( __METHOD__ . '(): Unable to retrieve Stripe Event object. ' . $e->getMessage() );

			return new WP_Error( 'invalid_request', __( 'Invalid webhook data. Webhook could not be processed.', 'gravityformsstripe' ), array( 'status_header' => 500 ) );

		}

		// Get event properties.
		$action = array( 'id' => $event['id'] );
		$type   = rgar( $event, 'type' );

		$this->log_debug( __METHOD__ . '() Webhook event details => ' . print_r( $action + array( 'type' => $type ), 1 ) );

		switch ( $type ) {

			case 'charge.refunded':

				$action['transaction_id'] = rgars( $event, 'data/object/id' );
				$entry_id                 = $this->get_entry_by_transaction_id( $action['transaction_id'] );
				if ( ! $entry_id ) {
					return new WP_Error( 'entry_not_found', sprintf( __( 'Entry for transaction id: %s was not found. Webhook cannot be processed.', 'gravityformsstripe' ), $action['transaction_id'] ) );
				}

				$entry = GFAPI::get_entry( $entry_id );

				$action['entry_id'] = $entry_id;
				$action['type']     = 'refund_payment';
				$action['amount']   = $this->get_amount_import( rgars( $event, 'data/object/amount_refunded' ), $entry['currency'] );

				break;

			case 'customer.subscription.deleted':

				$action['subscription_id'] = rgars( $event, 'data/object/id' );
				$entry_id                  = $this->get_entry_by_transaction_id( $action['subscription_id'] );
				if ( ! $entry_id ) {
					return new WP_Error( 'entry_not_found', sprintf( __( 'Entry for subscription id: %s was not found. Webhook cannot be processed.', 'gravityformsstripe' ), $action['subscription_id'] ) );
				}

				$entry = GFAPI::get_entry( $entry_id );

				$action['entry_id'] = $entry_id;
				$action['type']     = 'cancel_subscription';
				$action['amount']   = $this->get_amount_import( rgars( $event, 'data/object/plan/amount' ), $entry['currency'] );

				break;

			case 'invoice.payment_succeeded':

				$subscription = $this->get_subscription_line_item( $event );
				if ( ! $subscription ) {
					return new WP_Error( 'invalid_request', sprintf( __( 'Subscription line item not found in request', 'gravityformsstripe' ) ) );
				}

				$action['subscription_id'] = rgar( $subscription, 'id' );
				$entry_id                  = $this->get_entry_by_transaction_id( $action['subscription_id'] );
				if ( ! $entry_id ) {
					return new WP_Error( 'entry_not_found', sprintf( __( 'Entry for subscription id: %s was not found. Webhook cannot be processed.', 'gravityformsstripe' ), $action['subscription_id'] ) );
				}

				$entry = GFAPI::get_entry( $entry_id );

				$action['transaction_id'] = rgars( $event, 'data/object/charge' );
				$action['entry_id']       = $entry_id;
				$action['type']           = 'add_subscription_payment';
				$action['amount']         = $this->get_amount_import( rgars( $event, 'data/object/amount_due' ), $entry['currency'] );

				$action['note'] = '';

				// Get starting balance, assume this balance represents a setup fee or trial.
				$starting_balance = $this->get_amount_import( rgars( $event, 'data/object/starting_balance' ), $entry['currency'] );
				if ( $starting_balance > 0 ) {
					$action['note'] = $this->get_captured_payment_note( $action['entry_id'] ) . ' ';
				}

				$amount_formatted = GFCommon::to_money( $action['amount'], $entry['currency'] );
				$action['note'] .= sprintf( __( 'Subscription payment has been paid. Amount: %s. Subscription Id: %s', 'gravityformsstripe' ), $amount_formatted, $action['subscription_id'] );

				break;

			case 'invoice.payment_failed':

				$subscription = $this->get_subscription_line_item( $event );
				if ( ! $subscription ) {
					return new WP_Error( 'invalid_request', sprintf( __( 'Subscription line item not found in request', 'gravityformsstripe' ) ) );
				}

				$action['subscription_id'] = rgar( $subscription, 'id' );
				$entry_id                  = $this->get_entry_by_transaction_id( $action['subscription_id'] );
				if ( ! $entry_id ) {
					return new WP_Error( 'entry_not_found', sprintf( __( 'Entry for subscription id: %s was not found. Webhook cannot be processed.', 'gravityformsstripe' ), $action['subscription_id'] ) );
				}

				$entry = GFAPI::get_entry( $entry_id );

				$action['type']     = 'fail_subscription_payment';
				$action['amount']   = $this->get_amount_import( rgar( $subscription, 'amount' ), $entry['currency'] );
				$action['entry_id'] = $this->get_entry_by_transaction_id( $action['subscription_id'] );

				break;

		}

		if ( has_filter( 'gform_stripe_webhook' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_stripe_webhook.' );

			/**
			 * Enable support for custom webhook events.
			 *
			 * @param array $action An associative array containing the event details.
			 * @param array $event The Stripe event object for the webhook which was received.
			 *
			 * @since 1.0.0
			 */
			$action = apply_filters( 'gform_stripe_webhook', $action, $event );
		}

		if ( rgempty( 'entry_id', $action ) ) {
			$this->log_debug( __METHOD__ . '() entry_id not set for callback action; no further processing required.' );

			return false;
		}

		return $action;

	}

	/**
	 * Generate the url Stripe webhooks should be sent to.
	 *
	 * @return string
	 */
	public function get_webhook_url() {

		return get_bloginfo( 'url' ) . '/?callback=' . $this->_slug;

	}

	/**
	 * Helper to check that webhooks are enabled.
	 *
	 * @return bool
	 */
	public function is_webhook_enabled() {

		return $this->get_plugin_setting( 'webhooks_enabled' ) == true;

	}





	// # HELPER FUNCTIONS ----------------------------------------------------------------------------------------------

	/**
	 * Retrieve the specified api key.
	 *
	 * @param string $type The type of key to retrieve.
	 *
	 * @return string
	 */
	public function get_api_key( $type = 'secret' ) {

		// Check for api key in query first; user must be an administrator to use this feature.
		$api_key = $this->get_query_string_api_key( $type );
		if ( $api_key && current_user_can( 'update_core' ) ) {
			return $api_key;
		}

		// Get API mode.
		$settings = $this->get_plugin_settings();
		$mode     = $this->get_api_mode( $settings );

		// Get API key based on current mode and defined type.
		$setting_key = "{$mode}_{$type}_key";
		$api_key     = $this->get_setting( $setting_key, '', $settings );

		return $api_key;

	}

	/**
	 * Helper to implement the gform_stripe_api_mode filter so the api mode can be overridden.
	 *
	 * @param array $settings The plugin settings.
	 *
	 * @return string $api_mode Either live or test.
	 */
	public function get_api_mode( $settings ) {

		// Get API mode from settings.
		$api_mode = rgar( $settings, 'api_mode' );

		// Filter API mode.
		return apply_filters( 'gform_stripe_api_mode', $api_mode );

	}

	/**
	 * Retrieve the specified api key from the query string.
	 *
	 * @param string $type The type of key to retrieve.
	 *
	 * @return string
	 */
	public function get_query_string_api_key( $type = 'secret' ) {

		return rgget( $type );

	}

	/**
	 * Retrieve the publishable api key.
	 *
	 * @return string
	 */
	public function get_publishable_api_key() {

		return $this->get_api_key( 'publishable' );

	}

	/**
	 * Retrieve the secret api key.
	 *
	 * @return string
	 */
	public function get_secret_api_key() {

		return $this->get_api_key( 'secret' );

	}

	/**
	 * Retrieve the first part of the subscription's entry note.
	 *
	 * @param int $entry_id The ID of the entry currently being processed.
	 *
	 * @return string
	 */
	public function get_captured_payment_note( $entry_id ) {

		// Get feed for entry.
		$entry = GFAPI::get_entry( $entry_id );
		$feed  = $this->get_payment_feed( $entry );

		// Define note based on if setup fee is enabled.
		if ( rgars( $feed, 'meta/setupFee_enabled' ) ) {
			$note = esc_html__( 'Setup fee has been paid.', 'gravityformsstripe' );
		} else {
			$note = esc_html__( 'Trial has been paid.', 'gravityformsstripe' );
		}

		return $note;
	}

	/**
	 * Retrieve the labels for the various card types.
	 *
	 * @return array
	 */
	public function get_card_labels() {

		// Get credit card types.
		$card_types  = GFCommon::get_card_types();

		// Initialize credit card labels array.
		$card_labels = array();

		// Loop through card types.
		foreach ( $card_types as $card_type ) {

			// Add card label for card type.
			$card_labels[ $card_type['slug'] ] = $card_type['name'];

		}

		return $card_labels;

	}

	/**
	 * Get the slug for the card type returned by Stripe.js
	 *
	 * @param string $type The possible types are "Visa", "MasterCard", "American Express", "Discover", "Diners Club", and "JCB" or "Unknown".
	 *
	 * @return string
	 */
	public function get_card_slug( $type ) {

		// If type is defined, attempt to get card slug.
		if ( $type ) {

			// Get card types.
			$card_types = GFCommon::get_card_types();

			// Loop through card types.
			foreach ( $card_types as $card ) {

				// If the requested card type is equal to the current card's name, return the slug.
				if ( rgar( $card, 'name' ) === $type ) {
					return rgar( $card, 'slug' );
				}

			}

		}

		return $type;

	}

	/**
	 * Populate the $_POST with the last four digits of the card number and card type.
	 *
	 * @param array $form Form object.
	 */
	public function populate_credit_card_last_four( $form ) {

		if ( ! $this->is_payment_gateway ) {
			return;
		}

		$cc_field                                 = $this->get_credit_card_field( $form );
		$_POST[ 'input_' . $cc_field->id . '_1' ] = 'XXXXXXXXXXXX' . rgpost( 'stripe_credit_card_last_four' );
		$_POST[ 'input_' . $cc_field->id . '_4' ] = rgpost( 'stripe_credit_card_type' );

	}

	/**
	 * Add the value of the trialPeriod property to the order data which is to be included in the $submission_data.
	 *
	 * @param array $feed The feed currently being processed.
	 * @param array $form The form currently being processed.
	 * @param array $entry The entry currently being processed.
	 *
	 * @return array
	 */
	public function get_order_data( $feed, $form, $entry ) {

		$order_data          = parent::get_order_data( $feed, $form, $entry );
		$order_data['trial'] = rgars( $feed, 'meta/trialPeriod' );

		return $order_data;

	}

	/**
	 * Return the description to be used with the Stripe charge.
	 *
	 * @param array $entry The entry object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $feed The feed object currently being processed.
	 *
	 * @return string
	 */
	public function get_payment_description( $entry, $submission_data, $feed ) {

		// Charge description format:
		// Entry ID: 123, Products: Product A, Product B, Product C

		$strings = array();

		if ( $entry['id'] ) {
			$strings['entry_id'] = sprintf( esc_html__( 'Entry ID: %d', 'gravityformsstripe' ), $entry['id'] );
		}

		$strings['products'] = sprintf(
			_n( 'Product: %s', 'Products: %s', count( $submission_data['line_items'] ), 'gravityformsstripe' ),
			implode( ', ', wp_list_pluck( $submission_data['line_items'], 'name' ) )
		);

		$description = implode( ', ', $strings );

		/**
		 * Allow the charge description to be overridden.
		 *
		 * @param string $description The charge description.
		 * @param array $strings Contains the Entry ID and Products. The array which was imploded to create the description.
		 * @param array $entry The entry object currently being processed.
		 * @param array $submission_data The customer and transaction data.
		 * @param array $feed The feed object currently being processed.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'gform_stripe_charge_description', $description, $strings, $entry, $submission_data, $feed );
	}

	/**
	 * Retrieve the subscription line item from from the Stripe response.
	 *
	 * @param array $response The Stripe webhook response.
	 *
	 * @return bool|array
	 */
	public function get_subscription_line_item( $response ) {

		$lines = rgars( $response, 'data/object/lines/data' );

		foreach ( $lines as $line ) {
			if ( 'subscription' === $line['type'] ) {
				return $line;
			}
		}

		return false;
	}

	/**
	 * Generate the subscription plan id.
	 *
	 * @param array     $feed The feed object currently being processed.
	 * @param float|int $payment_amount The recurring amount.
	 * @param int       $trial_period_days The number of days the trial should last.
	 *
	 * @return string
	 */
	public function get_subscription_plan_id( $feed, $payment_amount, $trial_period_days ) {

		$safe_trial_period = $trial_period_days ? 'trial' . $trial_period_days . 'days' : '';

		$safe_feed_name     = str_replace( ' ', '', strtolower( $feed['meta']['feedName'] ) );
		$safe_billing_cycle = $feed['meta']['billingCycle_length'] . $feed['meta']['billingCycle_unit'];

		$plan_id = implode( '_', array_filter( array( $safe_feed_name, $feed['id'], $safe_billing_cycle, $safe_trial_period, $payment_amount ) ) );

		return $plan_id;

	}

}
