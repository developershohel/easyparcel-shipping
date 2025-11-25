<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('WC_Easyparcel_Shipping_Zone')) {

    defined( 'EASYPARCEL_MODULE_SETUP_PATH') || define( 'EASYPARCEL_MODULE_SETUP_PATH'  , EASYPARCEL_MODULE_PATH . 'setup/' );
    defined( 'EASYPARCEL_MODULE_SETUP_URL') || define( 'EASYPARCEL_MODULE_SETUP_URL'  , EASYPARCEL_MODULE_URL . 'setup/' );

    class WC_Easyparcel_Shipping_Zone extends WC_Shipping_Method {

        /**
         * Constructor for your shipping class
         *
         * @access public
         * @return void
         */
        public function __construct() {
            $this->id                 = 'easyparcel_shipping';
            $this->title              = __('EasyParcel Courier Setting', 'easyparcel-shipping');
            $this->method_title       = __('EasyParcel Courier Setting', 'easyparcel-shipping');
            $this->method_description = __('A shipping zone is a geographic region where a certain set of shipping methods are offered. WooCommerce will match a customer to a single zone using their shipping address and present the shipping methods within that zone to them.', 'easyparcel-shipping');
            $this->instance_id        = empty($instance_id) ? 0 : absint($instance_id);
        }

        /**
         * Init your settings
         *
         * @access public
         * @return void
         */
        function init() {}

        /**
         * Output the shipping settings screen. Overwrite original
         * handle for easyparcel_shipping main and sub pages
         */
        public function admin_options() {
            global $current_section, $hide_save_button, $wpdb;

            // Check user capabilities
            if (!current_user_can('manage_woocommerce')) {
                wp_die('You do not have sufficient permissions to access this page.');
            }

            $hide_save_button = true;
            $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();
            if($WC_Easyparcel_Shipping_Method->settings['legacy_courier_setting'] != 'yes'){
                echo "<h4>";
                echo "You are currently disable Legacy Courier Setting<br>";
                echo "Please <a href='".esc_url($WC_Easyparcel_Shipping_Method->plugin_url)."'>click here</a> to enable the legacy courier setting in-order to use Legacy Courier Setting.<br>";
                echo "</h4>";
                return;
            }

            $result = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = 'easyparcel'");
            if(empty($result)){
                echo '<h4><font color="red">Important**</font><br>You will need to setup EasyParcel Shipping first <a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=shipping&section')) . '">HERE</a> before proceeding to EasyParcel Courier Setting.';
                return ;
            }
            if ('easyparcel_shipping' === $current_section) {

                // Verify nonce for any form submissions
                if (!empty($_POST) || !empty($_GET['action'])) {
                    if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'action')) {
                        wp_die('Security check failed. Please try again.', 'default');
                    }
                }
                
                // Sanitize input
                $zone_id = isset($_REQUEST['zone_id']) ? sanitize_text_field(wp_unslash($_REQUEST['zone_id'])) : '';
                $courier_id = isset($_REQUEST['courier_id']) ? sanitize_text_field(wp_unslash($_REQUEST['courier_id'])) : '';
                $perform = isset($_REQUEST['perform']) ? sanitize_text_field(wp_unslash($_REQUEST['perform'])) : '';
                
                // Load Shipping Zone listing
                if(empty($zone_id) && empty($courier_id)){
                    include_once EASYPARCEL_MODULE_SETUP_PATH . 'html_load_shipping_zone_list.php';

                } 
                // Add New Zone
                else if (!empty($zone_id) && empty($courier_id) && !empty($perform)) {
                    include_once EASYPARCEL_MODULE_SETUP_PATH . 'html_setup_courier_page.php';

                } 
                // Add Courier into Zone
                else if (!empty($zone_id) && empty($courier_id)) {
                    include_once EASYPARCEL_MODULE_SETUP_PATH . 'html_setup_zone.php';

                } 
                // Edit Zone
                elseif (!empty($courier_id)) {
                    include_once EASYPARCEL_MODULE_SETUP_PATH . 'html_edit_courier_panel.php';
                }
            }
        }

        public static function wc_add_shipping_method($methods) {
            $methods['easyparcel_zone'] = __CLASS__;
            return $methods;
        }

        public static function filteringRegionRate($zone,$customize=false){
            $r_data = array();
            $locations = $zone->get_zone_locations();
            if(empty($locations)) {return array();} // no rate to show
            $countries = array_filter($locations, function ($location) {
                return 'country' === $location->type;
            });
            $states = array_filter($locations, function ($location) {
                return 'state' === $location->type;
            });

            //this part do for controlling condition 
            // if more than 1 country, should block at select option thr
            // if state more than 2 (same country), then use country instead
            // for international, 1 country 1 zone.
            
            $my_state = array();
            $other_state = array();
            // print_R($states);
            foreach($states as $state){
                $temp = explode(':',$state->code);
                if($temp[0] == 'MY'){
                    $my_state[] = $temp[1];
                }else{
                    $other_state[] = $temp;
                }
            }
            //do condition returning what to do for rate
            if(count($countries) > 1){
                $country_list = array();
                foreach($countries as $c){
                    $country_list[] = $c->code;
                }
                if(!empty($states)){
                    foreach($states as $state){
                        $temp = explode(':',$state->code);
                        $country_list[] = $temp[0];
                    }
                }
                $r_data['condition'] = 'country';
                $r_data['country'] = $country_list;
            }else if(count($countries) > 0){
                if(!empty($my_state) && !empty($other_state)){
                    return array(); // no rate to show
                }else if(!empty($other_state)){
                    $test_arr = array();
                    foreach($other_state as $ostat){
                        $test_arr[$ostat[0]][] = $ostat[1];
                    }
                    if(count($test_arr) > 1){ // consists multiple country and state
                        return array(); // no rate to show
                    }else{
                        if($countries == key($test_arr)){
                            $r_data['condition'] = 'country';
                            $r_data['country'] = strtolower(key($countries));
                        }
                    }
                }else{
                    // only one country
                    $r_data['condition'] = 'country';
                    $r_data['country'] = strtolower($countries[0]->code);
                }
            }else if(!empty($my_state) && !empty($other_state)){
                return array(); // no rate to show
            }else if(!empty($other_state)){
                $test_arr = array();
                foreach($other_state as $ostat){
                    $test_arr[$ostat[0]][] = $ostat[1];
                }
                if(count($test_arr) > 1){ // consists multiple country and state
                    return array(); // no rate to show
                }else{
                    // for international only get country 
                    $r_data['condition'] = 'country';
                    $r_data['country'] = strtolower(key($test_arr));
                }
            }else if(!empty($my_state)){
                if(count($my_state) > 2){
                    // for MY if more than 2 state, will direct use country
                    $r_data['condition'] = 'country';
                    $r_data['country'] = 'my';
                }else{
                    $r_data['condition'] = 'state';
                    $r_data['country'] = 'my';
                    $r_data['state'] = $my_state;
                }
            }
        
            //do foreach to get rate and mapping
            $rates = array();
            if(!empty($r_data)){
                switch ( $r_data['condition'] ) {
                    case 'country':
                        if(is_array($r_data['country'])){
                            $temp_rate = array();
                            foreach($r_data['country'] as $c){
                                $temp['country'] = $c;
                                $temp['state'] = '';
                                $temp_rate = self::callrate($temp,$customize);
                                if(!empty($temp_rate)){
                                    $rates = array_merge($rates,$temp_rate);
                                }
                            }
                        } else{
                            $temp = array();
                            $temp['country'] = $r_data['country'];
                            $temp['state'] = '';
                            $rates = self::callrate($temp,$customize);
                        }
                        break;
                    case 'state':
                        $temp_rate = array();
                        foreach($r_data['state'] as $state){
                            $temp['country'] = $r_data['country'];
                            $temp['state'] = $state;
                            $temp_rate = self::callrate($temp,$customize);
                            if(!empty($temp_rate)){
                                $rates = array_merge($rates,$temp_rate);
                            }
                        }
                        break;
                }
            }
            // return $rates;
            $groupped = array();
            foreach ($rates as $rate) {
                $groupped[$rate->courier_id][] = $rate;
            }
            $shipping_rate_list = array();
            foreach ($groupped as $cid => $services) {
                foreach ($services as $rate) {
                    $courier_service_label = $rate->service_name;

                    $courier_logo = array();
                    $courier_logo['ep_courier_logo'] = $rate->courier_logo; ### save ep courier logo ###
                    $courier_service_label .= ' ['.$rate->delivery.']';

                    $shipping_rate = array(
                        'rate_id' => $rate->rate_id,
                        'service_id' => $rate->service_id,
                        'service_name' => $rate->service_name,
                        'Service_Type' => $rate->service_type,
                        'courier_id' => $rate->courier_id,
                        'courier_name' => $rate->courier_name,
                        'courier_logo' => $rate->courier_logo,
                        'courier_info' => $rate->delivery,
                        'courier_extra_info' => $courier_service_label,
                        'courier_display_name' => $rate->courier_name,
                        'sample_cost' => $rate->price,
                        'sample_cost_display' => '('.get_woocommerce_currency().' '.$rate->price.')',
                        'dropoff_point' => isset($rate->dropoff_point) ? $rate->dropoff_point : (isset($rate->Dropoff_Point) ? $rate->Dropoff_Point : '')
                    );
                    $shipping_rate_list[$rate->service_id] = $shipping_rate;
                }
            }
            return $shipping_rate_list;
        }


        public static function callrate($data,$customize=false){
            if (!class_exists('Easyparcel_Shipping_API')) {
                // Include Easyparcel API
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }
			Easyparcel_Shipping_API::init();
			if(!isset($_SESSION['ep_auth_status'])){
                $auth = Easyparcel_Shipping_API::auth();
                $_SESSION['ep_auth_status'] =  $auth;	
            }
			
            if ($_SESSION['ep_auth_status'] != 'Success.') {
                // show authentication got problem, prompt user to setup correct email + integration id
                // todo
                return array();
            } else {
                // go get rate
                $destination = array();
                $destination['country'] = $data['country'];
                $destination['state'] = $data['state'];
                $destination['postcode'] = ''; // no have postcode, so ignore it as empty
                if($customize){
                    switch (strtoupper($data['state'])) {
                        case "SBH":
                          $destination['postcode'] = "88000";
                          break;
                        case "SRW":
                        case "SWK":
                            $destination['postcode'] = "93000";
                          break;
                        case "LBN":
                            $destination['postcode'] = "87000";
                          break;
                        default:
                          break;
                    }
                }
                $items = array();
                $items[0]['weight'] = 1;
                $items[0]['length'] = 10;
                $items[0]['width'] = 10;
                $items[0]['height'] = 10;
                return $rates = Easyparcel_Shipping_API::getShippingRate($destination,$items);
            }
        }

        public static function chargesOption($selected =''){
            $charges = array();
            $charges[2] = array('text' => 'EasyParcel Member Rate', 'selected' => '');
            // $charges[3] = array('text' => 'EasyParcel Public Rate', 'selected' => '');
            $charges[4] = array('text' => 'Add On EasyParcel Member Rate', 'selected' => '');
            $charges[1] = array('text' => 'Flat Rate', 'selected' =>'');

            foreach($charges as $k => &$c){
                if($k == $selected){
                    $c['selected'] = 'selected';
                }
            }

            return $charges;
        }
        
        public static function freeShippingByOption($selected =''){
            $option = array();
            $option[1] = array('text' => 'A minimum order amount', 'selected' => '');
            $option[2] = array('text' => 'A minimum order quantity', 'selected' => '');

            foreach($option as $k => &$c){
                if($k == $selected){
                    $c['selected'] = 'selected';
                }
            }

            return $option;
        }
        
        public static function addonChargesOption($selected =''){
            $option = array();
            $option[1] = array('text' => 'Add On By Amount ('.get_woocommerce_currency().')', 'selected' => '');
            $option[2] = array('text' => 'Add On By Percentage (%)', 'selected' => '');

            foreach($option as $k => &$c){
                if($k == $selected){
                    $c['selected'] = 'selected';
                }
            }

            return $option;
        }

        public static function checkDropoff($courier,$courier_list = array()){
            $option = array();
            $option['optional'] = array('text' => 'Drop Off Point', 'selected' => '');
            if(!empty($courier_list[$courier->service_id])){
                if(!empty($courier_list[$courier->service_id]['dropoff_point']))
                foreach($courier_list[$courier->service_id]['dropoff_point'] as $k => $v){
                    if($v->point_id == $courier->courier_dropoff_point){
                        $option[$v->point_id] = array('text' => $v->point_name, 'selected' => 'selected');
                    }else{
                        $option[$v->point_id] = array('text' => $v->point_name, 'selected' => '');
                    }
                }
            }
            return $option;
        }

    }

}
