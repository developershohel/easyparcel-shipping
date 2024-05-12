<?php
/**
 * Shipping zone admin
 *
 * @package WooCommerce\Admin\Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<tbody>
<?php if ( 0 !== $zone->get_id() ) :
	?>
    <tr class="form-group">
        <th scope="row" class="titledesc">
            <label for="courier_services">
				<?php esc_html_e( 'Courier Service', 'easyparcel-shipping' ); ?>
				<?php echo wc_help_tip( __( 'Choose your preferred couriers to be displayed on the checkout page.', 'easyparcel-shipping' ) );
				?>
            </label>
        </th>
        <td class="forminp">
            <select data-attribute="courier_services" id="courier_services" name="courier_services"
                    data-placeholder="<?php esc_attr_e( 'Select courier service', 'easyparcel-shipping' ); ?>"
                    class="wc-shipping-zone-region-select chosen_select">
				<?php
				foreach ( $courier_list as $k => $v ) {
					$dropoff_value = ! empty( $v['dropoff_point'] ) ? 'yes' : 'no';
					$services_type = $v['service_type'] ?? '';
					echo '<option value="' . esc_attr( $v['service_id'] ) . '"  data-service_name="' . esc_attr( $v['service_name'] ) . '" data-courier_id="' . esc_attr( $v['courier_id'] ) . '" data-courier_name="' . esc_attr( $v['courier_name'] ) . '" data-courier_logo="' . esc_url( $v['courier_logo'] ) . '" data-courier_info="' . esc_attr( $v['delivery'] ) . '" data-service_id="' . esc_attr( $v['service_id'] ) . '" data-sample_cost="' . esc_attr( $v['shipment_price'] ) . '" data-dropoff="' . esc_attr( $dropoff_value ) . '" data-services_type="' . esc_attr( $services_type ) . '" data-price = "' . esc_attr( $v['price'] ) . '" data-addon_price="' . esc_attr( $v['addon_price'] ) . '" data-shipment_price="' . esc_attr( $v['shipment_price'] ) . '">' . esc_html( $v['service_name'] ) . '</option>';
				}
				?>
            </select>
			<?php
			foreach ( $courier_list as $k => $v ) {
				if ( ! empty( $v['dropoff_point'] ) ) {
					echo '<div id="' . esc_attr( $v['service_id'] ) . '" style="display:none">';
					foreach ( $v['dropoff_point'] as $dpk => $dpv ) {
						echo '<option value="' . esc_attr( $dpv['point_id'] ) . '" data-dropoff_name="' . esc_attr( $dpv['point_name'] ) . '">' . esc_html( $dpv['point_name'] ) . '</option>';
					}
					echo '</div>';
				}
			}
			?>
            <img class="img-wrap" id="courier_service_img" width="auto !important" height="30px !important"
                 src="" style="display:inline-block;">
        </td>
    </tr>
    <tr id="courier_dropoff_panel" style="display:none">
        <th scope="row" class="titledesc">
            <label for="dropoff_point">
				<?php esc_html_e( 'Courier Dropoff Point', 'easyparcel-shipping' ); ?>
				<?php echo wc_help_tip( __( 'Choose the dropoff point you wish to dropoff your parcel. [optional]', 'easyparcel-shipping' ) );
				?>
            </label>
        </th>
        <td class="forminp">
            <select data-attribute="dropoff_point" id="dropoff_point" name="dropoff_point"
                    data-placeholder="<?php esc_attr_e( 'Select your dropoff point', 'easyparcel-shipping' ); ?>"
                    class="wc-shipping-zone-region-select chosen_select">
            </select>
        </td>
    </tr>
    <tr id="courier_display_name_panel" style="display:none">
        <th scope="row" class="titledesc">
            <label for="courier_display_name">
				<?php esc_html_e( 'Courier Display Name', 'easyparcel-shipping' ); ?>
				<?php echo wc_help_tip( __( 'Customise the courier display name shown to buyer in cart/payment page', 'easyparcel-shipping' ) ); 
				?>
            </label>
        </th>
        <td class="forminp">
            <input type="text" data-attribute="courier_display_name" name="courier_display_name"
                   id="courier_display_name" value="" placeholder="">
        </td>
    </tr>
    <tr id="courier_delivery_panel" style="display:none">
        <th scope="row" class="titledesc">
            <label for="courier_delivery_days">
				<?php esc_html_e( 'Courier Delivery Days', 'easyparcel-shipping' ); ?>
            </label>
        </th>
        <td class="forminp">
            <input type="text" data-attribute="courier_info_panel" name="courier_delivery_days"
                   id="courier_delivery_days" value="">
        </td>
    </tr>
    <tr>
        <th scope="row" class="titledesc">
            <label for="charges">
				<?php esc_html_e( 'Shipping Rate Setting', 'easyparcel-shipping' ); ?>
				<?php echo wc_help_tip( __( 'Choose your preferred shipping rate setting to be shown to your buyers on the checkout page.', 'easyparcel-shipping' ) ); 
				?>
            </label>
        </th>
        <td class="forminp">
            <select data-attribute="charges_option" id="charges" name="charges_option"
                    data-placeholder="<?php esc_attr_e( 'Select your charges', 'easyparcel-shipping' ); ?>"
                    class="wc-shipping-zone-region-select chosen_select">
				<?php
				foreach ( $charges as $k => $v ) {
					echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['text'] ) . '</option>';
				}
				?>
            </select>
        </td>
    </tr>
    <tr id="shipping_rate_option_panel" style="display:none">
        <th scope="row" class="titledesc">
            <label for="shipping_rate_option">
				<?php esc_html_e( 'Add On Options', 'easyparcel-shipping' ); ?>
				<?php echo wc_help_tip( __( 'Choose your preferred type for add on option.<br>For add on by amount, key in any amount.<br>For add on by percentage, key in a number between 1 and 100.', 'easyparcel-shipping' ) );
				?>
            </label>
        </th>
        <td class="forminp">
            <select data-attribute="shipping_rate_option" id="shipping_rate_option"
                    name="shipping_rate_option"
                    data-placeholder="<?php esc_attr_e( 'Select your charges', 'easyparcel-shipping' ); ?>"
                    class="wc-shipping-zone-region-select chosen_select">
                <option value="1" selected>Add On By Amount (<?php echo esc_attr( get_woocommerce_currency() ); ?>)
                </option>
                <option value="2">Add On By Percentage (%)</option>
            </select>
        </td>
    </tr>
    <tr id="charges_shipping_rate_panel" style="display:none">
        <th scope="row" class="charges-value">
            <label for="charges_value">
				<?php esc_html_e( 'Shipping Rate', 'easyparcel-shipping' ); ?>
            </label>
        </th>
        <td class="charges-value">
			<?php
			echo '<input type="text" data-attribute="charges-value" name="charges_value" id="charges_value" value="">';
			?>
        </td>
    </tr>
    <tr>
        <th scope="row" class="titledesc">Free Shipping Options</th>
        <th scope="row" class="titledesc">
            <label><input class="form-check-input" type="checkbox"
                          id="free_shipping"> <?php esc_html_e( 'Enable free shipping rule to apply', 'easyparcel-shipping' ); ?>
            </label>
        </th>
    </tr>
    <tr class="free_shipping_tab"
        id="free_shipping_tab" style="display:none">
        <th scope="row" class="titledesc">
            <label for="free_shipping_by">
				<?php esc_html_e( 'Free shipping method', 'easyparcel-shipping' ); ?>
            </label>
        </th>
        <td class="forminp">
            <select data-attribute="free_shipping_by" id="free_shipping_by" name="free_shipping_by"
                    data-placeholder="<?php esc_attr_e( 'Select your charges', 'easyparcel-shipping' ); ?>"
                    class="wc-shipping-zone-region-select chosen_select">
				<?php
				foreach ( $freeshippingby as $k => $v ) {
					echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['text'] ) . '</option>';
				}
				?>
            </select>
        </td>
    </tr>
    <tr class="free_shipping_tab_value_panel"
        id="free_shipping_tab_value_panel" style="display:none">
        <th scope="row" class="free_shipping_by_desc">
            <label for="free_shipping_by">
                <span id="free_shipping_text">Minimum Order Amount</span>
				<?php echo wc_help_tip( __( 'Provide free shipping if the order amount is same as or higher than the amount set.', 'easyparcel-shipping' ) );
				?>
            </label>
        </th>
        <td class="forminp">
            <label><input type="text" data-attribute="free_shipping_value" name="free_shipping_value"
                          id="free_shipping_value" value=""></label>
        </td>
    </tr>
<?php
endif; ?>
<tr style="display: none">
    <td>
        <input type="hidden" id="courier_data" name="courier_data">
        <input type="hidden" id="easyparcel_ajax_save_courier_services" name="easyparcel_ajax_save_courier_services"
               value="<?php echo esc_attr( wp_create_nonce( 'easyparcel_ajax_save_courier_services' ) ); ?>">
    </td>
</tr>
</tbody>