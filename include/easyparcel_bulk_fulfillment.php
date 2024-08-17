<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
add_filter( 'bulk_actions-edit-shop_order', 'easyparcel_bulk_actions_order_fulfillment', 20, 1 );
add_filter( 'handle_bulk_actions-edit-shop_order', 'easyparcel_handle_bulk_actions_order_fulfillment', 10, 3 );
add_action( 'admin_notices', 'easyparcel_bulk_actions_order_fulfillment_admin_notice' );
add_action( 'wp_ajax_easyparcel_bulk_fulfillment_popup', 'easyparcel_bulk_fulfillment_popup' );
add_filter( 'manage_shop_order_posts_columns', 'easyparcel_shop_order_columns', 99 );
add_action( 'manage_shop_order_posts_custom_column', 'easyparcel_render_shop_order_columns' );
add_filter( 'manage_shop_order_posts_columns', 'easyparcel_destination_columns', 99 );
add_filter( "manage_edit-shop_order_sortable_columns", 'easyparcel_destination_columns_sortable' );
add_action( 'pre_get_posts', 'easyparcel_shop_order_column_destination_sortable_orderby' );
add_action( 'manage_shop_order_posts_custom_column', 'easyparcel_render_destination_columns' );
add_action( 'wp_ajax_wc_shipment_tracking_save_form_bulk', 'easyparcel_save_bulk_order_ajax' );

/**
 * Easyparcel bulk actions order fulfillment
 *
 * @param $actions
 *
 * @return mixed
 */
function easyparcel_bulk_actions_order_fulfillment( $actions ) {
	$actions['order_fulfillment'] = __( 'Order Fulfillment', 'easyparcel-shipping' );

	return $actions;
}

/**
 * Easyparcel handle bulk actions order fulfillment
 *
 * @param $redirect_to
 * @param $action
 * @param $post_ids
 *
 * @return mixed|string
 *
 */
function easyparcel_handle_bulk_actions_order_fulfillment( $redirect_to, $action, $post_ids ) {
    if ( $action !== 'order_fulfillment' ) {
		return $redirect_to;
	}

	$processed_ids = array();

	foreach ( $post_ids as $post_id ) {
		$processed_ids[] = $post_id;
	}

	return add_query_arg( array(
		'order_fulfillment' => '1',
		'processed_count'   => count( $processed_ids ),
		'processed_ids'     => implode( ',', $processed_ids ),
	), $redirect_to );
}

/**
 * Easyparcel bulk actions order fulfillment admin notice
 * @return void
 */

function easyparcel_bulk_actions_order_fulfillment_admin_notice() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if ( empty( $_REQUEST['order_fulfillment'] ) ) {
		return;
	}
	$count   = intval( wp_unslash($_REQUEST['processed_count']) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$message = $count === 1 ? 'Processed 1 Order for fulfillment.' : "Processed $count Orders for fulfillment.";
	printf(
		'<div id="message" class="updated fade"><p>%s</p></div>',
		esc_html( $message )
	);
}


/**
 * Easyparcel bulk fulfillment popup
 *
 * @return void
 *
 */

function easyparcel_bulk_fulfillment_popup() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		exit( 'You are not allowed' );
	}
	check_ajax_referer( 'easyparcel_bulk_fulfillment_popup', 'security' );
	$order_ids        = isset( $_POST['order_ids'] ) ? wc_clean( $_POST['order_ids'] ) : '';
	$paid_order_ids   = array();
	$unpaid_order_ids = array();

	foreach ( $order_ids as $order_id ) {
		$order                  = wc_get_order( $order_id );
		$easyparcel_paid_status = ( $order->meta_exists( '_easyparcel_payment_status' ) ) ? 1 : 0; # 1 = Paid / 0 = Pending
		if ( $easyparcel_paid_status ) {
			$paid_order_ids[] = $order_id;
		} else {
			$unpaid_order_ids[] = $order_id;
		}
	}

	ob_start();
	if ( $unpaid_order_ids ) {
		$order_id = $order_number = implode( ',', $unpaid_order_ids );
		$post     = (object) array();
		$post->ID = $unpaid_order_ids[0];

		$default_provider = '';

		$api_detail                    = easyparcel_get_api_detail_bulk( $post );
		$shipment_providers_by_country = $api_detail->shipment_providers_list;
		$dropoff_point_list            = wp_json_encode( $api_detail->dropoff_point_list );
		ob_start();
		?>
        <div id="easyparcel_fulfillment_popout" class="fulfillment_popup_wrapper add_fulfillment_popup">
            <div class="fulfillment_popup_row">
                <div class="popup_header">
                    <h3 class="popup_title"><?php echo esc_html( 'Shipment Fulfillment -# '.$order_number ); ?></h3>
                    <span class="dashicons dashicons-no-alt popup_close_icon"></span>
                </div>
                <div class="popup_body">
                    <form id="add_fulfillment_form" method="POST" class="add_fulfillment_form">
                        <p class="form-field form-50">
                            <label for="shipping_provider"><?php echo esc_html( 'Courier Services:' ); ?></label>
                            <select class="chosen_select shipping_provider_dropdown" id="shipping_provider"
                                    name="shipping_provider">
                                <option value=""><?php echo esc_html( 'Select Preferred Courier Service' ); ?></option>
								<?php
								foreach ( $shipment_providers_by_country as $providers ) {
									$selected = ( $providers->provider_name == $default_provider ) ? 'selected' : '';
									echo '<option value="' . esc_attr( $providers->ts_slug ) . '" ' . esc_html( $selected ) . '>' . esc_html( $providers->provider_name ) . '</option>';
								}
								?>
                            </select>
                        </p>
						<?php
						#### drop off - S ####
						echo '<p class="form-field drop_off_field form-50"></p>';
						woocommerce_wp_hidden_input( array(
							'id'    => 'easyparcel_dropoff',
							'value' => $dropoff_point_list
						) );
						#### drop off - E ####
						?>
                        <p class="form-field date_shipped_field form-50">
                            <label for="date_shipped"><?php echo esc_html( 'Drop Off / Pick Up Date' ); ?></label>
                            <input type="text" class="ast-date-picker-field" name="date_shipped" id="date_shipped" value="<?php echo esc_html( date_i18n('Y-m-d', current_time( 'timestamp' ) ) ); ?>"
                                   placeholder="<?php echo esc_html( date_i18n('Y-m-d', time() ) ); ?>">
                        </p>
                        <hr>
                        <p>
                            <input type="hidden" name="action" value="add_shipment_fulfillment">
                            <input type="hidden" name="order_id" id="order_id" value="<?php echo esc_attr( $order_id ); ?>">
                            <input type="button" name="Submit" value="Fulfill Order" class="button-primary btn_green button-save-form">
                        </p>
                    </form>
                </div>
            </div>
            <div class="popupclose"></div>
        </div>
		<?php
		ob_end_flush();
	} else {
		echo "your selected shipment have been fulfill.";
	}
	exit;
}

/**
 * Easyparcel save bulk order ajax
 *
 * @return void
 *
 */
function easyparcel_save_bulk_order_ajax() {
	check_ajax_referer( 'easyparcel_bulk_fulfillment_popup', 'security', true );
	$shipping_provider = isset( $_POST['shipping_provider'] ) ? wc_clean( $_POST['shipping_provider'] ) : '';
	$courier_name      = isset( $_POST['courier_name'] ) ? wc_clean( $_POST['courier_name'] ) : '';
	$drop_off_point    = isset( $_POST['drop_off_point'] ) ? wc_clean( $_POST['drop_off_point'] ) : '';
	$pick_up_date      = isset( $_POST['pick_up_date'] ) ? wc_clean( $_POST['pick_up_date'] ) : '';
	$order_id          = isset( $_POST['order_id'] ) ? wc_clean( $_POST['order_id'] ) : '';

	### Bulk Order Part ###
	if ( ! class_exists( 'Easyparcel_Extend_Shipping_Method' ) ) {
		include_once 'easyparcel_shipping.php';
	}
	$Easyparcel_Extend_Shipping_Method = new Easyparcel_Extend_Shipping_Method();

	if ( $pick_up_date != '' && $shipping_provider != '' ) {
		$obj                    = (object) array();
		$obj->order_id          = $order_id;
		$obj->pick_up_date      = $pick_up_date;
		$obj->shipping_provider = $shipping_provider;
		$obj->courier_name      = $courier_name;
		$obj->drop_off_point    = $drop_off_point;
		$easyparcel_order               = $Easyparcel_Extend_Shipping_Method->process_bulk_booking_order( $obj );
		if ( ! empty( $easyparcel_order ) ) {
			print_r( $easyparcel_order );
		} else {
			echo 'success';
		}

	} else {
		echo 'Please fill all the required data.';
	}

	die();
}

/**
 * Easyparcel get api detail bulk
 *
 * @param $post
 *
 * @return object
 *
 */
function easyparcel_get_api_detail_bulk( $post ) {

	if ( ! class_exists( 'Easyparcel_Extend_Shipping_Method' ) ) {
		include_once 'easyparcel_shipping.php';
	}

	$Easyparcel_Extend_Shipping_Method = new Easyparcel_Extend_Shipping_Method();
	$rates                         = $Easyparcel_Extend_Shipping_Method->get_admin_shipping( $post );

	$obj                          = (object) array();
	$obj->shipment_providers_list = array();
	$obj->dropoff_point_list      = array();

	foreach ( $rates as $rate ) {
		$shipment_provider                = (object) array();
		$shipment_provider->ts_slug       = $rate['id'];
		$shipment_provider->provider_name = $rate['label'];
		$obj->shipment_providers_list[]   = $shipment_provider;

		$dropoff                   = array();
		$dropoff[ $rate['id'] ]    = $rate['dropoff_point'];
		$obj->dropoff_point_list[] = $dropoff;
	}

	return $obj;
}

/**
 * Easyparcel shop order columns
 *
 * @param $columns
 *
 * @return mixed
 *
 */
function easyparcel_shop_order_columns( $columns ) {
	$columns['easyparcel_order_list_shipment_tracking'] = __( 'Shipment Tracking', 'easyparcel-shipping-integration' );

	return $columns;
}

/**
 * Easyparcel render shop order columns
 *
 * @param $column
 *
 * @return void
 *
 */
function easyparcel_render_shop_order_columns( $column ) {
	global $post;
	if ( 'easyparcel_order_list_shipment_tracking' === $column ) {
		echo wp_kses_post( easyparcel_get_shipment_tracking_column( $post->ID ) );
	}
}

/**
 * Easyparcel destination columns
 *
 * @param $columns
 *
 * @return mixed
 *
 */
function easyparcel_destination_columns( $columns ) {
	$columns['easyparcel_order_list_destination'] = __( 'Destination', 'easyparcel-shipping-integration' );

	return $columns;
}

/**
 * Easyparcel render destination columns
 *
 * @param $column
 *
 * @return void
 *
 */
function easyparcel_render_destination_columns( $column ) {
	global $post, $the_order;
	if ( ! is_a( $the_order, 'WC_Order' ) ) {
		$the_order = wc_get_order( $post->ID );
	}

	if ( $column == 'easyparcel_order_list_destination' ) {
		$WC_Country = new WC_Countries();
		if ( strtolower( $WC_Country->get_base_country() ) !== strtolower( $the_order->get_shipping_country() ) ) {
			echo "International";
		} else {
			echo "Domestic";
		}
	}
}

/**
 * Easyparcel get shipment tracking column
 *
 * @param $order_id
 *
 * @return mixed|null
 *
 */
function easyparcel_get_shipment_tracking_column( $order_id ) {
	wp_enqueue_style( 'easyparcel_order_list_styles', plugin_dir_url( __FILE__ ) . '/css/admin.css', array(), EASYPARCEL_VERSION );
	wp_enqueue_script( 'easyparcel-admin-order-js', plugin_dir_url( __FILE__ ) . '/js/admin_order.js', array( 'jquery' ), EASYPARCEL_VERSION, true );
	wp_localize_script(
		'easyparcel-admin-order-js',
		'easyparcel_orders_params',
		array(
			'order_nonce' => wp_create_nonce( 'easyparcel_bulk_fulfillment_popup' ),
		)
	);
	ob_start();

	$order                  = wc_get_order( $order_id );
	$easyparcel_paid_status = ( $order->meta_exists( '_easyparcel_payment_status' ) ) ? 1 : 0;
	if ( $easyparcel_paid_status == 1 ) {
		$selected_courier = ( ! empty( $order->get_meta( '_easyparcel_selected_courier' ) ) ) ? $order->get_meta( '_easyparcel_selected_courier' ) : '-';
		$awb              = ( ! empty( $order->get_meta( '_easyparcel_awb' ) ) ) ? $order->get_meta( '_easyparcel_awb' ) : '-';
		$tracking_url     = ( ! empty( $order->get_meta( '_easyparcel_tracking_url' ) ) ) ? $order->get_meta( '_easyparcel_tracking_url' ) : '-';
		$awb_link         = ( ! empty( $order->get_meta( '_easyparcel_awb_id_link' ) ) ) ? $order->get_meta( '_easyparcel_awb_id_link' ) : '-';
		echo '<ul class="easyparcel_order_list_shipment_tracking">';
		printf(
			'<li>
                        <div><b>%s</b>
                        </div>
                        <a href="%s" target="_blank">%s</a>
                        <a href="%s" target="_blank">[Download AWB]</a>
                    </li>',
			esc_html( $selected_courier ),
			esc_url( $tracking_url ),
			esc_html( $awb ),
			esc_url( $awb_link )
		);
		echo '</ul>';
	} else {
		echo '–';
	}

	return apply_filters( 'easyparcel_get_shipment_tracking_column', ob_get_clean(), $order_id );
}

/**
 * Easyparcel destination columns sortable
 *
 * @param $columns
 *
 * @return array
 *
 */
function easyparcel_destination_columns_sortable( $columns ) {
	$meta_key = '_shipping_country';

	return wp_parse_args( array( 'easyparcel_order_list_destination' => $meta_key ), $columns );
}

/**
 * Easyparcel shop order column destination sortable order by
 *
 * @param $query
 *
 * @return void
 *
 */
function easyparcel_shop_order_column_destination_sortable_orderby( $query ) {
	global $pagenow;
    $post_type = filter_input(INPUT_GET, 'post_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
    $post_type = sanitize_text_field($post_type);
	if ( 'edit.php' === $pagenow && !empty($post_type) && 'shop_order' === $post_type ) {
		$orderby  = $query->get( 'orderby' );
		$meta_key = '_shipping_country';
		if ( '_shipping_country' === $orderby ) {
			$query->set( 'meta_key', $meta_key );
			$query->set( 'orderby', 'meta_value' );
		}
	}
}
//add_action('woocommerce_order_details_after_order_table')
