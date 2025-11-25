<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$courier_id = absint(wp_unslash($_REQUEST['courier_id']));

global $wpdb;
$courier = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id = %d",
        $courier_id
    )
);

if (!$courier) {
    wp_die(esc_html__('Courier does not exist!', 'easyparcel-shipping'));
}else{
    $courier = $courier[0];
}
// echo'<pre>';print_R($courier); echo'</pre>';

// decide what to use

$charges = self::chargesOption($courier->charges);
$freeshippingby = self::freeShippingByOption($courier->free_shipping_by);
if(!empty($courier->charges_value)){
    if (strpos($courier->charges_value, ':') !== false) {
        $temp = explode(':',$courier->charges_value);
        $addonCharges = self::addonChargesOption($temp[0]);
        $addonChargesValue = $temp[1];
    }else{
        $addonCharges = self::addonChargesOption();
        $addonChargesValue = $courier->charges_value;
    }
}else{
    $addonCharges = self::addonChargesOption();
    $addonChargesValue = 0;
}
//special checking for got dropoff things
if(!empty($courier->courier_dropoff_point)){
    if (!class_exists('EP_Shipping_Zones')) {
        include_once EASYPARCEL_DATASTORE_PATH .'ep_shipping_zones.php';
    }
    $zone = EP_Shipping_Zones::get_zone(absint($courier->zone_id));
    $courier_list = self::filteringRegionRate($zone,true);
    $dropoffpoint = self::checkDropoff($courier,$courier_list);
}

//
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
        'courier_id' => $courier_id,
        'ep_courier_setup_nonce' => wp_create_nonce('ep_courier_setup_nonce'),
        'type' => 'edit',
        'service_id' => $courier->service_id,
    )
);
wp_enqueue_script('easyparcel_admin_shipping_zone_courier_setup');

// Layout
?>

<h2>
	<span class="wc-shipping-zone-name"><?php esc_html_e( 'Courier Setting > ', 'easyparcel-shipping' ); ?><?php echo esc_html( $courier->courier_display_name ? $courier->courier_display_name : __( 'Courier', 'easyparcel-shipping' ) ); ?></span>
</h2>

<table class="form-table wc-shipping-zone-settings" id="courier-setting-table">
	<tbody>
		<?php if ( 0 !== $courier->id ) : ?>
			
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="courier_service">
						<?php esc_html_e( 'Courier Service', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Choose your preferred couriers to be displayed on the checkout page.', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					
					<input type="text" data-attribute="" name="" id="" value="<?php echo esc_attr($courier->courier_name) ?>" placeholder="" disabled>
    <img class="img-wrap" 
         id="courier_service_img" 
         src="<?php echo esc_url($courier->courier_logo); ?>" 
         alt="<?php echo esc_attr($courier->courier_name); ?> logo"
         style="display:inline-block; height:30px; width:auto;">
				</td>
			</tr>
			<?php if (!empty($courier->courier_dropoff_point)) : ?>
			<tr valign="top" class="" id="courier_dropoff_panel">
				<th scope="row" class="titledesc">
					<label for="charges">
						<?php esc_html_e( 'Courier Dropoff Point', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Choose the dropoff point you wish to dropoff your parcel. [optional]', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select data-attribute="dropoff_point" id="dropoff_point" name="dropoff_point" data-placeholder="<?php esc_attr_e( 'Select your dropoff point', 'easyparcel-shipping' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<?php
						foreach ( $dropoffpoint as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '" '.esc_attr( $v['selected'] ).'>' . esc_html( $v['text'] ) . '</option>';
						}
						?>
					</select>
				</td>
			</tr>
			<?php endif; ?>
			<tr valign="top" class="" id="courier_display_name_panel" style="display:none">
				<th scope="row" class="titledesc">
					<label for="courier_display_name">
						<?php esc_html_e( 'Courier Display Name', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Customise the courier display name shown to buyer in cart/payment page', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="courier_display_name" name="courier_display_name" id="courier_display_name" value="<?php echo esc_attr($courier->courier_display_name) ? esc_attr($courier->courier_display_name) : '' ?>" placeholder="">
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
							echo '<option value="' . esc_attr( $k ) . '" '.esc_attr( $v['selected'] ).'>' . esc_html( $v['text'] ) . '</option>';
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
					<input type="text" data-attribute="addon_1_charges_value_1" name="addon_1_charges_value_1" id="addon_1_charges_value_1" value="<?php echo esc_attr($addonChargesValue) ? esc_attr($addonChargesValue) : '' ?>" placeholder="">
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
						<?php
						foreach ( $addonCharges as $k => $v ) {
							echo '<option value="' . esc_attr( $k ) . '" '.esc_attr( $v['selected'] ).'>' . esc_html( $v['text'] ) . '</option>';
						}
						?>
					</select>
					<input type="text" data-attribute="addon_4_charges_value_2" name="addon_4_charges_value_2" id="addon_4_charges_value_2" value="<?php echo esc_attr($addonChargesValue) ? esc_attr($addonChargesValue) : '' ?>" placeholder="">
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label><input class="form-check-input" type="checkbox" value="" id="free_shipping" <?php echo ($courier->free_shipping==1) ? 'checked' : ''; ?>> <?php esc_html_e( 'Enable free shipping rule to apply', 'easyparcel-shipping' ); ?></input></label>
					
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
						<?php echo wc_help_tip( __( 'Provide free shipping if the order amount is same as or higher than the amount set.', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="free_shipping_value" name="free_shipping_value" id="free_shipping_value" value="<?php echo esc_attr($courier->free_shipping_value) ? esc_attr($courier->free_shipping_value) : ''?>" placeholder="">
				</td>
			</tr>

		<?php endif; ?>
	</tbody>
</table>

<p class="submit edit_courier">
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