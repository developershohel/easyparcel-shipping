<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Data Store 
if (!class_exists('EP_Shipping_Zones')) {
    include_once EASYPARCEL_DATASTORE_PATH .'ep_shipping_zones.php';
}

// render script
wp_register_script(
    'easyparcel_admin_shipping_zone', 
    EASYPARCEL_MODULE_SETUP_URL . 'admin_shipping_zone.js', 
    array('jquery', 'wp-util', 'underscore', 'backbone', 'jquery-ui-sortable', 'wc-enhanced-select', 'wc-backbone-modal'), 
    EASYPARCEL_VERSION, 
	array('in_footer' => true)
);
wp_localize_script(
    'easyparcel_admin_shipping_zone',
    'shippingZonesLocalizeScript',
    array(
        'zones' => EP_Shipping_Zones::get_zones('json'),
        'default_zone' => array(
            'zone_id' => 0,
            'zone_name' => '',
            'zone_order' => null,
        ),
        'wc_shipping_zones_nonce' => wp_create_nonce('wc_shipping_zones_nonce'),
        'strings' => array(
            'unload_confirmation_msg' => 'Your changed data will be lost if you leave this page without saving.',
            'delete_confirmation_msg' => 'Are you sure you want to delete this zone? This action cannot be undone.',
            'save_failed' => 'Your changes were not saved. Please retry.',
            'no_shipping_methods_offered' => 'No shipping methods offered to this zone.',
            'no_courier_applied' => 'No courier applied to this zone.',
        ),
    )
);
wp_enqueue_script('easyparcel_admin_shipping_zone');

// Layout
?>

<h2 class="wc-shipping-zones-heading">
	<?php esc_html_e( 'EasyParcel Courier Setting', 'easyparcel-shipping' ); ?>
	<a href="<?php echo esc_url(admin_url( 'admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping&zone_id=new' )); ?>" class="page-title-action">
        <?php esc_html_e( 'Add Courier Setting', 'easyparcel-shipping' ); ?>
    </a>
</h2>

<p><?php echo esc_html_e( 'You can have different courier settings for different areas/zones according to your preference. Customers whose shipping address is in a specific area/zone will see the courier options and shipping rates offered to them.','easyparcel-shipping' ); ?></p>
<table class="wc-shipping-zones widefat">
	<thead>
		<tr style="width:100%;">
			<th class="wc-shipping-zone-sort"><?php wc_help_tip( 'You can set multiple zone name here. The shipping destination will be applied to the order according to priority listed from top. You can drag and drop to reorganize the priority list.', true); ?></th>
			<th class="wc-shipping-zone-name" style="width:25%;"><?php esc_html_e( 'Zone Name', 'easyparcel-shipping' ); ?></th>
			<th class="wc-shipping-zone-courier" style="width:40%;"><?php esc_html_e( 'Courier(s) - (Rate)', 'easyparcel-shipping' ); ?></th>
			<th class="wc-shipping-zone-region" style="width:30%;"><?php esc_html_e( 'Region(s)', 'easyparcel-shipping' ); ?></th>
		</tr>
	</thead>
	<tbody class="wc-shipping-zone-rows"></tbody>
</table>

<template type="text/html" id="tmpl-wc-shipping-zone-row">
	<tr data-id="{{ data.zone_id }}">
		<td width="1%" class="wc-shipping-zone-sort"></td>
		<td class="wc-shipping-zone-name">
			<a href="admin.php?page=wc-settings&amp;tab=shipping&amp;section=easyparcel_shipping&amp;zone_id={{ data.zone_id }}">{{ data.zone_name }}</a>
			<div class="row-actions">
				<a href="admin.php?page=wc-settings&amp;tab=shipping&amp;section=easyparcel_shipping&amp;zone_id={{ data.zone_id }}"><?php esc_html_e( 'Edit', 'easyparcel-shipping' ); ?></a> | <a href="#" class="wc-shipping-zone-delete"><?php esc_html_e( 'Delete', 'easyparcel-shipping' ); ?></a>
			</div>
		</td>
		<td width="40%" class="wc-shipping-zone-methods">
			<div><ul></ul></div>
		</td>
		<td class="wc-shipping-zone-region">
			{{ data.formatted_zone_location }}
		</td>
	</tr>
</template>

<template type="text/template" id="tmpl-wc-modal-add-shipping-method">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Add shipping method', 'easyparcel-shipping' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'easyparcel-shipping' ); ?></span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<div class="wc-shipping-zone-method-selector">
							<p><?php esc_html_e( 'Choose the shipping method you wish to add. Only shipping methods which support zones are listed.', 'easyparcel-shipping' ); ?></p>

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
							<input type="hidden" name="zone_id" value="{{{ data.zone_id }}}" />
						</div>
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button button-primary button-large"><?php esc_html_e( 'Add shipping method', 'easyparcel-shipping' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</template>

<style>
table.wc-shipping-zones td.wc-shipping-zone-methods ul li::before {
	content:'';
}
</style>
