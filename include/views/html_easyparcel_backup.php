<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
			global $wpdb;
			$backup_file    = isset( $_POST['backup_file'] ) ? sanitize_term_field( $_POST['backup_file'] ) : '';
			$backup_content = "<?php \n";

			echo esc_html( 'backup process initialize' ) . "<br>\n";

			$backup_content .= "\$backup_option = json_decode('" . wp_json_encode( get_option( 'woocommerce_easyparcel_settings' ) ) . "');\n";

			$easyparcel_zones = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zones" );
			$backup_content   .= "\$backup_db_easyparcel_zones = json_decode('" . wp_json_encode( $easyparcel_zones ) . "');\n";
			echo esc_html( 'backup in progress' ) . "<br>\n";

			$easyparcel_zones_courier = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}easyparcel_zones_courier" );
			$backup_content           .= "\$backup_db_easyparcel_zones_courier = json_decode('" . wp_json_encode( $easyparcel_zones_courier ) . "');\n";

			$easyparcel_zone_locations = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}woocommerce_shipping_zone_locations" );
			$backup_content            .= "\$backup_db_easyparcel_zone_locations = json_decode('" . wp_json_encode( $easyparcel_zone_locations ) . "');\n";

			$backup_content .= "?>\n";
			file_put_contents( $this->backup_path . $backup_file . $this->backup_extention, $backup_content );
			echo esc_html( 'complete backup' ) . "<br>\n";
			echo esc_html( 'new backup file' ) . "\"$backup_file\"" . esc_html( 'created' ) . "<br><br>\n";
		}

		public function execRestoreProcess() {
			echo esc_html( 'restore process initialize' ) . "<br>\n";
			$restore_path = isset( $_POST['restore_path'] ) ? sanitize_text_field( $_POST['restore_path'] ) : '';
			$RestoreFile  = $this->backup_path . $restore_path . $this->backup_extention;
			echo esc_html( 'restore from : ' ) . $restore_path . "<br>\n";
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
				$delete = apply_filters( 'wp_delete_file', $this->backup_path . $file );
				if ( ! empty( $delete ) ) {
					@unlink( $delete );
				}
			}
		}

		public function trim( $string ) {
			return str_replace( $this->backup_extention, "", $string );
		}

		protected function truncateTable( $table ) {
			global $wpdb;
			// echo "<br>TRUNCATE TABLE {$wpdb->prefix}$table<br>";
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}$table" );
		}

		protected function insertTable( $table, $datas ) {
			global $wpdb;
			$field = array_keys( (array) $datas[0] );

			$query = "INSERT INTO {$wpdb->prefix}$table (" . implode( ',', $field ) . ") VALUES ";
			$list  = [];
			foreach ( $datas as $row ) {
				array_push( $list, '("' . implode( '","', array_values( (array) $row ) ) . '")' );
			}
			$query .= implode( ",", $list );
			// echo "<br>\n$query\n<br>";
			$wpdb->query( $query );
		}
	}
}

?>

<h2 class="wc-shipping-zones-heading"> <?php _e( 'EasyParcel Backup & Restore', 'easyparcel_backup' ); ?></h2>
<form method="post">
	<?php
	$Backup = new WC_Easyparcel_Backup_HTML();
	if ( ! empty( $_POST ) ) {
		if ( isset( $_POST['backup'] ) ) {
			$Backup->execBackupProcess();
		}

		if ( isset( $_POST['restore'] ) ) {
			$Backup->execRestoreProcess();
		}

		if ( isset( $_POST['clear_backup'] ) ) {
			$Backup->clearBackup();
		}
	}
	$Backup->loadBackupFile();
	?>
    <button type="submit" name="backup" class="button button-primary button-large wc-shipping-zone-method-save">Backup
    </button>
    <input type="text" name="backup_file" value="<?php echo Date( 'Y_m_d_H_i_s' ); ?>">
    <br><br>

	<?php if ( $Backup->hasBackupFile() ) { ?>

        <button type="submit" name="restore" class="button button-primary button-large wc-shipping-zone-method-save">
            Restore
        </button>
        <select name="restore_path" class="wc-shipping-zone-region-select chosen_select">
			<?php foreach ( $Backup->backup_list as $file ) { ?>
                <option><?php echo $Backup->trim( $file ); ?></option>
			<?php } ?>
        </select>

        <br><br>
        <button type="submit" name="clear_backup"
                class="button button-primary button-large wc-shipping-zone-method-save">Clear All Backup File
        </button>

	<?php } ?>
</form>
