<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Scheduler for subscription events that uses the Task Scheduler

class HForce_Action_Scheduler extends HForce_Scheduler {

    
	protected $action_hooks = array(
		'woocommerce_scheduled_subscription_payment'       => 'next_payment',
		'woocommerce_scheduled_subscription_payment_retry' => 'payment_retry',
		'woocommerce_scheduled_subscription_expiration'    => 'end',
	);

	public function update_date( $subscription, $date_type, $datetime ) {
            
            
		if ( in_array( $date_type, $this->get_date_types_to_schedule() ) ) {

			$action_hook = $this->get_scheduled_action_hook( $subscription, $date_type );

			if ( ! empty( $action_hook ) ) {

				$action_args    = $this->get_action_args( $date_type, $subscription );
				$timestamp      = Hforce_Date_Time_Utils::convert_date_to_time( $datetime );
				$next_scheduled = wc_next_scheduled_action( $action_hook, $action_args );

				if ( $next_scheduled !== $timestamp ) {
					$this->unschedule_actions( $action_hook, $action_args );
					if ( $timestamp > current_time( 'timestamp', true ) && ( 'payment_retry' == $date_type || 'active' == $subscription->get_status() ) ) {
						wc_schedule_single_action( $timestamp, $action_hook, $action_args );
					}
				}
			}
		}
	}

	public function delete_date( $subscription, $date_type ) {
		$this->update_date( $subscription, $date_type, 0 );
	}

	public function update_status( $subscription, $new_status, $old_status ) {

		switch ( $new_status ) {
			case 'active' :

				foreach ( $this->action_hooks as $action_hook => $date_type ) {

					$action_args    = $this->get_action_args( $date_type, $subscription );
					$next_scheduled = wc_next_scheduled_action( $action_hook, $action_args );
					$event_time     = $subscription->get_time( $date_type );

					if ( false !== $next_scheduled && $next_scheduled != $event_time ) {
						$this->unschedule_actions( $action_hook, $action_args );
					}

					if ( 0 != $event_time && $event_time > current_time( 'timestamp', true ) && $next_scheduled != $event_time ) {
						wc_schedule_single_action( $event_time, $action_hook, $action_args );
					}
				}
				break;
			case 'pending-cancel' :

				foreach ( $this->action_hooks as $action_hook => $date_type ) {
					$this->unschedule_actions( $action_hook, $this->get_action_args( $date_type, $subscription ) );
				}

				$end_time       = $subscription->get_time( 'end' );
				$action_args    = $this->get_action_args( 'end', $subscription );
				$next_scheduled = wc_next_scheduled_action( 'woocommerce_scheduled_subscription_end_of_prepaid_term', $action_args );

				if ( false !== $next_scheduled && $next_scheduled != $end_time ) {
					$this->unschedule_actions( 'woocommerce_scheduled_subscription_end_of_prepaid_term', $action_args );
				}

				if ( $end_time > current_time( 'timestamp', true ) && $next_scheduled != $end_time ) {
					wc_schedule_single_action( $end_time, 'woocommerce_scheduled_subscription_end_of_prepaid_term', $action_args );
				}
				break;
			case 'on-hold' :
			case 'cancelled' :
			case 'expired' :
			case 'trash' :
				foreach ( $this->action_hooks as $action_hook => $date_type ) {
					$this->unschedule_actions( $action_hook, $this->get_action_args( $date_type, $subscription ) );
				}
				$this->unschedule_actions( 'woocommerce_scheduled_subscription_end_of_prepaid_term', $this->get_action_args( 'end', $subscription ) );
				break;
		}
	}

	protected function get_scheduled_action_hook( $subscription, $date_type ) {

		$hook = '';

		switch ( $date_type ) {
			case 'next_payment' :
				$hook = 'woocommerce_scheduled_subscription_payment';
				break;
			case 'payment_retry' :
				$hook = 'woocommerce_scheduled_subscription_payment_retry';
				break;
			case 'end' :
				if ( $subscription->has_status( 'cancelled' ) ) {
					$hook = 'woocommerce_scheduled_subscription_end_of_prepaid_term';
				} elseif ( $subscription->has_status( 'active' ) ) {
					$hook = 'woocommerce_scheduled_subscription_expiration';
				}
				break;
		}

		return apply_filters( 'hf_subscription_scheduled_action_hook', $hook, $date_type );
	}

	protected function get_action_args( $date_type, $subscription ) {

		if ( 'payment_retry' == $date_type ) {
			$last_order_id = $subscription->get_last_order( 'ids', 'renewal' );
			$action_args   = array( 'order_id' => $last_order_id );

		} else {
			$action_args = array( 'subscription_id' => $subscription->get_id() );
		}

		return apply_filters( 'hf_subscription_scheduled_action_args', $action_args, $date_type, $subscription );
	}

	protected function unschedule_actions( $action_hook, $action_args ) {
		do {
			wc_unschedule_action( $action_hook, $action_args );
			$next_scheduled = wc_next_scheduled_action( $action_hook, $action_args );
		} while ( false !== $next_scheduled );
	}
}