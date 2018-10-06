<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

global $post;

$subscription_price = get_post_meta($post->ID, '_hf_subscription_price', true);
$subscription_interval = get_post_meta($post->ID, '_subscription_period_interval', true);

if (!$subscription_period = get_post_meta($post->ID, '_subscription_period', true)) {
    $subscription_period = 'month';
}

$price_tooltip = __('Choose the subscription price, interval and period.', 'xa-woocommerce-subscription');


echo '<div class="options_group subscription_pricing show_if_subscription">';
?><p class="form-field _subscription_price_fields _hf_subscription_price_field">
    <label for="_hf_subscription_price"><?php printf(esc_html__('Subscription price (%s)', 'xa-woocommerce-subscription'), esc_html(get_woocommerce_currency_symbol())); ?></label>
    <span class="wrap">
        <input type="text" id="_hf_subscription_price" name="_hf_subscription_price" class="wc_input_price wc_input_hf_subscription_price" placeholder="<?php echo esc_attr_x('e.g. 69', 'example price', 'xa-woocommerce-subscription'); ?>" step="any" min="0" value="<?php echo esc_attr($subscription_price); ?>" />
        <label for="_subscription_period_interval" class="hf_hidden_label"><?php esc_html_e('Subscription interval', 'xa-woocommerce-subscription'); ?></label>
        <select id="_subscription_period_interval" name="_subscription_period_interval" class="wc_input_subscription_period_interval">
            <?php foreach (Hforce_Date_Time_Utils::get_subscription_period_interval_strings() as $value => $label) { ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $subscription_interval, true) ?>><?php echo esc_html($label); ?></option>
            <?php } ?>
        </select>
        <label for="_subscription_period" class="hf_hidden_label"><?php esc_html_e('Subscription period', 'xa-woocommerce-subscription'); ?></label>
        <select id="_subscription_period" name="_subscription_period" class="wc_input_subscription_period last" >
            <?php foreach (Hforce_Date_Time_Utils::subscription_period_strings() as $value => $label) { ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($value, $subscription_period, true) ?>><?php echo esc_html($label); ?></option>
            <?php } ?>
        </select>
    </span>
    <?php echo HForce_Woocommerce_Subscription_Admin::hforce_help_tooltip($price_tooltip); ?>
</p>
<?php
woocommerce_wp_select(
        array(
            'id' => '_subscription_length',
            'class' => 'wc_input_subscription_length select short',
            'label' => __('Subscription length', 'xa-woocommerce-subscription'),
            'options' => Hforce_Date_Time_Utils::hforce_get_subscription_ranges($subscription_period),
            'desc_tip' => true,
            'description' => __('Automatically expire the subscription after this length of time.', 'xa-woocommerce-subscription'),
        )
);
do_action('hf_subscription_product_options_pricing');
wp_nonce_field('hf_subscription_meta', '_hfnonce');
echo '</div>';
echo '<div class="show_if_subscription clear"></div>';