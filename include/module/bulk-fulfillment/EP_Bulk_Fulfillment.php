<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}	
if (!class_exists('EP_Bulk_Fulfillment')) {

    defined( 'EASYPARCEL_MODULE_BULK_FULFILLMENT_PATH') || define( 'EASYPARCEL_MODULE_BULK_FULFILLMENT_PATH'  , EASYPARCEL_MODULE_PATH . 'bulk-fulfillment/' );
    defined( 'EASYPARCEL_MODULE_BULK_FULFILLMENT_URL') || define( 'EASYPARCEL_MODULE_BULK_FULFILLMENT_URL'  , EASYPARCEL_MODULE_URL . 'bulk-fulfillment/' );

    class EP_Bulk_Fulfillment {
        
        public static function add_all_bulk_actions( $actions ) {
            $actions['easyparcel_order_fulfillment'] = __( 'Easyparcel Order Fulfillment', 'easyparcel-shipping' );
            $actions['download_easyparcel_awb'] = __( 'EasyParcel Download AWBs', 'easyparcel-shipping' );
            return $actions;
        }
        
        public static function handle_all_bulk_actions( $redirect_to, $action, $post_ids ) {
            if ( $action === 'easyparcel_order_fulfillment' ) {
                self::set_temp_data('bulk_fulfillment', array(
                    'action' => 'fulfillment',
                    'count' => count($post_ids),
                    'order_ids' => $post_ids
                ));
                return remove_query_arg(array('order_fulfillment', 'processed_count', 'processed_ids'), $redirect_to);
            }
            
            if ( $action === 'download_easyparcel_awb' ) {
                return self::handle_download_easyparcel_awb_bulk_action( $redirect_to, $action, $post_ids );
            }
            
            return $redirect_to;
        }
        
        // Helper method to store temporary data using WordPress transients
        private static function set_temp_data($key, $data, $expiration = 300) {
            $user_id = get_current_user_id();
            $transient_key = "easyparcel_{$key}_{$user_id}";
            return set_transient($transient_key, $data, $expiration);
        }
        
        private static function get_temp_data($key) {
            $user_id = get_current_user_id();
            $transient_key = "easyparcel_{$key}_{$user_id}";
            return get_transient($transient_key);
        }
        
        private static function delete_temp_data($key) {
            $user_id = get_current_user_id();
            $transient_key = "easyparcel_{$key}_{$user_id}";
            return delete_transient($transient_key);
        }
        
        public static function register_bulk_action() {
            if (!class_exists('EP_EasyParcel')){
                include_once EASYPARCEL_PATH . 'include/EP_EasyParcel.php';
            }

            // Register bulk actions for both legacy and HPOS
            $hook_prefix = self::is_hpos_enabled() ? 'woocommerce_page_wc-orders' : 'edit-shop_order';
            add_filter( "bulk_actions-{$hook_prefix}", array( __CLASS__, 'add_all_bulk_actions' ) );
            add_filter( "handle_bulk_actions-{$hook_prefix}", array( __CLASS__, 'handle_all_bulk_actions' ), 10, 3 );

            // Add columns and handlers
            $column_hooks = [
                ['manage_edit-shop_order_sortable_columns', 'destination_columns_sortable'],
                ['manage_shop_order_posts_columns', 'shop_order_columns', 99],
                ['manage_shop_order_posts_columns', 'destination_columns', 99],
                ['manage_woocommerce_page_wc-orders_columns', 'shop_order_columns', 99],
                ['manage_woocommerce_page_wc-orders_columns', 'destination_columns', 99]
            ];
            
            foreach ($column_hooks as $hook) {
                add_filter($hook[0], array(__CLASS__, $hook[1]), $hook[2] ?? 10);
            }

            $action_hooks = [
                ['manage_shop_order_posts_custom_column', 'render_shop_order_columns'],
                ['manage_shop_order_posts_custom_column', 'render_destination_columns'],
                ['manage_woocommerce_page_wc-orders_custom_column', 'render_shop_order_columns'],
                ['manage_woocommerce_page_wc-orders_custom_column', 'render_destination_columns']
            ];
            
            foreach ($action_hooks as $hook) {
                add_action($hook[0], array(__CLASS__, $hook[1]), 10, 2);
            }

            add_action( 'easyparcel_delete_old_zip_files', array( __CLASS__ ,'delete_old_zip_files'));

            if (!wp_next_scheduled('easyparcel_delete_old_zip_files')) {
                wp_schedule_event(time(), 'daily', 'easyparcel_delete_old_zip_files');
            }
            self::enqueue_style();
            self::enqueue_script();
        }
        
        public static function enqueue_style() {
            $screen = get_current_screen();
            
            if ($screen && in_array($screen->id, ['woocommerce_page_wc-orders', 'edit-shop_order'])) {
                wp_enqueue_style( 
                    'easyparcel_order_list_styles', 
                    EASYPARCEL_MODULE_SETUP_URL . 'admin.css', 
                    array(),
                    EASYPARCEL_VERSION . '-popup-fix',
                    'all'
                );
                
                // Inline styles would go here - keeping original for now as requested
            }
        }

        public static function enqueue_script() {
            wp_enqueue_script('jquery-ui-datepicker');
            // Don't load any external CSS - rely on WordPress admin styles and browser defaults
            
            wp_register_script('easyparcel-admin-order-js', EASYPARCEL_MODULE_BULK_FULFILLMENT_URL . 'admin_order.js', array('jquery', 'jquery-ui-datepicker'), EASYPARCEL_VERSION, array('in_footer' => true));
            wp_localize_script(
                'easyparcel-admin-order-js',
                'easyparcel_orders_params',
                array(
                    'order_nonce' => wp_create_nonce( 'easyparcel_bulk_fulfillment_popup' ),
                    'is_hpos' => EP_EasyParcel::is_wc_hpos_enable()
                )
            );
            wp_enqueue_script('easyparcel-admin-order-js');
        }        
        
        public static function register_ajax_action(){
            $ajax_actions = [
                'easyparcel_bulk_fulfillment_popup' => 'easyparcel_bulk_fulfillment_popup',
                'wc_shipment_tracking_save_form_bulk' => 'save_bulk_order_ajax',
                'generate_easyparcel_awb_zip' => 'generate_easyparcel_awb_zip_callback'
            ];
            
            foreach ($ajax_actions as $action => $method) {
                add_action("wp_ajax_{$action}", array(__CLASS__, $method));
            }
            
            add_action( 'admin_notices', array( __CLASS__, 'bulk_action_admin_notice' ) );
            add_action( 'admin_notices', array( __CLASS__, 'display_awb_download_notice' ) );
            add_action( 'admin_footer', array( __CLASS__, 'add_bulk_fulfillment_script' ) );
        }
        
        // Removed duplicate methods - keeping only handle_all_bulk_actions
        
		public static function bulk_action_admin_notice() {
			$session_data = self::get_temp_data('bulk_fulfillment');

			if ($session_data && $session_data['action'] === 'fulfillment') {
				self::delete_temp_data('bulk_fulfillment');
				self::display_fulfillment_notice($session_data['count'], $session_data['order_ids']);
			}

		}
        
        private static function display_fulfillment_notice($count, $order_ids) {
            if ($count > 0 && !empty($order_ids)) {
                /* translators: %s: Number of orders selected for fulfillment */
                $message = _n( '%s order selected for EasyParcel fulfillment.',
                    '%s orders selected for EasyParcel fulfillment.',
                    $count,
                    'easyparcel-shipping'
                );
                
                echo '<div id="message" class="updated fade"><p>' . esc_html( sprintf( $message, intval($count) ) ) . '</p></div>';
                
                echo '<script type="text/javascript">
                var easyparcelBulkOrderIds = ' . wp_json_encode($order_ids) . ';
                jQuery(document).ready(function() {
                    setTimeout(function() { openBulkFulfillmentPopup(); }, 500);
                });
                </script>';
            }
        }
    
		public static function easyparcel_bulk_fulfillment_popup() {
			if ( !isset( $_POST['security'] ) || !wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['security'])), 'easyparcel_bulk_fulfillment_popup' ) ) {
				wp_die( 'Security check failed' );
			}
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				exit( 'You are not allowed' );
			}

			// FIXED: Sanitize $_POST immediately with map_deep for arrays
			$order_ids = [];
			if (isset($_POST['order_ids'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized on next line
				$raw_data = map_deep(wp_unslash($_POST['order_ids']), 'sanitize_text_field');
                if (is_array($raw_data)) {
                    $order_ids = array_filter($raw_data, function($value) {
                        return !empty(trim($value)) && is_numeric($value);
                    });
                } else {
                    $order_ids = array_filter(explode(',', $raw_data), function($value) {
                        return !empty(trim($value)) && is_numeric($value);
                    });
                }
			}

			$unpaid_order_ids = self::filter_unpaid_orders($order_ids);

			if($unpaid_order_ids){
				self::render_fulfillment_popup($unpaid_order_ids);
			}else{
				echo '<script>alert("Your selected shipments have been fulfilled.")</script>';
			}
			exit;	
		}
        
        private static function filter_unpaid_orders($order_ids) {
            $unpaid_order_ids = array();
            foreach($order_ids as $order_id) {
                $order = wc_get_order( $order_id );
                if (!$order) continue;
                
                $easyparcel_paid_status = ($order->meta_exists('_ep_payment_status')) ? 1 : 0;
                if(!$easyparcel_paid_status){
                    array_push($unpaid_order_ids, $order_id);
                }
            }
            return $unpaid_order_ids;
        }
        
        private static function render_fulfillment_popup($unpaid_order_ids) {
            $order_number = implode(',', $unpaid_order_ids);
            $post = (object)array('ID' => $unpaid_order_ids[0]);
            $api_detail = self::get_api_detail_bulk($post);
            $shipment_providers_by_country = $api_detail->shipment_providers_list;
            $dropoff_point_list = wp_json_encode($api_detail->dropoff_point_list);
            
            // Security: Add nonce for form submission
            $nonce = wp_create_nonce('easyparcel_bulk_fulfillment_form');
            
            // Define allowed HTML for admin forms
            $allowed_html = array(
                'div' => array('id' => array(), 'class' => array()),
                'form' => array('id' => array(), 'method' => array(), 'class' => array()),
                'input' => array('type' => array(), 'id' => array(), 'name' => array(), 'value' => array(), 'class' => array(), 'required' => array(), 'readonly' => array(), 'placeholder' => array()),
                'select' => array('id' => array(), 'name' => array(), 'class' => array(), 'required' => array()),
                'option' => array('value' => array(), 'selected' => array()),
                'label' => array('for' => array()),
                'p' => array('class' => array()),
                'h3' => array('class' => array()),
                'span' => array('class' => array()),
                'hr' => array(),
            );
            
            ob_start();
            ?>
            <div id="easyparcel_fulfillment_popout" class="fulfillment_popup_wrapper add_fulfillment_popup" >
                <div class="fulfillment_popup_row">
                    <div class="popup_header">
                    <h3 class="popup_title"><?php esc_html_e( 'Shipment Fulfillment', 'easyparcel-shipping'); ?> - #<?php echo esc_html($order_number); ?></h3>						
                        <span class="dashicons dashicons-no-alt popup_close_icon"></span>
                    </div>
                    <div class="popup_body">
                        <form id="add_fulfillment_form" method="POST" class="add_fulfillment_form">	
                            <?php wp_nonce_field('easyparcel_bulk_fulfillment_form', 'form_nonce'); ?>
                            <p class="form-field form-50">
                                <label for="shipping_provider"><?php esc_html_e( 'Courier Services:', 'easyparcel-shipping'); ?></label>
                                <select class="chosen_select shipping_provider_dropdown" id="shipping_provider" name="shipping_provider" required>
                                    <option value=""><?php esc_html_e( 'Select Preferred Courier Service', 'easyparcel-shipping'); ?></option>
                                    <?php 
                                        foreach ( $shipment_providers_by_country as $providers ) {		
                                            printf(
                                                '<option value="%s">%s</option>',
                                                esc_attr($providers->ts_slug),
                                                esc_html($providers->provider_name)
                                            );
                                        }
                                    ?>
                                </select>
                            </p>
                            
                            <p class="form-field drop_off_field form-50"></p>
                            
                            <input type="hidden" id="easyparcel_dropoff" name="easyparcel_dropoff" value="<?php echo esc_attr($dropoff_point_list); ?>" />
                            
                            <p class="form-field date_shipped_field form-50">
                                <label for="date_shipped"><?php esc_html_e( 'Drop Off / Pick Up Date', 'easyparcel-shipping'); ?></label>
                                <input type="text" 
                                       class="easyparcel-datepicker" 
                                       name="date_shipped" 
                                       id="date_shipped" 
                                       value="<?php echo esc_attr( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>" 
                                       placeholder="<?php echo esc_attr( date_i18n( 'Y-m-d', time() ) ); ?>" 
                                       required 
                                       readonly>						
                            </p>								
                            <hr>
                            <p>		
                                <input type="hidden" name="action" value="add_shipment_fulfillment">
                                <input type="hidden" name="order_id" id="order_id" value="<?php echo esc_attr($order_number); ?>">
                                <input type="hidden" name="form_nonce" value="<?php echo esc_attr($nonce); ?>">
                                <input type="button" 
                                       name="Submit" 
                                       value="<?php esc_attr_e( 'Fulfill Order', 'easyparcel-shipping'); ?>" 
                                       class="button-primary btn_green button-save-form">    
                            </p>			
                        </form>
                    </div>								
                </div>
                <div class="popupclose"></div>
            </div>
            <?php
            $output = ob_get_clean();
            
            // Use wp_kses with custom allowed HTML for admin forms
            echo wp_kses($output, $allowed_html);
        }
    
        public static function save_bulk_order_ajax() {
            // Enhanced security checks
            if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'easyparcel_bulk_fulfillment_popup')) {
                wp_send_json_error(['message' => 'Security check failed']);
                wp_die();
            }
            
            if (!current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
                wp_die();
            }
            
            // Additional form nonce check if present
            if (!isset($_POST['form_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['form_nonce'])), 'easyparcel_bulk_fulfillment_form')) {
                wp_send_json_error(['message' => 'Form security check failed']);
                wp_die();
            }
            
            $form_data = array(
                'shipping_provider' => sanitize_text_field( wp_unslash( $_POST['shipping_provider'] ?? '' )),
                'courier_name' => sanitize_text_field( wp_unslash( $_POST['courier_name'] ?? '' )),
                'drop_off_point' => sanitize_text_field( wp_unslash( $_POST['drop_off_point'] ?? '' )),
                'pick_up_date' => sanitize_text_field( wp_unslash($_POST['pick_up_date'] ?? '')),
                'order_id' => sanitize_text_field( wp_unslash($_POST['order_id'] ?? ''))
            );

            if (!class_exists('WC_Easyparcel_Shipping_Method')) {
                include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Method.php';
            }
            
            $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();

            // Validation
            if (empty($form_data['pick_up_date'])) {
                wp_send_json_error(['message' => 'Please fill in Drop Off / Pick Up Date']);
                wp_die();
            } 
            if (empty($form_data['shipping_provider'])) {
                wp_send_json_error(['message' => 'Please select Courier Services']);
                wp_die();
            }

            $obj = (object)$form_data;
            $ep_order = $WC_Easyparcel_Shipping_Method->process_bulk_booking_order($obj);

            $credit_balance = self::get_credit_balance();
            $credit_balance_html = self::build_credit_balance_html($credit_balance, $WC_Easyparcel_Shipping_Method);

            if(!empty($ep_order)){
                $error_msg = self::format_error_message($ep_order) . $credit_balance_html;
                wp_send_json_error(['message' => $error_msg]);
            }else{
                $success_message = '<div style="color: green; font-weight: bold; margin-bottom: 10px;">Order(s) fulfilled successfully!</div>' . $credit_balance_html;
                wp_send_json_success(['message' => $success_message]);
            }
            
            wp_die();
        }
        
        private static function get_credit_balance() {
            if (!class_exists('Easyparcel_Shipping_API')) {
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }
            
            try {
                if (class_exists('WC_Easyparcel_Shipping_Method') && class_exists('Easyparcel_Shipping_API')) {
                    Easyparcel_Shipping_API::init();
                    return Easyparcel_Shipping_API::getCreditBalance();
                }
                return "Service unavailable";
            } catch (Exception $e) {
                return "Unable to get balance";
            }
        }
        
        private static function build_credit_balance_html($credit_balance, $shipping_method) {
            $html = "<hr>Your EasyParcel Credit Balance: " . $credit_balance;
            
            if(isset($shipping_method->settings['sender_country'])){
                $country = strtolower($shipping_method->settings['sender_country']);
                $html .= '&nbsp;&nbsp;<a target="_blank" href="https://app.easyparcel.com/' . $country . '/en/account/topup">Top Up</a> | <a target="_blank" href="https://app.easyparcel.com/' . $country . '/en/account/auto-topup">Set Up Auto Top Up</a>';
            }
            
            return $html;
        }
        
        private static function format_error_message($error_msg) {
            $replacements = [
                "send_contact" => "receiver's contact",
                "pick_contact" => "sender's contact", 
                "send_mobile" => "receiver's mobile",
                "pick_mobile" => "sender's mobile",
                "send_code" => "receiver's postcode",
                "pick_code" => "sender's postcode"
            ];
            
            return str_replace(array_keys($replacements), array_values($replacements), $error_msg);
        }
    
        public static function get_api_detail_bulk($post) {
            if (!class_exists('WC_Easyparcel_Shipping_Method')) {
                include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Method.php';
            }
            
            if (!class_exists('WC_Easyparcel_Shipping_Method')) {
                return (object)array('shipment_providers_list' => array(), 'dropoff_point_list' => array());
            }
            
            try {
                $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();
                $rates = $WC_Easyparcel_Shipping_Method->get_order_shipping_price_list($post->ID);
            } catch (Exception $e) {
                $rates = array();
            }
        
            $obj = (object)array('shipment_providers_list' => array(), 'dropoff_point_list' => array());
        
            if (is_array($rates)) {
                foreach($rates as $rate){
                    $shipment_provider = (object)array(
                        'cid' => $rate['courier_id'] ?? '',
                        'ts_slug' => $rate['id'] ?? '',
                        'provider_name' => $rate['label'] ?? '',
                        'have_dropoff' => isset($rate['dropoff_point']) ? count($rate['dropoff_point']) > 0 : false
                    );
                    array_push($obj->shipment_providers_list, $shipment_provider);
        
                    $dropoff = array($rate['id'] ?? '' => $rate['dropoff_point'] ?? array());
                    array_push($obj->dropoff_point_list, $dropoff);
                }
            }
        
            return $obj;
        }
    
        public static function destination_columns( $columns ) {
            $columns['easyparcel_order_list_destination'] = __( 'Destination', 'easyparcel-shipping' );
            return $columns;
        }
    
        public static function render_destination_columns( $column, $order ) {
            if ( $column == 'easyparcel_order_list_destination' ) {
                $current_order = EP_EasyParcel::is_wc_hpos_enable() ? $order : wc_get_order($GLOBALS['post']->ID ?? 0);

                if ($current_order) {
                    $WC_Country = new WC_Countries();
                    echo (strtolower($WC_Country->get_base_country()) !== strtolower($current_order->get_shipping_country())) ? "International" : "Domestic";
                }
            }
        }
    
        public static function shop_order_columns( $columns ) {
            $columns['easyparcel_order_list_shipment_tracking'] = __( 'Shipment Tracking', 'easyparcel-shipping' );
            return $columns;
        }
    
        public static function render_shop_order_columns( $column, $order ) {
            if ( 'easyparcel_order_list_shipment_tracking' === $column ) {
                $current_order = EP_EasyParcel::is_wc_hpos_enable() ? $order : wc_get_order($GLOBALS['post']->ID ?? 0);
                echo wp_kses_post( self::get_shipment_tracking_column( $current_order ) );
            }
        }
    
        public static function get_shipment_tracking_column( $order ) {
            if (!$order || !$order->meta_exists('_ep_payment_status')) {
                return '–';
            }
            
            $tracking_data = array(
                'courier' => $order->get_meta('_ep_selected_courier') ?: '-',
                'awb' => $order->get_meta('_ep_awb') ?: '-',
                'tracking_url' => $order->get_meta('_ep_tracking_url') ?: '-',
                'awb_link' => $order->get_meta('_ep_awb_id_link') ?: '-'
            );
            
            return sprintf(
                '<ul class="easyparcel_order_list_shipment_tracking"><li><div><b>%s</b></div><a href="%s" target="_blank">%s</a>%s</li></ul>',
                esc_html($tracking_data['courier']),
                esc_url($tracking_data['tracking_url']),
                esc_html($tracking_data['awb']),
                ($tracking_data['awb_link'] !== '-') ? '<a href="' . esc_url($tracking_data['awb_link']) . '" target="_blank">[Download AWB]</a>' : ''
            );
        }

        public static function destination_columns_sortable( $columns ) {
            return wp_parse_args( array('easyparcel_order_list_destination' => '_shipping_country'), $columns );
        }

        public static function generate_easyparcel_awb_zip_callback() {
			require_once(ABSPATH . 'wp-admin/includes/file.php');
			global $wp_filesystem;
			WP_Filesystem();

			if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'easyparcel_bulk_fulfillment_popup')) {
				wp_send_json_error(['message' => 'Security check failed']);
				wp_die();
			}

			if (!current_user_can('manage_woocommerce')) {
				wp_send_json_error(['message' => 'Insufficient permissions']);
				wp_die();
			}

			// FIXED: Sanitize $_POST immediately with map_deep for arrays
			$order_ids = [];
			if (isset($_POST['order_ids'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized on next line
				$raw_data = map_deep(wp_unslash($_POST['order_ids']), 'sanitize_text_field');
                if (is_array($raw_data)) {
                    $order_ids = array_filter($raw_data, function($value) {
                        return !empty(trim($value)) && is_numeric($value);
                    });
                } else {
                    $order_ids = array_filter(explode(',', $raw_data), function($value) {
                        return !empty(trim($value)) && is_numeric($value);
                    });
                }
			}

			if (empty($order_ids)) {
				wp_send_json_error(['message' => 'No orders selected.']);
				wp_die();
			}

			$awb_urls = self::get_awb_urls_for_orders($order_ids);
			if (!empty($awb_urls)) {
				$result = self::create_awb_zip($awb_urls, $order_ids);
				if ($result['success']) {
					wp_send_json_success(['download_link' => $result['download_link']]);
				} else {
					wp_send_json_error(['message' => $result['message']]);
				}
			} else {
				wp_send_json_error(['message' => 'No AWB URLs found. Please make sure the selected orders have been fulfilled through EasyParcel and have valid AWB links.']);
			}
			wp_die();
		}
        
        private static function create_awb_zip($awb_urls, $order_ids) {
            $zip_filename = 'AWBs_' . time() . '.zip';
            $zip_filepath = wp_upload_dir()['basedir'] . '/' . $zip_filename;
            $zip = new ZipArchive();
            
            if ($zip->open($zip_filepath, ZipArchive::CREATE) !== TRUE) {
                return ['success' => false, 'message' => 'Failed to create ZIP file.'];
            }
            
            foreach ($awb_urls as $index => $url) {
                $order_id = isset($order_ids[$index]) ? $order_ids[$index] : ($index + 1);
                $filename = 'AWB_Order_' . $order_id . '.pdf';
                
                $content = wp_remote_get($url);
                if (wp_remote_retrieve_response_code($content) === 200) {
                    $zip->addFromString($filename, wp_remote_retrieve_body($content));
                }
            }
            $zip->close();
            self::store_zip_file_info($zip_filepath);
            
            return [
                'success' => true, 
                'download_link' => wp_upload_dir()['baseurl'] .'/'. urlencode($zip_filename)
            ];
        }

        public static function get_awb_urls_for_orders($order_ids) {
            $awb_urls = [];
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order || !$order->meta_exists('_ep_payment_status')) continue;

                $awb_link = $order->get_meta('_ep_awb_id_link');
                if (!empty($awb_link) && ($awb_link != "-")) {
                    $awb_urls[] = str_replace('&amp;', '&', $awb_link);
                }
            }
            return $awb_urls;
        }
        
        public static function store_zip_file_info($filepath) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            global $wp_filesystem;
            WP_Filesystem();

            $awb_manager_path = wp_upload_dir()['basedir'] . '/awb_zip_manager.json';
            $json_data = [];
            
            if($wp_filesystem->exists($awb_manager_path)){
                $existing_content = $wp_filesystem->get_contents($awb_manager_path);
                $json_data = json_decode($existing_content, true) ?: [];
            }

            $json_data[] = array("file" => $filepath, "time" => time());
            $wp_filesystem->put_contents($awb_manager_path, wp_json_encode($json_data));
        }
        
        public static function delete_old_zip_files() {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            global $wp_filesystem;
            WP_Filesystem();

            $awb_manager_path = wp_upload_dir()['basedir'] . '/awb_zip_manager.json';
            $json_data = [];
            
            if($wp_filesystem->exists($awb_manager_path)){
                $existing_content = $wp_filesystem->get_contents($awb_manager_path);
                $json_data = json_decode($existing_content, true) ?: [];
            }
            
            $new_json_data = [];
            foreach($json_data as $item){
                if(time() - $item['time'] > 3 * DAY_IN_SECONDS){
                    wp_delete_file($item['file']);
                }else{
                    $new_json_data[] = $item;
                }
            }
            $wp_filesystem->put_contents($awb_manager_path, wp_json_encode($new_json_data));
        }

		  public static function handle_download_easyparcel_awb_bulk_action($redirect_to, $action, $post_ids) {
		if ($action !== 'download_easyparcel_awb') {
			return $redirect_to;
		}

		$awb_urls = self::get_awb_urls_for_orders($post_ids);

		if (!empty($awb_urls)) {
			self::set_temp_data('awb_download', array(
				'action' => 'awb_download',
				'count' => count($awb_urls),
				'order_ids' => $post_ids
			));
			return $redirect_to;
		} else {
			// Use transients instead of URL parameters
			self::set_temp_data('awb_error', array(
				'action' => 'awb_error',
				'count' => count($post_ids)
			));
			return $redirect_to;
		}
	}

			public static function display_awb_download_notice() {
				// Handle successful AWB download
				$session_data = self::get_temp_data('awb_download');
				if ($session_data && $session_data['action'] === 'awb_download') {
					self::delete_temp_data('awb_download');

					if ($session_data['count'] > 0 && !empty($session_data['order_ids'])) {
						self::render_awb_download_script($session_data['count'], $session_data['order_ids']);
					}
				}

				// ADD THIS: Handle AWB download errors
				$error_data = self::get_temp_data('awb_error');
				if ($error_data && $error_data['action'] === 'awb_error') {
					self::delete_temp_data('awb_error');

					$message = sprintf('No AWBs found for the selected orders (%s orders checked). Please ensure orders are fulfilled through EasyParcel.', intval($error_data['count']));
					echo '<div id="message" class="error fade"><p>' . esc_html($message) . '</p></div>';
				}
			}
        
        private static function render_awb_download_script($count, $order_ids) {
            echo '<div id="awb-download-message" class="updated fade">';
            echo '<p><span id="awb-status">' . esc_html(sprintf('Creating ZIP file for %s AWBs...', intval($count))) . '</span></p>';
            echo '</div>';
            
            // Generate a unique script handle
            $script_handle = 'easyparcel-awb-download-' . uniqid();
            
            // Register and localize the script
            wp_register_script($script_handle, '', array('jquery'), '1.0.0', true);
            wp_localize_script($script_handle, 'easyparcelAwbData', array(
                'orderIds' => $order_ids,
                'nonce' => wp_create_nonce('easyparcel_bulk_fulfillment_popup'),
                'ajaxUrl' => admin_url('admin-ajax.php')
            ));
            wp_enqueue_script($script_handle);
            
            echo '<script type="text/javascript">
            jQuery(document).ready(function() {
                jQuery.ajax({
                    url: easyparcelAwbData.ajaxUrl,
                    type: "POST",
                    data: {
                        action: "generate_easyparcel_awb_zip",
                        security: easyparcelAwbData.nonce,
                        order_ids: easyparcelAwbData.orderIds
                    },
                    success: function(response) {
                        if (response.success && response.data.download_link) {
                            jQuery("#awb-status").text("Download starting...");
                            window.location.href = response.data.download_link;
                            setTimeout(function() { jQuery("#awb-download-message").fadeOut(); }, 2000);
                        } else {
                            jQuery("#awb-status").text("Error: " + (response.data ? response.data.message : "Unknown error"));
                            jQuery("#awb-download-message").removeClass("updated").addClass("error");
                        }
                    },
                    error: function() {
                        jQuery("#awb-status").text("Error creating ZIP file. Please try again.");
                        jQuery("#awb-download-message").removeClass("updated").addClass("error");
                    }
                });
            });
            </script>';
        }
        
        private static function render_direct_download_script($count, $order_ids) {
            $awb_urls = self::get_awb_urls_for_orders($order_ids);
            
            if (!empty($awb_urls)) {
                echo '<div id="message" class="updated fade">';
                /* translators: %s: Number of AWB files being downloaded */
                $message = _n('%s AWB downloading automatically.', '%s AWBs downloading automatically.', $count, 'easyparcel-shipping');
                echo '<p>' . esc_html(sprintf($message, intval($count))) . '</p>';
                echo '</div>';
                
                echo '<script type="text/javascript">
                jQuery(document).ready(function() {
                    var downloadDelay = 0;
                    var awbUrls = ' . wp_json_encode($awb_urls) . ';
                    awbUrls.forEach(function(url, index) {
                        setTimeout(function() { window.open(url, "_blank"); }, downloadDelay);
                        downloadDelay += 500;
                    });
                    setTimeout(function() { jQuery("#message").fadeOut(); }, downloadDelay + 2000);
                });
                </script>';
            }
        }

        public static function add_bulk_fulfillment_script() {
            $screen = get_current_screen();
            if (!$screen || (!in_array($screen->id, ['edit-shop_order', 'woocommerce_page_wc-orders']))) {
                return;
            }
            
            // Generate nonces once and escape them
            $fulfillment_nonce = wp_create_nonce('easyparcel_bulk_fulfillment_popup');
            
            // Keeping original CSS and JavaScript as requested - can be further optimized if needed
            ?>
            <style>
            /* Popup styles - shortened version */
            #easyparcel_fulfillment_popout {
                display: none !important; position: fixed !important; top: 0 !important; left: 0 !important;
                width: 100% !important; height: 100% !important; background: rgba(0, 0, 0, 0.7) !important;
                z-index: 999999 !important; overflow: hidden !important;
            }
            #easyparcel_fulfillment_popout.show { display: block !important; }
            .fulfillment_popup_row {
                position: absolute !important; top: 50% !important; left: 50% !important;
                transform: translate(-50%, -50%) !important; background: #fff !important;
                border-radius: 5px !important; width: 550px !important; height: auto !important;
                max-height: 500px !important; overflow: visible !important;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3) !important; box-sizing: border-box !important;
            }
            .popup_header {
                position: relative !important; padding: 15px 50px 15px 20px !important;
                background: #0073aa; color: #fff !important; border-radius: 5px 5px 0 0 !important;
                border-bottom: 1px solid #005a87; box-sizing: border-box !important;
            }
			.fulfillment_popup_row .popup_header{
				background: #fff !important;
				border-bottom: 1px solid #e0e0e0 !important;
			}
            .popup_close_icon {
                position: absolute !important; top: 15px !important; right: 15px !important;
                width: 20px !important; height: 20px !important; color: #fff !important;
                cursor: pointer !important; font-size: 18px !important; line-height: 1 !important; z-index: 10 !important;
            }
            .popup_title {
                margin: 0 !important; font-size: 16px !important; font-weight: 600 !important;
                color: #fff; padding-right: 30px !important; white-space: nowrap !important;
                overflow: hidden !important; text-overflow: ellipsis !important;
            }
            .fulfillment_popup_row .popup_header .popup_title{
				color: black;
			}
            .popup_body {
                padding: 20px !important; background: #fff !important; box-sizing: border-box !important;
                width: 100% !important; overflow: visible !important;
            }
            .popup_body .form-field {
                margin-bottom: 15px !important; clear: both !important; width: 100% !important; box-sizing: border-box !important;
            }
            .popup_body .form-field label {
                display: block !important; margin-bottom: 5px !important; font-weight: 600 !important; color: #333 !important;
            }
            .popup_body select, .popup_body input[type="text"] {
                width: 100% !important; padding: 8px 12px !important; border: 1px solid #ddd !important;
                border-radius: 3px !important; font-size: 14px !important; box-sizing: border-box !important; height: 38px !important;
            }
            .popup_body .button-save-form {
                background: #0073aa !important; border: 1px solid #0073aa !important; color: #fff !important;
                padding: 10px 20px !important; border-radius: 3px !important; cursor: pointer !important;
                font-size: 14px !important; min-width: 120px !important;
            }
            .popup_body .button-save-form:hover { background: #005a87 !important; border-color: #005a87 !important; }
            .popup_body .button-save-form:disabled { opacity: 0.6 !important; cursor: not-allowed !important; }
            #easyparcel_result_popup {
                display: none !important; position: fixed !important; top: 0 !important; left: 0 !important;
                width: 100% !important; height: 100% !important; background: rgba(0, 0, 0, 0.7) !important;
                z-index: 9999999 !important; overflow: hidden !important;
            }
            #easyparcel_result_popup.show { display: block !important; }
            .result_popup_row {
                position: absolute !important; top: 50% !important; left: 50% !important;
                transform: translate(-50%, -50%) !important; background: #fff !important; border-radius: 5px !important;
                width: 500px !important; max-height: 400px !important; overflow: visible !important;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3) !important; box-sizing: border-box !important;
            }
            </style>
            
            <script type="text/javascript">
            function openBulkFulfillmentPopup() {
				if (typeof easyparcelBulkOrderIds === 'undefined') {
					alert('No order IDs found. Please try again.');
					return;
				}

				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
                        action: 'easyparcel_bulk_fulfillment_popup',
                        security: '<?php echo esc_js($fulfillment_nonce); ?>',
                        order_ids: easyparcelBulkOrderIds
                    },
					success: function(response) {
                        jQuery('#easyparcel_fulfillment_popout, #easyparcel_result_popup').remove();
                        jQuery('body').append(response);

                        var popup = jQuery('#easyparcel_fulfillment_popout');
                        if (popup.length) {
                            popup.addClass('show');
                            setTimeout(function() {
                                initializeDatepicker();
                                initializeSelect2();
                            }, 100);
                        } else {
                            alert('Error loading fulfillment form. Please try again.');
                        }
                    },
                    error: function() { alert('Error loading fulfillment form. Please try again.'); }
				});
			}
            
			function initializeDatepicker() {
				jQuery('.easyparcel-datepicker').datepicker({
					dateFormat: 'yy-mm-dd', minDate: 0, maxDate: '+1Y', changeMonth: true, changeYear: true,
					beforeShow: function(input, inst) {
						setTimeout(function() { inst.dpDiv.css({'z-index': 2000000}); }, 0);
					}
				});
			}

			function initializeSelect2() {
				initializeEasyParcelCourierDropdowns();
			}
				
            function initializeEasyParcelCourierDropdowns() {
                if (jQuery.fn.select2) {
                    if (jQuery('#shipping_provider').hasClass('select2-hidden-accessible')) {
                        jQuery('#shipping_provider').select2('destroy');
                    }
                    jQuery('#shipping_provider').select2({
                        width: '100%', dropdownParent: jQuery('body'),
                        containerCssClass: 'easyparcel-courier-select-container',
                        dropdownCssClass: 'easyparcel-courier-dropdown',
                        placeholder: 'Select Preferred Courier Service', allowClear: false
                    });
                }
            }
            
            jQuery(document).on('click', '.popup_close_icon, .popupclose', closePopup);
            jQuery(document).on('keydown', function(e) { if (e.keyCode === 27) closePopup(); });
            
            function closePopup() {
                jQuery('#easyparcel_fulfillment_popout').removeClass('show');
                setTimeout(function() { jQuery('#easyparcel_fulfillment_popout').remove(); }, 300);
            }
            
            jQuery(document).on('click', '.button-save-form', function(e) {
                e.preventDefault();
                
                var form = jQuery('#add_fulfillment_form');
                var formData = {
                    shipping_provider: form.find('#shipping_provider').val(),
                    date_shipped: form.find('#date_shipped').val(),
                    order_id: form.find('#order_id').val(),
                    drop_off_point: form.find('#drop_off').val() || '',
                    courier_name: form.find('#shipping_provider option:selected').text()
                };
                var button = jQuery(this);
                
                // Validation
                if (!formData.shipping_provider) {
                    jQuery('#shipping_provider').next('.select2-container').find('.select2-selection').css('border-color', 'red');
                    return false;
                }
                if (!formData.date_shipped) {
                    jQuery('#date_shipped').css('border-color', 'red');
                    return false;
                }
                
                // Reset validation styles
                jQuery('#shipping_provider').next('.select2-container').find('.select2-selection').css('border-color', '#ddd');
                jQuery('#date_shipped').css('border-color', '#ddd');
                
                button.prop('disabled', true).val('Processing...');
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wc_shipment_tracking_save_form_bulk',
                        security: '<?php echo esc_js($fulfillment_nonce); ?>',
                        form_nonce: '<?php echo esc_js(wp_create_nonce("easyparcel_bulk_fulfillment_form")); ?>', // ADD THIS LINE
                        shipping_provider: formData.shipping_provider,
                        courier_name: formData.courier_name,
                        drop_off_point: formData.drop_off_point,
                        pick_up_date: formData.date_shipped,
                        order_id: formData.order_id
                    },
                    success: function(response) {
                        button.prop('disabled', false).val('Fulfill Order');

                        var result;
                        var responseMessage = '';
                        var responseStatus = 'error';

                        // Check if response is already an object (JSON response)
                        if (typeof response === 'object' && response !== null) {
                            result = response;
                            responseMessage = result.data ? result.data.message : (result.message || 'Unknown response');
                            responseStatus = result.success ? 'success' : 'error';
                        } else {
                            // Handle string response
                            try {
                                result = JSON.parse(response);
                                responseMessage = result.data ? result.data.message : (result.message || 'Unknown response');
                                responseStatus = result.success ? 'success' : 'error';
                            } catch (e) {
                                // If parsing fails, treat as string
                                responseMessage = response || 'Unknown error occurred';
                                // Check if string contains success indicators
                                if (typeof response === 'string' && (
                                    response.toLowerCase().includes('success') || 
                                    response.includes('fulfilled successfully')
                                )) {
                                    responseStatus = 'success';
                                }
                            }
                        }

                        showResultPopup(
                            responseStatus === 'success' ? 'Order Fulfilled Successfully' : 'EasyParcel Order Failed',
                            responseMessage, 
                            responseStatus
                        );
                    },
                    error: function() {
                        button.prop('disabled', false).val('Fulfill Order');
                        showResultPopup('EasyParcel Order Failed', 'Error submitting form. Please try again.', 'error');
                    }
                });
            });

            function showResultPopup(title, message, type) {
                jQuery('#easyparcel_fulfillment_popout').remove();
                
                var iconColor = type === 'success' ? '#4CAF50' : '#f44336';
                var processedMessage = message;
                
                if (type === 'success' && message.includes('Order(s) fulfilled successfully!')) {
                    processedMessage = message.replace('Order(s) fulfilled successfully!', 
                        '<span style="color: #4CAF50; font-weight: bold;">Order(s) fulfilled successfully!</span>');
                } else if (type === 'error' && message.includes('<hr>')) {
                    var parts = message.split('<hr>');
                    processedMessage = '<span style="color: #f44336;">' + parts[0] + '</span>';
                    if (parts[1]) processedMessage += '<hr>' + parts[1];
                } else if (type === 'error') {
                    processedMessage = '<span style="color: #f44336;">' + message + '</span>';
                }
                
                var popupHtml = '<div id="easyparcel_result_popup"><div class="result_popup_row">' +
                    '<div class="popup_header" style="background: ' + iconColor + ';"><h3 class="popup_title">' + title + '</h3>' +
                    '<span class="dashicons dashicons-no-alt popup_close_icon" onclick="closeResultPopup()"></span></div>' +
                    '<div class="popup_body"><div style="text-align: center; padding: 20px;"><div style="font-size: 16px; line-height: 1.5;">' + processedMessage + '</div></div>' +
                    '<div style="text-align: center; padding: 20px;"><button class="button-primary" onclick="closeResultPopup()" style="background: ' + iconColor + '; border-color: ' + iconColor + ';">Close</button></div></div></div></div>';
                
                jQuery('body').append(popupHtml);
                jQuery('#easyparcel_result_popup').addClass('show');
            }

            function closeResultPopup() {
                jQuery('#easyparcel_result_popup').removeClass('show');
                setTimeout(function() {
                    jQuery('#easyparcel_result_popup').remove();
                    window.location.reload();
                }, 300);
            }
            
            jQuery(document).on('change', '#shipping_provider', function() {
                var shipping_provider = jQuery(this).val();
                var easyparcel_dropoff = jQuery('#easyparcel_dropoff').val();
                jQuery('.drop_off_field').html('');

                if (!easyparcel_dropoff) return;

                try {
                    var easyparcel_dropoff_list = JSON.parse(easyparcel_dropoff);

                    for (let i = 0; i < easyparcel_dropoff_list.length; i++) {
                        if (easyparcel_dropoff_list[i][shipping_provider] && 
                            easyparcel_dropoff_list[i][shipping_provider].length > 0) {
                            
                            var dropoff_select = '<label for="drop_off">Drop Off Point:</label>' +
                                '<select id="drop_off" name="drop_off" style="width:100%;">' +
                                '<option value="">[Optional] Select Drop Off Point</option>';
                            
                            for (let j = 0; j < easyparcel_dropoff_list[i][shipping_provider].length; j++) {
                                dropoff_select += '<option value="' + easyparcel_dropoff_list[i][shipping_provider][j]['point_id'] + '">' + 
                                                easyparcel_dropoff_list[i][shipping_provider][j]['point_name'] + '</option>';
                            }
                            dropoff_select += '</select>';

                            jQuery('.drop_off_field').html(dropoff_select);
                            
                            if (jQuery.fn.select2) {
                                setTimeout(function() {
                                    if (jQuery('#drop_off').hasClass('select2-hidden-accessible')) {
                                        jQuery('#drop_off').select2('destroy');
                                    }
                                    jQuery('#drop_off').select2({
                                        width: '100%', dropdownParent: jQuery('body'),
                                        containerCssClass: 'ep-select-container',
                                        dropdownCssClass: 'ep-select-dropdown',
                                        placeholder: '[Optional] Select Drop Off Point', allowClear: true
                                    });
                                }, 100);
                            }
                            break;
                        }
                    }
                } catch (e) {
                    console.error('Error parsing dropoff data:', e);
                }
            });
            </script>
            <?php
        }

        public static function is_hpos_enabled() {
            return class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
                   \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }

        public static function get_order($order_id) {
            return wc_get_order($order_id);
        }
    }
}