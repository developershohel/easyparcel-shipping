<?php

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }
    if (!class_exists('WC_Easyparcel_Shipping_Method')) {
        include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Method.php';
    }
    $WC_Easyparcel_Shipping_Method = new WC_Easyparcel_Shipping_Method();
    
    // Get order first
    $order = wc_get_order( self::get_order_id() );
    
    // Add handler for retry button
    if (isset($_POST['retryAWB']) && !empty($_POST['retryAWB'])) {
        // Sanitize the nonce first
        $retry_nonce = isset($_POST['retry_awb_nonce']) ? sanitize_text_field($_POST['retry_awb_nonce']) : '';
        
        // Verify nonce for security
        if (!empty($retry_nonce) && wp_verify_nonce($retry_nonce, 'retry_awb_action')) {
            if($WC_Easyparcel_Shipping_Method->process_retrive_booking_awb(self::get_order_id())){
                $order = wc_get_order( self::get_order_id() ); // Refresh order data
                // Optionally add success message
                echo '<div class="notice notice-success"><p>AWB retrieval attempted successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>AWB retrieval failed. Please try again.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Security check failed.</p></div>';
        }
    }
    
    $selected_courier = (!empty($order->get_meta('_ep_selected_courier'))) ? $order->get_meta('_ep_selected_courier') : '';
    $awb = (!empty($order->get_meta('_ep_awb'))) ? $order->get_meta('_ep_awb') : '';
    $tracking_url = (!empty($order->get_meta('_ep_tracking_url'))) ? $order->get_meta('_ep_tracking_url') : '';
    $awb_link = (!empty($order->get_meta('_ep_awb_id_link'))) ? $order->get_meta('_ep_awb_id_link') : '';
    $easyparcel_paid_status = ($order->meta_exists('_ep_payment_status')) ? 1 : 0; # 1 = Paid / 0 = Pending

    $default_provider = $order->get_shipping_method();
    if(!empty($selected_courier)){
        $default_provider = $selected_courier;
    }

    $coureierDDP = ["EP-CR0DI", "EP-CR06"];
    $shipment_providers_list = array();
    $easycover_list = array();
    $insurance_basic_coverage = array();
    $ddp_list = array();
    $dropoff_point_list = array();
    $is_auto_fulfillment_preset = false;
    $selected_service_name = '';
    $selected_service_id = '';
    $selected_dropoff_point = '';

    $auto_fulfillment_setting = get_option('easyparcel_auto_fulfillment_settings');
    
    $rates = $WC_Easyparcel_Shipping_Method->get_order_shipping_price_list($order->id);
    foreach($rates as $rate){
        $shipment_provider = (object)array();
        $shipment_provider->cid = $rate['courier_id'];
        $shipment_provider->ts_slug = $rate['id'];
        $shipment_provider->provider_name = $rate['label'];
        $shipment_provider->price = $rate['cost'] ?? 0;
        $shipment_provider->currency = $rate['basic_coverage_currency'] ?? "";
        $shipment_provider->have_dropoff = is_array($rate['dropoff_point'] ?? null) && count($rate['dropoff_point']) > 0;
        array_push($shipment_providers_list, $shipment_provider);

        if($rate['easycover']){	
            array_push($easycover_list, $rate['id']);
            $insurance_basic_coverage[$rate['id']] = array(
                'basic_coverage' => $rate['basic_coverage'], 
                'basic_coverage_currency' => $rate['basic_coverage_currency']
            );
        }

        if(in_array($rate['courier_id'], $coureierDDP)){
            array_push($ddp_list, $rate['id']);
        }

        $dropoff = array();
        $dropoff[$rate['id']] = $rate['dropoff_point'];
        array_push($dropoff_point_list, $dropoff);

			
        if(!empty($auto_fulfillment_setting['ep_is_auto_fulfillment'])){
            $is_auto_fulfillment_preset = true;
            if($rate['courier_id'] == $auto_fulfillment_setting['ep_courier']){
                if($auto_fulfillment_setting['ep_pickup_dropoff'] == "dropoff" and $shipment_provider->have_dropoff){
                    $selected_dropoff_point = $auto_fulfillment_setting['ep_courier_dropoff'];
                    $selected_service_name = $shipment_provider->provider_name;
                    $selected_service_id = $shipment_provider->ts_slug;
                }elseif($auto_fulfillment_setting['ep_pickup_dropoff'] != "dropoff" and !$shipment_provider->have_dropoff){
                    $selected_service_name = $shipment_provider->provider_name;
                    $selected_service_id = $shipment_provider->ts_slug;
                }
            }

        }

        $selected_dropoff_point = isset($rate['selected_dropoff_point']) ? $rate['selected_dropoff_point'] : $selected_dropoff_point;
    }
    
    if($is_auto_fulfillment_preset && empty($default_provider)) { 
        $default_provider = $selected_service_name;
    }

    $easycover_exclusion = [9, 13, 21, 22, 26, 27, 29, 32];

    wp_register_script( 'const-script', false , array(), EASYPARCEL_VERSION , array('in_footer' => true));
    wp_localize_script( 'const-script',  'easyparcel_account_country',  $WC_Easyparcel_Shipping_Method->settings['sender_country'] );
    wp_localize_script( 'const-script',  'easyparcel_easycover',  $easycover_list );
    wp_localize_script( 'const-script',  'easyparcel_easycover_exclusion',  $easycover_exclusion );
    wp_localize_script( 'const-script',  'easyparcel_coureierDDP',  $ddp_list );
    wp_localize_script( 'const-script',  'easyparcel_insurance_basic_coverage',  $insurance_basic_coverage );
    wp_enqueue_script('const-script');
?>

<div id="easyparcel-fulfillment-form">
    <p class="form-field shipping_provider_field">
        <label for="shipping_provider"><?php echo esc_html__( 'Courier Services:', 'easyparcel-shipping' ) ?></label><br/>
        <select id="shipping_provider" name="shipping_provider" class="chosen_select shipping_provider_dropdown" style="width:100%;">
            <option value=""><?php echo esc_html__( 'Select Preferred Courier Service', 'easyparcel-shipping' ) ?></option>
            <?php foreach ( $shipment_providers_list as $providers ) {?>
                <option value="<?php echo esc_attr( $providers->ts_slug ) ?>" <?php selected( $default_provider, $providers->provider_name )?> >
                    <?php echo esc_html( $providers->provider_name ) ?> - <?php echo esc_html( $providers->currency . ' ' . number_format($providers->price, 2) ) ?>
                </option>
            <?php }?>
        </select>
    </p>

    <?php woocommerce_wp_hidden_input( array(
        'id'    => 'easyparcel_dropoff',
        'value' => wp_json_encode($dropoff_point_list)
    ) );?>
    <?php woocommerce_wp_hidden_input( array(
        'id'    => 'selected_easyparcel_dropoff',
        'value' => $selected_dropoff_point
    ) );?>
    <p class="form-field drop_off_field "></p>

    <?php // handle woocommerce fulfillment create nonce ?>
    <?php woocommerce_wp_hidden_input( array(
        'id'    => 'easyparcel_fulfillment_create_nonce',
        'value' => wp_create_nonce( 'create-easyparcel-fulfillment' ),
    ) );?>

    <?php // Display only if order is paid ?>
    <?php if( $easyparcel_paid_status ) {?>
        <p class="form-field tracking_number_field ">
            <label for="tracking_number">Tracking number:</label>
            <input type="text" class="short" style="" name="tracking_number" id="tracking_number" value="<?php echo esc_attr( $awb )?>" autocomplete="off"> 
        </p>
        <p class="form-field tracking_url_field ">
            <label for="tracking_url">Tracking url:</label>
            <input type="text" class="short" style="" name="tracking_url" id="tracking_url" value="<?php echo esc_attr( $tracking_url )?>" autocomplete="off"> 
        </p>
        <button class="button button-info btn_ast2 button-save-form">
            <?php echo esc_html__( 'Edit FulFillment', 'easyparcel-shipping' ) ?>
        </button>
        <?php // Check AWB is not empty and tracking url is not empty ?>
        <?php if(!empty($awb) and !empty($tracking_url)){ ?>
            <p class="fulfillment_details">
                <?php echo esc_attr($selected_courier); ?><br>
                <a href="<?php echo esc_url($tracking_url); ?>" target="_blank"><?php echo esc_attr($awb); ?></a><br>
                <a href="<?php echo esc_url($awb_link); ?>" target="_blank">[Download AWB]</a>
            </p>
        <?php // Check AWB is empty and tracking url is empty ?>
        <?php } else {?>
            <form method="post">
                <?php wp_nonce_field('retry_awb_action', 'retry_awb_nonce'); ?>
                <button class = "button button-primary btn_ast2 button-save" type="submit" name="retryAWB">Retry</button>
            </form>
        <?php }?>

    <?php // Display only if order is not paid ?>
    <?php } else {?>
        <?php woocommerce_wp_text_input( array(
            'id'          => 'pick_up_date',
            'label'       => __( 'Drop Off / Pick Up Date', 'easyparcel-shipping' ),
            'placeholder' => date_i18n( __( 'Y-m-d' , 'easyparcel-shipping' ), time() ),
            'description' => '',
            'class'       => 'date-picker-field',
            'value'       => date_i18n( __( 'Y-m-d' , 'easyparcel-shipping' ), current_time( 'timestamp' ) ),
        ) );?>
        <hr>
        <strong class="easyparcel-add-on">Add-On Services</strong>

        <?php woocommerce_form_field( 'easycover', array( 
            'type' => 'checkbox', 
            'class' => array('input-checkbox','easyparcel-add-on'), 
            'label' => __('EasyCover' , 'easyparcel-shipping'),
            'value'  => true, 
        ), false ); ?>

        <?php woocommerce_form_field( 'easyparcel_ddp', array( 
            'type' => 'checkbox', 
            'class' => array('input-checkbox','easyparcel-add-on'), 
            'label' => __('Delivered Duty Paid (DDP)' , 'easyparcel-shipping'),
            'value'  => true, 
        ), false ); ?>

        <p class="form-field easyparcel-add-on parcel_category_field">
            <label for="easyparcel_parcel_category"><?php echo esc_html__( 'Courier Services:', 'easyparcel-shipping' ) ?></label><br/>
            <select id="easyparcel_parcel_category" name="easyparcel_parcel_category" class="chosen_select easyparcel_parcel_category_dropdown" style="width:100%;">
                <option value=""><?php echo esc_html__( 'Select Parcel Category', 'easyparcel-shipping' ) ?></option>
                <?php foreach ( self::get_parcel_category_list() as $parcel_category ) {?>
                    <option value="<?php echo esc_attr( $parcel_category->parcel_category_id ) ?>">
                        <?php echo esc_html( $parcel_category->parcel_category ) ?>
                    </option>
                <?php }?>
            </select>
        </p>
        <button class="button button-primary btn_ast2 button-save-form">
            <?php echo esc_html__( 'Fulfill Order', 'easyparcel-shipping' ) ?>
        </button>
    <?php }?>
</div>