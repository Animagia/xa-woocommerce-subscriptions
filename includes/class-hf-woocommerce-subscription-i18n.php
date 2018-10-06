<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Define the internationalization functionality
 *
 */

class HForce_Woocommerce_Subscription_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain('xa-woocommerce-subscription', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/');

	}



}
