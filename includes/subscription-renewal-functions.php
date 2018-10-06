<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function hf_create_renewal_order( $subscription ) {

	$renewal_order = hf_create_order_from_subscription( $subscription, 'renewal_order' );
	if ( is_wp_error( $renewal_order ) ) {
		return new WP_Error( 'renewal-order-error', $renewal_order->get_error_message() );
	}
	hforce_set_objects_property( $renewal_order, 'subscription_renewal', $subscription->get_id(), 'save' );
	return apply_filters( 'hf_renewal_order_created', $renewal_order, $subscription );
}

 
function hf_order_contains_renewal( $order ) {

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

 
function hf_cart_contains_renewal() {

	$contains_renewal = false;

	if ( ! empty( WC()->cart->cart_contents ) ) {
		foreach ( WC()->cart->cart_contents as $cart_item ) {
			if ( isset( $cart_item['subscription_renewal'] ) ) {
				$contains_renewal = $cart_item;
				break;
			}
		}
	}

	return apply_filters( 'hf_cart_contains_renewal', $contains_renewal );
}

function hf_cart_contains_failed_renewal_order_payment() {

	$contains_renewal = false;
	$cart_item        = hf_cart_contains_renewal();

	if ( false !== $cart_item && isset( $cart_item['subscription_renewal']['renewal_order_id'] ) ) {
		$renewal_order           = wc_get_order( $cart_item['subscription_renewal']['renewal_order_id'] );
		$is_failed_renewal_order = apply_filters( 'hf_subscriptions_is_failed_renewal_order', $renewal_order->has_status( 'failed' ), $cart_item['subscription_renewal']['renewal_order_id'], $renewal_order->get_status() );

		if ( $is_failed_renewal_order ) {
			$contains_renewal = $cart_item;
		}
	}

	return apply_filters( 'hf_cart_contains_failed_renewal_order_payment', $contains_renewal );
}


function hforce_get_subscriptions_for_renewal_order( $order ) {

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



function hf_create_order_from_subscription( $subscription, $type ) {

        if ( ! is_string( $type ) ) {
		$type =  new WP_Error( 'order_from_subscription_type_type', sprintf( __( '"%s" passed to the function was not a string.', 'xa-woocommerce-subscription' ), $type ) );

	}
	if ( ! in_array( $type, apply_filters( 'hf_new_order_types', array( 'renewal_order', 'resubscribe_order' ) ) ) ) {
		$type = new WP_Error( 'order_from_subscription_type', sprintf( __( '"%s" is not a valid new order type.', 'xa-woocommerce-subscription' ), $type ) );
	}

	if ( is_wp_error( $type ) ) {
		return $type;
	}

	global $wpdb;

	try {
                // -InnoDB
		$wpdb->query( 'START TRANSACTION' );

		if ( ! is_object( $subscription ) ) {
			$subscription = hforce_get_subscription( $subscription );
		}

		$new_order = wc_create_order( array(
			'customer_id'   => $subscription->get_user_id(),
			'customer_note' => $subscription->get_customer_note(),
		) );

		update_order_meta( $subscription, $new_order, $type );

		$items = apply_filters( 'hf_new_order_items', $subscription->get_items( array( 'line_item', 'fee', 'shipping', 'tax' ) ), $new_order, $subscription );
		$items = apply_filters( 'hf_' . $type . '_items', $items, $new_order, $subscription );

		foreach ( $items as $item_index => $item ) {

			$item_name = apply_filters( 'hf_new_order_item_name', $item['name'], $item, $subscription );
			$item_name = apply_filters( 'hf_' . $type . '_item_name', $item_name, $item, $subscription );

			$order_item_id = wc_add_order_item( hforce_get_objects_property( $new_order, 'id' ), array(
				'order_item_name' => $item_name,
				'order_item_type' => $item['type'],
			) );

			if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to( '3.0' ) ) {
				foreach ( $item['item_meta'] as $meta_key => $meta_values ) {
					foreach ( $meta_values as $meta_value ) {
						wc_add_order_item_meta( $order_item_id, $meta_key, maybe_unserialize( $meta_value ) );
					}
				}
			} else {
				$order_item = $new_order->get_item( $order_item_id );
                                //copy order item details
				hf_copy_order_item_details( $item, $order_item );
				$order_item->save();
			}

			if ( 'line_item' == $item['type'] && isset( $item['product_id'] ) ) {

				$product_id = hf_get_canonical_product_id( $item );
				$product    = wc_get_product( $product_id );

				if ( false !== $product ) {

					$args = array(
						'totals' => array(
							'subtotal'     => $item['line_subtotal'],
							'total'        => $item['line_total'],
							'subtotal_tax' => $item['line_subtotal_tax'],
							'tax'          => $item['line_tax'],
							'tax_data'     => maybe_unserialize( $item['line_tax_data'] ),
						),
					);

					if ( ! empty( $item['variation_id'] ) && null !== ( $variation_data = hforce_get_objects_property( $product, 'variation_data' ) ) ) {
						foreach ( $variation_data as $attribute => $variation ) {
							if ( isset( $item[ str_replace( 'attribute_', '', $attribute ) ] ) ) {
								$args['variation'][ $attribute ] = $item[ str_replace( 'attribute_', '', $attribute ) ];
							}
						}
					}

					if ( isset( $order_item ) && is_callable( array( $order_item, 'set_backorder_meta' ) ) ) {
						$order_item->set_backorder_meta();
						$order_item->save();
					} elseif ( $product->backorders_require_notification() && $product->is_on_backorder( $item['qty'] ) ) {
						wc_add_order_item_meta( $order_item_id, apply_filters( 'woocommerce_backordered_item_meta_name', __( 'Backordered', 'xa-woocommerce-subscription' ) ), $item['qty'] - max( 0, $product->get_total_stock() ) );
					}

					if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to( '3.0' ) ) {
						do_action( 'woocommerce_order_add_product', hforce_get_objects_property( $new_order, 'id' ), $order_item_id, $product, $item['qty'], $args );
					}
				}
			}
		}

		$wpdb->query( 'COMMIT' );

		return apply_filters( 'hf_new_order_created', $new_order, $subscription, $type );

	} catch ( Exception $e ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'new-order-error', $e->getMessage() );
	}
}

// As update_order_meta()

function hf_copy_order_item_details( $from_item, &$to_item ) {

	foreach ( $from_item->get_meta_data() as $meta_data ) {
		$to_item->update_meta_data( $meta_data->key, $meta_data->value );
	}
        $ord_item_type = $from_item->get_type();
	switch ( $ord_item_type ) {
                
                case 'shipping':
			$to_item->set_props( array(
				'method_id' => $from_item->get_method_id(),
				'total'     => $from_item->get_total(),
				'taxes'     => $from_item->get_taxes(),
			) );
			break;
		case 'tax':
			$to_item->set_props( array(
				'rate_id'            => $from_item->get_rate_id(),
				'label'              => $from_item->get_label(),
				'compound'           => $from_item->get_compound(),
				'tax_total'          => $from_item->get_tax_total(),
				'shipping_tax_total' => $from_item->get_shipping_tax_total(),
			) );
			break;
		case 'fee':
			$to_item->set_props( array(
				'tax_class'  => $from_item->get_tax_class(),
				'tax_status' => $from_item->get_tax_status(),
				'total'      => $from_item->get_total(),
				'taxes'      => $from_item->get_taxes(),
			) );
			break;
		case 'line_item':
			$to_item->set_props( array(
				'product_id'   => $from_item->get_product_id(),
				'variation_id' => $from_item->get_variation_id(),
				'quantity'     => $from_item->get_quantity(),
				'tax_class'    => $from_item->get_tax_class(),
				'subtotal'     => $from_item->get_subtotal(),
				'total'        => $from_item->get_total(),
				'taxes'        => $from_item->get_taxes(),
			) );
			break;
                    
                    
		case 'coupon':
			$to_item->set_props( array(
				'discount'     => $from_item->discount(),
				'discount_tax' => $from_item->discount_tax(),
			) );
			break;
	}
}

function hforce_get_subscriptions_for_resubscribe_order( $order ) {

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

 function get_failed_order_replaced_by($renewal_order_id) {
     
    global $wpdb;
    $failed_order_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_failed_order_replaced_by' AND meta_value = %s", $renewal_order_id));
    return ( null === $failed_order_id ) ? false : $failed_order_id;
}