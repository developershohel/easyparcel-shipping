<?php
function easyparcel_courier_list() {
	global $wpdb;
	if ( ! isset( $_POST['zone_id'] ) || ! isset( $_POST['instance_id'] ) ) {
		echo 'No Zone ID or Instance ID Found';
		wp_die();
	}
	$zone_id     = absint( $_POST['zone_id'] );
	$instance_id = absint( $_POST['instance_id'] );
	if ( ! class_exists( 'WC_Easyparcel_Shipping_Zone' ) ) {
		include_once 'wc_easyparcel_shipping_zone.php';
	}
	$courier_table = $wpdb->prefix . 'easyparcel_zones_courier';
	$courier       = $wpdb->get_var( "SELECT instance_id FROM $courier_table WHERE zone_id=$zone_id AND instance_id=$instance_id" );
	$shipping_zone = new WC_Easyparcel_Shipping_Zone();
	if ( empty( $courier ) ) {
		$shipping_zone->add_new_courier( $zone_id );
	} else {
		$shipping_zone->setup_courier_content( $zone_id, $instance_id );
	}
	wp_die();
}

add_action( 'wp_ajax_easyparcel_courier_list', 'easyparcel_courier_list' );
add_action( 'wp_ajax_nopriv_easyparcel_courier_list', 'easyparcel_courier_list' );

function easyparcel_check_setting() {
	$value = get_option( 'woocommerce_easyparcel_settings' );
	if ( isset( $value['enabled'] ) && $value['enabled'] == 'yes' ) {
		echo wp_json_encode( [ 'status' => true ] );
	} else {
		echo wp_json_encode( [ 'status' => false ] );
	}
	wp_die();
}

add_action( 'wp_ajax_easyparcel_check_setting', 'easyparcel_check_setting' );
add_action( 'wp_ajax_nopriv_easyparcel_check_setting', 'easyparcel_check_setting' );

function easyparcel_ajax_save_courier_services() {
	global $wpdb;
	if ( ! isset( $_POST['courier_data'] ) ) {
		echo wp_json_encode( array( 'status' => false, 'message' => "We don't find any data" ) );
		wp_die();
	}
	$courier_data = wp_json_encode( $_POST['courier_data'] );
	$courier_args = json_decode( $courier_data, true );
	$zone_id      = absint( $courier_args['zone_id'] );

	if ( empty( $zone_id ) ) {
		echo wp_json_encode( array( 'status' => false, 'message' => "We don't find any zone ID" ) );
		wp_die();
	}

	$instance_id         = absint( $courier_args['instance_id'] );
	$method              = sanitize_text_field( $_POST['courier_setting'] );
	$table               = $wpdb->prefix . 'easyparcel_zones_courier';
	$courier_order_value = $wpdb->get_var( "SELECT courier_order FROM $table WHERE zone_id=$zone_id" );
	$shipping_table      = $wpdb->prefix . 'woocommerce_shipping_zone_methods';
	$shipping_method     = $wpdb->get_results( "SELECT * FROM $shipping_table WHERE zone_id=$zone_id", ARRAY_A );
	try {
		if ( $method == 'popup' ) {
			if ( empty( $instance_id ) ) {
				echo wp_json_encode( array( 'status' => false, 'message' => "We don't find the instance ID" ) );
				wp_die();
			}
			$get_courier_id = $wpdb->get_var( "SELECT id FROM $table WHERE instance_id=$instance_id AND zone_id=$zone_id" );
			if ( empty( $get_courier_id ) ) {
				if ( $courier_order_value !== false ) {
					$courier_args['courier_order'] = (int) $wpdb->get_var( "SELECT MAX(courier_order) FROM $table WHERE zone_id=$zone_id" ) + 1;
				}
				$insert_courier = $wpdb->insert( $table, $courier_args );
				if ( $insert_courier !== false ) {
					echo wp_json_encode( array(
						'status'       => true,
						'message'      => "Courier Successfully Save",
						'courier_name' => $courier_args['courier_display_name'] ?? $courier_args['courier_name'],
						'courier_id'   => $wpdb->insert_id
					) );
				} else {
					echo wp_json_encode( array( 'status' => false, 'message' => "Courier Didn't update" ) );
					wp_die();
				}
			} else {
				$update_courier = $wpdb->update( $table, $courier_args, [
					'id' => $get_courier_id
				] );
				if ( $update_courier !== false ) {
					echo wp_json_encode( array(
						'status'       => true,
						'message'      => "Courier Successfully Save",
						'courier_name' => $courier_args['courier_display_name'] ?? $courier_args['courier_name'],
						'courier_id'   => $wpdb->insert_id
					) );
				} else {
					echo wp_json_encode( array( 'status' => false, 'message' => "Courier Didn't update" ) );
					wp_die();
				}
			}
		} else if ( $method == 'edit_courier' ) {
			$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : '';
			if ( empty( $id ) ) {
				echo wp_json_encode( array( 'status' => false, 'message' => "Courier Don't find the courier ID" ) );
				wp_die();
			}
			$instance_id = $wpdb->get_var( "SELECT instance_id FROM $table WHERE id=$id" );
			if ( empty( $instance_id ) ) {
				$max_method_order = $wpdb->get_var( "SELECT MAX(method_order) FROM $shipping_table WHERE zone_id=$zone_id" );
				$method_order     = $max_method_order + 1;
				$instance_method  = $wpdb->insert( $shipping_table, array(
					'zone_id'      => $zone_id,
					'method_id'    => 'easyparcel',
					'method_order' => $method_order
				) );
				if ( $instance_method !== false ) {
					$new_instance_id             = $wpdb->insert_id;
					$courier_args['instance_id'] = $new_instance_id;
					$update_courier              = $wpdb->update( $table, $courier_args, [
						'id' => $id
					] );
					if ( $update_courier !== false ) {
						echo wp_json_encode( array(
							'status'     => true,
							'message'    => "Courier Successfully Save",
							'courier_id' => $id
						) );
					} else {
						echo wp_json_encode( array( 'status' => false, 'message' => "Courier Didn't update" ) );
					}
				} else {
					echo wp_json_encode( array( 'status' => false, 'message' => "Courier Didn't update" ) );
				}
			} else {
				$update_courier = $wpdb->update( $table, $courier_args, [
					'id' => $id
				] );
				if ( $update_courier !== false ) {
					echo wp_json_encode( array(
						'status'     => true,
						'message'    => "Courier Successfully Save",
						'courier_id' => $id
					) );
				} else {
					echo wp_json_encode( array( 'status' => false, 'message' => "Courier Didn't update" ) );
				}
			}
		} else if ( $method == 'setup_courier' ) {
			if ( $courier_order_value !== false ) {
				$courier_args['courier_order'] = (int) $wpdb->get_var( "SELECT MAX(courier_order) FROM $table WHERE zone_id=$zone_id" ) + 1;
			}
			if ( ! empty( $shipping_method ) ) {
				$max_method_order = $wpdb->get_var( "SELECT MAX(method_order) FROM $shipping_table WHERE zone_id=$zone_id" );
				$method_order     = $max_method_order + 1;
				$instance_method  = $wpdb->insert( $shipping_table, array(
					'zone_id'      => $zone_id,
					'method_id'    => 'easyparcel',
					'method_order' => $method_order
				) );
			} else {
				$instance_method = $wpdb->insert( $shipping_table, array(
					'zone_id'      => $zone_id,
					'method_id'    => 'easyparcel',
					'method_order' => 1
				) );
			}
			if ( $instance_method !== false ) {
				$new_instance_id             = $wpdb->insert_id;
				$courier_args['instance_id'] = $new_instance_id;
				$insert_courier              = $wpdb->insert( $table, $courier_args );
				if ( $insert_courier !== false ) {
					echo wp_json_encode( array(
						'status'       => true,
						'message'      => "Courier Successfully Save",
						'courier_name' => $courier_args['courier_display_name'] ?? $courier_args['courier_name']
					) );
				} else {
					echo wp_json_encode( array( 'status' => false, 'message' => "Courier Didn't update" ) );
				}
			} else {
				echo wp_json_encode( array( 'status' => false, 'message' => "Courier Didn't update" ) );
			}
		}
	} catch ( Exception $e ) {
		echo wp_json_encode( array( 'status' => false, 'message' => $e->getMessage() ) );
	}
	wp_die();
}

add_action( 'wp_ajax_easyparcel_ajax_save_courier_services', 'easyparcel_ajax_save_courier_services' );
add_action( 'wp_ajax_nopriv_easyparcel_ajax_save_courier_services', 'easyparcel_ajax_save_courier_services' );