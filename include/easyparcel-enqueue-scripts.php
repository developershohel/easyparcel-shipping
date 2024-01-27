<?php
add_action( 'admin_enqueue_scripts', 'ep_enqueue_scripts' );
function ep_enqueue_scripts() {
	wp_enqueue_style( 'ep-admin', EASYPARCEL_ASSETS_URL . 'css/ep-admin.css' );
	wp_enqueue_script( 'ep-admin', EASYPARCEL_ASSETS_URL . 'js/ep-admin.js' );
	wp_localize_script( 'ep-admin', 'ajax_object', array(
		'ajax_url'  => admin_url( 'admin-ajax.php' ),
		'admin_url' => admin_url()
	) );
}