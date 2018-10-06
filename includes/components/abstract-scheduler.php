<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

abstract class HForce_Scheduler {

	
	protected $date_types_to_schedule;

	public function __construct() {
            
		add_action( 'init', array( $this, 'set_date_types_to_schedule' ) );
		add_action( 'hf_subscription_date_updated', array( &$this, 'update_date' ), 10, 3 );
		add_action( 'hf_subscription_date_deleted', array( &$this, 'delete_date' ), 10, 2 );
		add_action( 'hf_subscription_status_updated', array( &$this, 'update_status' ), 10, 3 );
	}

	public function set_date_types_to_schedule() {
		$this->date_types_to_schedule = apply_filters( 'hf_subscription_date_types_to_schedule', array_keys( hforce_get_subscription_available_date_types() ) );

		if ( isset( $this->date_types_to_schedule['start'] ) ) {
			unset( $this->date_types_to_schedule['start'] );
		}

		if ( isset( $this->date_types_to_schedule['last_payment'] ) ) {
			unset( $this->date_types_to_schedule['last_payment'] );
		}
	}

	protected function get_date_types_to_schedule() {
		return $this->date_types_to_schedule;
	}

	abstract public function update_date( $subscription, $date_type, $datetime );

	abstract public function delete_date( $subscription, $date_type );

	abstract public function update_status( $subscription, $new_status, $old_status );
}
