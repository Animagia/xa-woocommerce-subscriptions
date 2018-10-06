<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// get recurring shipping methods.

function hf_cart_totals_shipping_html() {

    $initial_packages = WC()->shipping->get_packages();
    $show_package_details = count(WC()->cart->recurring_carts) > 1 ? true : false;
    $show_package_name = true;

    foreach (WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart) {

        if (HForce_Subscription_Cart::cart_contains_subscriptions_needing_shipping() && 0 !== $recurring_cart->next_payment_date) {

            $packages = $recurring_cart->get_shipping_packages();

            foreach ($packages as $i => $base_package) {

                $product_names = array();
                $base_package['recurring_cart_key'] = $recurring_cart_key;

                $package = HForce_Subscription_Cart::get_calculated_shipping_for_package($base_package);
                $index = sprintf('%1$s_%2$d', $recurring_cart_key, $i);

                if ($show_package_details && isset($package['contents'])) {
                    foreach ($package['contents'] as $item_id => $values) {
                        $product_names[] = $values['data']->get_title() . ' &times;' . $values['quantity'];
                    }
                    $package_details = implode(', ', $product_names);
                } else {
                    $package_details = '';
                }

                $chosen_initial_method = isset(WC()->session->chosen_shipping_methods[$i]) ? WC()->session->chosen_shipping_methods[$i] : '';

                if (isset(WC()->session->chosen_shipping_methods[$recurring_cart_key . '_' . $i])) {
                    $chosen_recurring_method = WC()->session->chosen_shipping_methods[$recurring_cart_key . '_' . $i];
                } elseif (is_array($package) && in_array($chosen_initial_method, $package['rates'])) {
                    $chosen_recurring_method = $chosen_initial_method;
                } else {
                    $chosen_recurring_method = is_array($package) ? current($package['rates'])->id : $chosen_initial_method;
                }

                $shipping_selection_displayed = false;

                if (( 1 === count($package['rates']) ) || ( isset($package['rates'][$chosen_initial_method]) && isset($initial_packages[$i]) && $package['rates'] == $initial_packages[$i]['rates'] && apply_filters('hf_cart_totals_shipping_html_price_only', true, $package, $recurring_cart) )) {
                    $shipping_method = ( 1 === count($package['rates']) ) ? current($package['rates']) : $package['rates'][$chosen_initial_method];
                    ?>
                    <tr class="shipping recurring-total <?php echo esc_attr($recurring_cart_key); ?>">
                        <th><?php echo esc_html(sprintf(__('Shipping via %s', 'xa-woocommerce-subscription'), $shipping_method->label)); ?></th>
                        <td data-title="<?php echo esc_attr(sprintf(__('Shipping via %s', 'xa-woocommerce-subscription'), $shipping_method->label)); ?>">
                            <?php echo wp_kses_post(hf_cart_totals_shipping_method_price_label($shipping_method, $recurring_cart)); ?>
                            <?php if (1 === count($package['rates'])) : ?>
                                <?php hf_cart_render_shipping_input($index, $shipping_method); ?>
                                <?php do_action('woocommerce_after_shipping_rate', $shipping_method, $index); ?>
                            <?php endif; ?>
                            <?php if (!empty($show_package_details)) : ?>
                                <?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html($package_details) . '</small></p>'; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                } else {

                    $product_names = array();

                    $shipping_selection_displayed = true;

                    if ($show_package_name) {
                        $package_name = apply_filters('woocommerce_shipping_package_name', sprintf(_n('Shipping', 'Shipping %d', ( $i + 1), 'xa-woocommerce-subscription'), ( $i + 1)), $i, $package);
                    } else {
                        $package_name = '';
                    }

                    $args = array(
                        'package' => $package,
                        'available_methods' => $package['rates'],
                        'show_package_details' => $show_package_details,
                        'package_details' => $package_details,
                        'package_name' => $package_name,
                        'index' => $index,
                        'chosen_method' => $chosen_recurring_method,
                        'recurring_cart_key' => $recurring_cart_key,
                        'recurring_cart' => $recurring_cart,
                    );
                    wc_get_template('cart/cart-recurring-shipping.php', $args, '', HFORCE_SUBSCRIPTION_MAIN_PATH . 'public/templates/'
                    );
                    $show_package_name = false;
                }
                do_action('hf_subscriptions_after_recurring_shipping_rates', $index, $base_package, $recurring_cart, $chosen_recurring_method, $shipping_selection_displayed);
            }
        }
    }
}

function hf_cart_render_shipping_input($shipping_method_index, $shipping_method, $chosen_method = '', $input_type = 'hidden') {

    if ('radio' == $input_type) {
        $checked = checked($shipping_method->id, $chosen_method, false);
    } else {
        $input_type = 'hidden';
        $checked = '';
    }

    printf('<input type="%1$s" name="shipping_method[%2$s]" data-index="%2$s" id="shipping_method_%2$s_%3$s" value="%4$s" class="shipping_method shipping_method_%2$s" %5$s />', esc_attr($input_type), esc_attr($shipping_method_index), esc_attr(sanitize_title($shipping_method->id)), esc_attr($shipping_method->id), esc_attr($checked));
}

function hf_cart_totals_shipping_method($method, $cart) {

    $label = ( method_exists($method, 'get_label') ) ? $method->get_label() : $method->label; // WC < 2.5 compatibility (WC_Shipping_Rate::get_label() was introduced with WC 2.5)
    $label .= ': ' . hf_cart_totals_shipping_method_price_label($method, $cart);

    return apply_filters('hf_cart_totals_shipping_method', $label, $method, $cart);
}

function hf_cart_totals_shipping_method_price_label($method, $cart) {


    $price_label = '';

    if ($method->cost > 0) {

        if (WC()->cart->tax_display_cart == 'excl') {
            $price_label .= hf_cart_price_string($method->cost, $cart);
            if ($method->get_shipping_tax() > 0 && $cart->prices_include_tax) {
                $price_label .= ' <small>' . WC()->countries->ex_tax_or_vat() . '</small>';
            }
        } else {
            $price_label .= hf_cart_price_string($method->cost + $method->get_shipping_tax(), $cart);
            if ($method->get_shipping_tax() > 0 && !$cart->prices_include_tax) {
                $price_label .= ' <small>' . WC()->countries->inc_tax_or_vat() . '</small>';
            }
        }
    } elseif (!empty($cart->recurring_cart_key)) {
        $price_label .= __('Free', 'xa-woocommerce-subscription');
    }

    return $price_label;
}

function hf_cart_totals_coupon_html($coupon, $cart) {

    if (is_string($coupon)) {
        $coupon = new WC_Coupon($coupon);
    }

    $value = array();

    if ($amount = $cart->get_coupon_discount_amount(hf_get_coupon_property($coupon, 'code'), $cart->display_cart_ex_tax)) {
        $discount_html = '-' . wc_price($amount);
    } else {
        $discount_html = '';
    }


    $value[] = apply_filters('woocommerce_coupon_discount_amount_html', $discount_html, $coupon);

    if (hf_get_coupon_property($coupon, 'enable_free_shipping')) {
        $value[] = __('Free shipping coupon', 'xa-woocommerce-subscription');
    }

    $value = implode(', ', array_filter($value));
    $value = apply_filters('woocommerce_cart_totals_coupon_html', $value, $coupon);
    echo wp_kses_post(apply_filters('hf_cart_totals_coupon_html', hf_cart_price_string($value, $cart), $coupon, $cart));
}

function hf_cart_totals_order_total_html($cart) {

    $value = '<strong>' . $cart->get_total() . '</strong> ';

    if (wc_tax_enabled() && $cart->tax_display_cart == 'incl') {
        $tax_string_array = array();

        if (get_option('woocommerce_tax_total_display') == 'itemized') {
            foreach ($cart->get_tax_totals() as $code => $tax) {
                $tax_string_array[] = sprintf('%s %s', $tax->formatted_amount, $tax->label);
            }
        } else {
            $tax_string_array[] = sprintf('%s %s', wc_price($cart->get_taxes_total(true, true)), WC()->countries->tax_or_vat());
        }

        if (!empty($tax_string_array)) {
            $value .= '<small class="includes_tax">' . sprintf(__('(Includes %s)', 'xa-woocommerce-subscription'), implode(', ', $tax_string_array)) . '</small>';
        }
    }

    $value = apply_filters('woocommerce_cart_totals_order_total_html', $value);

    echo wp_kses_post(apply_filters('hf_cart_totals_order_total_html', hf_cart_price_string($value, $cart), $cart));
}

function hf_cart_price_string($recurring_amount, $cart) {

    return hforce_price_string(apply_filters('hf_cart_subscription_string_details', array(
        'recurring_amount' => $recurring_amount,
        'subscription_interval' => hf_cart_pluck($cart, 'subscription_period_interval'),
        'subscription_period' => hf_cart_pluck($cart, 'subscription_period', ''),
        'subscription_length' => hf_cart_pluck($cart, 'subscription_length'),
    )));
}

function hf_cart_pluck($cart, $field, $default = 0) {

    $value = $default;

    if (isset($cart->$field)) {
        $value = $cart->$field;
    } else {
        foreach ($cart->get_cart() as $cart_item) {

            if (isset($cart_item[$field])) {
                $value = $cart_item[$field];
            } else {
                $value = HForce_Subscriptions_Product::get_meta_data($cart_item['data'], $field, $default);
            }
        }
    }

    return $value;
}

function hf_cart_first_renewal_payment_date($order_total_html, $cart) {

    if (0 !== $cart->next_payment_date) {
        $first_renewal_date = date_i18n(wc_date_format(), Hforce_Date_Time_Utils::date_to_time(get_date_from_gmt($cart->next_payment_date)));
        $order_total_html .= '<div class="first-payment-date"><small>' . sprintf(__('First renewal date: %s', 'xa-woocommerce-subscription'), $first_renewal_date) . '</small></div>';
    }

    return $order_total_html;
}

add_filter('hf_cart_totals_order_total_html', 'hf_cart_first_renewal_payment_date', 10, 2);

function hf_get_cart_item_name($cart_item, $include = array()) {

    $include = wp_parse_args($include, array(
        'attributes' => false,
    ));

    $cart_item_name = $cart_item['data']->get_title();

    if ($include['attributes']) {

        $attributes_string = WC()->cart->get_item_data($cart_item, true);
        $attributes_string = implode(', ', array_filter(explode("\n", $attributes_string)));

        if (!empty($attributes_string)) {
            $cart_item_name = sprintf('%s (%s)', $cart_item_name, $attributes_string);
        }
    }

    return $cart_item_name;
}