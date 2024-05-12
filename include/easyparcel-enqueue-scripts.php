<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
add_action( 'admin_enqueue_scripts', 'easyparcel_enqueue_scripts' );
function easyparcel_enqueue_scripts() {
	wp_enqueue_style( 'ep-admin', EASYPARCEL_ASSETS_URL . 'css/ep-admin.css', array(), EASYPARCEL_VERSION );
	wp_enqueue_script( 'ep-admin', EASYPARCEL_ASSETS_URL . 'js/ep-admin.js', array(), EASYPARCEL_VERSION, true );
	wp_localize_script( 'ep-admin', 'ajax_object', array(
		'ajax_url'  => admin_url( 'admin-ajax.php' ),
		'admin_url' => admin_url()
	) );
}