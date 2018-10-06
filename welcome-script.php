<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

add_action('admin_init', 'hforce_subscription_welcome');
add_action('admin_menu', 'hforce_subscription_welcome_screen');
add_action('admin_head', 'hforce_subscription_welcome_screen_remove_menus');

function hforce_welcome_screen_and_activation_check() {
    set_transient('_subscription_welcome_screen_activation_redirect', true, 30);
}

function hforce_subscription_welcome() {

    if (!get_transient('_subscription_welcome_screen_activation_redirect')) {
        return;
    }
    delete_transient('_subscription_welcome_screen_activation_redirect');
    wp_safe_redirect(add_query_arg(array('page' => 'subscription-welcome'), admin_url('index.php')));
}

function hforce_subscription_welcome_screen() {
    add_dashboard_page('Welcome To HF Subscription for WooCommerce', 'Welcome To HF Subscription for WooCommerce', 'read', 'subscription-welcome', 'hforce_render_welcome_screen_content');
}

function hforce_render_welcome_screen_content() {
    include 'includes/welcome/welcome.php';
}

function hforce_subscription_welcome_screen_remove_menus() {
    remove_submenu_page('index.php', 'subscription-welcome');
}
