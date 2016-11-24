<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class GFP_Google_Analytics_Ecommerce
 *
 * Main plugin class
 *
 * @since  1.0.0
 *
 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
 */
class GFP_Google_Analytics_Ecommerce {

	/**
	 * Minimum Gravity Forms version allowed
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @var string
	 */
	private $min_gf_version = '1.8.18';

	/**
	 * Gravity Forms Add-On object
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @var GFP_Google_Analytics_Ecommerce_Addon
	 */
	private $addon = null;

	/**
	 * Constructor
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 */
	public function construct() {
	}

	/**
	 * Load WordPress functions
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 */
	public function run() {

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

	}


	/**
	 * Create GF Add-On
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 */
	public function plugins_loaded() {

		$this->load_textdomain();

		if ( class_exists( 'GFForms' ) ) {

			if ( ! class_exists( 'GFFeedAddOn' ) ) {

				GFForms::include_feed_addon_framework();

			}

			$this->addon = new GFP_Google_Analytics_Ecommerce_Addon( array(
				                                    'version'                    => GFP_GOOGLE_ANALYTICS_ECOMMERCE_CURRENT_VERSION,
				                                    'min_gf_version'             => $this->min_gf_version,
				                                    'plugin_slug'                => 'google-analytics-ecommerce',
				                                    'path'                       => GFP_GOOGLE_ANALYTICS_ECOMMERCE_PATH,
				                                    'full_path'                  => GFP_GOOGLE_ANALYTICS_ECOMMERCE_FILE,
				                                    'title'                      => 'Gravity Forms + Google Analytics Ecommerce Tracking',
				                                    'short_title'                => 'GA Ecommerce Tracking',
				                                    'url'                        => 'https://gravityplus.pro/gravity-forms-google-analytics-ecommerce-tracking',
				                                    'capabilities'               => array(
					                                    'gravityplus_gaecommerce_plugin_settings',
					                                    'gravityplus_gaecommerce_form_settings',
					                                    'gravityplus_gaecommerce_uninstall'
				                                    ),
				                                    'capabilities_settings_page' => array( 'gravityplus_gaecommerce_plugin_settings' ),
				                                    'capabilities_form_settings' => array( 'gravityplus_gaecommerce_form_settings' ),
				                                    'capabilities_uninstall'     => array( 'gravityplus_gaecommerce_uninstall' )
			                                    ) );
		}
	}

	/**
	 * Load language files
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 */
	public function load_textdomain() {

		$gfp_google_analytics_ecommerce_lang_dir = dirname( plugin_basename( GFP_GOOGLE_ANALYTICS_ECOMMERCE_FILE ) ) . '/languages/';
		$gfp_google_analytics_ecommerce_lang_dir = apply_filters( 'gfp_google_analytics_ecommerce_language_dir', $gfp_google_analytics_ecommerce_lang_dir );

		$locale = apply_filters( 'plugin_locale', get_locale(), 'gravityplus-google-analytics-ecommerce-tracking' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'gravityplus-google-analytics-ecommerce-tracking', $locale );

		$mofile_local  = $gfp_google_analytics_ecommerce_lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/gravityplus-google-analytics-ecommerce-tracking/' . $mofile;

		if ( file_exists( $mofile_global ) ) {

			load_textdomain( 'gravityplus-google-analytics-ecommerce-tracking', $mofile_global );

		} elseif ( file_exists( $mofile_local ) ) {

			load_textdomain( 'gravityplus-google-analytics-ecommerce-tracking', $mofile_local );

		} else {

			load_plugin_textdomain( 'gravityplus-google-analytics-ecommerce-tracking', false, $gfp_google_analytics_ecommerce_lang_dir );

		}
	}


	/**
	 * Return GF Add-On object
	 *
	 * @since  1.0.0
	 *
	 * @author Naomi C. Bush for gravity+ <support@gravityplus.pro>
	 *
	 * @return GFP_Google_Analytics_Ecommerce_Addon
	 */
	public function get_addon_object() {

		return $this->addon;

	}


}