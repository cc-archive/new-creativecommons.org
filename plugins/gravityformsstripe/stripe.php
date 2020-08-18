<?php
/**
 * Plugin Name: Gravity Forms Stripe Add-On
 * Plugin URI: https://gravityforms.com
 * Description: Integrates Gravity Forms with Stripe, enabling end users to purchase goods and services through Gravity Forms.
 * Version: 3.7.3
 * Author: Gravity Forms
 * Author URI: https://gravityforms.com
 * License: GPL-2.0+
 * Text Domain: gravityformsstripe
 * Domain Path: /languages
 *
 * ------------------------------------------------------------------------
 * Copyright 2009 - 2020 Rocketgenius, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

defined( 'ABSPATH' ) || die();

define( 'GF_STRIPE_VERSION', '3.7.3' );

// If Gravity Forms is loaded, bootstrap the Stripe Add-On.
add_action( 'gform_loaded', array( 'GF_Stripe_Bootstrap', 'load' ), 5 );

/**
 * Class GF_Stripe_Bootstrap
 *
 * Handles the loading of the Stripe Add-On and registers with the Add-On framework.
 *
 * @since 1.0.0
 */
class GF_Stripe_Bootstrap {

	/**
	 * If the Payment Add-On Framework exists, Stripe Add-On is loaded.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @uses GFAddOn::register()
	 *
	 * @return void
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gf-stripe.php' );

		GFAddOn::register( 'GFStripe' );

	}

}

/**
 * Obtains and returns an instance of the GFStripe class
 *
 * @since  1.0.0
 * @access public
 *
 * @uses GFStripe::get_instance()
 *
 * @return object GFStripe
 */
function gf_stripe() {
	return GFStripe::get_instance();
}
