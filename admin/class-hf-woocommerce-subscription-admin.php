<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class HForce_Woocommerce_Subscription_Admin {

    //setting tab slug
    const PLUGIN_ID = 'hf_subscriptions';
    const TEXT_DOMAIN = 'xa-woocommerce-subscription';
    const PLUGN_BASE_PATH = __FILE__;

    private $plugin_name;
    private $version;
    private static $action_scheduler;
    
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    //subscription settings field prefix
    public static $option_prefix = 'hf_subscriptions';
    public static $name = 'subscription';
    private static $product_meta_saved = false;
    private static $guest_checkout_option_changed = false;
    public static $is_gateway_supports = array();

    public function __construct($plugin_name, $version) {

        
        $this->plugin_name = $plugin_name;
        $this->version = $version;
            $task_scheduler = apply_filters('hf_subscription_scheduler', 'HForce_Action_Scheduler');
            self::$action_scheduler = new $task_scheduler();
                                   
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {



        $screen = get_current_screen();
        $is_woocommerce_screen = ( in_array($screen->id, array('product', 'edit-shop_order', 'shop_order', 'edit-hf_shop_subscription', 'hf_shop_subscription', 'users', 'woocommerce_page_wc-settings')) ) ? true : false;

        if ($is_woocommerce_screen || 'edit-product' == $screen->id) {
            wp_enqueue_style('woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), $this->version);
            wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/hf-woocommerce-subscription-admin.css', array(), $this->version, 'all');
        }
    }
    
    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {


        global $post;

        $screen = get_current_screen();
        $is_woocommerce_screen = ( in_array($screen->id, array('product', 'edit-shop_order', 'shop_order', 'edit-hf_shop_subscription', 'hf_shop_subscription', 'users', 'woocommerce_page_wc-settings')) ) ? true : false;

        if ($is_woocommerce_screen) {
            $dependency_scripts = array('jquery');
            $wc_admin_script_handle = 'wc-admin-meta-boxes';
            $trash_subscription_order_warning = __('Deleting this order will also delete the subscriptions purchased with the order.', 'xa-woocommerce-subscription');

            if ($screen->id == 'product') {
                $dependency_scripts[] = $wc_admin_script_handle;
                $dependency_scripts[] = 'wc-admin-product-meta-boxes';

                $script_params = array(
                    'ajax_url'      => admin_url( 'admin-ajax.php' ),
                    'ProductType' => self::$name,
                    'SingularLocalizedTrialPeriod' => Hforce_Date_Time_Utils::get_available_time_periods(),
                    'PluralLocalizedTrialPeriod' => Hforce_Date_Time_Utils::get_available_time_periods('plural'),
                    'LocalizedSubscriptionLengths' => Hforce_Date_Time_Utils::hforce_get_subscription_ranges(),
                    'BulkEditPeriodMessage' => __('Enter the new period, either day, week, month or year:', 'xa-woocommerce-subscription'),
                    'BulkEditLengthMessage' => __('Enter a new length (e.g. 5):', 'xa-woocommerce-subscription'),
                    'BulkEditIntervalMessage' => __('Enter a new interval as a single number (e.g. to charge every 2nd month, enter 2):', 'xa-woocommerce-subscription'),
                );
            } else if ('edit-shop_order' == $screen->id) {
                $script_params = array(
                    'BulkTrashWarning' => __("You are about to trash one or more orders which contain a subscription.\n\nTrashing the orders will also trash the subscriptions purchased with these orders.", 'xa-woocommerce-subscription'),
                    'TrashWarning' => $trash_subscription_order_warning,
                );
            } else if ('shop_order' == $screen->id) {
                $dependency_scripts[] = $wc_admin_script_handle;
                $dependency_scripts[] = 'wc-admin-order-meta-boxes';

                if (self::is_woocommerce_prior_to('2.6')) {
                    $dependency_scripts[] = 'wc-admin-order-meta-boxes-modal';
                }

                $script_params = array(
                    'TrashWarning' => $trash_subscription_order_warning,
                    'postId' => $post->ID,
                );
            } else if ('users' == $screen->id) {
                $script_params = array(
                    'DeleteUserWarning' => __("Warning: Deleting a user will also delete the user's subscriptions. The user's orders will remain but be reassigned to the 'Guest' user.\n\nDo you want to continue to delete this user and any associated subscriptions?", 'xa-woocommerce-subscription'),
                );
            }
            $script_params['ajaxURL'] = admin_url('admin-ajax.php');
            
            wp_enqueue_script('hf_subscription_admin', HFORCE_BASE_URL . 'admin/js/hf-woocommerce-subscription-admin.js', $dependency_scripts, filemtime(HFORCE_SUBSCRIPTION_MAIN_PATH . 'admin/js/hf-woocommerce-subscription-admin.js'));
            wp_localize_script('hf_subscription_admin', 'HFSubscriptions_OBJ', apply_filters('hf_subscription_admin_script_parameters', $script_params));
        }
    }

    /**
     * Add action links to the plugin page.
     *
     * @since    1.0.0
     */
    public function hf_action_links($links) {


       $plugin_links = array(
           '<a href="' . self::settings_tab_url() . '">' . __('Settings', 'xa-woocommerce-subscription') . '</a>',
           '<a href="https://wordpress.org/support/plugin/xa-woocommerce-subscriptions">' . __('Support', 'xa-woocommerce-subscription') . '</a>',
           '<a href="https://wordpress.org/support/plugin/xa-woocommerce-subscriptions/reviews?rate=5#new-post">' . __('Review', 'xa-woocommerce-subscription') . '</a>',
       );
       if (array_key_exists('deactivate', $links)) {
           $links['deactivate'] = str_replace('<a', '<a class="hfsubscriptions-deactivate-link"', $links['deactivate']);
       }
       return array_merge($plugin_links, $links);
   }

    
    //settings page
    
    public static function settings_tab_url() {

        
        $settings_tab_url = admin_url('admin.php?page=wc-settings&tab=' . self::PLUGIN_ID);
        return $settings_tab_url;
    }

    public function add_subscription_settings_tab($settings_tabs) {

        $settings_tabs[self::PLUGIN_ID] = __('WebToffee Subscriptions', 'xa-woocommerce-subscription');
        return $settings_tabs;
    }
    
    public static function add_subscription_settings_page() {
        
        woocommerce_admin_fields(self::get_settings());
        wp_nonce_field('hf_subscription_settings', '_hfnonce', false);
    }
    

    public static function get_settings() {

        return apply_filters('hf_subscription_settings', array(
            array(
                'name' => __('Manage Text', 'xa-woocommerce-subscription'),
                'type' => 'title',
                'desc' => '',
                'id' => self::$option_prefix . '_button_text',
            ),
            array(
                'name' => __('My Account Tab Title', 'xa-woocommerce-subscription'),
                'desc' => __('My Account Tab Title for subscription listing page.', 'xa-woocommerce-subscription'),
                'id' => self::$option_prefix . '_subscription_tab_title',
                'css' => 'min-width:150px;',
                'default' => __('Subscriptions', 'xa-woocommerce-subscription'),
                'type' => 'text',
                'desc_tip' => true,
            ),
            array(
                'name' => __('My Account Tab Text', 'xa-woocommerce-subscription'),
                'desc' => __('My Account Tab Text for subscription listing page.', 'xa-woocommerce-subscription'),
                'id' => self::$option_prefix . '_subscription_tab_text',
                'css' => 'min-width:150px;',
                'default' => __('Subscriptions', 'xa-woocommerce-subscription'),
                'type' => 'text',
                'desc_tip' => true,
            ),
            array(
                'name' => __('Add to Cart Button Text', 'xa-woocommerce-subscription'),
                'desc' => __('Add to Cart Button Text on product page.', 'xa-woocommerce-subscription'),
                'id' => self::$option_prefix . '_add_to_cart_button_text',
                'css' => 'min-width:150px;',
                'default' => __('Subscribe', 'xa-woocommerce-subscription'),
                'type' => 'text',
                'desc_tip' => true,
            ),
            array(
                'name' => __('Place Order Button Text', 'xa-woocommerce-subscription'),
                'desc' => __('Place Order Button Text in checkout Page when an order contains a subscription.', 'xa-woocommerce-subscription'),
                'id' => self::$option_prefix . '_order_button_text',
                'css' => 'min-width:150px;',
                'default' => __('Subscribe', 'xa-woocommerce-subscription'),
                'type' => 'text',
                'desc_tip' => true,
            ),
            array('type' => 'sectionend', 'id' => self::$option_prefix . '_button_text'),
            
            array(
                'name' => __('Other', 'xa-woocommerce-subscription'),
                'type' => 'title',
                'desc' => '',
                'id' => self::$option_prefix . '_other',
            ),
            
            array(
                'name' => __('Allow Mixed Checkout', 'xa-woocommerce-subscription'),
                'desc' => __('Allow subscription products and normal products to be purchased together.', 'xa-woocommerce-subscription'),
                'id' => self::$option_prefix . '_hf_allow_multiple_purchase',
                'default' => 'no',
                'type' => 'checkbox',
            ),
            array('type' => 'sectionend', 'id' => self::$option_prefix . '_other'),
                ));
    }
    
    public function save_subscription_settings() {

        if (empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_subscription_settings')) {
            return;
        }        
        woocommerce_update_options(self::get_settings());
    }


    public function hf_add_subscription_product_type($product_types) {

        $product_types['subscription'] = __('Simple subscription', 'xa-woocommerce-subscription');
        return $product_types;
    }

    public function add_data_store($data_store) {

        $data_store['subscription'] = 'HForce_Subscription_Data_Store';
        return $data_store;
    }

    public function subscription_product_fields() {

        include( HFORCE_SUBSCRIPTION_MAIN_PATH . 'admin/templates/subscription/product-price-fields.php' );
    }

    public static function append_numeral_suffix($number) {

        if (strlen($number) > 1 && 1 == substr($number, -2, 1)) {
            $number_string = sprintf(__('%sth', 'xa-woocommerce-subscription'), $number);
        } else {
            switch (substr($number, -1)) {
                case 1:
                    $number_string = sprintf(__('%sst', 'xa-woocommerce-subscription'), $number);
                    break;
                case 2:
                    $number_string = sprintf(__('%snd', 'xa-woocommerce-subscription'), $number);
                    break;
                case 3:
                    $number_string = sprintf(__('%srd', 'xa-woocommerce-subscription'), $number);
                    break;
                default:
                    $number_string = sprintf(__('%sth', 'xa-woocommerce-subscription'), $number);
                    break;
            }
        }
        return apply_filters('hf_alter_numeral_suffix', $number_string, $number);
    }

    public static function hforce_help_tooltip($tip_data, $allow_html = false) {

        if (function_exists('wc_help_tip')) {

            $help_tip = wc_help_tip($tip_data, $allow_html);
        } else {

            if ($allow_html) {
                $tip_data = wc_sanitize_tooltip($tip_data);
            } else {
                $tip_data = esc_attr($tip_data);
            }

            $help_tip = sprintf('<img class="help_tip" data-tip="%s" src="%s/assets/images/help.png" height="16" width="16" />', $tip_data, esc_url(WC()->plugin_url()));
        }

        return $help_tip;
    }

    public static function get_subscription_ranges($key, $callback = 'get_original_subscription_ranges', $params = array(), $expires = WEEK_IN_SECONDS) {
        
        $expires = absint($expires);
        $data = get_transient($key);
        if (false === $data && !empty($callback)) {
            $data = self::get_original_subscription_ranges();
            set_transient($key, $data, $expires);
        }
        return $data;
    }

    public function save_subscription_meta_data($post_id) {

        if (empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_subscription_meta') || false === self::is_subscription_product_save_request($post_id, apply_filters('hf_subscription_product_types', array(self::$name)))) {
            return;
        }

        $subscription_price = isset($_REQUEST['_hf_subscription_price']) ? wc_format_decimal($_REQUEST['_hf_subscription_price']) : '';
        $sale_price = wc_format_decimal($_REQUEST['_sale_price']);

        update_post_meta($post_id, '_hf_subscription_price', $subscription_price);
        update_post_meta($post_id, '_regular_price', $subscription_price);
        update_post_meta($post_id, '_sale_price', $sale_price);

        $date_from = ( isset($_POST['_sale_price_dates_from']) ) ? Hforce_Date_Time_Utils::date_to_time($_POST['_sale_price_dates_from']) : '';
        $date_to = ( isset($_POST['_sale_price_dates_to']) ) ? Hforce_Date_Time_Utils::date_to_time($_POST['_sale_price_dates_to']) : '';
        $now = gmdate('U');
        if (!empty($date_to) && empty($date_from)) {
            $date_from = $now;
        }
        update_post_meta($post_id, '_sale_price_dates_from', $date_from);
        update_post_meta($post_id, '_sale_price_dates_to', $date_to);

        if (!empty($sale_price) && ( ( empty($date_to) && empty($date_from) ) || ( $date_from < $now && ( empty($date_to) || $date_to > $now ) ) )) {
            $price = $sale_price;
        } else {
            $price = $subscription_price;
        }

        update_post_meta($post_id, '_price', stripslashes($price));


        $subscription_fields = array(
            '_subscription_period',
            '_subscription_period_interval',
            '_subscription_length',
        );

        foreach ($subscription_fields as $field_name) {
            if (isset($_REQUEST[$field_name])) {
                update_post_meta($post_id, $field_name, stripslashes($_REQUEST[$field_name]));
            }
        }

        self::$product_meta_saved = true;
    }


    private static function is_subscription_product_save_request($post_id, $product_types) {

        if (self::$product_meta_saved) {
            $is_subscription_product_save_request = false;
        } elseif (empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_subscription_meta')) {
            $is_subscription_product_save_request = false;
        } elseif (!isset($_POST['product-type']) || !in_array($_POST['product-type'], $product_types)) {
            $is_subscription_product_save_request = false;
        } elseif (empty($_POST['post_ID']) || $_POST['post_ID'] != $post_id) {
            $is_subscription_product_save_request = false;
        } else {
            $is_subscription_product_save_request = true;
        }

        return apply_filters('hf_admin_is_subscription_product_save_request', $is_subscription_product_save_request, $post_id, $product_types);
    }

    public static function is_woocommerce_prior_to($version) {

        $woocommerce_is_pre_version = (!defined('WC_VERSION') || version_compare(WC_VERSION, $version, '<')) ? true : false;
        return $woocommerce_is_pre_version;
    }

    public function register_subscription_order_types() {


        /*
         *      $args are passed to register_post_type, but there are a few specific to this function:
         *      - exclude_from_orders_screen (bool) Whether or not this order type also get shown in the main.
         *      -orders screen.
         *      - add_order_meta_boxes (bool) Whether or not the order type gets shop_order meta boxes.
         *      - exclude_from_order_count (bool) Whether or not this order type is excluded from counts.
         *      - exclude_from_order_views (bool) Whether or not this order type is visible by customers when.
         *      - viewing orders e.g. on the my account page.
         *      - exclude_from_order_reports (bool) Whether or not to exclude this type from core reports.
         *      - exclude_from_order_sales_reports (bool) Whether or not to exclude this type from core sales reports.
         */

        wc_register_order_type('hf_shop_subscription', apply_filters('woocommerce_register_post_type_hf_subscription', array(
            'labels' => array(
                'name' => __('WebToffee Subscriptions', 'xa-woocommerce-subscription'),
                'singular_name' => __('Subscription', 'xa-woocommerce-subscription'),
                'add_new' => __('Add Subscription', 'xa-woocommerce-subscription'),
                'add_new_item' => __('Add New Subscription', 'xa-woocommerce-subscription'),
                'edit' => __('Edit', 'xa-woocommerce-subscription'),
                'edit_item' => __('Edit Subscription', 'xa-woocommerce-subscription'),
                'new_item' => __('New Subscription', 'xa-woocommerce-subscription'),
                'view' => __('View Subscription', 'xa-woocommerce-subscription'),
                'view_item' => __('View Subscription', 'xa-woocommerce-subscription'),
                'search_items' => __('Search Subscriptions', 'xa-woocommerce-subscription'),
                'not_found' => __('No Subscriptions found', 'xa-woocommerce-subscription'),
                'not_found_in_trash' => __('No Subscriptions found in trash', 'xa-woocommerce-subscription'),
                'parent' => __('Parent Subscriptions', 'xa-woocommerce-subscription'),
                'menu_name' => __('WebToffee Subscriptions', 'xa-woocommerce-subscription'),
            ),
            'description' => __('This is where subscriptions are stored.', 'xa-woocommerce-subscription'),
            'public' => false,
            'show_ui' => true,
            'capability_type' => 'shop_order',
            'map_meta_cap' => true,
            'publicly_queryable' => false,            
            'show_in_menu' => current_user_can('manage_woocommerce') ? 'woocommerce' : true,
            'exclude_from_search' => true,
            'show_in_nav_menus' => false,
            'supports' => array('title', 'comments', 'custom-fields'),
            'has_archive' => true,
            'rewrite' => false,
            'query_var' => false,
            'hierarchical' => false,
            'exclude_from_orders_screen' => true,
            'add_order_meta_boxes' => true,
            'exclude_from_order_count' => true,
            'exclude_from_order_views' => true,
            'exclude_from_order_webhooks' => true,
            'exclude_from_order_reports' => true,
            'exclude_from_order_sales_reports' => true,
            'class_name' => 'HForce_Subscription',
                        )
                )
        );
    }

    public function register_subscription_status() {


        $subscription_statuses = hforce_get_subscription_statuses();
        $registered_statuses = apply_filters('hf_subscriptions_registered_statuses', array(
            'wc-active'         => _nx_noop('Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', '', 'xa-woocommerce-subscription'),
            'wc-expired'        => _nx_noop('Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', '', 'xa-woocommerce-subscription'),
            'wc-pending-cancel' => _nx_noop('Pending Cancellation <span class="count">(%s)</span>', 'Pending Cancellation <span class="count">(%s)</span>', '', 'xa-woocommerce-subscription'),
            'wc-pending'        => _nx_noop( 'Pending <span class="count">(%s)</span>','Pending <span class="count">(%s)</span>', '', 'xa-woocommerce-subscription' ),
            'wc-on-hold'        => _nx_noop( 'On hold <span class="count">(%s)</span>','On hold <span class="count">(%s)</span>','', 'xa-woocommerce-subscription' ),
            'wc-cancelled'      => _nx_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>','','xa-woocommerce-subscription' ),
        ));
        
        if (is_array($subscription_statuses) && is_array($registered_statuses)) {

            foreach ($registered_statuses as $status => $label_count) {                
                
                register_post_status($status, array(
                    'label' => $subscription_statuses[$status],
                    'public' => true,
                    'exclude_from_search' => false,
                    'show_in_admin_all_list' => true,
                    'show_in_admin_status_list' => true,
                    'label_count' => $label_count,
                ));
            }
        }
    }

    public static function get_original_subscription_ranges() {

        $intervals = array('day', 'week', 'month', 'year',);
        foreach ($intervals as $period) {

            $subscription_lengths = array(__('Never expire', 'xa-woocommerce-subscription'));

            switch ($period) {
                case 'day':
                    $subscription_lengths[] = __('1 day', 'xa-woocommerce-subscription');
                    $subscription_range = range(2, 90);
                    break;
                case 'week':
                    $subscription_lengths[] = __('1 week', 'xa-woocommerce-subscription');
                    $subscription_range = range(2, 52);
                    break;
                case 'month':
                    $subscription_lengths[] = __('1 month', 'xa-woocommerce-subscription');
                    $subscription_range = range(2, 24);
                    break;
                case 'year':
                    $subscription_lengths[] = __('1 year', 'xa-woocommerce-subscription');
                    $subscription_range = range(2, 5);
                    break;
            }

            foreach ($subscription_range as $number) {
                $subscription_range[$number] = Hforce_Date_Time_Utils::subscription_period_strings($number, $period);
            }

            $subscription_lengths += $subscription_range;
            $subscription_ranges[$period] = $subscription_lengths;
        }

        return $subscription_ranges;
    }

    public function hf_shop_subscription_columns($columns_exist) {


        $columns = array(
            'cb' => '<input type="checkbox" />',
            'order_title' => __('Subscription', 'xa-woocommerce-subscription'),
            'status' => __('Status', 'xa-woocommerce-subscription'),
            'order_items' => __('Items', 'xa-woocommerce-subscription'),
            'recurring_total' => __('Total', 'xa-woocommerce-subscription'),
            'start_date' => __('Start Date', 'xa-woocommerce-subscription'),
            'next_payment_date' => __('Next Payment', 'xa-woocommerce-subscription'),
            'orders' => __('Orders', 'xa-woocommerce-subscription'),
        );

        return $columns;
    }

    public function hf_shop_subscription_sortable_columns($columns) {

        $columns['order_title'] = 'ID';
        $columns['start_date'] = 'date';
        $columns['next_payment_date'] = 'next_payment_date';
        return $columns;
    }

    public function render_hf_shop_subscription_columns($column) {

        
        global $post, $the_subscription, $wp_list_table;

        
        if (empty($the_subscription) || $the_subscription->get_id() != $post->ID) {
            $the_subscription = hforce_get_subscription($post->ID);
        }

        
        $subscription_status = $the_subscription->get_status();
        
        $column_content = '';

        switch ($column) {
            

            case 'order_title' :

                $customer_tip = '';

                if ($address = $the_subscription->get_formatted_billing_address()) {
                    $customer_tip .= __('Billing:', 'xa-woocommerce-subscription') . ' ' . esc_html($address);
                }

                if ($the_subscription->get_billing_email()) {
                    $customer_tip .= '<br/><br/>' . sprintf(__('Email: %s', 'xa-woocommerce-subscription'), esc_attr($the_subscription->get_billing_email()));
                }

                if ($the_subscription->get_billing_phone()) {
                    $customer_tip .= '<br/><br/>' . sprintf(__('Tel: %s', 'xa-woocommerce-subscription'), esc_html($the_subscription->get_billing_phone()));
                }

                if (!empty($customer_tip)) {
                    echo '<div class="tips" data-tip="' . esc_attr($customer_tip) . '">';
                }

                $username = '';

                if ($the_subscription->get_user_id() && ( false !== ( $user_info = get_userdata($the_subscription->get_user_id()) ) )) {

                    $username = '<a href="user-edit.php?user_id=' . absint($user_info->ID) . '">';

                    if ($the_subscription->get_billing_first_name() || $the_subscription->get_billing_last_name()) {
                        $username .= esc_html(ucfirst($the_subscription->get_billing_first_name()) . ' ' . ucfirst($the_subscription->get_billing_last_name()));
                    } elseif ($user_info->first_name || $user_info->last_name) {
                        $username .= esc_html(ucfirst($user_info->first_name) . ' ' . ucfirst($user_info->last_name));
                    } else {
                        $username .= esc_html(ucfirst($user_info->display_name));
                    }

                    $username .= '</a>';
                } elseif ($the_subscription->get_billing_first_name() || $the_subscription->get_billing_last_name()) {
                    $username = trim($the_subscription->get_billing_first_name() . ' ' . $the_subscription->get_billing_last_name());
                }
                $column_content = sprintf(__('%1$s#%2$s%3$s for %4$s', 'xa-woocommerce-subscription'), '<a href="' . esc_url(admin_url('post.php?post=' . absint($post->ID) . '&action=edit')) . '" class="subscription-view">', '<strong>' . esc_attr($the_subscription->get_order_number()) . '</strong>', '</a>', $username);
                $column_content .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details', 'xa-woocommerce-subscription') . '</span></button>';

                break;
                
            case 'status' :

                $column_content = sprintf('<mark class="%s"><span>%s</span></mark>', sanitize_title($subscription_status), hforce_get_subscription_status_name($subscription_status));

                $post_type_object = get_post_type_object($post->post_type);

                $actions = array();

                $action_url = add_query_arg(
                        array(
                            'post' => $the_subscription->get_id(),
                            '_wpnonce' => wp_create_nonce('bulk-posts'),
                        )
                );

                if (isset($_REQUEST['status'])) {
                    $action_url = add_query_arg(array('status' => $_REQUEST['status']), $action_url);
                }

                $all_statuses = array(
                    'active' => __('Reactivate', 'xa-woocommerce-subscription'),
                    'on-hold' => __('Suspend', 'xa-woocommerce-subscription'),
                    'cancelled' => __('Cancel', 'xa-woocommerce-subscription'),
                    'trash' => __('Trash', 'xa-woocommerce-subscription'),
                    'deleted' => __('Delete Permanently', 'xa-woocommerce-subscription'),
                );
                
                $suspend_veno = TRUE;
                $pypal_msg = '';
                $subscription_payment_method = $the_subscription->get_payment_method();
                if (!empty($subscription_payment_method) && 'paypal' == $subscription_payment_method) {
                    $pypal_msg = __(" ( Canceling here will not cancel from PayPal. Need to cancel by customer from PayPal dashboard under pre-approved payments ) ", 'xa-woocommerce-subscription');
                    $suspend_veno = FALSE;
                }
                
                if(!$suspend_veno){
                    unset($all_statuses['on-hold']);
                    $all_statuses['cancelled'] = __('Cancel', self::TEXT_DOMAIN).$pypal_msg;
                }
                
                
                foreach ($all_statuses as $status => $label) {

                    if ($the_subscription->can_be_updated_to($status)) {

                        if (in_array($status, array('trash', 'deleted'))) {

                            if (current_user_can($post_type_object->cap->delete_post, $post->ID)) {

                                if ('trash' == $post->post_status) {
                                    $actions['untrash'] = '<a title="' . esc_attr(__('Restore this item from the Trash', 'xa-woocommerce-subscription')) . '" href="' . wp_nonce_url(admin_url(sprintf($post_type_object->_edit_link . '&amp;action=untrash', $post->ID)), 'untrash-post_' . $post->ID) . '">' . __('Restore', 'xa-woocommerce-subscription') . '</a>';
                                } elseif (EMPTY_TRASH_DAYS) {
                                    $actions['trash'] = '<a class="submitdelete" title="' . esc_attr(__('Move this item to the Trash', 'xa-woocommerce-subscription')) . '" href="' . get_delete_post_link($post->ID) . '">' . __('Trash', 'xa-woocommerce-subscription') . '</a>';
                                }

                                if ('trash' == $post->post_status || !EMPTY_TRASH_DAYS) {
                                    $actions['delete'] = '<a class="submitdelete" title="' . esc_attr(__('Delete this item permanently', 'xa-woocommerce-subscription')) . '" href="' . get_delete_post_link($post->ID, '', true) . '">' . __('Delete Permanently', 'xa-woocommerce-subscription') . '</a>';
                                }
                            }
                        } else {

                            if ('pending-cancel' === $subscription_status) {
                                $label = __('Cancel Now', 'xa-woocommerce-subscription');
                            }

                            $actions[$status] = sprintf('<a href="%s">%s</a>', add_query_arg('action', $status, $action_url), $label);
                        }
                    }
                }

                if ('pending' === $subscription_status) {
                    unset($actions['active']);
                    unset($actions['trash']);
                } elseif (!in_array($subscription_status, array('cancelled', 'pending-cancel', 'expired', 'suspended'))) {
                    unset($actions['trash']);
                }

                $actions = apply_filters('hf_subscription_list_table_actions', $actions, $the_subscription);
                
                $column_content .= $wp_list_table->row_actions($actions);
                $column_content = apply_filters('hf_subscription_list_table_column_status_content', $column_content, $the_subscription, $actions);
                break;
                
            case 'order_items' :

                $subscription_items = $the_subscription->get_items();
                switch (count($subscription_items)) {
                    case 0 :
                        $column_content .= '&ndash;';
                        break;
                    case 1 :
                        foreach ($subscription_items as $item) {
                            $column_content .= self::get_item_display($item, $the_subscription);
                        }
                        break;
                    default :
                        $column_content .= '<a href="#" class="show_order_items">' . esc_html(apply_filters('woocommerce_admin_order_item_count', sprintf(_n('%d item', '%d items', $the_subscription->get_item_count(), 'xa-woocommerce-subscription'), $the_subscription->get_item_count()), $the_subscription)) . '</a>';
                        $column_content .= '<table class="order_items" cellspacing="0">';

                        foreach ($subscription_items as $item) {
                            $column_content .= self::get_item_display($item, $the_subscription, 'row');
                        }

                        $column_content .= '</table>';
                        break;
                }
                break;

            case 'recurring_total' :
                $column_content .= esc_html(strip_tags($the_subscription->get_formatted_order_total()));
                $column_content .= '<small class="meta">' . esc_html(sprintf(__('Via %s', 'xa-woocommerce-subscription'), $the_subscription->get_payment_method_to_display())) . '</small>';
                break;

            case 'start_date':
            case 'next_payment_date':
            
                $date_type_map = array('start_date' => 'date_created', 'last_payment_date' => 'last_order_date_created');
                $date_type = array_key_exists($column, $date_type_map) ? $date_type_map[$column] : $column;

                //var_dump($the_subscription->get_date_to_display($date_type));
                if (0 == $the_subscription->get_time($date_type, 'gmt')) {
                    $column_content .= '-';
                } else {
                    $column_content .= sprintf('<time class="%s" title="%s">%s</time>', esc_attr($column), esc_attr(date(__('Y/m/d g:i:s A', 'xa-woocommerce-subscription'), $the_subscription->get_time($date_type, 'site'))), esc_html($the_subscription->get_date_to_display($date_type)));

                    if ('next_payment_date' == $column && $the_subscription->payment_method_supports('gateway_scheduled_payments') && !$the_subscription->is_manual() && $the_subscription->has_status('active')) {
                        $column_content .= '<div class="woocommerce-help-tip" data-tip="' . esc_attr__('This date should be treated as an estimate only. The payment gateway for this subscription controls when payments are processed.', 'xa-woocommerce-subscription') . '"></div>';
                    }
                }

                $column_content = $column_content;
                break;

            case 'orders' :
                $column_content .= $this->get_related_orders_link($the_subscription);
                break;
        }

        echo wp_kses(apply_filters('hf_subscription_list_table_column_content', $column_content, $the_subscription, $column), array('a' => array('class' => array(), 'href' => array(), 'data-tip' => array(), 'title' => array()), 'time' => array('class' => array(), 'title' => array()), 'mark' => array('class' => array(), 'data-tip' => array()), 'small' => array('class' => array()), 'table' => array('class' => array(), 'cellspacing' => array(), 'cellpadding' => array()), 'tr' => array('class' => array()), 'td' => array('class' => array()), 'div' => array('class' => array(), 'data-tip' => array()), 'br' => array(), 'strong' => array(), 'span' => array('class' => array(), 'data-tip' => array()), 'p' => array('class' => array()), 'button' => array('type' => array(), 'class' => array())));
    }

    public function get_related_orders_link($the_subscription) {

        $related_orders_count = count($the_subscription->get_related_orders());
        if ($related_orders_count) {
            return sprintf(
                    '<a href="%s">%s</a>', admin_url('edit.php?post_status=all&post_type=shop_order&_subscription_related_orders=' . absint($the_subscription->get_id())), $related_orders_count
            );
        } else {
            return __('No Related Orders', 'xa-woocommerce-subscription');
        }
    }
    
    //get related orders
    public function filtered_orders($where) {
        
        global $typenow, $wpdb;

        if (is_admin() && 'shop_order' == $typenow) {
            $related_orders = array();
            if (isset($_GET['_subscription_related_orders']) && $_GET['_subscription_related_orders'] > 0) {

                $subscription_id = absint($_GET['_subscription_related_orders']);
                $subscription = hforce_get_subscription($subscription_id);

                if (!hforce_is_subscription($subscription)) {
                    hf_add_admin_notice(sprintf(__('Could not find subscription with ID #%d.', 'xa-woocommerce-subscription'), $subscription_id), 'error');
                    $where .= " AND {$wpdb->posts}.ID = 0";
                } else {                   
                    $where .= sprintf(" AND {$wpdb->posts}.ID IN (%s)", implode(',', array_map('absint', array_unique($subscription->get_related_orders('ids')))));
                }
            }
        }
        return $where;
    }
    
    // edit

    
    public function enable_checkout_registration($checkout = '') {

        if (self::whether_cart_contains_subscription() and ! is_user_logged_in()) {
            if (true === $checkout->enable_guest_checkout) {
                $checkout->enable_guest_checkout = false;
                self::$guest_checkout_option_changed = true;

                $checkout->must_create_account = true;
            }
        }
    }

    public static function whether_cart_contains_subscription() {

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

    
    
    public static function is_gateway_supports($property) {

        // $supports_flag can be 'multiple_subscriptions' , 'subscriptions'
        if (!isset(self::$is_gateway_supports[$property])) {

            self::$is_gateway_supports[$property] = false;

            foreach (WC()->payment_gateways->get_available_payment_gateways() as $gateway) {
                if ($gateway->supports($property)) {
                    self::$is_gateway_supports[$property] = true;
                    break;
                }
            }
        }
        return self::$is_gateway_supports[$property];
    }
    
    
    
    public function disable_checkout_registration($checkout = '') {

        if (self::$guest_checkout_option_changed) {
            $checkout->enable_guest_checkout = true;
            if (!is_user_logged_in()) {
                $checkout->must_create_account = false;
            }
        }
    }

    public function disable_guest_checkout($params) {

        if (self::whether_cart_contains_subscription() and ! is_user_logged_in() and isset($params['option_guest_checkout']) and 'yes' == $params['option_guest_checkout']) {
            $params['option_guest_checkout'] = 'no';
        }

        return $params;
    }

    public function register_on_subscription_checkout($params) {

        if (self::whether_cart_contains_subscription() && !is_user_logged_in()) {
            $_POST['createaccount'] = 1;
        }
    }

    public function make_checkout_form_account_fields_required($checkout_fields) {

        if (self::whether_cart_contains_subscription() and ! is_user_logged_in()) {

            $account_fields = array(
                'account_username',
                'account_password',
                'account_password-2',
            );

            foreach ($account_fields as $account_field) {
                if (isset($checkout_fields['account'][$account_field])) {
                    $checkout_fields['account'][$account_field]['required'] = true;
                }
            }
        }

        return $checkout_fields;
    }

    public function maybe_remove_cart_items($cart_item_key) {


        if (isset(WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['subscription_id'])) {

            $removed_item_count = 0;
            $subscription_id = WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['subscription_id'];

            if(!empty(WC()->cart->cart_contents)){
            foreach (WC()->cart->cart_contents as $key => $cart_item) {

                if (isset($cart_item[$this->cart_item_key]) && $subscription_id == $cart_item[$this->cart_item_key]['subscription_id']) {
                    WC()->cart->removed_cart_contents[$key] = WC()->cart->cart_contents[$key];
                    unset(WC()->cart->cart_contents[$key]);
                    $removed_item_count++;
                }
            }
            }
            unset(WC()->session->order_awaiting_payment);
            $this->clear_coupons();

            if ($removed_item_count > 1 && 'woocommerce_before_cart_item_quantity_zero' == current_filter()) {
                wc_add_notice(esc_html__('All subscription items have been removed from the cart.', 'xa-woocommerce-subscription'), 'notice');
            }
        }
    }

    public static function process_checkout($order_id, $checkout_data) {


        if (!self::whether_cart_contains_subscription()) {
            return;
        }
        $order = new WC_Order($order_id);
        $subscriptions = array();

        $subscriptions = get_subscriptions_by_order(hforce_get_objects_property($order, 'id'), array('order_type' => 'parent'));

        if (!empty($subscriptions)) {
            remove_action('before_delete_post', 'HForce_Woocommerce_Subscription_Admin::maybe_cancel_subscription');
            foreach ($subscriptions as $subscription) {
                wp_delete_post($subscription->get_id());
            }
            add_action('before_delete_post', 'HForce_Woocommerce_Subscription_Admin::maybe_cancel_subscription');
        }

        HForce_Subscription_Cart::set_global_recurring_shipping_packages();

        foreach (WC()->cart->recurring_carts as $recurring_cart) {

            $subscription = self::create_subscription($order, $recurring_cart, $checkout_data);

            if (is_wp_error($subscription)) {
                throw new Exception($subscription->get_error_message());
            }

            do_action('woocommerce_checkout_subscription_created', $subscription, $order, $recurring_cart);
        }

        do_action('subscriptions_created_for_order', $order);
    }

    public static function maybe_cancel_subscription($post_id) {

        if ('hf_shop_subscription' == get_post_type($post_id) && 'auto-draft' !== get_post_status($post_id)) {
            $subscription = hforce_get_subscription($post_id);
            if ($subscription->can_be_updated_to('cancelled')) {
                $subscription->update_status('cancelled');
            }
        }
    }

    public static function create_subscription($order, $cart, $post_data) {
        
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            $variation_id = hf_cart_pluck($cart, 'variation_id');
            $product_id = empty($variation_id) ? hf_cart_pluck($cart, 'product_id') : $variation_id;

            $subscription_details = array(
                'start_date' => $cart->start_date,
                'order_id' => hforce_get_objects_property($order, 'id'),
                'customer_id' => $order->get_user_id(),
                'billing_period' => hf_cart_pluck($cart, 'subscription_period'),
                'billing_interval' => hf_cart_pluck($cart, 'subscription_period_interval'),
                'customer_note' => hforce_get_objects_property($order, 'customer_note'),
            );
            
            $subscription = hf_create_subscription($subscription_details);

            if (is_wp_error($subscription)) {
                throw new Exception($subscription->get_error_message());
            }

            $subscription = self::set_order_address($order, $subscription);

            $subscription->update_dates(array( 'next_payment' => $cart->next_payment_date,  'end' => $cart->end_date, ));

            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $order_payment_method = hforce_get_objects_property($order, 'payment_method');

            if ($cart->needs_payment() && isset($available_gateways[$order_payment_method])) {
                $subscription->set_payment_method($available_gateways[$order_payment_method]);
            }

            if (!$cart->needs_payment()) {
                $subscription->set_requires_manual_renewal(true);
            } elseif (!isset($available_gateways[$order_payment_method]) || !$available_gateways[$order_payment_method]->supports('subscriptions')) {
                $subscription->set_requires_manual_renewal(true);
            }

            update_order_meta($order, $subscription, 'subscription');

            if (is_callable(array(WC()->checkout, 'create_order_line_items'))) {
                WC()->checkout->create_order_line_items($subscription, $cart);
            } else {
                foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                    $item_id = self::add_cart_item($subscription, $cart_item, $cart_item_key);
                }
            }

            if (is_callable(array(WC()->checkout, 'create_order_fee_lines'))) {
                WC()->checkout->create_order_fee_lines($subscription, $cart);
            } else {
                foreach ($cart->get_fees() as $fee_key => $fee) {
                    $item_id = $subscription->add_fee($fee);

                    if (!$item_id) {
                        throw new Exception(sprintf(__('Error %d: Unable to create subscription. Please try again.', 'xa-woocommerce-subscription'), 403));
                    }

                    do_action('woocommerce_add_order_fee_meta', $subscription->get_id(), $item_id, $fee, $fee_key);
                }
            }

            self::add_shipping($subscription, $cart);

            if (is_callable(array(WC()->checkout, 'create_order_tax_lines'))) {
                WC()->checkout->create_order_tax_lines($subscription, $cart);
            } else {
                foreach (array_keys($cart->taxes + $cart->shipping_taxes) as $tax_rate_id) {
                    if ($tax_rate_id && !$subscription->add_tax($tax_rate_id, $cart->get_tax_amount($tax_rate_id), $cart->get_shipping_tax_amount($tax_rate_id)) && apply_filters('woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated') !== $tax_rate_id) {
                        throw new Exception(sprintf(__('Error %d: Unable to add tax to subscription. Please try again.', 'xa-woocommerce-subscription'), 405));
                    }
                }
            }

            if (is_callable(array(WC()->checkout, 'create_order_coupon_lines'))) {
                WC()->checkout->create_order_coupon_lines($subscription, $cart);
            } else {
                foreach ($cart->get_coupons() as $code => $coupon) {
                    if (!$subscription->add_coupon($code, $cart->get_coupon_discount_amount($code), $cart->get_coupon_discount_tax_amount($code))) {
                        throw new Exception(sprintf(__('Error %d: Unable to create order. Please try again.', 'xa-woocommerce-subscription'), 406));
                    }
                }
            }

            $subscription->set_shipping_total($cart->shipping_total);
            $subscription->set_discount_total($cart->get_cart_discount_total());
            $subscription->set_discount_tax($cart->get_cart_discount_tax_total());
            $subscription->set_cart_tax($cart->tax_total);
            $subscription->set_shipping_tax($cart->shipping_tax_total);
            $subscription->set_total($cart->total);

            do_action('woocommerce_checkout_create_subscription', $subscription, $post_data);
            $subscription->save();
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('checkout-error', $e->getMessage());
        }

        return $subscription;
    }

    public static function add_shipping($subscription, $cart) {

        HForce_Subscription_Cart::set_calculation_type('recurring_total');

        foreach ($cart->get_shipping_packages() as $package_index => $base_package) {

            $package = HForce_Subscription_Cart::get_calculated_shipping_for_package($base_package);
            $recurring_shipping_package_key = $cart->recurring_cart_key . '_' . $package_index;
            $shipping_method_id = isset(WC()->checkout()->shipping_methods[$package_index]) ? WC()->checkout()->shipping_methods[$package_index] : '';

            if (isset(WC()->checkout()->shipping_methods[$recurring_shipping_package_key])) {
                $shipping_method_id = WC()->checkout()->shipping_methods[$recurring_shipping_package_key];
                $package_key = $recurring_shipping_package_key;
            } else {
                $package_key = $package_index;
            }

            if (isset($package['rates'][$shipping_method_id])) {

                if (self::is_woocommerce_prior_to('3.0')) {

                    $item_id = $subscription->add_shipping($package['rates'][$shipping_method_id]);

                    do_action('woocommerce_add_shipping_order_item', $subscription->get_id(), $item_id, $package_key);
                    do_action('hf_subscription_add_recurring_shipping_order_item', $subscription->get_id(), $item_id, $package_key);
                } else {

                    $shipping_rate = $package['rates'][$shipping_method_id];
                    $item = new WC_Order_Item_Shipping();
                    $item->legacy_package_key = $package_key;
                    $item->set_props(array(
                        'method_title' => $shipping_rate->label,
                        'method_id' => $shipping_rate->id,
                        'total' => wc_format_decimal($shipping_rate->cost),
                        'taxes' => array('total' => $shipping_rate->taxes),
                        'order_id' => $subscription->get_id(),
                    ));

                    foreach ($shipping_rate->get_meta_data() as $key => $value) {
                        $item->add_meta_data($key, $value, true);
                    }

                    $subscription->add_item($item);

                    $item->save();
                    wc_do_deprecated_action('hf_subscription_add_recurring_shipping_order_item', array($subscription->get_id(), $item->get_id(), $package_key), '2.2.0', 'CRUD and woocommerce_checkout_create_subscription_shipping_item action instead');

                    do_action('woocommerce_checkout_create_order_shipping_item', $item, $package_key, $package);
                    do_action('woocommerce_checkout_create_subscription_shipping_item', $item, $package_key, $package);
                }
            }
        }

        HForce_Subscription_Cart::set_calculation_type('none');
    }

    public static function set_order_address($from_order, $to_order, $address_type = 'all') {

        if (in_array($address_type, array('shipping', 'all'))) { // set shipping address
            $to_order->set_address(array(
                'first_name' => hforce_get_objects_property($from_order, 'shipping_first_name'),
                'last_name' => hforce_get_objects_property($from_order, 'shipping_last_name'),
                'company' => hforce_get_objects_property($from_order, 'shipping_company'),
                'address_1' => hforce_get_objects_property($from_order, 'shipping_address_1'),
                'address_2' => hforce_get_objects_property($from_order, 'shipping_address_2'),
                'city' => hforce_get_objects_property($from_order, 'shipping_city'),
                'state' => hforce_get_objects_property($from_order, 'shipping_state'),
                'postcode' => hforce_get_objects_property($from_order, 'shipping_postcode'),
                'country' => hforce_get_objects_property($from_order, 'shipping_country'),
                    ), 'shipping');
        }

        if (in_array($address_type, array('billing', 'all'))) { // set billing address
            $to_order->set_address(array(
                'first_name' => hforce_get_objects_property($from_order, 'billing_first_name'),
                'last_name' => hforce_get_objects_property($from_order, 'billing_last_name'),
                'company' => hforce_get_objects_property($from_order, 'billing_company'),
                'address_1' => hforce_get_objects_property($from_order, 'billing_address_1'),
                'address_2' => hforce_get_objects_property($from_order, 'billing_address_2'),
                'city' => hforce_get_objects_property($from_order, 'billing_city'),
                'state' => hforce_get_objects_property($from_order, 'billing_state'),
                'postcode' => hforce_get_objects_property($from_order, 'billing_postcode'),
                'country' => hforce_get_objects_property($from_order, 'billing_country'),
                'email' => hforce_get_objects_property($from_order, 'billing_email'),
                'phone' => hforce_get_objects_property($from_order, 'billing_phone'),
                    ), 'billing');
        }

        return apply_filters('hf_subscription_set_order_address', $to_order, $from_order, $address_type);
    }

    public static function get_item_display($item, $the_subscription, $element = 'div') {

        $product = apply_filters('woocommerce_order_item_product', $the_subscription->get_product_from_item($item), $item);
        $item_meta_html = self::get_item_meta_html($item, $product);

        if ('div' === $element) {
            $item_html = self::get_item_display_div($item, self::get_item_name_html($item, $product), $item_meta_html);
        } else {
            $item_html = self::get_item_display_row($item, self::get_item_name_html($item, $product, 0), $item_meta_html);
        }

        return $item_html;
    }

    public static function get_item_display_row($item, $item_name, $item_meta_html) {

        ob_start();
        ?>
        <tr class="<?php echo esc_attr(apply_filters('woocommerce_admin_order_item_class', '', $item)); ?>">
            <td class="qty"><?php echo absint($item['qty']); ?></td>
            <td class="name">
        <?php
        echo wp_kses($item_name, array('a' => array('href' => array())));

        if ($item_meta_html) {
            echo hforce_help_tooltip($item_meta_html);
        }
        ?>
            </td>
        </tr>
        <?php
        $item_html = ob_get_clean();

        return $item_html;
    }

    public static function get_item_meta_html($item, $product) {

        if (self::is_woocommerce_prior_to('3.0')) {
            $item_meta = self::xa_get_order_item_meta($item, $product);
            $item_meta_html = $item_meta->display(true, true);
        } else {
            $item_meta_html = wc_display_item_meta($item, array(
                'before' => '',
                'after' => '',
                'separator' => '\n',
                'echo' => false,
                    ));
        }

        return $item_meta_html;
    }

    public static function xa_get_order_item_meta($item, $product) {
        return new WC_Order_Item_Meta($item, $product);
    }

    
    public static function get_item_name_html($item, $product, $include_quantity = 1) {

        $item_quantity = absint($item['qty']);
        $item_name = '';
        if (wc_product_sku_enabled() && $product && $product->get_sku()) {
            $item_name .= $product->get_sku() . ' - ';
        }
        $item_name .= apply_filters('woocommerce_order_item_name', $item['name'], $item, false);
        $item_name = esc_html($item_name);
        if (1 === $include_quantity && $item_quantity > 1) {
            $item_name = sprintf('%s &times; %s', absint($item_quantity), $item_name);
        }
        if ($product) {
            $item_name = sprintf('<a href="%s">%s</a>', get_edit_post_link(( $product->is_type('variation') ) ? hforce_get_objects_property($product, 'parent_id') : $product->get_id()), $item_name);
        }

        return $item_name;
    }

    public static function get_item_display_div($item, $item_name_html, $item_meta_html) {

        $item_html = '<div class="order-item">';
        $item_html .= wp_kses($item_name_html, array('a' => array('href' => array())));

        if ($item_meta_html) {
            $item_html .= hforce_help_tooltip($item_meta_html);
        }

        $item_html .= '</div>';

        return $item_html;
    }
    
    public function add_subscription_actions( $actions ) {
            
        
		global $theorder;
		if ( hforce_is_subscription( $theorder ) && ! $theorder->has_status( hforce_get_subscription_ended_statuses() ) ) {

			if ( $theorder->payment_method_supports( 'subscription_date_changes' ) && $theorder->has_status( 'active' ) ) {
				$actions['hf_process_renewal'] = __( 'Process renewal', 'xa-woocommerce-subscription' );
			}

			$actions['hf_create_pending_renewal'] = __( 'Create pending renewal order', 'xa-woocommerce-subscription' );

		} else if ( self::can_retry_renewal_order( $theorder ) ) {
			$actions['hf_retry_renewal_payment'] = __( 'Retry Renewal Payment', 'xa-woocommerce-subscription' );
		}
                
		return $actions;
	}
        
        public function process_renewal_action_request($subscription){
            do_action( 'woocommerce_scheduled_subscription_payment', $subscription->get_id() );
	    $subscription->add_order_note( __( 'Process renewal requested by admin.', 'xa-woocommerce-subscription' ), false, true );
        }
        
        public function create_pending_renewal_subscription_order($subscription) {

            $subscription->update_status('on-hold');

            $renewal_order = hf_create_renewal_order($subscription);

            if (!$subscription->is_manual()) {
                $renewal_order->set_payment_method(wc_get_payment_gateway_by_order($subscription));
            }

            $subscription->add_order_note(__('Create pending renewal order requested by admin action.', 'xa-woocommerce-subscriptions'), false, true);
        }
        
        public function process_renewal_action($subscription_id) {
        
        
            
        $subscription = hforce_get_subscription($subscription_id);
        if (empty($subscription) || !$subscription->has_status('active')) {
            return false;
        }

        if (0 == $subscription->get_total() || $subscription->is_manual() || '' == $subscription->get_payment_method() || !$subscription->payment_method_supports('gateway_scheduled_payments')) {

            $subscription->update_status('on-hold', __('Subscription renewal payment due:', 'xa-woocommerce-subscription'));
            $renewal_order = hf_create_renewal_order($subscription);

            if (is_wp_error($renewal_order)) {
                $renewal_order = hf_create_renewal_order($subscription);

                if (is_wp_error($renewal_order)) {
                    throw new Exception(__('Error: Unable to create renewal order from scheduled payment. Please try again.', 'xa-woocommerce-subscription'));
                }
            }

            if (0 == $renewal_order->get_total()) {

                $renewal_order->payment_complete();
                $subscription->update_status('active');
            } else {

                if ($subscription->is_manual()) {
                    do_action('woocommerce_generated_manual_renewal_order', hforce_get_objects_property($renewal_order, 'id'));
                } else {
                    $renewal_order->set_payment_method(wc_get_payment_gateway_by_order($subscription));

                    if (is_callable(array($renewal_order, 'save'))) {
                        $renewal_order->save();
                    }
                }
            }
        }
    }

    private static function can_retry_renewal_order( $order ) {

		$can_retry = false;

		if ( hf_order_contains_renewal( $order ) && $order->needs_payment() && '' != hforce_get_objects_property( $order, 'payment_method' ) ) {
			$supports_date_changes          = false;
			$order_payment_gateway          = wc_get_payment_gateway_by_order( $order );
			$order_payment_gateway_supports = ( isset( $order_payment_gateway->id ) ) ? has_action( 'woocommerce_scheduled_subscription_payment_' . $order_payment_gateway->id ) : false;

			foreach ( hforce_get_subscriptions_for_renewal_order( $order ) as $subscription ) {
				$supports_date_changes = $subscription->payment_method_supports( 'subscription_date_changes' );
				$is_automatic = ! $subscription->is_manual();
				break;
			}
			$can_retry = $order_payment_gateway_supports && $supports_date_changes && $is_automatic;
		}
		return $can_retry;
	}
        
        private static function can_retry_renewal_payment( $order ) {

           
		$can_be_retried = false;

		if ( hf_order_contains_renewal( $order ) && $order->needs_payment() && '' != hforce_get_objects_property( $order, 'payment_method' ) ) {
			$supports_date_changes          = false;
			$order_payment_gateway          = wc_get_payment_gateway_by_order( $order );
			$order_payment_gateway_supports = ( isset( $order_payment_gateway->id ) ) ? has_action( 'woocommerce_scheduled_subscription_payment_' . $order_payment_gateway->id ) : false;

			foreach ( hforce_get_subscriptions_for_renewal_order( $order ) as $subscription ) {
				$supports_date_changes = $subscription->payment_method_supports( 'subscription_date_changes' );
				$is_automatic = ! $subscription->is_manual();
				break;
			}
			$can_be_retried = $order_payment_gateway_supports && $supports_date_changes && $is_automatic;
		}
		return $can_be_retried;
	}

      public static function update_subscription_payment($order_id, $old_order_status, $new_order_status) {

          
        if (is_order_contains_subscription($order_id, array('parent', 'renewal'))) {

            $subscriptions = get_subscriptions_by_order($order_id, array('order_type' => array('parent', 'renewal')));
            $was_activated = false;
            $order = wc_get_order($order_id);
            $order_completed = in_array($new_order_status, array(apply_filters('woocommerce_payment_complete_order_status', 'processing', $order_id), 'processing', 'completed')) && in_array($old_order_status, apply_filters('woocommerce_valid_order_statuses_for_payment', array('pending', 'on-hold', 'failed'), $order));
            
            foreach ($subscriptions as $subscription) {

                if ($order_completed && !$subscription->has_status(hforce_get_subscription_ended_statuses()) && !$subscription->has_status('active')) {

                    $new_start_date_offset = current_time('timestamp', true) - $subscription->get_time('date_created');
                    //var_dump($new_start_date_offset);exit;

                    if ($new_start_date_offset > HOUR_IN_SECONDS) {

                        $dates = array('date_created' => current_time('mysql', true));

                        
                            foreach (array('next_payment', 'end') as $date_type) {
                                if (0 != $subscription->get_time($date_type)) {
                                    $dates[$date_type] = gmdate('Y-m-d H:i:s', $subscription->get_time($date_type) + $new_start_date_offset);
                                }
                            }
                        

                        $subscription->update_dates($dates);
                    }

                    $subscription->payment_complete();
                    $was_activated = true;
                } elseif ('failed' == $new_order_status) {
                    $subscription->payment_failed();
                }
            }
            
            if ($was_activated) {
                do_action('subscriptions_activated_for_order', $order_id);
            }
        }
    }
    
    public function process_bulk_actions() {

		if ( ! isset( $_REQUEST['post_type'] ) || 'hf_shop_subscription' !== $_REQUEST['post_type'] || ! isset( $_REQUEST['post'] ) ) {
			return;
		}
		
                
                $action = $this->get_current_bulk_action();
                
		switch ( $action ) {
			case 'active':
			case 'on-hold':
			case 'cancelled' :
				$new_status = $action;
				break;
			default:
				return;
		}

		$report_action = 'marked_' . $new_status;
		$updated_count = 0;
		$subscription_ids = array_map( 'absint', (array) $_REQUEST['post'] );
		$res_args = array(
			'post_type'    => 'hf_shop_subscription',
			$report_action => true,
			'ids'          => join( ',', $subscription_ids ),
			'error_count'  => 0,
		);

		foreach ( $subscription_ids as $subscription_id ) {
			$subscription = hforce_get_subscription( $subscription_id );
			$order_note   = __( 'Subscription status changed by bulk edit:', 'xa-woocommerce-subscription' );

			try {

				if ( 'cancelled' == $action ) {
					$subscription->cancel_order( $order_note );
				} else {
					$subscription->update_status( $new_status, $order_note, true );
				}

				switch ( $action ) {
					case 'active' :
					case 'on-hold' :
					case 'cancelled' :
					case 'trash' :
						do_action( 'woocommerce_admin_changed_subscription_to_' . $action, $subscription_id );
						break;
				}

				$updated_count++;

			} catch ( Exception $e ) {
				$res_args['error'] = urlencode( $e->getMessage() );
				$res_args['error_count']++;
			}
		}

		$res_args['changed'] = $updated_count;
		$response = add_query_arg( $res_args, wp_get_referer() ? wp_get_referer() : '' );
		wp_safe_redirect( esc_url_raw( $response ) );

		exit();
	}
        
        public function get_current_bulk_action(){
            
            $action = '';
            if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
			$action = $_REQUEST['action'];
		} else if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
			$action = $_REQUEST['action2'];
		}
                return $action;
        }
        
        
    public function subscription_relationship_column($columns) {

        $column_header = '<span class="">' . __('Subscription', 'xa-woocommerce-subscription') . '</span>';

        if (array_key_exists('shipping_address', $columns)) {
            $new_array = array();
            foreach ($columns as $key => $value) {
                $new_array[$key] = $value;
                if ($key === 'shipping_address') {
                    $new_array['subscription_relationship'] = $column_header;
                }
            }
            return $new_array;
        } else {
            return $columns;
        }
        
    }

    public  function subscription_relationship_column_content($column) {
        global $post;

        if ('subscription_relationship' == $column) {
            if (is_order_contains_subscription($post->ID, 'renewal')) {
                echo '<span class="subscription_renewal_order tips" data-tip="' . __('Renewal Order', 'xa-woocommerce-subscription') . '"></span>';
            } elseif (is_order_contains_subscription($post->ID, 'resubscribe')) {
                echo '<span class="subscription_resubscribe_order tips" data-tip="' . __('Resubscribe Order', 'xa-woocommerce-subscription') . '"></span>';
            } elseif (is_order_contains_subscription($post->ID, 'parent')) {
                echo '<span class="subscription_parent_order tips" data-tip="' . __('Parent Order', 'xa-woocommerce-subscription') . '"></span>';
            } else {
                echo '<span class="normal_order">&ndash;&ndash;</span>';
            }
        }
    }
    
    
    
    
    
        // Fire a gateway specific hook for when a subscription payment is due.
	public  function gateway_scheduled_subscription_payment( $subscription_id ) {

            
		if ( ! is_object( $subscription_id ) ) {
			$subscription = hforce_get_subscription( $subscription_id );
		} else {			
			$subscription = $subscription_id;
		}

		if ( $subscription===false ) {
			throw new InvalidArgumentException( sprintf( __( 'Subscription does not exist in scheduled action: %d', 'xa-woocommerce-subscription' ), $subscription_id ) );
		}
             
		if ( ! $subscription->is_manual() ) {
			self::run_gateway_renewal_payment_hook( $subscription->get_last_order('all', array( 'renewal','parent') ));
		}
	}
    
        public static function run_gateway_renewal_payment_hook( $renewal_order ) {

            
		$renewal_order_payment_method = hforce_get_objects_property( $renewal_order, 'payment_method' );
                
		if ( ! empty( $renewal_order ) && $renewal_order->get_total() > 0 && ! empty( $renewal_order_payment_method ) ) {
			WC()->payment_gateways();
			do_action( 'woocommerce_scheduled_subscription_payment_' . $renewal_order_payment_method, $renewal_order->get_total(), $renewal_order );
		}
	}

        
        public static function run_gateway_status_updated_hook( $subscription, $new_status ) {

		if ( $subscription->is_manual() ) {
			return;
		}

		switch ( $new_status ) {
			case 'active' :
				$hook_prefix = 'woocommerce_subscription_activated_';
				break;
			case 'on-hold' :
				$hook_prefix = 'woocommerce_subscription_on-hold_';
				break;
			case 'pending-cancel' :
				$hook_prefix = 'woocommerce_subscription_pending-cancel_';
				break;
			case 'cancelled' :
				$hook_prefix = 'woocommerce_subscription_cancelled_';
				break;
			case 'expired' :
				$hook_prefix = 'woocommerce_subscription_expired_';
				break;
			default :
				$hook_prefix = apply_filters( 'hf_subscription_gateway_status_updated_hook_prefix', 'hf_subscription_status_updated_', $subscription, $new_status );
				break;
		}

                
		do_action( $hook_prefix . $subscription->get_payment_method(), $subscription );
	}
        

    public static function add_order_note($renewal_order, $subscription) {
        
        if (!is_object($subscription)) {
            $subscription = hforce_get_subscription($subscription);
        }
        if (!is_object($renewal_order)) {
            $renewal_order = wc_get_order($renewal_order);
        }
        if (is_a($renewal_order, 'WC_Order') && hforce_is_subscription($subscription)) {
            $order_number = sprintf(__('#%s', 'xa-woocommerce-subscription'), $renewal_order->get_order_number());
            $subscription->add_order_note(sprintf(__('Order %s created to record renewal.', 'xa-woocommerce-subscription'), sprintf('<a href="%s">%s</a> ', esc_url(get_edit_post_link(hforce_get_objects_property($renewal_order, 'id'))), $order_number)));
        }
        return $renewal_order;
    }
    
    public function payment_gateways_support_subscriptions_column($header) {

        
        $header_new = array_slice($header, 0, count($header) - 1, true) +
                array('subscriptions' => __('Support Recurring Pay', 'xa-woocommerce-subscription')) +
                array_slice($header, count($header) - 1, count($header) - ( count($header) - 1 ), true);
        return $header_new;
        
    }
    public function payment_gateways_subscriptions_support($gateway) {

        echo '<td class="subscriptions">';
        if (( is_array($gateway->supports) && in_array('subscriptions', $gateway->supports)) || $gateway->id == 'paypal') { 
            $status_html = '<span class="status-enabled tips" data-tip="' . esc_attr__('Supports automatic renewal payments with the Subscriptions plugin.', 'xa-woocommerce-subscription') . '">' . __('Yes', 'xa-woocommerce-subscription') . '</span>';
        } else {
            $status_html = '<span class="status-disabled">' . __('No', 'xa-woocommerce-subscription') . '</span>';
        }
        $allowed_html = wp_kses_allowed_html('post');
        $allowed_html['span']['data-tip'] = true;
        $subscriptions_support_status_html = apply_filters('woocommerce_payment_gateways_subscriptions_support_status_html', $status_html, $gateway);
        echo wp_kses($subscriptions_support_status_html, $allowed_html);
        echo '</td>';
    }
    
    public function hf_order_button_text($button_text) {

        global $product;

        if (self::whether_cart_contains_subscription()) {
            $button_text = get_option(self::$option_prefix . '_order_button_text', __('Subscribe Now', 'xa-woocommerce-subscriptions'));
            if (!$button_text)
                $button_text = __('Subscribe Now', 'xa-woocommerce-subscriptions');
        }
        return $button_text;
    }

}