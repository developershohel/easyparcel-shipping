<?php
/**
 * Create courier Table
 */
function easyparcel_create_zones_courier_table() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();
	$sql             = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}easyparcel_zones_courier (
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
}