<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}	
if (!class_exists('EP_Fulfillment_Metabox')) {

    defined( 'EASYPARCEL_MODULE_FULFILL_METABOX_PATH') || define( 'EASYPARCEL_MODULE_FULFILL_METABOX_PATH'  , EASYPARCEL_MODULE_PATH . 'fulfillment/' );
    defined( 'EASYPARCEL_MODULE_FULFILL_METABOX_URL') || define( 'EASYPARCEL_MODULE_FULFILL_METABOX_URL'  , EASYPARCEL_MODULE_URL . 'fulfillment/' );
    defined( 'EASYPARCEL_MODULE_SETUP_URL') || define( 'EASYPARCEL_MODULE_SETUP_URL', EASYPARCEL_MODULE_URL . 'setup/' );
    
    class EP_Fulfillment_Metabox {
        
        public static function register_meta_box() {

            add_meta_box( 
                'easyparcel-shipping-integration-order-fulfillment', 
                __( 'EasyParcel Fulfillment', 'easyparcel-shipping' ), 
                array( __CLASS__ , 'render_meta_box'), 
                'shop_order', 
                'side', 
                'high' 
            );
            add_meta_box( 
                'easyparcel-shipping-integration-order-fulfillment', 
                __( 'EasyParcel Fulfillment', 'easyparcel-shipping' ), 
                array( __CLASS__ , 'render_meta_box'), 
                'woocommerce_page_wc-orders', 
                'side', 
                'high' 
            );
        }
        
        public static function render_meta_box(){
            wp_enqueue_style( 'easyparcel_order_list_styles', EASYPARCEL_MODULE_SETUP_URL . 'admin.css', array(), EASYPARCEL_VERSION);
            require( EASYPARCEL_MODULE_FULFILL_METABOX_PATH . 'html_meta_box.php' );

            wp_enqueue_script( 
                'easyparcel-shipping-integration-order-fulfillment-js', 
                EASYPARCEL_MODULE_FULFILL_METABOX_URL . 'easyparcel_meta_box.js', 
                array( 'jquery' ),
                EASYPARCEL_VERSION, 
                array('in_footer' => true)
            );
        }

        public static function get_order_id(){
            if (!class_exists('EP_EasyParcel')){
                include_once EASYPARCEL_PATH . 'include/EP_EasyParcel.php';
            }
            $order_id = false;
            if(EP_EasyParcel::is_wc_hpos_enable()){
                // HPOS 
                if(isset($_GET['id']) && current_user_can('edit_shop_orders')) {
                    $order_id = absint($_GET['id']);
                }
            }else{
                // WPS (legacy)
                global $post;
                if ($post && current_user_can('edit_post', $post->ID)) {
                    $order_id = $post->ID;
                }
            }
            return $order_id;
        }

        public static function get_parcel_category_list(){
            if (!class_exists('Easyparcel_Shipping_API')) {
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }
            $parcel_category = Easyparcel_Shipping_API::getParcelCategoryList();
    
            return $parcel_category;
        }

        public static function get_api_detail($order_id) {
            $coureierDDP = ["EP-CR0DI", "EP-CR06"];
    
            // ADD THIS BLOCK HERE (before line 68)
            if (!class_exists('WC_Easyparcel_Shipping_Method')) {
                include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Method.php';
            }
    
            $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();
            $rates = $WC_Easyparcel_Shipping_Method->get_admin_shipping($order_id);
    
            $obj = (object)array();
            $obj->easycover_list = array();
            $obj->ddp_list = array();
            $obj->shipment_providers_list = array();
            $obj->dropoff_point_list = array();
            $obj->selected_dropoff_point = '';
            $obj->insurance_basic_coverage = array();
            $obj->is_auto_fulfillment_preset = false;
            $obj->selected_service_name = '';
            $obj->selected_service_id = '';
    
            $auto_fulfillment_setting = get_option('easyparcel_auto_fulfillment_settings');
    
            // echo "<pre>"; print_r($auto_fulfillment_setting); echo "</pre>";
            foreach($rates as $rate){
                // echo "<pre>"; print_r($rate); echo "</pre>";
                $shipment_provider = (object)array();
                $shipment_provider->cid = $rate['courier_id'];
                $shipment_provider->ts_slug = $rate['id'];
                $shipment_provider->provider_name = $rate['label'];
                $shipment_provider->have_dropoff = count($rate['dropoff_point']) > 0;
                array_push($obj->shipment_providers_list, $shipment_provider);
    
                if($rate['easycover']){	
                    array_push($obj->easycover_list, $rate['id']);
                    $obj->insurance_basic_coverage[$rate['id']] = array(
                        'basic_coverage' => $rate['basic_coverage'], 
                        'basic_coverage_currency' => $rate['basic_coverage_currency']
                    );
                }
    
                if(in_array($rate['courier_id'], $coureierDDP)){
                    array_push($obj->ddp_list, $rate['id']);
                }
                
                $dropoff = array();
                $dropoff[$rate['id']] = $rate['dropoff_point'];
                array_push($obj->dropoff_point_list, $dropoff);
    
                
                if(!empty($auto_fulfillment_setting['ep_is_auto_fulfillment'])){
                    $obj->is_auto_fulfillment_preset = true;
                    if($rate['courier_id'] == $auto_fulfillment_setting['ep_courier']){
                        if($auto_fulfillment_setting['ep_pickup_dropoff'] == "dropoff" and $shipment_provider->have_dropoff){
                            $obj->selected_dropoff_point = $auto_fulfillment_setting['ep_courier_dropoff'];
                            $obj->selected_service_name = $shipment_provider->provider_name;
                            $obj->selected_service_id = $shipment_provider->ts_slug;
                        }elseif($auto_fulfillment_setting['ep_pickup_dropoff'] != "dropoff" and !$shipment_provider->have_dropoff){
                            $obj->selected_service_name = $shipment_provider->provider_name;
                            $obj->selected_service_id = $shipment_provider->ts_slug;
                        }
                    }
    
                }
    
                $obj->selected_dropoff_point = isset($rate['selected_dropoff_point']) ? $rate['selected_dropoff_point'] : $obj->selected_dropoff_point;
            }
            // echo "<pre>";print_r($obj->easycover_list);echo "</pre>";
            return $obj;
        }

    }

}