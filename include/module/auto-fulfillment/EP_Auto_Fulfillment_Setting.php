<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}	
if (!class_exists('EP_Auto_Fulfillment_Setting')) {

    defined( 'EASYPARCEL_MODULE_AUTOFULFILL_PATH') || define( 'EASYPARCEL_MODULE_AUTOFULFILL_PATH'  , EASYPARCEL_MODULE_PATH . 'auto-fulfillment/' );
    defined( 'EASYPARCEL_MODULE_AUTOFULFILL_URL') || define( 'EASYPARCEL_MODULE_AUTOFULFILL_URL'  , EASYPARCEL_MODULE_URL . 'auto-fulfillment/' );
    class EP_Auto_Fulfillment_Setting {
        
        public static function load() {
            self::handlePost();
            self::render_page();
            self::enqueue_style();
            self::enqueue_script();
        }


        public static function render_page() {
            require( EASYPARCEL_MODULE_AUTOFULFILL_PATH . 'html_easyparcel_auto_fulfillment_setting.php' );
        }

        
        public static function enqueue_style() {
            wp_enqueue_style( 'easyparcel_css', EASYPARCEL_MODULE_AUTOFULFILL_URL . 'easyparcel.css', array(), EASYPARCEL_VERSION);
            wp_enqueue_style( 'easyparcel_breadcrumb_css', EASYPARCEL_MODULE_AUTOFULFILL_URL . 'ep_breadcrumb.css',array(), EASYPARCEL_VERSION );
        }
        
        public static function enqueue_script() {
            wp_enqueue_script( 'easyparcel_auto_fulfillment_setting_js', EASYPARCEL_MODULE_AUTOFULFILL_URL . 'admin_auto_fulfillment_setting.js', array( 'jquery' ), EASYPARCEL_VERSION, array('in_footer' => true));
        
            // Get the setting safely
            $auto_fulfillment_setting = get_option('easyparcel_auto_fulfillment_settings', array());
        
            // Localize script to pass data to JavaScript safely
            wp_localize_script( 'easyparcel_auto_fulfillment_setting_js', 'obj', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'ep_auto_fulfillment_nonce' ),
                'ep_courier_dropoff' => sanitize_text_field($auto_fulfillment_setting['ep_courier_dropoff'] ?? '')
            ));
        }

        public static function register_ajax_action()
        {
            add_action( "wp_ajax_ep_get_courier_dropoff_list", array ( __CLASS__, 'ajax_get_courier_dropoff_list' ));
            add_action('woocommerce_new_order', array ( __CLASS__, 'auto_fulfillment_handling' ),1000);
            add_action('woocommerce_update_order', array ( __CLASS__, 'auto_fulfillment_handling' ),1000);
        }

        public static function handlePost()
		{
			if(!empty($_POST)){
				// SECURITY: Add nonce verification
				if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'easyparcel_auto_fulfillment_settings')) {
					wp_die('Security check failed');
				}

				// SECURITY: Check user capabilities
				if (!current_user_can('manage_woocommerce')) {
					wp_die('You are not allowed to perform this action');
				}

				// SECURITY: Properly sanitize POST data
				$auto_fulfillment_setting = array(
					"ep_is_auto_fulfillment" => sanitize_text_field(wp_unslash($_POST['ep_is_auto_fulfillment'] ?? '')),
					"ep_courier" => sanitize_text_field(wp_unslash($_POST['ep_courier'] ?? '')),
					"ep_pickup_dropoff" => sanitize_text_field(wp_unslash($_POST['ep_pickup_dropoff'] ?? '')),
					"ep_courier_dropoff" => sanitize_text_field(wp_unslash($_POST['ep_courier_dropoff'] ?? '')),
				);

				if(get_option('easyparcel_auto_fulfillment_settings')){
					update_option('easyparcel_auto_fulfillment_settings', $auto_fulfillment_setting);
				}else{
					add_option('easyparcel_auto_fulfillment_settings', $auto_fulfillment_setting);
				}
			}
		}

        /**
         * 
         * Hook Action
         * 
         */

        // Ajax get Easyparcel Courier Dropoff List
        public static function ajax_get_courier_dropoff_list()
        {
            // SECURITY: Add nonce verification
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ep_auto_fulfillment_nonce')) {
                wp_send_json_error('Security check failed');
                wp_die();
            }

            // SECURITY: Check user capabilities
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error('Insufficient permissions');
                wp_die();
            }

            $list = array();
            if (!class_exists('WC_Easyparcel_Shipping_Method')) {
                include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Method.php';
            }

            if (!class_exists('Easyparcel_Shipping_API')) {
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }
            $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();
            
            Easyparcel_Shipping_API::init();

            if(isset($_POST['cid'])){
                $cid = sanitize_text_field(wp_unslash($_POST['cid']));
                $state = $WC_Easyparcel_Shipping_Method->settings['sender_state'];
            
                $ep_data = Easyparcel_Shipping_API::getCourierDropoffList($cid, $state);
                
                foreach($ep_data->{$cid} as $item){
                    array_push($list, (object)array(
                        "point_id" => $item->point_id,
                        "point_name" => $item->point_name,
                        "point_contact" => $item->point_contact,
                        "point_addr1" => $item->point_addr1,
                        "point_addr2" => $item->point_addr2,
                        "point_addr3" => $item->point_addr3,
                        "point_addr4" => $item->point_addr4,
                        "point_postcode" => $item->point_postcode,
                        "point_city" => $item->point_city,
                        "point_state" => $item->point_state,
                        "start_time" => $item->start_time,
                        "end_time" => $item->end_time,
                        "price" => $item->price,
                    ));
                }
            }

            wp_send_json_success($list);
        }

        // Auto Fulfillment Action
        public static function auto_fulfillment_handling($order_id){
            $auto_fulfillment_setting = get_option('easyparcel_auto_fulfillment_settings');
            $order = wc_get_order($order_id);

            // FULFILLMENT CONDITION CHECK: Order must be processing, no payment status, and auto fulfillment enabled
            if(!$order->meta_exists('_ep_payment_status') and $order->get_status() == "processing"){
                if($auto_fulfillment_setting['ep_is_auto_fulfillment'] == 'yes' and $order->get_meta('_ep_auto_fulfill_statue') == ''){
                    // make sure fulfillment process only run once
                    // FULFILLMENT STEP 1: Mark order as processing to prevent duplicate runs
                    $order->update_meta_data('_ep_auto_fulfill_statue','processing');
                    $order->save();

                    wc_get_logger()->info("Order #$order_id is currently fulfilling in EasyParcel through Auto Fulfillment.");

                    if (!class_exists('EP_Fulfillment_Metabox')) {
                        include_once EASYPARCEL_MODULE_PATH . 'fulfillment/EP_Fulfillment_Metabox.php';
                    }

                    // FULFILLMENT STEP 2: Get API details for the order
                    $detail = EP_Fulfillment_Metabox::get_api_detail($order_id);

                    // FULFILLMENT STEP 3: Build fulfillment object with smart courier selection
                    $obj = (object)array();
                    $obj->order_id = $order_id;
                    $obj->pick_up_date = date_i18n( __( 'Y-m-d', 'easyparcel-shipping' ), current_time( 'timestamp' ) );
                    
                    $service_id = '';
                    $service_name = '';
                    $selection_method = '';
                    
                    // STEP 1: Try to match order's shipping method first
                    if(!empty($order->get_shipping_method())){
                        $order_shipping_method = $order->get_shipping_method();
                        
                        // Log available providers for debugging
                        $available_providers = array();
                        foreach($detail->shipment_providers_list as $provider){
                            $available_providers[] = array(
                                'name' => $provider->provider_name,
                                'courier_id' => $provider->courier_id ?? 'not_set',
                                'ts_slug' => $provider->ts_slug ?? 'not_set'
                            );
                            
                            if($provider->provider_name == $order_shipping_method){
                                $service_id = $provider->ts_slug;
                                $service_name = $provider->provider_name;
                                $selection_method = 'matched_shipping_method';
                            }
                        }
                    }

                    // STEP 2: Fallback to pre-configured courier if no match found
                    if(empty($service_id)){
                        $configured_courier_id = $auto_fulfillment_setting['ep_courier'] ?? '';
                        
                        if(!empty($configured_courier_id)){
                            foreach($detail->shipment_providers_list as $provider){
                                if($provider->courier_id == $configured_courier_id){
                                    $service_id = $provider->ts_slug;
                                    $service_name = $provider->provider_name;
                                    $selection_method = 'fallback_configured_courier';
                                    break;
                                }
                            }
                        }
                    }

                    // STEP 3: Final fallback to detail defaults if still empty
                    if(empty($service_id)){
                        $service_id = $detail->selected_service_id ?? '';
                        $service_name = $detail->selected_service_name ?? '';
                        $selection_method = 'detail_defaults';
                    }

                    $obj->shipping_provider = $service_id;
                    $obj->courier_name = $service_name;

                    // Set dropoff point based on settings
                    if($auto_fulfillment_setting['ep_pickup_dropoff'] == 'dropoff'){
                        $obj->drop_off_point = $auto_fulfillment_setting['ep_courier_dropoff'] ?? '';
                    } else {
                        $obj->drop_off_point = ""; // Pickup from door
                    }
    
                    $obj->easycover = "false";
                    $obj->easyparcel_ddp = "";
                    $obj->easyparcel_parcel_category = "";
                    
                    if (!class_exists('WC_Easyparcel_Shipping_Method')) {
                        include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Method.php';
                    }
                    $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();
                    
                    // FULFILLMENT STEP 4: Validate required data before processing
                    if(empty($obj->shipping_provider)) {
                        wc_get_logger()->warning("Order #$order_id fulfillment aborted - Missing shipping provider", array(
                            'source' => 'easyparcel-auto-fulfillment',
                            'shipping_provider' => $obj->shipping_provider,
                            'courier_name' => $obj->courier_name,
                            'order_shipping_method' => $order->get_shipping_method()
                        ));
                        $order->update_meta_data('_ep_auto_fulfill_statue','fail');
                        $order->save();
                        return; // Exit early to prevent API call
                    }
                    
                    // FULFILLMENT STEP 4: Process the actual booking - THIS IS THE MAIN FULFILLMENT ACTION
                    $ep_order = $WC_Easyparcel_Shipping_Method->process_booking_order($obj);

                    // FULFILLMENT STEP 5: Handle fulfillment result
                    if($ep_order == ''){
                        // SUCCESS - Fulfillment completed
                        $order->update_meta_data('_ep_auto_fulfill_statue','complete');
                        $order->save();
                        $order = wc_get_order($order_id);
                        wc_get_logger()->info("Order #$order_id completed fulfilled in EasyParcel through Auto Fulfillment.",
                            array(
                                "courier" => $order->get_meta('_ep_selected_courier'),
                                "order_no" => $order->get_meta('_ep_order_number'),
                                "airwaybill_no" => $order->get_meta('_ep_awb'),
                                "fulfillment_date" => $order->get_meta('_ep_fulfillment_date'),
                            )
                        );
                    }else{
                        // FAILURE - Fulfillment failed
                        $order->update_meta_data('_ep_auto_fulfill_statue','fail');
                        $order->save();

                        $error_msg = $ep_order;

                        $error_msg = str_replace("send_contact", "receiver's contact", $error_msg);
                        $error_msg = str_replace("pick_contact", "sender's contact", $error_msg);
                        $error_msg = str_replace("send_mobile", "receiver's mobile", $error_msg);
                        $error_msg = str_replace("pick_mobile", "sender's mobile", $error_msg);
                        $error_msg = str_replace("send_code", "receiver's postcode", $error_msg);
                        $error_msg = str_replace("pick_code", "sender's postcode", $error_msg);

                        wc_get_logger()->warning("Order #$order_id fail to fulfill in EasyParcel.",
                            array(
                                'source' => 'easyparcel-auto-fulfillment',
                                'error-message' => $error_msg
                            )
                        );
                    }
                    
    
                }
            }

            // RESET FULFILLMENT STATUS: If order is on-hold and previously failed, reset for retry
            if($order->get_status() == "on-hold" and $order->get_meta('_ep_auto_fulfill_statue') == 'fail'){
                $order->update_meta_data('_ep_auto_fulfill_statue','');
                $order->save();
                
                wc_get_logger()->info("Order #$order_id fulfillment status reset due to on-hold status", array(
                    'source' => 'easyparcel-auto-fulfillment',
                    'action' => 'reset_fulfillment_status'
                ));
            }


        }
    }

}