<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class HF_Subscription_PayPal_Standard_IPN_Failure_Handler {

    private static $transaction_details = null;
    public static $log = null;

    public static function attach($transaction_details) {
        self::$transaction_details = $transaction_details;
        $transient_key = 'hforce_paypal_ipn_error_occurred';
        $api_username = HF_Subscription_PayPal::get_option('api_username');

        WC_Gateway_Paypal::$log_enabled = true;

        if (get_transient($transient_key) == $api_username && !defined('WP_DEBUG')) {
            define('WP_DEBUG', true);

            if (!defined('WP_DEBUG_DISPLAY')) {
                define('WP_DEBUG_DISPLAY', false);
            }
        }

        add_action('hforce_paypal_ipn_process_failure', __CLASS__ . '::log_ipn_errors', 10, 2);
        add_action('shutdown', __CLASS__ . '::catch_unexpected_shutdown');
    }

    public static function detach($transaction_details) {
        remove_action('hforce_paypal_ipn_process_failure', __CLASS__ . '::log_ipn_errors');
        remove_action('shutdown', __CLASS__ . '::catch_unexpected_shutdown');

        self::$transaction_details = null;
    }

    public static function catch_unexpected_shutdown() {

        if (!empty(self::$transaction_details) && $error = error_get_last()) {
            if (in_array($error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {
                do_action('hforce_paypal_ipn_process_failure', self::$transaction_details, $error);
            }
        }

        self::$transaction_details = null;
    }

    public static function log_ipn_errors($transaction_details, $error = '') {
        // show this latern 
        self::log_to_failure(sprintf('transaction details: %s', print_r($transaction_details, true)));

        if (!empty($error)) {
            update_option('hforce_fatal_error_handling_ipn', $error['message']);
            self::log_to_failure(sprintf('Error processing PayPal IPN message: %s in %s on line %s.', $error['message'], $error['file'], $error['line']));

            if (!empty($error['trace'])) {
                self::log_to_failure(sprintf('Stack trace: %s', PHP_EOL . $error['trace']));
            }
        }

        set_transient('hforce_paypal_ipn_error_occurred', HF_Subscription_Paypal::get_option('api_username'), WEEK_IN_SECONDS);
    }

    public static function log_to_failure($message) {

        if (empty(self::$log)) {
            self::$log = new WC_Logger();
        }

        self::$log->add('hf-paypal-pn-failures', $message);
    }

    public static function log_unexpected_exception($exception) {
        $error = array(
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        );

        if (empty($error['message'])) {
            $error['message'] = 'Unhandled Exception: no message';
        }

        self::log_ipn_errors(self::$transaction_details, $error);
    }

}