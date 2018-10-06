<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// recurring shipping methods display
// based on the WC core template: /woocommerce/templates/cart/cart-shipping.php

?>
<tr class="shipping recurring-total <?php echo esc_attr( $recurring_cart_key ); ?>">
	<th><?php echo wp_kses_post( $package_name ); ?></th>
	<td data-title="<?php echo esc_attr( $package_name ); ?>">
		<?php if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to( '2.6' ) && is_cart() ) :  ?>
			<?php echo wp_kses_post( wpautop( __( 'Recurring shipping options can be selected on checkout.', 'xa-woocommerce-subscription' ) ) ); ?>
		<?php elseif ( 1 < count( $available_methods ) ) : ?>
			<ul id="shipping_method_<?php echo esc_attr( $recurring_cart_key ); ?>">
				<?php foreach ( $available_methods as $method ) : ?>
					<li>
						<?php
							hf_cart_render_shipping_input( $index, $method, $chosen_method, 'radio' );
							printf( '<label for="shipping_method_%1$s_%2$s">%3$s</label>', esc_attr( $index ), esc_attr( sanitize_title( $method->id ) ), wp_kses_post( hf_cart_totals_shipping_method( $method, $recurring_cart ) ) );
							do_action( 'woocommerce_after_shipping_rate', $method, $index );
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php elseif ( ! WC()->customer->has_calculated_shipping() ) : ?>
			<?php echo wp_kses_post( wpautop( __( 'Shipping costs will be calculated once you have provided your address.', 'xa-woocommerce-subscription' ) ) ); ?>
		<?php else : ?>
			<?php echo wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping methods available. Please double check your address, or contact us if you need any help.', 'xa-woocommerce-subscription' ) ) ); ?>
		<?php endif; ?>

		<?php if ( $show_package_details ) : ?>
			<?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html( $package_details ) . '</small></p>'; ?>
		<?php endif; ?>
	</td>
</tr>