<?php
/**
 * Plugin Name: EasyParcel Shipping
 * Plugin URI: https://easyparcel.com/
 * Description: EasyParcel Shipping plugin allows you to enable order fulfillment without leaving your store and allow your customer to pick their preferable courier during check out. To get started, activate EasyParcel Shipping plugin and proceed to Woocommerce > Settings > Shipping > EasyParcel Shipping to set up your Integration ID. ⚠️ Notice ~ if facing any fulfilment duplication please update to version 1.0.22 or later to avoid this issue. Note: This plugin collects store information (email, phone number, store name, URL, and country) to provide shipping services.
 * Version: 1.0.32
 * Author: EasyParcel
 * Author URI: https://www.easyparcel.com/
 * Text Domain: easyparcel-shipping
 * WC requires at least: 4
 * WC tested up to: 8.3
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
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
**/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
global $jal_db_version;
$jal_db_version = '1.0';

define( 'EASYPARCEL_VERSION', '1.0.32' );

define( 'EASYPARCEL__FILE__'            ,  __FILE__  );
define( 'EASYPARCEL_PLUGIN_BASE'        , plugin_basename( __FILE__ ) );
define( 'EASYPARCEL_PATH'               , plugin_dir_path( __FILE__ ) );
define( 'EASYPARCEL_URL'                , plugins_url( '/', __FILE__ ) );

define( 'EASYPARCEL_SERVICE_PATH'       , EASYPARCEL_PATH . 'include/service/' );
define( 'EASYPARCEL_DATASTORE_PATH'     , EASYPARCEL_PATH . 'include/data_store/' );

define( 'EASYPARCEL_MODULE_PATH'        , EASYPARCEL_PATH . 'include/module/' );
define( 'EASYPARCEL_MODULE_URL'         , EASYPARCEL_URL . 'include/module/' );

// to use is_plugin_active_for_network(), need import this
if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
}

$woo_activated_single = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
$woo_activated_multi = is_plugin_active_for_network("woocommerce/woocommerce.php"); // network activated case


if($woo_activated_single || $woo_activated_multi) {
    include_once EASYPARCEL_PATH . 'database/create.php';

    // Register activation hook to log store info only once
    register_activation_hook( __FILE__, 'easyparcel_activation_log' );
    
    function easyparcel_activation_log() {
        // Schedule a one-time action to log after WooCommerce is loaded
        if ( ! wp_next_scheduled( 'easyparcel_log_store_info_once' ) ) {
            wp_schedule_single_event( time(), 'easyparcel_log_store_info_once' );
        }
    }
    
    // Hook the actual logging function
    add_action( 'easyparcel_log_store_info_once', 'easyparcel_do_log_store_info' );
    
    function easyparcel_do_log_store_info() {

            // Retrieve WooCommerce store information
            $store_email = get_option( 'woocommerce_email_from_address' );
            $store_url = get_site_url();
            $store_country = get_option( 'woocommerce_default_country' );
            $store_name = get_bloginfo( 'name' );
            $country_code = explode(':', $store_country)[0];

            if ( empty( $store_email ) ) {
                $store_email = get_option( 'admin_email' );
            }

            $api_data = array(
                'store_name'    => $store_name,
                'email'   => $store_email,
                'url'     => $store_url,
                'phone_no' => "-",
                'country' => strtolower($country_code),
                'platform' => "woocommerce",
                'action_type'  => '1'
            );

            $api_url = 'https://api.easyparcel.com/open_api/integration_installation_tracker';
            
            wp_remote_post( $api_url, array(
                'method'      => 'POST',
                'timeout'     => 15,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => array(
                    'Content-Type' => 'application/json',
                ),
                'body'        => json_encode( $api_data ),
                'cookies'     => array()
            ));
    }

    if (!class_exists('EP_EasyParcel')){
        include_once EASYPARCEL_PATH . 'include/EP_EasyParcel.php';
    }
    $easyparcel = new EP_EasyParcel(); 
}

?>