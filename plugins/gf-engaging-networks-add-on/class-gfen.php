<?php
/**
 * GF Add-On class for managing Feed.
 */

GFForms::include_feed_addon_framework();

/**
 * Main class for handling the Engaging Networks feed
 */
class GFEN extends GFFeedAddOn {

	protected $_version = GF_EN_VERSION;
	protected $_min_gravityforms_version = '1.9.3';
	protected $_slug = 'gravityforms-en';
	protected $_path = 'gravityforms-en/gravityforms-en.php';
	protected $_full_path = __FILE__;
	protected $_url = 'https://cornershopcreative.com';
	protected $_title = 'Gravity Forms Engaging Networks Add-On';
	protected $_short_title = 'Engaging Networks';

	/**
	 * Members plugin integration
	 */
	protected $_capabilities = array( 'gravityforms_en', 'gravityforms_en_uninstall' );

	/**
	 * Permissions
	 */
	protected $_capabilities_settings_page = 'gravityforms_en';
	protected $_capabilities_form_settings = 'gravityforms_en';
	protected $_capabilities_uninstall = 'gravityforms_en_uninstall';

	/**
	 * Other stuff
	 */
	private static $settings;
	private static $api;
	private static $_instance = null;
	private $tags = null;
	/** As of GF 2.2, we can do this to speed up things for end users. */
	public $_async_feed_processing = true;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFEN
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new GFEN();
		}

		return self::$_instance;
	}


	/**
	 * Load javascipt file for our integration admin page.
	 */
	public function scripts() {

		$scripts = array(
			array(
				'handle'    => 'gravityforms_en',
				'src'       => plugin_dir_url( __FILE__ ) . 'assets/gf-en.js',
				'version'   => GF_EN_VERSION,
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
				'callback'  => '',
				'enqueue'   => array(
					array(
						'admin_page' => array( 'form_settings' ),
						'tab'        => '',
					),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	// # FEED PROCESSING -----------------------------------------------------------------------------------------------
	/**
	 * Process the feed, add the submission to EN.
	 *
	 * @param array $feed  The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form  The form object currently being processed.
	 *
	 * @return void
	 */
	public function process_feed( $feed, $entry, $form ) {
		$this->log_debug( __METHOD__ . '(): Processing feed.' );

		// Connect to EN
		$api = $this->get_api();
		if ( ! is_object( $api ) ) {
			$this->log_error( __METHOD__ . '(): Failed to set up the API.' );
			return;
		}

		$feed_meta = $feed['meta'];

		// retrieve name => value pairs for all fields mapped in the 'supporterFields' field map
		$field_map = $this->get_field_map_fields( $feed, 'supporterFields' );

		// As EN seems to have different names for the email field, loop through until we find one.
		$email_field_names = array( 'supporter|email', 'supporter|Email+Address', 'supporter|Email', 'supporter|Email%20Address' );
		$email = '';
		$num_values = count( $email_field_names );

		do {
			// @TODO refactor to eliminate "Undefined index" warnings
			$email      = $this->get_field_value( $form, $entry, $field_map[ array_shift( $email_field_names ) ] );
			$num_values = count( $email_field_names );
		} while ( empty( $email ) && $num_values );

		// Get the questions/opt-ins
		$field_map = array_merge( $field_map, $this->get_field_map_fields( $feed, 'questionFields' ) );

		// See if we have a Page we're processing
		$page_id   = $feed['meta']['pageId'];
		if ( 'None' !== $page_id ) {
			list( $page_type, $page_id ) = explode( '-', $page_id );
		} else {
			$page_id   = false;
			$page_type = false;
		}

		/*
			@todo if we're a donation page, load up those fields
			if ( 'nd' === $page_type ) {
				$field_map = array_merge( $field_map, $this->get_field_map_fields( $feed, 'ndFields' ) );
			}
		*/

		// abort if email is invalid
		if ( GFCommon::is_invalid_or_empty_email( $email ) ) {
			$this->log_error( __METHOD__ . '(): A valid email address must be provided.' );
			$this->log_error( wp_json_encode( $field_map ) );
			return;
		}

		$override_empty_fields = gf_apply_filters( 'gform_en_override_empty_fields', $form['id'], true, $form, $entry, $feed );
		if ( ! $override_empty_fields ) {
			$this->log_debug( __METHOD__ . '(): Empty fields will not be overridden.' );
		}

		$post_vars = array();

		// Loop through the fields, populating $post_vars as necessary
		foreach ( $field_map as $name => $field_id ) {

			// We can skip unassigned stuff
			if ( '' === $field_id ) {
				continue;
			}

			$field_value = $this->get_field_value( $form, $entry, $field_id );

			// abbreviate things to match EN format
			if ( 'supporter|Country' === $name || 'supporter|State' === $name ) {
				$field_value = $api->abbreviate( $field_value, $name );
			}

			if ( empty( $field_value ) && ! $override_empty_fields ) {
				continue;
			} else {
				// For exploding out keys with pipe(|) dividers into sub-arrays. Periods would make more sense but GF doesn't seem to like them.
				// see https://stackoverflow.com/questions/37356391/php-explode-string-key-into-multidimensional-array-with-values/37356572
				$temp = &$post_vars;
				foreach ( explode( '|', $name ) as $key ) {
					$temp =& $temp[ urldecode( $key ) ];
				}
				$temp = $field_value;
			}
		}//end foreach

		try {
			$params = $post_vars;
			$params = gf_apply_filters( 'gform_en_args_pre_post', $form['id'], $params, $form, $entry, $feed );

			if ( ! $page_id ) {
				$this->log_debug( __METHOD__ . '(): Calling subscribe, Parameters ' . print_r( $params, true ) ); // phpcs:ignore
				$call = $api->save_supporter( $params );
			} else {
				$params = $this->fix_empty_questions( $params );
				$this->log_debug( __METHOD__ . '(): Calling page/' . $page_id . '/process Parameters ' . print_r( $params, true ) ); // phpcs:ignore
				$call = $api->post( '/page/' . $page_id . '/process', wp_json_encode( $params ) );
			}
		} catch ( Exception $e ) {

			$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );
		}

		// if we successfully added the supporter, try to do other stuff
		if ( isset( $call->id ) && $call->id ) {

			$this->log_debug( __METHOD__ . "(): API subscribe for $email successful with token " . $api->ens_auth_token );
			$cons_id = $call->id;

		} else {
			$this->log_error( __METHOD__ . "(): API subscribe for $email failed." );
			$this->log_error( 'Call object info: ' . wp_json_encode( $call ) );
			$errors = $api->getErrors();
			foreach ( $errors as $err ) {
				$this->log_error( 'API error: ' . $err );
			}
		}
	}

	/**
	 * Returns the value of the selected field.
	 *
	 * @param array  $form      The form object currently being processed.
	 * @param array  $entry     The entry object currently being processed.
	 * @param string $field_id The ID of the field being processed.
	 *
	 * @return array
	 */
	public function get_field_value( $form, $entry, $field_id ) {
		$field_value = '';

		switch ( strtolower( $field_id ) ) {

			case 'form_title':
				$field_value = rgar( $form, 'title' );
				break;

			case 'date_created':
				$date_created = rgar( $entry, strtolower( $field_id ) );
				if ( empty( $date_created ) ) {
					// the date created may not yet be populated if this function is called during the validation phase and the entry is not yet created
					$field_value = gmdate( 'Y-m-d H:i:s' );
				} else {
					$field_value = $date_created;
				}
				break;

			case 'ip':
			case 'source_url':
				$field_value = rgar( $entry, strtolower( $field_id ) );
				break;

			default:
				$field = GFFormsModel::get_field( $form, $field_id );

				if ( is_object( $field ) ) {

					$is_integer = intval( $field_id ) === $field_id;
					$input_type = RGFormsModel::get_input_type( $field );

					if ( $is_integer && 'address' === $input_type ) {

						$field_value = $this->get_full_address( $entry, $field_id );

					} elseif ( $is_integer && 'name' === $input_type ) {

						$field_value = $this->get_full_name( $entry, $field_id );

					} elseif ( $is_integer && 'checkbox' === $input_type ) {

						$selected = array();
						foreach ( $field->inputs as $input ) {
							$index = (string) $input['id'];
							if ( ! rgempty( $index, $entry ) ) {
								$selected[] = rgar( $entry, $index );
							}
						}
						$field_value = implode( '|', $selected );

					} elseif ( 'phone' === $input_type && 'standard' === $field->phoneFormat ) { // phpcs:ignore

						// reformat phone numbers
						// format: NPA-NXX-LINE (404-555-1212) when US/CAN
						$field_value = rgar( $entry, $field_id );
						if ( ! empty( $field_value ) && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches ) ) {
							$field_value = sprintf( '%s-%s-%s', $matches[1], $matches[2], $matches[3] );
						}
					} else {

						if ( is_callable( array( 'GF_Field', 'get_value_export' ) ) ) {
							$field_value = $field->get_value_export( $entry, $field_id );
						} else {
							$field_value = rgar( $entry, $field_id );
						}
					}//end if
				} else {

					$field_value = rgar( $entry, $field_id );

				}//end if
		}//end switch

		return $field_value;
	}


	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------
	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init() {

		parent::init();

	}

	/**
	 * Clear the cached settings on uninstall.
	 *
	 * @return bool
	 */
	public function uninstall() {

		parent::uninstall();

		GFCache::delete( 'en_plugin_settings' );

		return true;
	}

	// ------- Plugin settings -------
	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => '',
				'description' => '<p>' . __( 'Use Gravity Forms to collect user information and feed it to your Engaging Networks data. To obtain an API key you will need to <a href="https://engagingnetworks.support/knowledge-base/permissions-creating-an-api-user-not-responsive/" target="_blank" rel="noreferrer">create an API user in Engaging Networks</a>.', 'gfen' ) . '</p>',
				'fields'      => array(
					array(
						'name'              => 'en_api_key',
						'label'             => esc_html__( 'API key', 'gfen' ),
						'type'              => 'text',
						'class'             => 'medium wide',
						'tooltip'           => esc_html__( 'Enter your Engaging Networks API key. Note that you must have your EN API configured to allow connections from your site’s IP address.', 'gfen' ),
					),
					array(
						'name'              => 'en_datacenter',
						'label'             => esc_html__( 'EN URL', 'gfen' ),
						'type'              => 'radio',
						'tooltip'           => esc_html__( 'Choose the URL format of your EN account, which correlates to the “data center” your account uses.', 'gfen' ),
						'default_value'     => 'www',
						'choices'           => array(
							array(
								'label' => 'https://www.e-activist.com',
								'value' => 'www',
							),
							array(
								'label' => 'https://us.e-activist.com',
								'value' => 'us',
							),
						),
					),
				),
			),

		);
	}

	/**
	 * Gets the configuration for this EN API setup.
	 *
	 * @return array The post data containing the updated settings.
	 */
	public function get_posted_settings() {
		$post_data = parent::get_posted_settings();

		if ( $this->is_plugin_settings( $this->_slug ) && $this->is_save_postback() && ! empty( $post_data ) ) {

			$feed_count = $this->count_feeds();

			if ( $feed_count > 0 ) {
				$settings                  = $this->get_previous_settings();
				$settings['en_api_key']    = rgar( $post_data, 'en_api_key' );
				$settings['en_datacenter'] = rgar( $post_data, 'en_datacenter' );

				if ( ! $this->is_valid_en_auth( $settings['en_api_key'], $settings['en_datacenter'] ) ) {
					$server_ip = $this->get_server_ip();
					// translators: placeholder is server's IP address.
					GFCommon::add_error_message( sprintf( __( 'Unable to connect to Engaging Networks with the provided API key. Are you sure this server’s IP address (possibly %s) is authorized on the specified data center?', 'gfen' ), $server_ip ) );
				}

				return $settings;
			} else {
				GFCache::delete( 'en_plugin_settings' );
			}
		}//end if
		return $post_data;
	}

	/**
	 * Count how many EN feeds exist. Presumably this'll be just one, but the Feeds framework allows for more
	 *
	 * @return int
	 */
	public function count_feeds() {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->prefix}gf_addon_feed WHERE addon_slug=%s", $this->_slug ) );
	}

	// ------- Feed list page -------
	/**
	 * Prevent feeds being listed or created if the auth isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		$settings = $this->get_plugin_settings();

		return $this->is_valid_en_auth( $settings['en_api_key'], $settings['en_datacenter'] );
	}

	/**
	 * If the api key is invalid or empty return the appropriate message.
	 *
	 * @return string
	 */
	public function configure_addon_message() {

		// translators: placeholder is Gravity Form short title
		$settings_label = sprintf( esc_html__( '%s Settings', 'gravityforms' ), $this->get_short_title() );
		// translators: placeholders are settings url and settings label
		$settings_link  = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_plugin_settings_url() ), $settings_label );

		$settings = $this->get_plugin_settings();

		if ( rgempty( 'en_api_key', $settings ) ) {
			// translators: placeholder is anchor and title for settings link.
			return sprintf( esc_html__( 'To get started, please configure your %s.', 'gravityforms' ), $settings_link );
		}

		// translators: placeholder is anchor and title for settings link.
		return sprintf( esc_html__( 'Unable to connect to EN with the provided credentials. Please make sure you have entered valid information on the %s page.', 'gfen' ), $settings_link );

	}

	/**
	 * Display a warning message instead of the feeds if the API key isn't valid.
	 *
	 * @param array   $form The form currently being edited.
	 * @param integer $feed_id The current feed ID.
	 */
	public function feed_edit_page( $form, $feed_id ) {

		if ( ! $this->can_create_feed() ) {

			echo '<h3><span>' . wp_kses( $this->feed_settings_title() ) . '</span></h3>';
			echo '<div>' . wp_kses( $this->configure_addon_message() ) . '</div>';

			return;
		}

		parent::feed_edit_page( $form, $feed_id );
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'   => esc_html__( 'Name', 'gfen' ),
		);
	}


	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @return array The feed settings.
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'EN Feed Settings', 'gfen' ),
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'gfen' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => '<h6>' . esc_html__( 'Name', 'gfen' ) . '</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gfen' ),
					),
					array(
						'name'     => 'pageId',
						'label'    => esc_html__( 'Related Page', 'gfen' ),
						'type'     => 'select',
						'tooltip'  => '<h6>' . esc_html__( 'Campaign Page', 'gfen' ) . '</h6>' . esc_html__( 'Select the Page this form feeds into (optional). Note: For performance reasons this list is only updated hourly.', 'gfen' ),
						'choices'  => $this->en_page_choices(),
					),
					array(
						'name'      => 'supporterFields',
						'label'     => esc_html__( 'Supporter Fields', 'gfen' ),
						'type'      => 'field_map',
						'field_map' => $this->supporter_field_map(),
						'tooltip'   => '<h6>' . esc_html__( 'Supporter Fields', 'gfen' ) . '</h6>' . esc_html__( 'Associate your EN constituent fields with the appropriate Gravity Form fields.', 'gfen' ),
					),
					array(
						'name'      => 'questionFields',
						'label'     => esc_html__( 'Question/Opt-In Fields', 'gfen' ),
						'type'      => 'field_map',
						'field_map' => $this->question_field_map(),
						'tooltip'   => '<h6>' . esc_html__( 'Question and Opt-In Fields', 'gfen' ) . '</h6>' . esc_html__( 'Associate your EN Questions and Opt-Ins with the appropriate Gravity Form fields.', 'gfen' ),
					),
					array(
						'name'      => 'pageFields',
						'label'     => esc_html__( 'Page Fields', 'gfen' ),
						'type'      => 'field_map',
						'field_map' => $this->page_field_map(),
						'tooltip'   => '<h6>' . esc_html__( 'Page Fields', 'gfen' ) . '</h6>' . esc_html__( 'Optionally associate GF fields with EN page-specific fields.', 'gfen' ),
					),
					array(
						'name'      => 'ndFields',
						'label'     => esc_html__( 'Donation Fields', 'gfen' ),
						'type'      => 'field_map',
						'field_map' => $this->donation_field_map(),
						'tooltip'   => '<h6>' . esc_html__( 'Donation Fields', 'gfen' ) . '</h6>' . esc_html__( 'Associate your donation page fields with the appropriate Gravity Form fields.', 'gfen' ),
					),
					array(
						'name'    => 'optinCondition',
						'label'   => esc_html__( 'Conditional Logic', 'gfen' ),
						'type'    => 'feed_condition',
						'tooltip' => '<h6>' . esc_html__( 'Conditional Logic', 'gfen' ) . '</h6>' . esc_html__( 'When conditional logic is enabled, form submissions will only be passed to EN when the conditions are met. When disabled all form submissions will be exported.', 'gfen' ),
					),
					array(
						'type' => 'save',
					),
				),
			),
		);
	}

	/**
	 * Return an array of EN constituent fields which can be mapped to the Form fields/entry meta.
	 *
	 * @return array
	 */
	public function supporter_field_map() {

		$field_map = array();

		// New, API-way of getting fields
		$supporter_fields = $this->get_api()->get_supporter_fields();

		// each $field is stdClass with properties of id, name, tag, and property
		// best guess is that 'property' is what should be posted to /supporter, but we'll see
		foreach ( $supporter_fields as $field ) {
			$config_array = array(
				'name'       => 'supporter|' . rawurlencode( $field->name ),
				'label'      => $field->name,
				'required'   => 'emailAddress' === $field->property ? true : false,
				'field_type' => 'emailAddress' === $field->property ? array( 'email', 'hidden' ) : '',
			);
			$field_map[] = $config_array;
		}

		return $field_map;
	}


	/**
	 * Return an array of EN 'question' fields which can be mapped to the Form fields/entry meta.
	 *
	 * @return array
	 */
	public function question_field_map() {

		$field_map = array();

		// API-way of getting fields
		$question_fields = $this->get_api()->get_questions();

		// each $field is stdClass with properties of id, questionId, name, type
		// 'type' is GEN for questions and OPT for opt-ins
		foreach ( $question_fields as $field ) {
			$config_array = array(
				'name'       => 'supporter|questions|' . rawurlencode( $field->name ),
				'label'      => ( 'OPT' === $field->type ) ? __( 'Opt-in: ' ) . $field->name : $field->name,
				'required'   => false,
				'field_type' => ( 'OPT' === $field->type ) ? array( 'checkbox', 'radio', 'hidden' ) : '',
			);
			if ( 'OPT' === $field->type ) {
				$config_array['tooltip'] = __( "Value must be 'Y' or 'N' for EN to accept, other values are ignored." );
				array_unshift( $field_map, $config_array );
			} else {
				$field_map[] = $config_array;
			}
		}

		return $field_map;
	}


	/**
	 * Return an array of EN donation fields which can be mapped to the Form fields/entry meta.
	 *
	 * @return array
	 */
	public function donation_field_map() {

		$field_map = array();

		$fields = array(
			// Hard-coded because EN's REST API offers no way to fetch a list of defined transation fields
			'Donation Amount'       => 'donationAmt',
			'Recurring?'            => 'recurrpay',
			'Recurring Frequency'   => 'recurrfreq',
			'Honoree Name'          => 'honname',
			'Inform Name'           => 'infname',
			'Payment Type'          => 'paymenttype',
			'Card Number'           => 'ccnumber',
			'Card Expiry (MM/YYYY)' => 'ccexpire',
			'Card CVV'              => 'ccvv',

			/*
			Misc others per email from Josh
			'othamt1'               => 'othamt1',
			'othamt2'               => 'othamt2',
			'othamt3'               => 'othamt3',
			'othamt4'               => 'othamt4',
			*/
		);

		foreach ( $fields as $key => $name ) {
			$config_array = array(
				'name'       => 'transaction|' . rawurlencode( $name ),
				'label'      => $key,
				'required'   => false,
			);
			$field_map[] = $config_array;
		}

		return $field_map;
	}


	/**
	 * Return an array of EN fields which can be submitted only to the /page/process endpoint.
	 *
	 * @return array
	 */
	public function page_field_map() {

		$field_map = array();

		$fields = array(
			// Hard-coded because EN's REST API offers no way to fetch a list of defined transation fields
			'trackingId' => 'Tracking ID',
			'appealCode' => 'Appeal Code',
			'txn1'       => 'UTM Source',
			'txn2'       => 'UTM Medium',
			'txn3'       => 'UTM Campaign',
			'txn4'       => 'UTM Content',
			'txn5'       => 'UTM Term',
			'txn6'       => 'Custom 1',
			'txn7'       => 'Custom 2',
			'txn8'       => 'Custom 3',
			'txn9'       => 'Custom 4',
			'txn10'      => 'Custom 5',
		);

		foreach ( $fields as $key => $name ) {
			$config_array = array(
				'name'       => $key,
				'label'      => $name,
				'required'   => false,
			);
			$field_map[] = $config_array;
		}

		return $field_map;
	}


	/**
	 * Gets a list of EN Pages to show in the select list
	 *
	 * @return array An array of label/value/optgroup values suitable to pass into a 'choices' key in a GF select field.
	 */
	private function en_page_choices() {

		$api = $this->get_api();
		$pages_by_type = $api->get_all_pages();

		// First choice is always 'none'
		$choices = array(
			array(
				'label' => 'None',
				'name' => '',
			),
		);

		// Pages are grouped by type, loop through the types.
		foreach ( $pages_by_type as $page_type => $pages ) {

			// If this type has Pages, setup the optgroup
			if ( count( $pages ) ) {

				// Get all the Pages of the current type.
				$subchoices = array();
				foreach ( $pages as $page ) {
					$label = $page->name . ' - ' . $page->campaignStatus; // phpcs:ignore
					$subchoices[] = array(
						'label' => $label,
						'value' => $page_type . '-' . $page->id,
					);
				}

				// Actually populate the optgroup choices.
				$choices[] = array(
					'label' => $api->get_friendly_page_type( $page_type ),
					'value' => 'optgroup',
					'choices' => $subchoices,
				);

			}
		}//end foreach

		return $choices;
	}

	// # HELPERS -------------------------------------------------------------------------------------------------------

	/**
	 * Checks to make sure the EN credentials stored in settings actually work!
	 */
	public function is_valid_en_auth( $api_key, $datacenter ) {
		if ( ! class_exists( 'ENConnector' ) ) {
			require_once( 'class-enconnector.php' );
		}
		$api = ENConnector::initialize( $api_key, $datacenter );
		if ( count( $api->getErrors() ) ) {
			return false;
		}
		return $api;
	}

	/**
	 * Validate the provided api_key and return an instance of ENConnector class.
	 *
	 * @return ENConnector|null
	 */
	public function get_api() {

		if ( self::$api ) {
			return self::$api;
		}

		if ( self::$settings ) {
			$settings = self::$settings;
		} else {
			$settings = $this->get_plugin_settings();
			self::$settings = $settings;
		}

		$api = null;

		if ( ! class_exists( 'ENConnector' ) ) {
			require_once( 'class-enconnector.php' );
		}

		try {
			if ( rgempty( 'en_datacenter', $settings ) ) {
				$settings['en_datacenter'] = 'www';
			}
			$api = ENConnector::initialize( $settings['en_api_key'], $settings['en_datacenter'] );

		} catch ( Exception $e ) {
			$this->log_error( __METHOD__ . '(): Failed to set up the API.' );
			$this->log_error( __METHOD__ . '(): ' . $e->getCode() . ' - ' . $e->getMessage() );
			return null;
		}

		self::$api = $api;
		return self::$api;
	}


	/**
	 * Returns the combined value of the specified Address field.
	 * Street 2 and Country are the only inputs not required by MailChimp.
	 * If other inputs are missing MailChimp will not store the field value, we will pass a hyphen when an input is empty.
	 * MailChimp requires the inputs be delimited by 2 spaces.
	 *
	 * @param array  $entry The entry currently being processed.
	 * @param string $field_id The ID of the field to retrieve the value for.
	 *
	 * @return string
	 */
	public function get_full_address( $entry, $field_id ) {
		$street_value  = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.1' ) ) );
		$street2_value = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.2' ) ) );
		$city_value    = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.3' ) ) );
		$state_value   = str_replace( '  ', ' ', trim( rgar( $entry, $field_id . '.4' ) ) );
		$zip_value     = trim( rgar( $entry, $field_id . '.5' ) );
		$country_value = trim( rgar( $entry, $field_id . '.6' ) );

		if ( ! empty( $country_value ) ) {
			$country_value = GF_Fields::get( 'address' )->get_country_code( $country_value );
		}

		$address = array(
			! empty( $street_value ) ? $street_value : '-',
			$street2_value,
			! empty( $city_value ) ? $city_value : '-',
			! empty( $state_value ) ? $state_value : '-',
			! empty( $zip_value ) ? $zip_value : '-',
			$country_value,
		);

		return implode( '  ', $address );
	}


	/**
	 * Check that an email address is an email address.
	 */
	static function is_valid_email( $email ) {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}


	/**
	 * We want to turn arrays and pipe-separated text fields into simple arrays of tag names
	 */
	private function normalize_tags( $tag_field ) {
		$tags = array();
		if ( is_string( $tag_field ) ) {
			$tags = explode( '|', $tag_field );
		} elseif ( is_array( $tag_field ) ) {
			foreach ( $tag_field as $key => $value ) {
				$tags[] = $value;
			}
		}

		return array_map( 'trim', $tags );
	}


	/**
	 * If an "Opt in" has an empty value, EN chokes. Try unsetting any questions that are empty...
	 */
	private function fix_empty_questions( $params ) {
		if ( isset( $params['supporter']['questions'] ) && is_array( $params['supporter']['questions'] ) ) {
			foreach ( $params['supporter']['questions'] as $question => $answer ) {
				if ( empty( trim( $answer ) ) ) {
					unset( $params['supporter']['questions'][ $question ] );
				}
			}
		}
		return $params;
	}


	/**
	 * Try to determine this server's IP address to help with troubleshooting connection issues.
	 */
	private function get_server_ip() {
		$return = '[' . __( 'UNKNOWN', 'gfen' ) . ']';
		$response = wp_remote_get( 'http://ipecho.net/plain' );
		if ( is_array( $response ) ) {
			$return = $response['body'];
		}
		return $return;
	}

}
