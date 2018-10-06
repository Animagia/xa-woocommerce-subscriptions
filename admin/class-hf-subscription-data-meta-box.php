<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Order Data
 *
 * Functions for displaying the subscription data meta box.
 *
 */
    
    
class HForce_Meta_Box_Subscription_Data extends WC_Meta_Box_Order_Data {

                                                                
	public static function output( $post ) {
            
		global $the_subscription;

		if ( ! is_object( $the_subscription ) || $the_subscription->get_id() !== $post->ID ) {
			$the_subscription = wc_get_order( $post->ID );
		}
		
		self::init_address_fields();
		wp_nonce_field( 'woocommerce_save_data', 'hf_meta_nonce' );
                
		?>
		
		<div class="panel-wrap woocommerce">
			<input name="post_title" type="hidden" value="<?php echo empty( $post->post_title ) ? esc_attr( get_post_type_object( $post->post_type )->labels->singular_name ) : esc_attr( $post->post_title ); ?>" />
			<input name="post_status" type="hidden" value="<?php echo esc_attr( 'wc-' . $the_subscription->get_status() ); ?>" />
			<div id="order_data" class="panel">

				<h2><?php
				printf( esc_html_x( 'Subscription #%s details', 'edit subscription header', 'xa-woocommerce-subscription' ), esc_html( $the_subscription->get_order_number() ) ); ?></h2>

				<div class="order_data_column_container">
					<div class="order_data_column">

						<p class="form-field form-field-wide wc-customer-user">
							
							<label for="customer_user"><?php _e( 'Customer:', 'woocommerce' ) ?> <?php
								if ( $the_subscription->get_user_id( 'edit' ) ) {
									$args = array(
										'post_status'    => 'all',
										'post_type'      => 'hf_shop_subscription',
										'_customer_user' => $the_subscription->get_user_id( 'edit' ),
									);
									printf( '<a href="%s">%s &rarr;</a>',
									esc_url( add_query_arg( $args, admin_url( 'edit.php' ) ) ),
									esc_html__( 'View other subscriptions', 'xa-woocommerce-subscription' )
                                                                        );
								}
							?></label>
							<?php
							$user_string = '';
							$user_id     = '';
							if ( $the_subscription->get_user_id() ) {
								$user_id     = absint( $the_subscription->get_user_id() );
								$user        = get_user_by( 'id', $user_id );
								
								$user_string = sprintf(
									esc_html__( '%1$s (#%2$s &ndash; %3$s)', 'woocommerce' ),
									$user->display_name,
									absint( $user->ID ),
									$user->user_email
								);
							}
							?>
							<select class="wc-customer-search" id="customer_user" name="customer_user" data-placeholder="<?php esc_attr_e( 'Guest', 'woocommerce' ); ?>" data-allow_clear="true">
								<option value="<?php echo esc_attr( $user_id ); ?>" selected="selected"><?php echo htmlspecialchars( $user_string ); ?></option>
							</select>
							
						</p>

						<p class="form-field form-field-wide">
							<label for="order_status"><?php esc_html_e( 'Subscription status:', 'xa-woocommerce-subscription' ); ?></label>
							<select id="order_status" name="order_status">
								<?php
								$statuses = hforce_get_subscription_statuses();
								foreach ( $statuses as $status => $status_name ) {
									if ( ! $the_subscription->can_be_updated_to( $status ) && ! $the_subscription->has_status( str_replace( 'wc-', '', $status ) ) ) {
										continue;
									}
									echo '<option value="' . esc_attr( $status ) . '" ' . selected( $status, 'wc-' . $the_subscription->get_status(), false ) . '>' . esc_html( $status_name ) . '</option>';
								}
								?>
							</select>
						</p>

						<?php do_action( 'woocommerce_admin_order_data_after_order_details', $the_subscription ); ?>

					</div>
					<div class="order_data_column">
						<h4><?php _e( 'Billing Details', 'xa-woocommerce-subscription' ); ?> <a class="edit_address" href="#"><a href="#" class="tips load_customer_billing" data-tip="Load billing address" style="display:none;"><?php _e( 'Load billing address', 'xa-woocommerce-subscription' ); ?></a></a></h4>
						<?php

                                                echo '<div class="address">';

						if ( $the_subscription->get_formatted_billing_address() ) {
							echo '<p><strong>' . esc_html__( 'Address', 'xa-woocommerce-subscription' ) . ':</strong>' . wp_kses( $the_subscription->get_formatted_billing_address(), array( 'br' => array() ) ) . '</p>';
						} else {
							echo '<p class="none_set"><strong>' . esc_html__( 'Address', 'xa-woocommerce-subscription' ) . ':</strong> ' . esc_html__( 'No billing address set.', 'xa-woocommerce-subscription' ) . '</p>';
						}

						foreach ( self::$billing_fields as $key => $field ) {

							if ( isset( $field['show'] ) && false === $field['show'] ) {
								continue;
							}

							$function_name = 'get_billing_' . $key;

							if ( is_callable( array( $the_subscription, $function_name ) ) ) {
								$field_value = $the_subscription->$function_name( 'edit' );
							} else {
								$field_value = $the_subscription->get_meta( '_billing_' . $key );
							}

							echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . wp_kses_post( make_clickable( esc_html( $field_value ) ) ) . '</p>';
						}

						echo '<p' . ( ( '' != $the_subscription->get_payment_method() ) ? ' class="' . esc_attr( $the_subscription->get_payment_method() ) . '"' : '' ) . '><strong>' . esc_html__( 'Payment Method', 'xa-woocommerce-subscription' ) . ':</strong>' . wp_kses_post( nl2br( $the_subscription->get_payment_method_to_display() ) );

						if ( '' != $the_subscription->get_payment_method()  && ! $the_subscription->is_manual() ) {
							echo hforce_help_tooltip( sprintf( __( 'Gateway ID: [%s]', 'xa-woocommerce-subscription' ), $the_subscription->get_payment_method() ) );
						}

						echo '</p>';
						echo '</div>';
						echo '<div class="edit_address">';

						foreach ( self::$billing_fields as $key => $field ) {
							if ( ! isset( $field['type'] ) ) {
								$field['type'] = 'text';
							}

							switch ( $field['type'] ) {
								case 'select' :
									
									woocommerce_wp_select( array( 'id' => '_billing_' . $key, 'label' => $field['label'], 'options' => $field['options'], 'value' => isset( $field['value'] ) ? $field['value'] : null ) );
									break;
								default :
									
									woocommerce_wp_text_input( array( 'id' => '_billing_' . $key, 'label' => $field['label'], 'value' => isset( $field['value'] ) ? $field['value'] : null ) );
									break;
							}
						}

						echo '</div>';

						do_action( 'woocommerce_admin_order_data_after_billing_address', $the_subscription );
						?>
					</div>
					<div class="order_data_column">

						<h4><?php _e( 'Shipping Details', 'xa-woocommerce-subscription' ); ?>
							<a class="edit_address" href="#">
								<a href="#" class="tips billing-same-as-shipping" data-tip="Copy from billing" style="display:none;"><?php _e( 'Copy from billing', 'xa-woocommerce-subscription' ); ?></a>
								<a href="#" class="tips load_customer_shipping" data-tip="Load shipping address" style="display:none;"><?php _e( 'Load shipping address', 'xa-woocommerce-subscription' ); ?></a>
							</a>
						</h4>
						<?php
						
						echo '<div class="address">';

						if ( $the_subscription->get_formatted_shipping_address() ) {
							echo '<p><strong>' . __( 'Address', 'xa-woocommerce-subscription' ) . ':</strong>' . wp_kses( $the_subscription->get_formatted_shipping_address(), array( 'br' => array() ) ) . '</p>';
						} else {
							echo '<p class="none_set"><strong>' . __( 'Address', 'xa-woocommerce-subscription' ) . ':</strong> ' . __( 'No shipping address set.', 'xa-woocommerce-subscription' ) . '</p>';
						}

						if ( self::$shipping_fields ) {
							foreach ( self::$shipping_fields as $key => $field ) {
								if ( isset( $field['show'] ) && false === $field['show'] ) {
									continue;
								}

								$function_name = 'get_shipping_' . $key;

								if ( is_callable( array( $the_subscription, $function_name ) ) ) {
									$field_value = $the_subscription->$function_name( 'edit' );
								} else {
									$field_value = $the_subscription->get_meta( '_shipping_' . $key );
								}

								echo '<p><strong>' . esc_html( $field['label'] ) . ':</strong> ' . wp_kses_post( make_clickable( esc_html( $field_value ) ) ) . '</p>';
							}
						}

						if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' == get_option( 'woocommerce_enable_order_comments', 'yes' ) ) && $post->post_excerpt ) {
							echo '<p><strong>' . __( 'Customer Note:', 'xa-woocommerce-subscription' ) . '</strong> ' . wp_kses_post( nl2br( $post->post_excerpt ) ) . '</p>';
						}

						echo '</div>';

						echo '<div class="edit_address">';

						if ( self::$shipping_fields ) {
							foreach ( self::$shipping_fields as $key => $field ) {
								if ( ! isset( $field['type'] ) ) {
									$field['type'] = 'text';
								}

								switch ( $field['type'] ) {
									case 'select' :
										woocommerce_wp_select( array( 'id' => '_shipping_' . $key, 'label' => $field['label'], 'options' => $field['options'] ) );
										break;
									default :
										woocommerce_wp_text_input( array( 'id' => '_shipping_' . $key, 'label' => $field['label'] ) );
										break;
								}
							}
						}

						if ( apply_filters( 'woocommerce_enable_order_notes_field', 'yes' == get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) {
							?>
							<p class="form-field form-field-wide"><label for="excerpt"><?php esc_html_e( 'Customer Note:', 'xa-woocommerce-subscription' ) ?></label>
								<textarea rows="1" cols="40" name="excerpt" tabindex="6" id="excerpt" placeholder="<?php esc_attr_e( 'Customer\'s notes about the order', 'xa-woocommerce-subscription' ); ?>"><?php echo wp_kses_post( $post->post_excerpt ); ?></textarea></p>
								<?php
						}

						echo '</div>';

						do_action( 'woocommerce_admin_order_data_after_shipping_address', $the_subscription );
						?>
					</div>
				</div>
				<div class="clear"></div>
			</div>
		</div>
                <style type="text/css">
			#post-body-content, #titlediv, #major-publishing-actions, #minor-publishing-actions, #visibility, #submitdiv { display:none }
		</style>
		<?php
	}

        
	public static function save( $post_id, $post = '' ) {
            
                if(empty($_POST))                    
                    return;
                
                //echo "hello";exit;  echo '<pre>';print_r($_POST);exit;
		global $wpdb;

		if ( 'hf_shop_subscription' != $post->post_type || empty( $_POST['hf_meta_nonce'] ) || ! wp_verify_nonce( $_POST['hf_meta_nonce'], 'woocommerce_save_data' ) ) {
			return;
		}
               
		self::init_address_fields();
		add_post_meta( $post_id, '_order_key', uniqid( 'order_' ), true );
		update_post_meta( $post_id, '_customer_user', absint( $_POST['customer_user'] ) );

		if ( self::$billing_fields ) {
			foreach ( self::$billing_fields as $key => $field ) {

				if ( ! isset( $_POST[ '_billing_' . $key ] ) ) {
					continue;
				}
				update_post_meta( $post_id, '_billing_' . $key, wc_clean( $_POST[ '_billing_' . $key ] ) );
			}
		}

		if ( self::$shipping_fields ) {
			foreach ( self::$shipping_fields as $key => $field ) {

				if ( ! isset( $_POST[ '_shipping_' . $key ] ) ) {
					continue;
				}
				update_post_meta( $post_id, '_shipping_' . $key, wc_clean( $_POST[ '_shipping_' . $key ] ) );
			}
		}

		$subscription = hforce_get_subscription( $post_id );

		try {
			if ( 'cancelled' == $_POST['order_status'] ) {
				$subscription->cancel_order();
			} else {
                               //hf_sanitize_subscription_status_key
				$subscription->update_status( wc_clean($_POST['order_status']), '', true );
			}
		} catch ( Exception $e ) {
			hf_add_admin_notice( sprintf( __( 'Error updating some information: %s', 'xa-woocommerce-subscription' ), $e->getMessage() ), 'error' );
		}

		do_action( 'woocommerce_process_hf_shop_subscription_meta', $post_id, $post );
	}

}