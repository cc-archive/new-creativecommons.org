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
 * Class GFP_Google_Analytics_Ecommerce_API
 *
 * Handles sending requests to Measurement Protocol
 *
 * @since  1.0.0
 *
 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
 */
class GFP_Google_Analytics_Ecommerce_API {

	private $base_url = 'https://ssl.google-analytics.com/collect';

	private $version = '1';

	private $client_id = '';


	/**
	 * Constructor
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 */
	public function __construct() {
	}

	/**
	 *Send hit to Google Analytics
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @param $hit_type
	 * @param $args
	 *
	 * @return int|string
	 */
	public function send_hit( $hit_type, $args ) {

		$result = 0;

		if ( method_exists( $this, "generate_{$hit_type}" ) && is_callable( array( $this, "generate_{$hit_type}" ) ) ) {

			$hit = call_user_func( array( $this, "generate_{$hit_type}" ), $args );

		}

		if ( ! empty( $hit ) ) {

			$arguments[ 'user-agent' ] = $_SERVER[ 'HTTP_USER_AGENT' ];
			$arguments[ 'body' ]       = $hit;

			$response = wp_remote_post( $this->base_url, $arguments );

			$result = wp_remote_retrieve_response_code( $response );
		}

		return $result;

	}

	/**
	 * Generate transaction hit
	 *
	 * //All values sent to Google Analytics must be both UTF-8 and URL Encoded
	 *
	 * Example:
	 * v=1              // Version. text
	 * &tid=UA-XXXX-Y   // Tracking ID / Property ID. text
	 * &cid=555         // Anonymous Client ID. text
	 *
	 * &t=transaction   // Transaction hit type. text
	 * &ti=12345        // transaction ID. Required. text
	 * &ta=westernWear  // Transaction affiliation. text
	 * &tr=50.00        // Transaction revenue. currency
	 * &ts=32.00        // Transaction shipping. currency
	 * &tt=12.00        // Transaction tax. currency
	 * &cu=EUR          // Currency code. text
	 *
	 * //currency needs decimal point as delimiter
	 * //boolean = 1 or 0
	 *
	 * //no longer than 8192 bytes
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @param $args
	 *
	 * @return string
	 */
	public function generate_transaction( $args ) {

		$defaults = array(
			'v'   => $this->version,
			'tid' => '',
			'cid' => $this->get_client_id(),
			't'   => 'transaction',
			'ti'  => '',
		);

		$transaction_args = wp_parse_args( $args, $defaults );

		$transaction = http_build_query( $transaction_args );

		return $transaction;

	}

	/**
	 * Generate item hit
	 *
	 * Example:
	 * v=1              // Version.
	 * &tid=UA-XXXX-Y   // Tracking ID / Property ID.
	 * &cid=555         // Anonymous Client ID.
	 *
	 * &t=item          // Item hit type.
	 * &ti=12345        // Transaction ID. Required. text
	 * &in=sofa         // Item name. Required. text
	 * &ip=300          // Item price. currency
	 * &iq=2            // Item quantity. int
	 * &ic=u3eqds43     // Item code / SKU. text
	 * &iv=furniture    // Item variation / category. text
	 * &cu=EUR          // Currency code. text
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @param $args
	 *
	 * @return string
	 */
	public function generate_item( $args ) {

		//multiple items in a transaction, loop through?

		$defaults = array(
			'v'   => $this->version,
			'tid' => '',
			'cid' => $this->get_client_id(),
			't'   => 'item',
			'ti'  => '',
			'in'  => '',
		);

		$item_args = wp_parse_args( $args, $defaults );

		$item = http_build_query( $item_args );

		return $item;

	}

	/**
	 * Return the Current Client Id
	 *
	 * Adapted from https://github.com/ins0/google-measurement-php-client/blob/master/src/Racecore/GATracking/GATracking.php
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @return string
	 */
	public function get_client_id() {

		if ( empty( $this->client_id ) ) {

			// see if there's a tracking cookie
			if ( isset( $_COOKIE[ '_ga' ] ) ) {

				$gaCookie = explode( '.', $_COOKIE[ '_ga' ] );

				if ( isset( $gaCookie[ 2 ] ) ) {

					// check if uuid
					if ( $this->check_uuid( $gaCookie[ 2 ] ) ) {
						// uuid set in cookie
						$client_id = $gaCookie[ 2 ];

					} elseif ( isset( $gaCookie[ 2 ] ) && isset( $gaCookie[ 3 ] ) ) {
						// google old client id
						$client_id = "{$gaCookie[2]}.{$gaCookie[3]}";
					}

				}
			}

			if ( empty( $client_id ) ) {
				$client_id = $this->generate_client_id();
			}

			$this->client_id = $client_id;

		} else {

			$client_id = $this->client_id;

		}

		return $client_id;

	}

	/**
	 * Check if is a valid UUID v4
	 *
	 * Taken from https://github.com/ins0/google-measurement-php-client/blob/master/src/Racecore/GATracking/GATracking.php
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @param $uuid
	 *
	 * @return int
	 */
	private function check_uuid( $uuid ) {

		return preg_match(
			'#^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$#i',
			$uuid
		);

	}

	/**
	 * Generate client ID
	 *
	 * Taken from https://github.com/ins0/google-measurement-php-client/blob/master/src/Racecore/GATracking/GATracking.php
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @return string
	 */
	private function generate_client_id() {

		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			// 16 bits for "time_mid"
			mt_rand( 0, 0xffff ),
			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand( 0, 0x0fff ) | 0x4000,
			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand( 0, 0x3fff ) | 0x8000,
			// 48 bits for "node"
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff )
		);

	}

}