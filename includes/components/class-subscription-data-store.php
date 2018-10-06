<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// subscription Data Store: Stored in Custom post Type - Extends WC_Order_Data_Store_CPT to make sure subscription related meta data is read/updated.

class HForce_Subscription_Data_Store extends WC_Order_Data_Store_CPT implements WC_Object_Data_Store_Interface, WC_Order_Data_Store_Interface {


    
	protected $subscription_internal_meta_keys = array(
		'_schedule_next_payment',
		'_schedule_cancelled',
		'_schedule_end',
		'_schedule_payment_retry',
	);

	protected $subscription_meta_keys_to_props = array(
		'_billing_period'           => 'billing_period',
		'_billing_interval'         => 'billing_interval',
		'_suspension_count'         => 'suspension_count',
		'_requires_manual_renewal'  => 'requires_manual_renewal',
		'_schedule_next_payment'    => 'schedule_next_payment',
		'_schedule_cancelled'       => 'schedule_cancelled',
		'_schedule_end'             => 'schedule_end',
		'_schedule_payment_retry'   => 'schedule_payment_retry',

	);

	public function __construct() {
		$this->internal_meta_keys = array_merge( $this->internal_meta_keys, $this->subscription_internal_meta_keys );
	}

        /**
	 * Method to create a new subscription in the database.
	 *
	 * @param WC_Order $subscription Order object.
	 */
	public function create( &$subscription ) {
		parent::create( $subscription );
		do_action( 'woocommerce_new_subscription', $subscription->get_id() );
	}

	// read subscription data.

	protected function read_order_data( &$subscription, $post_object ) {

		parent::read_order_data( $subscription, $post_object );

		$props_to_set = $dates_to_set = array();

		foreach ( $this->subscription_meta_keys_to_props as $meta_key => $prop_key ) {
			if ( 0 === strpos( $prop_key, 'schedule' ) || in_array( $meta_key, $this->subscription_internal_meta_keys )  ) {

				$meta_value = get_post_meta( $subscription->get_id(), $meta_key, true );

				if ( 0 === strpos( $prop_key, 'schedule' ) ) {
					$date_type = str_replace( 'schedule_', '', $prop_key );
					$dates_to_set[ $date_type ] = ( false == $meta_value ) ? 0 : $meta_value;
				} else {
					$props_to_set[ $prop_key ] = $meta_value;
				}
			}
		}

		$subscription->update_dates( $dates_to_set );
		$subscription->set_props( $props_to_set );
	}

	public function update( &$subscription ) {
		parent::update( $subscription );
		do_action( 'woocommerce_update_subscription', $subscription->get_id() );
	}

	protected function update_post_meta( &$subscription ) {

		$updated_props = array();

		foreach ( $this->get_props_to_update( $subscription, $this->subscription_meta_keys_to_props ) as $meta_key => $prop ) {
			$meta_value = ( 'schedule_' == substr( $prop, 0, 9 ) ) ? $subscription->get_date( $prop ) : $subscription->{"get_$prop"}( 'edit' );

			if ( 'requires_manual_renewal' === $prop ) {
				$meta_value = $meta_value ? 'true' : 'false';
			}

			update_post_meta( $subscription->get_id(), $meta_key, $meta_value );
			$updated_props[] = $prop;
		}

		do_action( 'hf_subscription_object_updated_props', $subscription, $updated_props );

		parent::update_post_meta( $subscription );
	}

	public function get_total_refunded( $subscription ) {

		$total = 0;

		foreach ( $subscription->get_related_orders( 'all' ) as $order ) {
			$total += parent::get_total_refunded( $order );
		}

		return $total;
	}

	public function get_total_tax_refunded( $subscription ) {

		$total = 0;

		foreach ( $subscription->get_related_orders() as $order ) {
			$total += parent::get_total_tax_refunded( $order );
		}

		return abs( $total );
	}

	public function get_total_shipping_refunded( $subscription ) {

		$total = 0;

		foreach ( $subscription->get_related_orders( 'all' ) as $order ) {
			$total += parent::get_total_shipping_refunded( $order );
		}

		return abs( $total );
	}

	public function save_dates( $subscription ) {
            
		$saved_dates    = array();
		$changes        = $subscription->get_changes();
		$date_meta_keys = array(
			'_schedule_next_payment',
			'_schedule_cancelled',
			'_schedule_end',
			'_schedule_payment_retry',
		);

		$date_meta_keys_to_props = array_intersect_key( $this->subscription_meta_keys_to_props, array_flip( $date_meta_keys ) );

		foreach ( $this->get_props_to_update( $subscription, $date_meta_keys_to_props ) as $meta_key => $prop ) {
			update_post_meta( $subscription->get_id(), $meta_key, $subscription->get_date( $prop ) );
			$saved_dates[ $prop ] = Hforce_Date_Time_Utils::hf_get_datetime_from( $subscription->get_time( $prop ) );
		}

		$post_data = array();

		if ( isset( $changes['date_created'] ) ) {
			$post_data['post_date']      = gmdate( 'Y-m-d H:i:s', $subscription->get_date_created( 'edit' )->getOffsetTimestamp() );
			$post_data['post_date_gmt']  = gmdate( 'Y-m-d H:i:s', $subscription->get_date_created( 'edit' )->getTimestamp() );
			$saved_dates['date_created'] = $subscription->get_date_created();
		}

		if ( isset( $changes['date_modified'] ) ) {
			$post_data['post_modified']     = gmdate( 'Y-m-d H:i:s', $subscription->get_date_modified( 'edit' )->getOffsetTimestamp() );
			$post_data['post_modified_gmt'] = gmdate( 'Y-m-d H:i:s', $subscription->get_date_modified( 'edit' )->getTimestamp() );
			$saved_dates['date_modified']   = $subscription->get_date_modified();
		}

		if ( ! empty( $post_data ) ) {
			$post_data['ID'] = $subscription->get_id();
			wp_update_post( $post_data );
		}

		return $saved_dates;
	}
        
        
        public function get_order_count( $status ) {
		global $wpdb;
		return absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( * ) FROM {$wpdb->posts} WHERE post_type = 'hf_shop_subscription' AND post_status = %s", $status ) ) );
	}

	public function get_orders( $args = array() ) {

		$parent_args = $args = wp_parse_args( $args, array(
			'type'   => 'hf_shop_subscription',
			'return' => 'objects',
		) );

		$parent_args['return'] = 'ids';

		$subscriptions = parent::get_orders( $parent_args );

		if ( $args['paginate'] ) {

			if ( 'objects' === $args['return'] ) {
				$return = array_map( 'hforce_get_subscription', $subscriptions->orders );
			} else {
				$return = $subscriptions->orders;
			}

			return (object) array(
				'orders'        => $return,
				'total'         => $subscriptions->total,
				'max_num_pages' => $subscriptions->max_num_pages,
			);

		} else {

			if ( 'objects' === $args['return'] ) {
				$return = array_map( 'hforce_get_subscription', $subscriptions );
			} else {
				$return = $subscriptions;
			}

			return $return;
		}
	}
        
}