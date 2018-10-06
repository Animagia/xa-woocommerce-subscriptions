<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class HForce_Meta_Box_Subscription_Schedule {

    public static function output($post) {
        global $post, $the_subscription;

        if (empty($the_subscription)) {
            $the_subscription = hforce_get_subscription($post->ID);
        }
        ?>
        <div class="wc-metaboxes-wrapper">
            <div id="billing-schedule">
                <?php if ($the_subscription->can_date_be_updated('next_payment')) : ?>
                    <div class="billing-schedule-edit hf-date-input"><?php
                        echo woocommerce_wp_select(array(
                            'id' => '_billing_interval',
                            'class' => 'billing_interval',
                            'label' => __('Recurrence:', 'xa-woocommerce-subscription'),
                            'value' => $the_subscription->get_billing_interval(),
                            'options' => Hforce_Date_Time_Utils::get_subscription_period_interval_strings(),
                                )
                        );

                        echo woocommerce_wp_select(array(
                            'id' => '_billing_period',
                            'class' => 'billing_period',
                            'label' => __('Billing Period', 'xa-woocommerce-subscription'),
                            'value' => $the_subscription->get_billing_period(),
                            'options' => Hforce_Date_Time_Utils::subscription_period_strings(),
                                )
                        );
                        ?>
                        <input type="hidden" name="hf-lengths" id="hf-lengths" data-subscription_lengths="<?php echo esc_attr(hforce_json_encode(Hforce_Date_Time_Utils::hforce_get_subscription_ranges())); ?>">
                    </div>
                <?php else : ?>
                    <strong><?php esc_html_e('Recurrence:', 'xa-woocommerce-subscription'); ?></strong>
                    <?php printf('%s %s', esc_html(Hforce_Date_Time_Utils::get_subscription_period_interval_strings($the_subscription->get_billing_interval())), esc_html(Hforce_Date_Time_Utils::subscription_period_strings(1, $the_subscription->get_billing_period()))); ?>
                <?php endif; ?>
            </div>

            <?php foreach (hforce_get_subscription_available_date_types() as $date_key => $date_label) : ?>
                <?php $internal_date_key = hf_normalise_date_type_key($date_key) ?>
                <?php if (false === hforce_display_date_type($date_key, $the_subscription)) : ?>
                    <?php continue; ?>
                <?php endif; ?>
                <div id="subscription-<?php echo esc_attr($date_key); ?>-date" class="date-fields">
                    <strong><?php echo esc_html($date_label); ?>:</strong>
                    <input type="hidden" name="<?php echo esc_attr($date_key); ?>_timestamp_utc" id="<?php echo esc_attr($date_key); ?>_timestamp_utc" value="<?php echo esc_attr($the_subscription->get_time($internal_date_key, 'gmt')); ?>"/>
                    <?php if ($the_subscription->can_date_be_updated($internal_date_key)) : ?>
                        <?php echo wp_kses(Hforce_Date_Time_Utils::hf_date_input($the_subscription->get_time($internal_date_key, 'site'), array('name_attr' => $date_key)), array('input' => array('type' => array(), 'class' => array(), 'placeholder' => array(), 'name' => array(), 'id' => array(), 'maxlength' => array(), 'size' => array(), 'value' => array(), 'patten' => array()), 'div' => array('class' => array()), 'span' => array(), 'br' => array())); ?>
                    <?php else : ?>
                        <?php echo esc_html($the_subscription->get_date_to_display($internal_date_key)); ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public static function save($post_id, $post) {

        //echo '<pre>';print_r($_POST);exit;
        if ('hf_shop_subscription' == $post->post_type && !empty($_POST['hf_meta_nonce']) && wp_verify_nonce($_POST['hf_meta_nonce'], 'woocommerce_save_data')) {


            if (isset($_POST['_billing_interval'])) {
                update_post_meta($post_id, '_billing_interval', $_POST['_billing_interval']);
            }


            if (!empty($_POST['next_payment'])) {
                $next_payment_date = $_POST['next_payment'] . ' ' . $_POST['next_payment_hour'] . ':' . $_POST['next_payment_minute'] . ':' . date("s");
                update_post_meta($post_id, '_schedule_next_payment', $next_payment_date);
            } else {
                $next_payment_date = date('Y-m-d H:i:s', current_time('timestamp', true));
                update_post_meta($post_id, '_next_payment', $next_payment_date);
                update_post_meta($post_id, '_schedule_next_payment', $next_payment_date);
            }

            if (!empty($_POST['_billing_period'])) {
                update_post_meta($post_id, '_billing_period', $_POST['_billing_period']);
            }

            $subscription = hforce_get_subscription($post_id);

            $dates = array();

            foreach (hforce_get_subscription_available_date_types() as $date_type => $date_label) {
                $date_key = hf_normalise_date_type_key($date_type);

                if ('last_order_date_created' == $date_key) {
                    continue;
                }

                $utc_timestamp_key = $date_type . '_timestamp_utc';

                if ('date_created' === $date_key && empty($_POST[$utc_timestamp_key])) {
                    $datetime = current_time('timestamp', true);
                } elseif (isset($_POST[$utc_timestamp_key])) {
                    $datetime = $_POST[$utc_timestamp_key];
                } else {
                    continue;
                }

                $dates[$date_key] = gmdate('Y-m-d H:i:s', $datetime);
            }
            if (isset($next_payment_date)) {
                $dates['next_payment'] = $next_payment_date;
            }
            try {
                $subscription->update_dates($dates, 'gmt');

                wp_cache_delete($post_id, 'posts');
            } catch (Exception $e) {
                hf_add_admin_notice($e->getMessage(), 'error');
            }

            $subscription->save();
        }
    }

}