<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!empty($_POST)) {
    // Verify nonce for security
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ep_courier_setup_nonce')) {
        wp_die('Security check failed. Please try again.');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_woocommerce')) {
        wp_die('You are not allowed to perform this action.');
    }
}

$zone_id = absint(wc_clean(wp_unslash($_GET['zone_id'] ?? '')));

if (!class_exists('EP_Shipping_Zones')) {
    include_once EASYPARCEL_DATASTORE_PATH . 'ep_shipping_zones.php';
}

$zone = EP_Shipping_Zones::get_zone($zone_id);

if (!$zone) {
    wp_die(esc_html__('Zone does not exist!', 'easyparcel-shipping'));
}

// decide what to use
$courier_list = array();
$courier_set = $zone->get_couriers();

$new_list = self::filteringRegionRate($zone,true);

if(!empty($courier_set)){   
    foreach($courier_set as $k => $v){
        if(strtolower($v['service_id']) == 'all'){
            $courier_list[] = array(
                'service_name' => 'All Couriers',
                'courier_id' => 'all',
                'courier_name' => 'All Couriers',
                'courier_logo' => '',
                'courier_info' => 'all',
                'service_id' => 'all',
                'sample_cost' => '0.00',
                'sample_cost_display' => '',
            );
        }else if(strtolower($v['service_id']) == 'cheapest'){
            $courier_list[] = array(
                'service_name' => 'Cheapest Courier',
                'courier_id' => 'cheapest',
                'courier_name' => 'Cheapest Courier',
                'courier_logo' => '',
                'courier_info' => 'cheapest',
                'service_id' => 'cheapest',
                'sample_cost' => '0.00',
                'sample_cost_display' => '',
            );
        }else if(isset($new_list[$v['service_id']])){
            unset($new_list[$v['service_id']]);
        }
    }
}
if(empty($courier_set)){
    $courier_list[] = array(
        'service_name' => 'All Couriers',
        'courier_id' => 'all',
        'courier_name' => 'All Couriers',
        'courier_logo' => '',
        'courier_info' => 'all',
        'service_id' => 'all',
        'sample_cost' => '0.00',
        'sample_cost_display' => '',
    );
    $courier_list[] = array(
        'service_name' => 'Cheapest Courier',
        'courier_id' => 'cheapest',
        'courier_name' => 'Cheapest Courier',
        'courier_logo' => '',
        'courier_info' => 'cheapest',
        'service_id' => 'cheapest',
        'sample_cost' => '0.00',
        'sample_cost_display' => '',
    );
}
$courier_list = array_merge($courier_list,$new_list);

$charges = self::chargesOption();
$freeshippingby = self::freeShippingByOption();
$addonCharges = self::addonChargesOption();

wp_register_script(
    'easyparcel_admin_shipping_zone_courier_setup', 
    EASYPARCEL_MODULE_SETUP_URL . 'admin_shipping_zone_courier_setup.js', 
    array('jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal'), 
    EASYPARCEL_VERSION, 
	array('in_footer' => true)
);

wp_localize_script(
    'easyparcel_admin_shipping_zone_courier_setup',
    'shippingZoneMethodsLocalizeScript',
    array(
        'zone_id' => $zone_id,
        'ep_courier_setup_nonce' => wp_create_nonce('ep_courier_setup_nonce'),
        'type' => 'insert',
    )
);

wp_enqueue_script('easyparcel_admin_shipping_zone_courier_setup');
?>


<h2>
	<span class="wc-shipping-zone-name"><?php esc_html_e( 'Courier Setting > ', 'easyparcel-shipping' ); ?><?php echo esc_html( $zone->get_zone_name() ? $zone->get_zone_name() : __( 'Zone', 'easyparcel-shipping' ) ); ?></span>
</h2>

<table class="form-table wc-shipping-zone-settings" id="courier-setting-table">
	<tbody>
		<?php if ( 0 !== $zone->get_id() ) : ?>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="zone_region">
						<?php esc_html_e( 'Zone Region', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'The zone regions you setup for', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<?php echo esc_attr($zone->get_formatted_location()) ?>
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="courier_service">
						<?php esc_html_e( 'Courier Service', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Choose your preferred couriers to be displayed on the checkout page.', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="courier_services" id="courier_services" name="courier_services" data-placeholder="<?php esc_attr_e( 'Select courier service', 'easyparcel-shipping' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<?php
						foreach ( $courier_list as $k => $v ) {
							$dropoff_option = (!empty($v['dropoff_point'])) ? 'yes' : 'no' ;
							echo '<option value="' . esc_attr( $k ).'"  data-service_name="' . esc_html($v['service_name']) . '" data-courier_id="' . esc_html($v['courier_id']) . '" data-courier_name="' . esc_html($v['courier_name']) .'" data-courier_logo="' . esc_html($v['courier_logo']) .'" data-courier_info="' . esc_html($v['courier_info']) .'" data-service_id="' . esc_html($v['service_id']) .'" data-sample_cost="' . esc_html($v['sample_cost']) .'" data-dropoff="' . esc_attr($dropoff_option) .'" >' . esc_html($v['service_name']).'</option>';
						}
						?>
					</select>
					<?php 
					foreach ( $courier_list as $k => $v ) {
						if(!empty($v['dropoff_point'])){
							echo '<div id="'.esc_html($v['courier_id']).'" style="display:none">';
							foreach($v['dropoff_point'] as $dpk => $dpv){
								echo '<option value="' . esc_attr( $dpv->point_id ) . '">' . esc_html( $dpv->point_name ) . '</option>';
							}
							echo'</div>';
						}
					}
					?>
					<img class="img-wrap" id="courier_service_img" width="auto !important" height="30px !important" src="" style="display:inline-block;">
				</td>
			</tr>
			<tr valign="top" class="" id="courier_dropoff_panel" style="display:none">
				<th scope="row" class="titledesc">
					<label for="charges">
						<?php esc_html_e( 'Courier Dropoff Point', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Choose the dropoff point you wish to dropoff your parcel. [optional]', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="dropoff_point" id="dropoff_point" name="dropoff_point" data-placeholder="<?php esc_attr_e( 'Select your dropoff point', 'easyparcel-shipping' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						
					</select>
				</td>
			</tr>
			<tr valign="top" class="" id="courier_display_name_panel" style="display:none">
				<th scope="row" class="titledesc">
					<label for="courier_display_name">
						<?php esc_html_e( 'Courier Display Name', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Customise the courier display name shown to buyer in cart/payment page', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="courier_display_name" name="courier_display_name" id="courier_display_name" value="" placeholder="">
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="charges">
						<?php esc_html_e( 'Shipping Rate Setting', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Choose your preferred shipping rate setting to be shown to your buyers on the checkout page.', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="charges_option" id="charges_option" name="charges_option" data-placeholder="<?php esc_attr_e( 'Select your charges', 'easyparcel-shipping' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<?php
						foreach ( $charges as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['text'] ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
			<tr valign="top" class="" id="addon_1" style="display:none"> <!-- addon option 1 -->
				<th scope="row" class="titledesc">
					<label for="addon_option_1">
						<?php esc_html_e( 'Add On Options', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Key in your flat rate for this courier service', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="addon_1_charges_value_1" name="addon_1_charges_value_1" id="addon_1_charges_value_1" value="" placeholder="">
				</td>
			</tr>
			<tr valign="top" class="" id="addon_4" style="display:none"> <!-- addon option 4 -->
				<th scope="row" class="titledesc">
					<label for="addon_option_4">
						<?php esc_html_e( 'Add On Options', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Choose your preferred type for add on option.<br>For add on by amount, key in any amount.<br>For add on by percentage, key in a number between 1 and 100.', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="addon_4_charges_value_1" id="addon_4_charges_value_1" name="addon_4_charges_value_1" data-placeholder="<?php esc_attr_e( 'Select your charges', 'easyparcel-shipping' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<option value="1">Add On By Amount (<?php echo esc_html(get_woocommerce_currency()); ?>)</option>
						<option value="2">Add On By Percentage (%)</option>
					</select>
					<input type="text" data-attribute="addon_4_charges_value_2" name="addon_4_charges_value_2" id="addon_4_charges_value_2" value="" placeholder="">
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label><input class="form-check-input" type="checkbox" value="" id="free_shipping"> <?php esc_html_e( 'Enable free shipping rule to apply', 'easyparcel-shipping' ); ?></input></label>
					
				</th>
				<td class="forminp">
				</td>
			</tr>
			<tr valign="top" class="free_shipping_tab" id="free_shipping_tab" style="display:none">
				<th scope="row" class="titledesc">
					<label for="free_shipping_by">
						<?php esc_html_e( 'Free shipping requires..', 'easyparcel-shipping' ); ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="free_shipping_by" id="free_shipping_by" name="free_shipping_by" data-placeholder="<?php esc_attr_e( 'Select your charges', 'easyparcel-shipping' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<?php
						foreach ( $freeshippingby as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '" '.esc_attr( $v['selected'] ).'>' . esc_html( $v['text'] ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
			<tr valign="top" class="free_shipping_tab" id="free_shipping_tab" style="display:none">
				<th scope="row" class="free_shipping_by_desc">
					<label for="free_shipping_by">
						<span id="free_shipping_text">Minimum Order Amount</span>
						<?php echo wc_help_tip( __('Provide free shipping if the order amount is same as or higher than the amount set.', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="free_shipping_value" name="free_shipping_value" id="free_shipping_value" value="" placeholder="">
				</td>
			</tr>

		<?php endif; ?>
	</tbody>
</table>

<p class="submit setup_courier">
	<button type="submit" name="submit" id="submit" class="button button-primary button-large wc-shipping-zone-method-save" value="<?php esc_attr_e( 'Save changes', 'easyparcel-shipping' ); ?>"><?php esc_html_e( 'Save changes', 'easyparcel-shipping' ); ?></button>
	<button type="submit" name="back" id="back" class="button button-primary button-large wc-shipping-zone-method-back" value="<?php esc_attr_e( 'Back', 'easyparcel-shipping' ); ?>"><?php esc_html_e( 'Back', 'easyparcel-shipping' ); ?></button>
</p>
<style>
table#courier-setting-table th {
	width:30%;
}
table#courier-setting-table td {
	width:70%;
}
</style>