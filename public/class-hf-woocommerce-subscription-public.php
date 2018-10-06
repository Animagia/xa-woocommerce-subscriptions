<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Plugin_Name
 * @subpackage Plugin_Name/public
 * @author     Your Name <email@example.com>
 */
class HForce_Woocommerce_Subscription_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
            
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/hf-woocommerce-subscription-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Plugin_Name_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Plugin_Name_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/hf-woocommerce-subscription-public.js', array( 'jquery' ), $this->version, false );

	}
        
        
    public function add_to_cart_redirect($url) {

        if (isset($_REQUEST['add-to-cart']) && is_numeric($_REQUEST['add-to-cart']) && HForce_Subscriptions_Product::is_subscription((int) $_REQUEST['add-to-cart'])) {

            if ('yes' != get_option(HForce_Woocommerce_Subscription_Admin::$option_prefix . '_hf_allow_multiple_purchase', 'no')) {
                wc_clear_notices();
                $url = wc_get_checkout_url();
            } elseif ('yes' != get_option('woocommerce_cart_redirect_after_add') && HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('2.5')) {
                $url = remove_query_arg('add-to-cart');
            }
        }
        return $url;
    }    
    
    public function redirect_cart_and_account_hooks() {

        add_filter('woocommerce_add_to_cart_redirect', array($this, 'add_to_cart_redirect'));
        if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('2.6')) {
            add_action('woocommerce_before_my_account', __CLASS__ . '::get_my_subscriptions_template');
        }
    }
        
    public function render_subscription_add_to_cart() {
        
        wc_get_template('single-product/add-to-cart/subscription.php', array(), '', HFORCE_SUBSCRIPTION_MAIN_PATH . 'public/templates/');
    }

    // load the subscriptions.php template on the My Account page.

    public static function get_my_subscriptions_template() {

        
        $subscriptions = hforce_get_users_subscriptions();
        $user_id = get_current_user_id();
        $params = array('subscriptions' => $subscriptions, 'user_id' => $user_id);
        wc_get_template('myaccount/subscriptions.php', $params, '', HFORCE_SUBSCRIPTION_MAIN_PATH. 'public/templates/');
    }
    
    public function add_view_subscription_template($located_template, $template_name, $args, $template_path, $default_path){
        
        
        global $wp;
        if ('myaccount/my-account.php' == $template_name && !empty($wp->query_vars['view-subscription']) && HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('2.6')) {
            $located_template = wc_locate_template('myaccount/view-subscription.php', $template_path, HFORCE_SUBSCRIPTION_MAIN_PATH. 'public/templates/');
            
        }
        
        return $located_template;
    }
    
    public function get_view_subscription_template() {
        wc_get_template('myaccount/view-subscription.php', array(), '', HFORCE_SUBSCRIPTION_MAIN_PATH . 'public/templates/');
    }
    
    public function get_related_subscriptions($order_id) {
        
        	$template      = 'myaccount/related-subscriptions.php';
		$subscriptions = get_subscriptions_by_order( $order_id, array( 'order_type' => 'any' ) );
                
		if ( ! empty( $subscriptions ) ) {
			wc_get_template( $template, array( 'order_id' => $order_id, 'subscriptions' => $subscriptions ), '', HFORCE_SUBSCRIPTION_MAIN_PATH . 'public/templates/' );
		}
    }
    
    	public function maybe_change_users_subscription() {

		if ( isset( $_GET['change_subscription_to'] ) && isset( $_GET['subscription_id'] ) && isset( $_GET['_wpnonce'] )  ) {

			$user_id      = get_current_user_id();
			$subscription = hforce_get_subscription( $_GET['subscription_id'] );
			$new_status   = $_GET['change_subscription_to'];

			if ( self::validate_request( $user_id, $subscription, $new_status, $_GET['_wpnonce'] ) ) {
				self::change_users_subscription( $subscription, $new_status );

				wp_safe_redirect( $subscription->get_view_order_url() );
				exit;
			}
		}
	}
        
        public static function validate_request( $user_id, $subscription, $new_status, $wpnonce = '' ) {
		$subscription = ( ! is_object( $subscription ) ) ? hforce_get_subscription( $subscription ) : $subscription;

		if ( ! hforce_is_subscription( $subscription ) ) {
			HForce_Subscription_Cart::add_notice( __( 'That subscription does not exist. Please contact us if you need assistance.', 'xa-woocommerce-subscription' ), 'error' );
			return false;

		} elseif ( ! empty( $wpnonce ) && wp_verify_nonce( $wpnonce, $subscription->get_id() . $subscription->get_status() ) === false ) {
			HForce_Subscription_Cart::add_notice( __( 'Security error. Please contact us if you need assistance.', 'xa-woocommerce-subscription' ), 'error' );
			return false;

		} elseif ( ! user_can( $user_id, 'edit_hf_shop_subscription_status', $subscription->get_id() ) ) {
			HForce_Subscription_Cart::add_notice( __( 'That doesn\'t appear to be one of your subscriptions.', 'xa-woocommerce-subscription' ), 'error' );
			return false;

		} elseif ( ! $subscription->can_be_updated_to( $new_status ) ) {
			HForce_Subscription_Cart::add_notice( sprintf( __( 'That subscription can not be changed to %s. Please contact us if you need assistance.', 'xa-woocommerce-subscription' ), hf_get_subscription_status_name( $new_status ) ), 'error' );
			return false;
		}

		return true;
	}
    public static function change_users_subscription( $subscription, $new_status ) {
		$subscription = ( ! is_object( $subscription ) ) ? hf_get_subscription( $subscription ) : $subscription;
		$changed = false;

		switch ( $new_status ) {
			case 'active' :
				if ( ! $subscription->needs_payment() ) {
					$subscription->update_status( $new_status );
					$subscription->add_order_note( _x( 'Subscription reactivated by the subscriber from their account page.', 'order note left on subscription after user action', 'xa-woocommerce-subscription' ) );
					HForce_Subscription_Cart::add_notice( _x( 'Your subscription has been reactivated.', 'Notice displayed to user confirming their action.', 'xa-woocommerce-subscription' ), 'success' );
					$changed = true;
				} else {
					HForce_Subscription_Cart::add_notice( __( 'You can not reactivate that subscription until paying to renew it. Please contact us if you need assistance.', 'xa-woocommerce-subscription' ), 'error' );
				}
				break;
			case 'on-hold' :
				if ( hforce_can_user_put_subscription_on_hold( $subscription ) ) {
					$subscription->update_status( $new_status );
					$subscription->add_order_note( _x( 'Subscription put on hold by the subscriber from their account page.', 'order note left on subscription after user action', 'xa-woocommerce-subscription' ) );
					HForce_Subscription_Cart::add_notice( _x( 'Your subscription has been put on hold.', 'Notice displayed to user confirming their action.', 'xa-woocommerce-subscription' ), 'success' );
					$changed = true;
				} else {
					HForce_Subscription_Cart::add_notice( __( 'You can not suspend that subscription - the suspension limit has been reached. Please contact us if you need assistance.', 'xa-woocommerce-subscription' ), 'error' );
				}
				break;
			case 'cancelled' :
				$subscription->cancel_order();
				$subscription->add_order_note( _x( 'Subscription cancelled by the subscriber from their account page.', 'order note left on subscription after user action', 'xa-woocommerce-subscription' ) );
				HForce_Subscription_Cart::add_notice( _x( 'Your subscription has been cancelled.', 'Notice displayed to user confirming their action.', 'xa-woocommerce-subscription' ), 'success' );
				$changed = true;
				break;
		}

		if ( $changed ) {
			do_action( 'hf_customer_changed_subscription_to_' . $new_status, $subscription );
		}
	}
}
