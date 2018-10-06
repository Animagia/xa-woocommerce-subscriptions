<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once( 'subscription-price-functions.php' );
require_once( 'subscription-cart-functions.php' );
require_once( 'subscription-renewal-functions.php' );



function hforce_is_subscription_limited_for_user($product, $user_id = 0) {
    
    if (!is_object($product)) {
        $product = wc_get_product($product);
    }

    $limited = FALSE;
    if( ( 'active' == get_subscription_product_limitation($product) && hforce_user_has_subscription($user_id, $product->get_id(), 'on-hold') ) || ( 'no' !== get_subscription_product_limitation($product) && hforce_user_has_subscription($user_id, $product->get_id(), get_subscription_product_limitation($product)) )  ){
         $limited = TRUE;
    }
    
}
function get_subscription_product_limitation($product) {

    if (!is_object($product) || !is_a($product, 'WC_Product')) {
        $product = wc_get_product($product);
    }

    return apply_filters('hf_subscription_product_limitation', HForce_Subscriptions_Product::get_meta_data($product, 'subscription_limit', 'no', 'use_default_value'), $product);
}

function get_subscriptions_by_order( $order_id, $args = array() ) {

	if ( is_object( $order_id ) ) {
		$order_id = hforce_get_objects_property( $order_id, 'id' );
	}

	$args = wp_parse_args( $args, array(
			'order_id'               => $order_id,
			'subscriptions_per_page' => -1,
			'order_type'             => array( 'parent', ),
		)
	);
	if ( ! is_array( $args['order_type'] ) ) {
		$args['order_type'] = array( $args['order_type'] );
	}
	$subscriptions = array();
	$get_all       = ( in_array( 'any', $args['order_type'] ) ) ? true : false;
        
	if ( $order_id && in_array( 'parent', $args['order_type'] ) || $get_all ) {
		$subscriptions = hforce_get_subscriptions( $args );
	}
	if ( is_order_contains_resubscribe( $order_id ) && ( in_array( 'resubscribe', $args['order_type'] ) || $get_all ) ) {
		$subscriptions += get_subscriptions_resubscribe_orders( $order_id );
	}
	if ( is_order_contains_renewal( $order_id ) && ( in_array( 'renewal', $args['order_type'] ) || $get_all ) ) {
		$subscriptions += get_subscriptions_for_renewal_order( $order_id );
	}
	return $subscriptions;
}


function is_order_contains_subscription( $order, $order_type = array( 'parent', 'resubscribe', 'renewal', ) ) {

	if ( ! is_array( $order_type ) ) {
		$order_type = array( $order_type );
	}

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}

	$contains_subscription = false;
	$get_all               = ( in_array( 'any', $order_type ) ) ? true : false;

        
	if ( ( in_array( 'parent', $order_type ) || $get_all ) && count(get_subscriptions_by_order( hforce_get_objects_property( $order, 'id' ), array( 'order_type' => 'parent' ) ) ) > 0 ) {
		$contains_subscription = true;

	} elseif ( ( in_array( 'renewal', $order_type ) || $get_all ) && hf_order_contains_renewal( $order ) ) {
		$contains_subscription = true;

	} elseif ( ( in_array( 'resubscribe', $order_type ) || $get_all ) && is_order_contains_resubscribe( $order ) ) {
		$contains_subscription = true;

	}

	return $contains_subscription;
}

function get_subscriptions_for_renewal_order( $order ) {

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}

	$subscriptions = array();

	if ( is_a( $order, 'WC_Abstract_Order' ) ) {
		$subscription_ids = hforce_get_objects_property( $order, 'subscription_renewal', 'multiple' );

		foreach ( $subscription_ids as $subscription_id ) {
			if ( hforce_is_subscription( $subscription_id ) ) {
				$subscriptions[ $subscription_id ] = hforce_get_subscription( $subscription_id );
			}
		}
	}

	return apply_filters( 'hf_subscriptions_for_renewal_order', $subscriptions, $order );
}

function is_order_contains_renewal( $order ) {

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( hforce_is_order( $order ) && hforce_get_objects_property( $order, 'subscription_renewal' ) ) {
		$is_renewal = true;
	} else {
		$is_renewal = false;
	}

	return apply_filters( 'hf_subscriptions_is_renewal_order', $is_renewal, $order );
}

function is_order_contains_resubscribe( $order ) {

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}

	if ( hforce_get_objects_property( $order, 'subscription_resubscribe' ) ) {
		$is_resubscribe_order = true;
	} else {
		$is_resubscribe_order = false;
	}

	return apply_filters( 'hf_subscriptions_is_resubscribe_order', $is_resubscribe_order, $order );
}

function get_subscriptions_resubscribe_orders( $order ) {

	if ( ! is_a( $order, 'WC_Abstract_Order' ) ) {
		$order = wc_get_order( $order );
	}
	$subscriptions    = array();
	$subscription_ids = hforce_get_objects_property( $order, 'subscription_resubscribe', 'multiple' );
	foreach ( $subscription_ids as $subscription_id ) {
		if ( hforce_is_subscription( $subscription_id ) ) {
			$subscriptions[ $subscription_id ] = hforce_get_subscription( $subscription_id );
		}
	}
	return apply_filters( 'hf_subscriptions_for_resubscribe_order', $subscriptions, $order );
}


function hforce_json_encode($data) {
    if (function_exists('wp_json_encode')) {
        return wp_json_encode($data);
    }
    return json_encode($data);
}



function hf_update_users_role( $user_id, $new_role ) {

	$user = new WP_User( $user_id );

	if ( ! empty( $user->roles ) && in_array( 'administrator', $user->roles ) ) {
		return;
	}

	if ( ! apply_filters( 'hf_subscription_update_users_role', true, $user, $new_role ) ) {
		return;
	}

	$roles = hf_get_new_user_role_names( $new_role );

	$new_role = $roles['new'];
	$role_old = $roles['old'];

	if ( ! empty( $role_old ) ) {
		$user->remove_role( $role_old );
	}

	$user->add_role( $new_role );

	do_action( 'hf_subscription_updated_users_role', $new_role, $user, $role_old );
	return $user;
}

 
function hf_get_new_user_role_names( $new_role ) {
    
	$default_subscriber_role = 'subscriber';
	$default_cancelled_role = 'customer';
	$role_old = '';

	if ( 'default_subscriber_role' == $new_role ) {
		$role_old = $default_cancelled_role;
		$new_role = $default_subscriber_role;
	} elseif ( in_array( $new_role, array( 'default_inactive_role', 'default_cancelled_role' ) ) ) {
		$role_old = $default_subscriber_role;
		$new_role = $default_cancelled_role;
	}

	return array(
		'new' => $new_role,
		'old' => $role_old,
	);
}

if ( is_admin() ) {
	    
        function hf_add_admin_notice( $message, $notice_type = 'success' ) {

                $notices = get_transient( '_hf_admin_notices' );
                if ( false === $notices ) {
                        $notices = array();
                }
                $notices[ $notice_type ][] = $message;
                set_transient( '_hf_admin_notices', $notices, 60 * 60 );
        }

        function hf_display_admin_notices( $clear = true ) {

                $notices = get_transient( '_hf_admin_notices' );
                if ( false !== $notices && ! empty( $notices ) ) {
                        if ( ! empty( $notices['success'] ) ) {
                                array_walk( $notices['success'], 'esc_html' );
                                echo '<div id="moderated" class="updated"><p>' . wp_kses_post( implode( "</p>\n<p>", $notices['success'] ) ) . '</p></div>';
                        }

                        if ( ! empty( $notices['error'] ) ) {
                                array_walk( $notices['error'], 'esc_html' );
                                echo '<div id="moderated" class="error"><p>' . wp_kses_post( implode( "</p>\n<p>", $notices['error'] ) ) . '</p></div>';
                        }
                }

                if ( false !== $clear ) {
                        hf_clear_admin_notices();
                }
        }
        add_action( 'admin_notices', 'hf_display_admin_notices' );

        
        function hf_clear_admin_notices() {
                delete_transient( '_hf_admin_notices' );
        }    
    
}

function hforce_is_subscription( $subscription ) {

	if ( is_object( $subscription ) && is_a( $subscription, 'HForce_Subscription' ) ) {
		$is_subscription = true;
	} elseif ( is_numeric( $subscription ) && 'hf_shop_subscription' == get_post_type( $subscription ) ) {
		$is_subscription = true;
	} else {
		$is_subscription = false;
	}

	return apply_filters( 'hforce_is_subscription', $is_subscription, $subscription );
}

function hforce_get_subscription( $the_subscription ) {
    
	if ( is_object( $the_subscription ) && hforce_is_subscription( $the_subscription ) ) {
		$the_subscription = $the_subscription->get_id();
	}
        
	$subscription = WC()->order_factory->get_order( $the_subscription );
	if ( ! hforce_is_subscription( $subscription ) ) {
		$subscription = false;
	}
        
	return apply_filters( 'hforce_get_subscription', $subscription );
}

// front end
function available_user_actions_for_subscription( $subscription, $user_id ) {

	$actions = array();
	if ( user_can( $user_id, 'edit_hf_shop_subscription_status', $subscription->get_id() ) ) {
		$admin_with_suspension_disallowed =  false;
		$current_status = $subscription->get_status();
                
                $suspend_veno = TRUE;
                $pypal_msg = '';
                $subscription_payment_method = $subscription->get_payment_method();
                if (!empty($subscription_payment_method) && 'paypal' == $subscription_payment_method) {
                    $pypal_msg = __("  ( Please cancel the subscription from PayPal dashboard under pre-approved payments ) ", 'xa-woocommerce-subscription');
                    $suspend_veno = FALSE;
                }
                
                if ( $suspend_veno && $subscription->can_be_updated_to( 'on-hold' ) && hforce_can_user_put_subscription_on_hold( $subscription, $user_id ) && ! $admin_with_suspension_disallowed ) {
			$actions['suspend'] = array(
				'url'  => hforce_get_users_change_status_link( $subscription->get_id(), 'on-hold', $current_status ),
				'name' => __( 'Suspend', 'xa-woocommerce-subscription' ),
			);
		} elseif( $subscription->can_be_updated_to( 'active' ) && ! $subscription->needs_payment() ) {
			$actions['reactivate'] = array(
				'url'  => hforce_get_users_change_status_link( $subscription->get_id(), 'active', $current_status ),
				'name' => __( 'Reactivate', 'xa-woocommerce-subscription' ),
			);
		}
		if ( hforce_can_user_resubscribe_to( $subscription, $user_id ) ) {
			$actions['resubscribe'] = array(
				'url'  => hforce_get_users_resubscribe_link( $subscription ),
				'name' => __( 'Resubscribe', 'xa-woocommerce-subscription' ),
			);
		}
		$next_payment = $subscription->get_time( 'next_payment' );
		if ( $subscription->can_be_updated_to( 'cancelled' ) && ( ! $subscription->is_one_payment() && ( $subscription->has_status( 'on-hold' ) && empty( $next_payment ) ) || $next_payment > 0 ) ) {
			$actions['cancel'] = array(
				'url'  => hforce_get_users_change_status_link( $subscription->get_id(), 'cancelled', $current_status ),
				'name' => __( 'Cancel', 'xa-woocommerce-subscription' ).$pypal_msg,
			);
		}
	}

        //echo '<pre>';print_r($actions);exit;
	return apply_filters( 'hf_view_subscription_actions', $actions, $subscription );
}


function hf_create_subscription( $args = array() ) {

	$order = ( isset( $args['order_id'] ) ) ? wc_get_order( $args['order_id'] ) : null;

	if ( ! empty( $order ) ) {
		$default_start_date  = Hforce_Date_Time_Utils::get_datetime_utc_string( hforce_get_objects_property( $order, 'date_created' ) );
	} else {
		$default_start_date = gmdate( 'Y-m-d H:i:s' );
	}

	$default_args = array(
		'status'             => '',
		'order_id'           => 0,
		'customer_note'      => null,
		'customer_id'        => ( ! empty( $order ) ) ? $order->get_user_id() : null,
		'start_date'         => $default_start_date,
		'created_via'        => ( ! empty( $order ) ) ? hforce_get_objects_property( $order, 'created_via' ) : '',
		'order_version'      => ( ! empty( $order ) ) ? hforce_get_objects_property( $order, 'version' ) : WC_VERSION,
		'currency'           => ( ! empty( $order ) ) ? hforce_get_objects_property( $order, 'currency' ) : get_woocommerce_currency(),
		'prices_include_tax' => ( ! empty( $order ) ) ? ( ( hforce_get_objects_property( $order, 'prices_include_tax' ) ) ? 'yes' : 'no' ) : get_option( 'woocommerce_prices_include_tax' ),
	);

        
	$args              = wp_parse_args( $args, $default_args );
	$subscription_data = array();

	if ( ! is_string( $args['start_date'] ) || false === Hforce_Date_Time_Utils::is_datetime_mysql_format( $args['start_date'] ) ) {
		return new WP_Error( 'hf_subscription_invalid_start_date_format', __( 'Invalid date. The date must be a string and of the format: "Y-m-d H:i:s".', 'xa-woocommerce-subscription' ) );
	} else if ( Hforce_Date_Time_Utils::date_to_time( $args['start_date'] ) > current_time( 'timestamp', true ) ) {
		return new WP_Error( 'hf_subscription_invalid_start_date', __( 'Subscription start date must be before current day.', 'xa-woocommerce-subscription' ) );
	}

	if ( empty( $args['customer_id'] ) || ! is_numeric( $args['customer_id'] ) || $args['customer_id'] <= 0 ) {
		return new WP_Error( 'hf_subscription_invalid_customer_id', __( 'Invalid subscription customer_id.', 'xa-woocommerce-subscription' ) );
	}

	if ( empty( $args['billing_period'] ) || ! in_array( strtolower( $args['billing_period'] ), array_keys( Hforce_Date_Time_Utils::subscription_period_strings() ) ) ) {
		return new WP_Error( 'hf_subscription_invalid_billing_period', __( 'Invalid subscription billing period given.', 'xa-woocommerce-subscription' ) );
	}

	if ( empty( $args['billing_interval'] ) || ! is_numeric( $args['billing_interval'] ) || absint( $args['billing_interval'] ) <= 0 ) {
		return new WP_Error( 'hf_subscription_invalid_billing_interval', __( 'Invalid subscription billing interval given. Must be an integer greater than 0.', 'xa-woocommerce-subscription' ) );
	}

	$subscription_data['post_type']     = 'hf_shop_subscription';
	$subscription_data['post_status']   = 'wc-pending';
	$subscription_data['ping_status']   = 'closed';
	$subscription_data['post_author']   = 1;
	$subscription_data['post_password'] = uniqid( 'order_' );
	$post_title_date = strftime( __( '%b %d, %Y @ %I:%M %p', 'xa-woocommerce-subscription' ) );
	$subscription_data['post_title']    = sprintf( __( 'Subscription &ndash; %s', 'xa-woocommerce-subscription' ), $post_title_date );
	$subscription_data['post_date_gmt'] = $args['start_date'];
	$subscription_data['post_date']     = get_date_from_gmt( $args['start_date'] );

	if ( $args['order_id'] > 0 ) {
		$subscription_data['post_parent'] = absint( $args['order_id'] );
	}

	if ( ! is_null( $args['customer_note'] ) && ! empty( $args['customer_note'] ) ) {
		$subscription_data['post_excerpt'] = $args['customer_note'];
	}

	if ( $args['status'] ) {
		if ( ! in_array( 'wc-' . $args['status'], array_keys( hforce_get_subscription_statuses() ) ) ) {
			return new WP_Error( 'woocommerce_invalid_subscription_status', __( 'Invalid subscription status given.', 'xa-woocommerce-subscription' ) );
		}
		$subscription_data['post_status']  = 'wc-' . $args['status'];
	}

        $subscription_data = apply_filters( 'hf_new_subscription_data', $subscription_data, $args );
        
	$subscription_id = wp_insert_post($subscription_data, true );
        
	if ( is_wp_error( $subscription_id ) ) {
		return $subscription_id;
	}

	update_post_meta( $subscription_id, '_order_key', 'wc_' . apply_filters( 'woocommerce_generate_order_key', uniqid( 'order_' ) ) );
	update_post_meta( $subscription_id, '_order_currency', $args['currency'] );
	update_post_meta( $subscription_id, '_prices_include_tax', $args['prices_include_tax'] );
	update_post_meta( $subscription_id, '_created_via', sanitize_text_field( $args['created_via'] ) );
	update_post_meta( $subscription_id, '_billing_period', $args['billing_period'] );
	update_post_meta( $subscription_id, '_billing_interval', absint( $args['billing_interval'] ) );
	update_post_meta( $subscription_id, '_customer_user', $args['customer_id'] );
	update_post_meta( $subscription_id, '_order_version', $args['order_version'] );

	return hforce_get_subscription( $subscription_id );
}


function update_order_meta( $from_order, $to_order, $type = 'subscription' ) {
    
	global $wpdb;

	if ( ! is_a( $from_order, 'WC_Abstract_Order' ) || ! is_a( $to_order, 'WC_Abstract_Order' ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. Orders expected aren\'t orders.',  'xa-woocommerce-subscription' ) );
	}

	if ( ! is_string( $type ) ) {
		throw new InvalidArgumentException( __( 'Invalid data. Type of copy is not a string.', 'xa-woocommerce-subscription' ) );
	}

	if ( ! in_array( $type, array( 'subscription', 'renewal_order', 'resubscribe_order' ) ) ) {
		$type = 'copy_order';
	}

	$meta_query = $wpdb->prepare(
		"SELECT `meta_key`, `meta_value`
		 FROM {$wpdb->postmeta}
		 WHERE `post_id` = %d
		 AND `meta_key` NOT LIKE '_schedule_%%'
		 AND `meta_key` NOT IN (
			 '_paid_date',
			 '_date_paid',
			 '_completed_date',
			 '_date_completed',
			 '_order_key',
			 '_edit_lock',
			 '_wc_points_earned',
			 '_transaction_id',
			 '_billing_interval',
			 '_billing_period',
			 '_subscription_resubscribe',
			 '_subscription_renewal',
			 '_subscription_switch',
			 '_payment_method',
			 '_payment_method_title'
		 )",
		hforce_get_objects_property( $from_order, 'id' )
	);

	if ( 'renewal_order' == $type ) {
		$meta_query .= " AND `meta_key` NOT LIKE '_download_permissions_granted' ";
	}

	
	$meta_query = apply_filters( 'hf_' . $type . '_meta_query', $meta_query, $to_order, $from_order );
	$meta       = $wpdb->get_results( $meta_query, 'ARRAY_A' );
	$meta       = apply_filters( 'hf_' . $type . '_meta', $meta, $to_order, $from_order );

	foreach ( $meta as $meta_item ) {
		hforce_set_objects_property( $to_order, $meta_item['meta_key'], maybe_unserialize( $meta_item['meta_value'] ), 'save', '', 'omit_key_prefix' );
	}
}

function hforce_get_subscription_statuses() {

	$subscription_status = array(
		'wc-pending'        => __( 'Pending', 'xa-woocommerce-subscription' ),
		'wc-active'         => __( 'Active', 'xa-woocommerce-subscription' ),
		'wc-on-hold'        => __( 'On hold', 'xa-woocommerce-subscription' ),
		'wc-cancelled'      => __( 'Cancelled', 'xa-woocommerce-subscription' ),		
		'wc-expired'        => __( 'Expired', 'xa-woocommerce-subscription' ),
		'wc-pending-cancel' => __( 'Pending Cancellation', 'xa-woocommerce-subscription' ),
	);

	return apply_filters( 'hf_subscription_statuses', $subscription_status );
}

function hforce_get_subscription_status_name( $status ) {

	if ( ! is_string( $status ) ) {
		return new WP_Error( 'hf_subscription_wrong_status_format', __( 'Can not get status name. Status is not a string.', 'xa-woocommerce-subscription' ) );
	}

	$statuses = hforce_get_subscription_statuses();
	$sanitized_status_key = hf_sanitize_subscription_status_key( $status );
	$status_name   = isset( $statuses[ $sanitized_status_key ] ) ? $statuses[ $sanitized_status_key ] : $status;
	return apply_filters( 'hf_subscription_status_name', $status_name, $status );
}


function hforce_get_users_subscriptions( $user_id = 0 ) {

	if ( 0 === $user_id || empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$subscriptions = apply_filters( 'hf_pre_get_users_subscriptions', array(), $user_id );

	if ( empty( $subscriptions ) && 0 !== $user_id && ! empty( $user_id ) ) {

		$post_ids = get_posts( array(
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'post_type'      => 'hf_shop_subscription',
			'orderby'        => 'date',
			'order'          => 'desc',
			'meta_key'       => '_customer_user',
			'meta_value'     => $user_id,
			'meta_compare'   => '=',
			'fields'         => 'ids',
		) );

		foreach ( $post_ids as $post_id ) {
			$subscription = hforce_get_subscription( $post_id );

			if ( $subscription ) {
				$subscriptions[ $post_id ] = $subscription;
			}
		}
	}

	return apply_filters( 'hforce_get_users_subscriptions', $subscriptions, $user_id );
}

function maybe_add_prefix_key($key, $prefix = '_') {
    return ( substr($key, 0, strlen($prefix)) != $prefix ) ? $prefix . $key : $key;
}

function is_cart_contains_resubscribe( $cart = '' ) {

        //echo '<pre>';print_r(WC()->cart);exit;
	$is_contains_resubscribe = false;
	if ( empty( $cart ) ) {
		$cart = WC()->cart;
	}
	if ( ! empty( $cart->cart_contents ) ) {
		foreach ( $cart->cart_contents as $cart_item ) {
			if ( isset( $cart_item['subscription_resubscribe'] ) ) {
				$is_contains_resubscribe = $cart_item;
				break;
			}
		}
	}
	return apply_filters( 'hf_cart_contains_resubscribe', $is_contains_resubscribe, $cart );
}


function hf_get_address_type_to_display( $address_type ) {
    
	if ( ! is_string( $address_type ) ) {
		return new WP_Error( 'hf_subscription_wrong_address_type_format', __( 'Can not get address type display name. Address type is not a string.', 'xa-woocommerce-subscription' ) );
	}

	$address_types = apply_filters( 'hf_subscription_address_types', array(
		'shipping' => __( 'Shipping Address', 'xa-woocommerce-subscription' ),
		'billing' => __( 'Billing Address', 'xa-woocommerce-subscription' ),
	) );

	$address_type_display = isset( $address_types[ $address_type ] ) ? $address_types[ $address_type ] : $address_type;
	return apply_filters( 'hf_subscription_address_type_display', $address_type_display, $address_type );
}

function hforce_get_subscription_available_date_types() {

	$dates = array(
		'start'        => __( 'Start Date', 'xa-woocommerce-subscription' ),
		'next_payment' => __( 'Next Payment', 'xa-woocommerce-subscription' ),
		'last_payment' => __( 'Last Order Date', 'xa-woocommerce-subscription' ),
		'cancelled'    => __( 'Cancelled Date', 'xa-woocommerce-subscription' ),
		'end'          => __( 'End Date', 'xa-woocommerce-subscription' ),
	);
	return apply_filters( 'hf_subscription_available_dates', $dates );
}

function hforce_display_date_type( $date_type, $subscription ) {

	if ( 'last_payment' === $date_type ) {
		$display_date_type = false;
	} elseif ( 'cancelled' === $date_type && 0 == $subscription->get_date( $date_type ) ) {
		$display_date_type = false;
	} else {
		$display_date_type = true;
	}
	return apply_filters( 'hforce_display_date_type', $display_date_type, $date_type, $subscription );
}

function hf_get_date_meta_key( $date_type ) {
    
	if ( ! is_string( $date_type ) ) {
		return new WP_Error( 'hf_subscription_wrong_date_type_format', __( 'Date type is not a string.', 'xa-woocommerce-subscription' ) );
	} elseif ( empty( $date_type ) ) {
		return new WP_Error( 'hf_subscription_wrong_date_type_format', __( 'Date type can not be an empty string.', 'xa-woocommerce-subscription' ) );
	}
	return apply_filters( 'hf_subscription_date_meta_key_prefix', sprintf( '_schedule_%s', $date_type ), $date_type );
}

function hf_normalise_date_type_key( $date_type_key, $display_deprecated_notice = false ) {

	$prefix_length = strlen( 'schedule_' );
	if ( 'schedule_' === substr( $date_type_key, 0, $prefix_length ) ) {
		$date_type_key = substr( $date_type_key, $prefix_length );
	}

	$suffix_length = strlen( '_date' );
	if ( '_date' === substr( $date_type_key, -$suffix_length ) ) {
		$date_type_key = substr( $date_type_key, 0, -$suffix_length );
	}

	$deprecated_notice = '';

	if ( 'start' === $date_type_key ) {
		$deprecated_notice = 'The "start" date type parameter has been deprecated to align date types with improvements to date APIs in WooCommerce 3.0, specifically the introduction of a new "date_created" API. Use "date_created"';
		$date_type_key     = 'date_created';
	} elseif ( 'last_payment' === $date_type_key ) {
		$deprecated_notice = 'The "last_payment" date type parameter has been deprecated due to ambiguity (it actually returns the date created for the last order) and to align date types with improvements to date APIs in WooCommerce 3.0, specifically the introduction of a new "date_paid" API. Use "last_order_date_created" or "last_order_date_paid"';
		$date_type_key = 'last_order_date_created';
	}

	return $date_type_key;
}

function hf_sanitize_subscription_status_key( $status_key ) {
	if ( ! is_string( $status_key ) || empty( $status_key ) ) {
		return '';
	}
	$status_key = ( 'wc-' === substr( $status_key, 0, 3 ) ) ? $status_key : sprintf( 'wc-%s', $status_key );
	return $status_key;
}

function hforce_get_subscriptions( $args ) {
	global $wpdb;

	$args = wp_parse_args( $args, array(
			'subscriptions_per_page' => 10,
			'paged'                  => 1,
			'offset'                 => 0,
			'orderby'                => 'start_date',
			'order'                  => 'DESC',
			'customer_id'            => 0,
			'product_id'             => 0,
			'variation_id'           => 0,
			'order_id'               => 0,
			'subscription_status'    => 'any',
			'meta_query_relation'    => 'AND',
		)
	);

	if ( 0 !== $args['order_id'] && 'shop_order' !== get_post_type( $args['order_id'] ) ) {
		return array();
	}

	if ( ! in_array( $args['subscription_status'], array( 'any', 'trash' ) ) ) {
		$args['subscription_status'] = hf_sanitize_subscription_status_key( $args['subscription_status'] );
	}

	$query_args = array(
		'post_type'      => 'hf_shop_subscription',
		'post_status'    => $args['subscription_status'],
		'posts_per_page' => $args['subscriptions_per_page'],
		'paged'          => $args['paged'],
		'offset'         => $args['offset'],
		'order'          => $args['order'],
		'fields'         => 'ids',
		'meta_query'     => array(),
	);

	if ( 0 != $args['order_id'] && is_numeric( $args['order_id'] ) ) {
		$query_args['post_parent'] = $args['order_id'];
	}

	switch ( $args['orderby'] ) {
		case 'status' :
			$query_args['orderby'] = 'post_status';
			break;
		case 'start_date' :
			$query_args['orderby'] = 'date';
			break;
		case 'end_date' :
			$query_args = array_merge( $query_args, array(
				'orderby'   => 'meta_value',
				'meta_key'  => hf_get_date_meta_key( $args['orderby'] ),
				'meta_type' => 'DATETIME',
			) );
			$query_args['meta_query'][] = array(
				'key'     => hf_get_date_meta_key( $args['orderby'] ),
				'value'   => 'EXISTS',
				'type'    => 'DATETIME',
			);
			break;
		default :
			$query_args['orderby'] = $args['orderby'];
			break;
	}

	if ( 0 != $args['customer_id'] && is_numeric( $args['customer_id'] ) ) {
		$query_args['meta_query'][] = array(
			'key'     => '_customer_user',
			'value'   => $args['customer_id'],
			'type'    => 'numeric',
			'compare' => ( is_array( $args['customer_id'] ) ) ? 'IN' : '=',
		);
	};

	if ( ( 0 != $args['product_id'] && is_numeric( $args['product_id'] ) ) || ( 0 != $args['variation_id'] && is_numeric( $args['variation_id'] ) ) ) {
		$query_args['post__in'] = hforce_get_subscriptions_for_product( array( $args['product_id'], $args['variation_id'] ) );
	}

	if ( ! empty( $query_args['meta_query'] ) ) {
		$query_args['meta_query']['relation'] = $args['meta_query_relation'];
	}

	$query_args = apply_filters( 'hforce_get_subscription_query_args', $query_args, $args );

	$subscription_post_ids = get_posts( $query_args );

	$subscriptions = array();

	foreach ( $subscription_post_ids as $post_id ) {
		$subscriptions[ $post_id ] = hforce_get_subscription( $post_id );
	}
	return apply_filters( 'woocommerce_available_hf_subscriptions', $subscriptions, $args );
}

function hforce_get_subscriptions_for_product( $product_ids, $fields = 'ids' ) {
    
	global $wpdb;

	if ( is_array( $product_ids ) ) {
		$ids_for_query = implode( "', '", array_map( 'absint', array_unique( $product_ids ) ) );
	} else {
		$ids_for_query = absint( $product_ids );
	}

	$subscription_ids = $wpdb->get_col( "
		SELECT DISTINCT order_items.order_id FROM {$wpdb->prefix}woocommerce_order_items as order_items
			LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS itemmeta ON order_items.order_item_id = itemmeta.order_item_id
			LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
		WHERE posts.post_type = 'hf_shop_subscription'
			AND itemmeta.meta_value IN ( '" . $ids_for_query . "' )
			AND itemmeta.meta_key   IN ( '_variation_id', '_product_id' )"
	);

	$subscriptions = array();

	foreach ( $subscription_ids as $post_id ) {
		$subscriptions[ $post_id ] = ( 'ids' !== $fields ) ? hforce_get_subscription( $post_id ) : $post_id;
	}
	return apply_filters( 'hf_subscription_for_product', $subscriptions, $product_ids, $fields );
}


function display_subscription_item_meta($item, $order){
    
	if ( function_exists( 'wc_display_item_meta' ) ) {
		wc_display_item_meta( $item );
	} else {
		$order->display_item_meta( $item );
	}
}
function display_subscription_item_downloads($item, $order){
    
	if ( function_exists( 'wc_display_item_downloads' ) ) {
		wc_display_item_downloads( $item );
	} else {
		$order->display_item_downloads( $item );
	}
}

function hf_can_items_be_removed( $subscription ) {
    
	$allow_remove = false;
	if ( sizeof( $subscription->get_items() ) > 1 && $subscription->payment_method_supports( 'subscription_amount_changes' ) && $subscription->has_status( array( 'active', 'on-hold', 'pending' ) ) ) {
		$allow_remove = true;
	}
	return apply_filters( 'hf_can_items_be_removed', $allow_remove, $subscription );
}

 function get_item_remove_url($subscription_id, $order_item_id) {

        $remove_link = add_query_arg(array('subscription_id' => $subscription_id, 'remove_item' => $order_item_id));
        $remove_link = wp_nonce_url($remove_link, $subscription_id);

        return $remove_link;
}

function hf_get_order_items_product_id( $item_id ) {
    
	global $wpdb;

	$product_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta
		 WHERE order_item_id = %d
		 AND meta_key = '_product_id'",
		$item_id
	) );
	return $product_id;
}

function hf_get_canonical_product_id( $item_or_product ) {

	if ( is_a( $item_or_product, 'WC_Product' ) ) {
		$product_id = $item_or_product->get_id();
	} elseif ( is_a( $item_or_product, 'WC_Order_Item' ) ) { 
		$product_id = ( $item_or_product->get_variation_id() ) ? $item_or_product->get_variation_id() : $item_or_product->get_product_id();
	} else { 
		$product_id = ( ! empty( $item_or_product['variation_id'] ) ) ? $item_or_product['variation_id'] : $item_or_product['product_id'];
	}
	return $product_id;
}

function hforce_get_subscription_ended_statuses() {
        $ended_sub_status = apply_filters( 'hf_subscription_ended_statuses', array( 'cancelled', 'trash', 'expired', 'pending-cancel' ) );
	return $ended_sub_status;
}

function hforce_help_tooltip($tip, $allow_html = false) {

    if (function_exists('wc_help_tip')) {
        $help_tip = wc_help_tip($tip, $allow_html);
    } else {
        if ($allow_html) {
            $tip = wc_sanitize_tooltip($tip);
        } else {
            $tip = esc_attr($tip);
        }
        $help_tip = sprintf('<img class="help_tip" data-tip="%s" src="%s/assets/images/help.png" height="16" width="16" />', $tip, esc_url(WC()->plugin_url()));
    }
    return $help_tip;
}

function hforce_get_objects_property( $object, $property, $single = 'single', $default = null ) {

	$prefixed_key = Hforce_Date_Time_Utils::add_prefix_key( $property );
	$value = ! is_null( $default ) ? $default : ( ( 'single' == $single ) ? null : array() );
	switch ( $property ) {

		case 'name' :
			if ( HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to( '3.0' ) ) {
				$value = $object->post->post_title;
			} else { 
				$value = $object->get_name();
			}
			break;

		case 'post' :
			if ( HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to( '3.0' ) ) {
				$value = $object->post;
			} else { 
				if ( method_exists( $object, 'is_type' ) && $object->is_type( 'variation' ) ) {
					$value = get_post( $object->get_parent_id() );
				} else {
					$value = get_post( $object->get_id() );
				}
			}
			break;

		case 'post_status' :
			$value = hforce_get_objects_property( $object, 'post' )->post_status;
			break;

		case 'parent_id' :
			if ( method_exists( $object, 'get_parent_id' ) ) { 
				$value = $object->get_parent_id();
			} else { 
				$value = $object->get_parent();
			}
			break;

		case 'variation_data' :
			if ( function_exists( 'wc_get_product_variation_attributes' ) ) { 
				$value = wc_get_product_variation_attributes( $object->get_id() );
			} else {
				$value = $object->$property;
			}
			break;

		case 'downloads' :
			if ( method_exists( $object, 'get_downloads' ) ) { 
				$value = $object->get_downloads();
			} else {
				$value = $object->get_files();
			}
			break;

		case 'order_version' :
		case 'version' :
			if ( method_exists( $object, 'get_version' ) ) {
				$value = $object->get_version();
			} else { 
				$value = $object->order_version;
			}
			break;
                case 'date_created' :
		case 'order_date' :
		case 'date' :
			if ( method_exists( $object, 'get_date_created' ) ) {
				$value = $object->get_date_created();
			} else {
				if ( '0000-00-00 00:00:00' != $object->post->post_date_gmt ) {
					$value = new WC_DateTime( $object->post->post_date_gmt, new DateTimeZone( 'UTC' ) );
					$value->setTimezone( new DateTimeZone( wc_timezone_string() ) );
				} else {
					$value = new WC_DateTime( $object->post->post_date, new DateTimeZone( wc_timezone_string() ) );
				}
			}
			break;

		case 'order_currency' :
		case 'currency' :
			if ( method_exists( $object, 'get_currency' ) ) {
				$value = $object->get_currency();
			} else { 
				$value = $object->get_order_currency();
			}
			break;

		case 'date_paid' :
			if ( method_exists( $object, 'get_date_paid' ) ) {
				$value = $object->get_date_paid();
			} else {
				if ( ! empty( $object->paid_date ) ) {
					$value = new WC_DateTime( $object->paid_date, new DateTimeZone( wc_timezone_string() ) );
				} else {
					$value = null;
				}
			}
			break;

		case 'cart_discount' :
			if ( method_exists( $object, 'get_total_discount' ) ) { 
				$value = $object->get_total_discount();
			} else { 
				$value = $object->cart_discount;
			}
			break;

		default :

			$function_name = 'get_' . $property;

			if ( is_callable( array( $object, $function_name ) ) ) {
				$value = $object->$function_name();
			} else {

				if ( method_exists( $object, 'get_meta' ) ) {
					if ( $object->meta_exists( $prefixed_key ) ) {
						if ( 'single' === $single ) {
							$value = $object->get_meta( $prefixed_key, true );
						} else {
							$value = wp_list_pluck( $object->get_meta( $prefixed_key, false ), 'value' );
						}
					}
				} elseif ( 'single' === $single && isset( $object->$property ) ) {
					$value = $object->$property;
				} elseif ( strtolower( $property ) !== 'id' && metadata_exists( 'post', hforce_get_objects_property( $object, 'id' ), $prefixed_key ) ) {
					if ( 'single' === $single ) {
						$value = get_post_meta( hforce_get_objects_property( $object, 'id' ), $prefixed_key, true );
					} else {
						$value = get_post_meta( hforce_get_objects_property( $object, 'id' ), $prefixed_key, false );
					}
				}
			}
			break;
	}

	return $value;
}

function hforce_set_objects_property( &$object, $key, $value, $save = 'save', $meta_id = '', $prefix_meta_key = 'prefix_meta_key' ) {

	$prefixed_key = Hforce_Date_Time_Utils::add_prefix_key( $key );

	if ( in_array( $prefixed_key, array( '_shipping_address_index', '_billing_address_index' ) ) ) {
		return;
	}

	$meta_setters_map = array(
		'_cart_discount'         => 'set_discount_total',
		'_cart_discount_tax'     => 'set_discount_tax',
		'_customer_user'         => 'set_customer_id',
		'_order_tax'             => 'set_cart_tax',
		'_order_shipping'        => 'set_shipping_total',
		'_sale_price_dates_from' => 'set_date_on_sale_from',
		'_sale_price_dates_to'   => 'set_date_on_sale_to',
	);

	if ( isset( $meta_setters_map[ $prefixed_key ] ) && is_callable( array( $object, $meta_setters_map[ $prefixed_key ] ) ) ) {
		$function = $meta_setters_map[ $prefixed_key ];
		$object->$function( $value );

	} elseif ( is_callable( array( $object, 'set' . $prefixed_key ) ) ) {

		if ( '_prices_include_tax' === $prefixed_key && ! is_bool( $value ) ) {
			$value = 'yes' === $value ? true : false;
		}

		$object->{ "set$prefixed_key" }( $value );

	} elseif ( is_callable( array( $object, 'set' . str_replace( '_order', '', $prefixed_key ) ) ) ) {
		$function_name = 'set' . str_replace( '_order', '', $prefixed_key );
		$object->$function_name( $value );

	} elseif ( is_callable( array( $object, 'update_meta_data' ) ) ) {
		$meta_key = ( 'prefix_meta_key' === $prefix_meta_key ) ? $prefixed_key : $key;
		$object->update_meta_data( $meta_key, $value, $meta_id );

	} elseif ( 'name' === $key ) {
		$object->post->post_title = $value;

	} else {
		$object->$key = $value;
	}

	if ( 'save' === $save ) {
		if ( is_callable( array( $object, 'save' ) ) ) { 
			$object->save();
		} elseif ( 'date_created' == $key ) { 
			wp_update_post( array( 'ID' => hforce_get_objects_property( $object, 'id' ), 'post_date' => get_date_from_gmt( $value ), 'post_date_gmt' => $value ) );
		} elseif ( 'name' === $key ) { 
			wp_update_post( array( 'ID' => hforce_get_objects_property( $object, 'id' ), 'post_title' => $value ) );
		} else {
			$meta_key = ( 'prefix_meta_key' === $prefix_meta_key ) ? $prefixed_key : $key;

			if ( ! empty( $meta_id ) ) {
				update_metadata_by_mid( 'post', $meta_id, $value, $meta_key );
			} else {
				update_post_meta( hforce_get_objects_property( $object, 'id' ), $meta_key, $value );
			}
		}
	}
}

function hf_delete_objects_property( &$object, $key, $save = 'save', $meta_id = '' ) {

	$prefixed_key = Hforce_Date_Time_Utils::add_prefix_key( $key );

	if ( ! empty( $meta_id ) && method_exists( $object, 'delete_meta_data_by_mid' ) ) {
		$object->delete_meta_data_by_mid( $meta_id );
	} elseif ( method_exists( $object, 'delete_meta_data' ) ) {
		$object->delete_meta_data( $prefixed_key );
	} elseif ( isset( $object->$key ) ) {
		unset( $object->$key );
	}

	
	if ( 'save' === $save ) {
		if ( method_exists( $object, 'save' ) ) {
			$object->save();
		} elseif ( ! empty( $meta_id ) ) {
			delete_metadata_by_mid( 'post', $meta_id );
		} else {
			delete_post_meta( hforce_get_objects_property( $object, 'id' ), $prefixed_key );
		}
	}
}

function hforce_is_order( $order ) {

	if ( method_exists( $order, 'get_type' ) ) {
		$is_order = ( 'shop_order' === $order->get_type() );
	} else {
		$is_order = ( isset( $order->order_type ) && 'simple' === $order->order_type );
	}

	return $is_order;
}

function hf_product_deprecated_property_handler( $property, $product ) {

	
	$function_name  = 'get_' . str_replace( 'subscription_', '', str_replace( 'subscription_period_', '', $property ) );
	$class_name     = get_class( $product );
	$value          = null;

	if ( in_array( $property, array( 'product_type', 'parent_product_type' ) ) || ( is_callable( array( 'HForce_Subscriptions_Product', $function_name ) ) && false !== strpos( $property, 'subscription' ) ) ) {

		switch ( $property ) {
			case 'product_type':
				$value       = $product->get_type();
				$alternative = $class_name . '::get_type()';
				break;                       

			default:
				$value       = call_user_func( array( 'HForce_Subscriptions_Product', $function_name ), $product );
				$alternative = sprintf( 'HForce_Subscriptions_Product::%s( $product )', $function_name );
				break;
		}

	}

	return $value;
}

function hf_get_coupon_property( $coupon, $property ) {

	$value = '';

	if ( HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to( '3.0' ) ) {
		$value = $coupon->$property;
	} else {
		$property_to_get = array(
			'type'                       => 'get_discount_type',
			'exclude_product_ids'        => 'get_excluded_product_ids',
			'expiry_date'                => 'get_date_expires',
			'exclude_product_categories' => 'get_excluded_product_categories',
			'customer_email'             => 'get_email_restrictions',
			'enable_free_shipping'       => 'get_free_shipping',
			'coupon_amount'              => 'get_amount',
		);

		switch ( true ) {
			case 'exists' == $property:
				$value = ( $coupon->get_id() > 0 ) ? true : false;
				break;
			case isset( $property_to_get[ $property ] ) && is_callable( array( $coupon, $property_to_get[ $property ] ) ):
				$function = $property_to_get[ $property ];
				$value    = $coupon->$function();
				break;
			case is_callable( array( $coupon, 'get_' . $property ) ):
				$value = $coupon->{ "get_$property" }();
				break;
		}
	}
        
	return $value;
}








/**
 * Checks if a user has a certain capability
 * @param array $allcaps
 * @param array $caps
 * @param array $args
 * @return true/false
 */
function hforce_user_has_capability( $allcaps, $caps, $args ) {
	if ( isset( $caps[0] ) ) {
		switch ( $caps[0] ) {

			case 'edit_hf_shop_subscription_status' :
				$user_id  = $args[1];
				$subscription = hforce_get_subscription( $args[2] );

				if ( $user_id === $subscription->get_user_id() ) {
					$allcaps['edit_hf_shop_subscription_status'] = true;
				}
			break;
                        case 'edit_hf_shop_subscription_payment_method' :
				$user_id  = $args[1];
				$subscription = hforce_get_subscription( $args[2] );

				if ( $subscription && $user_id === $subscription->get_user_id() ) {
					$allcaps['edit_hf_shop_subscription_payment_method'] = true;
				}
			break;
			case 'edit_hf_shop_subscription_line_items' :
				$user_id  = $args[1];
				$subscription = hforce_get_subscription( $args[2] );

				if ( $user_id === $subscription->get_user_id() ) {
					$allcaps['edit_hf_shop_subscription_line_items'] = true;
				}
			break;
			case 'subscribe_again' :
				$user_id  = $args[1];
				$subscription = hforce_get_subscription( $args[2] );

				if ( $user_id === $subscription->get_user_id() ) {
					$allcaps['subscribe_again'] = true;
				}
			break;
			case 'pay_for_order' :
				$user_id = $args[1];
				$order   = wc_get_order( $args[2] );

				if ( $order && is_order_contains_subscription( $order, 'any' ) ) {

					if ( $user_id === $order->get_user_id() ) {
						$allcaps['pay_for_order'] = true;
					} else {
						unset( $allcaps['pay_for_order'] );
					}
				}
			break;
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'hforce_user_has_capability', 15, 3 );





function hforce_can_user_put_subscription_on_hold( $subscription, $user = '' ) {

	$user_can_suspend = false;

	if ( empty( $user ) ) {
		$user = wp_get_current_user();
	} elseif ( is_int( $user ) ) {
		$user = get_user_by( 'id', $user );
	}

	if ( user_can( $user, 'manage_woocommerce' ) ) {

		$user_can_suspend = true;

	} else { 

		if ( ! is_object( $subscription ) ) {
			$subscription = hforce_get_subscription( $subscription );
		}

		if ( $user->ID == $subscription->get_user_id() ) {

                        // suspend related validation - implement in settings
			$suspension_count    = intval( $subscription->get_suspension_count() );
			$allowed_suspensions = get_option( HForce_Woocommerce_Subscription_Admin::$option_prefix . '_max_customer_suspensions', 0 );

			if ( 'unlimited' === $allowed_suspensions || $allowed_suspensions > $suspension_count ) {
				$user_can_suspend = true;
			}
		}
	}

	return apply_filters( 'hforce_can_user_put_subscription_on_hold', $user_can_suspend, $subscription );
}

function hforce_get_users_change_status_link( $subscription_id, $status, $current_status = '' ) {

	if ( '' === $current_status ) {
		$subscription = hforce_get_subscription( $subscription_id );

		if ( $subscription instanceof HForce_Subscription ) {
			$current_status = $subscription->get_status();
		}
	}

	$action_link = add_query_arg( array( 'subscription_id' => $subscription_id, 'change_subscription_to' => $status ) );
	$action_link = wp_nonce_url( $action_link, $subscription_id . $current_status );

	return apply_filters( 'hf_users_change_status_link', $action_link, $subscription_id, $status );
}

function hforce_can_user_resubscribe_to( $subscription, $user_id = '' ) {

	if ( ! is_object( $subscription ) ) {
		$subscription = hforce_get_subscription( $subscription );
	}
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}
	if ( empty( $subscription ) ) {
		$can_user_resubscribe = false;

	} elseif ( ! user_can( $user_id, 'subscribe_again', $subscription->get_id() ) ) {
		$can_user_resubscribe = false;
	} elseif ( ! $subscription->has_status( array( 'pending-cancel', 'cancelled', 'expired', 'trash' ) ) ) {
		$can_user_resubscribe = false;
	} elseif ( $subscription->get_total() <= 0 ) {
		$can_user_resubscribe = false;
	} else {
		$resubscribe_orders = get_posts( array(
			'meta_query'  => array(
				array(
					'key'     => '_subscription_resubscribe',
					'compare' => '=',
					'value'   => $subscription->get_id(),
					'type'    => 'numeric',
				),
			),
			'post_type'   => 'shop_order',
			'post_status' => 'any',
		) );

		$all_line_items_exist = true;
		$has_active_limited_subscription = false;

		foreach ( $subscription->get_items() as $line_item ) {
			$product = ( ! empty( $line_item['variation_id'] ) ) ? wc_get_product( $line_item['variation_id'] ) : wc_get_product( $line_item['product_id'] );
			if ( false === $product ) {
				$all_line_items_exist = false;
				break;
			}

			if ( 'active' == get_subscription_product_limitation( $product ) && ( hforce_user_has_subscription( $user_id, $product->get_id(), 'on-hold' ) || hforce_user_has_subscription( $user_id, $product->get_id(), 'active' ) ) ) {
				$has_active_limited_subscription = true;
				break;
			}
		}

		if ( empty( $resubscribe_orders ) && $subscription->get_completed_payment_count() > 0 && true === $all_line_items_exist && false === $has_active_limited_subscription ) {
			$can_user_resubscribe = true;
		} else {
			$can_user_resubscribe = false;
		}
	}

	return apply_filters( 'hforce_can_user_resubscribe_to_subscription', $can_user_resubscribe, $subscription, $user_id );
}

function hforce_user_has_subscription( $user_id = 0, $product_id = '', $status = 'any' ) {

	$subscriptions = hf_get_users_subscriptions( $user_id );

	$has_subscription = false;

	if ( empty( $product_id ) ) { // Any subscription

		if ( ! empty( $status ) && 'any' != $status ) { // We need to check for a specific status
			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->has_status( $status ) ) {
					$has_subscription = true;
					break;
				}
			}
		} elseif ( ! empty( $subscriptions ) ) {
			$has_subscription = true;
		}
	} else {

		foreach ( $subscriptions as $subscription ) {
			if ( $subscription->has_product( $product_id ) && ( empty( $status ) || 'any' == $status || $subscription->has_status( $status ) ) ) {
				$has_subscription = true;
				break;
			}
		}
	}

	return apply_filters( 'hforce_user_has_subscription', $has_subscription, $user_id, $product_id, $status );
}

function hforce_get_users_resubscribe_link( $subscription ) {

	$subscription_id  = ( is_object( $subscription ) ) ? $subscription->get_id() : $subscription;
	$resubscribe_link = add_query_arg( array( 'resubscribe' => $subscription_id ), get_permalink( wc_get_page_id( 'myaccount' ) ) );
	$resubscribe_link = wp_nonce_url( $resubscribe_link, $subscription_id );
	return apply_filters( 'hforce_users_resubscribe_link', $resubscribe_link, $subscription_id );
}


/* PayPal related  start*/

function hforce_get_paypal_item_name( $item_name ) {

	if ( strlen( $item_name ) > 127 ) {
		$item_name = substr( $item_name, 0, 124 ) . '...';
	}
	return html_entity_decode( $item_name, ENT_NOQUOTES, 'UTF-8' );
}

function hforce_set_paypal_id( $order, $paypal_subscription_id ) {

	if ( ! is_object( $order ) ) {
		$order = wc_get_order( $order );
	}

	if ( hforce_is_paypal_profile_type( $paypal_subscription_id, 'billing_agreement' ) ) {
		if ( ! in_array( $paypal_subscription_id, get_user_meta( $order->get_user_id(), '_paypal_subscription_id', false ) ) ) {
			add_user_meta( $order->get_user_id(), '_paypal_subscription_id', $paypal_subscription_id );
		}
	}

	hforce_set_objects_property( $order, 'paypal_subscription_id', $paypal_subscription_id );
}

function hforce_is_paypal_profile_type( $profile_id, $profile_type ) {

	if ( 'billing_agreement' === $profile_type && 'B-' == substr( $profile_id, 0, 2 ) ) {
		$is_a = true;
	} elseif ( 'out_of_date_id' === $profile_type && 'S-' == substr( $profile_id, 0, 2 ) ) {
		$is_a = true;
	} else {
		$is_a = false;
	}

	return apply_filters( 'hf_subscriptions_is_paypal_profile_a_' . $profile_type, $is_a, $profile_id );
}

function hforce_get_paypal_billing_agreement_id( $order ) {

	if ( ! is_object( $order ) ) {
		$order = wc_get_order( $order );
	}
	return hforce_get_objects_property( $order, '_paypal_subscription_id' );
}

/* PayPal related  end*/