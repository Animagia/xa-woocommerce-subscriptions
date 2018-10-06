<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Fired during plugin activation
 *
 */
class HForce_Woocommerce_Subscription_Activator {

    public function __construct() {

        add_action('admin_notices', array($this, 'when_my_plugin_activate'), 999);
    }

    public static function activate() {

       
        if (!function_exists('hf_is_woocommerce_active')) {
            require_once( 'hf-includes/hf-functions.php' );
        }

        // WC active check
        if (!hf_is_woocommerce_active()) {
            $error_message = __('HF Subscription Plugin not activated. WooCommerce is required.', 'xa-woocommerce-subscription');
            set_transient('hf_subscription_activation_error_message', $error_message, 120);
            if (!function_exists('deactivate_plugins')) {
                require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            }
            deactivate_plugins(plugin_basename(__FILE__));

            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }

            return false;
        } else {
            set_transient('hf_subscription_activation_error_message', '', 120);
        }
    }

    function when_my_plugin_activate() {
        $message = get_transient('hf_subscription_activation_error_message');

        if (!empty($message)) {
            echo "<div class='notice notice-error is-dismissible'>
            <p>$message</p>
        </div>";
        }
    }
    
}