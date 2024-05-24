<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
// Hook to run a function after deleting a shipping zone
add_action( 'woocommerce_delete_shipping_zone', 'easyparcel_delete_all_courier_by_zone_id' );

// Hook to run a function after deleting a shipping method
add_action( 'woocommerce_shipping_zone_method_deleted',
	'easyparcel_delete_courier_for_shipping_method', 10, 3 );

function easyparcel_delete_courier_for_shipping_method( $instance_id, $method_id, $zone_id ) {
	global $wpdb;
	$instance_id   = absint( $instance_id );
	$courier_table = $wpdb->prefix . 'easyparcel_zones_courier';
	if ( $instance_id ) {
		$get_courier = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id=%d AND instance_id=%d", $zone_id, $instance_id ) );
		if ( $get_courier ) {
			$wpdb->delete( $courier_table, [ 'id' => $get_courier ] );
		}
	}
}

function easyparcel_delete_all_courier_by_zone_id( $zone_id ) {
	global $wpdb;
	$zone_id       = absint( $zone_id );
	$courier_table = $wpdb->prefix . 'easyparcel_zones_courier';
	if ( $zone_id ) {
		$get_couriers = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id=%d", $zone_id ) );
		if ( !empty($get_couriers )) {
			foreach ( $get_couriers as $courier ) {
				$wpdb->delete( $courier_table, [ 'id' => $courier->id ] );
			}
		}
	}
}




