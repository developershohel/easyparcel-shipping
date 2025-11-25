<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
} 
class EP_EasyParcel {

    public function __construct() {
        $this->plugin_init();
        add_action( 'before_woocommerce_init'                       , array( __CLASS__ , 'declare_compatibility'));
        add_action( 'woocommerce_shipping_init'                     , array( __CLASS__ , 'wc_shipping_init'));
        add_filter( 'plugin_action_links_'.EASYPARCEL_PLUGIN_BASE   , array( __CLASS__ , 'register_plugin_links'));
        add_action( 'admin_menu'                                    , array( __CLASS__ , 'wp_register_custom_menu') );

        // AJAX handlers
        $this->register_ajax_action();

        add_action( 'admin_init', array( __CLASS__, 'load_bulk_fulfillment_hooks' ), 20 );
    }

    public function plugin_init(){
        include_once EASYPARCEL_MODULE_PATH . 'fulfillment/EP_Fulfillment_Metabox.php';
        add_action( 'add_meta_boxes', array('EP_Fulfillment_Metabox', 'register_meta_box'), 5);

        include_once EASYPARCEL_MODULE_PATH . 'auto-fulfillment/EP_Auto_Fulfillment_Setting.php';
        // include_once EASYPARCEL_MODULE_PATH . 'backup/EP_Easyparcel_Backup.php';
        
        add_filter('woocommerce_get_order_item_totals', 'display_ep_fields_on_order_item_totals', 1000, 3);
    }

    public static function declare_compatibility(){
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', EASYPARCEL__FILE__ , true );
        }
    }
    
    public static function wc_shipping_init() {
        // Load Easyparcel Shipping Setting Page
        if (!class_exists('WC_Easyparcel_Shipping_Method')) {
            include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Method.php';
            add_filter('woocommerce_shipping_methods', array('WC_Easyparcel_Shipping_Method', 'wc_add_shipping_method'));
            add_filter('woocommerce_cart_shipping_method_full_label', array( 'WC_Easyparcel_Shipping_Method' , 'wc_shipping_render_courier_logo' ) , 10 , 2 );
        }
        
        // Load Easyparcel Shipping Zone Setting Page
        if (!class_exists('WC_Easyparcel_Shipping_Zone')) {
            include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Zone.php';
            add_filter('woocommerce_shipping_methods', array('WC_Easyparcel_Shipping_Zone', 'wc_add_shipping_method'));
        }

        // bulk-fulfillment class include (but we now hook via admin_init too)
        if (!class_exists('EP_Bulk_Fulfillment')) {
            include_once EASYPARCEL_MODULE_PATH . 'bulk-fulfillment/EP_Bulk_Fulfillment.php';
        }

            // Then initialize API properly
        if (!class_exists('Easyparcel_Shipping_API')) {
            include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
        }
        
        if (class_exists('WC_Easyparcel_Shipping_Method') && class_exists('Easyparcel_Shipping_API')) {
            Easyparcel_Shipping_API::init(); 
        }
    }

    /**
     * Called on admin_init to register bulk-action filters & AJAX.
     */
    public static function load_bulk_fulfillment_hooks() {
        if ( ! class_exists( 'EP_Bulk_Fulfillment' ) ) {
            include_once EASYPARCEL_MODULE_PATH . 'bulk-fulfillment/EP_Bulk_Fulfillment.php';
        }
        EP_Bulk_Fulfillment::register_bulk_action();
        EP_Bulk_Fulfillment::register_ajax_action();
    }

    public static function register_plugin_links($links){
        return array_merge(
            $links,
            array(
                '<a href="'. admin_url('admin.php?page=wc-settings&tab=shipping&section=easyparcel').'"> '. __('Settings', 'easyparcel-shipping') .'</a>'
            )
        );
    }

    public static function wp_register_custom_menu(){

        add_menu_page(
            'easyparcel_shipping',  // page_title - The text to be displayed in the title tags of the page when the menu is selected.
            'EasyParcel Shipping',  // menu_title - The text to be used for the menu.
            'manage_options',       // capability - The capability required for this menu to be displayed to the user.
            'admin.php?page=wc-settings&tab=shipping&section=easyparcel', // menu_slug - The slug name to refer to this menu by. Should be unique for this menu page and only include lowercase alphanumeric, dashes, and underscores characters to be compatible with sanitize_key().
            '',                     // callback - The function to be called to output the content for this page.
            'dashicons-store',      // icon_url - The URL to the icon to be used for this menu. (base64-encoded SVG using a data URI || name of a Dashicons helper class)
            66                      // position - The position in the menu order this item should appear.
        );
        add_submenu_page( 
            'admin.php?page=wc-settings&tab=shipping&section=easyparcel', // parent_slug - The slug name for the parent menu (or the file name of a standard WordPress admin page).
            'Auto Fulfillment Setting',                 // page_title - The text to be displayed in the title tags of the page when the menu is selected.
            'Auto Fulfillment Setting',                 // menu_title - The text to be used for the menu.
            'manage_options',                           // capability - The capability required for this menu to be displayed to the user.
            'easyparcel-auto-fulfilment-setting',       // menu_slug - The slug name to refer to this menu by. Should be unique for this menu and only include lowercase alphanumeric, dashes, and underscores characters to be compatible with sanitize_key().
            array('EP_Auto_Fulfillment_Setting', 'load')// callback - The function to be called to output the content for this page.
        );
        add_submenu_page( 
            'admin.php?page=wc-settings&tab=shipping&section=easyparcel', 
            'EasyParcel Courier Setting',               // page_title - The text to be displayed in the title tags of the page when the menu is selected.
            'EasyParcel Courier Setting',               // menu_title - The text to be used for the menu.
            'manage_options',                           // capability - The capability required for this menu to be displayed to the user.
            'admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping'   // menu_slug - The slug name to refer to this menu by. Should be unique for this menu and only include lowercase alphanumeric, dashes, and underscores characters to be compatible with sanitize_key().
        );
        // add_submenu_page( 
        //     'admin.php?page=wc-settings&tab=shipping&section=easyparcel', 
        //     'EasyParcel Backup',                        // page_title - The text to be displayed in the title tags of the page when the menu is selected.
        //     'EasyParcel Backup',                        // menu_title - The text to be used for the menu.
        //     'manage_options',                           // capability - The capability required for this menu to be displayed to the user.
        //     'easyparcel-backup',                        // menu_slug - The slug name to refer to this menu by. Should be unique for this menu and only include lowercase alphanumeric, dashes, and underscores characters to be compatible with sanitize_key().
        //     array('EP_Easyparcel_Backup', 'load')       // callback - The function to be called to output the content for this page.
        // );
    }

    public static function register_ajax_action(){
        include_once EASYPARCEL_MODULE_PATH . 'setup/ajax_action.php';
        include_once EASYPARCEL_MODULE_PATH . 'fulfillment/ajax_action.php';
        EP_Auto_Fulfillment_Setting::register_ajax_action();

        if (!class_exists('EP_Bulk_Fulfillment')) {
            include_once EASYPARCEL_MODULE_PATH . 'bulk-fulfillment/EP_Bulk_Fulfillment.php';
        }
        EP_Bulk_Fulfillment::register_ajax_action();
    }


    public static function is_wc_hpos_enable(){
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }
}

// Add the email tracking function here
function display_ep_fields_on_order_item_totals($total_rows, $order, $tax_display) {
    $new_total_rows = [];
    
    foreach ($total_rows as $key => $values) {
        $new_total_rows[$key] = $values;
        
        if ($key === 'shipping') {
            $ep_awb = (!empty($order->get_meta('_ep_awb'))) ? $order->get_meta('_ep_awb') : '- ';
            $selected_courier = (!empty($order->get_meta('_ep_selected_courier'))) ? $order->get_meta('_ep_selected_courier') : '-';
            
            $new_total_rows["final_courier"] = array(
                'label' => "Fulfillment:",
                'value' => $selected_courier,
            );
            
            $ep_tracking_url = (!empty($order->get_meta('_ep_tracking_url'))) ? 
                '<a href="'.$order->get_meta('_ep_tracking_url').'" target="_blank"><u>'.$ep_awb.'</u></a>' : '- ';
            
            $new_total_rows["Tracking"] = array(
                'label' => "Tracking:",
                'value' => $ep_tracking_url,
            );
        }
    }
    return $new_total_rows;
}
?>