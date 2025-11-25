<?php

add_action( 'wp_ajax_wc_shipment_tracking_save_form', 'save_meta_box_ajax' );	



function save_meta_box_ajax() {
    // Sanitize the security nonce first
    $security_nonce = isset( $_POST['security'] ) ? sanitize_text_field( $_POST['security'] ) : '';
    
    // Check if our nonce is set and valid
    if ( empty( $security_nonce ) || ! wp_verify_nonce( $security_nonce, 'create-easyparcel-fulfillment' ) ) {
        wp_die( 'Security check failed' );
    }
    if(isset($_POST)){
        $_POST = sanitizeEverything('sanitize_text_field', $_POST);
    }
    check_ajax_referer( 'create-easyparcel-fulfillment', 'security', true );
    // Sanitize and validate input data
    $shipping_provider = isset( $_POST['shipping_provider'] ) ? sanitize_text_field( $_POST['shipping_provider'] ) : '';
    $courier_name = isset( $_POST['courier_name'] ) ? sanitize_text_field( $_POST['courier_name'] ) : '';
    $drop_off_point = isset( $_POST['drop_off_point'] ) ? sanitize_text_field( $_POST['drop_off_point'] ) : '';
    $tracking_number = isset( $_POST['tracking_number'] ) ? sanitize_text_field( $_POST['tracking_number'] ) : '';
    $tracking_url = isset( $_POST['tracking_url'] ) ? esc_url_raw( $_POST['tracking_url'] ) : '';

    ### Order Part ###
    $pick_up_date = isset( $_POST['pick_up_date'] ) ? wc_clean($_POST['pick_up_date']) : '';
    $easycover = isset( $_POST['easycover'] ) ? wc_clean($_POST['easycover']) : '';
    $easyparcel_ddp = isset( $_POST['easyparcel_ddp'] ) ? wc_clean($_POST['easyparcel_ddp']) : '';
    $easyparcel_parcel_category = isset( $_POST['easyparcel_parcel_category'] ) ? wc_clean($_POST['easyparcel_parcel_category']) : '';
    $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

    if ( ! $order_id ) {
        wp_die( 'Invalid order ID' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_die( 'Order not found' );
    }

    // Check user capability
    if ( ! current_user_can( 'edit_post', $order_id ) ) {
        wp_die( 'You do not have permission to edit this order' );
    }
    // wp_die(print_r($order->get_meta( '_ep_payment_status' ),true));

    $easyparcel_paid_status = $order->get_meta( '_ep_payment_status' ) === '1';
    if (!class_exists('WC_Easyparcel_Shipping_Method')) {
        include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Method.php';
    }
    $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();


    if(!$easyparcel_paid_status){
        ### Add Fulfillment Part ###
        if ($pick_up_date == '')
            echo "Please fill in Drop Off / Pick Up Date";
        else if ($shipping_provider == '')
            echo "Please select Courier Services";
        else {
            $obj = (object)array();
            $obj->order_id = $order_id;
            $obj->pick_up_date = $pick_up_date;
            $obj->shipping_provider = $shipping_provider;
            $obj->courier_name = $courier_name;
            $obj->drop_off_point = $drop_off_point;
            $obj->easycover = $easycover;
            $obj->easyparcel_ddp = $easyparcel_ddp;
            $obj->easyparcel_parcel_category = $easyparcel_parcel_category;
            $ep_order = $WC_Easyparcel_Shipping_Method->process_booking_order($obj);
            $error_msg='';
            
            if (!class_exists('Easyparcel_Shipping_API')) {
                include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
            }
            
            // get credit balance
            $credit_balance = Easyparcel_Shipping_API::getCreditBalance();
            $credit_balance_html = "<hr>Your EasyParcel Credit Balance: " . esc_html( $credit_balance );
            
            if(isset($WC_Easyparcel_Shipping_Method->settings['sender_country'])){
                    $sender_country = esc_attr( strtolower($WC_Easyparcel_Shipping_Method->settings['sender_country']) );
                    $credit_balance_html .= '&nbsp;&nbsp;<a target="_blank" href="https://app.easyparcel.com/' . $sender_country . '/en/account/topup">Top Up</a> | <a target="_blank" href="https://app.easyparcel.com/' . $sender_country . '/en/account/auto-topup">Set Up Auto Top Up</a>';
            }

            if(!empty($ep_order)){
                $error_msg = $ep_order;
                $error_msg = str_replace("send_contact", "receiver's contact", $error_msg);
                $error_msg = str_replace("pick_contact", "sender's contact", $error_msg);

                $error_msg = str_replace("send_mobile", "receiver's mobile", $error_msg);
                $error_msg = str_replace("pick_mobile", "sender's mobile", $error_msg);

                $error_msg = str_replace("send_code", "receiver's postcode", $error_msg);
                $error_msg = str_replace("pick_code", "sender's postcode", $error_msg);
            
                $error_msg .= $credit_balance_html;
                echo wp_kses_post( $error_msg ); // Change from print_r to echo
            } else {
                echo esc_html__( 'success', 'easyparcel-shipping' ). wp_kses_post( $credit_balance_html );
            }  
        }

    }else{
        ### Edit Fulfillment Part ###
        if ( strlen( $tracking_number ) == 0)
            wp_die( 'Please fill in tracking number.' );
        else if (strlen( $tracking_url ) == 0)
            wp_die( 'Please fill in tracking url.' );
        else if ($shipping_provider == '' )
            wp_die( 'Please select courier service.' );
        else {		
            $order->update_meta_data('_ep_awb', $tracking_number);
            $order->update_meta_data('_ep_selected_courier', $courier_name);
            // $order->update_meta_data('_ep_awb_id_link', '');
            $order->update_meta_data('_ep_tracking_url', $tracking_url);
            $order->save();
            echo 'successEdit';
        }
    }

    die();
}
?>