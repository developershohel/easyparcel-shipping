<?php
//function is_shipping_tab() {
//	$shipping            = array();
//	$current_tab         = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : '';
//	$shipping_id         = isset( $_GET['zone_id'] ) ? sanitize_text_field( $_GET['zone_id'] ) : '';
//	$section             = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '';
//	$shipping['method']  = $current_tab;
//	$shipping['id']      = $shipping_id;
//	$shipping['section'] = $section;
//
//	return $shipping;
//}
//
//if ( is_admin() ) {
//	add_action( 'admin_init', 'check_shipping_tab' );
//	function check_shipping_tab() {
//		$shipping = is_shipping_tab();
//		if ( isset( $shipping['method'] ) == 'shipping' && isset( $shipping['id'] ) ) {
//			add_action( 'woocommerce_shipping_zone_after_methods_table', 'ep_shipping_courier_list' );
//		}
//	}
//}
//
function ep_shipping_courier_list() {
	global $wpdb;
	if ( ! isset( $_GET['tab'] ) && ! isset( $_GET['zone_id'] ) ) {
		return;
	}
	if ( ! class_exists( 'WC_Easyparcel_Shipping_Zone' ) ) {
		include_once 'wc_easyparcel_shipping_zone.php';
	}
	$shipping_zone = new WC_Easyparcel_Shipping_Zone();
	if ( sanitize_text_field( $_GET['tab'] ) == 'shipping' && sanitize_key( $_GET['zone_id'] ) !== 'new' ) {
		$zone_id = absint( $_GET['zone_id'] );
		$table   = $wpdb->prefix . 'easyparcel_zones_courier';
		$shipping_zone->setup_courier_page( $zone_id );
	}
}

// Hook to run a function after deleting a shipping zone
add_action( 'woocommerce_delete_shipping_zone', 'easyparcel_delete_all_courier_by_zone_id' );

// Hook to run a function after deleting a shipping method
add_action( 'woocommerce_shipping_zone_method_deleted',
	'easyparcel_delete_courier_for_shipping_method', 10, 3 );

function easyparcel_delete_courier_for_shipping_method( $instance_id, $method_id, $zone_id ) {
	global $wpdb;
	$courier_table = $wpdb->prefix . 'easyparcel_zones_courier';
	if ( $instance_id ) {
		$get_courier = $wpdb->get_var( "SELECT id FROM $courier_table WHERE zone_id=$zone_id AND instance_id=$instance_id" );
		if ( $get_courier ) {
			$wpdb->delete( $courier_table, [ 'id' => $get_courier ] );
		}
	}
}

function easyparcel_delete_all_courier_by_zone_id( $zone_id ) {
	global $wpdb;
	$courier_table = $wpdb->prefix . 'easyparcel_zones_courier';
	if ( $zone_id ) {
		$get_couriers = $wpdb->get_results( "SELECT id FROM $courier_table WHERE zone_id=$zone_id" );
		if ( $get_couriers ) {
			foreach ( $get_couriers as $courier ) {
				$wpdb->delete( $courier_table, [ 'id' => $courier->id ] );
			}
		}
	}
}




