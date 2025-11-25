<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$zone_id = wc_clean(wp_unslash($_REQUEST['zone_id']));

if ('new' === $zone_id) {
    if (!class_exists('EP_Shipping_Zone')) {
        include_once EASYPARCEL_DATASTORE_PATH . 'ep_shipping_zone.php';
    }
    $zone = new EP_Shipping_Zone();
} else {
    if (!class_exists('EP_Shipping_Zones')) {
        include_once EASYPARCEL_DATASTORE_PATH . 'ep_shipping_zones.php';
    }
    $zone = EP_Shipping_Zones::get_zone(absint($zone_id));
}

if (!$zone) {
    wp_die(esc_html__('Zone does not exist!', 'easyparcel-shipping'));
}

$allowed_countries = WC()->countries->get_shipping_countries();
$shipping_continents = WC()->countries->get_shipping_continents();

// Prepare locations.
$locations = array();
$postcodes = array();

foreach ($zone->get_zone_locations() as $location) {
    if ('postcode' === $location->type) {
        $postcodes[] = $location->code;
    } else {
        $locations[] = $location->type . ':' . $location->code;
    }
}
$add_btn_disabled = false;
$couriers = $zone->get_couriers();
foreach($couriers as $k=>&$v){
    if(!empty($v['courier_dropoff_point'])){
        $v['courier_display_name'] .= ' (DropOff Point)';
    }
    if($v['service_id'] == 'all' || $v['service_id'] == 'cheapest'){
        $add_btn_disabled = true;
    }
}


wp_register_script(
    'easyparcel_admin_shipping_zone_methods', 
    EASYPARCEL_MODULE_SETUP_URL . 'admin_shipping_zone_methods.js', 
    array('jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-backbone-modal'), 
    EASYPARCEL_VERSION, 
	array('in_footer' => true)
);

wp_localize_script(
    'easyparcel_admin_shipping_zone_methods',
    'shippingZoneMethodsLocalizeScript',
    array(
        'methods' => $couriers,
        'zone_name' => $zone->get_zone_name(),
        'zone_id' => $zone->get_id(),
        'wc_shipping_zones_nonce' => wp_create_nonce('wc_shipping_zones_nonce'),
        'add_courier_option' => $add_btn_disabled,
        'strings' => array(
            'unload_confirmation_msg' => __('Your changed data will be lost if you leave this page without saving.', 'easyparcel-shipping'),
            'save_changes_prompt' => __('Do you wish to save your changes first? Your changed data will be discarded if you choose to cancel.', 'easyparcel-shipping'),
            'save_failed' => __('Your changes were not saved. Please retry.', 'easyparcel-shipping'),
            'add_method_failed' => __('Shipping method could not be added. Please retry.', 'easyparcel-shipping'),
            'no_location_detected' => __('Kindly save your location before proceed to add in courier service', 'easyparcel-shipping'),
            'yes' => __('Yes', 'easyparcel-shipping'),
            'no' => __('No', 'easyparcel-shipping'),
            'default_zone_name' => __('Zone', 'easyparcel-shipping'),
        ),
    )
);

wp_enqueue_script('easyparcel_admin_shipping_zone_methods');
?>



<h2>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping' ) ); ?>"><?php esc_html_e( 'EasyParcel Courier Setting', 'easyparcel-shipping' ); ?></a> &gt;
	<span class="wc-shipping-zone-name"><?php echo esc_html( $zone->get_zone_name() ? $zone->get_zone_name() : __( 'Zone', 'easyparcel-shipping' ) ); ?></span>
</h2>

<?php do_action( 'woocommerce_shipping_zone_before_methods_table', $zone ); ?>

<table class="form-table wc-shipping-zone-settings">
	<tbody>
		<?php if ( 0 !== $zone->get_id() ) : ?>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="zone_name">
						<?php esc_html_e( 'Zone Name', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Set your zone name', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<input type="text" data-attribute="zone_name" name="zone_name" id="zone_name" value="<?php echo esc_attr( $zone->get_zone_name( 'edit' ) ); ?>" placeholder="<?php esc_attr_e( 'For eg: Peninsular Malaysia etc', 'easyparcel-shipping' ); ?>">
				</td>
			</tr>
			<tr valign="top" class="">
				<th scope="row" class="titledesc">
					<label for="zone_locations">
						<?php esc_html_e( 'Destination', 'easyparcel-shipping' ); ?>
						<?php echo wc_help_tip( __( 'Select the destination for this zone. For eg: Penang, Selangor etc', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
					</label>
				</th>
				<td class="forminp">
					<select multiple="multiple" data-attribute="zone_locations" id="zone_locations" name="zone_locations" data-placeholder="<?php esc_attr_e( 'Select regions within this zone', 'easyparcel-shipping' ); ?>" class="wc-shipping-zone-region-select chosen_select">
						<?php
						foreach ( $shipping_continents as $continent_code => $continent ) {
							echo '<option value="continent:' . esc_attr( $continent_code ) . '"' . esc_attr(wc_selected( "continent:$continent_code", $locations )) . '>' . esc_html( $continent['name'] ) . '</option>';

							$countries = array_intersect( array_keys( $allowed_countries ), $continent['countries'] );

							foreach ( $countries as $country_code ) {
								echo '<option value="country:' . esc_attr( $country_code ) . '"' . esc_attr(wc_selected( "country:$country_code", $locations )) . '>' . esc_html( '&nbsp;&nbsp; ' . $allowed_countries[ $country_code ] ) . '</option>';

								$states = WC()->countries->get_states( $country_code );

								if ( $states ) {
									foreach ( $states as $state_code => $state_name ) {
										echo '<option value="state:' . esc_attr( $country_code . ':' . $state_code ) . '"' . esc_attr(wc_selected( "state:$country_code:$state_code", $locations )) . '>' . esc_html( '&nbsp;&nbsp;&nbsp;&nbsp; ' . $state_name . ', ' . $allowed_countries[ $country_code ] ) . '</option>';
									}
								}
							}
						}
						?>
					</select>
				</td>
			<?php endif; ?>
		</tr>
		<tr valign="top" class="">
			<th scope="row" class="titledesc">
				<label>
					<?php esc_html_e( 'Courier Services', 'easyparcel-shipping' ); ?>
					<?php echo wc_help_tip( __( 'Select the courier options for this destination. The courier options will apply to orders with a shipping address in this destination.', 'easyparcel-shipping' ) ); // @codingStandardsIgnoreLine ?>
				</label>
			</th>
			<td class="">
				<table class="wc-shipping-zone-methods widefat">
					<thead>
						<tr>
							<th class="wc-shipping-zone-method-sort"></th>
							<th class="wc-shipping-zone-method-title"><?php esc_html_e( 'Courier', 'easyparcel-shipping' ); ?></th>
							<th class="wc-shipping-zone-method-enabled"><?php esc_html_e( 'Enabled', 'easyparcel-shipping' ); ?></th>
							<th class="wc-shipping-zone-method-description"><?php esc_html_e( 'Rate', 'easyparcel-shipping' ); ?></th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<td colspan="4">
								<button type="submit" id='add_courier_option' class="button wc-shipping-zone-add-method" <?php echo esc_attr($add_btn_disabled); ?> value="<?php esc_attr_e( 'Add courier service', 'easyparcel-shipping' ); ?>"><?php esc_html_e( 'Add courier service', 'easyparcel-shipping' ); ?></button>
							</td>
						</tr>
					</tfoot>
					<tbody class="wc-shipping-zone-method-rows"></tbody>
				</table>
			</td>
		</tr>
	</tbody>
</table>

<?php do_action( 'woocommerce_shipping_zone_after_methods_table', $zone ); ?>

<p class="submit">
	<button type="submit" name="submit" id="submit" class="button button-primary button-large wc-shipping-zone-method-save" value="<?php esc_attr_e( 'Save changes', 'easyparcel-shipping' ); ?>" disabled><?php esc_html_e( 'Save changes', 'easyparcel-shipping' ); ?></button>
</p>

<template type="text/html" id="tmpl-wc-shipping-zone-method-row-blank">
	<tr>
		<td class="wc-shipping-zone-method-blank-state" colspan="4">
			<p><?php esc_html_e( 'You can add multiple courier options here. Only customers in this destination can see them.', 'easyparcel-shipping' ); ?></p>
		</td>
	</tr>
</template>

<template type="text/html" id="tmpl-wc-shipping-zone-method-row">
	<tr data-id="{{ data.instance_id }}" data-enabled="{{ data.enabled }}">
		<td width="1%" class="wc-shipping-zone-method-sort"></td>
		<td width="78%" class="wc-shipping-zone-method-title">
			<a class="wc-shipping-zone-method-settings" href="admin.php?page=wc-settings&amp;tab=shipping&amp;section=easyparcel_shipping&amp;courier_id={{ data.instance_id }}">{{{ data.courier_display_name }}} </a>
			<div class="row-actions">
				<a class="wc-shipping-zone-method-settings" href="admin.php?page=wc-settings&amp;tab=shipping&amp;section=easyparcel_shipping&amp;courier_id={{ data.instance_id }}"><?php esc_html_e( 'Edit', 'easyparcel-shipping' ); ?></a> | <a href="#" class="wc-shipping-zone-method-delete"><?php esc_html_e( 'Delete', 'easyparcel-shipping' ); ?></a>
			</div>
		</td>
		<td width="1%" class="wc-shipping-zone-method-enabled"><a href="#">{{{ data.enabled_icon }}}</a></td>
		<td width="20%" class="wc-shipping-zone-method-description">
			<strong class="wc-shipping-zone-method-type">{{ data.method_title }}</strong>
			{{{ data.rate }}}
		</td>
	</tr>
</template>

<template type="text/template" id="tmpl-wc-modal-shipping-method-settings">
	<div class="wc-backbone-modal wc-backbone-modal-shipping-method-settings">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1>
						<?php
						printf(
							/* translators: %s: shipping method title */
							esc_html__( '%s Settings', 'easyparcel-shipping' ),
							'{{{ data.method.method_title }}}'
						);
						?>
					</h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'easyparcel-shipping' ); ?></span>
					</button>
				</header>
				<article class="wc-modal-shipping-method-settings">
					<form action="" method="post">
						{{{ data.method.settings_html }}}
						<input type="hidden" name="instance_id" value="{{{ data.instance_id }}}" />
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Save changes', 'easyparcel-shipping' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</template>

<template type="text/template" id="tmpl-wc-modal-add-shipping-method">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Add courier service', 'easyparcel-shipping' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'easyparcel-shipping' ); ?></span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<div class="wc-shipping-zone-method-selector">
							<p><?php esc_html_e( 'Choose the courier service you wish to add. Only courier services which support zones are listed.', 'easyparcel-shipping' ); ?></p>

							<select name="add_method_id">
								<?php
								foreach ( WC()->shipping()->load_shipping_methods() as $method ) {
									if ( ! $method->supports( 'shipping-zones' ) ) {
										continue;
									}
									echo '<option data-description="' . esc_attr( wp_kses_post( wpautop( $method->get_method_description() ) ) ) . '" value="' . esc_attr( $method->id ) . '">' . esc_html( $method->get_method_title() ) . '</li>';
								}
								?>
							</select>
						</div>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add courier service', 'easyparcel-shipping' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</template>
