<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!class_exists('Easyparcel_Shipping_API')) {
    // Include Easyparcel API
    include_once EASYPARCEL_SERVICE_PATH . 'easyparcel_api.php';
}

Easyparcel_Shipping_API::init();
$couriers_list = Easyparcel_Shipping_API::getCourierList();
$courier_option = array();
$couriers = array();
foreach($couriers_list as $item) {
    $courier_option[$item->courier_id] = $item->short_name;
    $couriers[$item->courier_id] = $item;
}

$zone = WC_Shipping_Zones::get_zone_by('instance_id', $this->instance_id);

// echo "<pre>";
// print_r($zone->get_id()); echo "\n";
// print_r($zone->get_zone_locations()); echo "\n";
// print_r($this->instance_id);echo "\n";
// print_r($this->settings);echo "\n";
// echo "</pre>";

$zone_setting = $this->fetch_easyparcel_zones_setting($zone->get_id());
if(empty($zone_setting)) $zone_setting =  $this->get_default_courier_shipping_zone_setting();

// echo "<pre>zone_setting\n";
// print_r($zone_setting);
// echo "</pre>";

if (!$zone) {
    wp_die(esc_html__('Zone does not exist!', 'easyparcel-shipping'));
}

wp_register_script(
    'easyparcel_admin_shipping_zone_setup', 
    EASYPARCEL_MODULE_SETUP_URL . 'admin_shipping_zone_setup.js', 
    array('jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal'), 
    '1.0.0'
);
wp_localize_script(
    'easyparcel_admin_shipping_zone_setup',
    'shippingZoneSetupLocalizeScript',
    array(
        'easyparcel_admin_shipping_zone_setup_nonce' => wp_create_nonce('easyparcel_admin_shipping_zone_setup_nonce'),
        'zone_id' => $zone->get_id(),
        'instance_id' => $this->instance_id,
        'zone_setting' => $zone_setting,
        'couriers' => $couriers,
    )
);
wp_enqueue_script('easyparcel_admin_shipping_zone_setup');
?>

<?php if ( 0 !== $zone->get_id() ){ ?>

    <table class="form-table" id="shipping-zone-setting-panel-1">
        <tbody>
            <tr class="">
                <th scope="row" class="titledesc">
                    <label for="zone_region">
                    <?php
                        esc_html_e( 'Zone Region', 'easyparcel-shipping' );
                        echo wp_kses_post(
                            wc_help_tip(
                                esc_attr__( 'The zone regions you set up for', 'easyparcel-shipping' )
                            )
                        );
                    ?>
                    </label>   
                </th>
                <td class="forminp">
                    <?php echo esc_attr($zone->get_formatted_location()) ?>
                </td>
            </tr>
            <tr class="">
                <th scope="row" class="titledesc">
                    <label for="courier_service">
                    <?php
                        esc_html_e( 'Setting Type', 'easyparcel-shipping' );
                        echo wp_kses_post(
                            wc_help_tip(
                                esc_attr__( 'Choose your preferred setup.', 'easyparcel-shipping' )
                            )
                        );
                    ?>
                    </label>
                </th>
                <td class="forminp">
                    <?php woocommerce_wp_select( array(
                        'id'        => 'setting_type',
                        'class'     => 'ep-form-field',
                        'value'     => esc_attr($zone_setting['setting_type']),
                        'options'   => array(
                                        'all' => __('Show All Couriers', 'easyparcel-shipping'),
                                        'cheapest' => __('Show Only Cheapest Courier', 'easyparcel-shipping'),
                                        'couriers' => __('Show Selected Courier', 'easyparcel-shipping'),
                                    ),
                    ));?>
                </td>
            </tr>
        </tbody>
    </table>
    <h3 class="wc-settings-sub-title " id="woocommerce_easyparcel_setting_panel">
        <?php esc_html_e( 'Setting', 'easyparcel-shipping' ); ?>
    </h3>
    <table class="form-table" id="shipping-zone-setting-panel-2">
        <tbody>
        </tbody>
    </table>

<?php } ?>

<p class="submit setup_courier">
    <a class="button button-primary button-large wc-shipping-zone-method-save" onclick="onSaveChange()"><?php esc_html_e( 'Save changes', 'easyparcel-shipping' ); ?></a>
    <a class="button button-primary button-large wc-shipping-zone-method-back" href="<?php echo esc_url(admin_url( 'admin.php?page=wc-settings&tab=shipping&zone_id=' . $zone->get_id() )); ?>"><?php esc_attr_e( 'Back', 'easyparcel-shipping' ); ?></a>
</p>



<div class="easyparcel-modal" id="easyparcelModal">
    <div class="modal-content">
        <span class="close-modal" onclick="CloseEPModal()">&times;</span>
        <div class="modal-body"></div>
    </div>
</div>

<template type="text/template"  id="ep-single-setting">
    <tr class="courier ep_courier">
        <th scope="row" class="titledesc">
            <label for="ep_courier"><?php esc_html_e( 'Courier', 'easyparcel-shipping' ); ?></label>
        </th>
        <td class="forminp">
            <?php woocommerce_wp_select( array(
                    'id'        => 'ep_courier',
                    'class'     => 'ep-form-field',
                    'options'   => $courier_option,
                    'custom_attributes' => array(
                        'onchange' => 'changeCourier(this)'
                    )
            )); ?>
        </td>
    </tr>
    <tr class="courier_name" style="display: none;">
        <th scope="row" class="titledesc">
            <label for="courier_name"><?php esc_html_e( 'Courier Name (Internal)', 'easyparcel-shipping' ); ?></label>
        </th>
        <td class="forminp">
            <?php woocommerce_wp_text_input( array(
                'id'        => 'courier_name',
                'class'     => 'ep-form-field',
                'readonly'  => true,
                'custom_attributes' => array('readonly' => 'readonly')
            ) );?>
        </td>
    </tr>
    <tr class="label">
        <th scope="row" class="titledesc">
            <label for="label"><?php esc_html_e( 'Custom Display Name', 'easyparcel-shipping' ); ?></label>
            <p class="description"><?php esc_html_e( 'Leave blank to use default service names', 'easyparcel-shipping' ); ?></p>
        </th>
        <td class="forminp">
            <?php woocommerce_wp_text_input( array(
				'id'        => 'label',
                'class'     => 'ep-form-field',
                'placeholder' => 'Default'
			) );?>
        </td>
    </tr>
    <tr class="charges_type_row">
        <th scope="row" class="titledesc">
            <label for="charges_type"><?php esc_html_e( 'Shipping Rate Setting', 'easyparcel-shipping' ); ?></label>
        </th>
        <td class="forminp">
            <?php woocommerce_wp_select( array(
                'id'        => 'charges_type',
                'class'     => 'ep-form-field',
                'options'   => array(
                                'member_rate' => __('EasyParcel Member Rate', 'easyparcel-shipping'),
                                'member_rate_addon' => __('Add On EasyParcel Member Rate', 'easyparcel-shipping'),
                                'flat_rate' => __('Flat Rate', 'easyparcel-shipping'),
                            ),
                'custom_attributes' => array(
                    'onchange' => 'changeChargesType(this)'
                )
            ));?>
        </td>
    </tr>
    <tr class="charges_value_row">
        <th scope="row" class="titledesc">
            <label for="charges_value"><?php esc_html_e( 'Amount', 'easyparcel-shipping' ); ?></label>
        </th>
        <td class="forminp">
            <?php woocommerce_wp_text_input( array(
				'id'        => 'charges_value',
                'class'     => 'ep-form-field',
                'data_type' => 'price',
			) );?>
        </td>
    </tr>
    <tr class="charges_addon_type_row" >
        <th scope="row" class="titledesc">
            <label for="charges_addon_type"><?php esc_html_e( 'Charges Addon Type', 'easyparcel-shipping' ); ?></label>
        </th>
        <td class="forminp">
            <?php woocommerce_wp_select( array(
                'id'        => 'charges_addon_type',
                'class'     => 'ep-form-field',
                'options'   => array(
                                'amount' => __('Addon by Amount', 'easyparcel-shipping'),
                                'percent' => __('Addon by Percent', 'easyparcel-shipping'),
                            ),
            ));?>
        </td>
    </tr>
    <tr class="charges_addon_value_row">
        <th scope="row" class="titledesc">
            <label for="charges_addon_value"><?php esc_html_e( 'Amount', 'easyparcel-shipping' ); ?></label>
        </th>
        <td class="forminp">
            <?php woocommerce_wp_text_input( array(
				'id'        => 'charges_addon_value',
                'class'     => 'ep-form-field',
                'data_type' => 'price',
			) );?>
        </td>
    </tr>
    <tr class="is_free_shipping_row">
        <th scope="row" class="titledesc">
            <label for="is_free_shipping"><?php esc_html_e( 'Enable free shipping rule to apply', 'easyparcel-shipping' ); ?></label>
        </th>
        <td class="forminp">
            <input type="checkbox" id="is_free_shipping" name="is_free_shipping" onchange="changeFreeShipping(this)" value="1"/>
        </td>
    </tr>
    <tr class="free_shipping_type_row">
        <th scope="row" class="titledesc">
            <label for="free_shipping_type"><?php esc_html_e( 'Free shipping requires..', 'easyparcel-shipping' ); ?></label>
        </th>
        <td class="forminp">
            <?php woocommerce_wp_select( array(
                'id'        => 'free_shipping_type',
                'class'     => 'ep-form-field',
                'options'   => array(
                                'amount' => __('A minimum order amount', 'easyparcel-shipping'),
                                'quantity' => __('A minimum order quantity', 'easyparcel-shipping'),
                            ),
            ));?>
        </td>
    </tr>
    <tr class="free_shipping_value_row">
        <th scope="row" class="titledesc">
            <label for="free_shipping_value"><?php esc_html_e( 'Minimum Order Amount', 'easyparcel-shipping' ); ?></label>
        </th>
        <td class="forminp">
            <?php woocommerce_wp_text_input( array(
				'id'        => 'free_shipping_value',
                'class'     => 'ep-form-field',
                'data_type' => 'price',
			) );?>
        </td>
    </tr>
</template>

<template type="text/template"  id="ep-courier-setting">
    <tr class="">
        <td class="forminp" rowspan="2">
            <table class="widefat wc-shipping-classes">
                <thead>
                    <tr>
                        <th width="1%">&nbsp;</th>
                        <th width="59%">Courier</th>
                        <th width="40%">Charges Type</th>
                    </tr>
                </thead>
                <tbody class="ui-sortable row_content">
                    <tr class="tmpl_row" data-id="" style="display: none;">
                        <td width="1%" class="wc-shipping-zone-sort sortable-handle">&nbsp;</td>
                        <td width="59%" class="">
                            <a class="btn courier_display" onclick="OpenEPModal(this)">mamamiya </a>
                            <div class="row-actions">
                                <a class="btn" onclick="OpenEPModal(this)">Edit</a> | <a class="btn" onclick="DeleteCourier(this)">Delete</a>
                            </div>
                        </td>
                        <td width="40%" class="">
                            <p class="rate_method"></p>
                            <input type="hidden" class="ep_row_data" value="">
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right;">
                            <a class="button" onclick="OpenEPModal()"><?php esc_html_e( 'Add Courier', 'easyparcel-shipping' ); ?></a>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </td>
    </tr>
</template>
<style>
table#courier-setting-table th {
	width:30%;
}
table#courier-setting-table td {
	width:70%;
}
</style>