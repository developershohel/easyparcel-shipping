<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', 'easyparcel_add_meta_box' );
// add_action( 'woocommerce_my_account_my_orders_actions','add_column_my_account_orders_ast_track_column', 10, 2 );
// add_action( 'woocommerce_process_shop_order_meta', 'save_meta_box', 0, 2 );
add_action( 'wp_ajax_wc_shipment_tracking_save_form', 'easyparcel_save_meta_box_ajax' );


function easyparcel_add_meta_box() {
	add_meta_box( 'easyparcel-shipping-integration-order-fulfillment', __( 'EasyParcel Fulfillment', 'easyparcel-shipping' ), 'easyparcel_order_page_custom_meta_box', 'shop_order', 'side', 'high' );
}

function easyparcel_order_page_custom_meta_box() {
	global $post, $wpdb;
	#### DYNAMIC VALUE - S ####
	$order                  = wc_get_order( $post->ID );
	$selected_courier       = ( ! empty( $order->get_meta( '_easyparcel_selected_courier' ) ) ) ? $order->get_meta( '_easyparcel_selected_courier' ) : '';
	$awb                    = ( ! empty( $order->get_meta( '_easyparcel_awb' ) ) ) ? $order->get_meta( '_easyparcel_awb' ) : '';
	$tracking_url           = ( ! empty( $order->get_meta( '_easyparcel_tracking_url' ) ) ) ? $order->get_meta( '_easyparcel_tracking_url' ) : '';
	$awb_link               = ( ! empty( $order->get_meta( '_easyparcel_awb_id_link' ) ) ) ? $order->get_meta( '_easyparcel_awb_id_link' ) : '';
	$easyparcel_paid_status = ( $order->meta_exists( '_easyparcel_payment_status' ) ) ? 1 : 0; # 1 = Paid / 0 = Pending

	$default_provider = $order->get_shipping_method();
	if ( ! empty( $selected_courier ) ) {
		$default_provider = $selected_courier;
	}

	$api_detail                    = easyparcel_get_api_detail( $post );
	$shipment_providers_by_country = $api_detail->shipment_providers_list;
	$dropoff_point_list            = wp_json_encode( $api_detail->dropoff_point_list );
	#### DYNAMIC VALUE - E ####

	echo '<div id="easyparcel-fulfillment-form">';
	echo '<p class="form-field shipping_provider_field"><label for="shipping_provider">' . esc_html__( 'Courier Services:', 'easyparcel-shipping' ) . '</label><br/><select id="shipping_provider" name="shipping_provider" class="chosen_select shipping_provider_dropdown" style="width:100%;">';
	echo '<option value="">' . esc_html__( 'Select Preferred Courier Service', 'easyparcel-shipping' ) . '</option>';
	foreach ( $shipment_providers_by_country as $providers ) {
		$selected = ( $providers->provider_name == $default_provider ) ? 'selected' : '';
		echo '<option value="' . esc_attr( $providers->ts_slug ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $providers->provider_name ) . '</option>';
	}
	echo '</select></p>';
	#### drop off - S ####
	echo '<p class="form-field drop_off_field "></p>';
	woocommerce_wp_hidden_input( array(
		'id'    => 'easyparcel_dropoff',
		'value' => $dropoff_point_list
	) );
	woocommerce_wp_hidden_input( array(
		'id'    => 'selected_easyparcel_dropoff',
		'value' => $api_detail->selected_dropoff_point
	) );
	#### drop off - E ####
	if ( $easyparcel_paid_status ) {
		echo '<p class="form-field tracking_number_field ">
            <label for="tracking_number">' . esc_html( 'Tracking number:' ) . '</label>
            <input type="text" class="short" style="" name="tracking_number" id="tracking_number" value="' . esc_attr( $awb ) . '" autocomplete="off"> 
            </p>';
		echo '<p class="form-field tracking_url_field ">
            <label for="tracking_url">' . esc_html( 'Tracking url:' ) . '</label>
            <input type="text" class="short" style="" name="tracking_url" id="tracking_url" value="' . esc_url( $tracking_url ) . '" autocomplete="off"> 
            </p>';
	}
	woocommerce_wp_hidden_input( array(
		'id'    => 'easyparcel_fulfillment_create_nonce',
		'value' => wp_create_nonce( 'create-easyparcel-fulfillment' ),
	) );
	if ( $easyparcel_paid_status ) {
		echo '<button class="button button-info btn_ast2 button-save-form">' . esc_html__( 'Edit FulFillment', 'easyparcel-shipping' ) . '</button>';
	} else {
		woocommerce_wp_text_input( array(
			'id'          => 'pick_up_date',
			'label'       => __( 'Drop Off / Pick Up Date', 'easyparcel-shipping' ),
			'placeholder' => date_i18n( __( 'Y-m-d', 'easyparcel-shipping' ), time() ),
			'description' => '',
			'class'       => 'date-picker-field',
			'value'       => date_i18n( __( 'Y-m-d', 'easyparcel-shipping' ), current_time( 'timestamp' ) ),
		) );
		echo '<button class="button button-primary btn_ast2 button-save-form">' . esc_html__( 'Fulfill Order', 'easyparcel-shipping' ) . '</button>';
	}
	if ( $easyparcel_paid_status ) {
		echo '<p class="fulfillment_details">' . esc_attr( $selected_courier ) . '<br>
			<a href="' . esc_url( $tracking_url ) . '" target="_blank">' . esc_html( $awb ) . '</a><br>
			<a href="' . esc_url( $awb_link ) . '" target="_blank">' . esc_html( '[Download AWB]' ) . '</a></p>';
	}
	echo '</div>';
	wp_enqueue_script( 'easyparcel-shipping-integration-order-fulfillment-js', plugin_dir_url( __FILE__ ) . 'js/easyparcel_meta_box.js', array( 'jquery' ), EASYPARCEL_VERSION, true );
}

function easyparcel_save_meta_box_ajax() {
	check_ajax_referer( 'create-easyparcel-fulfillment', 'security', true );
	$shipping_provider = isset( $_POST['shipping_provider'] ) ? wc_clean( $_POST['shipping_provider'] ) : '';
	$courier_name      = isset( $_POST['courier_name'] ) ? wc_clean( $_POST['courier_name'] ) : '';
	$drop_off_point    = isset( $_POST['drop_off_point'] ) ? wc_clean( $_POST['drop_off_point'] ) : '';
	$tracking_number   = isset( $_POST['tracking_number'] ) ? wc_clean( $_POST['tracking_number'] ) : '';
	$tracking_number   = str_replace( ' ', '', $tracking_number );
	$tracking_url      = isset( $_POST['tracking_url'] ) ? wc_clean( $_POST['tracking_url'] ) : '';
	$tracking_url      = str_replace( ' ', '', $tracking_url );

	### Order Part ###
	$pick_up_date           = isset( $_POST['pick_up_date'] ) ? wc_clean( $_POST['pick_up_date'] ) : '';
	$order_id               = isset( $_POST['order_id'] ) ? wc_clean( $_POST['order_id'] ) : '';
	$order                  = wc_get_order( $order_id );
	$easyparcel_paid_status = ( $order->meta_exists( '_easyparcel_payment_status' ) ) ? 1 : 0; # 1 = Paid / 0 = Pending

	if ( ! class_exists( 'Easyparcel_Extend_Shipping_Method' ) ) {
		include_once 'easyparcel_shipping.php';
	}
	$Easyparcel_Extend_Shipping_Method = new Easyparcel_Extend_Shipping_Method();

	if ( ! $easyparcel_paid_status ) {
		### Add Fulfillment Part ###
		if ( $pick_up_date != '' && $shipping_provider != '' ) {
			$obj                    = (object) array();
			$obj->order_id          = $order_id;
			$obj->pick_up_date      = $pick_up_date;
			$obj->shipping_provider = $shipping_provider;
			$obj->courier_name      = $courier_name;
			$obj->drop_off_point    = $drop_off_point;
			$easyparcel_order               = $Easyparcel_Extend_Shipping_Method->process_booking_order( $obj );
			if ( ! empty( $easyparcel_order ) ) {
				print_r( $easyparcel_order );
			} else {
				echo 'success';
			}

		} else {
			echo 'Please fill all the required data. easyparcel_save_meta_box_ajax';
		}

	} else {
		### Edit Fulfillment Part ###
		if ( strlen( $tracking_number ) > 0 && strlen( $tracking_url ) > 0 && $shipping_provider != '' ) {
			$order->update_meta_data( '_easyparcel_awb', $tracking_number );
			$order->update_meta_data( '_easyparcel_selected_courier', $courier_name );
			$order->update_meta_data( '_easyparcel_awb_id_link', '' );
			$order->update_meta_data( '_easyparcel_tracking_url', $tracking_url );
			$order->save();
			echo 'success';
		} else {
			echo 'Please fill all the required data. easyparcel_save_meta_box_ajax';
		}
	}

	die();
}

function easyparcel_get_api_detail( $post ) {

	if ( ! class_exists( 'Easyparcel_Extend_Shipping_Method' ) ) {
		include_once 'easyparcel_shipping.php';
	}

	$Easyparcel_Extend_Shipping_Method = new Easyparcel_Extend_Shipping_Method();
	$rates                         = $Easyparcel_Extend_Shipping_Method->get_admin_shipping( $post );

	$obj                          = (object) array();
	$obj->shipment_providers_list = array();
	$obj->dropoff_point_list      = array();
	$obj->selected_dropoff_point  = '';

	foreach ( $rates as $rate ) {
		$shipment_provider                = (object) array();
		$shipment_provider->ts_slug       = $rate['id'];
		$shipment_provider->provider_name = $rate['label'];
		$obj->shipment_providers_list[]   = $shipment_provider;
		$dropoff                          = array();
		$dropoff[ $rate['id'] ]           = $rate['dropoff_point'];
		$obj->dropoff_point_list[]        = $dropoff;

		$obj->selected_dropoff_point = isset( $rate['selected_dropoff_point'] ) ? esc_attr( $rate['selected_dropoff_point'] ) : '';
	}

	return $obj;
}
	