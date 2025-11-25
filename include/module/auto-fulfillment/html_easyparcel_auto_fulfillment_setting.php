<?php 

    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }	
    
    // Load required classes safely - THIS WAS MISSING!
    if (!class_exists('WC_Easyparcel_Shipping_Method')) {
        include_once EASYPARCEL_MODULE_PATH . 'setup/WC_Easyparcel_Shipping_Method.php';
    }
    
    if (!class_exists('Easyparcel_Shipping_API')) {
        // Include Easyparcel API
        include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
    }
    
    // Initialize API safely with proper error handling and timeouts
    $couriers = array(); // Default empty array
    if (class_exists('WC_Easyparcel_Shipping_Method') && class_exists('Easyparcel_Shipping_API')) {
        try {
            // Set timeout for API calls to prevent hanging
            $original_timeout = ini_get('default_socket_timeout');
            ini_set('default_socket_timeout', 10); // 10 second timeout
            
            // Try to initialize the API with timeout protection
            $init_result = Easyparcel_Shipping_API::init();
            
            if ($init_result !== false) {
                // Add additional timeout protection for getCourierList
                set_time_limit(15); // Maximum 15 seconds for this operation
                
                $api_couriers = Easyparcel_Shipping_API::getCourierList();
                
                // Validate API response
                if (is_array($api_couriers) && !empty($api_couriers)) {
                    $couriers = $api_couriers;
                } else {
                    wc_get_logger()->warning('EasyParcel: API returned empty courier list.', array('source' => 'easyparcel-shipping'));
                }
            } else {
                wc_get_logger()->error('EasyParcel: API initialization failed.', array('source' => 'easyparcel-shipping'));
            }
            
            // Restore original timeout
            ini_set('default_socket_timeout', $original_timeout);
            
        } catch (Exception $e) {
            // Log the specific error
            wc_get_logger()->error('EasyParcel API Exception: ' . $e->getMessage(), array(
                'source' => 'easyparcel-shipping',
                'trace' => $e->getTraceAsString()
            ));
            
            // Restore timeout in case of exception
            if (isset($original_timeout)) {
                ini_set('default_socket_timeout', $original_timeout);
            }
        } catch (Error $e) {
            // Catch fatal errors too
            wc_get_logger()->error('EasyParcel Fatal Error: ' . $e->getMessage(), array(
                'source' => 'easyparcel-shipping'
            ));
        }
    } else {
        wc_get_logger()->info('EasyParcel: Required classes not loaded. Skipping API initialization.', array('source' => 'easyparcel-shipping'));
    }

    // Fallback if no couriers available
    if (empty($couriers)) {
        $couriers = array((object)array(
            'courier_id' => '',
            'short_name' => 'Contact EasyParcel support'
        ));
    }
    
    $auto_fulfillment_setting = get_option('easyparcel_auto_fulfillment_settings');

?>
<div class="easyparcel">
    <div class="ep_breadcrumb light">
        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=shipping&section=easyparcel')); ?>">Easyparcel</a>
        <a href="">Auto Fulfillment Setting</a>
    </div>
    <h1>Auto Fulfillment Setting</h1>
    <form class="card" method="post">
        <?php wp_nonce_field('easyparcel_auto_fulfillment_settings'); ?>        
        <h3>Preset Courier</h3>
        <div class="form-field">
            <label>Auto Fulfill</label>
            <select id="ep_is_auto_fulfillment" name="ep_is_auto_fulfillment">
                <option value="no" <?php selected( esc_attr($auto_fulfillment_setting['ep_is_auto_fulfillment'] ?? 'no'), "no" ); ?>>No</option>
                <option value="yes" <?php selected( esc_attr($auto_fulfillment_setting['ep_is_auto_fulfillment'] ?? 'no'), "yes" ); ?>>Yes</option>
            </select>
        </div>
        <div class="form-field">
            <label>Courier</label>
            <select id="ep_courier" name="ep_courier">
                <?php foreach($couriers as $item) {?>
                <option value="<?php echo esc_attr($item->courier_id ?? '') ?>" <?php selected( esc_attr($auto_fulfillment_setting['ep_courier'] ?? ''), esc_attr($item->courier_id ?? '') ); ?>><?php echo esc_html($item->short_name ?? 'Unknown') ?></option>
                <?php }?>
            </select>
        </div>
        <div class="form-field">
            <label>Pickup/Dropoff</label>
            <select name="ep_pickup_dropoff" id="ep_pickup_dropoff" class="ep_pickup_dropoff">
                <option value="pickup" <?php selected( esc_attr($auto_fulfillment_setting['ep_pickup_dropoff'] ?? 'pickup'), "pickup" ); ?>>Courier Pickup from Door</option>
                <option value="dropoff" <?php selected( esc_attr($auto_fulfillment_setting['ep_pickup_dropoff'] ?? 'pickup'), "dropoff" ); ?>>Dropoff at Courier Dropoff Point</option>
            </select>
        </div>
        <div class="form-field dropoff_field hide_field">
            <label>Dropoff Point</label>
            <select name="ep_courier_dropoff" id="ep_courier_dropoff" class="ep_courier_dropoff"></select>
        </div>
        <div class="form-field dropoff_field hide_field">
            <label></label>
            <p id="ep_dropoff_detail"></p>
        </div>
        <div class="form-field">
            <label></label>
            <p><button class="woocommerce-save-button components-button is-primary">Save</button></p>
        </div>
    </form>
</div>