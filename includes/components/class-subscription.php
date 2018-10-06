<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class HForce_Subscription extends WC_Order {

    
    
    // Stores data about status changes so relevant hooks can be fired.
    //protected $status_transition = false;
    protected $order = null;
    public $order_type = 'hf_shop_subscription';
    protected $data_store_name = 'subscription';
    protected $object_type = 'subscription';
    private $is_editable;
    protected $valid_date_types = array();
    protected $extra_data = array(
                'billing_period' => '',
                'billing_interval' => 1,
                'suspension_count' => 0,
                'requires_manual_renewal' => 'true',
                'schedule_next_payment' => null,
                'schedule_cancelled' => null,
                'schedule_end' => null,
                'schedule_payment_retry' => null,
               
    );    
    private $old_subscription_properties = array(
                'start_date',
                'next_payment_date',
                'end_date',
                'last_payment_date',
                'order',
                'payment_gateway',
                'requires_manual_renewal',
                'suspension_count',
    );

    public function __construct($subscription) {

        parent::__construct($subscription);
        $this->order_type = 'hf_shop_subscription';
    }

    public function get_type() {
        return $this->order_type;
    }

    public function __isset($key) {

        if (!HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.0') && in_array($key, $this->old_subscription_properties)) {
            $is_set = true;
        } else {
            $is_set = parent::__isset($key);
        }
        return $is_set;
    }

    public function __set($key, $value) {

        if (in_array($key, $this->old_subscription_properties)) {

            switch ($key) {

                case 'order' :
                    $function = 'HForce_Subscription::set_parent_id( $order_id )';
                    $this->set_parent_id(hforce_get_objects_property($value, 'id'));
                    $this->order = $value;
                    break;

                case 'requires_manual_renewal' :
                    $function = 'HForce_Subscription::set_requires_manual_renewal()';
                    $this->set_requires_manual_renewal($value);
                    break;

                case 'payment_gateway' :
                    $function = 'HForce_Subscription::set_payment_method()';
                    $this->set_payment_method($value);
                    break;

                case 'suspension_count' :
                    $function = 'HForce_Subscription::set_suspension_count()';
                    $this->set_suspension_count($value);
                    break;

                default :
                    $function = 'HForce_Subscription::update_dates()';
                    $this->update_dates(array($key => $value));
                    break;
            }

            if (!HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.0')) {
                hf_doing_it_wrong($key, sprintf('Subscription properties should not be set directly as WooCommerce 3.0 no longer supports direct property access. Use %s instead.', $function), '2.2.0');
            }
        } else {

            $this->$key = $value;
        }
    }

    public function __get($key) {

        if (in_array($key, $this->old_subscription_properties)) {

            switch ($key) {

                case 'order' :
                    $function = 'HForce_Subscription::get_parent()';
                    $value = $this->get_parent();
                    break;

                case 'requires_manual_renewal' :
                    $function = 'HForce_Subscription::get_requires_manual_renewal()';
                    $value = $this->get_requires_manual_renewal() ? 'true' : 'false'; // We now use booleans for getter return values, so we need to convert it when being accessed via the old property approach to the string value returned
                    break;

                case 'payment_gateway' :
                    $function = 'wc_get_payment_gateway_by_order( $subscription )';
                    $value = wc_get_payment_gateway_by_order($this);
                    break;

                case 'suspension_count' :
                    $function = 'HForce_Subscription::get_suspension_count()';
                    $value = $this->get_suspension_count();
                    break;

                default :
                    $function = 'HForce_Subscription::get_date( ' . $key . ' )';
                    $value = $this->get_date($key);
                    break;
            }

            if (!HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.0')) {
                hf_doing_it_wrong($key, sprintf('Subscription properties should not be accessed directly as WooCommerce 3.0 no longer supports direct property access. Use %s instead.', $function), '2.2.0');
            }
        } else {

            $value = parent::__get($key);
        }

        return $value;
    }

    
    	/**
	 * When a payment is complete this function is called.
	 *
	 * Most of the time this should mark an order as 'processing' so that admin can process/post the items.
	 * If the cart contains only downloadable items then the order is 'completed' since the admin needs to take no action.
	 * Stock levels are reduced at this point.
	 * Sales are also recorded for products.
	 * Finally, record the date of payment.
	 *
	 * Order must exist.
	 *
	 * @param string $transaction_id Optional transaction id to store in post meta.
	 * @return bool success
	 */
    private $completed_payment_count = false;
    public function payment_complete($transaction_id = '') {

        $this->completed_payment_count = false;
        $last_order = $this->get_last_order('all', 'any');

        if (false !== $last_order && $last_order->needs_payment()) {
            $last_order->payment_complete($transaction_id);
        }
        $this->set_suspension_count(0);

        hf_update_users_role($this->get_user_id(), 'default_subscriber_role');

        $note = __('Payment received.', 'xa-woocommerce-subscription');       

        $this->add_order_note($note);
        $this->update_status('active');
        do_action('hf_subscription_payment_complete', $this);

        if (false !== $last_order && is_order_contains_renewal($last_order)) {
            do_action('hf_subscription_renewal_payment_complete', $this, $last_order);
        }
    }

    /**
     * Gets order total - formatted for display.
     *
     * @param string $tax_display      Type of tax display.
     * @param bool   $display_refunded If should include refunded value.
     *
     * @return string
     */
    public function get_formatted_order_total($tax_display = '', $display_refunded = true) {
        
        if ($this->get_total() > 0 && '' !== $this->get_billing_period() && !$this->is_one_payment()) {
            $formatted_order_total = hforce_price_string($this->get_price_string_details($this->get_total()));
        } else {
            $formatted_order_total = parent::get_formatted_order_total();
        }
        return apply_filters('woocommerce_get_formatted_subscription_total', $formatted_order_total, $this);
        
    }
    
    
    
    /**
     * Checks if an order needs payment, based on status and order total.
     *
     * @return bool
     */
    public function needs_payment() {

        $needs_payment = false;
        if (parent::needs_payment()) {
            $needs_payment = true;
        } elseif (( $parent_order = $this->get_parent() ) && ( $parent_order->needs_payment() || $parent_order->has_status('on-hold') )) {
            $needs_payment = true;
        } else {

            $last_order_id = get_posts(array(
                'posts_per_page' => 1,
                'post_type' => 'shop_order',
                'post_status' => 'any',
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'DESC',
                'meta_query' => array(
                    array(
                        'key' => '_subscription_renewal',
                        'compare' => '=',
                        'value' => $this->get_id(),
                        'type' => 'numeric',
                    ),
                ),
                    ));

            if (!empty($last_order_id)) {

                $order = wc_get_order($last_order_id[0]);

                if ($order->needs_payment() || $order->has_status(array('on-hold', 'failed', 'cancelled'))) {
                    $needs_payment = true;
                }
            }
        }

        return apply_filters('hf_subscription_needs_payment', $needs_payment, $this);
    }

    public function payment_method_supports($payment_gateway_feature) {

        if ($this->is_manual() || ( false !== ( $payment_gateway = wc_get_payment_gateway_by_order($this) ) && $payment_gateway->supports($payment_gateway_feature) )) {
            $payment_gateway_supports = true;
        } else {
            $payment_gateway_supports = false;
        }

        return apply_filters('hf_subscription_payment_gateway_supports', $payment_gateway_supports, $payment_gateway_feature, $this);
    }

    public function can_be_updated_to($new_status) {

        $new_status = ( 'wc-' === substr($new_status, 0, 3) ) ? substr($new_status, 3) : $new_status;

        switch ($new_status) {
            case 'pending' :
                if ($this->has_status(array('auto-draft', 'draft'))) {
                    $can_be_updated = true;
                } else {
                    $can_be_updated = false;
                }
                break;
            case 'completed' :
            case 'active' :
                if ($this->payment_method_supports('subscription_reactivation') && $this->has_status('on-hold')) {
                    $can_be_updated = true;
                } elseif ($this->has_status('pending')) {
                    $can_be_updated = true;
                } else {
                    $can_be_updated = false;
                }
                break;
            case 'failed' :
            case 'on-hold' :
                if ($this->payment_method_supports('subscription_suspension') && $this->has_status(array('active', 'pending'))) {
                    $can_be_updated = true;
                } else {
                    $can_be_updated = false;
                }
                break;
            case 'cancelled' :
                if ($this->payment_method_supports('subscription_cancellation') && ( $this->has_status('pending-cancel') || !$this->has_status(hforce_get_subscription_ended_statuses()) )) {
                    $can_be_updated = true;
                } else {
                    $can_be_updated = false;
                }
                break;
            case 'pending-cancel' :
                if ($this->payment_method_supports('subscription_cancellation') && $this->has_status('active')) {
                    $can_be_updated = true;
                } else {
                    $can_be_updated = false;
                }
                break;
            case 'expired' :
                if (!$this->has_status(array('cancelled', 'trash', 'switched'))) {
                    $can_be_updated = true;
                } else {
                    $can_be_updated = false;
                }
                break;
            case 'trash' :
                if ($this->has_status(hforce_get_subscription_ended_statuses()) || $this->can_be_updated_to('cancelled')) {
                    $can_be_updated = true;
                } else {
                    $can_be_updated = false;
                }
                break;
            case 'deleted' :
                if ('trash' == $this->get_status()) {
                    $can_be_updated = true;
                } else {
                    $can_be_updated = false;
                }
                break;
            default :
                $can_be_updated = apply_filters('woocommerce_can_subscription_be_updated_to', false, $new_status, $this);
                break;
        }

        return apply_filters('hf_subscription_can_be_updated_to_' . $new_status, $can_be_updated, $this);
    }

    
    
    /**
     * Updates status of order immediately. Order must exist.
     *
     * @uses WC_Order::set_status()
     * @param string $new_status    Status to change the order to. No internal wc- prefix is required.
     * @param string $note          Optional note to add.
     * @param bool   $manual        Is this a manual order status change?.
     * @return bool
     */
    public function update_status($new_status, $note = '', $manual = false) {

        
        if (!$this->get_id()) {
            return;
        }
        
        $new_status = ( 'wc-' === substr($new_status, 0, 3) ) ? substr($new_status, 3) : $new_status;
        $new_status_key = 'wc-' . $new_status;
        $old_status = ( 'wc-' === substr($this->get_status(), 0, 3) ) ? substr($this->get_status(), 3) : $this->get_status();
        $old_status_key = 'wc-' . $old_status;

        if ($new_status !== $old_status || !in_array($old_status_key, array_keys(hforce_get_subscription_statuses()))) {

            do_action('hf_subscription_pre_update_status', $old_status, $new_status, $this);

            if (!$this->can_be_updated_to($new_status)) {

                $message = sprintf(__('Unable to change subscription status to "%s".', 'xa-woocommerce-subscription'), $new_status);
                $this->add_order_note($message);
                do_action('hf_subscription_unable_to_update_status', $this, $new_status, $old_status);
                throw new Exception($message);
            }

            try {

                $this->set_status($new_status, $note, $manual);

                switch ($new_status) {

                    case 'pending' :
                        break;

                    case 'pending-cancel' :

                        $end_date = $this->calculate_date('end_of_prepaid_term');

                        if (0 == $end_date || Hforce_Date_Time_Utils::convert_date_to_time($end_date) < current_time('timestamp', true)) {
                            $cancelled_date = $end_date = current_time('mysql', true);
                        } else {
                            $cancelled_date = current_time('mysql', true);
                        }

                        $this->delete_date('next_payment');
                        $this->update_dates(array('cancelled' => $cancelled_date, 'end' => $end_date));
                        break;

                    case 'completed' :
                    case 'active' :
                        $stored_next_payment = $this->get_time('next_payment');
                        if ($stored_next_payment < ( gmdate('U') + apply_filters('hf_subscription_activation_next_payment_date_threshold', 2 * HOUR_IN_SECONDS, $stored_next_payment, $old_status, $this) )) {

                            $calculated_next_payment = $this->calculate_date('next_payment');
                            if ($calculated_next_payment > 0) {
                                $this->update_dates(array('next_payment' => $calculated_next_payment));
                            } elseif ($stored_next_payment < gmdate('U')) {
                                $this->delete_date('next_payment');
                            }
                        } else {
                            do_action('hf_subscription_activation_next_payment_not_recalculated', $stored_next_payment, $old_status, $this);
                        }
                        
                        hf_update_users_role( $this->get_user_id(), 'default_subscriber_role' );
                        break;

                    case 'failed' :
                    case 'on-hold' :
                        $this->set_suspension_count($this->get_suspension_count() + 1);
                        break;
                    case 'cancelled' :
                    case 'expired' :
                        $this->delete_date('next_payment');

                        $dates_to_update = array(
                            'end' => current_time('mysql', true),
                        );

                        if ('cancelled' === $new_status && 0 == $this->get_date('cancelled')) {
                            $dates_to_update['cancelled'] = $dates_to_update['end'];
                        }

                        $this->update_dates($dates_to_update);
                        break;
                }

                $this->save();
            } catch (Exception $e) {
                $log = new WC_Logger();
                $log_entry = print_r($e, true);
                $log_entry .= 'Exception Trace: ' . print_r($e->getTraceAsString(), true);
                $log->add('hf-update-status-failures', $log_entry);
                $this->set_status($old_status, $note, $manual);
                $this->status_transition = false;
                $this->add_order_note(sprintf(__('Unable to change subscription status to "%s". Exception: %s', 'xa-woocommerce-subscription'), $new_status, $e->getMessage()));
                $this->save();
                do_action('hf_subscription_unable_to_update_status', $this, $new_status, $old_status);
                throw $e;
            }
        }
    }

    public function is_manual() {

        if (true === $this->get_requires_manual_renewal() || false === wc_get_payment_gateway_by_order($this)) {
            $is_manual = true;
        } else {
            $is_manual = false;
        }
       
        return $is_manual;
    }

    public function get_status($context = 'view') {

        if (in_array(get_post_status($this->get_id()), array('draft', 'auto-draft'))) {
            $this->post_status = 'wc-pending';
            $status = apply_filters('woocommerce_order_get_status', 'pending', $this);
        } else {
            $status = parent::get_status();
        }

        return $status;
    }

    public function get_valid_statuses() {
        return array_keys(hforce_get_subscription_statuses());
    }

    public function get_paid_order_statuses() {
        $paid_statuses = array(
            'processing',
            'completed',
            'wc-processing',
            'wc-completed',
        );

        $custom_status = apply_filters('woocommerce_payment_complete_order_status', 'completed', $this->get_id());

        if ('' !== $custom_status && !in_array($custom_status, $paid_statuses) && !in_array('wc-' . $custom_status, $paid_statuses)) {
            $paid_statuses[] = $custom_status;
            $paid_statuses[] = 'wc-' . $custom_status;
        }

        return apply_filters('hf_subscription_paid_order_statuses', $paid_statuses, $this);
    }

    
    
    
    	//  Handle the status transition.
	 
	protected function status_transition() {

                    
		if ( $this->status_transition ) {
                    
                        
			do_action( 'hf_subscription_status_' . $this->status_transition['to'], $this );

			if ( ! empty( $this->status_transition['from'] ) ) {
				$transition_note = sprintf( __( 'Status changed from %1$s to %2$s.', 'xa-woocommerce-subscription' ), hforce_get_subscription_status_name( $this->status_transition['from'] ), hforce_get_subscription_status_name( $this->status_transition['to'] ) );

				do_action( 'hf_subscription_status_' . $this->status_transition['from'] . '_to_' . $this->status_transition['to'], $this );
				do_action( 'hf_subscription_status_updated', $this, $this->status_transition['to'], $this->status_transition['from'] );
				do_action( 'hf_subscription_status_changed', $this->get_id(), $this->status_transition['from'], $this->status_transition['to'], $this );

			} else {
				$transition_note = sprintf( __( 'Status set to %s.', 'xa-woocommerce-subscription' ), hforce_get_subscription_status_name( $this->status_transition['to'] ) );
			}

			$this->add_order_note( trim( $this->status_transition['note'] . ' ' . $transition_note ), 0, $this->status_transition['manual'] );
			$this->status_transition = false;
		}
	}
    
    
    
    
    public function get_completed_payment_count() {

        if (false === $this->completed_payment_count) {

            $completed_payment_count = ( ( $parent_order = $this->get_parent() ) && ( null !== hforce_get_objects_property($parent_order, 'date_paid') || $parent_order->has_status($this->get_paid_order_statuses()) ) ) ? 1 : 0;

            $renewal_orders = get_posts(array(
                'posts_per_page' => -1,
                'post_status' => 'any',
                'post_type' => 'shop_order',
                'fields' => 'ids',
                'orderby' => 'date',
                'order' => 'desc',
                'meta_key' => '_subscription_renewal',
                'meta_compare' => '=',
                'meta_type' => 'numeric',
                'meta_value' => $this->get_id(),
                'update_post_term_cache' => false,
                    ));

            if (!empty($renewal_orders)) {

                $paid_status_renewal_orders = get_posts(array(
                    'posts_per_page' => -1,
                    'post_status' => $this->get_paid_order_statuses(),
                    'post_type' => 'shop_order',
                    'fields' => 'ids',
                    'orderby' => 'date',
                    'order' => 'desc',
                    'post__in' => $renewal_orders,
                        ));

                $paid_date_renewal_orders = get_posts(array(
                    'posts_per_page' => -1,
                    'post_status' => 'any',
                    'post_type' => 'shop_order',
                    'fields' => 'ids',
                    'orderby' => 'date',
                    'order' => 'desc',
                    'post__in' => $renewal_orders,
                    'meta_key' => '_paid_date',
                    'meta_compare' => 'EXISTS',
                    'update_post_term_cache' => false,
                        ));

                $paid_renewal_orders = array_unique(array_merge($paid_date_renewal_orders, $paid_status_renewal_orders));

                if (!empty($paid_renewal_orders)) {
                    $completed_payment_count += count($paid_renewal_orders);
                }
            }
        } else {
            $completed_payment_count = $this->completed_payment_count;
        }

        $this->completed_payment_count = apply_filters('hf_subscription_payment_completed_count', $completed_payment_count, $this);
        return $this->completed_payment_count;
    }

    public function get_failed_payment_count() {

        $failed_payment_count = ( ( $parent_order = $this->get_parent() ) && $parent_order->has_status('wc-failed') ) ? 1 : 0;

        $failed_renewal_orders = get_posts(array(
            'posts_per_page' => -1,
            'post_status' => 'wc-failed',
            'post_type' => 'shop_order',
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'desc',
            'meta_query' => array(
                array(
                    'key' => '_subscription_renewal',
                    'compare' => '=',
                    'value' => $this->get_id(),
                    'type' => 'numeric',
                ),
            ),
                ));

        if (!empty($failed_renewal_orders)) {
            $failed_payment_count += count($failed_renewal_orders);
        }

        return apply_filters('hf_subscription_payment_failed_count', $failed_payment_count, $this);
    }

    public function get_billing_period($context = 'view') {
        return $this->get_prop('billing_period', $context);
    }

    public function get_billing_interval($context = 'view') {
        return $this->get_prop('billing_interval', $context);
    }

    public function get_suspension_count($context = 'view') {
        return $this->get_prop('suspension_count', $context);
    }

     // set the manual renewal flag on the subscription.
    public function set_requires_manual_renewal($value) {

        if (!is_bool($value)) {
            if ('false' === $value || '' === $value) {
                $value = false;
            } else { 
                $value = true;
            }
        }
        $this->set_prop('requires_manual_renewal', $value);
    }
    
    public function get_requires_manual_renewal($context = 'view') {
        return $this->get_prop('requires_manual_renewal', $context);
    }


    public function set_billing_period($value) {
        $this->set_prop('billing_period', $value);
    }

    public function set_billing_interval($value) {
        $this->set_prop('billing_interval', (string) absint($value));
    }

    public function set_suspension_count($value) {
        $this->set_prop('suspension_count', absint($value));
    }

    public function set_parent_id($value) {
        $this->set_prop('parent_id', absint($value));
        $this->order = null;
    }


    public function get_date($date_type, $timezone = 'gmt') {

        $date_type = hf_normalise_date_type_key($date_type, true);

        if (empty($date_type)) {
            $date = 0;
        } else {
            switch ($date_type) {
                case 'date_created' :
                    $date = $this->get_date_created();
                    $date = is_null($date) ? Hforce_Date_Time_Utils::hf_get_datetime_from(get_the_date('Y-m-d H:i:s', $this->get_id())) : $date;
                    break;
                case 'date_modified' :
                    $date = $this->get_date_modified();
                    break;
                case 'date_paid' :
                    $date = $this->get_date_paid();
                    break;
                case 'date_completed' :
                    $date = $this->get_date_completed();
                    break;
                case 'last_order_date_created' :
                    $date = $this->get_related_orders_date('date_created', 'last');
                    break;
                case 'last_order_date_paid' :
                    $date = $this->get_related_orders_date('date_paid', 'last');
                    break;
                case 'last_order_date_completed' :
                    $date = $this->get_related_orders_date('date_completed', 'last');
                    break;
                default :
                    $date = $this->get_date_prop($date_type);
                    break;
            }

            if (is_null($date)) {
                $date = 0;
            }
        }

        if (is_a($date, 'DateTime')) {
            $date = clone $date;

            if ('gmt' === strtolower($timezone)) {
                $date->setTimezone(new DateTimeZone('UTC'));
            }
            $date = $date->format('Y-m-d H:i:s');
        }
        return apply_filters('hf_subscription_get_' . $date_type . '_date', $date, $this, $timezone);
    }

    protected function get_date_prop($date_type) {
        return $this->get_prop($this->get_date_prop_key($date_type));
    }

    protected function set_date_prop($date_type, $value) {
        parent::set_date_prop($this->get_date_prop_key($date_type), $value);
    }

    protected function get_date_prop_key($date_type) {
        $prefixed_date_type = maybe_add_prefix_key($date_type, 'schedule_');
        return array_key_exists($prefixed_date_type, $this->extra_data) ? $prefixed_date_type : $date_type;
    }

    public function get_date_paid($context = 'view') {
        return $this->get_related_orders_date('date_paid');
    }

    public function set_date_paid($date = null) {
        $this->set_last_order_date('date_paid', $date);
    }

    public function get_date_completed($context = 'view') {
        return $this->get_related_orders_date('date_completed');
    }

    public function set_date_completed($date = null) {
        $this->set_last_order_date('date_completed', $date);
    }

    protected function get_related_orders_date($date_type, $order_type = 'all') {

        $date = null;

        if ('last' === $order_type) {
            $last_order = $this->get_last_order('all');
            $date = (!$last_order ) ? null : hforce_get_objects_property($last_order, $date_type);
        } else {
            foreach ($this->get_related_orders('ids', $order_type) as $related_order_id) {
                $related_order = wc_get_order($related_order_id);
                $date = (!$related_order ) ? null : hforce_get_objects_property($related_order, $date_type);
                if (is_a($date, 'WC_Datetime')) {
                    break;
                }
            }
        }

        return $date;
    }

    protected function set_last_order_date($date_type, $date = null) {

        if ($this->object_read) {

            $setter = 'set_' . $date_type;
            $last_order = $this->get_last_order('all');

            if ($last_order && is_callable(array($last_order, $setter))) {
                $last_order->{$setter}($date);
                $last_order->save();
            }
        }
    }

    public function get_date_to_display($date_type = 'next_payment') {

        $date_type = hf_normalise_date_type_key($date_type, true);
        $timestamp_gmt = $this->get_time($date_type, 'gmt');

        if ('next_payment' == $date_type && !$this->has_status('active')) {
            $timestamp_gmt = 0;
        }

        if ($timestamp_gmt > 0) {

            $time_diff = $timestamp_gmt - current_time('timestamp', true);

            if ($time_diff > 0 && $time_diff < WEEK_IN_SECONDS) {
                $date_to_display = sprintf(__('In %s', 'xa-woocommerce-subscription'), human_time_diff(current_time('timestamp', true), $timestamp_gmt));
            } elseif ($time_diff < 0 && absint($time_diff) < WEEK_IN_SECONDS) {
                $date_to_display = sprintf(__('%s ago', 'xa-woocommerce-subscription'), human_time_diff(current_time('timestamp', true), $timestamp_gmt));
            } else {
                $date_to_display = date_i18n(wc_date_format(), $this->get_time($date_type, 'site'));
            }
        } else {
            switch ($date_type) {
                case 'end' :
                    $date_to_display = __('Not yet ended', 'xa-woocommerce-subscription');
                    break;
                case 'cancelled' :
                    $date_to_display = __('Not cancelled', 'xa-woocommerce-subscription');
                    break;
                case 'next_payment' :
                default :
                    $date_to_display = __('-', 'xa-woocommerce-subscription');
                    break;
            }
        }

        return apply_filters('hf_subscription_date_to_display', $date_to_display, $date_type, $this);
    }

    public function get_time($date_type, $timezone = 'gmt') {

        $datetime = $this->get_date($date_type, $timezone);
        $datetime = Hforce_Date_Time_Utils::convert_date_to_time($datetime);
        return $datetime;
    }

    public function update_dates($dates, $timezone = 'gmt') {
        
        global $wpdb;

        $this->object_read = true;
        $dates = $this->validate_date_updates($dates, $timezone);
        $is_updated = false;

        foreach ($dates as $date_type => $datetime) {

            if ($datetime == $this->get_date($date_type)) {
                continue;
            }
            if (0 == $datetime) {
                if (!in_array($date_type, array('date_created', 'last_order_date_created', 'last_order_date_modified'))) {
                    $this->delete_date($date_type);
                }
                continue;
            }

            $utc_timestamp = ( 0 === $datetime ) ? null : Hforce_Date_Time_Utils::convert_date_to_time($datetime);

            switch ($date_type) {
                case 'date_created' :
                    $this->set_date_created($utc_timestamp);
                    $is_updated = true;
                    break;
                case 'date_modified' :
                    $this->set_date_modified($utc_timestamp);
                    $is_updated = true;
                    break;
                case 'date_paid' :
                    $this->set_date_paid($utc_timestamp);
                    $is_updated = true;
                    break;
                case 'date_completed' :
                    $this->set_date_completed($utc_timestamp);
                    $is_updated = true;
                    break;
                case 'last_order_date_created' :
                    $this->set_last_order_date('date_created', $utc_timestamp);
                    $is_updated = true;
                    break;
                case 'last_order_date_paid' :
                    $this->set_last_order_date('date_paid', $utc_timestamp);
                    $is_updated = true;
                    break;
                case 'last_order_date_completed' :
                    $this->set_last_order_date('date_completed', $utc_timestamp);
                    $is_updated = true;
                    break;
                default :
                    $this->set_date_prop($date_type, $utc_timestamp);
                    $is_updated = true;
                    break;
            }

            
            if ($is_updated && true === $this->object_read) {
                $this->save_dates();
                do_action('hf_subscription_date_updated', $this, $date_type, $datetime);
            }
        }
    }

    public function delete_date($date_type) {

        $date_type = hf_normalise_date_type_key($date_type, true);

        switch ($date_type) {
            case 'date_created' :
                $message = __('The start date of a subscription can not be deleted, only updated.', 'xa-woocommerce-subscription');
                break;
            case 'last_order_date_created' :
            case 'last_order_date_modified' :
                $message = sprintf(__('The %s date of a subscription can not be deleted. You must delete the order.', 'xa-woocommerce-subscription'), $date_type);
                break;
            default :
                $message = '';
                break;
        }

        if (!empty($message)) {
            throw new Exception($message);
        }

        $this->set_date_prop($date_type, 0);

        if (true === $this->object_read) {
            $this->save_dates();
            do_action('hf_subscription_date_deleted', $this, $date_type);
        }
    }

    public function can_date_be_updated($date_type) {

        switch ($date_type) {
            case 'date_created' :
                if ($this->has_status(array('auto-draft', 'pending'))) {
                    $can_date_be_updated = true;
                } else {
                    $can_date_be_updated = false;
                }
                break;

            case 'next_payment' :
            case 'end' :
                if (!$this->has_status(hforce_get_subscription_ended_statuses()) && ( $this->has_status('pending') || $this->payment_method_supports('subscription_date_changes') )) {
                    $can_date_be_updated = true;
                } else {
                    $can_date_be_updated = false;
                }
                break;
            case 'last_order_date_created' :
                $can_date_be_updated = true;
                break;
            default :
                $can_date_be_updated = false;
                break;
        }

        return apply_filters('hf_subscription_can_date_be_updated', $can_date_be_updated, $date_type, $this);
    }

    public function calculate_date($date_type) {

        switch ($date_type) {
            case 'next_payment' :
                $date = $this->calculate_next_payment_date();
                break;
            case 'end_of_prepaid_term' :

                $next_payment_time = $this->get_time('next_payment');
                $end_time = $this->get_time('end');

                if ($this->get_time('next_payment') >= current_time('timestamp', true)) {
                    $date = $this->get_date('next_payment');
                } elseif (0 == $next_payment_time || $end_time <= current_time('timestamp', true)) {
                    $date = current_time('mysql', true);
                } else {
                    $date = $this->get_date('end');
                }
                break;
            default :
                $date = 0;
                break;
        }

        return apply_filters('hf_subscription_calculated_' . $date_type . '_date', $date, $this);
    }

    protected function calculate_next_payment_date() {

        $next_payment_date = 0;

        $start_time = $this->get_time('date_created');
        $next_payment_time = $this->get_time('next_payment');
        $last_payment_time = max($this->get_time('last_order_date_created'), $this->get_time('last_order_date_paid'));
        $end_time = $this->get_time('end');



            if ($last_payment_time > $start_time && apply_filters('hf_calculate_next_payment_from_last_payment', true, $this)) {
                $from_timestamp = $last_payment_time;
            } elseif ($next_payment_time > $start_time) {
                $from_timestamp = $next_payment_time;
            } else {
                $from_timestamp = $start_time;
            }

            $next_payment_timestamp = Hforce_Date_Time_Utils::get_next_timestamp($this->get_billing_interval(), $this->get_billing_period(), $from_timestamp);

            $i = 1;
            while ($next_payment_timestamp < ( current_time('timestamp', true) + 2 * HOUR_IN_SECONDS ) && $i < 3000) {
                $next_payment_timestamp = Hforce_Date_Time_Utils::get_next_timestamp($this->get_billing_interval(), $this->get_billing_period(), $next_payment_timestamp);
                $i += 1;
            }
        

        if (0 != $end_time && ( $next_payment_timestamp + 23 * HOUR_IN_SECONDS ) > $end_time) {
            $next_payment_timestamp = 0;
        }

        if ($next_payment_timestamp > 0) {
            $next_payment_date = gmdate('Y-m-d H:i:s', $next_payment_timestamp);
        }

        return $next_payment_date;
    }

    public function save_dates() {
        
        if ($this->data_store && $this->get_id()) {
            $saved_dates = $this->data_store->save_dates($this);

            $this->data = array_replace_recursive($this->data, $saved_dates);
            $this->changes = array_diff_key($this->changes, $saved_dates);
        }
    }

    public function get_formatted_line_subtotal($item, $tax_display = '') {

        if (!$tax_display) {
            $tax_display = get_option('woocommerce_tax_display_cart');
        }

        if (!isset($item['line_subtotal']) || !isset($item['line_subtotal_tax'])) {
            return '';
        }

        if ($this->is_one_payment()) {

            $subtotal = parent::get_formatted_line_subtotal($item, $tax_display);
        } else {

            if ('excl' == $tax_display) {
                $line_subtotal = $this->get_line_subtotal($item);
            } else {
                $line_subtotal = $this->get_line_subtotal($item, true);
            }
            $subtotal = hforce_price_string($this->get_price_string_details($line_subtotal));
            $subtotal = apply_filters('woocommerce_order_formatted_line_subtotal', $subtotal, $item, $this);
        }

        return $subtotal;
    }


    public function get_subtotal_to_display($compound = false, $tax_display = '') {

        if (!$tax_display) {
            $tax_display = get_option('woocommerce_tax_display_cart');
        }

        $subtotal = 0;

        if (!$compound) {
            foreach ($this->get_items() as $item) {

                if (!isset($item['line_subtotal']) || !isset($item['line_subtotal_tax'])) {
                    return '';
                }

                $subtotal += $item['line_subtotal'];

                if ('incl' == $tax_display) {
                    $subtotal += $item['line_subtotal_tax'];
                }
            }

            $subtotal = wc_price($subtotal, array('currency' => $this->get_currency()));

            if ('excl' == $tax_display && $this->get_prices_include_tax()) {
                $subtotal .= ' <small>' . WC()->countries->ex_tax_or_vat() . '</small>';
            }
        } else {

            if ('incl' == $tax_display) {
                return '';
            }

            foreach ($this->get_items() as $item) {

                $subtotal += $item['line_subtotal'];
            }

            $subtotal += $this->get_total_shipping();

            foreach ($this->get_taxes() as $tax) {

                if (!empty($tax['compound'])) {
                    continue;
                }
                $subtotal = $subtotal + $tax['tax_amount'] + $tax['shipping_tax_amount'];
            }
            $subtotal = $subtotal - $this->get_total_discount();
            $subtotal = wc_price($subtotal, array('currency' => $this->get_currency()));
        }

        return apply_filters('woocommerce_order_subtotal_to_display', $subtotal, $compound, $this);
    }

    protected function get_price_string_details($amount = 0, $display_ex_tax_label = false) {

        $subscription_details = array(
            'currency' => $this->get_currency(),
            'recurring_amount' => $amount,
            'subscription_period' => $this->get_billing_period(),
            'subscription_interval' => $this->get_billing_interval(),
            'display_excluding_tax_label' => $display_ex_tax_label,
        );

        return apply_filters('hf_subscription_price_string_data', $subscription_details, $this);
    }

    public function cancel_order($note = '') {

        if ($this->has_status('active') && $this->calculate_date('end_of_prepaid_term') > current_time('mysql', true) && apply_filters('hf_subscription_use_pending_cancel', true)) {
            $this->update_status('pending-cancel', $note);
        } elseif (!$this->can_be_updated_to('cancelled')) {
            $this->add_order_note($note);
        } else {
            $this->update_status('cancelled', $note);
        }
    }

    public function is_editable() {

        if (!isset($this->is_editable)) {

            if ($this->has_status(array('pending', 'draft', 'auto-draft'))) {
                $this->is_editable = true;
            } elseif ($this->is_manual() || $this->payment_method_supports('subscription_amount_changes')) {
                $this->is_editable = true;
            } else {
                $this->is_editable = false;
            }
        }

        return apply_filters('wc_order_is_editable', $this->is_editable, $this);
    }

    
    public function payment_failed($new_status = 'on-hold') {

        $last_order = $this->get_last_order('all', 'any');

        if (false !== $last_order && false === $last_order->has_status('failed')) {
            remove_filter('woocommerce_order_status_changed', 'HForce_Woocommerce_Subscription_Admin::update_subscription_payment');
            $last_order->update_status('failed');
            add_filter('woocommerce_order_status_changed', 'HForce_Woocommerce_Subscription_Admin::update_subscription_payment', 10, 3);
        }

        $this->add_order_note(__('Payment failed.', 'xa-woocommerce-subscription'));

        if ('cancelled' == $new_status || apply_filters('hf_subscription_max_failed_payments_exceeded', false, $this)) {
            if ($this->can_be_updated_to('cancelled')) {
                $this->update_status('cancelled', __('Subscription Cancelled: maximum number of failed payments reached.', 'xa-woocommerce-subscription'));
            }
        } elseif ($this->can_be_updated_to($new_status)) {
            $this->update_status($new_status);
        }

        do_action('hf_subscription_payment_failed', $this, $new_status);

        if (false !== $last_order && hf_order_contains_renewal($last_order)) {
            do_action('hf_subscription_renewal_payment_failed', $this, $last_order);
        }
    }

    
    public function get_refunds() { return array(); }
    public function get_total_refunded() { return 0; }
    public function get_total_refunded_for_item($item_id, $item_type = 'line_item') { return 0; }
    public function get_tax_refunded_for_item($item_id, $tax_id, $item_type = 'line_item') { return 0; }
    public function get_parent() { return wc_get_order($this->get_parent_id()); }



    public function get_related_orders($fields = 'ids', $order_type = 'all') {
        
        $fields = ( 'ids' == $fields ) ? $fields : 'all';

        $related_orders = array();

        $related_post_ids = get_posts(array(
                        'posts_per_page' => -1,
                        'post_type' => 'shop_order',
                        'post_status' => 'any',
                        'fields' => 'ids',
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'meta_query' => array(
                            array(
                                'key' => '_subscription_renewal',
                                'compare' => '=',
                                'value' => $this->get_id(),
                                'type' => 'numeric',
                            ),
                        ),
                    ));

        if ('all' == $fields) {

            foreach ($related_post_ids as $post_id) {
                $related_orders[$post_id] = wc_get_order($post_id);
            }

            if (false != $this->get_parent_id() && 'renewal' !== $order_type) {
                $related_orders[$this->get_parent_id()] = $this->get_parent();
            }
        } else {

            if (false != $this->get_parent_id() && 'renewal' !== $order_type) {
                $related_orders[$this->get_parent_id()] = $this->get_parent_id();
            }

            foreach ($related_post_ids as $post_id) {
                $related_orders[$post_id] = $post_id;
            }
        }

        return apply_filters('hf_subscription_related_orders', $related_orders, $this, $fields, $order_type);
    }

    public function get_last_order($fields = 'ids', $order_types = array('parent', 'renewal')) {


        $fields = ( 'ids' == $fields ) ? $fields : 'all';
        $order_types = ( 'any' == $order_types ) ? array('parent', 'renewal') : (array) $order_types;
        $related_orders = array();

        foreach ($order_types as $order_type) {
            switch ($order_type) {
                case 'parent':
                    if (false != $this->get_parent_id()) {
                        $related_orders[] = $this->get_parent_id();
                    }
                    break;
                case 'renewal':
      
                        $related_post_ids = get_posts(array(
                        'posts_per_page' => -1,
                        'post_type' => 'shop_order',
                        'post_status' => 'any',
                        'fields' => 'ids',
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'meta_query' => array(
                            array(
                                'key' => '_subscription_renewal',
                                'compare' => '=',
                                'value' => $this->get_id(),
                                'type' => 'numeric',
                            ),
                        ),
                    ));
                    $related_orders = $related_post_ids;
                    break;
                
                default:
                    break;
            }
        }


        if (empty($related_orders)) {
            $last_order = false;
        } else {
            $last_order = max($related_orders);

            if ('all' == $fields) {
                if (false != $this->get_parent_id() && $last_order == $this->get_parent_id()) {
                    $last_order = $this->get_parent();
                     } else {
                    $last_order = wc_get_order($last_order);
                    
                }
            }
        }

        return apply_filters('hf_subscription_last_order', $last_order, $this);
    }

    public function get_payment_method_to_display() {

        if (false !== ( $payment_gateway = wc_get_payment_gateway_by_order($this) )) {
            $payment_method_to_display = $payment_gateway->get_title();
        }elseif ($this->is_manual()) {
            $payment_method_to_display = __('Manual Renewal', 'xa-woocommerce-subscription');
        } else {
            $payment_method_to_display = $this->get_payment_method_title();
        }

        return apply_filters('hf_subscription_payment_method_to_display', $payment_method_to_display, $this);
    }

    public function set_payment_method($payment_method = '', $payment_meta = array()) {

        if (empty($payment_method)) {

            $this->set_requires_manual_renewal(true);
            $this->set_prop('payment_method', '');
            $this->set_prop('payment_method_title', '');
        } else {

            $payment_method_id = is_a($payment_method, 'WC_Payment_Gateway') ? $payment_method->id : $payment_method;
            if (!empty($payment_meta)) {
                $this->set_payment_method_meta($payment_method_id, $payment_meta);
            }

            if ($this->get_payment_method() !== $payment_method_id) {

                if ($this->object_read) {

                    if (is_a($payment_method, 'WC_Payment_Gateway')) {
                        $payment_gateway = $payment_method;
                    } else {
                        $payment_gateways = WC()->payment_gateways->payment_gateways();
                        $payment_gateway = isset($payment_gateways[$payment_method_id]) ? $payment_gateways[$payment_method_id] : null;
                    }

                   if (is_null($payment_gateway) || false == $payment_gateway->supports('subscriptions')) {
                        $this->set_requires_manual_renewal(true);
                    } else {
                        $this->set_requires_manual_renewal(false);
                    }

                    $this->set_prop('payment_method_title', is_null($payment_gateway) ? '' : $payment_gateway->get_title() );
                }

                $this->set_prop('payment_method', $payment_method_id);
            }
        }
    }

    protected function set_payment_method_meta($payment_method_id, $payment_meta) {

        if (!is_array($payment_meta)) {
            throw new InvalidArgumentException(__('Payment method meta must be an array.', 'xa-woocommerce-subscription'));
        }

        do_action('hf_subscription_validate_payment_meta', $payment_method_id, $payment_meta, $this);
        do_action('hf_subscription_validate_payment_meta_' . $payment_method_id, $payment_meta, $this);

        foreach ($payment_meta as $meta_table => $meta) {
            foreach ($meta as $meta_key => $meta_data) {
                if (isset($meta_data['value'])) {
                    switch ($meta_table) {
                        case 'user_meta':
                        case 'usermeta':
                            update_user_meta($this->get_user_id(), $meta_key, $meta_data['value']);
                            break;
                        case 'post_meta':
                        case 'postmeta':
                            $this->update_meta_data($meta_key, $meta_data['value']);
                            break;
                        case 'options':
                            update_option($meta_key, $meta_data['value']);
                            break;
                        default:
                            do_action('hf_save_other_payment_meta', $this, $meta_table, $meta_key, $meta_data['value']);
                    }
                }
            }
        }
    }

    public function get_view_order_url() {
        $view_subscription_url = wc_get_endpoint_url('view-subscription', $this->get_id(), wc_get_page_permalink('myaccount'));
        return apply_filters('hf_get_view_subscription_url', $view_subscription_url, $this->get_id());
    }

    
    public function add_product($product, $qty = 1, $args = array()) {
        
        $item_id = parent::add_product($product, $qty, $args);
        if ($item_id && $product->backorders_require_notification() && $product->is_on_backorder($qty)) {
            wc_delete_order_item_meta($item_id, apply_filters('woocommerce_backordered_item_meta_name', __('Backordered', 'xa-woocommerce-subscription')));
        }

        return $item_id;
    }
    
    public function is_download_permitted() {
        
        $sending_email = did_action('woocommerce_email_before_order_table') > did_action('woocommerce_email_after_order_table');
        $is_download_permitted = $this->has_status('active') || $this->has_status('pending-cancel');

        if ($sending_email && !$is_download_permitted) {
            $is_download_permitted = true;
        }

        return apply_filters('woocommerce_order_is_download_permitted', $is_download_permitted, $this);
    }

    public function has_product($product_id) {

        $has_product = false;
        foreach ($this->get_items() as $line_item) {
            if ($line_item['product_id'] == $product_id || $line_item['variation_id'] == $product_id) {
                $has_product = true;
                break;
            }
        }
        return $has_product;
    }

    public function is_one_payment() {

        $is_one_payment = false;

        if (0 != ( $end_time = $this->get_time('end') )) {

            $from_timestamp = $this->get_time('date_created');
            $next_payment_timestamp = Hforce_Date_Time_Utils::get_next_timestamp($this->get_billing_interval(), $this->get_billing_period(), $from_timestamp);

            if (( $next_payment_timestamp + DAY_IN_SECONDS - 1 ) > $end_time) {
                $is_one_payment = true;
            }
        }

        return apply_filters('hf_subscription_is_one_payment', $is_one_payment, $this);
    }

    public function get_item_downloads($item) {

        $files = array();

        $sending_email = ( did_action('woocommerce_email_before_order_table') > did_action('woocommerce_email_after_order_table') ) ? true : false;
        if ($this->has_status(apply_filters('hf_subscription_item_download_statuses', array('active', 'pending-cancel'))) || $sending_email) {
            $files = parent::get_item_downloads($item);
        }

        return apply_filters('woocommerce_get_item_downloads', $files, $item, $this);
    }

    public function validate_date_updates($dates, $timezone = 'gmt') {

        if (!is_array($dates)) {
            throw new InvalidArgumentException(__('Invalid format. First parameter needs to be an array.', 'xa-woocommerce-subscription'));
        }

        if (empty($dates)) {
            throw new InvalidArgumentException(__('Invalid data. First parameter was empty when passed to update_dates().', 'xa-woocommerce-subscription'));
        }

        $passed_date_keys = array_map('hf_normalise_date_type_key', array_keys($dates));
        $extra_keys = array_diff($passed_date_keys, $this->get_valid_date_types());

        if (!empty($extra_keys)) {
            throw new InvalidArgumentException(__('Invalid data. First parameter has a date that is not in the registered date types.', 'xa-woocommerce-subscription'));
        }

        $dates = array_combine($passed_date_keys, array_values($dates));
        $timestamps = $delete_date_types = array();
        foreach ($this->get_valid_date_types() as $date_type) {

            if (in_array($date_type, array('last_payment', 'start'))) {
                continue;
            }

            if (false === $this->object_read && ( 0 === strpos($date_type, 'last_order_date_') || in_array($date_type, array('date_paid', 'date_completed')) )) {
                continue;
            }

            if (isset($dates[$date_type])) {
                $datetime = $dates[$date_type];

                if (!empty($datetime) && false === Hforce_Date_Time_Utils::is_mysql_datetime_format($datetime)) {
                    throw new InvalidArgumentException(sprintf(__('Invalid %s date. The date must be of the format: "Y-m-d H:i:s".', 'xa-woocommerce-subscription'), $date_type));
                }


                if (empty($datetime)) {
                    $timestamps[$date_type] = 0;
                } else {

                    if ('gmt' !== strtolower($timezone)) {
                        $datetime = get_gmt_from_date($datetime);
                    }
                    $timestamps[$date_type] = Hforce_Date_Time_Utils::convert_date_to_time($datetime);
                }
            } else {
                $timestamps[$date_type] = $this->get_time($date_type);
            }

            if (0 == $timestamps[$date_type]) {
                if ('last_order_date_created' != $date_type && 'date_created' != $date_type) {
                    $delete_date_types[$date_type] = 0;
                }
                unset($timestamps[$date_type]);
            }
        }

        $messages = array();

        foreach ($timestamps as $date_type => $timestamp) {
            switch ($date_type) {
                case 'end' :
                    if (array_key_exists('cancelled', $timestamps) && $timestamp < $timestamps['cancelled']) {
                        $messages[] = sprintf(__('The %s date must occur after the cancellation date.', 'xa-woocommerce-subscription'), $date_type);
                    }

                case 'cancelled' :
                    if (array_key_exists('last_order_date_created', $timestamps) && $timestamp < $timestamps['last_order_date_created']) {
                        $messages[] = sprintf(__('The %s date must occur after the last payment date.', 'xa-woocommerce-subscription'), $date_type);
                    }
                    if (array_key_exists('next_payment', $timestamps) && $timestamp <= $timestamps['next_payment']) {
                        $messages[] = sprintf(__('The %s date must occur after the next payment date.', 'xa-woocommerce-subscription'), $date_type);
                    }

            }

            $dates[$date_type] = gmdate('Y-m-d H:i:s', $timestamp);
        }

        if ($this->object_read && !empty($messages)) {
            throw new Exception(implode(' ', $messages));
        }

        return array_merge($dates, $delete_date_types);
    }


    protected function get_valid_date_types() {

        if (empty($this->valid_date_types)) {
            $this->valid_date_types = apply_filters('hf_subscription_valid_date_types', array_merge(
                            array_keys(hforce_get_subscription_available_date_types()), array(
                            'date_created',
                            'date_modified',
                            'date_paid',
                            'date_completed',
                            'last_order_date_created',
                            'last_order_date_paid',
                            'last_order_date_completed',
                            'payment_retry',
                            )
                    ), $this);
        }

        return $this->valid_date_types;
    }

}