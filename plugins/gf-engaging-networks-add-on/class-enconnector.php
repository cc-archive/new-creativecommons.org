<?php
/**
 * Connects to the EN REST API to get information about configured actions.
 * See bit.ly/ens_services for documentation on said API.
 *
 * @author Ben Byrne, Cornershop Creative
 * @since May 26, 2010
 */

/**
 * The main class
 */
class ENConnector {

	protected static $instance = false;

	/**
	 * Gets the singleton instance of the EN connector.  You must call
	 * initialize() before you can call this function.
	 *
	 * @return GFENConnector The singleton instance, or false if it has not
	 *   yet been initialized.
	 */
	public static function instance() {
		return self::$instance;
	}

	/**
	 * Creates a new API connection and authenticates with the EN server.
	 *
	 * You must do this before calling
	 * any EN functions.
	 *
	 * @param string $api_key The user-provided API key for for EN.
	 * @param string $datacenter The URL prefix correlating to the EN datacenter to talk to.
	 * @return GFENConnector The newly-created GFENConnector singleton.
	 */
	public static function initialize( $api_key, $datacenter = 'www' ) {
		// For backwards compatibility, juuuust in case.
		if ( empty( $datacenter ) ) {
			$datacenter = 'www';
		}
		self::$instance = new ENConnector( $api_key, $datacenter );
		return self::$instance;
	}

	/** @var reference $ch The open CURL HTTP connection */
	protected $http = null;

	/** @var string $host The URL of the EN server */
	public $host = 'https://www.e-activist.com/ens/service';

	/** @var array $hosts The names and URLs of the EN datacenters */
	public $hosts = array(
		'www' => 'https://www.e-activist.com/ens/service',
		'us'  => 'https://us.e-activist.com/ens/service',
	);

	/** @var array $errors A list of connection errors */
	protected $errors = array();

	/** EN API key @var null  */
	public $api_key = null;

	/** EN API auth token @var null */
	public $ens_auth_token = null;

	/**
	 * Creates a new connection with the EN API.  You should use initialize()
	 * to create a singleton instead of calling this function directly.
	 */
	protected function __construct( $api_key, $datacenter ) {
		$this->api_key = $api_key;

		if ( 'www' !== $datacenter && array_key_exists( $datacenter, $this->hosts ) ) {
			$this->host = $this->hosts[ $datacenter ];
		}

		// Allow the host to be filtered in case it needs to change to us.e-activist.com or something
		$this->host = apply_filters( 'gf_en_api_base_url', $this->host );

		// Configure the HTTP connection (always POST, maintain cookies)
		$this->http = new WP_Http();

		$transient_name = 'en_token_' . md5( $api_key );

		// Use an existing cached auth token, or get a new one.
		if ( get_transient( $transient_name ) ) {

			// Found an existing one, use that.
			$auth_token = get_transient( $transient_name );
			$this->ens_auth_token = $auth_token;
			return;

		} else {

			// Authenticate against EN.
			$this->ens_auth_token = $api_key;
			$auth = $this->http->post( $this->host . '/authenticate', array(
				'headers' => array(
					'Content-Type'   => 'application/json; charset=UTF-8',
					'ens-auth-token' => $api_key,
				),
				'body' => $api_key,
			) );

			// Make sure we got out
			if ( ! isset( $auth ) ) {
				return;
			}

			// If we got a bad response, log it.
			if ( 200 !== $auth['response']['code'] ) {
				$this->errors[] = 'We were unable to authenticate with the server.';
				$this->errors[] = 'Response code was ' . $auth['response']['code'];
				$this->errors[] = 'Response was ' . wp_json_encode( $auth['response'] );
				return;
			}

			// If we got a good response, save the token and cache it for later.
			if ( ! empty( $auth['body'] ) ) {
				$response = json_decode( $auth['body'] );
				$this->ens_auth_token = $response->{'ens-auth-token'};
				// EN returns the expires time in milliseconds
				// (as of 3/30/2020, the milliseconds returned was 3600000 ms = 3600 seconds = 1 hour)
				if ( isset( $response->expires ) && is_numeric( $response->expires ) ) {
					$expires_time = $response->expires/1000;
				} else {
					$expires_time = HOUR_IN_SECONDS;
				}

				$this->errors[] = __( 'Auth token received was ', 'gravityforms-en' ) . $this->ens_auth_token;
				// Only cache the time if the expiry time is more than or equal to 30 minutes
				if ( $expires_time >= 1800 ) {
					set_transient( $transient_name, $response->{'ens-auth-token'}, $expires_time );
				}
			}
		}//end if
	}

	/**
	 * Convenience method to tacking on errors. Not called within object anywhere.
	 */
	public function addErrors( $errors ) {
		if ( is_string( $errors ) ) {
			$errors = array( $errors );
		}
		$this->errors = array_merge( $this->errors, $errors );
	}

	/**
	 * Gets a list of all the errors that have accumulated so far.
	 *
	 * @param boolean $reset If this set to false, the errors will be preserved
	 *   after this call.  Otherwise, they will be cleared.
	 * @return array<string> A list of error messages, or an empty list if there
	 *   have been no errors since the last time the list was reset.
	 */
	public function getErrors( $reset = true ) {
		$out = $this->errors;
		if ( $reset ) {
			$this->errors = array();
		}
		return $out;
	}

	/**
	 * Return true if the given XML is valid and has no errors.
	 *
	 * @param SimpleXMLElement $xml The XML document to check.
	 * @return boolean True if the XML exists and has no errors.
	 */
	public function success( $xml ) {
		return ! empty( $xml ) && ! isset( $xml->error );
	}


	/**
	 * Posts values to a URL and parses the resulting JSON string into
	 * an object or array of objects.
	 *
	 * @param string $path The path on the server, starting with /.
	 * @param array  $params An array of parameters to send in the request.
	 * @return array The response, parsed into arrays and hashes.
	 */
	public function post( $path, $params ) {

		// build an absolute URL if we have to
		if ( strpos( $path, 'http' ) === 0 ) {
			$url = $path;
		} else {
			$url = $this->host . $path;
		}

		$current_auth_token =  $this->ens_auth_token;

		// perform the API call
		$headers = array(
			'ens-auth-token' => $current_auth_token,
			'Content-Type'   => 'application/json; charset=UTF-8',
		);

		$res = $this->http->post(
			$url,
			array(
				'body' => $params,
				'headers' => $headers,
			)
		);

		$api_response_object = $this->handle_api_response( $res );

		// If the stored API auth token expired, then repeat the same call but this time using a new auth token key
		if ( $this->is_auth_token_expired( $api_response_object ) ) {
			$new_token = $this->force_new_token_generation();

			if ( false !== $new_token ) {
				$headers = array(
					'ens-auth-token' => $new_token,
					'Content-Type' => 'application/json; charset=UTF-8',
				);

				$res = $this->http->post(
					$url,
					array(
						'body' => $params,
						'headers' => $headers,
					)
				);

				return $this->handle_api_response( $res );
			}
		}

		return $api_response_object;
	}


	/**
	 * Posts values to a URL and parses the resulting JSON string into
	 * an object or array of objects.
	 *
	 * @param string $path The path on the server, starting with /.
	 * @param array  $params An array of parameters to send in the request.
	 * @return array The response, parsed into arrays and hashes.
	 */
	public function get( $path, $params = false ) {
		// build an absolute URL if we have to
		if ( strpos( $path, 'http' ) === 0 ) {
			$url = $path;
		} else {
			$url = $this->host . $path;
		}

		$current_auth_token =  $this->ens_auth_token;

		// perform the API call
		// We may be conflating body with params here if we want get_supporter to work
		$headers = array(
			'ens-auth-token' => $current_auth_token,
			'Content-Type' => 'application/json; charset=UTF-8',
		);

		$res = $this->http->get(
			$url,
			array(
				'body' => $params,
				'headers' => $headers,
			)
		);

		$api_response_object = $this->handle_api_response( $res );

		// If the stored API auth token expired, then repeat the same call but this time using a new auth token key
		if ( $this->is_auth_token_expired( $api_response_object ) ) {
			$new_token = $this->force_new_token_generation();

			if ( false !== $new_token ) {
				$headers = array(
					'ens-auth-token' => $new_token,
					'Content-Type' => 'application/json; charset=UTF-8',
				);

				$res = $this->http->get(
					$url,
					array(
						'body' => $params,
						'headers' => $headers,
					)
				);

				return $this->handle_api_response( $res );
			}
		}

		return $api_response_object;
	}

	/**
	 * Handle the Engaging Networks API response
	 *
	 * @param WP_HTTP_Response|WP_Error $response WordPress WP_HTTP Response object or WP_Error
	 *
	 * @return mixed|null Array of errors found in response or response as a JSON object
	 */
	public function handle_api_response( $response ) {
		// Perform a basic check.
		if ( empty( $response ) ) {
			$this->errors[] = __( 'Unable to connect to the server and receive a response.', 'gravityforms-en' );
			return null;
		} elseif ( is_wp_error( $response ) ) {
			$this->errors[] = __( 'WP Error: ', 'gravityforms-en' ) . $response->get_error_message();
			return null;
		}

		// Convert from a JSON object.
		$obj = json_decode( $response['body'] );
		if ( ! isset( $obj ) ) {
			$this->errors[] = __( 'Server provided invalid JSON:', 'gravityforms-en' ) . $response['body'];
		}

		// give back an object of the response
		return $obj;
	}


	/**
	 * Saves a supporter to the EN database.
	 *
	 * @param GFENObject|array $object The object to save. If it has a key parameter, an
	 *   existing record will be updated.
	 */
	public function save_supporter( $object ) {

		// Convert some field names, because the ENS API is "weird"
		$field_name_conversions = array();

		if ( is_object( $object ) || is_array( $object ) ) {

			if ( isset( $object['supporter'] ) ) {
				$object = $object['supporter'];
			}

			foreach ( $field_name_conversions as $existing_key => $new_key ) {
				if ( isset( $object[ $existing_key ] ) ) {
					$object[ $new_key ] = $object[ $existing_key ];
					unset( $object[ $existing_key ] );
				}
			}

			$object = wp_json_encode( $object );
		}

		return $this->post( '/supporter', $object );
	}


	/**
	 * Retrieves a supportre from the EN database.
	 *
	 * @param string $value The name of the table (action, action_content, etc.).
	 * @param string $type The object to save.  If it has a key parameter, an
	 *   existing record will be updated.
	 */
	public function get_supporter( $value, $type = 'id' ) {

		$params = array(
			'includeQuestions' => true,
		);

		if ( 'id' === $type ) {

			return $this->get( '/supporter/' . $value, wp_json_encode( $params ) );

		} else {

			$params['Email'] = $value;
			return $this->get( '/supporter', wp_json_encode( $params ) );

		}

	}


	/**
	 * Get all supporter fields defined in EN. This is cached to avoid thrashing the API.
	 */
	public function get_supporter_fields() {

		$fields = get_transient( 'en_fields_list' );

		if ( is_array( $fields ) ) {
			return $fields;
		}

		$fields = $this->get( '/supporter/fields' );

		if ( count( $fields ) ) {
			set_transient( 'en_fields_list', $fields, 5 * MINUTE_IN_SECONDS );
		}

		return $fields;
	}


	/**
	 * Get all 'question' fields. Opt-ins are also included here.
	 * Returns an array of objects with:
	 * id int
	 * questionId int
	 * name string OPT|GEN.... anything else?
	 * type
	 */
	public function get_questions() {

		$fields = get_transient( 'en_questions_list' );

		if ( is_array( $fields ) ) {
			return $fields;
		}

		$fields = $this->get( '/supporter/questions' );

		if ( count( $fields ) ) {
			set_transient( 'en_questions_list', $fields, 5 * MINUTE_IN_SECONDS );
		}

		return $fields;
	}


	/**
	 * This gets and returns a list of Pages from this EN account.
	 *
	 * @param string $type One of [dcf,mem,ems,unsub,pet,et,nd]. Optional, defaults to pet.
	 * @param string $status One of [live,new,tested]. Optional.
	 *
	 * @return array An array of Page objects, with properties of: id, campaignId,
	 *               name, title, type, subType, clientId, createdOn, modifiedOn,
	 *               campaignBaseUrl, campaignStatus, defaultLocale, and possibly
	 *               campaignAttributes, locales, and trackingParameters
	 */
	public function get_pages_by_type( $type = 'pet', $status = false ) {

		/**
		 * Types:
		 * dcf   = data capture form?
		 * mem   = membership?
		 * ems   = (email) sign-up form
		 * unsub = email unsubscribe
		 * pet   = petition
		 * et    = email to target?
		 * nd    =
		 */

		$params = array(
			'type' => $type,
		);

		if ( in_array( $status, array( 'live', 'new', 'tested' ), true ) ) {
			$params['status'] = $status;
		}

		return $this->get( '/page', $params );
	}


	/**
	 * Gets and returns information about a particular EN page.
	 *
	 * @return object the Page object from EN, which doesn't have everything I'd like. :)
	 */
	public function get_page_details( $page_id = false ) {
		return $this->get( '/page/' . $page_id );
	}


	/**
	 * Get ALL pages and return in an array
	 */
	public function get_all_pages() {

		$pages = get_transient( 'en_page_list' );

		if ( is_array( $pages ) ) {
			return $pages;
		}

		$pages = array();
		$page_types = array( 'dcf', 'mem', 'ems', 'unsub', 'pet', 'et', 'nd', 'ev', 'tw' );

		foreach ( $page_types as $type ) {
			$pages[ $type ] = $this->get_pages_by_type( $type );
		}

		if ( count( $pages ) ) {
			set_transient( 'en_page_list', $pages, 5 * MINUTE_IN_SECONDS );
		}

		return $pages;
	}


	/**
	 * Converts a machine-readable Page type into a human-readable one.
	 *
	 * @param string $type_code The machine-readable Page type.
	 *
	 * @return string The human-readable name for the given Page type.
	 */
	public function get_friendly_page_type( $type_code ) {

		$types = array(
			'dcf'   => 'Data Capture',
			'mem'   => 'Membership',
			'ems'   => 'Sign-Up Form',
			'unsub' => 'Email Unsubscribe',
			'pet'   => 'Petition',
			'et'    => 'Email to Target',
			'nd'    => 'Donation page',
			'ev'    => 'Event',
			'tw'    => 'Tweet',
		);

		if ( array_key_exists( $type_code, $types ) ) {
			return $types[ $type_code ];
		} else {
			return 'Unknown type: ' . $type_code;
		}

	}


	/**
	 * Helper method for turning states and countries into their abbreviations.
	 * Useful for normalizing user input, among other things.
	 */
	static function abbreviate( $value, $field_name ) {

		$states = array(
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VT' => 'Vermont',
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
			'AS' => 'America Samoa',
			'MP' => 'Northern Mariana Islands',
			'PR' => 'Puerto Rico',
			'VI' => 'Virgin Islands',
			'GU' => 'Guam',
			'AA' => 'Armed Forces Americas',
			'AE' => 'Armed Forces Europe',
			'AP' => 'Armed Forces Pacific',
			'AB' => 'Alberta',
			'BC' => 'British Columbia',
			'MB' => 'Manitoba',
			'NL' => 'Newfoundland and Labrador',
			'NB' => 'New Brunswick',
			'NS' => 'Nova Scotia',
			'NT' => 'Northwest Territories',
			'NU' => 'Nunavut',
			'ON' => 'Ontario',
			'PE' => 'Prince Edward Island',
			'QC' => 'Quebec',
			'SK' => 'Saskatchewan',
			'YT' => 'Yukon Territory',
			'ot' => 'Other',
		);

		$countries = array(
			'US' => 'United States',
			'AF' => 'Afghanistan',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AS' => 'American Samoa',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AI' => 'Anguilla',
			'AQ' => 'Antarctica',
			'AG' => 'Antigua and Barbuda',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AW' => 'Aruba',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BM' => 'Bermuda',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia',
			'BA' => 'Bosnia and Herzegovina',
			'BW' => 'Botswana',
			'BV' => 'Bouvet Island',
			'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory',
			'BN' => 'Brunei Darussalam',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CA' => 'Canada',
			'CV' => 'Cape Verde',
			'KY' => 'Cayman Islands',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CX' => 'Christmas Island',
			'CC' => 'Cocos (Keeling) Islands',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CG' => 'Congo',
			'CD' => 'Congo, The Democratic Republic of the',
			'CK' => 'Cook Islands',
			'CR' => 'Costa Rica',
			'CI' => "Cote D'Ivoire",
			'HR' => 'Croatia',
			'CU' => 'Cuba',
			'CW' => 'Curacao',
			'CY' => 'Cyprus',
			'CZ' => 'Czech Republic',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'TL' => 'East Timor',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'SV' => 'El Salvador',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands (Malvinas)',
			'FO' => 'Faroe Islands',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'FX' => 'France, Metropolitan',
			'GF' => 'French Guiana',
			'PF' => 'French Polynesia',
			'TF' => 'French Southern Territories',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GR' => 'Greece',
			'GL' => 'Greenland',
			'GD' => 'Grenada',
			'GP' => 'Guadeloupe',
			'GU' => 'Guam',
			'GT' => 'Guatemala',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HM' => 'Heard and McDonald Islands',
			'VA' => 'Holy See (Vatican City State)',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran, Islamic Republic of',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KI' => 'Kiribati',
			'KP' => "Korea, Dem. People's Republic of",
			'KR' => 'Korea, Republic of',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => "Lao People's Democratic Republic",
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libyan Arab Jamahiriya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'MO' => 'Macao',
			'MK' => 'Macedonia, Former Yugoslav Republic',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'MV' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MH' => 'Marshall Islands',
			'MQ' => 'Martinique',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'YT' => 'Mayotte',
			'MX' => 'Mexico',
			'FM' => 'Micronesia, Federated States of',
			'MD' => 'Moldova, Republic of',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'MS' => 'Montserrat',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar',
			'NA' => 'Namibia',
			'NR' => 'Nauru',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'NC' => 'New Caledonia',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NU' => 'Niue',
			'NF' => 'Norfolk Island',
			'MP' => 'Northern Mariana Islands',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PW' => 'Palau',
			'PS' => 'Palestinian Territory, Occupied',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Philippines',
			'PN' => 'Pitcairn',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'PR' => 'Puerto Rico',
			'QA' => 'Qatar',
			'RE' => 'Reunion',
			'RO' => 'Romania',
			'RU' => 'Russian Federation',
			'RW' => 'Rwanda',
			'SH' => 'Saint Helena',
			'KN' => 'Saint Kitts and Nevis',
			'LC' => 'Saint Lucia',
			'PM' => 'Saint Pierre and Miquelon',
			'VC' => 'Saint Vincent and the Grenadines',
			'WS' => 'Samoa',
			'SM' => 'San Marino',
			'ST' => 'Sao Tome and Principe',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'SP' => 'Serbia',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SX' => 'Sint Maarten',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'SB' => 'Solomon Islands',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'SS' => 'South Sudan',
			'GS' => 'S. Georgia and S. Sandwich Islands',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SJ' => 'Svalbard and Jan Mayen',
			'SZ' => 'Swaziland',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syrian Arab Republic',
			'TW' => 'Taiwan',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania, United Republic of',
			'TH' => 'Thailand',
			'TG' => 'Togo',
			'TK' => 'Tokelau',
			'TO' => 'Tonga',
			'TT' => 'Trinidad and Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'TC' => 'Turks and Caicos Islands',
			'TV' => 'Tuvalu',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'GB' => 'United Kingdom',
			'UM' => 'United States Outlying Islands',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VE' => 'Venezuela',
			'VN' => 'Vietnam',
			'VG' => 'Virgin Islands, British',
			'VI' => 'Virgin Islands, U.S.',
			'WF' => 'Wallis and Futuna',
			'EH' => 'Western Sahara',
			'YE' => 'Yemen',
			'YU' => 'Yugoslavia',
			'ZR' => 'Zaire',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		);

		if ( 'supporter|Country' === $field_name ) {
			$reference_array = $countries;
		} else {
			$reference_array = $states;
		}

		$abbreviation = array_search( $value, $reference_array, true );
		if ( $abbreviation ) {
			return $abbreviation;
		}

		return $value;

	}

	/**
	 * Determine if the EN response includes a message about the auth token being expired
	 *
	 * @param Object $en_response JSON response object from the EN API
	 *
	 * @return bool True if the auth token is expired and false otherwise
	 */
	public function is_auth_token_expired( $en_response ) {
		if ( is_object( $en_response ) && isset( $en_response->message ) && 'Invalid ens-auth-token' === $en_response->message ) {
			return true;
		}

		return false;
	}

	/**
	 * Generate a new EN API token
	 *
	 * @return string|bool New API auth token if successful. False if we could not generate a new token
	 */
	public function force_new_token_generation() {
		$transient_name = 'en_token_' . md5( $this->api_key );
		delete_transient( $transient_name );
		$new_self = new ENConnector( $this->api_key, $this->host );
		$new_auth_token = $new_self->ens_auth_token;
		
		if ( ! empty( $new_auth_token ) && $this->ens_auth_token !== $new_auth_token ) {
			$this->ens_auth_token = $new_auth_token;
			return $new_auth_token;
		}

		return false;
	}
}
