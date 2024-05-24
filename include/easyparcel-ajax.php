<?php
function easyparcel_courier_list()
{
    global $wpdb;
    $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $nonce = sanitize_text_field(wp_unslash($nonce)) ?? '';
    if (empty($nonce)) {
        wp_send_json(['status' => false, 'message' => 'Nonce is missing']);
        wp_die();
    } else if (!wp_verify_nonce($nonce, 'easyparcel_nonce')) {
        wp_send_json(['status' => false, 'message' => 'Nonce is invalid']);
        wp_die();
    }
    $zone_id = filter_input(INPUT_POST, 'zone_id', FILTER_SANITIZE_NUMBER_INT);
    $zone_id = absint($zone_id) ?? 0;
    $instance_id = filter_input(INPUT_POST, 'instance_id', FILTER_SANITIZE_NUMBER_INT);
    $instance_id = absint($instance_id) ?? 0;
    if (empty($zone_id) || empty($instance_id)) {
        wp_send_json(['status' => false, 'message' => 'No Zone ID or Instance ID Found']);
        wp_die();
    }

    if (!class_exists('Easyparcel_Extend_Shipping_Zone')) {
        include_once 'easyparcel_extend_shipping_zone.php';
    }
    $courier = $wpdb->get_var($wpdb->prepare("SELECT instance_id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id =%d AND instance_id =%d", $zone_id, $instance_id));
    $shipping_zone = new Easyparcel_Extend_Shipping_Zone();
    if (empty($courier)) {
        $shipping_zone->add_new_courier($zone_id);
    } else {
        $shipping_zone->setup_courier_content($zone_id, $instance_id);
    }
    wp_die();
}

add_action('wp_ajax_easyparcel_courier_list', 'easyparcel_courier_list');
add_action('wp_ajax_nopriv_easyparcel_courier_list', 'easyparcel_courier_list');

function easyparcel_check_setting()
{
    $nonce = filter_input(INPUT_POST, 'nonce',  FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $nonce = sanitize_text_field(wp_unslash($nonce)) ?? '';
    if (empty($nonce)) {
        wp_send_json(['status' => false, 'message' => 'Nonce is missing']);
        wp_die();
    } else if (!wp_verify_nonce($nonce, 'easyparcel_nonce')) {
        wp_send_json(['status' => false, 'message' => 'Nonce is invalid']);
        wp_die();
    }
    $value = get_option('easyparcel_settings');
    if (isset($value['enabled']) && $value['enabled'] == 'yes') {
        echo wp_json_encode(['status' => true]);
    } else {
        echo wp_json_encode(['status' => false]);
    }
    wp_die();
}

add_action('wp_ajax_easyparcel_check_setting', 'easyparcel_check_setting');
add_action('wp_ajax_nopriv_easyparcel_check_setting', 'easyparcel_check_setting');

function easyparcel_ajax_save_courier_services()
{
    global $wpdb;
    $nonce = filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $nonce = sanitize_text_field(wp_unslash($nonce)) ?? '';
    if (empty($nonce)) {
        wp_send_json(['status' => false, 'message' => 'Nonce is missing']);
        wp_die();
    } else if (!wp_verify_nonce($nonce, 'easyparcel_nonce')) {
        wp_send_json(['status' => false, 'message' => 'Nonce is invalid']);
        wp_die();
    }
    $courier_data = wp_unslash($_POST['courier_data']) ?? '';
    if (empty($courier_data)) {
        echo wp_json_encode(array('status' => false, 'message' => "We don't find any data"));
        wp_die();
    }
	if (is_array($courier_data)){
		$courier_data = array_map('sanitize_text_field', $courier_data);
	}else{
		wp_send_json(['status' => false, 'message' => 'Courier data is invalid']);
		wp_die();
	}
    $zone_id = absint($courier_data['zone_id']);

    if (empty($zone_id)) {
        echo wp_json_encode(array('status' => false, 'message' => "We don't find any zone ID"));
        wp_die();
    }

    $instance_id = absint($courier_data['instance_id']) ?? 0;
    $method = filter_input(INPUT_POST, 'courier_setting', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $method = sanitize_text_field($method) ?? '';
	if (empty($method)) {
		echo wp_json_encode(array('status' => false, 'message' => "We don't find any method"));
		wp_die();
	}
    $table = $wpdb->prefix . 'easyparcel_zones_courier';
    $courier_order_value = $wpdb->get_var($wpdb->prepare("SELECT courier_order FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id=%d", $zone_id));
    $shipping_table = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
    $shipping_method = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id=%d", $zone_id), ARRAY_A);
    try {
        if ($method == 'popup') {
            if (empty($instance_id)) {
                echo wp_json_encode(array('status' => false, 'message' => "We don't find the instance ID"));
                wp_die();
            }
            $get_courier_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE instance_id=%d AND zone_id=%d", $instance_id, $zone_id));
            if (empty($get_courier_id)) {
                if ($courier_order_value !== false) {
	                $courier_data['courier_order'] = (int)$wpdb->get_var($wpdb->prepare("SELECT MAX(courier_order) FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id=%d", $zone_id)) + 1;
                }
                $insert_courier = $wpdb->insert($table, $courier_data);
                if ($insert_courier !== false) {
                    echo wp_json_encode(array(
                        'status' => true,
                        'message' => "Courier Successfully Save",
                        'courier_name' => $courier_data['courier_display_name'] ?? $courier_data['courier_name'],
                        'courier_id' => $wpdb->insert_id
                    ));
                } else {
                    echo wp_json_encode(array('status' => false, 'message' => "Courier Didn't update"));
                    wp_die();
                }
            } else {
                $update_courier = $wpdb->update($table, $courier_data, [
                    'id' => $get_courier_id
                ]);
                if ($update_courier !== false) {
                    echo wp_json_encode(array(
                        'status' => true,
                        'message' => "Courier Successfully Save",
                        'courier_name' => $courier_data['courier_display_name'] ?? $courier_data['courier_name'],
                        'courier_id' => $get_courier_id
                    ));
                } else {
                    echo wp_json_encode(array('status' => false, 'message' => "Courier Didn't update"));
                    wp_die();
                }
            }
        } else if ($method == 'edit_courier') {
            $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
            $id = absint($id) ?? 0;
            if (empty($id)) {
                echo wp_json_encode(array('status' => false, 'message' => "Courier Don't find the courier ID"));
                wp_die();
            }
            $instance_id = $wpdb->get_var($wpdb->prepare("SELECT instance_id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id=%d", $id));
            if (empty($instance_id)) {
                $max_method_order = $wpdb->get_var($wpdb->prepare("SELECT MAX(method_order) FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id=%d", $zone_id));
                $method_order = $max_method_order + 1;
                $instance_method = $wpdb->insert($shipping_table, array(
                    'zone_id' => $zone_id,
                    'method_id' => 'easyparcel',
                    'method_order' => $method_order
                ));
                if ($instance_method !== false) {
                    $new_instance_id = $wpdb->insert_id;
                    $courier_data['instance_id'] = $new_instance_id;
                    $update_courier = $wpdb->update($table, $courier_data, [
                        'id' => $id
                    ]);
                    if ($update_courier !== false) {
                        echo wp_json_encode(array(
                            'status' => true,
                            'message' => "Courier Successfully Save",
                            'courier_id' => $id
                        ));
                    } else {
                        echo wp_json_encode(array('status' => false, 'message' => "Courier Didn't update"));
                    }
                } else {
                    echo wp_json_encode(array('status' => false, 'message' => "Courier Didn't update"));
                }
            } else {
                $update_courier = $wpdb->update($table, $courier_data, [
                    'id' => $id
                ]);
                if ($update_courier !== false) {
                    echo wp_json_encode(array(
                        'status' => true,
                        'message' => "Courier Successfully Save",
                        'courier_id' => $id
                    ));
                } else {
                    echo wp_json_encode(array('status' => false, 'message' => "Courier Didn't update"));
                }
            }
        } else if ($method == 'setup_courier') {
            if ($courier_order_value !== false) {
                $courier_data['courier_order'] = (int)$wpdb->get_var($wpdb->prepare("SELECT MAX(courier_order) FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id=%d", $zone_id)) + 1;
            }
            if (!empty($shipping_method)) {
                $max_method_order = $wpdb->get_var($wpdb->prepare("SELECT MAX(method_order) FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id=%d", $zone_id));
                $method_order = $max_method_order + 1;
                $instance_method = $wpdb->insert($shipping_table, array(
                    'zone_id' => $zone_id,
                    'method_id' => 'easyparcel',
                    'method_order' => $method_order
                ));
            } else {
                $instance_method = $wpdb->insert($shipping_table, array(
                    'zone_id' => $zone_id,
                    'method_id' => 'easyparcel',
                    'method_order' => 1
                ));
            }
            if ($instance_method !== false) {
                $new_instance_id = $wpdb->insert_id;
                $courier_data['instance_id'] = $new_instance_id;
                $insert_courier = $wpdb->insert($table, $courier_data);
                if ($insert_courier !== false) {
                    echo wp_json_encode(array(
                        'status' => true,
                        'message' => "Courier Successfully Save",
                        'courier_name' => $courier_data['courier_display_name'] ?? $courier_data['courier_name']
                    ));
                } else {
                    echo wp_json_encode(array('status' => false, 'message' => "Courier Didn't update"));
                }
            } else {
                echo wp_json_encode(array('status' => false, 'message' => "Courier Didn't update"));
            }
        }
    } catch (Exception $e) {
        echo wp_json_encode(array('status' => false, 'message' => $e->getMessage()));
    }
    wp_die();
}

add_action('wp_ajax_easyparcel_ajax_save_courier_services', 'easyparcel_ajax_save_courier_services');
add_action('wp_ajax_nopriv_easyparcel_ajax_save_courier_services', 'easyparcel_ajax_save_courier_services');