<?php
// Multisite not supported

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die();
}
global $wpdb;

// Drop the table using $wpdb->query() - Direct query (acceptable during uninstallation)
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easyparcel_zones_courier" );

// Delete option using delete_option() - Direct call (acceptable during uninstallation)
delete_option( 'woocommerce_easyparcel_settings' );
flush_rewrite_rules();