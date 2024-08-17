<?php
/**
 * Plugin Name: EasyParcel Shipping
 * Plugin URI: https://easyparcel.com/
 * Description: EasyParcel Shipping plugin allows you to enable order fulfillment without leaving your store and allow your customer to pick their preferable courier during check out. To get started, activate EasyParcel Shipping plugin and proceed to Woocommerce > Settings > Shipping > EasyParcel Shipping to set up your Integration ID.
 * Version: 2.0.6
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Author: EasyParcel
 * Author URI: https://www.easyparcel.com/
 * Text Domain: easyparcel-shipping
 * WC requires at least: 8.6.1
 * WC tested up to: 8.6.1
 *
 * License: GNU General Public License v3.0 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0-standalone.html
 *
 * EasyParcel Shipping is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * EasyParcel Shipping is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EasyParcel Shipping. If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'EASYPARCEL_VERSION', '2.8.13' );
define('EASYPARCEL_DB_VERSION', '2.8.13');
define( 'EASYPARCEL__FILE__', __FILE__ );
define( 'EASYPARCEL_PLUGIN_BASE', plugin_basename( EASYPARCEL__FILE__ ) );
define( 'EASYPARCEL_PATH', plugin_dir_path( EASYPARCEL__FILE__ ) );
define( 'EASYPARCEL_URL', plugins_url( '/', EASYPARCEL__FILE__ ) );
define( 'EASYPARCEL_INCLUDE_PATH', plugin_dir_path( EASYPARCEL__FILE__ ) . 'include/' );
define( 'EASYPARCEL_ASSETS_PATH', EASYPARCEL_PATH . 'assets/' );
define( 'EASYPARCEL_ASSETS_URL', EASYPARCEL_URL . 'assets/' );
/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	require_once EASYPARCEL_INCLUDE_PATH . 'easyparcel-enqueue-scripts.php';
	require_once EASYPARCEL_INCLUDE_PATH . 'easyparcel-ajax.php';
	require_once EASYPARCEL_INCLUDE_PATH . 'easyparcel-template-functions.php';
	if ( ! class_exists( 'Easyparcel_Integration' ) ):
		class Easyparcel_Integration {
			/**
			 * Construct the plugin.
			 */
			public function __construct() {
				include_once 'include/easyparcel_meta_box.php';
				include_once 'include/easyparcel_pickup_point.php';
				include_once 'include/easyparcel_bulk_fulfillment.php';
				// add_action('woocommerce_email_after_order_table', 'easyparcel_field_after_order_table', 10, 1);
				add_action( 'woocommerce_shipping_init', array( $this, 'init' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(
					$this,
					'plugin_action_links'
				) );
				add_action( 'admin_menu', 'easyparcel_register_menu_page' );
			}

			/**
			 * Initialize the plugin.
			 */
			public function init() {
				// start a session

				// Checks if WooCommerce is installed.
				add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );
				if ( class_exists( 'WC_Integration' ) ) {
					// Include our integration class.
					include_once 'include/easyparcel_shipping.php';
					include_once 'include/easyparcel_extend_shipping_zone.php'; //shipping zone

					$value = get_option( 'easyparcel_settings' );
					if ( isset( $value['enabled'] ) && $value['enabled'] == 'yes' ) {
						if ( ! class_exists( 'Easyparcel_Shipping_API' ) ) {
							include_once 'include/easyparcel_api.php';
						}
						Easyparcel_Shipping_API::init();
						$auth = Easyparcel_Shipping_API::auth();
						if ( $auth == 'Success.' ) {
							add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_zone_method' ) );
						} else {
							remove_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_zone_method' ) );
						}
					} else {
						add_action( 'admin_notices', function () {
							echo wp_kses_post( '<div id="message" class="notice notice-error is-dismissible"><p>' . esc_html( 'Kindly setup and activate your EasyParcel Shipping' ) . '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=easyparcel' ) ) . '">' . esc_html( 'Here' ) . '</a></p>' . '</div>' );
						} );
						remove_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_zone_method' ) );
					}
				}
				// add the filter - Custom shipping method label
				add_filter( 'woocommerce_cart_shipping_method_full_label', array(
					$this,
					'filter_cart_shipping_method_full_label'
				), 10, 2 );


				// Order Part
				// add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed_integration' ), 10 , 3);
			}

			/**
			 * Easyparcel filter woocommerce cart shipping method full label
			 *
			 * @param $label
			 * @param $method
			 *
			 * @return mixed|string
			 *
			 */

			public function filter_cart_shipping_method_full_label( $label, $method ) {
				$new_label = '';

				switch ( $method->get_method_id() ) {
					case 'easyparcel':
						$new_label = '<img src="' . esc_url( $method->meta_data['easyparcel_courier_logo'] ) . '" width="60" height="40" style="display:inline-block;border-radius:4px;border:1px solid #ccc;"> ' . $label;
						// pickup point list
						$new_label .= $this->easyparcel_pickup_point_shipping( $method, 0 );
						break;
					default:
						$new_label = $label;
				}

				return $new_label;
			}

			/**
			 * Easyparcel pickup point shipping
			 *
			 * @param $method
			 * @param $index
			 *
			 * @return string
			 *
			 */
			public function easyparcel_pickup_point_shipping( $method, $index ) {
				$pickup_available_methods = array();
				if ( isset( WC()->session ) ) {
					$pickup_available_methods = WC()->session->get( "EasyParcel_Pickup_Available" ) ? WC()->session->get( "EasyParcel_Pickup_Available" ) : array();
				}

				$field_id = "easyparcel_pickup_point";

				$chosen  = WC()->session->get( 'chosen_shipping_methods' ); // The chosen methods
				$value   = WC()->session->get( $field_id );
				$value   = WC()->session->__isset( $field_id ) ? $value : WC()->checkout->get_value( '_' . $field_id );
				$options = array(); // Initializing
				$html    = '';

				if ( ! empty( $chosen ) && $method->id === $chosen[ $index ] && in_array( $method->id, $pickup_available_methods ) ) {
					$field_options = WC()->session->get( 'EasyParcel_Pickup_' . $method->id );
					$html          .= '<div class="custom-easyparcel_pickup_point">';
					// Loop through field otions to add the correct keys
					$html .= '<select name="easyparcel_pickup_point" id="easyparcel_pickup_point" class="select">';
					foreach ( $field_options as $key => $option_value ) {
						$option_key             = ( $key === 0 ) ? '' : $key;
						$options[ $option_key ] = $option_value;
						$selected               = ( $option_key == $value ) ? 'selected' : '';
						$html                   .= '<option value="' . esc_attr( $option_key ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $option_value ) . '</option>';
					}
					$html .= '</select>';

					$html .= '</div>';
				}

				return $html;
			}

			/**
			 * Easyparcel order status changed integration
			 *
			 * @param $order_id
			 * @param $pre_status
			 * @param $next_status
			 *
			 * @return void
			 *
			 */
			public function order_status_changed_integration( $order_id, $pre_status, $next_status ) {
				$data           = (object) array();
				$data->order_id = $order_id;
				try {
					$Easyparcel_Extend_Shipping_Method = new Easyparcel_Extend_Shipping_Method();
					$Easyparcel_Extend_Shipping_Method->process_booking_order( $data );

				} catch ( Exception $e ) {
					$message = sprintf( __( 'Easyparcel status changed! Error: %s', 'easyparcel-shipping' ), $e->getMessage() );
					wc_add_notice( $message, "error" );
				}

			}

            /**
             * For add in setting tab for easy access to plugin
             * @param $settings_tabs
             * @return mixed
             */
			public static function add_settings_tab( $settings_tabs ) {
				$settings_tabs['shipping&section=easyparcel'] = __( 'EasyParcel', 'easyparcel-shipping' );

				return $settings_tabs;
			}

			/**
			 * Easyparcel add shipping method
			 *
			 * @param $methods
			 *
			 * @return mixed
			 *
			 */
			public function add_shipping_method( $methods ) {
				if ( is_array( $methods ) ) {
					$methods['easyparcel'] = 'Easyparcel_Extend_Shipping_Method';
				}

				return $methods;
			}

			/**
			 * Easyparcel add shipping zone method
			 *
			 * @param $methods
			 *
			 * @return mixed
			 *
			 */

			public function add_shipping_zone_method( $methods ) {
				if ( is_array( $methods ) ) {
					$methods['easyparcel_zone'] = 'Easyparcel_Extend_Shipping_Zone';
				}

				return $methods;
			}
			/**
			 *  Add Settings link to plugin page
			 */
			public function plugin_action_links( $links ) {
				return array_merge(
					$links,
					array( '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=shipping&section=easyparcel' ) . '"> ' . esc_html( 'Settings') . '</a>' )
				);
			}
		}

		$Easyparcel_Integration = new Easyparcel_Integration( __FILE__ );
	endif;

}

/**
 * Easyparcel register menu page
 *
 * @return void
 *
 */
function easyparcel_register_menu_page() {
	add_menu_page(
		'easyparcel_shipping',
		'EasyParcel Shipping',
		'manage_options',
		'admin.php?page=wc-settings&tab=shipping&section=easyparcel',
		'',
		'dashicons-store',
		66
	);
	add_submenu_page( 'admin.php?page=wc-settings&tab=shipping&section=easyparcel', 'EasyParcel Courier Setting', 'EasyParcel Courier Setting', 'manage_options', 'admin.php?page=wc-settings&tab=shipping&section=easyparcel_shipping' );

}

/**
 * Activate the plugin.
 */
function easyparcel_plugin_activate() {
	require_once EASYPARCEL_PATH . 'database/create.php';
	easyparcel_create_zones_courier_table();
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'easyparcel_plugin_activate' );
/**
 * Deactivation hook.
 */
function easyparcel_plugin_deactivate() {
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'easyparcel_plugin_deactivate' );

