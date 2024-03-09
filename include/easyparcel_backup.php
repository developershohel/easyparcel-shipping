<?php
/*
wordpress_data\wp-content\plugins\woocommerce\includes\admin\settings\views\html-admin-page-shipping-zones.php
wordpress_data\wp-content\plugins\woocommerce\includes\admin\settings\class-wc-settings-shipping.php
wordpress_data\wp-content\plugins\woocommerce\includes\class-wc-ajax.php
wordpress_data\wp-content\plugins\woocommerce\includes\class-wc-install.php
wordpress_data\wp-content\plugins\woocommerce\includes\class-wc-shipping-zone.php
wordpress_data\wp-content\plugins\woocommerce\includes\wc-core-functions.php
wordpress_data\wp-content\plugins\woocommerce\includes\wc-update-functions.php
wordpress_data\wp-content\plugins\woocommerce\includes\class-wc-shipping-zones.php
wordpress_data\wp-content\plugins\woocommerce\assets\js\admin\wc-shipping-zone-methods.js
 */
// echo "easyparcel_shipping ----------------------------------------------------------";
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Check if WooCommerce is active
 */

if ( ! class_exists( 'WC_Easyparcel_Backup' ) ) {
	class WC_Easyparcel_Backup extends WC_Shipping_Method {
		/**
		 * Constructor for your shipping class
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			$this->id                 = 'easyparcel_backup'; // ID for your shipping method. Should be unique.
			$this->method_title       = __( 'Easyparcel Backup & Restore' ); // Title shown in admin
			$this->method_description = __( 'A shipping zone is a geographic region where a certain set of shipping methods are offered. WooCommerce will match a customer to a single zone using their shipping address and present the shipping methods within that zone to them.' ); // Description shown in admin
			$this->title              = "Easyparcel Backup & Restore"; // This can be added as an setting but for this example its forced.
			$this->init();
		}

		/**
		 * Init your settings
		 *
		 * @access public
		 * @return void
		 */
		function init() {
		}

		/**
		 * Output the shipping settings screen. Overwrite original
		 * handle for easyparcel_shipping main and sub pages
		 */
		public function admin_options() {
			//check if default shipping zone exist easyparcel shipping method
			global $current_section, $hide_save_button, $wpdb;
			$hide_save_button = true;
			$this->load_zone_list();
		}

		public function load_zone_list() {
			include_once dirname( __FILE__ ) . '/views/html_easyparcel_backup.php';
		}

	}

}