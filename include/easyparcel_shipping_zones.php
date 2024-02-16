<?php
/**
 * Handles storage and retrieval of shipping zones
 *
 * @package WooCommerce\Classes
 * @version 3.3.0
 * @since   2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shipping zones class.
 */
class Easyparcel_Shipping_Zones {
	public function __construct() {
		include_once 'easyparcel_shipping_zone.php';
	}

	/**
	 * Get shipping zones from the database.
	 *
	 * @param string $context Getting shipping methods for what context. Valid values, admin, json.
	 *
	 * @return array Array of arrays.
	 * @since 2.6.0
	 */
	public static function get_zones( $context = 'admin' ) {
		include_once 'easyparcel_shipping_zone.php';
		$raw_zones = self::ep_get_zones();
		$zones     = array();

		foreach ( $raw_zones as $raw_zone ) {
			$zone                                                = new Easyparcel_Shipping_Zone( $raw_zone );
			$zones[ $zone->get_id() ]                            = $zone->get_data();
			$zones[ $zone->get_id() ]['zone_id']                 = $zone->get_id();
			$zones[ $zone->get_id() ]['couriers']                = $zone->get_couriers();
			$zones[ $zone->get_id() ]['formatted_zone_location'] = $zone->get_formatted_location();
			// $zones[ $zone->get_id() ]['rate']        = $zone->get_rate();
		}

		return $zones;
	}

	public static function ep_get_zones() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'woocommerce_shipping_zones';

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM  $table_name" ) );
	}

	public static function get_zone_courier( $zone_id = 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'easyparcel_zones_courier';

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM  $table_name WHERE zone_id = $zone_id ORDER BY courier_order ASC" ) );
	}

	/**
	 * Get shipping zone using it's ID
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @return Easyparcel_Shipping_Zone|bool
	 * @since 2.6.0
	 */
	public static function get_zone( $zone_id ) {
		return self::get_zone_by( 'zone_id', $zone_id );
	}

	/**
	 * Get shipping zone by an ID.
	 *
	 * @param string $by Get by 'zone_id' or 'instance_id'.
	 * @param int $id ID.
	 *
	 * @return Easyparcel_Shipping_Zone|bool
	 * @since 2.6.0
	 */
	public static function get_zone_by( $by = 'zone_id', $id = 0 ) {
		if ( ! class_exists( 'Easyparcel_Shipping_Zone_Data_Store' ) ) {
			include_once 'easyparcel_shipping_zones_data_store.php';
		}
		$zone_id = false;

		switch ( $by ) {
			case 'zone_id':
				$zone_id = $id;
				break;
			case 'courier_id':
				$data_store = new Easyparcel_Shipping_Zone_Data_Store();
				$zone_id    = $data_store->get_zone_id_by_instance_id( $id );
				break;
		}

		if ( false !== $zone_id ) {
			try {
				if ( ! class_exists( 'Easyparcel_Shipping_Zone' ) ) {
					include_once 'easyparcel_shipping_zone.php';
				}

				return new Easyparcel_Shipping_Zone( $zone_id );
			} catch ( Exception $e ) {
				return false;
			}
		}

		return false;
	}

	/**
	 * Get shipping zone using it's ID.
	 *
	 * @param int $instance_id Instance ID.
	 *
	 * @return bool|WC_Shipping_Method
	 * @since 2.6.0
	 */
	public static function get_shipping_method( $instance_id ) {
		$data_store          = WC_Data_Store::load( 'shipping-zone' );
		$raw_shipping_method = $data_store->get_method( $instance_id );
		$wc_shipping         = WC_Shipping::instance();
		$allowed_classes     = $wc_shipping->get_shipping_method_class_names();

		if ( ! empty( $raw_shipping_method ) && in_array( $raw_shipping_method->method_id, array_keys( $allowed_classes ), true ) ) {
			$class_name = $allowed_classes[ $raw_shipping_method->method_id ];
			if ( is_object( $class_name ) ) {
				$class_name = get_class( $class_name );
			}

			return new $class_name( $raw_shipping_method->instance_id );
		}

		return false;
	}

	/**
	 * Delete a zone using it's ID
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @since 2.6.0
	 */
	public static function delete_zone( $zone_id ) {
		if ( ! class_exists( 'Easyparcel_Shipping_Zone' ) ) {
			require_once 'easyparcel_shipping_zone.php';
		}
		$zone = new Easyparcel_Shipping_Zone( $zone_id );
		$zone->delete();
	}

	/**
	 * Find a matching zone for a given package.
	 *
	 * @param array $package Shipping package.
	 *
	 * @return Easyparcel_Shipping_Zone
	 * @since  2.6.0
	 * @uses   wc_make_numeric_postcode()
	 */
	public static function get_zone_matching_package( $package ) {
		if ( ! class_exists( 'Easyparcel_Shipping_Zone' ) ) {
			include_once 'easyparcel_shipping_zone.php';
		}
		$country          = strtoupper( wc_clean( $package['destination']['country'] ) );
		$state            = strtoupper( wc_clean( $package['destination']['state'] ) );
		$postcode         = wc_normalize_postcode( wc_clean( $package['destination']['postcode'] ) );
		$cache_key        = WC_Cache_Helper::get_cache_prefix( 'shipping_zones' ) . 'ep_shipping_zone_' . md5( sprintf( '%s+%s+%s', $country, $state, $postcode ) );
		$matching_zone_id = wp_cache_get( $cache_key, 'shipping_zones' );

		if ( false === $matching_zone_id ) {
			if ( ! class_exists( 'Easyparcel_Shipping_Zone_Data_Store' ) ) {
				include_once 'easyparcel_shipping_zones_data_store.php';
			}
			$data_store       = new Easyparcel_Shipping_Zone_Data_Store();
			$matching_zone_id = $data_store->get_zone_id_from_package( $package );
			wp_cache_set( $cache_key, $matching_zone_id, 'shipping_zones' );
		}

		return new Easyparcel_Shipping_Zone( $matching_zone_id ? $matching_zone_id : 0 );
	}

	public static function ep_get_valid_zone_courier( $destination ) {
		global $wpdb;
		$table_name  = $wpdb->prefix . 'woocommerce_shipping_zones';
		$table_name2 = $wpdb->prefix . 'easyparcel_zones_courier';
		$raw_zones   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM  $table_name join $table_name2 ON $table_name.zone_id = $table_name2.zone_id" ) );
		$destination['country'];
		$destination['state'];

		return true;
	}
}
