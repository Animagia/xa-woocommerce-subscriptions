<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class HForce_Woocommerce_Subscription {


	protected $loader;
	protected $plugin_name;

	protected $version;
        public static $endpoint = 'subscriptions';
        
        // setting tab slug
        const PLUGIN_ID = 'hf_subscriptions';


	public function __construct() {
            
		if ( defined( 'HFORCE_WC_SUBSCRIPTION_VERSION' ) ) {
			$this->version = HFORCE_WC_SUBSCRIPTION_VERSION;
		} else {
			$this->version = '1.2.9';
		}
		$this->plugin_name = 'xa-woocommerce-subscription';
                $this->plugin_base_name = HFORCE_SUBSCRIPTION_BASE_NAME; 
                
                $this->plugin_dir_path = plugin_dir_path( dirname( __FILE__ ) ); 

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
                add_action('plugins_loaded', array($this, 'load_wc_dependant_classes'));

	}
        public function load_wc_dependant_classes(){
                require_once $this->plugin_dir_path . 'includes/components/class-hf-product-subscription.php';
        }

        /**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - HForce_Woocommerce_Subscription_Loader. Orchestrates the hooks of the plugin.
	 * - HForce_Woocommerce_Subscription_i18n. Defines internationalization functionality.
	 * - HForce_Woocommerce_Subscription_Admin. Defines all hooks for the admin area.
	 * - HForce_Woocommerce_Subscription_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
                 
		require_once  $this->plugin_dir_path. 'includes/class-hf-woocommerce-subscription-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once $this->plugin_dir_path . 'includes/class-hf-woocommerce-subscription-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once $this->plugin_dir_path . 'admin/class-hf-woocommerce-subscription-admin.php';
                require_once $this->plugin_dir_path . 'admin/class-hf-subscription-data-meta-box.php';
                require_once $this->plugin_dir_path . 'admin/class-hf-subscription-schedule-meta-box.php';

                // related orders
                require_once $this->plugin_dir_path . 'admin/class-hf-subscription-related-meta-box.php';
                
                
                require_once $this->plugin_dir_path . 'includes/components/class-subscription-data-store.php';
                require_once $this->plugin_dir_path . 'includes/components/class-subscription.php';
                
                
                require_once $this->plugin_dir_path . 'includes/class-subscriptions-product.php';
                
                
                require_once $this->plugin_dir_path . 'includes/subscription-common-functions.php';
                
                require_once $this->plugin_dir_path . 'includes/class-hf-date-time-functions.php';
                                                
                require_once $this->plugin_dir_path . 'includes/class-subscriptions-cart.php';
                                
                
                require_once $this->plugin_dir_path . 'includes/components/action-scheduler/action-scheduler.php';
                require_once $this->plugin_dir_path . 'includes/components/abstract-scheduler.php';
                require_once $this->plugin_dir_path . 'includes/components/action-scheduler.php';
                
                
                
                /*PayPal*/
                require_once $this->plugin_dir_path . 'includes/class-subscription-paypal.php';
                
		/**
                 * 
                 * 
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once $this->plugin_dir_path . 'public/class-hf-woocommerce-subscription-public.php';

		
                require_once $this->plugin_dir_path . 'public/templates/myaccount/subscription-account-view.php';
                
                
                $this->loader = new HForce_Woocommerce_Subscription_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Plugin_Name_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new HForce_Woocommerce_Subscription_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

				$plugin_admin = new HForce_Woocommerce_Subscription_Admin( $this->get_plugin_name(), $this->get_version() );
                

				$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
				$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
                                
                $this->loader->add_filter( 'plugin_action_links_'.$this->get_plugin_base_name(), $plugin_admin, 'hf_action_links');
                $this->loader->add_filter( 'product_type_selector', $plugin_admin, 'hf_add_subscription_product_type');
                $this->loader->add_action( 'woocommerce_product_options_general_product_data', $plugin_admin, 'subscription_product_fields');
                $this->loader->add_action( 'save_post', $plugin_admin, 'save_subscription_meta_data', 11);
                
                $this->loader->add_action( 'woocommerce_before_checkout_form', $plugin_admin, 'enable_checkout_registration', -2);
                $this->loader->add_action( 'woocommerce_checkout_fields', $plugin_admin, 'make_checkout_form_account_fields_required', 10);
                $this->loader->add_action( 'woocommerce_after_checkout_form', $plugin_admin, 'disable_checkout_registration', 99);
                

                if (HForce_Woocommerce_Subscription_Admin::is_woocommerce_prior_to('3.3')) {
                    $this->loader->add_action( 'woocommerce_params', $plugin_admin, 'disable_guest_checkout', 99, 1);
                    $this->loader->add_action( 'wc_checkout_params', $plugin_admin, 'disable_guest_checkout', 99, 1);
                } else {
                    $this->loader->add_action( 'woocommerce_get_script_data', $plugin_admin, 'disable_guest_checkout', 99, 1);
                }
                
                $this->loader->add_action( 'woocommerce_before_checkout_process', $plugin_admin, 'register_on_subscription_checkout', 10);
                
                add_action('woocommerce_checkout_order_processed',  'HForce_Woocommerce_Subscription_Admin::process_checkout', 100, 2);
                
                $this->loader->add_action( 'init',  $plugin_admin, 'register_subscription_order_types', 10);
                $this->loader->add_action( 'init',  $plugin_admin, 'register_subscription_status', 10);
                
                $this->loader->add_action( 'woocommerce_remove_cart_item',  $plugin_admin, 'maybe_remove_cart_items', 10, 1);
                
                //add_action('init', array($this, 'maybe_activate_hf_subscriptions'));
                //add_action('init', array($this, 'load_plugin_textdomain'), 3);
 
                $this->loader->add_filter( 'woocommerce_data_stores', $plugin_admin, 'add_data_store', 10, 1);
                
                // subscription orders
                
                $this->loader->add_filter( 'manage_edit-hf_shop_subscription_columns', $plugin_admin, 'hf_shop_subscription_columns' );
				$this->loader->add_filter( 'manage_edit-hf_shop_subscription_sortable_columns', $plugin_admin, 'hf_shop_subscription_sortable_columns' );
				$this->loader->add_filter( 'manage_hf_shop_subscription_posts_custom_column', $plugin_admin, 'render_hf_shop_subscription_columns', 2);
                
                $this->loader->add_filter( 'manage_edit-shop_order_columns', $plugin_admin, 'subscription_relationship_column');
                $this->loader->add_action( 'manage_shop_order_posts_custom_column', $plugin_admin, 'subscription_relationship_column_content', 10, 1);
                
                $this->loader->add_filter( 'posts_where', $plugin_admin, 'filtered_orders');
                
                
                
                // subscription metaboxes
                add_action( 'add_meta_boxes', array( $this, 'load_subscription_metaboxes' ), 25 );
				add_action( 'add_meta_boxes', array( $this, 'remove_meta_boxes' ), 35 );
				add_action( 'woocommerce_process_shop_order_meta', array( $this, 'remove_meta_box_save' ), -1, 2 );
				add_action( 'woocommerce_process_shop_order_meta', 'HForce_Meta_Box_Subscription_Schedule::save', 10, 2 );
				add_action( 'woocommerce_process_shop_order_meta', 'HForce_Meta_Box_Subscription_Data::save', 10, 2 );
                add_action( 'save_post', array( $this, 'save_meta_boxes' ), 1, 2 );
                $this->loader->add_filter( 'woocommerce_order_actions', $plugin_admin, 'add_subscription_actions', 10, 1 );
                $this->loader->add_filter( 'woocommerce_order_action_hf_process_renewal', $plugin_admin, 'process_renewal_action_request', 10, 1 );
                $this->loader->add_action('woocommerce_scheduled_subscription_payment', $plugin_admin, 'process_renewal_action', 1, 1);
                
                
                $this->loader->add_action( 'woocommerce_order_action_hf_create_pending_renewal', $plugin_admin,  'create_pending_renewal_subscription_order', 10, 1 );
                
                // settings page
                
                $this->loader->add_filter( 'woocommerce_settings_tabs_array', $plugin_admin, 'add_subscription_settings_tab', 50);
                $this->loader->add_action( 'woocommerce_settings_tabs_hf_subscriptions', $plugin_admin, 'add_subscription_settings_page');
                $this->loader->add_action( 'woocommerce_update_options_' . self::PLUGIN_ID, $plugin_admin, 'save_subscription_settings');
                
                add_action( 'woocommerce_order_status_changed', 'HForce_Woocommerce_Subscription_Admin::update_subscription_payment', 10, 3);
                $this->loader->add_action( 'load-edit.php', $plugin_admin, 'process_bulk_actions' );
                
                // payment
                // Trigger a hook for gateways to charge recurring payments
				$this->loader->add_action( 'woocommerce_scheduled_subscription_payment', $plugin_admin ,'gateway_scheduled_subscription_payment', 10, 1 );
                // Create a gateway specific hooks for subscription events
				$this->loader->add_action( 'hf_subscription_status_updated', $plugin_admin ,'run_gateway_status_updated_hook', 10, 2 );
                $this->loader->add_filter( 'woocommerce_payment_gateways_setting_columns', $plugin_admin, 'payment_gateways_support_subscriptions_column');
                $this->loader->add_action( 'woocommerce_payment_gateways_setting_column_subscriptions', $plugin_admin, 'payment_gateways_subscriptions_support');
                
                add_action( 'admin_notices', array( __CLASS__, 'hforce_admin_notices' ) );
                
                // renewal
                $this->loader->add_filter('hf_renewal_order_created', $plugin_admin ,'add_order_note', 10, 2);
                
                $this->loader->add_filter( 'woocommerce_order_button_text', $plugin_admin, 'hf_order_button_text' );
                
                add_action( 'woocommerce_scheduled_subscription_expiration', array($this, 'do_expire_subscription'), 10, 1 );
                

	}
        public static function do_expire_subscription($subscription_id) {

            $subscription = hforce_get_subscription($subscription_id);

            if (false === $subscription) {
                throw new InvalidArgumentException(sprintf(__('Subscription does not exist in scheduled action: %d', 'xa-woocommerce-subscriptions'), $subscription_id));
            }

            $subscription->update_status('expired');
        }
        public static function hforce_admin_notices() {
            
            if (apply_filters('woocommerce_hforce_suppress_admin_notices', false)) {
                return;
            }

            $screen = get_current_screen();

            if($screen->id && strpos($screen->id, 'hf_shop_subscription'))
            {

            $notice = __('<li class="hf-premium-link"><a  target="_blank" href="%s" class="hf-nav-tab-premium">Upgrade to premium</a></li>', 'xa-woocommerce-subscription');
            $notice = sprintf($notice, 'https://www.webtoffee.com/product/woocommerce-subscriptions/');
            $notice.= __('<li>Supports Simple and Variable subscriptions.</li>', 'xa-woocommerce-subscription');
            $notice.= __('<li>Purchase Subscription and normal products in the same order.</li>', 'xa-woocommerce-subscription');
            $notice.= __('<li>Frequent releases with new features and compatibility updates.</li>', 'xa-woocommerce-subscription');
            $notice.= __('<li>Excellent Support for setting it up.</li>', 'xa-woocommerce-subscription');

            echo '<div class="updated woocommerce-message hf-nav-tab"><ul>' . $notice . '</ul><div class="hf-premium-banner"><img src="https://www.webtoffee.com/wp-content/uploads/2018/03/banner-774x200-2.jpg"/></div></div>';
            }
        }
        
        public function load_subscription_metaboxes() {
            
		global $post_ID;

                
		add_meta_box( 'hf-subscription-data', __( 'Subscription Details',  'xa-woocommerce-subscription' ), 'HForce_Meta_Box_Subscription_Data::output', 'hf_shop_subscription', 'normal', 'high' );
		add_meta_box( 'hf-subscription-schedule-box', __( 'Billing Schedule Details',  'xa-woocommerce-subscription' ), 'HForce_Meta_Box_Subscription_Schedule::output', 'hf_shop_subscription', 'side', 'default' );
		remove_meta_box( 'woocommerce-order-data', 'hf_shop_subscription', 'normal' );

		add_meta_box( 'subscription_related_orders', __( 'Related Orders', 'xa-woocommerce-subscription' ), 'HForce_Meta_Box_Related_Orders::output', 'hf_shop_subscription', 'normal', 'low' );

		if ( 'shop_order' === get_post_type( $post_ID ) && is_order_contains_subscription( $post_ID, 'any' ) ) {
			add_meta_box( 'subscription_renewal_orders', __( 'Related Orders', 'xa-woocommerce-subscription' ), 'HForce_Meta_Box_Related_Orders::output', 'shop_order', 'normal', 'low' );
		}
                
                
	}

	/**
	 * Removes the core Order Data meta box as we add our own Subscription Data meta box
	 */
	public function remove_meta_boxes() {
            
                remove_meta_box( 'woocommerce-order-data', 'hf_shop_subscription', 'normal' );
	}

	/**
	 * Don't save save some order related meta boxes
	 */
	public function remove_meta_box_save( $post_id, $post ) {

                //var_dump($_POST['_payment_method']);
                //var_dump($_POST['order_status']);
		if ( 'hf_shop_subscription' == $post->post_type ) {
			remove_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40, 2 );
		}
	}
        
        public function save_meta_boxes($post_id, $post = ''){
            
                //echo 'hhhhhhh'.$post->post_type;exit;
            	if ( in_array( $post->post_type, wc_get_order_types( 'order-meta-boxes' ) ) ) {
                        if(('trash' != @$_GET['action'])){                            
			do_action( 'woocommerce_process_shop_order_meta', $post_id, $post );
                        }
		}
        }

	private function define_public_hooks() {

		$plugin_public = new HForce_Woocommerce_Subscription_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );                                
                $this->loader->add_action( 'woocommerce_subscription_add_to_cart', $plugin_public, 'render_subscription_add_to_cart', 30 );
                
                $this->loader->add_action( 'plugins_loaded', $plugin_public, 'redirect_cart_and_account_hooks' );
                $this->loader->add_filter( 'wc_get_template', $plugin_public ,'add_view_subscription_template', 10, 5);
                $this->loader->add_action( 'woocommerce_account_view-subscription_endpoint', $plugin_public , 'get_view_subscription_template');
                
                $this->loader->add_action( 'woocommerce_order_details_after_order_table', $plugin_public,'get_related_subscriptions', 10, 1 );

                $this->loader->add_action( 'wp_loaded', $plugin_public,'maybe_change_users_subscription', 100 );
               
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

        public function get_plugin_base_name() {
		return $this->plugin_base_name;
	}
                
	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}
        
        public function run() {
		$this->loader->run();
	}

}




// WooCommerce QuickPay Start


if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {

    class WC_Subscriptions_Change_Payment_Gateway {

        public static $is_request_to_change_payment = false;
        public function __construct() {
            
        }

    }

}
// WooCommerce QuickPay end


// START -- Add subscription support for WC Stripe plugin

if (!class_exists('WC_Subscriptions_Order')) {

    class WC_Subscriptions_Order {

        public function __construct() {
            
        }

    }

}
if (!class_exists('WC_Subscriptions_Cart')) {

    class WC_Subscriptions_Cart {

        private static $calculation_type = 'none'; //none,recurring_total
        public function __construct() {
            
        }

        public static function cart_contains_subscription() {

            $is_cart_contains_subscription = false;

            if (!empty(WC()->cart->cart_contents) and ! hf_cart_contains_renewal()) {
                foreach (WC()->cart->cart_contents as $cart_item) {
                    if (HForce_Subscriptions_Product::is_subscription($cart_item['data'])) {
                        $is_cart_contains_subscription = true;
                        break;
                    }
                }
            }
            return $is_cart_contains_subscription;
        }
        
        public static function get_calculation_type() {
            return self::$calculation_type;
        }

        public static function set_calculation_type($calculation_type) {

            self::$calculation_type = $calculation_type;
            return $calculation_type;
        }
        
        // WooCommerce Services start
        public static function set_subscription_prices_for_calculation( $price, $product ) {

		if ( HForce_Subscriptions_Product::is_subscription( $product ) ) {
			$price = apply_filters( 'hforce_subscriptions_cart_get_price', $price, $product );
		} elseif ( 'recurring_total' == self::$calculation_type ) {
			$price = 0;
		}

		return $price;
	}
        // WooCommerce Services end

    }

}
if (!function_exists('wcs_create_renewal_order')) {

    function wcs_create_renewal_order() {
        
    }

}
if (!function_exists('wcs_order_contains_subscription')) {

    function wcs_order_contains_subscription($order, $order_type = array('parent', 'resubscribe', 'renewal',)) {

        if (!is_array($order_type)) {
            $order_type = array($order_type);
        }

        if (!is_a($order, 'WC_Abstract_Order')) {
            $order = wc_get_order($order);
        }

        $contains_subscription = false;
        $get_all = ( in_array('any', $order_type) ) ? true : false;


        if (( in_array('parent', $order_type) || $get_all ) && count(get_subscriptions_by_order(hforce_get_objects_property($order, 'id'), array('order_type' => 'parent'))) > 0) {
            $contains_subscription = true;
        } elseif (( in_array('renewal', $order_type) || $get_all ) && hf_order_contains_renewal($order)) {
            $contains_subscription = true;
        } elseif (( in_array('resubscribe', $order_type) || $get_all ) && is_order_contains_resubscribe($order)) {
            $contains_subscription = true;
        }

        return $contains_subscription;
    }

}

if (!function_exists('wcs_is_subscription')) {

    function wcs_is_subscription($subscription) {

        if (is_object($subscription) && is_a($subscription, 'HForce_Subscription')) {
            $is_subscription = true;
        } elseif (is_numeric($subscription) && 'hf_shop_subscription' == get_post_type($subscription)) {
            $is_subscription = true;
        } else {
            $is_subscription = false;
        }

        return apply_filters('hf_is_subscription', $is_subscription, $subscription);
    }

}

if (!function_exists('wcs_order_contains_renewal')) {

    function wcs_order_contains_renewal($order) {

        if (!is_a($order, 'WC_Abstract_Order')) {
            $order = wc_get_order($order);
        }

        if (hforce_is_order($order) && hforce_get_objects_property($order, 'subscription_renewal')) {
            $is_renewal = true;
        } else {
            $is_renewal = false;
        }

        return apply_filters('hf_subscriptions_is_renewal_order', $is_renewal, $order);
    }

}

if(!function_exists('wcs_get_subscriptions_for_order')){
function wcs_get_subscriptions_for_order( $order_id, $args = array() ) {

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
}


if(!function_exists('wcs_get_subscriptions_for_renewal_order')){
function wcs_get_subscriptions_for_renewal_order( $order ) {

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
}
// END -- Add subscription support for WC Stripe plugin
 
// Barclay Card Payments start

if (!class_exists('WC_Subscriptions')){
    class WC_Subscriptions{
        public static $version = '3.0.0';
        public static $name = 'hf_subscription';
    }
}

if (!class_exists('WC_Subscriptions_Manager')) {

    class WC_Subscriptions_Manager {

        public static function process_subscription_payments_on_order($order, $product_id = '') {

            $subscriptions = wcs_get_subscriptions_for_order($order);

            if (!empty($subscriptions)) {

                foreach ($subscriptions as $subscription) {
                    $subscription->payment_complete();
                }

                do_action('processed_subscription_payments_for_order', $order);
            }
        }

        public static function process_subscription_payment_failure_on_order($order, $product_id = '') {

            $subscriptions = wcs_get_subscriptions_for_order($order);

            if (!empty($subscriptions)) {

                foreach ($subscriptions as $subscription) {
                    $subscription->payment_failed();
                }

                do_action('processed_subscription_payment_failure_for_order', $order);
            }
        }

    }

}
// Barclay Card Payments end