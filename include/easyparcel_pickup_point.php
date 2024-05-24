<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'woocommerce_admin_order_data_after_shipping_address', 'easyparcel_admin_order_display_pickup_point', 30, 1 );
add_filter( 'woocommerce_get_order_item_totals', 'easyparcel_display_fields_on_order_item_totals', 1000, 3 );

function easyparcel_pickup_point_settings() {
	$pickup_available_methods = array();
	if ( isset( WC()->session ) ) {
		$pickup_available_methods = WC()->session->get( "EasyParcel_Pickup_Available" ) ? WC()->session->get( "EasyParcel_Pickup_Available" ) : array();
	}

	return array(
		'targeted_methods' => $pickup_available_methods, // Your targeted method(s) in this array
		'field_id'         => 'easyparcel_pickup_point', // Field Id
		'field_type'       => 'select', // Field type
		'field_label'      => '', // Leave empty value if the first option has a text (see below).
		'label_name'       => __( "Pick Up Point", "easyparcel" ), // for validation and as meta key for orders
	);
}

function easyparcel_display_fields_on_order_item_totals( $total_rows, $order, $tax_display ) {
	// Load settings and convert them in variables
	$new_total_rows = [];
	// Loop through order total rows
	foreach ( $total_rows as $key => $values ) {
		$new_total_rows[ $key ] = $values;
		// Inserting the easyparcel_pickup_point under shipping method
		if ( $key === 'shipping' ) {
			extract( easyparcel_pickup_point_settings() );
			$easyparcel_pickup_point = $order->get_meta( '_' . $field_id ); // Get easyparcel_pickup_point
			if ( ! empty( $easyparcel_pickup_point ) ) {
				$new_total_rows[ $field_id ] = array(
					'label' => $label_name . ":",
					'value' => $easyparcel_pickup_point,
				);
			}

			$easyparcel_awb = ( ! empty( $order->get_meta( '_easyparcel_awb' ) ) ) ? $order->get_meta( '_easyparcel_awb' ) : '- '; // Get EP AWB

			$selected_courier = ( ! empty( $order->get_meta( '_easyparcel_selected_courier' ) ) ) ? $order->get_meta( '_easyparcel_selected_courier' ) : '-';
			// if(!empty($selected_courier)){
			//     $order_data = $order->get_data();
			//     $selected_courier = $order_data['currency']." ".number_format($order_data['shipping_total'],2)." via ".$selected_courier;
			// }

			$new_total_rows["final_courier"] = array(
				'label' => "Fulfillment " . ":",
				'value' => $selected_courier,
			);

			$easyparcel_tracking_url            = ( ! empty( $order->get_meta( '_easyparcel_tracking_url' ) ) ) ? '<a href="' . esc_url( $order->get_meta( '_easyparcel_tracking_url' ) ) . '" target="_blank"><u>' . esc_html( $easyparcel_awb ) . '</u></a>' : '- '; // Get EP Tracking URL
			$new_total_rows["Tracking"] = array(
				'label' => "Tracking" . ":",
				'value' => $easyparcel_tracking_url,
			);
		}
	}

	return $new_total_rows;
}

function easyparcel_admin_order_display_pickup_point( $order ) {
	extract( easyparcel_pickup_point_settings() );
	$easyparcel_pickup_point = $order->get_meta( '_' . $field_id );
	if ( ! empty( $easyparcel_pickup_point ) ) {
		echo '<p><strong>' . esc_attr( $label_name ) . '</strong>: ' . esc_attr( $easyparcel_pickup_point ) . '</p>';
	}
}