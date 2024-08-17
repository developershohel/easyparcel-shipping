<?php
/**
 * Shipping zone admin
 *
 * @package WooCommerce\Admin\Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$courier_id = filter_input(INPUT_GET, 'courier_id', FILTER_SANITIZE_NUMBER_INT);
$courier_id = absint( $courier_id) ?? 0;
$zone_id = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT zone_id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id =%d",
		$courier_id
	)
);

$instance_id = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT instance_id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id =%d AND zone_id =%d",
		$courier_id,
		$zone_id
	)
);
?>

<h2>
    <span class="wc-shipping-zone-name"><?php esc_html_e( 'Courier Setting > ', 'easyparcel-shipping' ); ?><?php echo esc_html( $courier->courier_display_name ? $courier->courier_display_name : __( 'Courier', 'easyparcel-shipping' ) ); ?></span>
</h2>

<table class="form-table wc-shipping-zone-settings" id="courier-setting-table">
    <tbody>
	<?php if ( 0 !== $zone_id ) :
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
						if ( isset( $couriers[0]['courier_id'] ) && $v['courier_id'] ) {
							$service_id = $couriers[0]['courier_id'];
							if ( $service_id == $v['courier_id'] ) {
								echo '<option class="selected" value="' . esc_attr( $v['service_id'] ) . '"  data-service_name="' . esc_attr( $v['service_name'] ) . '" data-courier_id="' . esc_attr( $v['courier_id'] ) . '" data-courier_name="' . esc_attr( $v['courier_name'] ) . '" data-courier_logo="' . esc_attr( $v['courier_logo'] ) . '" data-courier_info="' . esc_attr( $v['delivery'] ) . '" data-service_id="' . esc_attr( $v['service_id'] ) . '" data-sample_cost="' . esc_attr( $v['shipment_price'] ) . '" data-dropoff="' . esc_attr( $dropoff_value ) . '" data-services_type="' . esc_attr( $services_type ) . '" data-price = "' . esc_attr( $v['price'] ) . '" data-addon_price="' . esc_attr( $v['addon_price'] ) . '" data-shipment_price="' . esc_attr( $v['shipment_price'] ) . '" selected>' . esc_html( $v['service_name'] ) . '</option>';
							} else {
								echo '<option value="' . esc_attr( $v['service_id'] ) . '"  data-service_name="' . esc_attr( $v['service_name'] ) . '" data-courier_id="' . esc_attr( $v['courier_id'] ) . '" data-courier_name="' . esc_attr( $v['courier_name'] ) . '" data-courier_logo="' . esc_url( $v['courier_logo'] ) . '" data-courier_info="' . esc_attr( $v['delivery'] ) . '" data-service_id="' . esc_attr( $v['service_id'] ) . '" data-sample_cost="' . esc_attr( $v['shipment_price'] ) . '" data-dropoff="' . esc_attr( $dropoff_value ) . '" data-services_type="' . esc_attr( $services_type ) . '" data-price = "' . esc_attr( $v['price'] ) . '" data-addon_price="' . esc_attr( $v['addon_price'] ) . '" data-shipment_price="' . esc_attr( $v['shipment_price'] ) . '">' . esc_html( $v['service_name'] ) . '</option>';
							}
						} else {
							if ( $v['service_id'] == 'all' ) {
								echo '<option class="selected" value="' . esc_attr( $v['service_id'] ) . '"  data-service_name="' . esc_html( $v['service_name'] ) . '" data-courier_id="' . esc_html( $v['courier_id'] ) . '" data-courier_name="' . esc_html( $v['courier_name'] ) . '" data-courier_logo="' . esc_html( $v['courier_logo'] ) . '" data-courier_info="' . esc_html( $v['delivery'] ) . '" data-service_id="' . esc_html( $v['service_id'] ) . '" data-sample_cost="' . esc_html( $v['shipment_price'] ) . '" data-dropoff="' . esc_attr( $dropoff_value ) . '" data-services_type="' . esc_attr( $services_type ) . '" data-price = "' . esc_attr( $v['price'] ) . '" data-addon_price="' . esc_attr( $v['addon_price'] ) . '" data-shipment_price="' . esc_attr( $v['shipment_price'] ) . '" selected>' . esc_html( $v['service_name'] ) . '</option>';
							} else {
								echo '<option value="' . esc_attr( $v['service_id'] ) . '"  data-service_name="' . esc_html( $v['service_name'] ) . '" data-courier_id="' . esc_html( $v['courier_id'] ) . '" data-courier_name="' . esc_html( $v['courier_name'] ) . '" data-courier_logo="' . esc_html( $v['courier_logo'] ) . '" data-courier_info="' . esc_html( $v['delivery'] ) . '" data-service_id="' . esc_html( $v['service_id'] ) . '" data-sample_cost="' . esc_html( $v['shipment_price'] ) . '" data-dropoff="' . esc_attr( $dropoff_value ) . '" data-services_type="' . esc_attr( $services_type ) . '" data-price = "' . esc_attr( $v['price'] ) . '" data-addon_price="' . esc_attr( $v['addon_price'] ) . '" data-shipment_price="' . esc_attr( $v['shipment_price'] ) . '">' . esc_html( $v['service_name'] ) . '</option>';
							}
						}
					}
					?>
                </select>
				<?php
				foreach ( $courier_list as $k => $v ) {
					if ( ! empty( $v['dropoff_point'] ) ) {
						echo '<div id="' . esc_html( $v['service_id'] ) . '" style="display:none">';
						foreach ( $v['dropoff_point'] as $dpk => $dpv ) {
							if ( $couriers[0]['courier_dropoff_point'] == $dpv['point_id'] ) {
								echo '<option class="selected" value="' . esc_attr( $dpv['point_id'] ) . '" data-dropoff_name="' . esc_attr( $dpv['point_name'] ) . '" selected>' . esc_html( $dpv['point_name'] ) . '</option>';
							} else {
								echo '<option value="' . esc_attr( $dpv['point_id'] ) . '" data-dropoff_name="' . esc_attr( $dpv['point_name'] ) . '" >' . esc_html( $dpv['point_name'] ) . '</option>';
							}
						}
						echo '</div>';
					}
				}
				?>
                <img class="img-wrap" id="courier_service_img" width="auto !important" height="30px !important"
                     src="<?php if ( isset( $couriers[0]['courier_logo'] ) )
					     echo esc_url( $couriers[0]['courier_logo'] ) ?>" style="display:inline-block;">
            </td>
        </tr>
        <tr id="courier_dropoff_panel" <?php if ( ! isset( $couriers[0]['courier_dropoff_name'] ) ) {
			echo 'style="display:none"';
		} ?>>
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
					<?php
					if ( isset( $couriers[0]['courier_dropoff_point'] ) ) {
						foreach ( $courier_list as $k => $v ) {
							if ( $v['service_id'] == $couriers[0]['service_id'] ) {
								if ( isset( $v['dropoff_point'] ) ) {
									foreach ( $v['dropoff_point'] as $dpk => $dpv ) {
										if ( $dpv['point_id'] == $couriers[0]['courier_dropoff_point'] ) {
											echo '<option class="selected" value="' . esc_attr( $dpv['point_id'] ) . '" data-dropoff_name="' . esc_attr( $dpv['point_name'] ) . '" selected>' . esc_html( $dpv['point_name'] ) . '</option>';
										} else {
											echo '<option value="' . esc_attr( $dpv['point_id'] ) . '" data-dropoff_name="' . esc_attr( $dpv['point_name'] ) . '">' . esc_html( $dpv['point_name'] ) . '</option>';
										}
									}
								}
							}
						}
					}
					?>
                </select>
            </td>
        </tr>
        <tr id="courier_display_name_panel" <?php if ( ! isset( $couriers[0]['courier_display_name'] ) ) {
			echo 'style="display:none"';
		} ?>>
            <th scope="row" class="titledesc">
                <label for="courier_display_name">
					<?php esc_html_e( 'Courier Display Name', 'easyparcel-shipping' ); ?>
					<?php echo wc_help_tip( __( 'Customise the courier display name shown to buyer in cart/payment page', 'easyparcel-shipping' ) ); 
					?>
                </label>
            </th>
            <td class="forminp">
                <input type="text" data-attribute="courier_display_name" name="courier_display_name"
                       id="courier_display_name" value="<?php if ( isset( $couriers[0]['courier_display_name'] ) ) {
					echo esc_attr( $couriers[0]['courier_display_name'] );
				} ?>" placeholder="">
            </td>
        </tr>
        <tr id="courier_delivery_panel" <?php if ( ! isset( $couriers[0]['courier_info'] ) ) {
			echo 'style="display:none"';
		} ?>>
            <th scope="row" class="titledesc">
                <label for="courier_delivery_days">
					<?php esc_html_e( 'Courier Delivery Days', 'easyparcel-shipping' ); ?>
                </label>
            </th>
            <td class="forminp">
                <input type="text" data-attribute="courier_info_panel" name="courier_delivery_days"
                       id="courier_delivery_days" value="<?php if ( isset( $couriers[0]['courier_info'] ) ) {
					echo esc_attr( $couriers[0]['courier_info'] );
				} ?>">
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
						if ( isset( $couriers[0]['charges'] ) ) {
							if ( $couriers[0]['charges'] == $k ) {
								echo '<option class="selected" value="' . esc_attr( $k ) . '" selected="selected">' . esc_html( $v['text'] ) . '</option>';
							} else {
								echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['text'] ) . '</option>';
							}
						} else {
							echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['text'] ) . '</option>';
						}
					}
					?>
                </select>
            </td>
        </tr>
        <tr id="shipping_rate_option_panel" <?php if ( $couriers[0]['charges'] == 2 || $couriers[0]['charges'] == 1 || $couriers[0]['charges'] == 0 ) {
			echo 'style="display:none"';
		} ?>>
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
					<?php
					if ( $couriers[0]['charges_value'] != false ) {
						$charges_value = explode( ':', $couriers[0]['charges_value'] );
						if ( $charges_value[0] == 1 ) {
							?>
                            <option value="1" selected>Add On By Amount
                                (<?php echo esc_html( get_woocommerce_currency() ); ?>)
                            </option>
                            <option value="2">Add On By Percentage (%)</option>
						<?php } else if ( $charges_value[0] == 2 ) { ?>
                            <option value="1">Add On By Amount (<?php echo esc_html( get_woocommerce_currency() ); ?>)
                            </option>
                            <option value="2" selected>Add On By Percentage (%)</option>
						<?php } else {
							?>
                            <option value="1">Add On By Amount (<?php echo esc_html( get_woocommerce_currency() ); ?>)
                            </option>
                            <option value="2">Add On By Percentage (%)</option>
						<?php }
					} else {
						?>
                        <option value="1" selected>Add On By Amount
                            (<?php echo esc_html( get_woocommerce_currency() ); ?>)
                        </option>
                        <option value="2">Add On By Percentage (%)</option>
						<?php
					}
					?>
                </select>
            </td>
        </tr>
        <tr id="charges_shipping_rate_panel" <?php if ( $couriers[0]['charges'] == 2 || $couriers[0]['charges'] == 0 ) {
			echo 'style="display:none"';
		} ?>>
            <th scope="row" class="charges-value">
                <label for="charges_value">
					<?php esc_html_e( 'Shipping Rate', 'easyparcel-shipping' ); ?>
                </label>
            </th>
            <td class="charges-value">
				<?php
				if ( $couriers[0]['charges_value'] != false ) {
					$charges_value = explode( ':', $couriers[0]['charges_value'] );
					echo '<input type="text" data-attribute="charges-value" name="charges_value" id="charges_value" value="' . esc_attr( $charges_value[1] ) . '">';
				} else {
					echo '<input type="text" data-attribute="charges-value" name="charges_value" id="charges_value" value="">';
				}
				?>
            </td>
        </tr>
        <tr>
            <th scope="row" class="titledesc">Free Shipping Options</th>
            <th scope="row" class="titledesc">
                <label><input class="form-check-input" type="checkbox"
                              id="free_shipping" <?php if ( $couriers[0]['free_shipping'] == true )
						echo esc_attr( 'checked="checked"' ) ?>> <?php esc_html_e( 'Enable free shipping rule to apply', 'easyparcel-shipping' ); ?>
                </label>
            </th>
        </tr>
        <tr class="free_shipping_tab"
            id="free_shipping_tab" <?php if ( $couriers[0]['free_shipping'] == false ) {
			echo 'style="display:none"';
		} ?>>
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
						if ( $couriers[0]['free_shipping_by'] != false ) {
							if ( $couriers[0]['free_shipping_by'] == $k ) {
								echo '<option value="' . esc_attr( $k ) . '" selected="selected">' . esc_html( $v['text'] ) . '</option>';
							} else {
								echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['text'] ) . '</option>';
							}
						} else {
							echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $v['text'] ) . '</option>';
						}
					}
					?>
                </select>
            </td>
        </tr>
        <tr class="free_shipping_tab_value_panel"
            id="free_shipping_tab_value_panel" <?php if ( $couriers[0]['free_shipping'] == false ) {
			echo 'style="display:none"';
		} ?>>
            <th scope="row" class="free_shipping_by_desc">
                <label for="free_shipping_by">
                    <span id="free_shipping_text">Minimum Order Amount</span>
					<?php echo wc_help_tip( __( 'Provide free shipping if the order amount is same as or higher than the amount set.', 'easyparcel-shipping' ) ); 
					?>
                </label>
            </th>
            <td class="forminp">
                <label><input type="text" data-attribute="free_shipping_value" name="free_shipping_value"
                              id="free_shipping_value" value="<?php if ( $couriers[0]['free_shipping_value'] != false )
						echo esc_attr( $couriers[0]['free_shipping_value'] ) ?>"></label>
            </td>
        </tr>
	<?php
	endif; ?>
    </tbody>
</table>
<p class="submit edit_courier">
    <button type="submit" name="submit" id="submit"
            class="button button-primary button-large wc-shipping-zone-method-save"
            value="<?php esc_attr_e( 'Save changes', 'easyparcel-shipping' ); ?>"><?php esc_html_e( 'Save changes', 'easyparcel-shipping' ); ?></button>
    <a href="<?php echo esc_url( admin_url( "admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&zone_id=$zone_id" ) ) ?>"
       class="button button-primary button-large wc-shipping-zone-method-back"><?php esc_html_e( 'Back', 'easyparcel-shipping' ); ?></a>
</p>
<p class="form-error"></p>
<input type="hidden" id="zone_id" name="zone_id" value="<?php echo esc_attr( $zone_id ) ?>">
<input type="hidden" id="instance_id" name="instance_id" value="<?php echo esc_attr( $instance_id ) ?>">
<input type="hidden" id="courier_id" name="courier_id" value="<?php echo esc_attr( $courier_id ) ?>">
<input type="hidden" id="redirect_url" name="redirect_url"
       value="<?php echo esc_url( admin_url( "admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&zone_id=$zone_id" ) ) ?>">
<input type="hidden" id="easyparcel_ajax_save_courier_services" name="easyparcel_ajax_save_courier_services"
       value="<?php echo esc_attr( wp_create_nonce( 'easyparcel_ajax_save_courier_services' ) ); ?>">