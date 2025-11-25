<?php

// Multisite not supported

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die();
}
// global $wpdb;
// $tableArray = [
//     $wpdb->prefix . "easyparcel_courier",
//     $wpdb->prefix . "easyparcel_courier_setting",
//     $wpdb->prefix . "easyparcel_zones_courier",
//     $wpdb->prefix . "easyparcel_zones",
//     $wpdb->prefix . "easyparcel_zone_locations",
//     // $wpdb->prefix . "table2",
// ];
// foreach ($tableArray as $table) {
//     $wpdb->query("DROP TABLE IF EXISTS $table");
// }

// // uninstall delete option
// delete_option('woocommerce_easyparcel_settings');
$store_email = get_option('woocommerce_email_from_address');
if (empty($store_email)) {
    $store_email = get_option('admin_email');
}

$store_url = get_site_url();
$store_country = get_option('woocommerce_default_country');
$store_name = get_bloginfo('name');

$country_code = explode(':', $store_country)[0];

$api_data = array(
    'store_name'     => $store_name,
    'phone_no' => '-',
    'email'    => $store_email,
    'url'      => $store_url,
    'country'  => strtolower($country_code),
    'platform' => 'woocommerce',
    'action'   => '2'
);

$api_url = 'https://api.easyparcel.com/open_api/integration_installation_tracker';

wp_remote_post($api_url, array(
    'method'      => 'POST',
    'timeout'     => 15,
    'httpversion' => '1.0',
    'blocking'    => true,
    'headers'     => array(
        'Content-Type' => 'application/json',
    ),
    'body'        => json_encode($api_data)
));
