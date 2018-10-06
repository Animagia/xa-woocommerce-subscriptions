<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once( 'hf-includes/class-hf-paypal-standard-ipn-failure-handler.php' );
if (!class_exists('HF_Subscription_Paypal')) {

    class HF_Subscription_Paypal {

        protected $wclog = '';
        protected $debug;
        protected static $ipn_handlers;
        protected static $paypal_settings;

        public function __construct() {


            self::$paypal_settings = self::get_options();
            if (self::$paypal_settings['enabled'] != 'yes') {
                return;
            }

            $this->debug = ( isset(self::$paypal_settings['debug']) && self::$paypal_settings['debug'] == 'yes' ) ? true : false;

            if ($this->debug) {
                $this->wclog = new WC_Logger();
            }

            // If subscription, set the PayPal args to be for a subscription instead of shopping cart
            add_filter('woocommerce_paypal_args', __CLASS__ . '::get_paypal_args', 10, 2);

            // Check if there is a subcription in a valid PayPal IPN request
            add_action('valid-paypal-standard-ipn-request', __CLASS__ . '::process_ipn_request', 0);
        }

        protected static function get_options() {

            self::$paypal_settings = get_option('woocommerce_paypal_settings');
            return self::$paypal_settings;
        }

        public static function get_option($setting_key) {
            return ( isset(self::$paypal_settings[$setting_key]) ) ? self::$paypal_settings[$setting_key] : '';
        }

        public static function get_paypal_args($paypal_args, $order) {


            $is_payment_change = false;
            $order_contains_failed_renewal = false;

            if ($cart_item = hf_cart_contains_failed_renewal_order_payment() || false !== get_failed_order_replaced_by(hforce_get_objects_property($order, 'id'))) {
                $subscriptions = hf_get_subscriptions_for_renewal_order($order);
                $order_contains_failed_renewal = true;
            } else {
                $subscriptions = get_subscriptions_by_order($order);
            }

            $subscription = array_pop($subscriptions);


            if ($order_contains_failed_renewal || (!empty($subscription) && $subscription->get_total() > 0 && 'yes' !== get_option(HForce_Woocommerce_Subscription_Admin::$option_prefix . '_turn_off_automatic_payments', 'no') )) {

                $paypal_args['cmd'] = '_xclick-subscriptions'; //subscription action

                foreach ($subscription->get_items() as $item) {
                    if ($item['qty'] > 1) {
                        $item_names[] = $item['qty'] . ' x ' . hforce_get_paypal_item_name($item['name']);
                    } elseif ($item['qty'] > 0) {
                        $item_names[] = hforce_get_paypal_item_name($item['name']);
                    }
                }


                if (empty($order)) {

                    $paypal_args['item_name'] = hforce_get_paypal_item_name(sprintf(_x('Subscription %1$s - %2$s', 'item name sent to paypal', 'xa-woocommerce-subscriptions'), $subscription->get_order_number(), implode(', ', $item_names)));
                } else {

                    $paypal_args['item_name'] = hforce_get_paypal_item_name(sprintf(_x('Subscription %1$s (Order %2$s) - %3$s', 'item name sent to paypal', 'xa-woocommerce-subscriptions'), $subscription->get_order_number(), $order->get_order_number(), implode(', ', $item_names)));
                }

                $unconverted_periods = array(
                    'billing_period' => $subscription->get_billing_period(),
                    'trial_period' => 0,
                );

                $converted_periods = array();


                foreach ($unconverted_periods as $key => $period) {
                    switch (strtolower($period)) {
                        case 'day':
                            $converted_periods[$key] = 'D';
                            break;
                        case 'week':
                            $converted_periods[$key] = 'W';
                            break;
                        case 'year':
                            $converted_periods[$key] = 'Y';
                            break;
                        case 'month':
                        default:
                            $converted_periods[$key] = 'M';
                            break;
                    }
                }

                $price_per_period = $subscription->get_total();
                $subscription_interval = $subscription->get_billing_interval();
                $start_timestamp = $subscription->get_time('date_created');
                $trial_end_timestamp = $subscription->get_time('trial_end');
                $next_payment_timestamp = $subscription->get_time('next_payment');

                $is_synced_subscription = FALSE;

                if ($is_synced_subscription) {
                    $length_from_timestamp = $next_payment_timestamp;
                } elseif ($trial_end_timestamp > 0) {
                    $length_from_timestamp = $trial_end_timestamp;
                } else {
                    $length_from_timestamp = $start_timestamp;
                }

                $subscription_length = Hforce_Date_Time_Utils::hforce_estimate_periods_between($length_from_timestamp, $subscription->get_time('end'), $subscription->get_billing_period());

                $subscription_installments = $subscription_length / $subscription_interval;

                $initial_payment = ( $is_payment_change ) ? 0 : $order->get_total();

                if ($order_contains_failed_renewal) {


                    $suffix = '-hfflreneword-' . hforce_get_objects_property($order, 'id');


                    $parent_order = $subscription->get_parent();


                    if (false === $parent_order) {

                        $order_number = ltrim($subscription->get_order_number(), _x('#', 'hash before the order number. Used as a character to remove from the actual order number', 'woocommerce-subscriptions')) . '-subscription';
                        $order_id_key = array('order_id' => $subscription->get_id(), 'order_key' => $subscription->get_order_key());
                    } else {
                        $order_number = ltrim($parent_order->get_order_number(), _x('#', 'hash before the order number. Used as a character to remove from the actual order number', 'woocommerce-subscriptions'));
                        $order_id_key = array('order_id' => hforce_get_objects_property($parent_order, 'id'), 'order_key' => hforce_get_objects_property($parent_order, 'order_key'));
                    }


                    $paypal_args['invoice'] = self::get_option('invoice_prefix') . date('Y-m-d') . '-' . $order_number . $suffix;
                    $paypal_args['custom'] = hforce_json_encode(array_merge($order_id_key, array('subscription_id' => $subscription->get_id(), 'subscription_key' => $subscription->get_order_key())));
                } else {


                    $paypal_args['custom'] = hforce_json_encode(array('order_id' => hforce_get_objects_property($order, 'id'), 'order_key' => hforce_get_objects_property($order, 'order_key'), 'subscription_id' => $subscription->get_id(), 'subscription_key' => $subscription->get_order_key()));
                }

                if ($order_contains_failed_renewal) {

                    $subscription_trial_length = 0;
                    $subscription_installments = max($subscription_installments - $subscription->get_completed_payment_count(), 0);
                } else {
                    $subscription_trial_length = 0;
                }

                if ($subscription_trial_length > 0) {

                    $paypal_args['a1'] = ( $initial_payment > 0 ) ? $initial_payment : 0;
                    $paypal_args['p1'] = $subscription_trial_length;
                    $paypal_args['t1'] = $converted_periods['trial_period'];

                    if (isset($second_trial_length) && $second_trial_length > 0) {
                        $paypal_args['a2'] = 0.01;
                        $paypal_args['p2'] = $second_trial_length;
                        $paypal_args['t2'] = $second_trial_period;
                    }
                } elseif ($initial_payment != $price_per_period) {

                    if (1 == $subscription_installments) {
                        $param_number = 3;
                    } else {
                        $param_number = 1;
                    }

                    $paypal_args['a' . $param_number] = $initial_payment;
                    $paypal_args['p' . $param_number] = $subscription_interval;
                    $paypal_args['t' . $param_number] = $converted_periods['billing_period'];
                }

                if (!isset($param_number) || 1 == $param_number) {

                    $paypal_args['a3'] = $price_per_period;
                    $paypal_args['p3'] = $subscription_interval;
                    $paypal_args['t3'] = $converted_periods['billing_period'];
                }

                if (1 == $subscription_installments || ( $initial_payment != $price_per_period && 0 == $subscription_trial_length && 2 == $subscription_installments )) {

                    $paypal_args['src'] = 0;
                } else {

                    $paypal_args['src'] = 1;

                    if ($subscription_installments > 0) {

                        if ($initial_payment != $price_per_period && 0 == $subscription_trial_length) {
                            $subscription_installments--;
                        }

                        $paypal_args['srt'] = $subscription_installments;
                    }
                }

                $paypal_args['sra'] = 0;
                $paypal_args['rm'] = 2;
            }

            return $paypal_args;
        }

        public static function process_ipn_request($ipn_details) {

            WC_Gateway_Paypal::log('Mark HF Sub Transaction info: ' . print_r($ipn_details, true));
            /* if ($this->debug) {
              $this->wclog->add('paypal', 'Subscription transaction details: ' . print_r($ipn_details, true));
              }
             * 
             */
            try {

                require_once( 'hf-includes/class-hf-paypal-standard-ipn-handler.php' );
                require_once( 'hf-includes/class-hf-paypal-reference-ipn-handler.php' );

                if (!isset($ipn_details['txn_type']) || !in_array($ipn_details['txn_type'], array_merge(self::get_ipn_handler('standard')->get_transaction_types(), self::get_ipn_handler('reference')->get_transaction_types()))) {
                    return;
                }

                WC_Gateway_Paypal::log('Transaction Details: ' . print_r($ipn_details, true));

                if (in_array($ipn_details['txn_type'], self::get_ipn_handler('standard')->get_transaction_types())) {
                    self::get_ipn_handler('standard')->valid_response($ipn_details);
                } elseif (in_array($ipn_details['txn_type'], self::get_ipn_handler('reference')->get_transaction_types())) {
                    self::get_ipn_handler('reference')->valid_response($ipn_details);
                }
            } catch (Exception $e) {
                HF_Subscription_PayPal_Standard_IPN_Failure_Handler::log_unexpected_exception($e);
            }
        }

        protected static function get_ipn_handler($ipn_type = 'standard') {

            $use_sandbox = ( 'yes' === self::get_option('testmode') ) ? true : false;

            if ('reference' === $ipn_type) {

                if (!isset(self::$ipn_handlers['reference'])) {
                    require_once( 'hf-includes/class-hf-paypal-reference-ipn-handler.php' );
                    self::$ipn_handlers['reference'] = new HF_Subscription_PayPal_Reference_IPN_Handler($use_sandbox, self::get_option('receiver_email'));
                }

                $ipn_handler = self::$ipn_handlers['reference'];
            } else {

                if (!isset(self::$ipn_handlers['standard'])) {
                    require_once( 'hf-includes/class-hf-paypal-standard-ipn-handler.php' );
                    self::$ipn_handlers['standard'] = new HF_Subscription_PayPal_Standard_IPN_Handler($use_sandbox, self::get_option('receiver_email'));
                }

                $ipn_handler = self::$ipn_handlers['standard'];
            }

            return $ipn_handler;
        }

        protected static function format_item_name($item_name) {

            if (strlen($item_name) > 127) {
                $item_name = substr($item_name, 0, 124) . '...';
            }
            die();
            return html_entity_decode($item_name, ENT_NOQUOTES, 'UTF-8');
        }

    }

    new HF_Subscription_Paypal();
}