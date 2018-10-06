<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class HF_Subscription_PayPal_Standard_IPN_Handler extends WC_Gateway_Paypal_IPN_Handler {

	
	protected $transaction_types = array(
		'subscr_signup',
		'subscr_payment',
		'subscr_cancel',
		'subscr_eot',
		'subscr_failed',
		'subscr_modify',
		'recurring_payment_skipped',
		'recurring_payment_suspended',
		'recurring_payment_suspended_due_to_max_failed_payment',
	);

	public function __construct( $sandbox = false, $receiver_email = '' ) {
            
		$this->receiver_email = $receiver_email;
		$this->sandbox        = $sandbox;
	}

	public function valid_response( $transaction_details ) {
            
		global $wpdb;
		$transaction_details = stripslashes_deep( $transaction_details );
		if ( ! $this->validate_transaction_type( $transaction_details['txn_type'] ) ) {
			return;
		}
		$transaction_details['txn_type'] = strtolower( $transaction_details['txn_type'] );
		$this->process_ipn_request( $transaction_details );

	}

	protected function process_ipn_request( $transaction_details ) {

		$subscription_id_and_key = self::get_order_id_and_key( $transaction_details, 'hf_shop_subscription' );
		$subscription            = hforce_get_subscription( $subscription_id_and_key['order_id'] );
		$subscription_key        = $subscription_id_and_key['order_key'];

		
		if ( ! is_callable( array( $subscription, 'get_id' ) ) ) {
			$subscription = hforce_get_subscription( wc_get_order_id_by_order_key( $subscription_key ) );
		}

		if ( 'recurring_payment_suspended_due_to_max_failed_payment' == $transaction_details['txn_type'] && empty( $subscription ) ) {
			WC_Gateway_Paypal::log( 'Returning as "recurring_payment_suspended_due_to_max_failed_payment" transaction is for a subscription created with Express Checkout' );
			return;
		}

		if ( empty( $subscription ) ) {

			
			if ( in_array( $transaction_details['txn_type'], array( 'subscr_cancel', 'subscr_eot' ) ) ) {

				$subscription_id_and_key = self::get_order_id_and_key( $transaction_details, 'hf_shop_subscription', '_old_paypal_subscriber_id' );

				if ( ! empty( $subscription_id_and_key['order_id'] ) ) {
					WC_Gateway_Paypal::log( 'IPN subscription cancellation request ignored - new PayPal Profile ID linked to this subscription, for subscription ' . $subscription_id_and_key['order_id'] );
					return;
				}
			}

			
			if ( 'recurring_payment_suspended' === $transaction_details['txn_type'] ) {
				
				$subscription_id_and_key = self::get_order_id_and_key( $transaction_details, 'hf_shop_subscription', '_switched_paypal_subscription_id' );

				if ( ! empty( $subscription_id_and_key['order_id'] ) ) {
					WC_Gateway_Paypal::log( 'IPN subscription suspension request ignored - subscription payment gateway changed via switch' . $subscription_id_and_key['order_id'] );
					return;
				}
			}

			if ( empty( $transaction_details['custom'] ) || ! $this->is_woocommerce_payload( $transaction_details['custom'] ) ) {
				WC_Gateway_Paypal::log( 'IPN request ignored - payload is not in a WooCommerce recognizable format' );
				return;
			}
		}

		if ( empty( $subscription ) ) {
			$message = 'Subscription IPN Error: Could not find matching Subscription.';
			WC_Gateway_Paypal::log( $message );
			throw new Exception( $message );
		}

		if ( $subscription->get_order_key() != $subscription_key ) {
			WC_Gateway_Paypal::log( 'Subscription IPN Error: Subscription Key does not match invoice.' );
			exit;
		}

		if ( isset( $transaction_details['txn_id'] ) ) {

			$handled_transactions = get_post_meta( $subscription->get_id(), '_paypal_ipn_tracking_ids', true );

			if ( empty( $handled_transactions ) ) {
				$handled_transactions = array();
			}

			$ipn_transaction_id = $transaction_details['txn_id'];

			if ( isset( $transaction_details['txn_type'] ) ) {
				$ipn_transaction_id .= '_' . $transaction_details['txn_type'];
			}

			if ( isset( $transaction_details['payment_status'] ) ) {
				$ipn_transaction_id .= '_' . $transaction_details['payment_status'];
			}

			if ( isset( $transaction_details['ipn_track_id'] ) ) {
				$ipn_transaction_id .= '_' . $transaction_details['ipn_track_id'];
			}

			if ( in_array( $ipn_transaction_id, $handled_transactions ) ) {
				WC_Gateway_Paypal::log( 'Subscription IPN Error: transaction ' . $ipn_transaction_id . ' has already been correctly handled.' );
				exit;
			}

			
			$ipn_lock_transient_name = 'hforce_pp_' . md5( $ipn_transaction_id ); 

			if ( 'in-progress' == get_transient( $ipn_lock_transient_name ) && 'recurring_payment_suspended_due_to_max_failed_payment' !== $transaction_details['txn_type'] ) {

				WC_Gateway_Paypal::log( 'Subscription IPN Error: an older IPN request with ID ' . $ipn_transaction_id . ' is still in progress.' );

				status_header( 503 );
				exit;
			}

			set_transient( $ipn_lock_transient_name, 'in-progress', apply_filters( 'woocommerce_subscriptions_paypal_ipn_request_lock_time', 4 * DAY_IN_SECONDS ) );
		}

		$is_renewal_sign_up_after_failure = false;

		if ( in_array( $transaction_details['txn_type'], array( 'subscr_signup', 'subscr_payment' ) ) && false !== strpos( $transaction_details['invoice'], '-hffrp-' ) ) {

			$renewal_order = wc_get_order( substr( $transaction_details['invoice'], strrpos( $transaction_details['invoice'], '-' ) + 1 ) );

			if ( hf_get_objects_property( $renewal_order, 'id' ) != get_post_meta( $subscription->get_id(), '_paypal_failed_sign_up_recorded', true ) ) {
				$is_renewal_sign_up_after_failure = true;
			}
		}


		$is_payment_change = false;

		if ( 'paypal' != $subscription->get_payment_method() ) {

		
			if ( 'recurring_payment_suspended' == $transaction_details['txn_type'] ) {

				WC_Gateway_Paypal::log( '"recurring_payment_suspended" IPN ignored: recurring payment method is not "PayPal". Returning to allow another extension to process the IPN, like PayPal Digital Goods.' );
				return;

			} elseif ( false === $is_renewal_sign_up_after_failure && false === $is_payment_change ) {

				WC_Gateway_Paypal::log( 'IPN ignored, recurring payment method has changed.' );
				exit;

			}
		}

		if ( $is_renewal_sign_up_after_failure || $is_payment_change ) {

			$existing_profile_id = hforce_get_paypal_billing_agreement_id( $subscription );

			if ( empty( $existing_profile_id ) || $existing_profile_id !== $transaction_details['subscr_id'] ) {
				update_post_meta( $subscription->get_id(), '_old_paypal_subscriber_id', $existing_profile_id );
				update_post_meta( $subscription->get_id(), '_old_payment_method', $subscription->get_payment_method() );
			}
		}

		if ( isset( $transaction_details['subscr_id'] ) && ! in_array( $transaction_details['txn_type'], array( 'subscr_cancel', 'subscr_eot' ) ) ) {
			hforce_set_paypal_id( $subscription, $transaction_details['subscr_id'] );

			if ( hforce_is_paypal_profile_type( $transaction_details['subscr_id'], 'out_of_date_id' ) && 'disabled' != get_option( 'hforce_paypal_invalid_profile_id' ) ) {
				update_option( 'hforce_paypal_invalid_profile_id', 'yes' );
			}
		}

		$is_first_payment = ( $subscription->get_completed_payment_count() < 1 ) ? true : false;



		switch ( $transaction_details['txn_type'] ) {
			case 'subscr_signup':

				$this->save_paypal_meta_data( $subscription, $transaction_details );
				$this->save_paypal_meta_data( $subscription->get_parent(), $transaction_details );

				if ( ! $is_payment_change && ! $is_renewal_sign_up_after_failure && 0 == $subscription->get_parent()->get_total() ) {
					$subscription->get_parent()->payment_complete();
					update_post_meta( $subscription->get_id(), '_paypal_first_ipn_ignored_for_pdt', 'true' );
				}
                                
                                $this->add_order_note( __( 'IPN subscription sign up completed.', 'woocommerce-subscriptions' ), $subscription, $transaction_details );
                                WC_Gateway_Paypal::log( 'IPN subscription sign up completed for subscription ' . $subscription->get_id() );
				
				break;

			case 'subscr_payment':

				if ( 0.01 == $transaction_details['mc_gross'] && 1 == $subscription->get_completed_payment_count() ) {
					WC_Gateway_Paypal::log( 'IPN ignored, treating IPN as secondary trial period.' );
					exit;
				}

				if ( ! $is_first_payment && ! $is_renewal_sign_up_after_failure ) {

					if ( $subscription->has_status( 'active' ) ) {
						
						$subscription->update_status( 'on-hold' );
						
					}

					$renewal_order = $this->get_renewal_order_by_transaction_id( $subscription, $transaction_details['txn_id'] );
					if ( is_null( $renewal_order ) ) {
						$renewal_order = hforce_create_renewal_order( $subscription );
					}

					$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
					$renewal_order->set_payment_method( $available_gateways['paypal'] );
				}

				if ( 'completed' == strtolower( $transaction_details['payment_status'] ) ) {
					$this->save_paypal_meta_data( $subscription, $transaction_details );

					$this->add_order_note( __( 'IPN subscription payment completed.', 'woocommerce-subscriptions' ), $subscription, $transaction_details );

					WC_Gateway_Paypal::log( 'IPN subscription payment completed for subscription ' . $subscription->get_id() );

					if ( $is_first_payment ) {

						$subscription->get_parent()->payment_complete( $transaction_details['txn_id'] );
						$this->save_paypal_meta_data( $subscription->get_parent(), $transaction_details );
						update_post_meta( $subscription->get_id(), '_paypal_first_ipn_ignored_for_pdt', 'true' );

					} elseif ( $subscription->get_completed_payment_count() == 1 && '' !== HF_Subscription_Paypal::get_option( 'identity_token' ) && 'true' != get_post_meta( $subscription->get_id(), '_paypal_first_ipn_ignored_for_pdt', true ) && false === $is_renewal_sign_up_after_failure ) {

						WC_Gateway_Paypal::log( 'IPN subscription payment ignored for subscription ' . $subscription->get_id() . ' due to PDT previously handling the payment.' );
						update_post_meta( $subscription->get_id(), '_paypal_first_ipn_ignored_for_pdt', 'true' );

					} elseif ( ! $subscription->has_status( array( 'cancelled', 'expired', 'trash' ) ) ) {

						if ( true === $is_renewal_sign_up_after_failure && is_object( $renewal_order ) ) {

							update_post_meta( $subscription->get_id(), '_paypal_failed_sign_up_recorded', hf_get_objects_property( $renewal_order, 'id' ) );

							if ( 'paypal' == get_post_meta( $subscription->get_id(), '_old_payment_method', true ) ) {

								$profile_id = get_post_meta( $subscription->get_id(), '_old_paypal_subscriber_id', true );

								if ( $profile_id !== $transaction_details['subscr_id'] ) {
									self::cancel_subscription( $subscription, $profile_id );
								}

								$this->add_order_note( __( 'IPN subscription failing payment method changed.', 'woocommerce-subscriptions' ), $subscription, $transaction_details );
							}
						}

						try {

							$update_dates = array();

							if ( $subscription->get_time( 'trial_end' ) > gmdate( 'U' ) ) {
								$update_dates['trial_end'] = gmdate( 'Y-m-d H:i:s', gmdate( 'U' ) - 1 );
								WC_Gateway_Paypal::log( sprintf( 'IPN subscription payment for subscription %d: trial_end is in futute (date: %s) setting to %s.', $subscription->get_id(), $subscription->get_date( 'trial_end' ), $update_dates['trial_end'] ) );
							} else {
								WC_Gateway_Paypal::log( sprintf( 'IPN subscription payment for subscription %d: trial_end is in past (date: %s).', $subscription->get_id(), $subscription->get_date( 'trial_end' ) ) );
							}

							if ( $subscription->get_time( 'next_payment' ) > gmdate( 'U' ) ) {
								$update_dates['next_payment'] = gmdate( 'Y-m-d H:i:s', gmdate( 'U' ) - 1 );
								WC_Gateway_Paypal::log( sprintf( 'IPN subscription payment for subscription %d: next_payment is in future (date: %s) setting to %s.', $subscription->get_id(), $subscription->get_date( 'next_payment' ), $update_dates['next_payment'] ) );
							} else {
								WC_Gateway_Paypal::log( sprintf( 'IPN subscription payment for subscription %d: next_payment is in past (date: %s).', $subscription->get_id(), $subscription->get_date( 'next_payment' ) ) );
							}

							if ( ! empty( $update_dates ) ) {
								$subscription->update_dates( $update_dates );
							}
						} catch ( Exception $e ) {
							WC_Gateway_Paypal::log( sprintf( 'IPN subscription payment exception subscription %d: %s.', $subscription->get_id(), $e->getMessage() ) );
						}


						try {
							$renewal_order->payment_complete( $transaction_details['txn_id'] );
						} catch ( Exception $e ) {
							WC_Gateway_Paypal::log( sprintf( 'IPN subscription payment exception calling $renewal_order->payment_complete() for subscription %d: %s.', $subscription->get_id(), $e->getMessage() ) );
						}

						$this->add_order_note( __( 'IPN subscription payment completed.', 'woocommerce-subscriptions' ), $renewal_order, $transaction_details );


						hforce_set_paypal_id( $renewal_order, $transaction_details['subscr_id'] );
					}
				} elseif ( in_array( strtolower( $transaction_details['payment_status'] ), array( 'pending', 'failed' ) ) ) {

					$this->add_order_note( sprintf( _x( 'IPN subscription payment %s.', 'used in order note', 'woocommerce-subscriptions' ), $transaction_details['payment_status'] ), $subscription, $transaction_details );

					if ( ! $is_first_payment ) {

						hforce_set_objects_property( $renewal_order, 'transaction_id', $transaction_details['txn_id'] );

						if ( 'failed' == strtolower( $transaction_details['payment_status'] ) ) {
							$subscription->payment_failed();
							$this->add_order_note( sprintf( _x( 'IPN subscription payment %s.', 'used in order note', 'woocommerce-subscriptions' ), $transaction_details['payment_status'] ), $renewal_order, $transaction_details );
						} else {
							$renewal_order->update_status( 'on-hold' );
							$this->add_order_note( sprintf( _x( 'IPN subscription payment %s for reason: %s.', 'used in order note', 'woocommerce-subscriptions' ), $transaction_details['payment_status'], $transaction_details['pending_reason'] ), $renewal_order, $transaction_details );
						}
					}

					WC_Gateway_Paypal::log( sprintf( 'IPN subscription payment %s for subscription %d ', $transaction_details['payment_status'], $subscription->get_id() ) );
				} else {

					WC_Gateway_Paypal::log( 'IPN subscription payment notification received for subscription ' . $subscription->get_id()  . ' with status ' . $transaction_details['payment_status'] );

				}

				break;

			case 'recurring_payment_suspended':

				
				$ipn_profile_id = ( isset( $transaction_details['subscr_id'] ) ) ? $transaction_details['subscr_id'] : $transaction_details['recurring_payment_id'];

				if ( hforce_get_paypal_billing_agreement_id( $subscription ) != $ipn_profile_id ) {

					WC_Gateway_Paypal::log( sprintf( 'IPN "recurring_payment_suspended" ignored for subscription %d - PayPal profile ID has changed', $subscription->id ) );

				} else if ( $subscription->has_status( 'active' ) ) {


					$subscription->update_status( 'on-hold', __( 'IPN subscription suspended.', 'woocommerce-subscriptions' ) );

					WC_Gateway_Paypal::log( 'IPN subscription suspended for subscription ' . $subscription->get_id() );

				} else {

					WC_Gateway_Paypal::log( sprintf( 'IPN "recurring_payment_suspended" ignored for subscription %d. Subscription already %s.', $subscription->get_id(), $subscription->get_status() ) );

				}

				break;

			case 'subscr_cancel':
				if ( hforce_get_paypal_billing_agreement_id( $subscription ) != $transaction_details['subscr_id'] ) {

					WC_Gateway_Paypal::log( 'IPN subscription cancellation request ignored - new PayPal Profile ID linked to this subscription, for subscription ' . $subscription->get_id() );

				} else {

					$subscription->cancel_order( __( 'IPN subscription cancelled.', 'woocommerce-subscriptions' ) );

					WC_Gateway_Paypal::log( 'IPN subscription cancelled for subscription ' . $subscription->get_id() );

				}

				break;

			case 'subscr_eot': 
				WC_Gateway_Paypal::log( 'IPN EOT request ignored for subscription ' . $subscription->get_id() );
				break;

			case 'subscr_failed':
			case 'recurring_payment_suspended_due_to_max_failed_payment':

				$ipn_failure_note = __( 'IPN subscription payment failure.', 'woocommerce-subscriptions' );

				if ( ! $is_first_payment && ! $is_renewal_sign_up_after_failure && $subscription->has_status( 'active' ) ) {
					
					$renewal_order = hforce_create_renewal_order( $subscription );

					
					$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
					$renewal_order->set_payment_method( $available_gateways['paypal'] );
					$this->add_order_note( $ipn_failure_note, $renewal_order, $transaction_details );
				}

				WC_Gateway_Paypal::log( 'IPN subscription payment failure for subscription ' . $subscription->get_id() );

				$this->add_order_note( $ipn_failure_note, $subscription, $transaction_details );

				try {
					$subscription->payment_failed();
				} catch ( Exception $e ) {
					WC_Gateway_Paypal::log( sprintf( 'IPN subscription payment failure, unable to process payment failure. Exception: %s ', $e->getMessage() ) );
				}

				break;
		}

		if ( isset( $transaction_details['txn_id'] ) ) {
			$handled_transactions[] = $ipn_transaction_id;
			update_post_meta( $subscription->get_id(), '_paypal_ipn_tracking_ids', $handled_transactions );
		}

		
		if ( isset( $ipn_lock_transient_name ) ) {
			delete_transient( $ipn_lock_transient_name );
		}

		$log_message = 'IPN subscription request processed for ' . $subscription->get_id();

		if ( isset( $ipn_id ) && ! empty( $ipn_id ) ) {
			$log_message .= sprintf( ' (%s)', $ipn_id );
		}

		WC_Gateway_Paypal::log( $log_message );

		exit;
	}


	public function get_transaction_types() {
		return $this->transaction_types;
	}

	protected function is_woocommerce_payload( $payload ) {
		return is_numeric( $payload ) ||
			(bool) preg_match( '/(wc_)?order_[a-f0-9]{5,20}/', $payload );
	}

	
	public static function get_order_id_and_key( $args, $order_type = 'shop_order', $meta_key = '_paypal_subscription_id' ) {

		$order_id = $order_key = '';

		if ( isset( $args['subscr_id'] ) ) { 
			$subscription_id = $args['subscr_id'];
		} elseif ( isset( $args['recurring_payment_id'] ) ) { 
			$subscription_id = $args['recurring_payment_id'];
		} else {
			$subscription_id = '';
		}

		if ( ! empty( $subscription_id ) ) {

			$posts = get_posts( array(
				'numberposts'      => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'meta_key'         => $meta_key,
				'meta_value'       => $subscription_id,
				'post_type'        => $order_type,
				'post_status'      => 'any',
				'suppress_filters' => true,
			) );

			if ( ! empty( $posts ) ) {
				$order_id  = $posts[0]->ID;
				$order_key = get_post_meta( $order_id, '_order_key', true );
			}
		}

		
		if ( empty( $order_id ) && isset( $args['custom'] ) ) {

			$order_details = json_decode( $args['custom'] );

			if ( is_object( $order_details ) ) { 

				if ( 'shop_order' == $order_type ) {
					$order_id  = $order_details->order_id;
					$order_key = $order_details->order_key;
				} elseif ( isset( $order_details->subscription_id ) ) {
					
					$order_id  = $order_details->subscription_id;
					$order_key = $order_details->subscription_key;
				} else {
					
					$subscriptions = hforce_get_subscriptions_for_order( absint( $order_details->order_id ), array( 'order_type' => array( 'parent' ) ) );

					if ( ! empty( $subscriptions ) ) {
						$subscription = array_pop( $subscriptions );
						$order_id  = $subscription->get_id();
						$order_key = $subscription->get_order_key();
					}
				}
			} else { 
				WC_Gateway_Paypal::log( __( 'Invalid PayPal IPN Payload: unable to find matching subscription.', 'xa-woocommerce-subscriptions' ) );
			}
		}

		return array( 'order_id' => (int) $order_id, 'order_key' => $order_key );
	}

	protected static function cancel_subscription( $subscription, $old_paypal_subscriber_id ) {

		if ( hforce_is_paypal_profile_type( $old_paypal_subscriber_id, 'billing_agreement' ) ) {
			return;
		}

		$current_profile_id = hforce_get_paypal_billing_agreement_id( $subscription->get_id() );
                
		hforce_set_paypal_id( $subscription, $old_paypal_subscriber_id );

		hforce_set_paypal_id( $subscription, $current_profile_id );
	}

	protected function validate_transaction_type( $txn_type ) {
		if ( in_array( strtolower( $txn_type ), $this->get_transaction_types() ) ) {
			return true;
		} else {
			return false;
		}
	}

	protected function add_order_note( $note, $order, $transaction_details ) {
            
		$note = apply_filters( 'hforce_paypal_ipn_note', $note, $order, $transaction_details );
		if ( ! empty( $note ) ) {
			$order->add_order_note( $note );
		}
	}

	protected function get_renewal_order_by_transaction_id( $subscription, $transaction_id ) {

		$orders = $subscription->get_related_orders( 'all', 'renewal' );
		$renewal_order = null;

		foreach ( $orders as $order ) {
			if ( $order->get_transaction_id() == $transaction_id ) {
				$renewal_order = $order;
				break;
			}
		}

		return $renewal_order;
	}
}
