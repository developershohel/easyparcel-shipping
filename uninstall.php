<?php

// Multisite not supported

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die();
}
global $wpdb;
$table = $wpdb->prefix . "easyparcel_zones_courier";
$wpdb->query( "DROP TABLE IF EXISTS $table" );
//uninstall delete option
delete_option( 'woocommerce_easyparcel_settings' );