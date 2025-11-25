<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Easyparcel_Shipping_Method' ) ) {

    defined( 'EASYPARCEL_MODULE_SETUP_PATH') || define( 'EASYPARCEL_MODULE_SETUP_PATH'  , EASYPARCEL_MODULE_PATH . 'setup/' );
    defined( 'EASYPARCEL_MODULE_SETUP_URL') || define( 'EASYPARCEL_MODULE_SETUP_URL'  , EASYPARCEL_MODULE_URL . 'setup/' );

    class WC_Easyparcel_Shipping_Method extends WC_Shipping_Method {

        public $plugin_url;
 
        /**
         * Constructor for your shipping class
         *
         * @access public
         * @return void
         */
        public function __construct($instance_id = 0) {
            $this->id                 = 'easyparcel';
            $this->title              = __('EasyParcel Shipping', 'easyparcel-shipping');
            $this->method_title       = __('EasyParcel Shipping', 'easyparcel-shipping');
            $this->method_description = $this->plugin_description();
            $this->instance_id        = empty($instance_id) ? 0 : absint($instance_id);
            $this->supports           = array(
                                            'shipping-zones',
                                            'settings',
                                            'instance-settings',
                                        );
            $this->enabled            = "yes";
            $this->plugin_url         = admin_url() . '/admin.php?page=wc-settings&tab=shipping&section=easyparcel';
            $this->init();
        }

        /**
         * Init your settings
         *
         * @access public
         * @return void
         */
        function init() {
            // Load the settings API
            $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
            $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

            // Save settings in admin if you have any defined
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this , 'process_admin_options' ) );
            add_action('admin_enqueue_scripts', array($this, 'admin_shipping_init'), 30);
            add_filter('woocommerce_cart_shipping_method_full_label', array(__CLASS__, 'wc_shipping_render_courier_logo'), 10, 2);
        }

        public function admin_options(){
            $this->loadCreditBalance(); 
            if($this->instance_id > 0){
                global $hide_save_button;
                $hide_save_button = true;
                if($this->settings['enabled'] != 'yes'){

                    echo "You not yet enable EasyParcel shipment methods<br>";
                    echo "Please <a href='".esc_url($this->plugin_url)."'>click here</a> to enable and setup EasyParcel shipment methods.<br>";
                    
                }elseif($this->settings['legacy_courier_setting'] == 'yes'){
                    echo "You are currently enable Legacy Courier Setting<br>";
                    echo "Please <a href='".esc_url($this->plugin_url)."'>click here</a> to disable the legacy courier setting in-order to continue.<br>";
                }else{
                    include_once EASYPARCEL_MODULE_SETUP_PATH . 'html_shipping_zone_setup.php';
                }
                
            }else{
                self::generate_settings_html();
            }
        }

        function admin_shipping_init($hook) {
				// Enqueue the main stylesheet
				wp_enqueue_style('easyparcel_order_list_styles', EASYPARCEL_MODULE_SETUP_URL . 'admin.css', array(), EASYPARCEL_VERSION);

				// Enqueue the script with a unique handle
				$handle = 'easyparcel-admin-shipping-js';
				wp_enqueue_script($handle, EASYPARCEL_MODULE_SETUP_URL . 'admin_shipping.js', array('jquery'), EASYPARCEL_VERSION, true);

				// Add the data directly to the script
				$script_data = array(
					'ajax_url' => admin_url('admin-ajax.php'),
					'nextNonce' => wp_create_nonce('ajax-nonce'),
					'courier_service' => null !== $this->get_option('courier_service') ? $this->get_option('courier_service') : '',
					'sender_state' => null !== $this->get_option('sender_state') ? $this->get_option('sender_state') : ''
				);
				wp_localize_script($handle, 'epSettings', array(
                    'storeCurrency' => get_woocommerce_currency(),
                ));
				wp_localize_script($handle, 'obj', $script_data);
		}

        function init_form_fields() {
            $this->form_fields = array(

                'ep_plugin_title' => array(
                    'title' => __('Easyparcel Plugin', 'easyparcel-shipping'),
                    'type' => 'title',
                    'desc' => '',
                ),

                'enabled' => array(
                    'title' => __('Enable', 'easyparcel-shipping'),
                    'type' => 'checkbox',
                    // 'description' => __('Enable to activate EasyParcel shipping method.', 'easyparcel-shipping'),
                    'label' => __('Enable to activate easyparcel shipping method', 'easyparcel-shipping'),
                    // 'desc_tip' => true,
                    'default' => 'no',
                ),

                'legacy_courier_setting' => array(
                    'title' => __('Legacy Courier Setting', 'easyparcel-shipping'),
                    'type' => 'checkbox',
                    // 'description' => __('Enable to activate Legacy courier setting.', 'easyparcel-shipping'),
                    'label' => __('Enable to activate legacy courier setting', 'easyparcel-shipping'),
                    // 'desc_tip' => false,
                    'default' => 'no',
                ),

                'show_all_couriers' => array(
                    'title' => __('Show all Courier available', 'easyparcel-shipping'),
                    'type' => 'checkbox',
                    // 'description' => __('Enable to activate Legacy courier setting.', 'easyparcel-shipping'),
                    'label' => __('Enable to show all courier during fulfillment', 'easyparcel-shipping'),
                    // 'desc_tip' => true,
                    'default' => 'yes',
                ),

                'sender_country_option' => array(
                    'title' => __('Which country do you wish to send from?', 'easyparcel-shipping'),
                    'type' => 'title',
                    'desc' => '',
                ),
                'sender_country' => array(
                    'title' => __('<font color="red">*</font>Country', 'easyparcel-shipping'),
                    'type' => 'select',
                    'default' => 'NONE',
                    'options' => array('NONE' => 'Kindly Choose your country', 'MY' => 'Malaysia', 'SG' => 'Singapore'),
                    'required' => true,
                ),

                'sender_detail' => array(
                    'title' => __('Sender Details', 'easyparcel-shipping'),
                    'type' => 'title',
                    'desc' => '',
                ),
                'easyparcel_email' => array(
                    'title' => __('<font color="red">*</font>EasyParcel Login Email', 'easyparcel-shipping'),
                    'type' => 'text',
                    'description' => __('Enter your registered EasyParcel login email here. If you do not have an EasyParcel account, sign up for free at <a href="https://easyparcel.com" target="_blank">easyparcel.com</a>', 'easyparcel-shipping'),
                    'desc_tip' => true,
                    'default' => '',
                    'required' => true,
                ),

                'integration_id' => array(
                    'title' => __('<font color="red">*</font>Integration ID', 'easyparcel-shipping'),
                    'type' => 'text',
                    'description' => __('Here’s how to get your integration ID: <br/>
                                        1. Login to your EasyParcel Account<br/>
                                        2. Click on "Dashboard" - "Integrations" - "Add New Store"<br/>
                                        3. Choose "WooCommerce" <br/>
                                        4. Fill in required details <br/>
                                        5. Copy the Integration ID and paste it here.', 'easyparcel-shipping'),
                    'desc_tip' => true,
                    'required' => true,
                ),
                'credit_balance' => array(
                    'title' => __('EasyParcel Credit Balance: ', 'easyparcel-shipping'),
                    'type' => 'title',
                ),
				'api_version' => array(
                    'title' => __('API Version: ', 'easyparcel-shipping'),
                    'type' => 'title',
                ),
                'sender_name' => array(
                    'title' => __('<font color="red">*</font>Name', 'easyparcel-shipping'),
                    'type' => 'text',
                    'default' => '',
                    'required' => true,
                ),
                'sender_contact_number' => array(
                    'title' => __('<font color="red">*</font>Contact Number', 'easyparcel-shipping'),
                    'type' => 'text',
                    'default' => '',
                    'placeholder' => 'key in with countrycode (MY)60 / (SG)65',
                    'required' => true,
                ),
                'sender_alt_contact_number' => array(
                    'title' => __('Alt. Contact Number', 'easyparcel-shipping'),
                    'type' => 'text',
                    'default' => '',
                    'placeholder' => 'key in with countrycode (MY)60 / (SG)65',
                ),
                'sender_company_name' => array(
                    'title' => __('Company Name', 'easyparcel-shipping'),
                    'type' => 'text',
                    'default' => '',
                ),
                'sender_address_1' => array(
                    'title' => __('<font color="red">*</font>Address Line 1', 'easyparcel-shipping'),
                    'type' => 'text',
                    'default' => get_option('woocommerce_store_address'),
                    'required' => true,
                ),
                'sender_address_2' => array(
                    'title' => __('Address Line 2', 'easyparcel-shipping'),
                    'type' => 'text',
                    'default' => get_option('woocommerce_store_address_2'),
                    'required' => true,
                ),
                'sender_city' => array(
                    'title' => __('<font color="red">*</font>City', 'easyparcel-shipping'),
                    'type' => 'text',
                    'default' => get_option('woocommerce_store_city'),
                    'required' => true,
                ),
                'sender_postcode' => array(
                    'title' => __('<font color="red">*</font>Postcode', 'easyparcel-shipping'),
                    'type' => 'text',
                    'default' => get_option('woocommerce_store_postcode'),
                    'required' => true,
                ),
                'sender_state' => array(
                    'title' => __('<font color="red">*</font>State', 'easyparcel-shipping'),
                    'type' => 'select',
                    'description' => __('state', 'easyparcel-shipping'),
                    'default' => '',
                    'desc_tip' => true,
                    'required' => true,
                    'options' => array(
                        'jhr' => 'Johor',
                        'kdh' => 'Kedah',
                        'ktn' => 'Kelantan',
                        'kul' => 'Kuala Lumpur',
                        'lbn' => 'Labuan',
                        'mlk' => 'Melaka',
                        'nsn' => 'Negeri Sembilan',
                        'phg' => 'Pahang',
                        'prk' => 'Perak',
                        'pls' => 'Perlis',
                        'png' => 'Penang',
                        'sbh' => 'Sabah',
                        'srw' => 'Sarawak',
                        'sgr' => 'Selangor',
                        'trg' => 'Terengganu',
                        'pjy' => 'Putra Jaya',
                    ),
                ),

                // additional option
                'addon_service_setting' => array(
                    'title' => __('Add On Service Settings', 'easyparcel-shipping'),
                    'type' => 'title',
                    'desc' => '',
                ),
                'addon_email_option' => array(
                    'title' => __('Tracking Email', 'easyparcel-shipping'),
                    'type' => 'checkbox',
                    'description' => __('EasyParcel will automatically send tracking details to receiver via email when your fulfillment is made for RM0.05.', 'easyparcel-shipping'),
                    'label' => __('Enable Tracking Email.', 'easyparcel-shipping'),
                    'desc_tip' => true,
                    'default' => 'no',
                ),
                'addon_sms_option' => array(
                    'title' => __('Tracking SMS', 'easyparcel-shipping'),
                    'type' => 'checkbox',
                    'description' => __('EasyParcel will automatically send tracking details to receiver via SMS when your  fulfillment is made for RM0.20.', 'easyparcel-shipping'),
                    'label' => __('Enable Tracking SMS', 'easyparcel-shipping'),
                    'desc_tip' => true,
                    'default' => 'no',
                ),
                'addon_whatsapp_option' => array(
                    'title' => __('Tracking Whatsapp', 'easyparcel-shipping'),
                    'type' => 'checkbox',
                    'description' => __('EasyParcel will automatically send tracking details to receiver via whatsapp when your  fulfillment is made for RM0.20.', 'easyparcel-shipping'),
                    'label' => __('Enable Tracking Whatsapp', 'easyparcel-shipping'),
                    'desc_tip' => true,
                    'default' => 'no',
                ),
				 'order_status_update_setting' => array(
                    'title' => __('Order Status Update Settings', 'easyparcel-shipping'),
                    'type' => 'title',
                    'desc' => '',
                ),
                'order_status_update_option' => array(
                    'title' => __('Order Status Auto Update', 'easyparcel-shipping'),
                    'type' => 'checkbox',
                    'description' => __('Order status will be updated as "completed" automatically once fulfillment done.', 'easyparcel-shipping'),
                    'label' => __('Enable order status auto update.', 'easyparcel-shipping'),
                    'desc_tip' => true,
                    'default' => 'no',
                ),
				 'currency_conversion_setting' => array(
                    'title' => __('Currency Conversion', 'easyparcel-shipping'),
                    'type' => 'title',
                    'desc' => '',
                ),
               'conversion_rate' => array(
					'title' => __('Currency Conversion Rate', 'easyparcel-shipping'),
					'type' => 'number',
					'description' => sprintf(
						__('Convert %1$s to <span id="ep-currency-target">%2$s</span> for EasyParcel shipping rates and parcel value. Enter how many <span id="ep-currency-target-2">%2$s</span> equals 1 %1$s (e.g., if 1 %1$s = 4.20 <span id="ep-currency-target-3">%2$s</span>, enter "4.2").', 'easyparcel-shipping'),
						get_woocommerce_currency(),
						($this->get_option('sender_country', 'MY') === 'SG') ? 'SGD' : 'MYR'
					),
					'desc_tip' => false,
					'default' => '',
					'placeholder' => __('', 'easyparcel-shipping'),
					'custom_attributes' => array(
						'step' => '0.01',
						'min' => '0'
					),
                ),
				 'save_section' => array(
                    'title' => '',
                    'type' => 'title',
                    'desc' => '',
                ),

                
            );
			
            // $this->admin_shipping_init();
            // add_action('admin_enqueue_scripts', array($this, 'admin_shipping_init'), 30);
        } 

        public function process_admin_options() {
            $this->init_settings();
            $post_data = $this->get_post_data();
            if(!empty($post_data)){
                foreach($post_data as $key => $value){
                    $post_data[$key] = sanitize_option($key, $value);
                }
                foreach ($this->get_form_fields() as $key => $field) {
                    if ('title' !== $this->get_field_type($field)) {
                        try {
                            $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                        } catch (Exception $e) {
                            $this->add_error($e->getMessage());
                        }
                    }
                }
                 //check if base country first
                $WC_Country = new WC_Countries();
                if(strtolower($WC_Country->get_base_country()) != strtolower($this->settings['sender_country'])){
                    $this->init_settings();
                    WC_Admin_Settings::add_error('Nothing changed. The selected country is not same with WooCommerce General Store country'); //Used for debugging
                    return false;
                }

                update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
                return $this->validate_save_settings();
            }
            return true;
        }

        public function validate_save_settings() {
            $err = 0;
            if ($this->settings['enabled'] == 'no') {
                WC_Admin_Settings::add_error("EasyParcel Shipping is disable."); //Used for debugging
                $err++;
            } else {
                if (empty($this->settings['sender_country']) || $this->settings['sender_country'] == 'NONE') {
                    WC_Admin_Settings::add_error("Choose one of the country"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_name'])) {
                    WC_Admin_Settings::add_error("Name cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_contact_number'])) {
                    WC_Admin_Settings::add_error("Contact number cannot be blank"); //Used for debugging
                    $err++;
                } else {
                    $my = '/^(\+?6?01)[02-46-9]-*[0-9]{7}$|^(\+?6?01)[1]-*[0-9]{8}$/';
                    $sg = '/\65(6|8|9)[0-9]{7}$/';
                    if (!empty($this->settings['sender_country']) && $this->settings['sender_country'] != 'NONE') {
                        if ($this->settings['sender_country'] == 'MY') {
                            if (!preg_match($my,$this->settings['sender_contact_number'])) {
                                $this->settings['sender_contact_number'] = '';
                                WC_Admin_Settings::add_error("Contact number invalid"); //Used for debugging
                                $err++;
                            }
                        }else{
                            if (!preg_match($sg,$this->settings['sender_contact_number'])) {
                                $this->settings['sender_contact_number'] = '';
                                WC_Admin_Settings::add_error("Contact number invalid"); //Used for debugging
                                $err++;
                            }
                        }
                    }
                }
                if (empty($this->settings['easyparcel_email'])) {
                    WC_Admin_Settings::add_error("EasyParcel Login Email cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_address_1'])) {
                    WC_Admin_Settings::add_error("Address 1 cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_city'])) {
                    WC_Admin_Settings::add_error("City cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['sender_state'])) {
                    WC_Admin_Settings::add_error("State/Zone cannot be blank"); //Used for debugging
                    $err++;
                }

                if (empty($this->settings['sender_postcode'])) {
                    WC_Admin_Settings::add_error("Postcode cannot be blank"); //Used for debugging
                    $err++;
                }
                if (empty($this->settings['integration_id'])) {
                    WC_Admin_Settings::add_error("Integration ID cannot be blank"); //Used for debugging
                    $err++;
                }
                if ($err > 0) {
                    WC_Admin_Settings::add_error("EasyParcel Shipping method may not be work properly without details above."); //Used for debugging
                }else{
                    WC_Admin_Settings::save_fields($this->settings);
                    if (!class_exists('Easyparcel_Shipping_API')) {
                        // Include Easyparcel API
                        include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
                    }
                    if (!empty($this->settings['integration_id'])) {
						
                        Easyparcel_Shipping_API::init();
                        $auth = Easyparcel_Shipping_API::auth();
						$_SESSION['ep_auth_status'] =  $auth;
						
                        if ($auth == 'Success.') {
                            $this->track_plugin_setup();
                            add_action('admin_notices', function () {
                                echo '<div id="message" class="notice notice-success is-dismissible"><p>Please proceed to setup your preferred shipping courier and zone <a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping')) . '">HERE</a></p></div>';
                            });
                        } else {
                            add_action('admin_notices', function ($auth) {
                                echo '<div id="message" class="notice notice-error is-dismissible"><p>' . esc_html($auth) . ' You have inserted invalid login email OR integration_id</p></div>';
                            });
                        }

                        // get credit balance if user at shipping settings page
                        $this->loadCreditBalance();
                    }
                }
            }
        }

        private function track_plugin_setup() {
            // Get store information
            $store_email = get_option('woocommerce_email_from_address');
            if (empty($store_email)) {
                $store_email = get_option('admin_email');
            }
            
            $store_url = get_site_url();
            $store_country = get_option('woocommerce_default_country');
            $store_name = get_bloginfo('name');
            $country_code = explode(':', $store_country)[0];

            $api_data = array(
                'store_name'       => $store_name,
                'email'      => $store_email,
                'url'        => $store_url,
                'country'    => strtolower($country_code),
                'action_type'  => 3,
                'integration_id'   => $this->settings['phone_number'],
                'platform' => 'woocommerce',
                'phone_no'    => isset($this->settings['sender_contact_number']) ? $this->settings['sender_contact_number'] : ''
            );


            $api_url = 'https://api.easyparcel.com/open_api/integration_installation_tracker';
            
            wp_remote_post($api_url, array(
                'method'      => 'POST',
                'timeout'     => 15,
                'httpversion' => '1.0',
                'blocking'    => true, // Changed to true so we can capture the response
                'headers'     => array(
                    'Content-Type' => 'application/json',
                ),
                'body'        => json_encode($api_data)
            ));
        }

        public function loadCreditBalance(){
            if (!class_exists('Easyparcel_Shipping_API')) {
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }
            
            // Direct sanitization in condition - preferred for security scanners
            if (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'wc-settings' &&
                isset($_GET['tab']) && sanitize_text_field(wp_unslash($_GET['tab'])) === 'shipping' &&
                isset($_GET['section']) && sanitize_text_field(wp_unslash($_GET['section'])) === 'easyparcel') {
                
                // Skip API calls in test environments
                if (isset($_SERVER['HTTP_USER_AGENT']) && strpos(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 'HeadlessChrome') !== false) {
                    $this->form_fields['credit_balance']['title'] = "EasyParcel Credit Balance: Test Mode";
                    return;
                }
                
                // Check if we have required settings
                if (empty($this->settings['integration_id']) || empty($this->settings['sender_country'])) {
                    $this->form_fields['credit_balance']['title'] = "EasyParcel Credit Balance: Configuration Required";
                    return;
                }
                
                // Always make fresh API call (no caching)
                try {
                    $balance = Easyparcel_Shipping_API::getCreditBalance($this->settings['integration_id'], $this->settings['sender_country']);
					$APIVersion = Easyparcel_Shipping_API::getAPIVersion();
                    
                    // Check if balance is valid
                    if (empty($balance) || is_wp_error($balance)) {
                        $balance = 'Unable to load balance, please contact easyparcel support';
                    }
                    
                } catch (Exception $e) {
                    $balance = 'Connection failed, please contact easyparcel support';
                }
                
                $topupUrl = "https://app.easyparcel.com/" . strtolower($this->settings['sender_country']) ."/en/account/topup";
                $autoTopupUrl = "https://app.easyparcel.com/" . strtolower($this->settings['sender_country']) . "/en/account/auto-topup";
                $this->form_fields['credit_balance']['title'] = "EasyParcel Credit Balance: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . esc_html($balance) . "&nbsp;&nbsp;&nbsp;&nbsp;<a target='_blank' href='" . esc_url($topupUrl) . "'>Top Up</a> | <a target='_blank' href='" . esc_url($autoTopupUrl) . "'>Set Up Auto Top Up</a>";
				
				$country = strtolower($this->settings['sender_country']);
				
				if (strtolower($APIVersion) === 'next gen') {
					$badge_color = '#28a745'; // green
					$badge_text  = 'NextGen';
				} else if(strtolower($APIVersion) === 'classic') {
					$badge_color = '#007bff'; // blue
					$badge_text  = 'Classic';
				}

				// Build badge HTML
				$badge_html = "<span style='background-color: {$badge_color}; color: #fff; padding: 3px 8px; border-radius: 6px; font-size: 14px; font-weight: bold; margin-left: 8px;'>{$badge_text}</span>";

				// Build API Version title
				$ApiVersion  = "API Version: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $badge_html;
				
				$ApiVersion .="<br><br><small>Please take note, after changing the version, you need to reset the preferred courier and preferred drop off point of the auto fulfilment.</small>";
				
				if ($country === 'sg') {
					$nextgen_link = "https://github.com/easyparcel/nextgen-integration-doc/blob/main/IronManSwitches/Shopify_SG.md";
				} else {
					$nextgen_link = "https://github.com/easyparcel/nextgen-integration-doc/blob/main/IronManSwitches/Shopify_MY.md";
				}
				
				if (strtolower($APIVersion) === 'classic') {
					$ApiVersion .= "<br><small>To change to NextGen version, you can refer <a href='" . esc_url($nextgen_link) . "' target='_blank'>here</a>.</small>";
				}
				
				$this->form_fields['api_version']['title'] = $ApiVersion;
				
            }
        }

        public static function wc_add_shipping_method($methods) {
            $methods['easyparcel'] = __CLASS__;
            return $methods;
        }

        public static function wc_shipping_render_courier_logo($label, $method) {
			if($method->get_method_id() == 'easyparcel'){
				$meta_data = $method->get_meta_data();
				$logo_url = '';

				// Check if meta_data has the logo
				if(isset($meta_data['ep_courier_logo']) && !empty($meta_data['ep_courier_logo'])) {
					$logo_url = $meta_data['ep_courier_logo'];
				} 
				// Fallback to the property if meta_data doesn't have it
				else if(isset($method->logo) && !empty($method->logo)) {
					$logo_url = $method->logo;
				}
                if(!empty($logo_url)) {
					$logo = '<img src="'.$logo_url.'" width="60" height="40" style="display:inline-block;border-radius:4px;border:1px solid #ccc;" /> ';
					$label = $logo . $label;
                }
			}
			return $label;
		}

        private function plugin_description(){
            $setting = get_option('woocommerce_easyparcel_settings');
            $desc = "Allow your buyers to choose their preferred courier option.<br>";
            if (!empty($setting)) {
                if ($setting['enabled'] == 'yes' && !empty($setting['integration_id']) && !empty($setting['easyparcel_email'])) {
                    $desc .= 'Setup shipping courier options and zones here.  <a href="' . admin_url('admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping') . '">HERE</a><br>';
                }
            }
            $desc .= '<a href="'.admin_url('admin.php?page=easyparcel-auto-fulfilment-setting').'">Auto Fulfillment Setting</a><br>';
            return $desc;
        }

        /**
         * calculate_shipping function.
         *
         * @access public
         * @param array $package
         * @return void
         */
        public function calculate_shipping( $package = array() ) {
            $destination = $package["destination"];

            $rates = $this->get_easyparcel_rate($destination, $package["contents"]);
            $zone_setting = $this->get_shipping_zone_setting($destination);

            $shipping_rate_list = array();

            if( $zone_setting['setting_type'] == 'all'){
                foreach($rates as $rate){
                    if(str_contains($rate->service_name, 'Drop-Off')) continue;
                    $this->add_rate(self::doCourierCalculation($this->checkout_page_compile_rate_format($rate), $zone_setting['settings'], $package));
                }
            }
            
            elseif( $zone_setting['setting_type'] == 'cheapest'){
                $shipping_rate = null;
                foreach($rates as $rate){
                    if(str_contains(strtolower($rate->service_name), 'drop-off') || 
                       str_contains(strtolower($rate->service_name), 'drop off')) {
                        continue;
                    }
                    if($shipping_rate == null){
                        $shipping_rate = $this->checkout_page_compile_rate_format($rate);
                    }elseif($shipping_rate['cost'] > $rate->price){
                        $shipping_rate = $this->checkout_page_compile_rate_format($rate);
                    }
                }
                $this->add_rate(self::doCourierCalculation($shipping_rate, $zone_setting['settings'], $package));
            }
            
            elseif( $zone_setting['setting_type'] == 'couriers'){
                $duplicate_check = array();
                foreach($zone_setting['settings'] as $setting_details){
                    foreach($rates as $rate){
                        if($rate->courier_id != $setting_details['courier_id']) continue;
                        if(in_array($rate->courier_id, $duplicate_check)) continue;
                        if(str_contains($rate->service_name, 'Drop-Off')) continue;

                        if($setting_details['is_legacy']){
                            $current_rate = $this->checkout_page_compile_rate_format($rate);
                            if($current_rate['service_name'] == $setting_details['service_name']){
                                $current_rate['selected_dropoff_point'] = $setting_details['courier_dropoff_point'];
                                $this->add_rate(self::doCourierCalculation($current_rate, $setting_details, $package));
                                array_push($duplicate_check, $courier_id);
                            }
                        }else{
                            $current_rate = $this->checkout_page_compile_rate_format($rate);
                            $this->add_rate(self::doCourierCalculation($current_rate, $setting_details, $package));
                            array_push($duplicate_check, $courier_id);
                        }
                    }
                }
            }
        }

        public function get_order_shipping_price_list($order_id){
            $order = wc_get_order($order_id);
            
            $destination = array();
            $destination['country'] = $order->get_shipping_country();
            $destination['state'] = $order->get_shipping_state();
            $destination['postcode'] = $order->get_shipping_postcode();

            $rates = $this->get_easyparcel_rate($destination, $order->get_items());
            $zone_setting = $this->get_shipping_zone_setting($destination);         
            $shipping_rate_list = array();

            $show_all_couriers = $this->get_option('show_all_couriers', 'yes');

            // Check if "show all couriers" is enabled
            if($show_all_couriers == 'yes'){
                foreach($rates as $rate){
                    $compiled_rate = $this->compile_rate_format($rate);
                    array_push($shipping_rate_list, $compiled_rate);
                }
                return $shipping_rate_list;
            }

            if( $zone_setting['setting_type'] == 'all'){
                wc_get_logger()->info('Processing: show all couriers', array('source' => 'easyparcel-method-debug'));
                foreach($rates as $rate){
                    $compiled_rate = $this->compile_rate_format($rate);
                    array_push($shipping_rate_list, $compiled_rate);

                }
            }

            elseif( $zone_setting['setting_type'] == 'cheapest'){
                $shipping_rate = null;
                foreach($rates as $rate){
                    if($shipping_rate == null){
                        $shipping_rate = $this->compile_rate_format($rate);
                    }elseif($shipping_rate['cost'] > $rate->price){
                        $shipping_rate = $this->compile_rate_format($rate);
                    }
                }
                if ($shipping_rate) {
                    array_push($shipping_rate_list, $shipping_rate);
                }
            }
            elseif( $zone_setting['setting_type'] == 'couriers'){
                $duplicate_check = array();
                foreach($zone_setting['settings'] as $setting_details){
                    foreach($rates as $rate){
                        if($rate->courier_id != $setting_details['courier_id']) continue;
                        
                        // Create unique identifier using courier_id + service_name combination
                        $current_rate = $this->compile_rate_format($rate);
                        $unique_identifier = $rate->courier_id . '_' . $current_rate['service_name'];
                        
                        if(in_array($unique_identifier, $duplicate_check)) continue;                                                  

                        // Just match courier_id, show ALL services from the courier
                        $current_rate['selected_dropoff_point'] = isset($setting_details['courier_dropoff_point']) ? $setting_details['courier_dropoff_point'] : '';                             
                        array_push($shipping_rate_list, $current_rate);                             
                        array_push($duplicate_check, $unique_identifier);                     
                    }                 
                }             
            }                         

            return $shipping_rate_list;
        }


        protected function get_easyparcel_rate($destination, $product_list){

            $items = $this->compile_items_for_ep_rate($product_list);

            if (!class_exists('Easyparcel_Shipping_API')) {
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }

            Easyparcel_Shipping_API::init();
            $rates = Easyparcel_Shipping_API::getShippingRate($destination, $items);

            // Initialize logger and context
            $logger = wc_get_logger();
            $context = array('source' => 'easyparcel-shipping');

            // Check if $rates is a WP_Error object
            if (is_wp_error($rates)) {
                wc_get_logger()->error('get_easyparcel_rate - WP_Error detected', array(
                    'source' => 'easyparcel-method-debug',
                    'error_message' => $rates->get_error_message(),
                    'error_code' => $rates->get_error_code()
                ));
                return array();
            }

            // Check if $rates is an array before sorting
            if (!is_array($rates)) {
                wc_get_logger()->warning('get_easyparcel_rate - Non-array response', array(
                    'source' => 'easyparcel-method-debug',
                    'response_type' => gettype($rates),
                    'response_data' => $rates
                ));
                return array();
            }
            
            // Only sort if we have a valid array - PHP 7.0+ compatible
            if (!empty($rates)) {
                usort($rates, function($a, $b) {
                    return ($a->cid > $b->cid) ? 1 : -1;
                });
            }

            return $rates;
        }

        protected function get_cheaper_rate($rates) {
            if (empty($rates)) {
                return null;
            }

            $cheapest_rate = null;
            $lowest_cost = PHP_FLOAT_MAX;

            foreach ($rates as $rate) {
                // Handle both array and object formats
                $cost = isset($rate['cost']) ? floatval($rate['cost']) : (isset($rate->cost) ? floatval($rate->cost) : PHP_FLOAT_MAX);
                
                if ($cost < $lowest_cost) {
                    $lowest_cost = $cost;
                    $cheapest_rate = $rate;
                }
            }

            return $cheapest_rate;
        }

        public function get_admin_shipping($order_id) {
            $order = wc_get_order($order_id);

            $destination = array();
            $destination['country'] = $order->get_shipping_country();
            $destination['state'] = $order->get_shipping_state();
            $destination['postcode'] = $order->get_shipping_postcode();

            $items = array();
            $product_factory = new WC_Product_Factory();

            foreach ($order->get_items() as $item) {
                $product = $product_factory->get_product($item["product_id"]);

                for ($i = 0; $i < $item["quantity"]; $i++) {
                    $items[] = array(
                        "weight" => $this->weightToKg($product->get_weight()),
                        "height" => $this->defaultDimension($this->dimensionToCm($product->get_height())),
                        "width" => $this->defaultDimension($this->dimensionToCm($product->get_width())),
                        "length" => $this->defaultDimension($this->dimensionToCm($product->get_length())),
                    );
                }
            }

            $i = 0;
            $weight = 0;
            foreach ($items as $item) {
                $weight += is_numeric($items[$i]['weight']) ? $items[$i]['weight'] : 0;
                $i++;
            }

            if (!class_exists('Easyparcel_Shipping_API')) {
                // Include Easyparcel API
                include_once 'easyparcel_api.php';
            }
            Easyparcel_Shipping_API::init();
            ### call EP Get Rate API ###
            $rates = Easyparcel_Shipping_API::getShippingRate($destination, $items, $weight);

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

                    $dropoff_point = array();
                    if (strtoupper($this->settings['sender_country']) == 'MY') {
                        $dropoff_point = $rate->dropoff_point;
                    }else if (strtoupper($this->settings['sender_country']) == 'SG') {
                        $dropoff_point = $rate->dropoff_point;
                    }

                    $shipping_rate = array(
                        'courier_id' => $rate->courier_id,
                        'id' => $rate->service_id,
                        'service_name' => $rate->service_name,
                        'label' => $courier_service_label,
                        'cost' => $rate->price,
                        'dropoff_point' => $dropoff_point,
                        'easycover' => $rate->addon_insurance_available,
                        'basic_coverage' => $rate->basic_insurance_max_value,
                        'basic_coverage_currency' => $rate->basic_insurance_currency,
                    );
                    array_push($shipping_rate_list, $shipping_rate);
                }
            }

            ### Filter based on setting - S ###
            if (!class_exists('EP_Shipping_Zones')) {
                // Include Easyparcel API
                include_once EASYPARCEL_DATASTORE_PATH .'ep_shipping_zones.php';
            }
            

            $package = array();
            $package['destination']['country'] = $order->get_shipping_country();
            $package['destination']['state'] = $order->get_shipping_state();
            $package['destination']['postcode'] = $order->get_shipping_postcode();

            $EP_Shipping_Zones = new EP_Shipping_Zones();
            $zone = $EP_Shipping_Zones->get_zone_matching_package($package);
            $zone_courier = $zone->get_couriers();
            

            $courier = array();
            if (!empty($zone_courier)) { //got zone + courier services only filter, else use all courier
                foreach ($zone_courier as $k => $v) {
                    if ($v['status'] != 0) {
                        $courier[$v['service_name']] = array(
                            'id' => $v['service_id'],
                            'label' => $v['courier_display_name'],
                            'service_name' => $v['service_name'],
                            // 'cost' => $rate->price,
                            'meta_data' => $v['courier_logo'],
                            'dropoff_point' => $v['courier_dropoff_point'],
                            'charges' => $v['charges'],
                            'charges_value' => $v['charges_value'],
                            'free_shipping' => $v['free_shipping'],
                            'free_shipping_by' => $v['free_shipping_by'],
                            'free_shipping_value' => $v['free_shipping_value'],
                        );
                    }
                }

                // get all rate then do the filtering to show only
                $standbylist = array();
                $new_shipping_rate_list = array();
                $count=0;
                foreach($courier as $ck => $cv){
                    foreach ($shipping_rate_list as $key => $val) {
                        $val['service_name'] = str_replace("&amp;","&",$val['service_name']);
                        if(strtolower($cv['id']) == 'all'){
                            array_push($new_shipping_rate_list, $val);
                        }else if(strtolower($cv['id']) == 'cheapest'){
                            $count++;
                            // do checking for cheapest
                            $cv['label'] = '';
                            $standbylist[] = $val;
                            if($count == count($shipping_rate_list)){
                                $cheapestrates = $this->get_cheaper_rate($standbylist);
                                array_push($new_shipping_rate_list, $cheapestrates);
                            }
                        }else if ($cv['service_name'] == $val['service_name']) {
                            $selected_dropoff_point = isset($courier[$cv['service_name']]['dropoff_point']) ? $courier[$val['service_name']]['dropoff_point'] : '';
                            $val['label'] = $cv['label']; // use setting display name
                            $val['selected_dropoff_point'] = $selected_dropoff_point;
                            array_push($new_shipping_rate_list, $val);
                        }else{
                            continue;
                        }
                    }
                }

                $shipping_rate_list = $new_shipping_rate_list; ### overwrite ###
            }
            ### Filter based on setting - E ###

            return $shipping_rate_list;
        }

        protected function compile_items_for_ep_rate($product_list) {
            // Get logger but only use for errors
            $logger = wc_get_logger();
            $context = array('source' => 'easyparcel-shipping');
            
            $items = array();
            $product_factory = new WC_Product_Factory();
        
            foreach ($product_list as $item_id => $item) {
                // Check if this is a WC_Order_Item_Product or an array
                if ($item instanceof WC_Order_Item_Product) {
                    // This is a WC_Order_Item_Product object (common when reading from an order)
                    $product_id = $item->get_product_id();
                    $variation_id = $item->get_variation_id();
                    $quantity = $item->get_quantity();
                    $name = $item->get_name();
                    
                    // Use variation ID if available, otherwise use product ID
                    $actual_product_id = ($variation_id > 0) ? $variation_id : $product_id;
                } else {
                    // This is an array (common in cart calculations)
                    $product_id = isset($item['product_id']) ? $item['product_id'] : 0;
                    $variation_id = isset($item['variation_id']) ? $item['variation_id'] : 0;
                    $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
                    $name = isset($item['name']) ? $item['name'] : 'Unknown';
                    
                    // Use variation ID if available, otherwise use product ID
                    $actual_product_id = ($variation_id > 0) ? $variation_id : $product_id;
                }
                
                // Extra verification for product ID - log only if there's a problem
                if ($actual_product_id <= 0) {
                    $logger->error("Invalid product ID: {$actual_product_id} for item '{$name}'", $context);
                    
                    // Try to find product ID using the SKU or name if available
                    if ($item instanceof WC_Order_Item_Product) {
                        // Try to get product from meta data
                        $sku = $item->get_meta('_sku');
                        if (!empty($sku)) {
                            $product_id_by_sku = wc_get_product_id_by_sku($sku);
                            if ($product_id_by_sku > 0) {
                                $actual_product_id = $product_id_by_sku;
                                $logger->info("Found product by SKU: {$sku}, new ID: {$actual_product_id}", $context);
                            }
                        }
                    }
                    
                    // If we still have an invalid ID, try searching by name
                    if ($actual_product_id <= 0 && !empty($name)) {
                        // Search for products with similar name
                        $products = wc_get_products(array(
                            'status' => 'publish',
                            'limit' => 1,
                            'search' => $name,
                            'return' => 'ids',
                        ));
                        
                        if (!empty($products)) {
                            $actual_product_id = $products[0];
                            $logger->info("Found product by name search: '{$name}', new ID: {$actual_product_id}", $context);
                        }
                    }
                }
                
                // Try to get the product
                $product = null;
                if ($actual_product_id > 0) {
                    $product = $product_factory->get_product($actual_product_id);
                }
                
                // Check if product exists
                if (!$product) {
                    $logger->error("Product not found with ID: {$actual_product_id} for item '{$name}'", $context);
                    
                    // Get default fallback values
                    $fallback_weight = apply_filters('easyparcel_fallback_weight', 0.5); // Default 500g
                    $fallback_dimension = apply_filters('easyparcel_fallback_dimension', 10); // Default 10cm
                    
                    // Log the fallback values being used
                    $logger->warning(
                        "Using fallback dimensions for missing product '{$name}': " . 
                        "Weight: {$fallback_weight}kg, Dimensions: {$fallback_dimension}x{$fallback_dimension}x{$fallback_dimension}cm", 
                        $context
                    );
                    
                    // Use default values for this item
                    for ($i = 0; $i < $quantity; $i++) {
                        array_push($items, array(
                            "weight" => $fallback_weight,
                            "height" => $fallback_dimension,
                            "width" => $fallback_dimension,
                            "length" => $fallback_dimension,
                        ));
                    }
                } else {
                    // If we get here, we have a valid product
                    // No logging for normal operation
                    
                    // Add an item for each quantity
                    for ($i = 0; $i < $quantity; $i++) {
                        array_push($items, array(
                            "weight" => $this->weightToKg($product->get_weight()),
                            "height" => $this->defaultDimension($this->dimensionToCm($product->get_height())),
                            "width" => $this->defaultDimension($this->dimensionToCm($product->get_width())),
                            "length" => $this->defaultDimension($this->dimensionToCm($product->get_length())),
                        ));
                    }
                }
            }
            return $items;
        }

        protected function get_shipping_zone_setting($destination){

            // echo "get_shipping_zone_setting\n";
            // echo "<pre>\n";
            // print_r($this->settings);
            // echo "<pre>\n";

            $package = array('destination' => $destination);

            if($this->settings['legacy_courier_setting'] == "yes"){
                return $this->get_shipping_zone_setting_legacy($package);
            }else{
                // $WC_Shipping_Zone_Data_Store = new WC_Shipping_Zone_Data_Store();
                // $zone_id = $WC_Shipping_Zone_Data_Store->get_zone_id_from_package($package);
                // echo "<pre>zone_id:\n";
                // print_r($zone_id);
                // echo "<pre>\n";
                return $this->get_courier_shipping_zone_setting($package);
            }
        }

        protected function get_courier_shipping_zone_setting($package){
            global $wpdb;
            $WC_Shipping_Zone_Data_Store = new WC_Shipping_Zone_Data_Store();
            $zone_id = $WC_Shipping_Zone_Data_Store->get_zone_id_from_package($package);
        
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."easyparcel_zones_setting WHERE zone_id = %d", $zone_id));
            
            if(!empty($results)){
                $result = $results[0];
                
                // Convert integer setting_type back to string
                $setting_type_map = array(
                    0 => 'all',
                    1 => 'cheapest', 
                    2 => 'couriers'
                );
                
                if (isset($setting_type_map[$result->setting_type])) {
                    $result->setting_type = $setting_type_map[$result->setting_type];
                }
                
                $result->settings = (array)json_decode($result->settings, JSON_OBJECT_AS_ARRAY);
                return (array)$result;
            }else{
                return $this->get_default_courier_shipping_zone_setting();
            }
        }

        protected function get_default_courier_shipping_zone_setting(){
            $default_setting = array(
                'setting_type' => 'all',
                'settings' => array(
                    'is_legacy' => false,
                    'label' => '',
                    'dropoff_point' => '',
                    'is_free_shipping' => 0,
                    'free_shipping_type' => 'amount',
                    'free_shipping_value' => 0,
                    'charges_type' => 'member_rate',
                    'charges_addon_type' => 'amount',
                    'charges_addon_value' => 0,
                )
            );
            return $default_setting;
        }

        protected function get_shipping_zone_setting_legacy($package){
            if (!class_exists('EP_Shipping_Zones')) {
                include_once EASYPARCEL_DATASTORE_PATH .'ep_shipping_zones.php';
            }
            
            $EP_Shipping_Zones = new EP_Shipping_Zones();
            $zone = $EP_Shipping_Zones->get_zone_matching_package($package);

            $zone_courier = array();
            foreach($zone->get_couriers() as $detail){
                if($detail['status'] == 0) continue;

                $setting_detail = array(
                    'is_legacy' => true,
                    'label' => $detail['courier_display_name'],

                    'courier_id' => $detail['courier_id'],
                    'courier_logo' => $detail['courier_logo'],
                    'service_name' => $detail['service_name'],
                    'courier_dropoff_point' => $detail['courier_dropoff_point'],

                    'is_free_shipping' => $detail['free_shipping'] == 1 ? true : false,
                    'free_shipping_type' => '',
                    'free_shipping_value' => $detail['free_shipping_value'],
                    
                    'charges_type' => '',
                    'charges_addon_type' => '',
                    'charges_addon_value' => 0,
                );

                switch($detail['free_shipping_by']){
                    case "1": $setting_detail['free_shipping_type'] = 'amount'; break;
                    case "2": $setting_detail['free_shipping_type'] = 'quantity'; break;
                }

                switch($detail['charges']){
                    case "1": 
                        $setting_detail['charges_type'] = 'flat_rate'; 
                        $setting_detail['charges_value'] = $detail['charges_value']; 
                        break;
                    case "2": 
                        $setting_detail['charges_type'] = 'member_rate'; 
                        break;
                    case "4": 
                        $setting_detail['charges_type'] = 'member_rate_addon'; 
                        $c_value = explode(":", $detail['charges_value']);
                        if($c_value[0] == 1){
                            $setting_detail['charges_addon_type'] = 'amount'; 
                            $setting_detail['charges_addon_value'] = (float)$c_value[1]; 
                        }elseif($c_value[0] == 2){
                            $setting_detail['charges_addon_type'] = 'percent'; 
                            $setting_detail['charges_addon_value'] = (float)$c_value[1]; 
                        }
                        break;
                }

                $setting_detail['label'] = ($detail["service_id"] == 'all' or $detail["service_id"] == 'cheapest') ? '' : $setting_detail['label'];

                if($detail["service_id"] == 'all'){
                    $zone_courier['setting_type'] = 'all';
                    $zone_courier['settings'] = $setting_detail;
                }elseif($detail["service_id"] == 'cheapest'){
                    $zone_courier['setting_type'] = 'cheapest';
                    $zone_courier['settings'] = $setting_detail;
                }else{
                    $zone_courier['setting_type'] = 'couriers';

                    if(!isset($zone_courier['settings'])) $zone_courier['settings'] = array();
                    
                    array_push($zone_courier['settings'], $setting_detail);
                }

            }
            // echo "get_shipping_zone_setting_legacy\n";
            // echo "<pre>\n";
            // print_r($zone_courier);
            // echo "<pre>\n";

            return $zone_courier;
        }

        protected function fetch_easyparcel_zones_setting($zone_id){
            global $wpdb;
        
            // Remove instance_id from query
            $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM ".$wpdb->prefix."easyparcel_zones_setting WHERE zone_id = %d", $zone_id));
            
            if(!empty($results)){
                $result = $results[0];
                
                // Integer-to-string mapping for JavaScript
                $setting_type_map = array(
                    0 => 'all',
                    1 => 'cheapest', 
                    2 => 'couriers'
                );
                
                $setting_type_string = isset($setting_type_map[$result->setting_type]) ? $setting_type_map[$result->setting_type] : 'all';
                $result->setting_type = $setting_type_string;
                
                $result->settings = (array)json_decode($result->settings);
                return (array)$result;
            }else{
                return $this->get_default_courier_shipping_zone_setting();
            }
        }
        
        
        protected function compile_rate_format($rate){
            $rate->service_name = str_replace("&amp;","&",$rate->service_name);
            return array(
                'id' => $rate->service_id,
                'label' => $rate->service_name,
                'cost' => $rate->price,
                'service_name' => $rate->service_name,
                'courier_id' => $rate->courier_id,
                'ep_cost' => $rate->price,
                'dropoff_point' => $rate->dropoff_point,
                'easycover' => $rate->addon_insurance_available,
                'basic_coverage' => $rate->basic_insurance_max_value,
                'basic_coverage_currency' => $rate->basic_insurance_currency,
                'selected_dropoff_point' => '',
				'meta_data' => array(
					'ep_courier_logo' => $rate->courier_logo,
				)
            );
        }


        protected function checkout_page_compile_rate_format($rate){
            $rate->service_name = str_replace("&amp;","&",$rate->service_name);
            
            // Get conversion rate and currencies
            $conversion_rate = $this->get_option('conversion_rate', '');
            $sender_country = $this->get_option('sender_country', 'MY');
            $store_currency = get_woocommerce_currency();
            $ep_currency = ($sender_country === 'SG') ? 'SGD' : 'MYR';
            
            // Start with original price
            $display_price = $rate->price;
            
            // Apply conversion only if all conditions are met
            if ($store_currency !== $ep_currency 
                && isset($conversion_rate) 
                && !empty($conversion_rate) 
                && is_numeric($conversion_rate) 
                && floatval($conversion_rate) > 0) {
                
                // Convert and round to 2 decimal places
                $display_price = round(floatval($rate->price) / floatval($conversion_rate), 2);
            }
            
            return array(
                'id' => $rate->service_id,
                'label' => $rate->service_name,
                'cost' => $display_price,
                'service_name' => $rate->service_name,
                'courier_id' => $rate->courier_id,
                'ep_cost' => $rate->price, // Original EasyParcel price
                'dropoff_point' => $rate->dropoff_point,
                'easycover' => $rate->addon_insurance_available,
                'basic_coverage' => $rate->basic_insurance_max_value,
                'basic_coverage_currency' => $rate->basic_insurance_currency,
                'selected_dropoff_point' => '',
                'meta_data' => array(
                    'ep_courier_logo' => $rate->courier_logo,
                )
            );
        }

        

        public function doCourierCalculation($rate, $setting, $package) {
            $rate['label'] = !empty($setting['label']) ? $setting['label'] : $rate['label'];

            $order_total = $package['cart_subtotal'];
            $order_quantity = 0;
            foreach ($package["contents"] as $key => $item) {
                $order_quantity += $item["quantity"];
            }

            $skipcost = false;
            if ($setting['is_free_shipping']) {
                if($setting['free_shipping_type'] == 'amount'){
                    $skipcost = $order_total >= (float)$setting['free_shipping_value'] ? true : false;
                }elseif($setting['free_shipping_type'] == 'quantity'){
                    $skipcost = $order_quantity >= (float)$setting['free_shipping_value'] ? true : false;
                }
            }

            if ($skipcost) {
                $rate['cost'] = 0;
            } else {
                switch ($setting['charges_type']) {
                    case "flat_rate": //flat
                        $rate['cost'] = (float)$setting['charges_value'];
                        break;
                    case "member_rate_addon": // addon ,
                        if ($setting['charges_addon_type'] == 'amount') {
                            $rate['cost'] = $rate['cost'] + (float)$setting['charges_addon_value'];

                        } else if ($setting['charges_addon_type'] == 'percent') { // add on by %
                            $setting['charges_addon_value'] = str_replace('%', '', $setting['charges_addon_value']); //remove % if exist
                            $rate['cost'] = $rate['cost'] *  ( 1 + ((float)$setting['charges_addon_value'] / 100) );
                        }
                        break;
                }
            }
            //check if valid 0 >
            $rate['cost'] = ($rate['cost'] < 0) ? 0 : $rate['cost'];
            $rate['cost'] = number_format((float) $rate['cost'], 2, '.', '');

            return $rate;
        }

        /**
         * process easyparcel order function
         */
        public function process_booking_order($obj) {
            if (!class_exists('Easyparcel_Shipping_API')) {
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }

            $woo_order = wc_get_order($obj->order_id);
            $data = (object) array();
            $data->order_id = $obj->order_id;
            $data->order = $woo_order;

            $weight = 0;
            $length = 0;
            $width = 0;
            $height = 0;
            $item_value = 0;

            $content = '';
            $product_factory = new WC_Product_Factory();


            $maxLength = $maxWidth = $maxHeight = $sumLength = $sumWidth = $sumHeight = 0;

            foreach ($data->order->get_items() as $item) {
                $data->product = $product_factory->get_product($item["product_id"]);
                $item_value += $item->get_subtotal();

                for ($i = 0; $i < $item["quantity"]; $i++) {
                    $weight += $this->weightToKg($data->product->get_weight());

                    $length = $this->defaultDimension($this->dimensionToCm($data->product->get_length()));
                    if (is_numeric($length)) {
                        $length = floatval($length);

                        $maxLength = max($maxLength, $length);  
                        $sumLength += $length;
                    }
                
                    $width = $this->defaultDimension($this->dimensionToCm($data->product->get_width()));
                    if (is_numeric($width)) {
                        $width = (float) $width;
                        $maxWidth = max($maxWidth, $width); 
                        $sumWidth += $width;
                    }
                
                    $height = $this->defaultDimension($this->dimensionToCm($data->product->get_height()));
                    if (is_numeric($height)) {
                        $height = floatval($height);
                        $maxHeight = max($maxHeight, $height);
                        $sumHeight += $height;
                    }
                }

                $content .= $item["name"] . ' ';
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

            $data->item_value = $item_value;
            $data->weight = $weight;
            $data->length = $length;
            $data->width = $width;
            $data->height = $height;
            $content = trim($content); // trim content first
            if (strlen($content) >= 30) {
                $content = substr($content, 0, 30) . '...';
            }
            $data->content = $content;
            $data->service_id = $obj->shipping_provider;
            $data->drop_off_point = $obj->drop_off_point;
            $data->collect_date = $obj->pick_up_date;
            $data->addon_insurance_enabled = $obj->easycover;
            $data->tax_duty = $obj->easyparcel_ddp == 'true' ? "DDP" : "DDU";
            $data->parcel_category_id = $obj->easyparcel_parcel_category;

            // echo "<pre>";print_r($data);echo "</pre>";

            Easyparcel_Shipping_API::init();
            $order_result = Easyparcel_Shipping_API::submitOrder($data);

            $return_result = '';

            if (!empty($order_result)) {

                if (!empty($order_result->order_number)) {
                    ### add woo order meta ###
                    $data->ep_order_number = $order_result->order_number;
                    $data->order->update_meta_data('_ep_order_number', $data->ep_order_number);
                    $data->order->update_meta_data('_ep_selected_courier', $obj->courier_name);
                    $data->order->save(); // save meta

                    ### call EP Pay Order API ###
                    $payment_result = Easyparcel_Shipping_API::payOrder($data);
                    if (!empty($payment_result)) {
                        if ($payment_result->error_code == 0) { ### EP Pay Order API Success ###
                            if (isset($payment_result->result)) {
								if(isset($payment_result->result[0])){
                                    $pay_success = false;
                                    if(is_object($payment_result->result[0]->parcel)){
                                        $pay_success = isset($payment_result->result[0]->parcel->parcelno);
                                    }else if (is_array($payment_result->result[0]->parcel)){
                                        $pay_success = isset($payment_result->result[0]->parcel[0]->parcelno);
                                    }
									if($pay_success){
										$obj_awb = $this->process_woo_payment_awb($payment_result->result[0]);

										$data->order->update_meta_data('_ep_fulfillment_date', gmdate('Y-m-d'));
										$data->order->update_meta_data('_ep_payment_status', 1);
										$data->order->update_meta_data('_ep_awb', $obj_awb->ep_awb);
										$data->order->update_meta_data('_ep_awb_id_link', $obj_awb->ep_awb_id_link);
										$data->order->update_meta_data('_ep_tracking_url', $obj_awb->ep_tracking_url);
										$data->order->save(); // save meta

										if ($obj_awb->ep_awb) {
											$this->process_woo_order_status_update_after_payment($data);
										}
									}else{
										$return_result = "EasyParcel Payment Failed. ". $payment_result->result[0]->messagenow;	
									}
								}else{
									$return_result = "EasyParcel Payment Failed.";
								}

                            } else {
                                $return_result = "EasyParcel Payment Failed. " . $payment_result->error_remark;
                            }
                        } else {
                            $return_result = "EasyParcel Payment Failed. " . $payment_result->error_remark;
                        }

                    } else {
                        $return_result = "EasyParcel Payment Failed. " . $payment_result->error_remark;
                    }
                } else {
                    $return_result = "EasyParcel Order Failed. " . $order_result->remarks;
                }
            } else {
                $return_result = "EasyParcel Order Failed. " . $order_result->remarks;
            }

            return $return_result;

        }

        /**
         * process easyparcel bulk order function
         */
        public function process_bulk_booking_order($obj) {
            if (!class_exists('Easyparcel_Shipping_API')) {
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }

            $weight = 0;
            $length = 0;
            $width = 0;
            $height = 0;

            $content = '';
            $product_factory = new WC_Product_Factory();

            $order_ids = explode(',', $obj->order_id);
            $bulk_order = array();
            foreach ($order_ids as $order_id) {
                $woo_order = wc_get_order($order_id);
                $data = (object) array();
                $data->order_id = $order_id;
                $data->order = $woo_order;

                ###Declare for bulk usage###
                $total_weight = 0;
                $total_length = 0;
                $total_width = 0;
                $total_height = 0;
                $item_value = 0;
                $full_content = '';
                $maxLength = $maxWidth = $maxHeight = $sumLength = $sumWidth = $sumHeight = 0;

                foreach ($data->order->get_items() as $item) {
                    $data->product = $product_factory->get_product($item["product_id"]);
                    $item_value += $item->get_subtotal();

                    ###Declare for bulk usage###
                    $weight = 0;
                    $length = $width = $height = 0;
                    $content = '';
                    for ($i = 0; $i < $item["quantity"]; $i++) {
                        $weight += $this->weightToKg($data->product->get_weight());

                        $length = $this->defaultDimension($this->dimensionToCm($data->product->get_length())); 
                        if (is_numeric($length)) {
                            $length = floatval($length);
                            $maxLength = max($maxLength, $length);  
                            $sumLength += sanitize_text_field($length);
                        }
                    
                        $width = $this->defaultDimension($this->dimensionToCm($data->product->get_width()));
                        if (is_numeric($width)) {
                            $width = (float) $width;
                            $maxWidth = max($maxWidth, $width); 
                            $sumWidth += $width;
                        }
                    
                        $height = $this->defaultDimension($this->dimensionToCm($data->product->get_height()));
                        if (is_numeric($height)) {
                            $height = floatval($height);
                            $maxHeight = max($maxHeight, $height);
                            $sumHeight += sanitize_text_field($height);
                        }
                    }
                    $total_weight += $weight;
                    
                    $full_content .= $item["name"] . ' ';
                }

                $smallestSum = min($sumLength, $sumWidth, $sumHeight); //In order to not let the volumetric weight become too large.

                if ($smallestSum === $sumLength) {
                    $total_length = $sumLength;
                    $total_width = $maxWidth;
                    $total_height = $maxHeight;
                } elseif ($smallestSum === $sumWidth) {
                    $total_length = $maxLength;
                    $total_width = $sumWidth;
                    $total_height = $maxHeight;
                } else {
                    $total_length = $maxLength;
                    $total_width = $maxWidth;
                    $total_height = $sumHeight;
                }

                $data->item_value = $item_value;
                $data->weight = $total_weight;
                $data->length = $total_length;
                $data->width = $total_width;
                $data->height = $total_height;
                $full_content = trim($full_content); // trim content first
                if (strlen($full_content) >= 30) {
                    $full_content = substr($full_content, 0, 30) . '...';
                }
                $data->content = $full_content;
                $data->service_id = $obj->shipping_provider;
                $data->drop_off_point = $obj->drop_off_point;
                $data->collect_date = $obj->pick_up_date;

                array_push($bulk_order, $data);
            }

            // echo "<pre>";print_r($bulk_order);echo "</pre>";

            Easyparcel_Shipping_API::init();
            $order_result = Easyparcel_Shipping_API::submitBulkOrder($bulk_order);

            $return_result = '';

            $paid_bulk_order_id = array();
            $paid_bulk_ep_order_number = array();

            if (!empty($order_result)) {
                if (!empty($order_result->result)) {
                    for ($i = 0; $i < count($bulk_order); $i++) {
                        if (isset($order_result->result[$i]) && !empty($order_result->result[$i]->order_number)) {

                            array_push($paid_bulk_order_id, $bulk_order[$i]->order_id);
                            array_push($paid_bulk_ep_order_number, $order_result->result[$i]->order_number);
                            ### add woo order meta ###
                            $bulk_order[$i]->order->update_meta_data('_ep_order_number', $order_result->result[$i]->order_number);
                            $bulk_order[$i]->order->update_meta_data('_ep_selected_courier', $obj->courier_name);
                            $bulk_order[$i]->order->save(); // save meta

                        } else {
                            $return_result .= "EasyParcel Order Failed. " . $order_result->result[$i]->remarks;
                        }

                    }
                } else {
                    $return_result .= "EasyParcel Order Failed. " . $order_result->error_remark;
                }
            }

            if (!empty($paid_bulk_ep_order_number) && !empty($paid_bulk_order_id)) {
                ### call EP Bulk Pay Order API ###
                $payment_result = Easyparcel_Shipping_API::payBulkOrder($paid_bulk_ep_order_number);

                if (!empty($payment_result)) {
                    if ($payment_result->error_code == 0) { ### EP Pay Order API Success ###
                    if (!empty($payment_result->result)) {
                        for ($i = 0; $i < count($paid_bulk_order_id); $i++) {
                            if (isset($payment_result->result[$i])) {
                                $pay_success = false;
                                if(is_object($payment_result->result[$i]->parcel)){
                                    $pay_success = isset($payment_result->result[$i]->parcel->parcelno);
                                }else if (is_array($payment_result->result[$i]->parcel)){
                                    $pay_success = isset($payment_result->result[$i]->parcel[0]->parcelno);
                                }
                                if($pay_success){
                                    $obj_awb = $this->process_woo_payment_awb($payment_result->result[$i]);
                                
                                    $woo_order = wc_get_order($paid_bulk_order_id[$i]);
                                    
                                    $woo_order->update_meta_data('_ep_payment_status', 1);
                                    $woo_order->update_meta_data('_ep_awb', $obj_awb->ep_awb);
                                    $woo_order->update_meta_data('_ep_awb_id_link', $obj_awb->ep_awb_id_link);
                                    $woo_order->update_meta_data('_ep_tracking_url', $obj_awb->ep_tracking_url);
                                    $woo_order->save(); // save meta

                                    if ($obj_awb->ep_awb) {
                                        // bulk need declare $data - need to put after save meta
                                        $data = (object) array();
                                        $data->order = $woo_order;
                                        $this->process_woo_order_status_update_after_payment($data);
                                    }
                                }else{
                                    $return_result = "EasyParcel Payment Failed. ". $payment_result->result[$i]->messagenow;	
                                }
                            } else {
                                $return_result .= "EasyParcel Payment Failed. " . $payment_result->result[$i]->messagenow;
                            }
                        }
                    }
                    } else {
                        $return_result .= "EasyParcel Payment Failed. " . $order_result->error_remark;
                    }
                } else {
                    $return_result .= "EasyParcel Payment Failed. " . $order_result->error_remark;
                }

            }

            return $return_result;
        }

        public function process_retrive_booking_awb($order_id){
            if (!class_exists('Easyparcel_Shipping_API')) {
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }

            // Initialize the API first to set up all URLs and credentials
            Easyparcel_Shipping_API::init();
            
            $data = (object)array();
            
            $data->order = wc_get_order($order_id);
            
            if($data->order){
                $payment_result = Easyparcel_Shipping_API::payOrder((object)array(
                    "ep_order_number" => $data->order->get_meta('_ep_order_number')
                ));
                
                if (!empty($payment_result)) {
                    if ($payment_result->error_code == 0) { ### EP Pay Order API Success ###
                        if (isset($payment_result->result)) {
                            // Fix: Check if result is an array or has array access
                            if (is_array($payment_result->result) && isset($payment_result->result[0])) {
                                $obj_awb = $this->process_woo_payment_awb($payment_result->result[0]);
                            } else {
                                // If result is a single object, pass it directly
                                $obj_awb = $this->process_woo_payment_awb($payment_result->result);
                            }
        
                            if(!empty($obj_awb->ep_awb)){
                                $data->order->update_meta_data('_ep_payment_status', 1);
                                $data->order->update_meta_data('_ep_awb', $obj_awb->ep_awb);
                                $data->order->update_meta_data('_ep_awb_id_link', $obj_awb->ep_awb_id_link);
                                $data->order->update_meta_data('_ep_tracking_url', $obj_awb->ep_tracking_url);
                                $data->order->save(); // save meta
        
                                if ($obj_awb->ep_awb) {
                                    // need to put after save meta
                                    $this->process_woo_order_status_update_after_payment($data);
                                }
                                return true;
                            }
                        } 
                    }
                }
            }
            return false;
        }

        private function process_woo_payment_awb($result) {
            $data = (object) array();
            $data->ep_awb = '';
            $data->ep_awb_id_link = '';
            $data->ep_tracking_url = '';
            
            if (isset($result->parcel)) {
                if(is_object($result->parcel)){
                    $data->ep_awb = $result->parcel->awb;
                    $data->ep_awb_id_link = $result->parcel->awb_id_link;
                    $data->ep_tracking_url = $result->parcel->tracking_url;
                }else if (is_array($result->parcel)){
                    $data->ep_awb = $result->parcel[0]->awb;
                    $data->ep_awb_id_link = $result->parcel[0]->awb_id_link;
                    $data->ep_tracking_url = $result->parcel[0]->tracking_url;
                }
            }            
            return $data;
        }

        private function process_woo_order_status_update_after_payment($data) {
            if ($this->settings['order_status_update_option'] == 'yes') {
                $data->order->update_status('completed');
            }
        }

        /**
         * This function is convert dimension to cm
         *
         * @access protected
         * @param number
         * @return number
         */
        protected function dimensionToCm($length) {
            $dimension_unit = get_option('woocommerce_dimension_unit');
            // convert other units into cm
            $length = is_numeric($length) ? (float)$length : 0;
            if ($dimension_unit != 'cm') {
                if ($dimension_unit == 'm') {
                    return $length * 100;
                } else if ($dimension_unit == 'mm') {
                    return $length * 0.1;
                } else if ($dimension_unit == 'in') {
                    return $length * 2.54;
                } else if ($dimension_unit == 'yd') {
                    return $length * 91.44;
                }
            }

            // already in cm
            return $length;
        }

        /**
         * This function is convert weight to kg
         *
         * @access protected
         * @param number
         * @return number
         */
        protected function weightToKg($weight) {
            $weight_unit = get_option('woocommerce_weight_unit');
            
            $weight = is_numeric($weight) ? (float)$weight : 0;
			if ($weight_unit != 'kg') {
                if ($weight_unit == 'g') {
                    return $weight * 0.001;
                } else if ($weight_unit == 'lbs') {
                    return $weight * 0.453592;
                } else if ($weight_unit == 'oz') {
                    return $weight * 0.0283495;
                }
            }

            // already kg
            return (float)$weight;
        }

        /**
         * This function return default value for length
         *
         * @access protected
         * @param number
         * @return number
         */
        protected function defaultDimension($length) {
            // default dimension to 1 if it is 0
            // $length = double($length);
            return $length > 0 ? $length : 0.1;
        }
    }
}

?>