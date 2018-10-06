<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

function hforce_price_string($subscription_details) {

    global $wp_locale;
    $subscription_details = wp_parse_args($subscription_details, array(
                'currency' => '',
                'initial_amount' => '',
                'initial_description' => __('up front', 'xa-woocommerce-subscription'),
                'recurring_amount' => '',
                'subscription_interval' => 1,
                'subscription_period' => '',
                'subscription_length' => 0,                
                'display_excluding_tax_label' => false,
            )
    );

    $subscription_details['subscription_period'] = strtolower($subscription_details['subscription_period']);

    if (is_numeric($subscription_details['initial_amount'])) {
        $initial_amount_string = wc_price($subscription_details['initial_amount'], array('currency' => $subscription_details['currency'], 'ex_tax_label' => $subscription_details['display_excluding_tax_label']));
    } else {
        $initial_amount_string = $subscription_details['initial_amount'];
    }

    if (is_numeric($subscription_details['recurring_amount'])) {
        $recurring_amount_string = wc_price($subscription_details['recurring_amount'], array('currency' => $subscription_details['currency'], 'ex_tax_label' => $subscription_details['display_excluding_tax_label']));
    } else {
        $recurring_amount_string = $subscription_details['recurring_amount'];
    }

    $subscription_period_string = Hforce_Date_Time_Utils::subscription_period_strings($subscription_details['subscription_interval'], $subscription_details['subscription_period']);
    $subscription_ranges = Hforce_Date_Time_Utils::hforce_get_subscription_ranges();

    if ($subscription_details['subscription_length'] > 0 && $subscription_details['subscription_length'] == $subscription_details['subscription_interval']) {
        if (!empty($subscription_details['initial_amount'])) {
            if ($subscription_details['subscription_interval'] == $subscription_details['subscription_length'] && 0 == $subscription_details['trial_length']) {
                $subscription_string = $initial_amount_string;
            } else {
                $subscription_string = sprintf(__('%1$s %2$s then %3$s', 'xa-woocommerce-subscription'), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string);
            }
        } else {
            $subscription_string = $recurring_amount_string;
        }
    }elseif (!empty($subscription_details['initial_amount'])) {
        $subscription_string = sprintf(_n('%1$s %2$s then %3$s / %4$s', '%1$s %2$s then %3$s every %4$s', $subscription_details['subscription_interval'], 'xa-woocommerce-subscription'), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, $subscription_period_string);
    } elseif (!empty($subscription_details['recurring_amount']) || intval($subscription_details['recurring_amount']) === 0) {
        $subscription_string = sprintf(_n('%1$s / %2$s', '%1$s every %2$s', $subscription_details['subscription_interval'], 'xa-woocommerce-subscription'), $recurring_amount_string, $subscription_period_string);
    } else {
        $subscription_string = '';
    }

    if ($subscription_details['subscription_length'] > 0) {
        $subscription_string = sprintf(__('%1$s for %2$s', 'xa-woocommerce-subscription'), $subscription_string, $subscription_ranges[$subscription_details['subscription_period']][$subscription_details['subscription_length']]);
    }

    if ($subscription_details['display_excluding_tax_label'] && wc_tax_enabled()) {
        $subscription_string .= ' <small>' . WC()->countries->ex_tax_or_vat() . '</small>';
    }

    return apply_filters('hf_subscription_price_string', $subscription_string, $subscription_details);
}


function hf_get_price_including_tax($product, $args = array()) {

    $args = wp_parse_args($args, array('qty' => 1,'price' => $product->get_price(),));

    if (function_exists('wc_get_price_including_tax')) {
        $price = wc_get_price_including_tax($product, $args);
    } else {
        $price = $product->get_price_including_tax($args['qty'], $args['price']);
    }
    return $price;
}

function hf_get_price_excluding_tax($product, $args = array()) {

    $args = wp_parse_args($args, array( 'qty' => 1, 'price' => $product->get_price(), ));

    if (function_exists('wc_get_price_excluding_tax')) {
        $price = wc_get_price_excluding_tax($product, $args);
    } else {
        $price = $product->get_price_excluding_tax($args['qty'], $args['price']);
    }
    return $price;
}

function hf_get_price_html_from_text($product = '') {

    if (function_exists('wc_get_price_html_from_text')) {
        $price_html_from_text = wc_get_price_html_from_text();
    } else {
        $price_html_from_text = $product->get_price_html_from_text();
    }
    return $price_html_from_text;
}