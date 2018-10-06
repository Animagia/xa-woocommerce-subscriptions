<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class HForce_Subscriptions_Product {


    public function __construct() {
        
    }

    
    public static function add_to_cart_text($button_text, $product_type = '') {
        
        global $product;

        if (self::is_subscription($product) || in_array($product_type, array('subscription', 'subscription-variation'))) {
            $button_text = get_option(HForce_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text', __('Subscribe', 'xa-woocommerce-subscription'));
            if(empty($button_text)){
                $button_text = __('Subscribe', 'xa-woocommerce-subscription');
            }
        }

        return $button_text;
    }

    public static function is_subscription($product) {

        $is_subscription = $product_id = false;
        $product = self::maybe_get_product_instance($product);
        if (is_object($product)) {
            $product_id = $product->get_id();
            $subscription_types = array('subscription',);
            if ($product->is_type($subscription_types)) {
                $is_subscription = true;
            }
        }

        return apply_filters('hf_product_is_subscription', $is_subscription, $product_id, $product);
    }


    public static function get_price_string($product, $include = array()) {
        
        global $wp_locale;
        $product = self::maybe_get_product_instance($product);

        if (!self::is_subscription($product)) {
            return;
        }

        $include = wp_parse_args($include, array(
            'tax_calculation' => get_option('woocommerce_tax_display_shop'),
            'subscription_price' => true,
            'subscription_period' => true,
            'subscription_length' => true,
            )
        );

        $include = apply_filters('hf_subscription_product_price_string_inclusions', $include, $product);

        $base_price = self::get_price($product);


        if (false != $include['tax_calculation']) {

            if (in_array($include['tax_calculation'], array('exclude_tax', 'excl'))) { // Subtract Tax
                if (isset($include['price'])) {
                    $price = $include['price'];
                } else {
                    $price = hf_get_price_excluding_tax($product, array('price' => $include['price']));
                }
            } else { 
                if (isset($include['price'])) {
                    $price = $include['price'];
                } else {
                    $price = hf_get_price_including_tax($product);
                }
            }
        } else {

            if (isset($include['price'])) {
                $price = $include['price'];
            } else {
                $price = wc_price($base_price);
            }
        }

        $price .= ' <span class="subscription-details">';

        $billing_interval = self::get_interval($product);
        $billing_period = self::get_period($product);
        $subscription_length = self::get_length($product);

        if ($include['subscription_length']) {
            $ranges = Hforce_Date_Time_Utils::hforce_get_subscription_ranges($billing_period);
        }

        if ($include['subscription_length'] && 0 != $subscription_length) {
            $include_length = true;
        } else {
            $include_length = false;
        }

        $subscription_string = '';

        if ($include['subscription_price'] && $include['subscription_period']) {
            if ($include_length && $subscription_length == $billing_interval) {
                $subscription_string = $price;
            } else {
                $subscription_period_string = Hforce_Date_Time_Utils::subscription_period_strings($billing_interval, $billing_period);
                if(is_string($subscription_period_string)){
                    $subscription_period_string = __($subscription_period_string, 'xa-woocommerce-subscription');
                }
                $subscription_string = sprintf(_n('%1$s / %2$s', ' %1$s every %2$s', $billing_interval, 'xa-woocommerce-subscription'), $price, $subscription_period_string);
            }
        } elseif ($include['subscription_price']) {
            $subscription_string = $price;
        } elseif ($include['subscription_period']) {
            $subscription_string = sprintf(__('every %s', 'xa-woocommerce-subscription'), Hforce_Date_Time_Utils::subscription_period_strings($billing_interval, $billing_period));
        }

        if ($include_length) {
            $subscription_string = sprintf(__('%1$s for %2$s', 'xa-woocommerce-subscription'), $subscription_string, $ranges[$subscription_length]);
        }


        $subscription_string .= '</span>';

        return apply_filters('hf_subscription_product_price_string', $subscription_string, $product, $include);
    }

    // returns the active price per period for a product if it is a subscription.

    public static function get_price($product) {

        $product = self::maybe_get_product_instance($product);

        $subscription_price = self::get_meta_data($product, 'subscription_price', 0);
        $sale_price = self::get_sale_price($product);
        $active_price = ( $subscription_price ) ? $subscription_price : self::get_regular_price($product);

        if ($product->is_on_sale() && $subscription_price > $sale_price) {
            $active_price = $sale_price;
        }

        return apply_filters('hf_subscription_product_price', $active_price, $product);
    }

    public static function get_regular_price($product, $context = 'view') {

        if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.0')) {
            $regular_price = $product->regular_price;
        } else {
            $regular_price = $product->get_regular_price($context);
        }

        return apply_filters('hf_subscription_product_regular_price', $regular_price, $product);
    }

    public static function get_sale_price($product, $context = 'view') {

        if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.0')) {
            $sale_price = $product->sale_price;
        } else {
            
            $sale_price = $product->get_sale_price($context);
        }
        return apply_filters('hf_subscription_product_sale_price', $sale_price, $product);
    }

    public static function get_period($product) {
        return apply_filters('hf_subscription_product_period', self::get_meta_data($product, 'subscription_period', ''), self::maybe_get_product_instance($product));
    }

    public static function get_interval($product) {
        return apply_filters('hf_subscription_product_period_interval', self::get_meta_data($product, 'subscription_period_interval', 1, 'use_default_value'), self::maybe_get_product_instance($product));
    }

    public static function get_length($product) {
        return apply_filters('hf_subscription_product_length', self::get_meta_data($product, 'subscription_length', 0, 'use_default_value'), self::maybe_get_product_instance($product));
    }

    public static function get_first_renewal_payment_date($product_id, $from_date = '', $timezone = 'gmt') {

        $first_renewal_timestamp = self::get_first_renewal_payment_time($product_id, $from_date, $timezone);

        if ($first_renewal_timestamp > 0) {
            $first_renewal_date = gmdate('Y-m-d H:i:s', $first_renewal_timestamp);
        } else {
            $first_renewal_date = 0;
        }
        return apply_filters('hf_subscription_product_first_renewal_payment_date', $first_renewal_date, $product_id, $from_date, $timezone);
    }

    public static function get_first_renewal_payment_time($product_id, $from_date = '', $timezone = 'gmt') {

        if (!self::is_subscription($product_id)) {
            return 0;
        }

        $from_date_param = $from_date;
        $billing_interval = self::get_interval($product_id);
        $billing_length = self::get_length($product_id);

        if ($billing_interval !== $billing_length) {

            if (empty($from_date)) {
                $from_date = gmdate('Y-m-d H:i:s');
            }


                $first_renewal_timestamp = Hforce_Date_Time_Utils::get_next_timestamp($billing_interval, self::get_period($product_id), Hforce_Date_Time_Utils::date_to_time($from_date));

                if ('site' == $timezone) {
                    $first_renewal_timestamp += ( get_option('gmt_offset') * HOUR_IN_SECONDS );
                }
            
        } else {
            $first_renewal_timestamp = 0;
        }
        return apply_filters('hf_subscription_product_first_renewal_payment_time', $first_renewal_timestamp, $product_id, $from_date_param, $timezone);
    }

    public static function get_expiration_date($product_id, $from_date = '') {

        $subscription_length = self::get_length($product_id);

        if ($subscription_length > 0) {

            if (empty($from_date)) {
                $from_date = gmdate('Y-m-d H:i:s');
            }

            $expiration_date = gmdate('Y-m-d H:i:s', Hforce_Date_Time_Utils::get_next_timestamp($subscription_length, self::get_period($product_id), Hforce_Date_Time_Utils::date_to_time($from_date)));
        } else {

            $expiration_date = 0;
        }

        return apply_filters('hf_subscription_product_expiration_date', $expiration_date, $product_id, $from_date);
    }

    private static function maybe_get_product_instance($product) {

        if (!is_object($product) || !is_a($product, 'WC_Product')) {
            $product = wc_get_product($product);
        }

        return $product;
    }

    public static function get_meta_data($product, $meta_key, $default_value, $empty_handling = 'allow_empty') {

        $product = self::maybe_get_product_instance($product);

        $meta_value = $default_value;

        if (self::is_subscription($product)) {

            if (is_callable(array($product, 'meta_exists'))) {
                $prefixed_key = Hforce_Date_Time_Utils::add_prefix_key($meta_key);

                if ($product->meta_exists($prefixed_key)) {
                    $meta_value = $product->get_meta($prefixed_key, true);
                }
            } elseif (isset($product->{$meta_key})) {
                $meta_value = $product->{$meta_key};
            }
        }

        if ('use_default_value' === $empty_handling && empty($meta_value)) {
            $meta_value = $default_value;
        }

        return $meta_value;
    }

    
}

new HForce_Subscriptions_Product();