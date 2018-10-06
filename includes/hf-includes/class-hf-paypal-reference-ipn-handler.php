<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class HF_Subscription_PayPal_Reference_IPN_Handler extends HF_Subscription_PayPal_Standard_IPN_Handler {

    protected $transaction_types = array(
        'mp_signup',
        'mp_cancel',
        'merch_pmt',
    );

    public function __construct($sandbox = false, $receiver_email = '') {
        $this->receiver_email = $receiver_email;
        $this->sandbox = $sandbox;
    }

    public function valid_response($transaction_details) {

        if (!$this->validate_transaction_type($transaction_details['txn_type'])) {
            return;
        }

        switch ($transaction_details['txn_type']) {

            case 'mp_cancel':
                $this->cancel_subscriptions($transaction_details['mp_id']);
                break;

            case 'merch_pmt' :

                if (!empty($transaction_details['custom']) && ( $order = $this->get_paypal_order($transaction_details['custom']) )) {

                    $transaction_details['payment_status'] = strtolower($transaction_details['payment_status']);

                    if (isset($transaction_details['test_ipn']) && 1 == $transaction_details['test_ipn'] && 'pending' == $transaction_details['payment_status']) {
                        $transaction_details['payment_status'] = 'completed';
                    }

                    WC_Gateway_Paypal::log('Found order #' . hf_get_objects_property($order, 'id'));
                    WC_Gateway_Paypal::log('Payment status: ' . $transaction_details['payment_status']);

                    if (method_exists($this, 'payment_status_' . $transaction_details['payment_status'])) {
                        call_user_func(array($this, 'payment_status_' . $transaction_details['payment_status']), $order, $transaction_details);
                    } else {
                        WC_Gateway_Paypal::log('Unknown payment status: ' . $transaction_details['payment_status']);
                    }
                }
                break;

            case 'mp_signup' :
                break;
        }
        exit;
    }

    protected function cancel_subscriptions($billing_agreement_id) {

        $subscription_ids = get_posts(array(
            'posts_per_page' => -1,
            'post_type' => 'hf_shop_subscription',
            'post_status' => 'any',
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_paypal_subscription_id',
                    'compare' => '=',
                    'value' => $billing_agreement_id,
                ),
            ),
                ));

        if (empty($subscription_ids)) {
            return;
        }

        $note = esc_html__('Billing agreement cancelled at PayPal.', 'xa-woocommerce-subscriptions');

        foreach ($subscription_ids as $subscription_id) {

            $subscription = hforce_get_subscription($subscription_id);


            if (false == $subscription || $subscription->is_manual() || 'paypal' != $subscription->get_payment_method() || $subscription->has_status(hforce_get_subscription_ended_statuses())) {
                continue;
            }

            try {
                $subscription->cancel_order($note);
                WC_Gateway_Paypal::log(sprintf('Subscription %s Cancelled: %s', $subscription_id, $note));
            } catch (Exception $e) {
                WC_Gateway_Paypal::log(sprintf('Unable to cancel subscription %s: %s', $subscription_id, $e->getMessage()));
            }
        }
    }

}