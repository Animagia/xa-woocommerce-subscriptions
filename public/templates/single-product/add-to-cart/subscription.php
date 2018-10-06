<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
/**
 * Simple product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/simple.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see 	    https://docs.woocommerce.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     3.0.0
 */
 
global $product;

if (!$product->is_purchasable() && (!is_user_logged_in() || 'no' == get_subscription_product_limitation($product) )) {
    return;
}

$user_id = get_current_user_id();

if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.0')) :
    $availability = $product->get_availability();
    if ($availability['availability']) :
        echo apply_filters('woocommerce_stock_html', '<p class="stock ' . $availability['class'] . '">' . $availability['availability'] . '</p>', $availability['availability']);
    endif;
else :
    echo wc_get_stock_html($product);
endif;

if (!$product->is_in_stock()) :
    ?>
    <link itemprop="availability" href="http://schema.org/OutOfStock">
<?php else : ?>

    <link itemprop="availability" href="http://schema.org/InStock">

    <?php do_action('woocommerce_before_add_to_cart_form'); ?>

    <?php if (!$product->is_purchasable() && 0 != $user_id && 'no' != get_subscription_product_limitation($product) && hforce_is_subscription_limited_for_user($product, $user_id)) : ?>
        <?php $resubscribe_link = hf_get_users_resubscribe_link_for_product($product->get_id()); ?>
        <?php if (!empty($resubscribe_link) && 'any' == get_subscription_product_limitation($product) && hf_user_has_subscription($user_id, $product->get_id(), get_subscription_product_limitation($product)) && !hf_user_has_subscription($user_id, $product->get_id(), 'active') && !hf_user_has_subscription($user_id, $product->get_id(), 'on-hold')) : // customer has an inactive subscription, maybe offer the renewal button ?>
            <a href="<?php echo esc_url($resubscribe_link); ?>" class="button product-resubscribe-link"><?php esc_html_e('Resubscribe', 'xa-woocommerce-subscription'); ?></a>
        <?php else : ?>
            <p class="limited-subscription-notice notice"><?php esc_html_e('You have an active subscription to this product already.', 'xa-woocommerce-subscription'); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <form class="cart" method="post" enctype='multipart/form-data'>

            <?php do_action('woocommerce_before_add_to_cart_button'); ?>

            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" />

            <?php
            if (!$product->is_sold_individually()) {
                woocommerce_quantity_input(array(
                    'min_value' => apply_filters('woocommerce_quantity_input_min', 1, $product),
                    'max_value' => apply_filters('woocommerce_quantity_input_max', $product->backorders_allowed() ? '' : $product->get_stock_quantity(), $product),
                ));
            }
            ?>
            <button type="submit" class="single_add_to_cart_button button alt"><?php echo esc_html($product->single_add_to_cart_text()); ?></button>
            <?php do_action('woocommerce_after_add_to_cart_button'); ?>
        </form>
    <?php endif; ?>
    <?php do_action('woocommerce_after_add_to_cart_form'); ?>

<?php endif; ?>