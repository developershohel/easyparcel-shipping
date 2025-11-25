<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}	

add_action( "wp_ajax_ep_shipping_zones_save_changes", 'ep_shipping_zones_save_changes');
add_action( "wp_ajax_ep_courier_setting_save_changes", 'ep_courier_setting_save_changes');
add_action( "wp_ajax_ep_shipping_zone_methods_save_changes", 'ep_shipping_zone_methods_save_changes' );
add_action(	'wp_ajax_get_easyparcel_default_address', 'get_easyparcel_default_address');

add_action( "wp_ajax_ep_admin_shipping_zone_save_changes", 'ep_admin_shipping_zone_save_changes');


function ep_shipping_zones_save_changes(){
    if (!class_exists('EP_Shipping_Zones')) {
        include_once EASYPARCEL_DATASTORE_PATH .'ep_shipping_zones.php';
    }
    
    // Verify nonce BEFORE sanitizing $_POST
    if ( ! isset( $_POST['wc_shipping_zones_nonce'], $_POST['changes'] ) ) {
        wp_send_json_error( 'missing_fields' );
        wp_die();
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_shipping_zones_nonce'] ) ), 'wc_shipping_zones_nonce' ) ) {
        wp_send_json_error( 'bad_nonce' );
        wp_die();
    }

    // Check User Caps.
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'missing_capabilities' );
        wp_die();
    }

    // Sanitize the changes array using WordPress core function
    $changes_sanitized = isset($_POST['changes']) && is_array($_POST['changes']) 
        ? map_deep(wp_unslash($_POST['changes']), 'sanitize_text_field')
        : array();
        
    foreach ( $changes_sanitized as $zone_id => $data ) {
        if ( isset( $data['deleted'] ) ) {
            if ( isset( $data['newRow'] ) ) {
                // So the user added and deleted a new row.
                // That's fine, it's not in the database anyways. NEXT!
                continue;
            }
            EP_Shipping_Zones::delete_zone( $zone_id );
            continue;
        }

        $zone_data = array_intersect_key(
            $data,
            array(
                'zone_id'    => 1,
                'zone_order' => 1,
            )
        );

        if ( isset( $zone_data['zone_id'] ) ) {
            if (!class_exists('EP_Shipping_Zone')) {
                include_once EASYPARCEL_DATASTORE_PATH .'ep_shipping_zone.php';
            }
            $zone = new EP_Shipping_Zone( $zone_data['zone_id'] );
            if ( isset( $zone_data['zone_order'] ) ) {
                $zone->set_zone_order( $zone_data['zone_order'] );
            }
            $zone->save();
        }
    }

    wp_send_json_success(
        array(
            'zones' => EP_Shipping_Zones::get_zones( 'json' ),
        )
    );
}

function ep_shipping_zone_methods_save_changes() {
    // Verify nonce FIRST
    if ( ! isset( $_POST['wc_shipping_zones_nonce'], $_POST['zone_id'], $_POST['changes'] ) ) {
        wp_send_json_error( 'missing_fields' );
        wp_die();
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wc_shipping_zones_nonce'] ) ), 'wc_shipping_zones_nonce' ) ) {
        wp_send_json_error( 'bad_nonce' );
        wp_die();
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'missing_capabilities' );
        wp_die();
    }

    global $wpdb;

    // Sanitize individual fields after nonce verification
    $zone_id = isset($_POST['zone_id']) ? sanitize_text_field(wp_unslash($_POST['zone_id'])) : '';

    if (!class_exists('EP_Shipping_Zone')) {
        include_once EASYPARCEL_DATASTORE_PATH . 'ep_shipping_zone.php';
    }
    
    $zone = new EP_Shipping_Zone( $zone_id );
    
    // Use map_deep instead of custom function
    $changes = isset($_POST['changes']) && is_array($_POST['changes']) 
        ? map_deep(wp_unslash($_POST['changes']), 'sanitize_text_field')
        : array();

    if ( isset( $changes['zone_name'] ) ) {
        $zone->set_zone_name( wc_clean( sanitize_text_field($changes['zone_name']) ) );
    }

    if ( isset( $changes['zone_locations'] ) ) {
        $zone->clear_locations( array( 'state', 'country', 'continent' ) );
        $locations = array_filter( array_map( 'wc_clean', (array) $changes['zone_locations'] ) );
        foreach ( $locations as $location ) {
            // Each posted location will be in the format type:code.
            $location_parts = explode( ':', sanitize_text_field($location) );
            switch ( $location_parts[0] ) {
                case 'state':
                    $zone->add_location( $location_parts[1] . ':' . $location_parts[2], 'state' );
                    break;
                case 'country':
                    $zone->add_location( $location_parts[1], 'country' );
                    break;
                case 'continent':
                    $zone->add_location( $location_parts[1], 'continent' );
                    break;
            }
        }
    }

    if ( isset( $changes['zone_postcodes'] ) ) {
        $zone->clear_locations( 'postcode' );
        $postcodes = array_filter( array_map( 'strtoupper', array_map( 'wc_clean', explode( "\n", sanitize_text_field($changes['zone_postcodes']) ) ) ) );
        foreach ( $postcodes as $postcode ) {
            $zone->add_location( $postcode, 'postcode' );
        }
    }

    if ( isset( $changes['methods'] ) ) {
        foreach ( $changes['methods'] as $instance_id => $data ) {
            $method_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id = %d", $instance_id ) );

            if ( isset( $data['deleted'] ) ) {
                if ( $wpdb->delete( "{$wpdb->prefix}easyparcel_zones_courier", array( 'id' => $instance_id ) ) ) {
                    // delete_option( $option_key ); // $option_key is not defined
                    // do_action( 'woocommerce_shipping_zone_method_deleted', $instance_id, $method_id, $zone_id );
                }
                continue;
            }

            $method_data = array_intersect_key(
                $data,
                array(
                    'method_order' => 1,
                    'enabled'      => 1,
                )
            );

            if ( isset( $method_data['method_order'] ) ) {
                $wpdb->update( "{$wpdb->prefix}easyparcel_zones_courier", array( 'courier_order' => absint( $method_data['method_order'] ) ), array( 'id' => absint( $instance_id ) ) );
            }

            if ( isset( $method_data['enabled'] ) ) {
                $is_enabled = absint( 'yes' === $method_data['enabled'] );
                if ( $wpdb->update( "{$wpdb->prefix}easyparcel_zones_courier", array( 'status' => $is_enabled ), array( 'id' => absint( $instance_id ) ) ) ) {
                    // do_action( 'woocommerce_shipping_zone_method_status_toggled', $instance_id, $method_id, $zone_id, $is_enabled );
                }
            }
        }
    }

    $zone->save();

    if (!class_exists('EP_Shipping_Zones')) {
        include_once EASYPARCEL_DATASTORE_PATH . 'ep_shipping_zones.php';
    }

    $hehe = EP_Shipping_Zones::get_zone( absint( $zone->get_id() ) );

    wp_send_json_success(
        array(
            'zone_id'   => $zone->get_id(),
            'zone_name' => $zone->get_zone_name(),
            'methods'   => $hehe->get_couriers(false,'json')
        )
    );
}


function ep_courier_setting_save_changes() {
    // ADD COMPREHENSIVE DEBUG LOGGING HERE:
    $logger = wc_get_logger();
    $context = array('source' => 'easyparcel-courier-debug');
    
    $logger->info('=== ep_courier_setting_save_changes DEBUG START ===', $context);
    $logger->info('$_POST data: ' . print_r($_POST, true), $context);
    
    // Verify nonce FIRST before any processing
    if ( ! isset( $_POST['ep_courier_setup_nonce'] ) ) {
        wp_send_json_error( 'missing_nonce' );
        wp_die();
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ep_courier_setup_nonce'] ) ), 'ep_courier_setup_nonce' ) ) {
        wp_send_json_error( 'bad_nonce' );
        wp_die();
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'missing_capabilities' );
        wp_die();
    }

    // Sanitize all POST data first - clean approach for plugin store
    $zone_id = isset($_POST['zone_id']) ? sanitize_text_field(wp_unslash($_POST['zone_id'])) : '';
    $method = isset($_POST['method']) ? sanitize_text_field(wp_unslash($_POST['method'])) : '';
    $courier_id = isset($_POST['courier_id']) ? sanitize_text_field(wp_unslash($_POST['courier_id'])) : '';
    
    // ADD DEBUG FOR SANITIZED VARIABLES:
    $logger->info('Sanitized variables:', $context);
    $logger->info('zone_id: ' . $zone_id, $context);
    $logger->info('method: ' . $method, $context);
    $logger->info('courier_id: ' . $courier_id, $context);
    
    // Validate method first
    if (!in_array($method, array('update', 'insert'), true)) {
        wp_send_json_error('invalid_method');
        wp_die();
    }
    
    // Sanitize the entire data array using WordPress core function
    $clean_data = isset($_POST['data']) && is_array($_POST['data']) ? map_deep(wp_unslash($_POST['data']), 'sanitize_text_field'): array();
    
    // ADD DEBUG FOR CLEAN DATA:
    $logger->info('clean_data: ' . print_r($clean_data, true), $context);
    
    // Build specific data arrays based on method
    $data = array();
    if ($method == 'update') {
        $data = array(
            'courier_display_name'  => $clean_data['courier_display_name'] ?? '',
            'charges_option'        => $clean_data['charges_option'] ?? '',
            'charges_value'         => $clean_data['charges_value'] ?? '',
            'free_shipping'         => $clean_data['free_shipping'] ?? '',
            'free_shipping_by'      => $clean_data['free_shipping_by'] ?? '',
            'free_shipping_value'   => $clean_data['free_shipping_value'] ?? '',
            'courier_dropoff_point' => $clean_data['courier_dropoff_point'] ?? ''
        );
    } elseif ($method == 'insert') {
        $data = array(
            'zone_id'               => $clean_data['zone_id'] ?? '',
            'courier_service'       => $clean_data['courier_service'] ?? '',
            'service_name'          => $clean_data['service_name'] ?? '',
            'courier_id'            => $clean_data['courier_id'] ?? '',      // ADD THIS LINE
            'courier_name'          => $clean_data['courier_name'] ?? '',    // ADD THIS LINE TOO
            'courier_logo'          => $clean_data['courier_logo'] ?? '',
            'courier_info'          => $clean_data['courier_info'] ?? '',
            'courier_display_name'  => $clean_data['courier_display_name'] ?? '',
            'courier_dropoff_point' => $clean_data['courier_dropoff_point'] ?? '',
            'sample_cost'           => $clean_data['sample_cost'] ?? '',
            'charges_option'        => $clean_data['charges_option'] ?? '',
            'charges_value'         => $clean_data['charges_value'] ?? '',
            'free_shipping'         => $clean_data['free_shipping'] ?? '',
            'free_shipping_by'      => $clean_data['free_shipping_by'] ?? '',
            'free_shipping_value'   => $clean_data['free_shipping_value'] ?? ''
        );
    }

    // ADD DEBUG FOR FINAL DATA ARRAY:
    $logger->info('Final $data array: ' . print_r($data, true), $context);
    
    // ADD SPECIFIC DEBUG FOR INSERT CASE:
    if ($method == 'insert') {
        $logger->info('INSERT method detected:', $context);
        $logger->info('courier_service: ' . ($data['courier_service'] ?? 'NOT_SET'), $context);
        $logger->info('service_name: ' . ($data['service_name'] ?? 'NOT_SET'), $context);
        $logger->info('Looking for courier_id in clean_data: ' . ($clean_data['courier_id'] ?? 'NOT_FOUND'), $context);
        $logger->info('Available keys in clean_data: ' . implode(', ', array_keys($clean_data)), $context);
    }

    global $wpdb;

    switch ( $method ) {
        case 'update':
            // ... existing update code ...
            break;
            
        case 'insert':
            if ( empty($zone_id) || empty($data) ) {
                wp_send_json_error( 'missing_fields' );
                wp_die();
            }
            
            // Trim data for cleaner storage
            foreach($data as &$d){
                if(!empty($d)){
                    $d = trim($d);
                }
            }
            
            // Get next courier order number
            $col = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT max(courier_order)+1 as courier_order FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id = %d",
                    $zone_id
                )
            );
            
            $courier_order = (empty($col[0]->courier_order)) ? 1 : intval($col[0]->courier_order);
            
            // ADD DEBUG BEFORE INSERT:
            $logger->info('About to INSERT with these values:', $context);
            $logger->info('service_id will be: ' . $data['courier_service'], $context);
            $logger->info('courier_id will be: ' . ($clean_data['courier_id'] ?? $data['courier_service'] ?? 'FALLBACK'), $context);
            
            // Insert new courier record
            $res = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}easyparcel_zones_courier 
                    ( zone_id, service_id, service_name, service_type, courier_id, courier_name, courier_logo, courier_info, courier_display_name, courier_dropoff_point, sample_cost, charges, charges_value, free_shipping, free_shipping_by, free_shipping_value, courier_order, status )
                    VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d )",
                    $data['zone_id'],
                    $data['courier_service'],    // service_id = EP-CS0INC ✅
                    $data['service_name'],
                    'parcel',
                    $data['courier_id'],         // courier_id = EP-CR0DI ✅ FIXED!
                    $data['courier_name'],       // courier_name = Janio Technologies Sdn. Bhd. ✅ FIXED!
                    $data['courier_logo'],
                    $data['courier_info'],
                    $data['courier_display_name'],
                    $data['courier_dropoff_point'],
                    $data['sample_cost'],
                    $data['charges_option'],
                    $data['charges_value'],
                    $data['free_shipping'],
                    $data['free_shipping_by'],
                    $data['free_shipping_value'],
                    $courier_order,
                    1
                )
            );
            
            // ADD DEBUG AFTER INSERT:
            $logger->info('INSERT result: ' . ($res ? 'SUCCESS' : 'FAILED'), $context);
            $logger->info('Last inserted ID: ' . $wpdb->insert_id, $context);
            
            break;
    }

    $logger->info('=== ep_courier_setting_save_changes DEBUG END ===', $context);

    wp_send_json_success(
        array(
            'zone_id'   => $zone_id,
            'result'    => 'success'
        )
    );
}

function get_easyparcel_default_address($obj) {
    
    // Verify nonce first
    if ( ! isset( $_POST['nonce'] ) ) {
        wp_send_json_error( 'missing_nonce' );
        wp_die();
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'get_easyparcel_default_address_nonce' ) ) {
        wp_send_json_error( 'bad_nonce' );
        wp_die();
    }

    // Check user capabilities (adjust as needed for your use case)
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'missing_capabilities' );
        wp_die();
    }

    if (!class_exists('Easyparcel_Shipping_API')) {
        include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
    }

    // Sanitize input after nonce verification
    $api = isset($_POST['api']) ? sanitize_text_field(wp_unslash($_POST['api'])) : '';
    $country = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';

    // get credit balance
    $ep_data = Easyparcel_Shipping_API::getAddressDefault($api, $country);
    
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode($ep_data);
    die;
}

function ep_admin_shipping_zone_save_changes($obj) {
    // Verify nonce FIRST
    if ( ! isset( $_POST['easyparcel_admin_shipping_zone_setup_nonce'] ) ) {
        wp_send_json_error( 'missing_nonce' );
        wp_die();
    }

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['easyparcel_admin_shipping_zone_setup_nonce'] ) ), 'easyparcel_admin_shipping_zone_setup_nonce' ) ) { 
        wp_send_json_error( 'bad_nonce' );
        wp_die();
    }

    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'missing_capabilities' );
        wp_die();
    }

    // NOW sanitize after nonce verification
    $zone_id = isset($_POST['zone_id']) ? absint($_POST['zone_id']) : 0;
    $instance_id = isset($_POST['instance_id']) ? absint($_POST['instance_id']) : 0;
    $setting_type = isset($_POST['setting_type']) ? sanitize_text_field(wp_unslash($_POST['setting_type'])) : '';
    
    // ADD: String-to-integer mapping for database
    $setting_type_map = array(
        'all' => 0,
        'cheapest' => 1,
        'couriers' => 2
    );

    $setting_type_int = isset($setting_type_map[$setting_type]) ? $setting_type_map[$setting_type] : 0;

    // Validate required fields
    if (empty($zone_id) || empty($instance_id) || empty($setting_type)) {
        wp_send_json_error('missing_required_fields');
        wp_die();
    }
    
    // Sanitize settings array using WordPress core function
    $settings = isset($_POST['settings']) && is_array($_POST['settings']) 
        ? map_deep(wp_unslash($_POST['settings']), 'sanitize_text_field')
        : array();

    global $wpdb;

    // Check if record exists
    $result = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$wpdb->prefix}easyparcel_zones_setting` WHERE zone_id = %d",
            $zone_id  // Only use zone_id
        )
    );

    if(empty($result)){
        $res = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO `{$wpdb->prefix}easyparcel_zones_setting` ( zone_id, setting_type, settings ) VALUES ( %d, %d, %s )",
                $zone_id,
                $setting_type_int,  // Use mapped integer
                wp_json_encode($settings)
            )
        );
    } else {
        $res = $wpdb->query(
            $wpdb->prepare(
                "UPDATE `{$wpdb->prefix}easyparcel_zones_setting` SET setting_type = %d, settings = %s WHERE zone_id = %d",
                $setting_type_int,  // Use mapped integer
                wp_json_encode($settings),
                $zone_id
            )
        );
    }
    
    wp_send_json_success(
        array(
            'zone_id' => $zone_id,
            'instance_id' => $instance_id,
            'res' => $res,
            'result' => 'success'
        )
    );
    
    die;
}


function sanitizeEverything( $func, $arr )
{
    $newArr = array();
    foreach( $arr as $key => $value ){
        $newArr[ $key ] = ( is_array( $value ) ? sanitizeEverything( $func, $value ) : ( is_array($func) ? call_user_func_array($func, $value) : $func( $value ) ) );
    }

    return $newArr;
}
?>