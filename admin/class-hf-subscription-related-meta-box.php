<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class HForce_Meta_Box_Related_Orders {

    public function __construct() {
        ;
    }

    public static function output($post) {

        if (hforce_is_subscription($post->ID)) {
            $subscription = hforce_get_subscription($post->ID);
            $order = ( false == $subscription->get_parent_id() ) ? $subscription : $subscription->get_parent();
        } else {
            $order = wc_get_order($post->ID);
        }

        add_action('hf_subscription_related_orders_rows', 'HForce_Meta_Box_Related_Orders::output_related_orders', 10);
        self::related_orders_table_header($post);
        do_action('hf_subscription_related_orders_meta_box', $order, $post);
    }

    public static function output_related_orders($post) {

        $subscriptions = array();
        $orders = array();

        if (hforce_is_subscription($post->ID)) {
            $subscriptions[] = hforce_get_subscription($post->ID);
        } elseif (is_order_contains_subscription($post->ID, array('parent', 'renewal'))) {
            $subscriptions = get_subscriptions_by_order($post->ID, array('order_type' => array('parent', 'renewal')));
        }

        foreach ($subscriptions as $subscription) {
            hforce_set_objects_property($subscription, 'relationship', __('Subscription', 'xa-woocommerce-subscription'), 'set_prop_only');
            $orders[] = $subscription;
        }

        $initial_subscriptions = array();

        if (hforce_is_subscription($post->ID)) {

            $initial_subscriptions = hforce_get_subscriptions_for_resubscribe_order($post->ID);

            $resubscribed_subscriptions = get_posts(array(
                'meta_key' => '_subscription_resubscribe',
                'meta_value' => $post->ID,
                'post_type' => 'hf_shop_subscription',
                'post_status' => 'any',
                'posts_per_page' => -1,
            ));

            foreach ($resubscribed_subscriptions as $subscription) {
                $subscription = hforce_get_subscription($subscription);
                hforce_set_objects_property($subscription, 'relationship', __('Resubscribed Subscription', 'xa-woocommerce-subscription'), 'set_prop_only');
                $orders[] = $subscription;
            }
        } else if (is_order_contains_subscription($post->ID, array('resubscribe'))) {
            $initial_subscriptions = get_subscriptions_by_order($post->ID, array('order_type' => array('resubscribe')));
        }

        foreach ($initial_subscriptions as $subscription) {
            hforce_set_objects_property($subscription, 'relationship', __('Initial Subscription', 'xa-woocommerce-subscription'), 'set_prop_only');
            $orders[] = $subscription;
        }

        if (1 == count($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                if ($subscription->get_parent_id()) {
                    $order = $subscription->get_parent();
                    hforce_set_objects_property($order, 'relationship', __('Parent Order', 'xa-woocommerce-subscription'), 'set_prop_only');
                    $orders[] = $order;
                }
            }
        }

        foreach ($subscriptions as $subscription) {

            foreach ($subscription->get_related_orders('all', 'renewal') as $order) {
                hforce_set_objects_property($order, 'relationship', __('Renewal Order', 'xa-woocommerce-subscription'), 'set_prop_only');
                $orders[] = $order;
            }
        }

        $orders = apply_filters('hf_subscription_admin_related_orders_to_display', $orders, $subscriptions, $post);

        foreach ($orders as $order) {

            if (hforce_get_objects_property($order, 'id') == $post->ID) {
                continue;
            }


            $order_post = hforce_get_objects_property($order, 'post');
            ?>
            <tr>
                <td>
                    <a href="<?php echo esc_url(get_edit_post_link(hforce_get_objects_property($order, 'id'))); ?>">
                        <?php echo sprintf(esc_html_x('#%s', 'hash before order number', 'xa-woocommerce-subscription'), esc_html($order->get_order_number())); ?>
                    </a>
                </td>
                <td>
                    <?php echo esc_html(hforce_get_objects_property($order, 'relationship')); ?>
                </td>
                <td>
                    <?php
                    $timestamp_gmt = hforce_get_objects_property($order, 'date_created')->getTimestamp();
                    if ($timestamp_gmt > 0) {
                        $t_time = get_the_time(__('Y/m/d g:i:s A', 'xa-woocommerce-subscription'), $order_post);
                        $human_readable_date = Hforce_Date_Time_Utils::get_humanreadable_time_diff($timestamp_gmt);
                    } else {
                        $t_time = $human_readable_date = __('Unpublished', 'xa-woocommerce-subscription');
                    }
                    ?>
                    <abbr title="<?php echo esc_attr($t_time); ?>">
                        <?php echo esc_html($human_readable_date); ?>
                    </abbr>
                </td>
                <td>
                    <?php echo esc_html(ucwords($order->get_status())); ?>
                </td>
                <td>
                    <span class="amount"><?php echo wp_kses($order->get_formatted_order_total(), array('small' => array(), 'span' => array('class' => array()), 'del' => array(), 'ins' => array())); ?></span>
                </td>
            </tr> <?php
        }
    }

    public static function related_orders_table_header($post) {
        ?>
        <div class="hf_subscription_related_orders">
            <table>
                <thead>
                    <tr>
                        <th><?php _e('Order Number ', 'xa-woocommerce-subscription'); ?></th>
                        <th><?php _e('Relationship', 'xa-woocommerce-subscription'); ?></th>
                        <th><?php _e('Order Date', 'xa-woocommerce-subscription'); ?></th>
                        <th><?php _e('Order Status', 'xa-woocommerce-subscription'); ?></th>
                        <th><?php _e('Order Total', 'xa-woocommerce-subscription'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php do_action('hf_subscription_related_orders_rows', $post); ?>
                </tbody>
            </table>
        </div>
        <?php
    }

}

new HForce_Meta_Box_Related_Orders();