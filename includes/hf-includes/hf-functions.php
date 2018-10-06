<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * Functions used by plugins
 */
if (!class_exists('Hf_Dependencies'))
    require_once 'class-hf-dependencies.php';

/**
 * WC Detection
 */
if (!function_exists('hf_is_woocommerce_active')) {

    function hf_is_woocommerce_active() {
        return Hf_Dependencies::woocommerce_active_check();
    }

}