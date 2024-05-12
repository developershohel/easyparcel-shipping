<?php
// Multisite not supported

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die();
}
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easyparcel_zones_courier" );
delete_option( 'easyparcel_settings' );
flush_rewrite_rules();