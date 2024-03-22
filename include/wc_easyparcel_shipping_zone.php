<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Check if WooCommerce is active
 */

if ( ! class_exists( 'WC_Easyparcel_Shipping_Zone' ) ) {
	class WC_Easyparcel_Shipping_Zone extends WC_Shipping_Method {
		/**
		 * Constructor for your shipping class
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			$this->id                 = 'easyparcel_shipping'; // Id for your shipping method. Should be unique.
			$this->method_title       = __( 'EasyParcel Courier Setting' ); // Title shown in admin
			$this->method_description = __( 'A shipping zone is a geographic region where a certain set of shipping methods are offered. WooCommerce will match a customer to a single zone using their shipping address and present the shipping methods within that zone to them.' ); // Description shown in admin
			$this->title              = "EasyParcel Courier Setting"; // This can be added as an setting but for this example its forced.
			// $this->plugin_url = admin_url() . '/admin.php?page=wc-settings&tab=shipping&section=easyparcel';
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
			$result = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = 'easyparcel'" );
			if ( empty( $result ) ) {
				$hide_save_button = true;
				echo '<h4><font color="red">Important**</font><br>' . esc_html( 'You will need to setup EasyParcel Shipping first' ) . '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section' ) ) . '" style="margin: 0 10px;">' . esc_html( 'HERE' ) . '</a>' . esc_html( 'before proceeding to EasyParcel Courier Setting.' );

				return;
			}


			if ( 'easyparcel_shipping' === $current_section ) {
				//echo $_GET['zone_id'].'$_REQUEST[courier_id]'.$_REQUEST['courier_id'];
				if ( ! isset( $_GET['zone_id'] ) && ! isset( $_REQUEST['courier_id'] ) ) {
					$this->load_zone_list();
					$hide_save_button = true;
				} else if ( isset( $_REQUEST['zone_id'] ) && ! isset( $_REQUEST['courier_id'] ) && isset( $_REQUEST['perform'] ) ) {
					$this->setup_courier_page( absint( wp_unslash( $_REQUEST['zone_id'] ) ) );
					$hide_save_button = true;
				} else if ( isset( $_REQUEST['zone_id'] ) && ! isset( $_REQUEST['courier_id'] ) ) {
					$this->setup_zone( wc_clean( wp_unslash( $_REQUEST['zone_id'] ) ) );
					$hide_save_button = true;
				} elseif ( isset( $_REQUEST['courier_id'] ) ) {
					$this->edit_courier_panel( absint( wp_unslash( $_REQUEST['courier_id'] ) ) );
					$hide_save_button = true;
				}
			}
		}

		/**
		 * Easyparcel load zone list
		 *
		 * @return void
		 */
		public function load_zone_list() {
			if ( ! class_exists( 'Easyparcel_Shipping_Zones' ) ) {
				// Include Easyparcel API
				include_once 'easyparcel_shipping_zones.php';
			}

			wp_register_script( 'easyparcel_admin_shipping_zone', plugin_dir_url( __FILE__ ) . 'js/admin_shipping_zone.js', array(
				'jquery',
				'wp-util',
				'underscore',
				'backbone',
				'jquery-ui-sortable',
				'wc-enhanced-select',
				'wc-backbone-modal'
			), '1.0', true );
			wp_localize_script(
				'easyparcel_admin_shipping_zone',
				'shippingZonesLocalizeScript',
				array(
					'zones'                   => Easyparcel_Shipping_Zones::get_zones( 'json' ),
					'default_zone'            => array(
						'zone_id'    => 0,
						'zone_name'  => '',
						'zone_order' => null,
					),
					'wc_shipping_zones_nonce' => wp_create_nonce( 'wc_shipping_zones_nonce' ),
					'strings'                 => array(
						'unload_confirmation_msg'     => __( 'Your changed data will be lost if you leave this page without saving.', 'easyparcel_zone' ),
						'delete_confirmation_msg'     => __( 'Are you sure you want to delete this zone? This action cannot be undone.', 'easyparcel_zone' ),
						'save_failed'                 => __( 'Your changes were not saved. Please retry.', 'easyparcel_zone' ),
						'no_shipping_methods_offered' => __( 'No shipping methods offered to this zone.', 'easyparcel_zone' ),
						'no_courier_applied'          => __( 'No courier applied to this zone.', 'easyparcel_zone' ),
					),
				)
			);
			wp_enqueue_script( 'easyparcel_admin_shipping_zone' );

			include_once dirname( __FILE__ ) . '/views/html_easyparcel_shipping_zones.php';
		}

		protected function get_region_options( $allowed_countries, $shipping_continents ) {
			$options = array();
			foreach ( $shipping_continents as $continent_code => $continent ) {
				$continent_data = array(
					'value'    => 'continent:' . esc_attr( $continent_code ),
					'label'    => esc_html( $continent['name'] ),
					'children' => array(),
				);

				$countries = array_intersect( array_keys( $allowed_countries ), $continent['countries'] );

				foreach ( $countries as $country_code ) {
					$country_data = array(
						'value'    => 'country:' . esc_attr( $country_code ),
						'label'    => esc_html( $allowed_countries[ $country_code ] ),
						'children' => array(),
					);

					$states = WC()->countries->get_states( $country_code );

					if ( $states ) {
						foreach ( $states as $state_code => $state_name ) {
							$country_data['children'][] = array(
								'value' => 'state:' . esc_attr( $country_code . ':' . $state_code ),
								'label' => esc_html( $state_name . ', ' . $allowed_countries[ $country_code ] ),
							);
						}
					}
					$continent_data['children'][] = $country_data;
				}
				$options[] = $continent_data;
			}

			return $options;
		}

		/**
		 * Easyparcel setup zone
		 *
		 * @param $zone_id
		 *
		 * @return void
		 *
		 */

		public function setup_zone( $zone_id ) {
			if ( 'new' === $zone_id ) {
				if ( ! class_exists( 'Easyparcel_Shipping_Zone' ) ) {
					include_once 'easyparcel_shipping_zone.php';
				}
				$zone = new Easyparcel_Shipping_Zone();
			} else {
				if ( ! class_exists( 'Easyparcel_Shipping_Zones' ) ) {
					include_once 'easyparcel_shipping_zones.php';
				}
				$zone = Easyparcel_Shipping_Zones::get_zone( absint( $zone_id ) );
			}

			if ( ! $zone ) {
				wp_die( esc_html__( 'Zone does not exist!', 'woocommerce' ) );
			}
			$allowed_countries   = WC()->countries->get_shipping_countries();
			$shipping_continents = WC()->countries->get_shipping_continents();

			// Prepare locations.
			$locations = array();
			$postcodes = array();

			foreach ( $zone->get_zone_locations() as $location ) {
				if ( 'postcode' === $location->type ) {
					$postcodes[] = $location->code;
				} else {
					$locations[] = $location->type . ':' . $location->code;
				}
			}
			$add_btn_disabled = false;
			$couriers         = $zone->get_couriers();
			foreach ( $couriers as $k => &$v ) {
				if ( ! empty( $v['courier_dropoff_point'] ) ) {
					$v['courier_display_name'] .= ' (DropOff Point)';
				}
				if ( $v['service_id'] == 'all' || $v['service_id'] == 'cheapest' ) {
					$add_btn_disabled = true;
				}
			}
			wp_register_script( 'easyparcel_admin_shipping_zone_methods', plugin_dir_url( __FILE__ ) . 'js/admin_shipping_zone_methods.js', array(
				'jquery',
				'wp-util',
				'underscore',
				'backbone',
				'jquery-ui-sortable',
				'wc-backbone-modal'
			), EASYPARCEL_VERSION, true );
			wp_localize_script(
				'easyparcel_admin_shipping_zone_methods',
				'shippingZoneMethodsLocalizeScript',
				array(
					'methods'                 => $couriers,
					'zone_name'               => $zone->get_zone_name(),
					'zone_id'                 => $zone->get_id(),
					'wc_shipping_zones_nonce' => wp_create_nonce( 'wc_shipping_zones_nonce' ),
					'add_courier_option'      => $add_btn_disabled,
					'strings'                 => array(
						'unload_confirmation_msg' => __( 'Your changed data will be lost if you leave this page without saving.', 'easyparcel-shipping' ),
						'save_changes_prompt'     => __( 'Do you wish to save your changes first? Your changed data will be discarded if you choose to cancel.', 'easyparcel-shipping' ),
						'save_failed'             => __( 'Your changes were not saved. Please retry.', 'easyparcel-shipping' ),
						'add_method_failed'       => __( 'Shipping method could not be added. Please retry.', 'easyparcel-shipping' ),
						'no_location_detected'    => __( 'Kindly save your location before proceed to add in courier service', 'easyparcel-shipping' ),
						'yes'                     => __( 'Yes', 'easyparcel-shipping' ),
						'no'                      => __( 'No', 'easyparcel-shipping' ),
						'default_zone_name'       => __( 'Zone', 'easyparcel-shipping' ),
					),
				)
			);
			wp_enqueue_script( 'easyparcel_admin_shipping_zone_methods' );
			include_once dirname( __FILE__ ) . '/views/html_easyparcel_shipping_zone_methods.php';
		}

		/**
		 * Easyparcel edit courier panel
		 *
		 * @param $courier_id
		 */
		public function edit_courier_panel( $courier_id ) {
			global $wpdb;
			$courier  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id =%s", $courier_id ) );
			$couriers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id =%s", $courier_id ), ARRAY_A );
			$zone_id  = $wpdb->get_var( $wpdb->prepare( "SELECT zone_id FROM {$wpdb->prefix}easyparcel_zones_courier WHERE id=%s", $courier_id ) );
			if ( ! $courier ) {
				wp_die( esc_html__( 'Courier does not exist!', 'woocommerce' ) );
			} else {
				$courier = $courier[0];
			}
			if ( ! class_exists( 'Easyparcel_Shipping_Zones' ) ) {
				include_once 'easyparcel_shipping_zones.php';
			}
			$zone = Easyparcel_Shipping_Zones::get_zone( absint( $zone_id ) );

			if ( ! $zone ) {
				wp_die( esc_html__( 'Zone does not exist!', 'woocommerce' ) );
			}

			// decide what to use
			$courier_list = array();
			$courier_set  = $zone->get_couriers();
			$new_list     = self::filteringRegionRate( $zone, true );
			if ( ! empty( $courier_set ) ) {
				foreach ( $courier_set as $k => $v ) {
					if ( strtolower( $v['service_id'] ) == 'all' ) {
						$courier_list[] = array(
							'service_name'        => 'All Couriers',
							'courier_id'          => 'all',
							'courier_name'        => 'All Couriers',
							'courier_logo'        => '',
							'courier_info'        => 'all',
							'service_id'          => 'all',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( strtolower( $v['service_id'] ) == 'cheapest' ) {
						$courier_list[] = array(
							'service_name'        => 'Cheapest Courier',
							'courier_id'          => 'cheapest',
							'courier_name'        => 'Cheapest Courier',
							'courier_logo'        => '',
							'courier_info'        => 'cheapest',
							'service_id'          => 'cheapest',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( isset( $new_list[ $v['service_id'] ] ) ) {
						unset( $new_list[ $v['service_id'] ] );
					}
				}
			}
			if ( empty( $courier_set ) ) {
				$courier_list[] = array(
					'service_name'        => 'All Couriers',
					'courier_id'          => 'all',
					'courier_name'        => 'All Couriers',
					'courier_logo'        => '',
					'courier_info'        => 'all',
					'service_id'          => 'all',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
				$courier_list[] = array(
					'service_name'        => 'Cheapest Courier',
					'courier_id'          => 'cheapest',
					'courier_name'        => 'Cheapest Courier',
					'courier_logo'        => '',
					'courier_info'        => 'cheapest',
					'service_id'          => 'cheapest',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
			}
			$courier_list   = array_merge( $courier_list, $new_list );
			$charges        = self::chargesOption();
			$freeshippingby = self::freeShippingByOption();
			$addonCharges   = self::addonChargesOption();
			$charges        = self::chargesOption( $courier->charges );
			$freeshippingby = self::freeShippingByOption( $courier->free_shipping_by );
			include_once dirname( __FILE__ ) . '/views/html_easyparcel_setup_courier_edit.php';
		}

		/**
		 * Easyparcel setup courier page
		 *
		 * @param $zone_id
		 *
		 * @return void
		 *
		 */
		public function setup_courier_page( $zone_id ) {
			global $wpdb;
			if ( ! class_exists( 'Easyparcel_Shipping_Zones' ) ) {
				include_once 'easyparcel_shipping_zones.php';
			}
			$zone = Easyparcel_Shipping_Zones::get_zone( absint( $zone_id ) );

			if ( ! $zone ) {
				wp_die( esc_html__( 'Zone does not exist!', 'woocommerce' ) );
			}

			// decide what to use
			$courier_list = array();
			$courier_set  = $zone->get_couriers();
			$new_list     = self::filteringRegionRate( $zone, true );
			if ( ! empty( $courier_set ) ) {
				foreach ( $courier_set as $k => $v ) {
					if ( strtolower( $v['service_id'] ) == 'all' ) {
						$courier_list[] = array(
							'service_name'        => 'All Couriers',
							'courier_id'          => 'all',
							'courier_name'        => 'All Couriers',
							'courier_logo'        => '',
							'courier_info'        => 'all',
							'service_id'          => 'all',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( strtolower( $v['service_id'] ) == 'cheapest' ) {
						$courier_list[] = array(
							'service_name'        => 'Cheapest Courier',
							'courier_id'          => 'cheapest',
							'courier_name'        => 'Cheapest Courier',
							'courier_logo'        => '',
							'courier_info'        => 'cheapest',
							'service_id'          => 'cheapest',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( isset( $new_list[ $v['service_id'] ] ) ) {
						unset( $new_list[ $v['service_id'] ] );
					}
				}
			}
			if ( empty( $courier_set ) ) {
				$courier_list[] = array(
					'service_name'        => 'All Couriers',
					'courier_id'          => 'all',
					'courier_name'        => 'All Couriers',
					'courier_logo'        => '',
					'courier_info'        => 'all',
					'service_id'          => 'all',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
				$courier_list[] = array(
					'service_name'        => 'Cheapest Courier',
					'courier_id'          => 'cheapest',
					'courier_name'        => 'Cheapest Courier',
					'courier_logo'        => '',
					'courier_info'        => 'cheapest',
					'service_id'          => 'cheapest',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
			}
			$courier_list   = array_merge( $courier_list, $new_list );
			$charges        = self::chargesOption();
			$freeshippingby = self::freeShippingByOption();
			$addonCharges   = self::addonChargesOption();
			$table          = $wpdb->prefix . 'easyparcel_zones_courier';
			$couriers       = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE zone_id=%s", $zone_id ), ARRAY_A );
			include_once dirname( __FILE__ ) . '/views/html_easyparcel_setup_courier.php';
		}

		/**
		 * Easyparcel setup courier content
		 *
		 * @param $zone_id
		 * @param $instance_id
		 *
		 * @return void
		 *
		 */
		public function setup_courier_content( $zone_id, $instance_id ) {
			global $wpdb;
			if ( ! class_exists( 'Easyparcel_Shipping_Zones' ) ) {
				include_once 'easyparcel_shipping_zones.php';
			}
			$zone = Easyparcel_Shipping_Zones::get_zone( absint( $zone_id ) );

			if ( ! $zone ) {
				wp_die( esc_html__( 'Zone does not exist!', 'woocommerce' ) );
			}

			// decide what to use
			$courier_list = array();
			$courier_set  = $zone->get_couriers();
			$new_list     = self::filteringRegionRate( $zone, true );
			if ( ! empty( $courier_set ) ) {
				foreach ( $courier_set as $k => $v ) {
					if ( strtolower( $v['service_id'] ) == 'all' ) {
						$courier_list[] = array(
							'service_name'        => 'All Couriers',
							'courier_id'          => 'all',
							'courier_name'        => 'All Couriers',
							'courier_logo'        => '',
							'courier_info'        => 'all',
							'service_id'          => 'all',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( strtolower( $v['service_id'] ) == 'cheapest' ) {
						$courier_list[] = array(
							'service_name'        => 'Cheapest Courier',
							'courier_id'          => 'cheapest',
							'courier_name'        => 'Cheapest Courier',
							'courier_logo'        => '',
							'courier_info'        => 'cheapest',
							'service_id'          => 'cheapest',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( isset( $new_list[ $v['service_id'] ] ) ) {
						unset( $new_list[ $v['service_id'] ] );
					}
				}
			}
			if ( empty( $courier_set ) ) {
				$courier_list[] = array(
					'service_name'        => 'All Couriers',
					'courier_id'          => 'all',
					'courier_name'        => 'All Couriers',
					'courier_logo'        => '',
					'courier_info'        => 'all',
					'service_id'          => 'all',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
				$courier_list[] = array(
					'service_name'        => 'Cheapest Courier',
					'courier_id'          => 'cheapest',
					'courier_name'        => 'Cheapest Courier',
					'courier_logo'        => '',
					'courier_info'        => 'cheapest',
					'service_id'          => 'cheapest',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
			}
			$courier_list   = array_merge( $courier_list, $new_list );
			$charges        = self::chargesOption();
			$freeshippingby = self::freeShippingByOption();
			$addonCharges   = self::addonChargesOption();
			$table          = $wpdb->prefix . 'easyparcel_zones_courier';
			$couriers       = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE zone_id=%s AND instance_id=%s", $zone_id, $instance_id ), ARRAY_A );
			include_once dirname( __FILE__ ) . '/views/html_easyparcel_setup_courier_content.php';
		}

		/**
		 * Easyparcel add new courier
		 *
		 * @param $zone_id
		 *
		 * @return void
		 *
		 */

		public function add_new_courier( $zone_id ) {
			global $wpdb;
			if ( ! class_exists( 'Easyparcel_Shipping_Zones' ) ) {
				include_once 'easyparcel_shipping_zones.php';
			}
			$zone = Easyparcel_Shipping_Zones::get_zone( absint( $zone_id ) );

			if ( ! $zone ) {
				wp_die( esc_html__( 'Zone does not exist!', 'woocommerce' ) );
			}

			// decide what to use
			$courier_list = array();
			$courier_set  = $zone->get_couriers();
			$new_list     = self::filteringRegionRate( $zone, true );
			if ( ! empty( $courier_set ) ) {
				foreach ( $courier_set as $k => $v ) {
					if ( strtolower( $v['service_id'] ) == 'all' ) {
						$courier_list[] = array(
							'service_name'        => 'All Couriers',
							'courier_id'          => 'all',
							'courier_name'        => 'All Couriers',
							'courier_logo'        => '',
							'courier_info'        => 'all',
							'service_id'          => 'all',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( strtolower( $v['service_id'] ) == 'cheapest' ) {
						$courier_list[] = array(
							'service_name'        => 'Cheapest Courier',
							'courier_id'          => 'cheapest',
							'courier_name'        => 'Cheapest Courier',
							'courier_logo'        => '',
							'courier_info'        => 'cheapest',
							'service_id'          => 'cheapest',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( isset( $new_list[ $v['service_id'] ] ) ) {
						unset( $new_list[ $v['service_id'] ] );
					}
				}
			}
			if ( empty( $courier_set ) ) {
				$courier_list[] = array(
					'service_name'        => 'All Couriers',
					'courier_id'          => 'all',
					'courier_name'        => 'All Couriers',
					'courier_logo'        => '',
					'courier_info'        => 'all',
					'service_id'          => 'all',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
				$courier_list[] = array(
					'service_name'        => 'Cheapest Courier',
					'courier_id'          => 'cheapest',
					'courier_name'        => 'Cheapest Courier',
					'courier_logo'        => '',
					'courier_info'        => 'cheapest',
					'service_id'          => 'cheapest',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
			}
			$courier_list   = array_merge( $courier_list, $new_list );
			$charges        = self::chargesOption();
			$freeshippingby = self::freeShippingByOption();
			$addonCharges   = self::addonChargesOption();
			$table          = $wpdb->prefix . 'easyparcel_zones_courier';
			$couriers       = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE zone_id=%s", $zone_id ), ARRAY_A );
			include_once dirname( __FILE__ ) . '/views/html_easyparcel_add_courier.php';
		}

		/**
		 * Easyparcel get courier list
		 *
		 * @param $zone_id
		 *
		 * @return array
		 *
		 */
		public function get_courier_list( $zone_id ) {
			global $wpdb;
			if ( ! class_exists( 'Easyparcel_Shipping_Zones' ) ) {
				include_once 'easyparcel_shipping_zones.php';
			}
			$zone = Easyparcel_Shipping_Zones::get_zone( absint( $zone_id ) );
			if ( ! $zone ) {
				wp_die( esc_html__( 'Zone does not exist!', 'woocommerce' ) );
			}

			// decide what to use
			$courier_list = array();
			$courier_set  = $zone->get_couriers();
			$new_list     = self::filteringRegionRate( $zone, true );

			if ( ! empty( $courier_set ) ) {
				foreach ( $courier_set as $k => $v ) {
					if ( strtolower( $v['service_id'] ) == 'all' ) {
						$courier_list[] = array(
							'service_name'        => 'All Couriers',
							'courier_id'          => 'all',
							'courier_name'        => 'All Couriers',
							'courier_logo'        => '',
							'courier_info'        => 'all',
							'service_id'          => 'all',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( strtolower( $v['service_id'] ) == 'cheapest' ) {
						$courier_list[] = array(
							'service_name'        => 'Cheapest Courier',
							'courier_id'          => 'cheapest',
							'courier_name'        => 'Cheapest Courier',
							'courier_logo'        => '',
							'courier_info'        => 'cheapest',
							'service_id'          => 'cheapest',
							'sample_cost'         => '0.00',
							'sample_cost_display' => '',
							'service_type'        => 'parcel',
							'delivery'            => 'all',
							'price'               => 0,
							'addon_price'         => 0,
							'shipment_price'      => 0,
						);
					} else if ( isset( $new_list[ $v['service_id'] ] ) ) {
						unset( $new_list[ $v['service_id'] ] );
					}
				}
			}
			if ( empty( $courier_set ) ) {
				$courier_list[] = array(
					'service_name'        => 'All Couriers',
					'courier_id'          => 'all',
					'courier_name'        => 'All Couriers',
					'courier_logo'        => '',
					'courier_info'        => 'all',
					'service_id'          => 'all',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
				$courier_list[] = array(
					'service_name'        => 'Cheapest Courier',
					'courier_id'          => 'cheapest',
					'courier_name'        => 'Cheapest Courier',
					'courier_logo'        => '',
					'courier_info'        => 'cheapest',
					'service_id'          => 'cheapest',
					'sample_cost'         => '0.00',
					'sample_cost_display' => '',
					'service_type'        => 'parcel',
					'delivery'            => 'all',
					'price'               => 0,
					'addon_price'         => 0,
					'shipment_price'      => 0,
				);
			}

			return array_merge( $courier_list, $new_list );
		}

		/**
		 * Easyparcel filtering region rate
		 *
		 * @param $zone
		 * @param $customize
		 *
		 * @return array|mixed|string
		 *
		 */
		public static function filteringRegionRate( $zone, $customize = false ) {
			$r_data    = array();
			$locations = $zone->get_zone_locations();
			if ( empty( $locations ) ) {
				return array();
			} // no rate to show
			$countries = array_filter( $locations, function ( $location ) {
				return 'country' === $location->type;
			} );
			$states    = array_filter( $locations, function ( $location ) {
				return 'state' === $location->type;
			} );

			$my_state    = array();
			$other_state = array();
			foreach ( $states as $state ) {
				$temp = explode( ':', $state->code );
				if ( $temp[0] == 'MY' ) {
					$my_state[] = $temp[1];
				} else {
					$other_state[] = $temp;
				}
			}
			//do condition returning what to do for rate
			if ( count( $countries ) > 1 ) {
				$country_list = array();
				foreach ( $countries as $c ) {
					$country_list[] = $c->code;
				}
				if ( ! empty( $states ) ) {
					foreach ( $states as $state ) {
						$temp           = explode( ':', $state->code );
						$country_list[] = $temp[0];
					}
				}
				$r_data['condition'] = 'country';
				$r_data['country']   = $country_list;
			} else if ( count( $countries ) > 0 ) {
				if ( ! empty( $my_state ) && ! empty( $other_state ) ) {
					return array(); // no rate to show
				} else if ( ! empty( $other_state ) ) {
					$test_arr = array();
					foreach ( $other_state as $ostat ) {
						$test_arr[ $ostat[0] ][] = $ostat[1];
					}
					if ( count( $test_arr ) > 1 ) { // consists multiple country and state
						return array(); // no rate to show
					} else {
						if ( $countries == key( $test_arr ) ) {
							$r_data['condition'] = 'country';
							$r_data['country']   = strtolower( key( $countries ) );
						}
					}
				} else {
					// only one country
					$r_data['condition'] = 'country';
					$r_data['country']   = strtolower( $countries[0]->code );
				}
			} else if ( ! empty( $my_state ) && ! empty( $other_state ) ) {
				return array(); // no rate to show
			} else if ( ! empty( $other_state ) ) {
				$test_arr = array();
				foreach ( $other_state as $ostat ) {
					$test_arr[ $ostat[0] ][] = $ostat[1];
				}
				if ( count( $test_arr ) > 1 ) { // consists multiple country and state
					return array(); // no rate to show
				} else {
					// for international only get country
					$r_data['condition'] = 'country';
					$r_data['country']   = strtolower( key( $test_arr ) );
				}
			} else if ( ! empty( $my_state ) ) {
				if ( count( $my_state ) > 2 ) {
					// for MY if more than 2 state, will direct use country
					$r_data['condition'] = 'country';
					$r_data['country']   = 'my';
				} else {
					$r_data['condition'] = 'state';
					$r_data['country']   = 'my';
					$r_data['state']     = $my_state;
				}
			}

			//do foreach to get rate and mapping
			$rates = array();
			if ( ! empty( $r_data ) ) {
				switch ( $r_data['condition'] ) {
					case 'country':
						if ( is_array( $r_data['country'] ) ) {
							$temp_rate = array();
							foreach ( $r_data['country'] as $c ) {
								$temp['country'] = $c;
								$temp['state']   = '';
								$temp_rate       = self::callrate( $temp, $customize );
								if ( ! empty( $temp_rate ) ) {
									$rates = array_merge( $rates, $temp_rate );
								}
							}
						} else {
							$temp            = array();
							$temp['country'] = $r_data['country'];
							$temp['state']   = '';
							$rates           = self::callrate( $temp, $customize );
						}
						break;
					case 'state':
						$temp_rate = array();
						foreach ( $r_data['state'] as $state ) {
							$temp['country'] = $r_data['country'];
							$temp['state']   = $state;
							$temp_rate       = self::callrate( $temp, $customize );
							if ( ! empty( $temp_rate ) ) {
								$rates = array_merge( $rates, $temp_rate );
							}
						}
						break;
				}
			}

			return $rates;
		}

		/**
		 * Easyparcel callrate
		 *
		 * @param $data
		 * @param $customize
		 *
		 * @return array|mixed|string
		 */

		public static function callrate( $data, $customize = false ) {
			if ( ! class_exists( 'Easyparcel_Shipping_API' ) ) {
				// Include Easyparcel API
				include_once 'easyparcel_api.php';
			}
			Easyparcel_Shipping_API::init();
			$auth = Easyparcel_Shipping_API::auth();
			if ( $auth != 'Success.' ) {
				// show authentication got problem, prompt user to setup correct email + integration id
				// todo
				return array();
			} else {
				// go get rate
				$destination             = array();
				$destination['country']  = $data['country'];
				$destination['state']    = $data['state'];
				$destination['postcode'] = ''; // no have postcode, so ignore it as empty
				if ( $customize ) {
					switch ( strtoupper( $data['state'] ) ) {
						case "SBH":
							$destination['postcode'] = "88000";
							break;
						case "SRW":
						case "SWK":
							$destination['postcode'] = "93000";
							break;
						case "LBN":
							$destination['postcode'] = "87000";
							break;
						default:
							break;
					}
				}
				$items              = array();
				$items[0]['length'] = 10;
				$items[0]['width']  = 10;
				$items[0]['height'] = 10;
				$weight             = 1;

				return Easyparcel_Shipping_API::getShippingRate( $destination, $items, $weight );
			}
		}

		/**
		 * Easyparcel charges option
		 *
		 * @param $selected
		 *
		 * @return array
		 *
		 */
		public static function chargesOption( $selected = '' ) {
			$charges    = array();
			$charges[2] = array( 'text' => 'EasyParcel Member Rate', 'selected' => '' );
			// $charges[3] = array('text' => 'EasyParcel Public Rate', 'selected' => '');
			$charges[4] = array( 'text' => 'Add On EasyParcel Member Rate', 'selected' => '' );
			$charges[1] = array( 'text' => 'Flat Rate', 'selected' => '' );

			foreach ( $charges as $k => &$c ) {
				if ( $k == $selected ) {
					$c['selected'] = 'selected';
				}
			}

			return $charges;
		}

		/**
		 * Easyparcel free shipping by option
		 *
		 * @param $selected
		 *
		 * @return array
		 *
		 */
		public static function freeShippingByOption( $selected = '' ) {
			$option    = array();
			$option[1] = array( 'text' => 'A minimum order amount', 'selected' => '' );
			$option[2] = array( 'text' => 'A minimum order quantity', 'selected' => '' );

			foreach ( $option as $k => &$c ) {
				if ( $k == $selected ) {
					$c['selected'] = 'selected';
				}
			}

			return $option;
		}

		/**
		 * Easyparcel addon charges option
		 *
		 * @param $selected
		 *
		 * @return array
		 *
		 */

		public static function addonChargesOption( $selected = '' ) {
			$option    = array();
			$option[1] = array( 'text' => 'Add On By Amount (' . get_woocommerce_currency() . ')', 'selected' => '' );
			$option[2] = array( 'text' => 'Add On By Percentage (%)', 'selected' => '' );

			foreach ( $option as $k => &$c ) {
				if ( $k == $selected ) {
					$c['selected'] = 'selected';
				}
			}

			return $option;
		}

		/**
		 * Easyparcel check dropoff
		 *
		 * @param $courier
		 * @param $courier_list
		 *
		 * @return array
		 *
		 */
		public static function checkDropoff( $courier, $courier_list = array() ) {
			$option = array();
//            $option['optional'] = array('text' => 'Drop Off Point', 'selected' => '');
			if ( ! empty( $courier_list[ $courier->service_id ] ) ) {
				if ( ! empty( $courier_list[ $courier->service_id ]['dropoff_point'] ) ) {
					foreach ( $courier_list[ $courier->service_id ]['dropoff_point'] as $k => $v ) {
						if ( $v->point_id == $courier->courier_dropoff_point ) {
							$option[ $v->point_id ] = array( 'text' => $v->point_name, 'selected' => 'selected' );
						} else {
							$option[ $v->point_id ] = array( 'text' => $v->point_name, 'selected' => '' );
						}
					}
				}
			}

			return $option;
		}

	}

}