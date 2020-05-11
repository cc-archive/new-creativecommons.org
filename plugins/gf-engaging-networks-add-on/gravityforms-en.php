<?php
/**
Plugin Name: Integration for Gravity Forms and Engaging Networks
Plugin URI: https://cornershopcreative.com/product/gravity-forms-add-ons/
Description: Integrates Gravity Forms with the Engaging Networks CRM, allowing form submissions to automatically create/update supporters and pages
Version: 2.1.2
Author: Cornershop Creative
Author URI: https://cornershopcreative.com
Text Domain: gfen
 */

define( 'GF_EN_VERSION', '2.1.2' );

add_action( 'gform_loaded', array( 'GF_EN_Bootstrap', 'load' ), 5 );

/**
 * Tells GravityForms to load up the Add-On
 */
class GF_EN_Bootstrap {

	/**
	 * Load our functionality if GF can handle it.
	 */
	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once( 'class-gfen.php' );

		GFAddOn::register( 'GFEN' );
	}
}

/**
 * Get an instance of our object. Not sure this is necessary?
 */
function gf_en() {
	return GFEN::get_instance();
}
