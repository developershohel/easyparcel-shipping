<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}	
if (!class_exists('Easyparcel_Shipping_API')) {
    class Easyparcel_Shipping_API {

        private static $apikey = '';
        private static $apiSecret = '';
		private static $apiVersion = '';
        private static $easyparcel_email = '';
        private static $authentication = ''; # Indicate from EP
        private static $integration_id = '';

        private static $sender_name = '';
        private static $sender_contact_number = '';
        private static $sender_alt_contact_number = '';
        private static $sender_company_name = '';
        private static $sender_address_1 = '';
        private static $sender_address_2 = '';
        private static $sender_postcode = '';
        private static $sender_city = '';
        private static $sender_state = '';
        private static $sender_country = '';

        private static $addon_email_option = '';
        private static $addon_sms_option = '';
        private static $addon_whatsapp_option = '';

        // private static $getrate_api_url = ''; // Hide it cause didn't use bulk api
        // private static $submitorder_api_url = ''; // Hide it cause didn't use bulk api
        // private static $payorder_api_url = ''; // Hide it cause didn't use bulk api

        private static $getrate_bulk_api_url = '';
        private static $submit_bulk_order_api_url = '';
        private static $pay_bulk_order_api_url = '';
        private static $get_default_address = '';

        private static $auth_url = '';
        private static $couriers_url = '';
        private static $courier_dropoff_url = '';
        private static $conversion_rate = '';

        /**
         * init
         *
         * @access public
         * @return void
         */
        public static function init() {

            $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();

            self::$sender_country = $WC_Easyparcel_Shipping_Method->settings['sender_country'];
            $host = 'http://connect.easyparcel.'.strtolower(self::$sender_country);

            // self::$getrate_api_url = $host . '/?ac=EPRateChecking'; // Hide it cause didn't use bulk api
            // self::$submitorder_api_url = $host . '/?ac=EPSubmitOrder'; // Hide it cause didn't use bulk api
            // self::$payorder_api_url = $host . '/?ac=EPPayOrder'; // Hide it cause didn't use bulk api

            self::$getrate_bulk_api_url = $host . '/?ac=EPRateCheckingBulk';
            self::$submit_bulk_order_api_url = $host . '/?ac=EPSubmitOrderBulk';
            self::$pay_bulk_order_api_url = $host . '/?ac=EPPayOrderBulk';

            self::$auth_url = $host . '?ac=EPCheckCreditBalance';

            self::$couriers_url = $host . '?ac=EPCourierList';
            self::$courier_dropoff_url = $host . '?ac=EPCourierDropoff';

            self::$easyparcel_email = $WC_Easyparcel_Shipping_Method->settings['easyparcel_email'];
            self::$integration_id = $WC_Easyparcel_Shipping_Method->settings['integration_id'];

            self::$sender_name = $WC_Easyparcel_Shipping_Method->settings['sender_name'];
            self::$sender_contact_number = $WC_Easyparcel_Shipping_Method->settings['sender_contact_number'];
            self::$sender_alt_contact_number = $WC_Easyparcel_Shipping_Method->settings['sender_alt_contact_number'];
            self::$sender_company_name = $WC_Easyparcel_Shipping_Method->settings['sender_company_name'];
            self::$sender_address_1 = $WC_Easyparcel_Shipping_Method->settings['sender_address_1'];
            self::$sender_address_2 = $WC_Easyparcel_Shipping_Method->settings['sender_address_2'];
            self::$sender_postcode = $WC_Easyparcel_Shipping_Method->settings['sender_postcode'];
            self::$sender_city = $WC_Easyparcel_Shipping_Method->settings['sender_city'];
            self::$sender_state = $WC_Easyparcel_Shipping_Method->settings['sender_state'];

            self::$addon_email_option = $WC_Easyparcel_Shipping_Method->settings['addon_email_option'];
            self::$addon_sms_option = $WC_Easyparcel_Shipping_Method->settings['addon_sms_option'];
            self::$addon_whatsapp_option = $WC_Easyparcel_Shipping_Method->settings['addon_whatsapp_option'];
            self::$conversion_rate = isset($WC_Easyparcel_Shipping_Method->settings['conversion_rate']) 
                ? $WC_Easyparcel_Shipping_Method->settings['conversion_rate'] 
                : '';

        }

        public static function countryValidate() {
            $WC_Country = new WC_Countries();
            if(strtolower($WC_Country->get_base_country()) == strtolower(self::$sender_country)){
                return true;
            }else{
                return false;
            }
        }

        public static function curlPost($data) {
            $r = '';

            // $ch = curl_init();
            // curl_setopt($ch, CURLOPT_URL, $data->url);
            // curl_setopt($ch, CURLOPT_POST, 1);
            // curl_setopt($ch, CURLOPT_POSTFIELDS, $data->pfs);
            // curl_setopt($ch, CURLOPT_HEADER, 0);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            // ob_start();
            // $r = curl_exec($ch);
            // ob_end_clean();
            // curl_close($ch);
            $args = array(
                'timeout'     => 60, // You might want to validate this as an integer
                'body'        => $data->pfs,
                'blocking'    => true,
                'headers'     => array(),
            );
            $r = wp_remote_post($data->url, $args );
            return $r;
        }

        public static function auth() {
            // $auth = array(
            //     'user_email' => self::$easyparcel_email,
            //     'integration_id' => self::$integration_id,
            // );
            $auth = array(
                'api' => self::$integration_id,
            );

            $data = (object) array();
            $data->url = self::$auth_url;
            $data->pfs = $auth;

            $r = self::curlPost($data);
            if (!is_wp_error($r)) {
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';
            }else{
                $json = '';
            }
            // Success.

            // echo '<pre>';print_r($data);echo '</pre>';
            // echo '<pre>';print_r($auth);echo '</pre>';
            // echo '<pre>';print_r($r);echo '</pre>';
            // echo '<pre>';print_r($json->error_code);echo '</pre>';
            // die();

            if(isset($json->error_code) && $json->error_code != '0'){
                return $json->error_remark;
            }else{
                return 'Success.';
            }
        }

        public static function getParcelCategoryList($api_key = "", $country_code = ""){
			if(empty($api_key))
				$api_key = self::$integration_id;
			if(empty($country_code))
				$country_code = self::$sender_country;
            
			$list = array();
			if(!empty($country_code) && !empty($api_key)) {
				$data = (object) array();
				$data->url = "http://connect.easyparcel." . strtolower($country_code) . "?ac=EPGetParcelCategory";
				
				$auth = array(
					'api' => $api_key,
				);
				$data->pfs = $auth;
				
				$r = self::curlPost($data);
				if (!is_wp_error($r)) {
					$body = json_decode(wp_remote_retrieve_body($r));
					$list = $body->result;
				}
			}
			return $list;
		}

        public static function getCreditBalance($api_key = "", $country_code = ""){
			if(empty($api_key))
				$api_key = self::$integration_id;
			if(empty($country_code))
				$country_code = self::$sender_country;
			
			
			$balance = "0.00";
			

			if(!empty($country_code) && !empty($api_key)) {
				$data = (object) array();
				$data->url = "http://connect.easyparcel." . strtolower($country_code) . "?ac=EPCheckCreditBalance";
				
				$auth = array(
					'api' => $api_key,
				);
				$data->pfs = $auth;
				
				$r = self::curlPost($data);
				if (!is_wp_error($r)) {
					$body = json_decode(wp_remote_retrieve_body($r));
					$money = $body->result;
					$currency = $body->currency;
					self::$apiVersion = $body->api_version;

					$balance = $currency . " " . number_format((float)$money, 2, '.', '');;
				}
			}
			return $balance;
		}
		
		public static function getAPIVersion(){
			return $APIVersion = self::$apiVersion;
		}

        public static function getShippingRate($destination,$items)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => sanitize_text_field(self::$authentication),
                    'api' => sanitize_text_field(self::$integration_id),
                    'bulk' => array(),
                    // 'exclude_fields' => ['rates.*.dropoff_point','rates.*.pickup_point','pgeon_point'],
                );

                $weight = 0;

                // Initialize dimensions
                $length = $width = $height = $maxLength = $maxWidth = $maxHeight = $sumLength = $sumWidth = $sumHeight = 0;
          
                foreach ($items as $item) {
                    $weight += $item['weight'];
                    if (is_numeric($item['length'])) {
                        $length = floatval($item['length']);

                        $maxLength = max($maxLength, $length);  
                        $sumLength += sanitize_text_field($length);
                    }
                
                    if (is_numeric($item['width'])) {
                        $width = (float) $item['width'];
                        $maxWidth = max($maxWidth, $width); 
                        $sumWidth += $width;
                    }
                
                    if (is_numeric($item['height'])) {
                        $height = floatval($item['height']);
                        $maxHeight = max($maxHeight, $height);
                        $sumHeight += sanitize_text_field($height);
                    }
                }

                $smallestSum = min($sumLength, $sumWidth, $sumHeight); //In order to not let the volumetric weight become too large.

                if ($smallestSum === $sumLength) {
                    $length = $sumLength;
                    $width = $maxWidth;
                    $height = $maxHeight;
                } elseif ($smallestSum === $sumWidth) {
                    $length = $maxLength;
                    $width = $sumWidth;
                    $height = $maxHeight;
                } else {
                    $length = $maxLength;
                    $width = $maxWidth;
                    $height = $sumHeight;
                }
                $weight = $weight > 0 ? $weight : 0.25;

                $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();

                if ($WC_Easyparcel_Shipping_Method->settings['cust_rate'] == 'normal_rate') {self::$easyparcel_email = '';
                    self::$integration_id = '';
                }

                //prevent user select fix Rate but didnt put postcode no result
                if ($WC_Easyparcel_Shipping_Method->settings['cust_rate'] == 'fix_rate' && self::$sender_postcode == '') {self::$sender_postcode = '11950';}

                $f = array(
                    'authentication' => sanitize_text_field(self::$authentication),
                    'api' => sanitize_text_field(self::$integration_id),
                    'pick_contact' => sanitize_text_field(self::$sender_contact_number),
                    'pick_country' => sanitize_text_field(strtolower(self::$sender_country)),
                    'pick_code' => sanitize_text_field(self::$sender_postcode),
                    'pick_state' => sanitize_text_field(self::$sender_state),
                    'send_country' => sanitize_text_field(strtolower($destination['country'])),
                    'send_state' => ($destination['state'] == '') ?  (($destination['country'] == 'sg') ? 'central' : (($destination['country'] == 'my') ? 'png' : '')) : sanitize_text_field($destination['state']),
                    'send_code' => ($destination['postcode'] == '') ? (($destination['country'] == 'sg') ? '058275' : (($destination['country'] == 'my') ? '11950' : '')) :  sanitize_text_field($destination['postcode']),
                    'weight' => floatval($weight),
                    'width' => floatval($width),
                    'height' => floatval($height),
                    'length' => floatval($length),
                );
                array_push($bulk_order['bulk'], $f);
                // print_r($f);die();

                $data = (object) array(
                    'url' => esc_url_raw(self::$getrate_bulk_api_url),
                    'pfs' => http_build_query($bulk_order)
                );

                // error_log( print_r( $data, true ) );
                $response = self::curlPost($data);


                if (is_wp_error($response)) {
                    // Handle errors appropriately
                    return new WP_Error('request_failed', esc_html($response->get_error_message()));
                }

                $body = wp_remote_retrieve_body($response);

                $json = json_decode($body);
                // error_log( print_r( $json, true ) );die();

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($bulk_order);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                /* debug on problem hapen to php newer than 7.2
                if(!empty($json) && isset($json->result[0])){
                    if(sizeof($json->result[0]->rates) > 0){
                        return $json->result[0]->rates;
                    }else{
                        return array();
                    }
                }else{
                    return array();
                }
                */

                // Check if the response is valid and not empty
                if (isset($json->result[0]) && !empty($json->result[0]->rates)) {
                    // Sanitize the rates data before returning
                    // Assume rates is an array of objects or arrays
                    return $json->result[0]->rates;
                } else {
                    return array();
                }

            }

            // if no support sender country
            return array(); // return empty array
        }

        public static function submitOrder($obj)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'bulk' => array()
                );

                $send_point = ''; // EP Buyer Pickup Point
                if( $obj->order->meta_exists('_ep_pickup_point_backend') && !empty($obj->order->get_meta('_ep_pickup_point_backend')) ){
                    $send_point = $obj->order->get_meta('_ep_pickup_point_backend');
                }
                $send_name = $obj->order->get_shipping_first_name().' '.$obj->order->get_shipping_last_name();
                $send_company = $obj->order->get_shipping_company();
                $send_contact = $obj->order->get_billing_phone();
                if( version_compare( WC_VERSION, '5.6', '>=' ) ){
                    ### WC 5.6 and above only can use shipping phone ###
                    if( !empty($obj->order->get_shipping_phone()) ){
                        $send_contact = $obj->order->get_shipping_phone();
                    }
                }
                $send_addr1 = $obj->order->get_shipping_address_1();
                $send_addr2 = $obj->order->get_shipping_address_2();
                $send_city = $obj->order->get_shipping_city();
                $send_code = !empty($obj->order->get_shipping_postcode()) ? $obj->order->get_shipping_postcode() : '';
                $send_state = !empty($obj->order->get_shipping_state()) ? $obj->order->get_shipping_state() : '';
                $send_country = !empty($obj->order->get_shipping_country()) ? $obj->order->get_shipping_country() : '';

                //add on email
                if(self::$addon_email_option == 'yes'){
                    $send_email = $obj->order->get_billing_email();
                }else{
                    $send_email = '';
                }

                //add on sms
                if(self::$addon_sms_option == 'yes'){
                    $sms = 1;
                }else{
                    $sms = 0;
                }

                //add on whatsapp
                if(self::$addon_whatsapp_option == 'yes'){
                    $whatsapp = 1;
                }else{
                    $whatsapp = 0;
                }

                // Currency conversion for item value
                $conversion_rate = self::$conversion_rate; // Assuming you store it as a static variable
                $store_currency = get_woocommerce_currency();
                $ep_currency = (strtolower(self::$sender_country) === 'sg') ? 'SGD' : 'MYR';
                
                // Start with original item value
                $submit_item_value = $obj->item_value;
                
                // Apply conversion if all conditions are met
                if ($store_currency !== $ep_currency 
                    && isset($conversion_rate) 
                    && !empty($conversion_rate) 
                    && is_numeric($conversion_rate) 
                    && floatval($conversion_rate) > 0
                    && isset($obj->item_value) 
                    && is_numeric($obj->item_value) 
                    && floatval($obj->item_value) > 0) {
                    
                    // Convert store currency to EasyParcel currency
                    $submit_item_value = round(floatval($obj->item_value) * floatval($conversion_rate), 2);
                }

                $f = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,

                    'pick_point' => $obj->drop_off_point, # optional
                    'pick_name' => self::$sender_name,
                    'pick_company' => self::$sender_company_name, # optional
                    'pick_contact' => self::$sender_contact_number,
                    'pick_mobile' => self::$sender_alt_contact_number, # optional
                    'pick_unit' => self::$sender_address_1, ### for sg address only ###
                    'pick_addr1' => (strtolower(self::$sender_country) == 'sg') ? self::$sender_address_2 : self::$sender_address_1,
                    'pick_addr2' => (strtolower(self::$sender_country) == 'sg') ? '' : self::$sender_address_2,
                    'pick_addr3' => '', # optional
                    'pick_addr4' => '', # optional
                    'pick_city' => self::$sender_city,
                    'pick_code' => self::$sender_postcode,
                    'pick_state' => self::$sender_state,
                    'pick_country' => self::$sender_country,

                    'send_point' => $send_point, # optional
                    'send_name' => $send_name,
                    'send_company' => $send_company, # optional
                    'send_contact' => $send_contact,
                    'send_mobile' => '', # optional
                    'send_unit' => $send_addr1, ### for sg address only ###
                    'send_addr1' => (strtolower($send_country) == 'sg') ? $send_addr2 : $send_addr1,
                    'send_addr2' => (strtolower($send_country) == 'sg') ? $send_addr3 : $send_addr2, # optional
                    'send_addr3' => '', # optional
                    'send_addr4' => '', # optional
                    'send_city' => $send_city,
                    'send_code' => $send_code, # required
                    'send_state' => (strtolower($send_country) == 'sg') ? 'Singapore' : $send_state,
                    'send_country' => $send_country,
                    
                    'weight' => $obj->weight,
                    'width' => $obj->width,
                    'height' => $obj->height,
                    'length' => $obj->length,
                    'content' => $obj->content,
                    'value' => $submit_item_value, // Use converted value
                    'service_id' => $obj->service_id,
                    'collect_date'	=> $obj->collect_date,
                    'addon_insurance_enabled' => $obj->addon_insurance_enabled,
                    'tax_duty' => $obj->tax_duty,
                    'parcel_category_id' => $obj->parcel_category_id,
                    'sms'	=> $sms, # optional
                    'addon_whatsapp_tracking_enabled'	=> $whatsapp, # optional
                    'send_email'	=> $send_email, # optional
                    'hs_code'	=> '', # optional
                    'REQ_ID'	=> '', # optional
                    'reference'	=> '' # optional
                );
                array_push($bulk_order['bulk'], $f);
                // print_r($f);die();
                // echo "<pre>";print_r($f);echo "</pre>";
                // echo "<pre>";print_r($obj);echo "</pre>";
                // die();

                $data = (object)array();
                $data->url = self::$submit_bulk_order_api_url;
                $data->pfs = http_build_query($bulk_order);
                
                $r = self::curlPost($data);
                if(!is_array($r)) $r = array();
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($f);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if(!empty($json) && isset($json->result[0])){
                    return $json->result[0];
                }else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }

        public static function payOrder($obj)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'bulk' => array()
                );
                
                $f = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'order_no' => $obj->ep_order_number,
                );
                array_push($bulk_order['bulk'], $f);
                // print_r($f);die();

                $data = (object) array();
                $data->url = self::$pay_bulk_order_api_url;
                $data->pfs = http_build_query($bulk_order);

                $r = self::curlPost($data);
                if(!is_array($r)) $r = array();
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($f);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if(!empty($json)){
                    return $json;
                } else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }

        public static function submitBulkOrder($orders)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'bulk' => array()
                );

                foreach ($orders as $obj) {
                    $send_point = ''; // EP Buyer Pickup Point
                    if( $obj->order->meta_exists('_ep_pickup_point_backend') && !empty($obj->order->get_meta('_ep_pickup_point_backend')) ){
                        $send_point = $obj->order->get_meta('_ep_pickup_point_backend');
                    }
                    $send_name = $obj->order->get_shipping_first_name().' '.$obj->order->get_shipping_last_name();
                    $send_company = $obj->order->get_shipping_company();
                    $send_contact = $obj->order->get_billing_phone();
                    if( version_compare( WC_VERSION, '5.6', '>=' ) ){
                        ### WC 5.6 and above only can use shipping phone ###
                        if( !empty($obj->order->get_shipping_phone()) ){
                            $send_contact = $obj->order->get_shipping_phone();
                        }
                    }
                    $send_addr1 = $obj->order->get_shipping_address_1();
                    $send_addr2 = $obj->order->get_shipping_address_2();
                    $send_city = $obj->order->get_shipping_city();
                    $send_code = !empty($obj->order->get_shipping_postcode()) ? $obj->order->get_shipping_postcode() : '';
                    $send_state = !empty($obj->order->get_shipping_state()) ? $obj->order->get_shipping_state() : '';
                    $send_country = !empty($obj->order->get_shipping_country()) ? $obj->order->get_shipping_country() : '';

                    //add on email
                    if(self::$addon_email_option == 'yes'){
                        $send_email = $obj->order->get_billing_email();
                    }else{
                        $send_email = '';
                    }

                    //add on sms
                    if(self::$addon_sms_option == 'yes'){
                        $sms = 1;
                    }else{
                        $sms = 0;
                    }

                    //add on whatsapp
                    if(self::$addon_whatsapp_option == 'yes'){
                        $whatsapp = 1;
                    }else{
                        $whatsapp = 0;
                    }

                    // Currency conversion for item value
                    $conversion_rate = self::$conversion_rate;
                    $store_currency = get_woocommerce_currency();
                    $ep_currency = (strtolower(self::$sender_country) === 'sg') ? 'SGD' : 'MYR';
                    
                    // Start with original item value
                    $submit_item_value = $obj->item_value;
                    
                    // Apply conversion if all conditions are met
                    if ($store_currency !== $ep_currency 
                        && isset($conversion_rate) 
                        && !empty($conversion_rate) 
                        && is_numeric($conversion_rate) 
                        && floatval($conversion_rate) > 0
                        && isset($obj->item_value) 
                        && is_numeric($obj->item_value) 
                        && floatval($obj->item_value) > 0) {
                        
                        // Convert store currency to EasyParcel currency
                        $submit_item_value = round(floatval($obj->item_value) * floatval($conversion_rate), 2);
                    }

                    $f = array(
                        'pick_point' => $obj->drop_off_point, # optional
                        'pick_name' => self::$sender_name,
                        'pick_company' => self::$sender_company_name, # optional
                        'pick_contact' => self::$sender_contact_number,
                        'pick_mobile' => self::$sender_alt_contact_number, # optional
                        'pick_unit' => self::$sender_address_1, ### for sg address only ###
                        'pick_addr1' => (strtolower(self::$sender_country) == 'sg') ? self::$sender_address_2 : self::$sender_address_1,
                        'pick_addr2' => (strtolower(self::$sender_country) == 'sg') ? '' : self::$sender_address_2,
                        'pick_addr3' => '', # optional
                        'pick_addr4' => '', # optional
                        'pick_city' => self::$sender_city,
                        'pick_code' => self::$sender_postcode,
                        'pick_state' => self::$sender_state,
                        'pick_country' => self::$sender_country,

                        'send_point' => $send_point, # optional
                        'send_name' => $send_name,
                        'send_company' => $send_company, # optional
                        'send_contact' => $send_contact,
                        'send_mobile' => '', # optional
                        'send_unit' => $send_addr1, ### for sg address only ###
                        'send_addr1' => (strtolower($send_country) == 'sg') ? $send_addr2 : $send_addr1,
                        'send_addr2' => (strtolower($send_country) == 'sg') ? $send_addr3 : $send_addr2, # optional
                        'send_addr3' => '', # optional
                        'send_addr4' => '', # optional
                        'send_city' => $send_city,
                        'send_code' => $send_code, # required
                        'send_state' => $send_state,
                        'send_country' => $send_country,
                        
                        'weight' => $obj->weight,
                        'width' => $obj->width,
                        'height' => $obj->height,
                        'length' => $obj->length,
                        'content' => $obj->content,
                        'value' => $submit_item_value, // Use converted value
                        'service_id' => $obj->service_id,
                        'collect_date'	=> $obj->collect_date,
                        'sms'	=> $sms, # optional
                        'addon_whatsapp_tracking_enabled'	=> $whatsapp, # optional
                        'send_email'	=> $send_email, # optional
                        'hs_code'	=> '', # optional
                        'REQ_ID'	=> '', # optional
                        'reference'	=> '' # optional
                    );

                    array_push($bulk_order['bulk'], $f);
                    
                }
                
                // print_r($f);die();
                // echo "<pre>";print_r($orders);echo "</pre>";
                // echo "<pre>";print_r($bulk_order);echo "</pre>";
                // die();

                $data = (object)array();
                $data->url = self::$submit_bulk_order_api_url;
                $data->pfs = http_build_query($bulk_order);
                
                $r = self::curlPost($data);
                if(!is_array($r)) $r = array();
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if(!empty($json)){
                    return $json;
                }else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }

        public static function payBulkOrder($orders)
        {
            if(self::countryValidate()){

                $bulk_order = array(
                    'authentication' => self::$authentication,
                    'api' => self::$integration_id,
                    'bulk' => array()
                );

                foreach ($orders as $order_no) {
                    $f = array(
                        'order_no' => $order_no,
                    );

                    array_push($bulk_order['bulk'], $f);
                }

                // print_r($f);die();
                // echo "<pre>";print_r($orders);echo "</pre>";
                // echo "<pre>";print_r($bulk_order);echo "</pre>";
                // die();

                $data = (object) array();
                $data->url = self::$pay_bulk_order_api_url;
                $data->pfs = http_build_query($bulk_order);

                $r = self::curlPost($data);
                if(!is_array($r)) $r = array();
                $json = (!empty($r['body'])) ? json_decode($r['body']) : '';

                // echo '<pre>';print_r($data);echo '</pre>';
                // echo '<pre>';print_r($json);echo '</pre>';
                // die();

                if (!empty($json)) {
                    return $json;
                } else {
                    return array();
                }
            }

            // if no support sender country
            return array(); // return empty array
        }

        public static function getAddressDefault($api,$country)
        {
			$host = 'http://connect.easyparcel.'.strtolower($country);
            self::$get_default_address = $host . '/?ac=EPAccountVerification';
			
			$data = (object) array();
			$data->url = self::$get_default_address;
			$data->pfs = http_build_query(array(
				'api' => $api,
			));

			$r = self::curlPost($data);
			if(!is_array($r)) $r = array();
			$json = (!empty($r['body'])) ? json_decode($r['body']) : '';


			if (!empty($json)) {
				return $json;
			} else {
				return array();
			}

        }

        public static function getCourierList()
        {	
			$data = (object) array();
			$data->url = self::$couriers_url;
			$data->pfs = http_build_query(array(
				'api' => self::$integration_id,
			));

			$r = self::curlPost($data);
			if(!is_array($r)) $r = array();
			$json = (!empty($r['body'])) ? json_decode($r['body']) : '';


			if (!empty($json)) {
				return $json->result;
			} else {
				return array();
			}

        }

        public static function getCourierDropoffList($cid,$state)
        {
			$data = (object) array();
			$data->url = self::$courier_dropoff_url;
			$data->pfs = http_build_query(array(
				'api' => self::$integration_id,
				'cid' => $cid,
				'state' => $state,
			));

			$r = self::curlPost($data);
			if(!is_array($r)) $r = array();
			$json = (!empty($r['body'])) ? json_decode($r['body']) : '';


			if (!empty($json)) {
				return $json->result;
			} else {
				return array();
			}

        }
    }
}