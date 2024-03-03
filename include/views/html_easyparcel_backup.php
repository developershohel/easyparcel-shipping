<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Check if WP_Filesystem is available
if ( ! function_exists( 'WP_Filesystem' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}

// Initialize the WP_Filesystem
if ( ! WP_Filesystem() ) {
	// Failed to initialize WP_Filesystem, handle the error accordingly
	return;
}
if ( ! class_exists( 'WC_Easyparcel_Backup_HTML' ) ) {
	class WC_Easyparcel_Backup_HTML {
		public $backup_list;
		public $backup_path;
		protected $ignore_files;
		protected $backup_extention;

		public function __construct() {
			$this->ignore_files = [ '.', '..' ];
			$this->backup_path  = wp_upload_dir()['basedir'] . "/easyparcel/";
			wp_mkdir_p( $this->backup_path );
			$this->backup_extention = ".ep.backup";
		}

		public function loadBackupFile() {
			// $this->backup_list = [];
			$this->backup_list = array_diff( scandir( $this->backup_path, 1 ), $this->ignore_files );
		}

		public function execBackupProcess() {
			global $wpdb, $wp_filesystem;

			// Check if WP_Filesystem is available
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// Initialize the WP_Filesystem
			if ( ! WP_Filesystem() ) {
				// Failed to initialize WP_Filesystem, handle the error accordingly
				return;
			}

			$backup_file    = isset( $_POST['backup_file'] ) && wp_verify_nonce( $_POST['backup_file'], 'backup_file_action' ) ? sanitize_file_name( $_POST['backup_file'] ) : '';
			$backup_content = "<?php \n";

			// Output message indicating backup process initialization
			echo esc_html( 'Backup process initialized' ) . "<br>\n";

			// Add backup_option content to backup_content
			$backup_content .= "\$backup_option = json_decode('" . wp_json_encode( get_option( 'woocommerce_easyparcel_settings' ) ) . "');\n";

			// Get easyparcel_zones from the database
			$easyparcel_zones = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zones" );
			// Add backup_db_easyparcel_zones content to backup_content
			$backup_content .= "\$backup_db_easyparcel_zones = json_decode('" . wp_json_encode( $easyparcel_zones ) . "');\n";

			// Output message indicating backup is in progress
			echo esc_html( 'Backup in progress' ) . "<br>\n";

			// Get easyparcel_zones_courier from the database
			$easyparcel_zones_courier = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}easyparcel_zones_courier" );
			// Add backup_db_easyparcel_zones_courier content to backup_content
			$backup_content .= "\$backup_db_easyparcel_zones_courier = json_decode('" . wp_json_encode( $easyparcel_zones_courier ) . "');\n";

			// Get easyparcel_zone_locations from the database
			$easyparcel_zone_locations = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_locations" );
			// Add backup_db_easyparcel_zone_locations content to backup_content
			$backup_content .= "\$backup_db_easyparcel_zone_locations = json_decode('" . wp_json_encode( $easyparcel_zone_locations ) . "');\n";

			// Add closing PHP tag to backup_content
			$backup_content .= "?>\n";

			$file_path = $this->backup_path . $backup_file . $this->backup_extention;
			$wp_filesystem->put_contents( $file_path, $backup_content, FS_CHMOD_FILE );

			// Output message indicating completion of backup
			echo esc_html( 'Complete backup' ) . "<br>\n";
			// Output message indicating the creation of a new backup file
			echo esc_html( 'New backup file' ) . '"' . esc_html( $backup_file ) . '"' . esc_html( 'created' ) . "<br><br>\n";

		}

		public function execRestoreProcess() {
			echo esc_html( 'restore process initialize' ) . "<br>\n";
			$restore_path = isset( $_POST['restore_path'] ) && wp_verify_nonce( $_POST['restore_path'], 'restore_path_action' ) ? sanitize_text_field( $_POST['restore_path'] ) : '';
			$RestoreFile  = $this->backup_path . $restore_path . $this->backup_extention;
			echo esc_html( 'restore from : ' ) . esc_html( $restore_path ) . "<br>\n";
			include_once( $RestoreFile );

			echo esc_html( 'restore easyparcel shipping setting' ) . "<br>\n";
			if ( get_option( 'woocommerce_easyparcel_settings' ) ) {
				update_option( 'woocommerce_easyparcel_settings', (array) $backup_option );
			} else {
				add_option( 'woocommerce_easyparcel_settings', (array) $backup_option );
			}

			echo esc_html( 'restore easyparcel courier setting' ) . "<br>\n";
			$this->truncateTable( "woocommerce_shipping_zones" );
			$this->insertTable( "woocommerce_shipping_zones", $backup_db_easyparcel_zones );

			$this->truncateTable( "easyparcel_zones_courier" );
			$this->insertTable( "easyparcel_zones_courier", $backup_db_easyparcel_zones_courier );

			$this->truncateTable( "woocommerce_shipping_zone_locations" );
			$this->insertTable( "woocommerce_shipping_zone_locations", $backup_db_easyparcel_zone_locations );

			echo esc_html( 'complete restored data' ) . "<br><br>\n";
		}

		public function hasBackupFile() {
			return count( $this->backup_list ) > 0 ? true : false;
		}

		public function clearBackup() {
			$this->loadBackupFile();
			foreach ( $this->backup_list as $file ) {
				$file_path = $this->backup_path . $file;

				// Use wp_delete_file() to delete the file
				$delete = apply_filters( 'wp_delete_file', $file_path );
				if ( ! empty( $delete ) ) {
					wp_delete_file( $delete );
				}
			}
		}

		public function trim( $string ) {
			return str_replace( $this->backup_extention, "", $string );
		}

		protected function truncateTable( $table ) {
			global $wpdb;
			// Execute the prepared query
			$wpdb->query( $wpdb->prepare( "TRUNCATE TABLE {$wpdb->prefix}%s", $table ) );
		}


		protected function insertTable( $table, $datas ) {
			global $wpdb;
			$field = array_keys( (array) $datas[0] );

			$query = "INSERT INTO {$wpdb->prefix}$table (" . implode( ',', $field ) . ") VALUES ";
			$list  = [];
			foreach ( $datas as $row ) {
				$list[] = '("' . implode( '","', array_values( (array) $row ) ) . '")';
			}
			$query .= implode( ",", $list );
			// echo "<br>\n$query\n<br>";
			$wpdb->query( $wpdb->prepare( $query ) );
		}
	}
}

?>

<h2 class="wc-shipping-zones-heading"> <?php esc_html_e( 'EasyParcel Backup & Restore', 'easyparcel_backup' ); ?></h2>
<form method="post">
	<?php
	$Backup = new WC_Easyparcel_Backup_HTML();
	if ( ! empty( $_POST ) ) {
		if ( isset( $_POST['backup'] ) && wp_verify_nonce( $_POST['backup_nonce'], 'backup_action' ) ) {
			$Backup->execBackupProcess();
		}

		if ( isset( $_POST['restore'] ) && wp_verify_nonce( $_POST['restore_nonce'], 'restore_action' ) ) {
			$Backup->execRestoreProcess();
		}

		if ( isset( $_POST['clear_backup'] ) && wp_verify_nonce( $_POST['clear_backup_nonce'], 'clear_backup_action' ) ) {
			$Backup->clearBackup();
		}
	}
	$Backup->loadBackupFile();
	wp_nonce_field( 'restore_path_action', 'restore_path' );
	wp_nonce_field( 'backup_file_action', 'backup_file' );
	wp_nonce_field( 'backup_action', 'backup_nonce' ); ?>
    <button type="submit" name="backup" class="button button-primary button-large wc-shipping-zone-method-save">Backup
    </button>
    <input type="text" name="backup_file" value="<?php echo esc_attr( gmdate( 'Y_m_d_H_i_s' ) ); ?>">
    <br><br>

	<?php if ( $Backup->hasBackupFile() ) { ?>
		<?php wp_nonce_field( 'restore_action', 'restore_nonce' ); ?>
        <button type="submit" name="restore" class="button button-primary button-large wc-shipping-zone-method-save">
            Restore
        </button>
        <select name="restore_path" class="wc-shipping-zone-region-select chosen_select">
			<?php foreach ( $Backup->backup_list as $file ) { ?>
                <option><?php echo esc_html( $Backup->trim( $file ) ); ?></option>
			<?php } ?>
        </select>

        <br><br>
		<?php wp_nonce_field( 'clear_backup_action', 'clear_backup_nonce' ); ?>
        <button type="submit" name="clear_backup"
                class="button button-primary button-large wc-shipping-zone-method-save">Clear All Backup File
        </button>
	<?php } ?>
</form>
