<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// My Acount link and content alteration

class HForce_Subscription_query extends WC_Query {

    public static $endpoint = 'subscriptions';

    public function __construct() {

        add_action('init', array($this, 'add_endpoints'));
        add_filter('the_title', array($this, 'change_endpoint_title'), 11, 1);

        if (!is_admin()) {

            add_filter('query_vars', array($this, 'add_query_vars'), 0);
            add_filter('woocommerce_account_menu_items', array($this, 'add_menu_items'));
            add_action('woocommerce_account_' . self::$endpoint . '_endpoint', array($this, 'render_endpoint_content'));
        }

        $this->init_query_vars();
    }

    public function init_query_vars() {

        $this->query_vars = array(
            'view-subscription' => get_option('woocommerce_myaccount_view_subscriptions_endpoint', 'view-subscription'),
        );
        if (!HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('2.6')) {
            $this->query_vars['subscriptions'] = get_option('woocommerce_myaccount_subscriptions_endpoint', 'subscriptions');
        }
    }

    public function change_endpoint_title($title) {

        if (in_the_loop() && is_account_page()) {
            foreach ($this->query_vars as $key => $query_var) {
                if ($this->is_query($query_var)) {
                    $title = $this->get_endpoint_title($key);
                    remove_filter('the_title', array($this, __FUNCTION__), 11);
                }
            }
        }
        return $title;
    }

    public function get_endpoint_title($endpoint) {

        global $wp;
        switch ($endpoint) {
            case 'view-subscription':
                $subscription = hforce_get_subscription($wp->query_vars['view-subscription']);
                $title = ( $subscription ) ? sprintf(__('Subscription #%s', 'xa-woocommerce-subscription'), $subscription->get_order_number()) : '';
                break;
            case 'subscriptions':
                $title = get_option(HForce_Woocommerce_Subscription_Admin::$option_prefix . '_subscription_tab_title', 'Subscriptions');
                if (empty($title))
                    $title = __('Subscriptions', 'xa-woocommerce-subscription');
                break;
            default:
                $title = '';
                break;
        }
        return $title;
    }

    public function add_menu_items($items) {

        $title = get_option(HForce_Woocommerce_Subscription_Admin::$option_prefix . '_subscription_tab_text', 'Subscriptions');
        if (empty($title))
            $title = __('Subscriptions', 'xa-woocommerce-subscription');

        $new_items = array();
        $new_items['subscriptions'] = $title;

        // Add the new item after `orders`.
        return $this->hf_insert_after_orders($items, $new_items, 'orders');
    }

    public function hf_insert_after_orders($items, $new_items, $after) {

        // Search for the item position and +1 since is after the selected item key.
        $position = array_search($after, array_keys($items)) + 1;

        // Insert the new item.
        $array = array_slice($items, 0, $position, true);
        $array += $new_items;
        $array += array_slice($items, $position, count($items) - $position, true);

        return $array;
    }
    
    public function render_endpoint_content() {
        HForce_Woocommerce_Subscription_Public::get_my_subscriptions_template();
    }

    protected function is_query($query_var) {
        global $wp;

        if (is_main_query() && is_page() && isset($wp->query_vars[$query_var])) {
            $is_view_subscription_query = true;
        } else {
            $is_view_subscription_query = false;
        }
        return apply_filters('is_hf_subscription_query', $is_view_subscription_query, $query_var);
    }

}

new HForce_Subscription_query();