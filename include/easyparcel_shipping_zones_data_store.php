<?php
/**
 * Class Easyparcel_Shipping_Zone_Data_Store file.
 *
 * @package WooCommerce\DataStores
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once 'interface/easyparcel_object_data_store_interface.php';
require_once 'interface/easyparcel_shipping_zone_data_store_interface.php';

/**
 * WC Shipping Zone Data Store.
 *
 * @version  3.0.0
 */
class Easyparcel_Shipping_Zone_Data_Store extends WC_Data_Store_WP implements Easyparcel_Shipping_Zone_Data_Store_Interface, Easyparcel_Object_Data_Store_Interface {

	/**
	 * Method to create a new shipping zone.
	 *
	 * @param WC_Shipping_Zone $zone Shipping zone object.
	 *
	 * @since 3.0.0
	 */
	public function create( &$zone ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_shipping_zones',
			array(
				'zone_name'  => $zone->get_zone_name(),
				'zone_order' => $zone->get_zone_order(),
			)
		);
		$zone->set_id( $wpdb->insert_id );
		$zone->save_meta_data();
		$this->save_locations( $zone );
		$zone->apply_changes();
		WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	/**
	 * Save locations to the DB.
	 * This function clears old locations, then re-inserts new if any changes are found.
	 *
	 * @param WC_Shipping_Zone $zone Shipping zone object.
	 *
	 * @return bool|void
	 * @since 3.0.0
	 *
	 */
	private function save_locations( &$zone ) {
		global $wpdb;
		$changed_props = array_keys( $zone->get_changes() );
		if ( ! in_array( 'zone_locations', $changed_props, true ) ) {
			return false;
		}
		$wpdb->delete( $wpdb->prefix . 'woocommerce_shipping_zone_locations', array( 'zone_id' => $zone->get_id() ) );

		foreach ( $zone->get_zone_locations( 'edit' ) as $location ) {
			$wpdb->insert(
				$wpdb->prefix . 'woocommerce_shipping_zone_locations',
				array(
					'zone_id'       => $zone->get_id(),
					'location_code' => $location->code,
					'location_type' => $location->type,
				)
			);
		}
	}

	/**
	 * Deletes a shipping zone from the database.
	 *
	 * @param WC_Shipping_Zone $zone Shipping zone object.
	 * @param array $args Array of args to pass to the delete method.
	 *
	 * @return void
	 * @since  3.0.0
	 */
	public function delete( &$zone, $args = array() ) {
		$zone_id = $zone->get_id();

		if ( $zone_id ) {
			global $wpdb;
			// Delete methods and their settings.
			$methods = $this->get_methods( $zone_id, false );

			if ( $methods ) {
				foreach ( $methods as $method ) {
					$this->delete_method( $method->zone_id );
				}
			}

			// Delete zone.
			$wpdb->delete( $wpdb->prefix . 'woocommerce_shipping_zone_locations', array( 'zone_id' => $zone_id ) );
			$wpdb->delete( $wpdb->prefix . 'woocommerce_shipping_zones', array( 'zone_id' => $zone_id ) );

			$zone->set_id( null );

			WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
			WC_Cache_Helper::get_transient_version( 'shipping', true );

			do_action( 'woocommerce_delete_shipping_zone', $zone_id );
		}
	}

	/**
	 * Get a list of shipping methods for a specific zone.
	 *
	 * @param int $zone_id Zone ID.
	 * @param bool $enabled_only True to request enabled methods only.
	 *
	 * @return array               Array of objects containing method_id, method_order, instance_id, is_enabled
	 * @since  3.0.0
	 */
	public function get_methods( $zone_id, $enabled_only ) {
		global $wpdb;

		if ( $enabled_only ) {
			return $wpdb->get_results($wpdb->prepare("SELECT id, courier_order, zone_id, status FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id =%d AND is_enabled = 1", $zone_id));
		} else {
			return $wpdb->get_results($wpdb->prepare("SELECT id, courier_order, zone_id, status FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id =%d", $zone_id));
		}
	}

	/**
	 * Delete a method instance.
	 *
	 * @param int $zone_id
	 *
	 * @since 3.0.0
	 */
	public function delete_method( $zone_id ) {
		global $wpdb;

		$method = $this->get_method( $zone_id );

		if ( ! $method ) {
			return;
		}
		// delete_option( 'woocommerce_' . $method->method_id . '_' . $zone_id . '_settings' );

		$wpdb->delete( $wpdb->prefix . 'easyparcel_zones_courier', array( 'zone_id' => $zone_id ) );

		// do_action( 'woocommerce_delete_shipping_zone_method', $zone_id );
	}

	/**
	 * Get a shipping zone method instance.
	 *
	 * @param int $zone_id Instance ID.
	 *
	 * @return object
	 * @since  3.0.0
	 */
	public function get_method( $zone_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT zone_id, id, courier_order, status FROM {$wpdb->prefix}easyparcel_zones_courier WHERE zone_id =%d LIMIT 1;", $zone_id ) );
	}

	/**
	 * Update zone in the database.
	 *
	 * @param WC_Shipping_Zone $zone Shipping zone object.
	 *
	 * @since 3.0.0
	 */
	public function update( &$zone ) {
		global $wpdb;
		if ( $zone->get_id() ) {
			$wpdb->update(
				$wpdb->prefix . 'woocommerce_shipping_zones',
				array(
					'zone_name'  => $zone->get_zone_name(),
					'zone_order' => $zone->get_zone_order(),
				),
				array( 'zone_id' => $zone->get_id() )
			);
		}
		$zone->save_meta_data();
		$this->save_locations( $zone );
		$zone->apply_changes();
		WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	/**
	 * Method to read a shipping zone from the database.
	 *
	 * @param WC_Shipping_Zone $zone Shipping zone object.
	 *
	 * @throws Exception If invalid data store.
	 * @since 3.0.0
	 */
	public function read( &$zone ) {
		global $wpdb;

		// Zone 0 is used as a default if no other zones fit.
		if ( 0 === $zone->get_id() || '0' === $zone->get_id() ) {
			$this->read_zone_locations( $zone );
			$zone->set_zone_name( __( 'Locations not covered by your other zones', 'easyparcel-shipping' ) );
			$zone->read_meta_data();
			$zone->set_object_read( true );

			/**
			 * Indicate that the WooCommerce shipping zone has been loaded.
			 *
			 * @param WC_Shipping_Zone $zone The shipping zone that has been loaded.
			 */
			do_action( 'woocommerce_shipping_zone_loaded', $zone );

			return;
		}

		$zone_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT zone_name, zone_order FROM {$wpdb->prefix}woocommerce_shipping_zones WHERE zone_id =%d LIMIT 1",
				$zone->get_id()
			)
		);

		if ( ! $zone_data ) {
			throw new Exception( esc_html( 'Invalid data store.' ) );
		}

		$zone->set_zone_name( $zone_data->zone_name );
		$zone->set_zone_order( $zone_data->zone_order );
		$this->read_zone_locations( $zone );
		$zone->read_meta_data();
		$zone->set_object_read( true );

		/** This action is documented in includes/datastores/class-wc-shipping-zone-data-store.php. */
		do_action( 'woocommerce_shipping_zone_loaded', $zone );
	}

	/**
	 * Read location data from the database.
	 *
	 * @param WC_Shipping_Zone $zone Shipping zone object.
	 */
	private function read_zone_locations( &$zone ) {
		global $wpdb;

		$locations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_code, location_type FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE zone_id =%d",
				$zone->get_id()
			)
		);

		if ( $locations ) {
			foreach ( $locations as $location ) {
				$zone->add_location( $location->location_code, $location->location_type );
			}
		}
	}

	/**
	 * Get count of methods for a zone.
	 *
	 * @param int $zone_id Zone ID.
	 *
	 * @return int Method Count
	 * @since  3.0.0
	 */
	public function get_method_count( $zone_id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id =%d", $zone_id ) );
	}

	/**
	 * Add a shipping method to a zone.
	 *
	 * @param int $zone_id Zone ID.
	 * @param string $type Method Type/ID.
	 * @param int $order Method Order.
	 *
	 * @return int             Instance ID
	 * @since  3.0.0
	 */
	public function add_method( $zone_id, $type, $order ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'woocommerce_shipping_zone_methods',
			array(
				'method_id'    => $type,
				'zone_id'      => $zone_id,
				'method_order' => $order,
			),
			array(
				'%s',
				'%d',
				'%d',
			)
		);

		return $wpdb->insert_id;
	}

	/**
	 * Find a matching zone ID for a given package.
	 *
	 * @param object $package Package information.
	 *
	 * @return int
	 * @since  3.0.0
	 */
	public function get_zone_id_from_package( $package ) {
		global $wpdb;

		$country   = strtoupper( wc_clean( $package['destination']['country'] ) );
		$state     = strtoupper( wc_clean( $package['destination']['state'] ) );
		$continent = strtoupper( wc_clean( WC()->countries->get_continent_code_for_country( $country ) ) );
		$postcode  = wc_normalize_postcode( wc_clean( $package['destination']['postcode'] ) );

		// Work out criteria for our zone search.
		$criteria   = array();
        $criteria[] = "( ( location_type = 'country' AND location_code = %s )";
        $criteria[] = "OR ( location_type = 'state' AND location_code = %s )";
        $criteria[] = "OR ( location_type = 'continent' AND location_code = %s )";
		$criteria[] = 'OR ( location_type IS NULL ) )';

		// Postcode range and wildcard matching.
		$postcode_locations = $wpdb->get_results( "SELECT zone_id, location_code FROM {$wpdb->prefix}woocommerce_shipping_zone_locations WHERE location_type = 'postcode';" );

		if ( $postcode_locations ) {
			$zone_ids_with_postcode_rules = array_map( 'absint', wp_list_pluck( $postcode_locations, 'zone_id' ) );
			$matches                      = wc_postcode_location_matcher( $postcode, $postcode_locations, 'zone_id', 'location_code', $country );
			$do_not_match                 = array_unique( array_diff( $zone_ids_with_postcode_rules, array_keys( $matches ) ) );
			if ( ! empty( $do_not_match ) ) {
				$criteria[] = 'AND zones.zone_id NOT IN (' . implode( ',', $do_not_match ) . ')';
			}
		}

		/**
		 * Get shipping zone criteria
		 *
		 * @param array $criteria Get zone criteria.
		 * @param array $package Package information.
		 * @param array $postcode_locations Postcode range and wildcard matching.
		 *
		 * @since 3.6.6
		 */
		$criteria    = apply_filters( 'woocommerce_get_zone_criteria', $criteria, $package, $postcode_locations );
		return $wpdb->get_var($wpdb->prepare( "SELECT zones.zone_id FROM {$wpdb->prefix}woocommerce_shipping_zones as zones
    LEFT OUTER JOIN {$wpdb->prefix}woocommerce_shipping_zone_locations as locations ON zones.zone_id = locations.zone_id AND location_type != 'postcode'
    WHERE (( location_type = 'country' AND location_code = %s ) OR ( location_type = 'state' AND location_code = %s ) OR ( location_type = 'continent' AND location_code = %s ) OR ( location_type IS NULL )) ORDER BY zone_order ASC, zones.zone_id ASC LIMIT 1", $country, $country . ':' . $state, $continent ));
	}

	/**
	 * Return an ordered list of zones.
	 *
	 * @return array An array of objects containing a zone_id, zone_name, and zone_order.
	 * @since 3.0.0
	 */
	public function get_zones() {
		global $wpdb;

		return $wpdb->get_results( "SELECT zone_id, zone_name, zone_order FROM {$wpdb->prefix}woocommerce_shipping_zones order by zone_order ASC, zone_id ASC;" );
	}

	/**
	 * Return a zone ID from an instance ID.
	 *
	 * @param int $id Instnace ID.
	 *
	 * @return int
	 * @since  3.0.0
	 */
	public function get_zone_id_by_instance_id( $id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT zone_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods as methods WHERE methods.instance_id =%d LIMIT 1;", $id ) );
	}
}
