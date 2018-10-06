<?php
/*
 * Plugin Name: Subscriptions for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/xa-woocommerce-subscriptions/
 * Description: Sell products with recurring payments in your WooCommerce Store.
 * Author: markhf
 * Author URI: 
 * Text Domain: xa-woocommerce-subscription
 * Version: 1.2.9
 * WC tested up to: 3.3.5
 * Domain Path: /languages
 * License:     GPLv3
 */

/*
 * Subscriptions for WooCommerce is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.

 * Subscriptions for WooCommerce is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with Subscriptions for WooCommerce. If not, see https://plugins.trac.wordpress.org/browser/xa-woocommerce-subscriptions/trunk/LICENSE.txt.


 * Subscriptions for WooCommerce bundles the following third-party resource by Prospress, Inc.
 * It uses the Action Scheduler. Copyrights and licenses indicated in said libararies.
 * Action Scheduler is licensed under the terms of the GNU GPL, Version 3
 * Source: https://github.com/Prospress/action-scheduler/
 * 
 */



// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('HFORCE_WC_SUBSCRIPTION_VERSION', '1.2.9');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-hf-woocommerce-subscription-activator.php
 */
function activate_hf_woocommerce_subscription() {

    hforce_welcome_screen_and_activation_check();
    require_once plugin_dir_path(__FILE__) . 'includes/class-hf-woocommerce-subscription-activator.php';
    HForce_Woocommerce_Subscription_Activator::activate();
}

// WC check optimize
if (!function_exists('hf_is_woocommerce_active')) {
    require_once( 'includes/hf-includes/hf-functions.php' );
}
if (!hf_is_woocommerce_active()) {

    if (!function_exists('deactivate_plugins')) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    deactivate_plugins(plugin_basename(__FILE__));
    return false;
}


require_once plugin_dir_path(__FILE__) . 'welcome-script.php';
require_once plugin_dir_path(__FILE__) . 'admin/partials/hf-woocommerce-subscription-admin-display.php';

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-hf-woocommerce-subscription-deactivator.php
 */
function deactivate_hf_woocommerce_subscription() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-hf-woocommerce-subscription-deactivator.php';
    HForce_Woocommerce_Subscription_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_hf_woocommerce_subscription');
register_deactivation_hook(__FILE__, 'deactivate_hf_woocommerce_subscription');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-hf-woocommerce-subscription.php';

if (!defined('HFORCE_SUBSCRIPTION_MAIN_PATH')) {
    define('HFORCE_SUBSCRIPTION_MAIN_PATH', plugin_dir_path(__FILE__));
}

if (!defined('HFORCE_SUBSCRIPTION_BASE_NAME')) {
    define('HFORCE_SUBSCRIPTION_BASE_NAME', plugin_basename(__FILE__));
}


if (!defined('HFORCE_BASE_URL')) {
    define('HFORCE_BASE_URL', plugin_dir_url(__FILE__));
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_hf_woocommerce_subscription() {

    // uninstall feedback catch
    include_once 'includes/class-hf-plugin-uninstall-feedback.php';

    $plugin = new HForce_Woocommerce_Subscription();
    $plugin->run();
}

run_hf_woocommerce_subscription();