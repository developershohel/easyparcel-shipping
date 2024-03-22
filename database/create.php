<?php
/**
 * Create courier Table
 */
function easyparcel_create_zones_courier_table() {
	global $wpdb;
	global $jal_db_version;
	$jal_db_version  = '1.0';
	$charset_collate = $wpdb->get_charset_collate();
	$table_name      = $wpdb->prefix . 'easyparcel_zones_courier';
	$sql             = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            zone_id BIGINT UNSIGNED NOT NULL,
            instance_id INT NULL,
            service_id varchar(9) NULL,
            service_name varchar(200) NULL,
            service_type varchar(50) NULL,
            courier_id varchar(9) NULL,
            courier_name tinytext NULL,
            courier_logo tinytext NULL,
            courier_info text NULL,
            courier_display_name varchar(100) NULL,
            courier_dropoff_point varchar(100) NULL,
            courier_dropoff_name varchar(100) NULL,
            price float(9,2) DEFAULT 0.00,
            addon_price float(9,2) DEFAULT 0.00,
            shipment_price float(9,2) DEFAULT 0.00,
            sample_cost float(9,2) DEFAULT 0.00,
            charges tinyint(2) NOT NULL,
            charges_value varchar(50) DEFAULT 0,
            free_shipping tinyint(2) DEFAULT 2,
            free_shipping_by tinyint(2) DEFAULT 0,
            free_shipping_value varchar(50) DEFAULT 0,
            courier_order BIGINT UNSIGNED NOT NULL,
            status tinyint(2) DEFAULT 1,
            PRIMARY KEY (id)
        ) $charset_collate;";
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
	$column_exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM information_schema.columns WHERE table_name = %s AND column_name = %s", $table_name, 'price'
		)
	);
	if ( empty( $column_exists ) ) {
		$query = $wpdb->prepare( "ALTER TABLE %s ADD COLUMN instance_id INT NULL AFTER zone_id, ADD COLUMN courier_dropoff_name varchar(100) NULL AFTER courier_dropoff_point, ADD COLUMN price FLOAT NULL DEFAULT 0.00 AFTER courier_dropoff_point, ADD COLUMN addon_price FLOAT NULL DEFAULT 0.00 AFTER price, ADD COLUMN shipment_price FLOAT NULL DEFAULT 0.00 AFTER addon_price", $table_name );
		$wpdb->query( $query );
//		$wpdb->query( $wpdb->prepare( "ALTER TABLE {$table_name} ADD COLUMN instance_id INT NULL AFTER zone_id, ADD COLUMN courier_dropoff_name varchar(100) NULL AFTER courier_dropoff_point, ADD COLUMN price FLOAT NULL DEFAULT 0.00 AFTER courier_dropoff_point, ADD COLUMN addon_price FLOAT NULL DEFAULT 0.00 AFTER price, ADD COLUMN shipment_price FLOAT NULL DEFAULT 0.00 AFTER addon_price" ) );
	}

	add_option( 'jal_db_version', $jal_db_version );
}