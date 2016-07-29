<?php

GFForms::include_payment_addon_framework();

class GFStripe extends GFPaymentAddOn {

	protected $_version = GF_STRIPE_VERSION;

	protected $_min_gravityforms_version = '1.9.14.17';
	protected $_slug = 'gravityformsstripe';
	protected $_path = 'gravityformsstripe/stripe.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Stripe Add-On';
	protected $_short_title = 'Stripe';
	protected $_requires_credit_card = true;
	protected $_supports_callbacks = true;
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Stripe requires monetary amounts to be formatted as the smallest unit for the currency being used e.g. cents.
	 *
	 * @var bool
	 */
	protected $_requires_smallest_unit = true;

	/**
	 * Permissions
	 */
	protected $_capabilities_settings_page = 'gravityforms_stripe';
	protected $_capabilities_form_settings = 'gravityforms_stripe';
	protected $_capabilities_uninstall = 'gravityforms_stripe_uninstall';

	/**
	 * Members plugin integration
	 */
	protected $_capabilities = array( 'gravityforms_stripe', 'gravityforms_stripe_uninstall' );

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFStripe
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
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
										'tab'        => array( $this->_slug, $this->get_short_title() )
								),
						)
				),
				array(
						'handle'    => 'gforms_stripe_frontend',
						'src'       => $this->get_base_url() . '/js/frontend.js',
						'version'   => $this->_version,
						'deps'      => array( 'jquery', 'stripe.js', 'gform_json' ),
						'in_footer' => false,
						'enqueue'   => array(
								array( $this, 'has_feed_callback' ),
						)
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
										'tab'        => array( $this->_slug, $this->get_short_title() )
								),
						),
						'strings'   => array(
								'spinner'          => GFCommon::get_base_url() . '/images/spinner.gif',
								'validation_error' => esc_html__( 'Error validating this key. Please try again later.', 'gravityformsstripe' ),

						)
				),
		);

		return array_merge( parent::scripts(), $scripts );
	}


	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	// ------- Plugin settings -------

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
		$key_name = rgpost( 'keyName' );

		// if no cache or if new value provided, do a fresh validation
		$this->include_stripe_api();
		Stripe::setApiKey( rgpost( 'key' ) );

		$is_valid = true;

		try {
			Stripe_Account::retrieve();
		} catch ( Stripe_AuthenticationError $e ) {
			$is_valid = false;
			$this->log_debug( __METHOD__ . "(): {$key_name}: " . $e->getMessage() );
		}

		$response = $is_valid ? 'valid' : 'invalid';

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
				'fields' => $this->api_settings_fields()
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
								'label' => sprintf( esc_html__( 'I have enabled the Gravity Forms webhook URL in my Stripe account.', 'gravityformsstripe' ) ),
								'value' => 1,
								'name'  => 'webhooks_enabled',
							),
						)
					),
					array(
						'type'     => 'save',
						'messages' => array( 'success' => esc_html__( 'Settings updated successfully', 'gravityformsstripe' ) )

					),
				)
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

	/**
	 * Prevent feeds being listed or created if the api keys aren't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		$settings = $this->get_plugin_settings();
		$api_mode = $this->get_api_mode( $settings );

		if ( $api_mode == 'live' ) {

			return rgar( $settings, 'live_publishable_key_is_valid' ) && rgar( $settings, 'live_secret_key_is_valid' ) && $this->is_webhook_enabled();
		} else {

			return rgar( $settings, 'test_publishable_key_is_valid' ) && rgar( $settings, 'test_secret_key_is_valid' ) && $this->is_webhook_enabled();
		}
	}

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @return array The feed settings.
	 */
	public function feed_settings_fields() {

		$default_settings = parent::feed_settings_fields();

		$customer_info_field = array(
				'name'       => 'customerInformation',
				'label'      => esc_html__( 'Customer Information', 'gravityformsstripe' ),
				'type'       => 'field_map',
				'dependency' => array(
						'field'  => 'transactionType',
						'values' => array( 'subscription' )
				),
				'field_map'  => array(
						array(
								'name'       => 'email',
								'label'      => esc_html__( 'Email', 'gravityformsstripe' ),
								'required'   => true,
								'field_type' => array(
										'email',
										'hidden',
								)
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
								'field_type' => array(
										'coupon',
										'text',
								),
								'tooltip' => '<h6>' . esc_html__( 'Coupon', 'gravityformsstripe' ) . '</h6>' . esc_html__( 'Select which field contains the coupon code to be applied to the recurring charge(s). The coupon must also exist in your Stripe Dashboard.', 'gravityformsstripe' ),
						),
				)
		);

		$default_settings = $this->replace_field( 'billingInformation', $customer_info_field, $default_settings );

		//set part of tooltip depending on transaction type
		if ( $this->get_setting( 'transactionType' ) == 'subscription' ) {
			$info = esc_html__( 'You will see this data when viewing a customer page.', 'gravityformsstripe' );
		} else {
			$info = esc_html__( 'You will see this data when viewing a payment page.', 'gravityformsstripe' );
		}
		//add custom  meta information
		$custom_meta = array(
			array(
				'name'                => 'metaData',
				'label'               => esc_html__( 'Metadata', 'gravityformsstripe' ),
				'type'                => 'dynamic_field_map',
				'limit'				  => 20,
				'exclude_field_types' => 'creditcard',
				'tooltip'             => '<h6>' . esc_html__( 'Metadata', 'gravityformsstripe' ) . '</h6>' . esc_html__( 'You may send custom meta information to Stripe. A maximum of 20 custom keys may be sent. The key name must be 40 characters or less, and the mapped data will be truncated to 500 characters per requirements by Stripe. ' . $info , 'gravityformsstripe' ),
				'validation_callback' => array( $this, 'validate_custom_meta'),
			),
		);

		$default_settings = $this->add_field_after( 'customerInformation', $custom_meta, $default_settings );

		// Stripe does not support ending a subscription after a set number of payments
		$default_settings = $this->remove_field( 'recurringTimes', $default_settings );

		$trial_period_field = array(
			'name'                => 'trialPeriod',
			'label'               => esc_html__( 'Trial Period', 'gravityformsstripe' ),
			'style'               => 'width:40px;text-align:center;',
			'type'                => 'trial_period',
			'validation_callback' => array( $this, 'validate_trial_period' )
		);
		$default_settings   = $this->add_field_after( 'trial', $trial_period_field, $default_settings );


		if ( $this->get_setting( 'transactionType' ) == 'product' ) {
			$receipt_settings = array(
				'name'    => 'receipt',
				'label'   => 'Stripe Receipt',
				'type'    => 'receipt',
				'tooltip' => '<h6>' . esc_html__( 'Stripe Receipt', 'gravityformsstripe' ) . '</h6>' . esc_html__( 'Stripe can send a receipt via email upon payment. Select an email field to enable this feature.', 'gravityformsstripe' )
			);

			$default_settings = $this->add_field_before( 'conditionalLogic', $receipt_settings, $default_settings );
		}

		return $default_settings;
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
	 * @param array $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_receipt( $field, $echo = true ) {

		$first_choice = array( 'label' => esc_html__( 'Do not send receipt', 'gravityformsstripe' ), 'value' => '' );
		$fields       = $this->get_form_fields_as_choices( $this->get_current_form(), array(
			'input_types' => array(
				'email',
				'hidden',
			)
		) );

		//Adding first choice to the beginning of the fields array
		array_unshift( $fields, $first_choice );

		$select = array(
			'name'    => 'receipt_field',
			'choices' => $fields,
		);

		$html = $this->settings_select( $select, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Define the markup for the setup_fee type field.
	 *
	 * @param array $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_setup_fee( $field, $echo = true ) {

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
					}"
				),
			)
		);

		$html = $this->settings_checkbox( $enabled_field, false );

		$form = $this->get_current_form();

		$is_enabled = $this->get_setting( "{$field['name']}_enabled" );

		$product_field = array(
			'name'    => $field['name'] . '_product',
			'type'    => 'select',
			'class'   => $is_enabled ? '' : 'hidden',
			'choices' => $this->get_payment_choices( $form )
		);

		$html .= '&nbsp' . $this->settings_select( $product_field, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Define the markup for the trial type field.
	 *
	 * @param array $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_trial( $field, $echo = true ) {

		//--- Enabled field ---
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
					}"
				),
			)
		);

		$html = $this->settings_checkbox( $enabled_field, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Define the markup for the trial_period type field.
	 *
	 * @param array $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 *
	 * @return string|void
	 */
	public function settings_trial_period( $field, $echo = true ) {

		$html = $this->settings_text( $field, false );
		$html .= ' <span class="gaddon-settings-input-suffix">' . esc_html__( 'days', 'gravityformsstripe' ) . '</span>';

		$validation_placeholder = array( 'name' => 'trialValidationPlaceholder' );

		if ( $this->field_failed_validation( $validation_placeholder ) ) {
			$html .= '&nbsp;' . $this->get_error_icon( $validation_placeholder );
		}

		$html .= '
			<script type="text/javascript">
			if( ! jQuery( "#trial_enabled" ).is( ":checked" ) || jQuery( "#setupFee_enabled" ).is( ":checked" ) ) {
				jQuery( "#trial_enabled" ).prop( "checked", false );
				jQuery( "#gaddon-setting-row-trialPeriod" ).hide();
			}
			</script>';

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

		$settings = $this->get_posted_settings();

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
		//Number of keys is limited to 20 - interface should control this, validating just in case
		//key names can only be max of 40 characters

		$settings = $this->get_posted_settings();
		$metaData = $settings['metaData'];

		if ( empty( $metaData ) ) {
			return;
		}

		//check the number of items in metadata array
		$metaCount = count( $metaData );
		if ( $metaCount > 20 ) {
			$this->set_field_error( array( esc_html__( 'You may only have 20 custom keys.' ), 'gravityformsstripe' ) );

			return;
		}

		//loop through metaData and check the key name length (custom_key)
		foreach ( $metaData as $meta ) {
			if ( empty( $meta['custom_key'] ) && ! empty( $meta['value'] ) ) {
				$this->set_field_error( array( 'name' => 'metaData' ), esc_html__( "A field has been mapped to a custom key without a name. Please enter a name for the custom key, remove the metadata item, or return the corresponding drop down to 'Select a Field'.", 'gravityformsstripe' ) );
				break;
			} elseif ( strlen( $meta['custom_key'] ) > 40 ) {
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

		$supported_billing_cycles = array(
			'day'   => array( 'label' => esc_html__( 'day(s)', 'gravityformsstripe' ), 'min' => 1, 'max' => 365 ),
			'week'  => array( 'label' => esc_html__( 'week(s)', 'gravityformsstripe' ), 'min' => 1, 'max' => 12 ),
			'month' => array( 'label' => esc_html__( 'month(s)', 'gravityformsstripe' ), 'min' => 1, 'max' => 12 ),
			'year'  => array( 'label' => esc_html__( 'year(s)', 'gravityformsstripe' ), 'min' => 1, 'max' => 1 )
		);

		return $supported_billing_cycles;
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

		$this->include_stripe_api();
		try {
			$customer_id = gform_get_meta( $entry['id'], 'stripe_customer_id' );
			$customer    = Stripe_Customer::retrieve( $customer_id );
			$customer->cancelSubscription();

			return true;
		} catch ( Stripe_Error $error ) {
			return false;
		}
	}

	//NOTE: to be implemented later with other Payment Add-Ons
	//        public function note_avatar(){
	//            return $this->get_base_url() . "/images/stripe_48x48.png";
	//        }

	/**
	 * Add supported notification events.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array
	 */
	public function supported_notification_events( $form ) {
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

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

	public function init() {

		add_filter( 'gform_register_init_scripts', array( $this, 'register_init_scripts' ), 10, 3 );
		add_filter( 'gform_field_content', array( $this, 'add_stripe_inputs' ), 10, 5 );
		add_filter( 'gform_field_validation', array( $this, 'pre_validation' ), 10, 4 );

		parent::init();

	}

	public function register_init_scripts( $form, $field_values, $is_ajax ) {

		if ( ! $this->has_feed( $form['id'] ) ) {
			return;
		}

		$cc_field = $this->get_credit_card_field( $form );

		$args = array(
			'apiKey'     => $this->get_publishable_api_key(),
			'formId'     => $form['id'],
			'ccFieldId'  => $cc_field->id,
			'ccPage'     => $cc_field->pageNumber,
			'isAjax'     => $is_ajax,
			'cardLabels' => $this->get_card_labels()
		);

		$script = 'new GFStripe( ' . json_encode( $args ) . ' );';
		GFFormDisplay::add_init_script( $form['id'], 'stripe', GFFormDisplay::ON_PAGE_RENDER, $script );

	}

	public function add_stripe_inputs( $content, $field, $value, $lead_id, $form_id ) {

		if ( ! $this->has_feed( $form_id ) || $field->get_input_type() != 'creditcard' ) {
			return $content;
		}

		if ( $this->get_stripe_js_response() ) {
			$content .= '<input type=\'hidden\' name=\'stripe_response\' id=\'gf_stripe_response\' value=\'' . rgpost( 'stripe_response' ) . '\' />';
		}

		if ( rgpost( 'stripe_credit_card_last_four' ) ) {
			$content .= '<input type="hidden" name="stripe_credit_card_last_four" id="gf_stripe_credit_card_last_four" value="' . rgpost( 'stripe_credit_card_last_four' ) . '" />';
		}

		if ( rgpost( 'stripe_credit_card_type' ) ) {
			$content .= '<input type="hidden" name="stripe_credit_card_type" id="stripe_credit_card_type" value="' . rgpost( 'stripe_credit_card_type' ) . '" />';
		}

		return $content;
	}

	# SUBMISSION

	/**
	 * Validate the card type and prevent the field from failing required validation, Stripe.js will handle the required validation.
	 *
	 * The card field inputs are erased on submit, this will cause two issues:
	 * 1. The field will fail standard validation if marked as required.
	 * 2. The card type validation will not be performed.
	 *
	 * @param array $result The field validation result and message.
	 * @param mixed $value The field input values; empty for the credit card field as they are cleared by frontend.js
	 * @param array $form The Form currently being processed.
	 * @param GF_Field $field The field currently being processed.
	 *
	 * @return array
	 */
	public function pre_validation( $result, $value, $form, $field ) {
		if ( $field->type == 'creditcard' && rgpost( 'stripe_credit_card_last_four' ) ) {
			$this->populate_credit_card_last_four( $form );

			$card_type = rgpost( 'stripe_credit_card_type' );
			$card_slug = $this->get_card_slug( $card_type );

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

		$this->include_stripe_api();

		if ( $this->get_stripe_js_error() ) {
			return $this->authorization_error( $this->get_stripe_js_error() );
		}

		$auth = $this->authorize_product( $feed, $submission_data, $form, $entry );

		return $auth;
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

			$stripe_response = $this->get_stripe_js_response();

			$charge_meta = array(
				'amount'      => $this->get_amount_export( $submission_data['payment_amount'], rgar( $entry, 'currency' ) ),
				'currency'    => rgar( $entry, 'currency' ),
				'card'        => $stripe_response->id,
				'description' => $this->get_payment_description( $entry, $submission_data, $feed ),
				'capture'     => false,
			);

			$receipt_field = rgars( $feed, 'meta/receipt_field' );
			if ( ! empty( $receipt_field ) && strtolower( $receipt_field ) != 'do not send receipt' ) {
				$charge_meta['receipt_email'] = $this->get_field_value( $form, $entry, $receipt_field );
			}

			$metadata = $this->get_stripe_meta_data( $feed, $entry, $form );

			if ( ! empty( $metadata ) ) {
				//add custom meta to charge object
				$charge_meta['metadata'] = $metadata;
			}

			$this->log_debug( __METHOD__ . '(): Charge meta to be created => ' . print_r( $charge_meta, 1 ) );

			$charge = Stripe_Charge::create( $charge_meta );

			$auth = array(
				'is_authorized' => true,
				'charge_id'     => $charge['id'],
			);

		} catch ( Stripe_Error $e ) {

			$auth = $this->authorization_error( $e->getMessage() );

		}

		return $auth;
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
		$metadata = array();

		//look for custom meta
		$custom_meta = rgars( $feed, 'meta/metaData' );

		if ( is_array( $custom_meta ) ) {
			//loop through custom meta and add to metadata for stripe
			foreach ( $custom_meta as $meta ) {
				if ( empty( $meta['custom_key'] ) || empty( $meta['value'] ) ) {
					continue;
				}

				$field_value = $this->get_field_value( $form, $entry, $meta['value'] );
				if ( ! empty( $field_value ) ) {
					//trim to 500 characters per Stripe requirement
					$field_value = substr( $field_value, 0, 500 );
					$metadata[ $meta['custom_key'] ] = $field_value;
				}

			}
		}

		return $metadata;
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

		$this->include_stripe_api();

		if ( $this->get_stripe_js_error() ) {
			return $this->authorization_error( $this->get_stripe_js_error() );
		}

		$payment_amount        = $submission_data['payment_amount'];
		$single_payment_amount = $submission_data['setup_fee'];
		$trial_period_days     = rgars( $feed, 'meta/trialPeriod' ) ? $submission_data['trial'] : null;
		$currency              = rgar( $entry, 'currency' );

		$plan_id = $this->get_subscription_plan_id( $feed, $payment_amount, $trial_period_days );
		$plan    = $this->get_plan( $plan_id );

		if ( rgar( $plan, 'error_message' ) ) {
			return $plan;
		}

		try {

			if ( ! $plan ) {

				$plan_meta = array(
					'interval'          => $feed['meta']['billingCycle_unit'],
					'interval_count'    => $feed['meta']['billingCycle_length'],
					'name'              => $feed['meta']['feedName'],
					'currency'          => $currency,
					'id'                => $plan_id,
					'amount'            => $this->get_amount_export( $payment_amount, $currency ),
					'trial_period_days' => $trial_period_days,
				);
				$this->log_debug( __METHOD__ . '(): Plan to be created => ' . print_r( $plan_meta, 1 ) );

				$plan = Stripe_Plan::create( $plan_meta );

			}

			$stripe_response = $this->get_stripe_js_response();

			$customer_meta = array(
					'description'     => $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'customerInformation_description' ) ),
					'email'           => $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'customerInformation_email' ) ),
					'card'            => $stripe_response->id,
					'account_balance' => $this->get_amount_export( $single_payment_amount, $currency ),
					'metadata'        => $this->get_stripe_meta_data( $feed, $entry, $form ),
			);

			$coupon_field_id = rgar( $feed['meta'], 'customerInformation_coupon' );
			$coupon          = $this->maybe_override_field_value( rgar( $entry, $coupon_field_id ), $form, $entry, $coupon_field_id );
			if ( $coupon ) {
				$customer_meta['coupon'] = $coupon;
			}

			$this->log_debug( __METHOD__ . '(): Customer meta to be created => ' . print_r( $customer_meta, 1 ) );

			$customer = Stripe_Customer::create( $customer_meta );

			if ( has_filter( 'gform_stripe_customer_after_create' ) ) {
				$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_stripe_customer_after_create.' );
			}
			/**
			 * Allow custom actions to be performed between the customer being created and subscribed to the plan.
			 *
			 * @param Stripe_Customer $customer The Stripe customer object.
			 * @param array $feed The feed currently being processed.
			 * @param array $entry The entry currently being processed.
			 * @param array $form The form currently being processed.
			 *
			 */
			do_action( 'gform_stripe_customer_after_create', $customer, $feed, $entry, $form );

			$subscription = $customer->updateSubscription( array( 'plan' => $plan->id ) );


		} catch ( Stripe_Error $e ) {

			return $this->authorization_error( $e->getMessage() );

		}

		return array(
			'is_success'      => true,
			'subscription_id' => $subscription->id,
			'customer_id'     => $customer->id,
			'amount'          => $payment_amount,
		);
	}

	/**
	 * Populate the $_POST with the last four digits of the card number and card type.
	 *
	 * @param $form
	 */
	public function populate_credit_card_last_four( $form ) {
		$cc_field                                 = $this->get_credit_card_field( $form );
		$_POST[ 'input_' . $cc_field->id . '_1' ] = 'XXXXXXXXXXXX' . rgpost( 'stripe_credit_card_last_four' );
		$_POST[ 'input_' . $cc_field->id . '_4' ] = rgpost( 'stripe_credit_card_type' );
	}

	/**
	 * Generate the subscription plan id.
	 *
	 * @param array $feed The feed object currently being processed.
	 * @param float|int $payment_amount The recurring amount.
	 * @param int $trial_period_days The number of days the trial should last.
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

	/**
	 * Try and retrieve the plan if a plan with the matching id has previously been created.
	 *
	 * @param string $plan_id The subscription plan id.
	 *
	 * @return array|bool
	 */
	public function get_plan( $plan_id ) {

		try {

			$plan = Stripe_Plan::retrieve( $plan_id );

		} catch ( Stripe_Error $e ) {

			/**
			 * There is no error type specific to failing to retrieve a subscription when an invalid plan ID is passed. We assume here
			 * that any 'invalid_request_error' means that the subscription does not exist even though other errors (like providing
			 * incorrect API keys) will also generate the 'invalid_request_error'. There is no way to differentiate these requests
			 * without relying on the error message which is more likely to change and not reliable.
			 */
			$response = $e->getJsonBody();
			if ( rgars( $response, 'error/type' ) != 'invalid_request_error' ) {
				$plan = $this->authorization_error( $e->getMessage() );
			} else {
				$plan = false;
			}
		}

		return $plan;
	}


	# POST SUBMISSION

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

		$charge = Stripe_Charge::retrieve( $auth['charge_id'] );

		try {

			$charge->description = $this->get_payment_description( $entry, $submission_data, $feed );
			$charge->metadata = $this->get_stripe_meta_data( $feed, $entry, $form );
			$charge->save();
			$charge = $charge->capture();

			$payment = array(
				'is_success'     => true,
				'transaction_id' => $charge['id'],
				'amount'         => $this->get_amount_import( $charge['amount'], $entry['currency'] ),
				'payment_method' => rgpost( 'stripe_credit_card_type' )
			);

		} catch ( Stripe_Error $e ) {

			$payment = array(
				'is_success'    => false,
				'error_message' => $e->getMessage()
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

		gform_update_meta( $entry['id'], 'stripe_customer_id', $authorization['subscription']['customer_id'] );

		// update to user meta post entry creation so entry ID is available
		try {
			$customer = Stripe_Customer::retrieve( $authorization['subscription']['customer_id'] );
			$customer->metadata = $this->get_stripe_meta_data( $feed, $entry, $form );
			$customer->save();
		} catch ( Stripe_Error $e ) {
			$this->log_debug( __METHOD__ . "(): Stripe_Customer: " . $e->getMessage() );
		}

		return parent::process_subscription( $authorization, $feed, $submission_data, $form, $entry );
	}


	# WEBHOOKS

	/**
	 * Process Stripe webhooks. Convert raw response into standard Gravity Forms $action.
	 *
	 * @return array|bool Return a valid GF $action or false if you have processed the callback yourself.
	 */
	public function callback() {

		$body = @file_get_contents( 'php://input' );

		$response = json_decode( $body, true );

		if ( empty( $response ) ) {

			if ( strpos( $body, 'ipn_is_json' ) !== false ) {
				$response = json_decode( $_POST, true );
			}

			if ( empty( $response ) ) {
				return false;
			}
		}

		//Handling test webhooks
		if ( $response['id'] == 'evt_00000000000000' ) {
			return new WP_Error( 'test_webhook_succeeded', __( 'Test webhook succeeded. Your Stripe Account and Stripe Add-On are configured correctly to process webhooks.', 'gravityformsstripe' ), array( 'status_header' => 200 ) );
		}

		$settings = $this->get_plugin_settings();
		$mode     = $this->get_api_mode( $settings );

		if ( $response['livemode'] == false && $mode == 'live' ) {
			return new WP_Error( 'invalid_request', __( 'Webhook from test transaction. Bypassed.', 'gravityformsstripe' ) );
		}

		try {
			//To make sure the request came from Stripe, getting the event object again from Stripe (based on the ID in the response)
			$event = $this->get_stripe_event( $response['id'] );
		} catch ( Stripe_Error $e ) {
			$this->log_error( __METHOD__ . '(): Unable to retrieve Stripe Event object. ' . $e->getMessage() );

			return new WP_Error( 'invalid_request', __( 'Invalid webhook data. Webhook could not be processed.', 'gravityformsstripe' ), array( 'status_header' => 500 ) );
		}

		$action = array( 'id' => $event['id'] );
		$type   = rgar( $event, 'type' );

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

				// get starting balance, assume this balance represents a setup fee or trial
				$starting_balance = $this->get_amount_import( rgars( $event, 'data/object/starting_balance' ), $entry['currency'] );
				if ( $starting_balance > 0 ) {
					$action['note'] = $this->get_captured_payment_note( $action['entry_id'] ) . ' ';
				}

				$amount_formatted = GFCommon::to_money( $action['amount'], $entry['currency'] );
				$action['note'] .= sprintf( __( 'Subscription payment has been paid. Amount: %s. Subscriber Id: %s', 'gravityformsstripe' ), $amount_formatted, $action['subscription_id'] );

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

		$action = apply_filters( 'gform_stripe_webhook', $action, $event );

		if ( rgempty( 'entry_id', $action ) ) {
			return false;
		}

		return $action;
	}

	/**
	 * Retrieve the specified Stripe Event.
	 *
	 * @param $event_id
	 *
	 * @return mixed
	 */
	public function get_stripe_event( $event_id ) {

		$this->include_stripe_api();
		$event = Stripe_Event::retrieve( $event_id );

		return $event;
	}

	/**
	 * Retrieve the first part of the subscription's entry note.
	 *
	 * @param int $entry_id The ID of the entry currently being processed.
	 *
	 * @return string
	 */
	public function get_captured_payment_note( $entry_id ) {

		$entry = GFAPI::get_entry( $entry_id );
		$feed  = $this->get_payment_feed( $entry );

		if ( rgars( $feed, 'meta/setupFee_enabled' ) ) {
			$note = esc_html__( 'Setup fee has been paid.', 'gravityformsstripe' );
		} else {
			$note = esc_html__( 'Trial has been paid.', 'gravityformsstripe' );
		}

		return $note;
	}



	# HELPERS

	/**
	 * Include the Stripe API and set the current API key.
	 */
	public function include_stripe_api() {

		if ( ! class_exists( 'Stripe' ) ) {
			require_once( $this->get_base_path() . '/includes/stripe-php/lib/Stripe.php' );
		}

		Stripe::setApiKey( $this->get_secret_api_key() );

		do_action( 'gform_stripe_post_include_api' );
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
	 * Retrieve the publishable api key.
	 *
	 * @return string
	 */
	public function get_publishable_api_key() {
		return $this->get_api_key( 'publishable' );
	}

	/**
	 * Retrieve the specified api key.
	 *
	 * @param string $type The type of key to retrieve.
	 *
	 * @return string
	 */
	public function get_api_key( $type = 'secret' ) {

		// check for api key in query first, user be an administrator to use this feature
		$api_key = $this->get_query_string_api_key( $type );
		if ( $api_key && current_user_can( 'update_core' ) ) {
			return $api_key;
		}

		$settings = $this->get_plugin_settings();
		$mode     = $this->get_api_mode( $settings );

		$setting_key = "{$mode}_{$type}_key";
		$api_key     = $this->get_setting( $setting_key, '', $settings );

		return $api_key;
	}

	/**
	 * Retrieve the specified api key from the query string.
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public function get_query_string_api_key( $type = 'secret' ) {
		return rgget( $type );
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
	 * Helper to implement the gform_stripe_api_mode filter so the api mode can be overridden.
	 *
	 * @param array $settings The plugin settings.
	 *
	 * @return string $api_mode Either live or test.
	 */
	public function get_api_mode( $settings ) {
		$api_mode = rgar( $settings, 'api_mode' );

		return apply_filters( 'gform_stripe_api_mode', $api_mode );
	}

	/**
	 * Check if the form has an active Stripe feed.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return bool
	 */
	public function has_feed_callback( $form ) {
		return $form && $this->has_feed( $form['id'] );
	}

	/**
	 * Response from Stripe.js is posted to the server as 'stripe_response'.
	 *
	 * @return array|void A valid Stripe response object or null
	 */
	public function get_stripe_js_response() {
		return json_decode( rgpost( 'stripe_response' ) );
	}

	/**
	 * Check if a Stripe.js has an error or is missing the ID and then return the appropriate message.
	 *
	 * @return bool|string
	 */
	public function get_stripe_js_error() {

		$response = $this->get_stripe_js_response();

		if ( isset( $response->error ) ) {
			return $response->error->message;
		} elseif ( empty( $response->id ) ) {
			return esc_html__( 'Unable to authorize card. No response from Stripe.js.', 'gravityformsstripe' );
		}

		return false;
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

		return apply_filters( 'gform_stripe_charge_description', implode( ', ', $strings ), $strings, $entry, $submission_data, $feed );
	}

	/**
	 * Retrieve the labels for the various card types.
	 *
	 * @return array
	 */
	public function get_card_labels() {
		$card_types  = GFCommon::get_card_types();
		$card_labels = array();
		foreach ( $card_types as $card_type ) {
			$card_labels[ $card_type['slug'] ] = $card_type['name'];
		}

		return $card_labels;
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
			if ( $line['type'] == 'subscription' ) {
				return $line;
			}
		}

		return false;
	}

	/**
	 * Helper to check that webhooks are enabled.
	 *
	 * @return bool
	 */
	public function is_webhook_enabled() {
		return $this->get_plugin_setting( 'webhooks_enabled' ) == true;
	}

	/**
	 * Prevent the 'options' checkboxes setting being included on the feed.
	 *
	 * @return bool
	 */
	public function option_choices() {
		return false;
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
	 * @ignore Not in use.
	 */
	public function is_field_on_valid_page( $field, $parent ) {

		$form = $this->get_current_form();

		$mapped_field_id   = $this->get_setting( $field['name'] );
		$mapped_field      = GFFormsModel::get_field( $form, $mapped_field_id );
		$mapped_field_page = $mapped_field->pageNumber;

		$cc_field = $this->get_credit_card_field( $form );
		$cc_page  = $cc_field->pageNumber;

		if ( $mapped_field_page > $cc_page ) {
			$this->set_field_error( $field, esc_html__( 'The selected field needs to be on the same page as the Credit Card field or a previous page.', 'gravityformsstripe' ) );
		}

	}

	/**
	 * Get the slug for the card type returned by Stripe.js
	 *
	 * @param string $type The possible types are "Visa", "MasterCard", "American Express", "Discover", "Diners Club", and "JCB" or "Unknown".
	 *
	 * @return string
	 */
	public function get_card_slug( $type ) {

		if ( $type ) {
			$card_types = GFCommon::get_card_types();

			foreach ( $card_types as $card ) {
				if ( $type == rgar( $card, 'name' ) ) {
					return rgar( $card, 'slug' );
				}
			}
		}

		return $type;
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

}