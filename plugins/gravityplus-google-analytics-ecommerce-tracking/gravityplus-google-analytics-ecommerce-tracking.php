<?php
/**
 * @wordpress-plugin
 * Plugin Name: Gravity Forms + Google Analytics Ecommerce Tracking
 * Plugin URI: https://gravityplus.pro/gravity-forms-google-analytics-ecommerce-tracking
 * Description: Send ecommerce data from successful payment form submissions to Google Analytics
 * Version: 1.0.0
 * Author: gravity+
 * Author URI: https://gravityplus.pro
 * Text Domain: gravityplus-google-analytics-ecommerce-tracking
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
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
 *
 * @package   GFP_Google_Analytics_Ecommerce
 * @version   1.0.0
 * @author    gravity+ <support@gravityplus.pro>
 * @license   GPL-2.0+
 * @link      https://gravityplus.pro
 * @copyright 2015 gravity+
 *
 * last updated: February 23, 2015
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


define( 'GFP_GOOGLE_ANALYTICS_ECOMMERCE_CURRENT_VERSION', '1.0.0' );
define( 'GFP_GOOGLE_ANALYTICS_ECOMMERCE_FILE', __FILE__ );
define( 'GFP_GOOGLE_ANALYTICS_ECOMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'GFP_GOOGLE_ANALYTICS_ECOMMERCE_URL', plugin_dir_url( __FILE__ ) );
define( 'GFP_GOOGLE_ANALYTICS_ECOMMERCE_SLUG', plugin_basename( dirname( __FILE__ ) ) );

//Load all of the necessary class files for the plugin
require_once( 'includes/class-loader.php' );
GFP_Google_Analytics_Ecommerce_Loader::load();

$gfp_google_analytics_ecommerce = new GFP_Google_Analytics_Ecommerce();
$gfp_google_analytics_ecommerce->run();