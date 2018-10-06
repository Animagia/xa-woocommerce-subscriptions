<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class HForce_Subscription_Cart {

    private static $recurring_shipping_packages = array();

    public function __construct() {


        add_filter('woocommerce_add_to_cart_handler', array($this, 'add_to_cart_handler'), 10, 2);
        add_filter('woocommerce_calculated_total', array($this, 'calculate_subscription_totals'), 999, 2);
        add_filter('woocommerce_cart_shipping_packages', array($this, 'set_cart_shipping_packages'), -9, 1);
        add_filter('woocommerce_cart_product_subtotal', __CLASS__ . '::get_formatted_product_subtotal', 10, 4);
        add_filter('woocommerce_cart_product_price', array($this, 'cart_product_price'), 10, 2);
        add_action('woocommerce_cart_totals_after_order_total', array($this, 'display_recurring_totals'));
        add_action('woocommerce_review_order_after_order_total', array($this, 'display_recurring_totals'));
        add_action('woocommerce_add_to_cart_validation', array($this, 'check_valid_add_to_cart'), 10, 6);
        add_action('woocommerce_add_to_cart_validation', array($this, 'need_empty_cart'), 10, 4);
        add_action('woocommerce_checkout_update_order_review', array($this, 'add_shipping_method_post_data'));
        add_filter('woocommerce_shipping_chosen_method', array($this, 'set_chosen_shipping_method'), 10, 2);
        add_filter('woocommerce_package_rates', array($this, 'cache_shipping_package_rates'), 1, 2);
        add_filter('woocommerce_shipping_packages', array($this, 'reset_shipping_method_count'), 999, 1);
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_recurring_shipping_methods'));
        
    }

    public function add_to_cart_handler($handler, $product) {
        
        
        if (HForce_Subscriptions_Product::is_subscription($product)) {
            switch ($handler) {
                case 'subscription' :
                    $handler = 'simple';
                    break;
            }
        }
        return $handler;
        
    }
    
    
    public function need_empty_cart($valid, $product_id, $quantity, $variation_id = '') {

        
        $is_subscription = HForce_Subscriptions_Product::is_subscription($product_id);
        $cart_contains_subscription = HForce_Woocommerce_Subscription_Admin::whether_cart_contains_subscription();
        $multiple_subscriptions_is_possible = HForce_Woocommerce_Subscription_Admin::is_gateway_supports('multiple_subscriptions');

        $manual_renewals_enabled = true;
        $canonical_product_id = (!empty($variation_id) ) ? $variation_id : $product_id;

        if ($is_subscription && 'yes' != get_option(HForce_Woocommerce_Subscription_Admin::$option_prefix . '_hf_allow_multiple_purchase', 'no')) {
            WC()->cart->empty_cart();
        } elseif ($is_subscription && hf_cart_contains_renewal() && !$multiple_subscriptions_is_possible && !$manual_renewals_enabled) {
            self::remove_subscriptions_from_cart();
            self::add_notice(__('A subscription renewal has been removed from your cart. Multiple subscriptions can not be purchased at the same time.', 'xa-woocommerce-subscription'), 'notice');
        } elseif ($is_subscription && $cart_contains_subscription && !$multiple_subscriptions_is_possible && !$manual_renewals_enabled && !self::cart_contains_product($canonical_product_id)) {
            self::remove_subscriptions_from_cart();
            self::add_notice(__('A subscription has been removed from your cart. Due to payment gateway restrictions, different subscription products can not be purchased at the same time.', 'xa-woocommerce-subscription'), 'notice');
        } elseif ($cart_contains_subscription && 'yes' != get_option(HForce_Woocommerce_Subscription_Admin::$option_prefix . '_hf_allow_multiple_purchase', 'no')) {
            self::remove_subscriptions_from_cart();
            self::add_notice(__('A subscription has been removed from your cart. Products and subscriptions can not be purchased at the same time.', 'xa-woocommerce-subscription'), 'notice');
            
            
            if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.0.8')) {
                add_filter('add_to_cart_fragments', __CLASS__ . '::redirect_ajax_add_to_cart');
            } else {
                add_filter('woocommerce_add_to_cart_fragments', __CLASS__ . '::redirect_ajax_add_to_cart');
            }
        }

        return $valid;
    }
    
    public static function redirect_ajax_add_to_cart($fragments) {

        return array( 'error' => true, 'product_url' => WC()->cart->get_cart_url(),);
    }
    
    public static function add_notice($message, $notice_type = 'success') {
        wc_add_notice($message, $notice_type);
    }
    
    public static function remove_subscriptions_from_cart() {

        foreach (WC()->cart->cart_contents as $cart_item_key => $cart_item) {
            if (HForce_Subscriptions_Product::is_subscription($cart_item['data'])) {
                WC()->cart->set_quantity($cart_item_key, 0);
            }
        }
    }
    
    private static $recurring_cart_key = 'none';
    private static $calculation_type = 'none'; //none,recurring_total
    private static $cached_recurring_cart = null;
    
    public function calculate_subscription_totals($total, $cart) {

        if (!HForce_Woocommerce_Subscription_Admin::whether_cart_contains_subscription() && !is_cart_contains_resubscribe()) {
            return $total;
        } elseif ('none' != self::$calculation_type) {
            return $total;
        }

        WC()->cart->total = ( $total < 0 ) ? 0 : $total;
        do_action('hf_subscription_cart_before_grouping');
        $subscription_groups = array();

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (HForce_Subscriptions_Product::is_subscription($cart_item['data'])) {
                $subscription_groups[self::get_recurring_cart_key($cart_item)][] = $cart_item_key;
            }
        }

        do_action('hf_subscription_cart_after_grouping');
        $recurring_carts = array();
        WC()->session->set('hf_shipping_methods', WC()->session->get('chosen_shipping_methods', array()));
        self::$calculation_type = 'recurring_total';

        foreach ($subscription_groups as $recurring_cart_key => $subscription_group) {

            $recurring_cart = clone WC()->cart;
            $product = null;
            self::$recurring_cart_key = $recurring_cart->recurring_cart_key = $recurring_cart_key;
            foreach ($recurring_cart->get_cart() as $cart_item_key => $cart_item) {
                if (!in_array($cart_item_key, $subscription_group)) {
                    unset($recurring_cart->cart_contents[$cart_item_key]);
                    continue;
                }
                if (null === $product) {
                    $product = $cart_item['data'];
                }
            }

            $recurring_cart->start_date = apply_filters('hf_recurring_cart_start_date', gmdate('Y-m-d H:i:s'), $recurring_cart);
            $recurring_cart->next_payment_date = apply_filters('hf_recurring_cart_next_payment_date', HForce_Subscriptions_Product::get_first_renewal_payment_date($product, $recurring_cart->start_date), $recurring_cart, $product);
            $recurring_cart->end_date = apply_filters('hf_recurring_cart_end_date', HForce_Subscriptions_Product::get_expiration_date($product, $recurring_cart->start_date), $recurring_cart, $product);
            self::$cached_recurring_cart = $recurring_cart;

            if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.2')) {
                $recurring_cart->fees = array();
            } else {
                $recurring_cart->add_fee(array(), array());
            }

            $recurring_cart->fee_total = 0;
            WC()->shipping->reset_shipping();
            self::maybe_restore_shipping_methods();
            $recurring_cart->calculate_totals();
            $recurring_carts[$recurring_cart_key] = clone $recurring_cart;
            $recurring_carts[$recurring_cart_key]->removed_cart_contents = array();
            $recurring_carts[$recurring_cart_key]->cart_session_data = array();
            self::$recurring_shipping_packages[$recurring_cart_key] = WC()->shipping->get_packages();
        }

        self::$calculation_type = self::$recurring_cart_key = 'none';

        WC()->shipping->reset_shipping();
        self::maybe_restore_shipping_methods();
        WC()->cart->calculate_shipping();

        unset(WC()->session->hf_shipping_methods);

        WC()->cart->recurring_carts = $recurring_carts;

        $total = max(0, round(WC()->cart->cart_contents_total + WC()->cart->tax_total + WC()->cart->shipping_tax_total + WC()->cart->shipping_total + WC()->cart->fee_total, WC()->cart->dp));


        return apply_filters('hf_subscription_calculated_total', $total);
    }


    public function add_shipping_method_post_data() {

        if (!HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('2.6')) {
            return;
        }
        check_ajax_referer('update-order-review', 'security');
        parse_str($_POST['post_data'], $form_data);

        if (!isset($_POST['shipping_method'])) {
            $_POST['shipping_method'] = array();
        }
        if (!isset($form_data['shipping_method'])) {
            $form_data['shipping_method'] = array();
        }

        foreach ($form_data['shipping_method'] as $key => $methods) {
            if (!is_numeric($key) && !array_key_exists($key, $_POST['shipping_method'])) {
                $_POST['shipping_method'][$key] = $methods;
            }
        }
    }

    public function reset_shipping_method_count($packages) {

        if ('none' !== self::$recurring_cart_key) {
            WC()->session->set('shipping_method_counts', array());
        }
        return $packages;
    }

    public function set_chosen_shipping_method($default_method, $available_methods, $package_index = 0) {

        $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
        $recurring_cart_package_key = self::$recurring_cart_key . '_' . $package_index;
        if ('none' !== self::$recurring_cart_key && isset($chosen_methods[$recurring_cart_package_key]) && isset($available_methods[$chosen_methods[$recurring_cart_package_key]])) {
            $default_method = $chosen_methods[$recurring_cart_package_key];
        } elseif (isset($chosen_methods[$package_index]) && $default_method !== $chosen_methods[$package_index] && isset($available_methods[$chosen_methods[$package_index]])) {
            $default_method = $chosen_methods[$package_index];
        }
        return $default_method;
        
    }

    public static function set_global_recurring_shipping_packages() {
        
        foreach (self::$recurring_shipping_packages as $recurring_cart_key => $packages) {
            foreach ($packages as $package_index => $package) {
                $recurring_shipping_package_key = $recurring_cart_key . '_' . $package_index;
                WC()->shipping->packages[$recurring_shipping_package_key] = $package;
            }
        }
        
    }

    public static function cart_contains_subscriptions_needing_shipping() {

        if ('no' === get_option('woocommerce_calc_shipping')) {
            return false;
        }

        $cart_contains_subscriptions_needing_shipping = false;

        if (HForce_Woocommerce_Subscription_Admin::whether_cart_contains_subscription()) {
            foreach (WC()->cart->cart_contents as $cart_item_key => $values) {
                $_product = $values['data'];
                if (HForce_Subscriptions_Product::is_subscription($_product) && $_product->needs_shipping()) {
                    $cart_contains_subscriptions_needing_shipping = true;
                }
            }
        }

        return apply_filters('woocommerce_cart_contains_subscriptions_needing_shipping', $cart_contains_subscriptions_needing_shipping);
    }

    public function set_cart_shipping_packages($packages) {

        if (HForce_Woocommerce_Subscription_Admin::whether_cart_contains_subscription()) {
            if ('none' == self::$calculation_type) {
                foreach ($packages as $index => $package) {

                    if (empty($packages[$index]['contents'])) {
                        unset($packages[$index]);
                    }
                }
            } elseif ('recurring_total' == self::$calculation_type) {
                foreach ($packages as $index => $package) {
                    if (empty($packages[$index]['contents'])) {
                        unset($packages[$index]);
                    } else {
                        $packages[$index]['recurring_cart_key'] = self::$recurring_cart_key;
                    }
                }
            }
        }

        return $packages;
    }

    public static function get_formatted_product_subtotal($product_subtotal, $product, $quantity, $cart) {

        if (HForce_Subscriptions_Product::is_subscription($product) && !hf_cart_contains_renewal()) {

            if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.0')) {
                $product_price_filter = 'woocommerce_get_price';
            } else {
                $product_price_filter = is_a($product, 'WC_Product_Variation') ? 'woocommerce_product_variation_get_price' : 'woocommerce_product_get_price';
            }
            $product_subtotal = HForce_Subscriptions_Product::get_price_string($product, array(
                        'price' => $product_subtotal,
                        'tax_calculation' => WC()->cart->tax_display_cart,
                            )
            );

            if (false !== strpos($product_subtotal, WC()->countries->inc_tax_or_vat())) {
                $product_subtotal = str_replace(WC()->countries->inc_tax_or_vat(), '', $product_subtotal) . ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
            }
            if (false !== strpos($product_subtotal, WC()->countries->ex_tax_or_vat())) {
                $product_subtotal = str_replace(WC()->countries->ex_tax_or_vat(), '', $product_subtotal) . ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
            }

            $product_subtotal = '<span class="subscription-price">' . $product_subtotal . '</span>';
        }

        return $product_subtotal;
    }

    public static function get_calculation_type() {
        return self::$calculation_type;
    }

    public static function set_calculation_type($calculation_type) {

        self::$calculation_type = $calculation_type;
        return $calculation_type;
    }

    public function cart_needs_payment($needs_payment, $cart) {

        if (false === $needs_payment && HForce_Woocommerce_Subscription_Admin::whether_cart_contains_subscription() && $cart->total == 0) {

            $recurring_total = 0;
            $is_one_period = true;
            

            foreach (WC()->cart->recurring_carts as $cart) {

                $recurring_total += $cart->total;

                $cart_length = hf_cart_pluck($cart, 'subscription_length');

                if (0 == $cart_length || hf_cart_pluck($cart, 'subscription_period_interval') != $cart_length) {
                    $is_one_period = false;
                }

            }
            if ($recurring_total > 0 && ( false === $is_one_period)) {
                $needs_payment = true;
            }
        }

        return $needs_payment;
    }

    private static function maybe_restore_shipping_methods() {
        
        if (!empty($_POST['calc_shipping']) && wp_verify_nonce($_POST['_wpnonce'], 'woocommerce-cart') && function_exists('WC')) {

            try {
                WC()->shipping->reset_shipping();

                $country = wc_clean($_POST['calc_shipping_country']);
                $state = isset($_POST['calc_shipping_state']) ? wc_clean($_POST['calc_shipping_state']) : '';
                $postcode = apply_filters('woocommerce_shipping_calculator_enable_postcode', true) ? wc_clean($_POST['calc_shipping_postcode']) : '';
                $city = apply_filters('woocommerce_shipping_calculator_enable_city', false) ? wc_clean($_POST['calc_shipping_city']) : '';

                if ($postcode && !WC_Validation::is_postcode($postcode, $country)) {
                    throw new Exception(__('Please enter a valid postcode/ZIP.', 'xa-woocommerce-subscription'));
                } elseif ($postcode) {
                    $postcode = wc_format_postcode($postcode, $country);
                }

                if ($country) {
                    WC()->customer->set_location($country, $state, $postcode, $city);
                    WC()->customer->set_shipping_location($country, $state, $postcode, $city);
                } else {
                    WC()->customer->set_to_base();
                    WC()->customer->set_shipping_to_base();
                }

                if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.0')) {
                    WC()->customer->calculated_shipping(true);
                } else {
                    WC()->customer->set_calculated_shipping(true);
                }

                do_action('woocommerce_calculated_shipping');
            } catch (Exception $e) {
                if (!empty($e)) {
                    wc_add_notice($e->getMessage(), 'error');
                }
            }
        }

        $chosen_shipping_method_cache = WC()->session->get('hf_shipping_methods', false);
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods', array());

        if (false !== $chosen_shipping_method_cache && empty($chosen_shipping_methods)) {
            WC()->session->set('chosen_shipping_methods', $chosen_shipping_method_cache);
        }

        if (isset($_POST['shipping_method']) && is_array($_POST['shipping_method'])) {
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods', array());
            foreach ($_POST['shipping_method'] as $i => $value) {
                $chosen_shipping_methods[$i] = wc_clean($value);
            }
            WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
        }
    }

    public function cart_product_price($price, $product) {

        if (HForce_Subscriptions_Product::is_subscription($product)) {
            $price = HForce_Subscriptions_Product::get_price_string($product, array('price' => $price, 'tax_calculation' => WC()->cart->tax_display_cart));
        }

        return $price;
    }


    public function display_recurring_totals() {

        if (HForce_Woocommerce_Subscription_Admin::whether_cart_contains_subscription()) {

            self::$calculation_type = 'recurring_total';
            $shipping_methods = array();
            $carts_with_multiple_payments = 0;
            if(!empty(WC()->cart->recurring_carts)){
            foreach (WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart) {
                    if (0 != $recurring_cart->next_payment_date) {
                        $carts_with_multiple_payments++;
                    }
                }
            }
            if ($carts_with_multiple_payments >= 1) {
                $args = array('shipping_methods' => $shipping_methods, 'recurring_carts' => WC()->cart->recurring_carts, 'carts_with_multiple_payments' => $carts_with_multiple_payments);
                wc_get_template('checkout/recurring-totals.php', $args, '', HFORCE_SUBSCRIPTION_MAIN_PATH . 'public/templates/');
            }
            self::$calculation_type = 'none';
        }
    }

    public static function get_recurring_cart_key($cart_item, $renewal_time = '') {

        $cart_key = '';
        $product = $cart_item['data'];
        $product_id = hf_get_canonical_product_id($product);
        $renewal_time = !empty($renewal_time) ? $renewal_time : HForce_Subscriptions_Product::get_first_renewal_payment_time($product_id);
        $interval = HForce_Subscriptions_Product::get_interval($product);
        $period = HForce_Subscriptions_Product::get_period($product);
        $length = HForce_Subscriptions_Product::get_length($product);

        if ($renewal_time > 0) {
            $cart_key .= gmdate('Y_m_d_', $renewal_time);
        }

        switch ($interval) {
            case 1 :
                if ('day' == $period) {
                    $cart_key .= 'daily';
                } else {
                    $cart_key .= sprintf('%sly', $period);
                }
                break;
            case 2 :
                $cart_key .= sprintf('every_2nd_%s', $period);
                break;
            case 3 :
                $cart_key .= sprintf('every_3rd_%s', $period);
                break;
            default:
                $cart_key .= sprintf('every_%dth_%s', $interval, $period);
                break;
        }

        if ($length > 0) {
            $cart_key .= '_for_';
            $cart_key .= sprintf('%d_%s', $length, $period);
            if ($length > 1) {
                $cart_key .= 's';
            }
        }

        return apply_filters('hf_subscription_recurring_cart_key', $cart_key, $cart_item);
    }

    public function check_valid_add_to_cart($is_valid, $product_id, $quantity, $variation_id = '', $variations = array(), $item_data = array()) {

        if ($is_valid && !isset($item_data['subscription_renewal']) && hf_cart_contains_renewal() && HForce_Subscriptions_Product::is_subscription($product_id)) {
            wc_add_notice(__('That subscription product can not be added to your cart as it already contains a subscription renewal.', 'xa-woocommerce-subscription'), 'error');
            $is_valid = false;
        }

        return $is_valid;
    }


    public static function validate_recurring_shipping_methods() {

        $shipping_methods = WC()->checkout()->shipping_methods;
        $added_invalid_notice = false;
        $standard_packages = WC()->shipping->get_packages();

        $calculation_type = self::$calculation_type;
        self::$calculation_type = 'recurring_total';
        $recurring_cart_key_flag = self::$recurring_cart_key;

        foreach (WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart) {

            if (false === $recurring_cart->needs_shipping() || 0 == $recurring_cart->next_payment_date) {
                continue;
            }

            self::$recurring_cart_key = $recurring_cart_key;
            $packages = $recurring_cart->get_shipping_packages();
            foreach ($packages as $package_index => $base_package) {
                $package = self::get_calculated_shipping_for_package($base_package);

                if (( isset($standard_packages[$package_index]) && $package['rates'] == $standard_packages[$package_index]['rates'] ) && apply_filters('hf_cart_totals_shipping_html_price_only', true, $package, WC()->cart->recurring_carts[$recurring_cart_key])) {
                    continue;
                }

                $recurring_shipping_package_key = $recurring_cart_key . '_' . $package_index;

                if (!isset($package['rates'][$shipping_methods[$recurring_shipping_package_key]])) {

                    if (!$added_invalid_notice) {
                        wc_add_notice(__('Invalid recurring shipping method.', 'xa-woocommerce-subscription'), 'error');
                        $added_invalid_notice = true;
                    }

                    WC()->checkout()->shipping_methods[$recurring_shipping_package_key] = '';
                }
            }
        }

        self::$calculation_type = $calculation_type;
        self::$recurring_cart_key = $recurring_cart_key_flag;
    }

    public static function cart_contains_product($product_id) {

        $cart_contains_product = false;

        if (!empty(WC()->cart->cart_contents)) {
            foreach (WC()->cart->cart_contents as $cart_item) {
                if (hf_get_canonical_product_id($cart_item) == $product_id) {
                    $cart_contains_product = true;
                    break;
                }
            }
        }

        return $cart_contains_product;
    }

    private static $shipping_rates = array();
    
    public function cache_shipping_package_rates($rates, $package) {
        
        $package_shipping_rates_key = md5(json_encode(array(array_keys($package['contents']), $package['contents_cost'], $package['applied_coupons'])));
        self::$shipping_rates[$package_shipping_rates_key] = $rates;
        return $rates;
    }

    public static function get_calculated_shipping_for_package($package) {
        
        $package_shipping_rates_key = md5(json_encode(array(array_keys($package['contents']), $package['contents_cost'], $package['applied_coupons'])));

        if (isset(self::$shipping_rates[$package_shipping_rates_key])) {
            $package['rates'] = apply_filters('woocommerce_package_rates', self::$shipping_rates[$package_shipping_rates_key], $package);
        } else {
            $package = WC()->shipping->calculate_shipping_for_package($package);
        }

        return $package;
    }

}

new HForce_Subscription_Cart();