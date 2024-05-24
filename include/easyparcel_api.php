<?php
if ( ! class_exists( 'Easyparcel_Shipping_API' ) ) {
	class Easyparcel_Shipping_API {

		private static $apikey = '';
		private static $apiSecret = '';
		private static $easyparcel_email = '';
		private static $authentication = ''; # Indicate from EP
		private static $integration_id = '';

		private static $sender_name = '';
		private static $sender_contact_number = '';
		private static $sender_alt_contact_number = '';
		private static $sender_company_name = '';
		private static $sender_address_1 = '';
		private static $sender_address_2 = '';
		private static $sender_postcode = '';
		private static $sender_city = '';
		private static $sender_state = '';
		private static $sender_country = '';

		private static $addon_email_option = '';
		private static $addon_sms_option = '';

		// private static $getrate_api_url = ''; // Hide it cause didn't use bulk api
		// private static $submitorder_api_url = ''; // Hide it cause didn't use bulk api
		// private static $payorder_api_url = ''; // Hide it cause didn't use bulk api

		private static $getrate_bulk_api_url = '';
		private static $submit_bulk_order_api_url = '';
		private static $pay_bulk_order_api_url = '';

		private static $auth_url = '';

		/**
		 * init
		 *
		 * @access public
		 * @return void
		 */
		public static function init() {
			if ( ! class_exists( 'Easyparcel_Extend_Shipping_Method' ) ) {
				include_once 'easyparcel_shipping.php';
			}

			$Easyparcel_Extend_Shipping_Method = new Easyparcel_Extend_Shipping_Method();

			self::$sender_country = $Easyparcel_Extend_Shipping_Method->settings['sender_country'];
			$host                 = 'http://connect.easyparcel.' . strtolower( self::$sender_country );

			// self::$getrate_api_url = $host . '/?ac=EPRateChecking'; // Hide it cause didn't use bulk api
			// self::$submitorder_api_url = $host . '/?ac=EPSubmitOrder'; // Hide it cause didn't use bulk api
			// self::$payorder_api_url = $host . '/?ac=EPPayOrder'; // Hide it cause didn't use bulk api

			self::$getrate_bulk_api_url      = $host . '/?ac=EPRateCheckingBulk';
			self::$submit_bulk_order_api_url = $host . '/?ac=EPSubmitOrderBulk';
			self::$pay_bulk_order_api_url    = $host . '/?ac=EPPayOrderBulk';

			self::$auth_url = $host . '?ac=EPCheckCreditBalance';

			self::$easyparcel_email = $Easyparcel_Extend_Shipping_Method->settings['easyparcel_email'];
			self::$integration_id   = $Easyparcel_Extend_Shipping_Method->settings['integration_id'];

			self::$sender_name               = $Easyparcel_Extend_Shipping_Method->settings['sender_name'];
			self::$sender_contact_number     = $Easyparcel_Extend_Shipping_Method->settings['sender_contact_number'];
			self::$sender_alt_contact_number = $Easyparcel_Extend_Shipping_Method->settings['sender_alt_contact_number'];
			self::$sender_company_name       = $Easyparcel_Extend_Shipping_Method->settings['sender_company_name'];
			self::$sender_address_1          = $Easyparcel_Extend_Shipping_Method->settings['sender_address_1'];
			self::$sender_address_2          = $Easyparcel_Extend_Shipping_Method->settings['sender_address_2'];
			self::$sender_postcode           = $Easyparcel_Extend_Shipping_Method->settings['sender_postcode'];
			self::$sender_city               = $Easyparcel_Extend_Shipping_Method->settings['sender_city'];
			self::$sender_state              = $Easyparcel_Extend_Shipping_Method->settings['sender_state'];

			self::$addon_email_option = $Easyparcel_Extend_Shipping_Method->settings['addon_email_option'];
			self::$addon_sms_option   = $Easyparcel_Extend_Shipping_Method->settings['addon_sms_option'];

		}

		public static function countryValidate() {
			$WC_Country = new WC_Countries();
			if ( strtolower( $WC_Country->get_base_country() ) == strtolower( self::$sender_country ) ) {
				return true;
			} else {
				return false;
			}
		}

		public static function curlPost( $data ) {
			$args = array(
				'timeout'  => 60,
				'body'     => $data->pfs,
				'blocking' => true,
				'headers'  => array(),
			);

			return wp_remote_post( $data->url, $args );
		}

		public static function auth() {
			$auth = array(
				'api' => self::$integration_id,
			);

			$data      = (object) array();
			$data->url = self::$auth_url;
			$data->pfs = $auth;

			$r = self::curlPost( $data );
			if ( ! is_wp_error( $r ) ) {
				$json = ( ! empty( $r['body'] ) ) ? json_decode( $r['body'] ) : '';
			} else {
				$json = '';
			}

			if ( isset( $json->error_code ) && $json->error_code != '0' ) {
				return $json->error_remark;
			} else {
				return 'Success.';
			}
		}

		public static function getShippingRate( $destination, $items, $weight ) {
			if ( ! class_exists( 'Easyparcel_Extend_Shipping_Method' ) ) {
				include_once 'easyparcel_shipping.php';
			}
			if ( self::countryValidate() ) {

				$bulk_order = array(
					'authentication' => self::$authentication,
					'api'            => self::$integration_id,
					'bulk'           => array()
				);

				if ( empty( $weight ) || number_format( $weight, 2 ) == 0 ) {
					$weight = 0.50;
				}

				$i      = 0;
				$length = 0;
				$width  = 0;
				$height = 0;
				foreach ( $items as $item ) {
					if ( is_numeric( $item['width'] ) && is_numeric( $item['length'] ) && is_numeric( $item['height'] ) ) {
						$length += (float) $item['length'];
						$width  += (float) $item['width'];
						$height += (float) $item['height'];
					}
					$i ++;
				}

				$Easyparcel_Extend_Shipping_Method = new Easyparcel_Extend_Shipping_Method();

				if ( $Easyparcel_Extend_Shipping_Method->settings['cust_rate'] == 'normal_rate' ) {
					self::$easyparcel_email = '';
					self::$integration_id   = '';
				}

				//prevent user select fix Rate but didnt put postcode no result
				if ( $Easyparcel_Extend_Shipping_Method->settings['cust_rate'] == 'fix_rate' && self::$sender_postcode == '' ) {
					self::$sender_postcode = '11950';
				}

				$f                    = array(
					'authentication' => self::$authentication,
					'api'            => self::$integration_id,
					'pick_country'   => strtolower( self::$sender_country ),
					'pick_code'      => self::$sender_postcode,
					'pick_state'     => self::$sender_state,
					'send_country'   => strtolower( $destination['country'] ),
					'send_state'     => ( $destination['state'] == '' ) ? ( ( $destination['country'] == 'sg' ) ? 'central' : '' ) : $destination['state'],
					'send_code'      => ( $destination['postcode'] == '' ) ? ( ( $destination['country'] == 'sg' ) ? '058275' : '' ) : $destination['postcode'],
					# required
					'weight'         => $weight,
					'width'          => $width,
					'height'         => $height,
					'length'         => $length,
				);
				$bulk_order['bulk'][] = $f;
				$data                 = (object) array();
				$data->url            = self::$getrate_bulk_api_url;
				$data->pfs            = http_build_query( $bulk_order );

				$r = self::curlPost( $data );

				if ( is_array( $r ) ) {
					$json = ( ! empty( $r['body'] ) && ! is_wp_error( $r['body'] ) ) ? json_decode( $r['body'], true ) : '';
				} else {
					$json = '';
				}

				if ( ! empty( $json ) && isset( $json['result'][0] ) ) {
					if ( ! empty( $json['result'][0]['rates'] ) ) {
						return $json['result'][0]['rates'];
					} else {
						return array();
					}
				} else {
					return array();
				}

			}

			// if no support sender country
			return array(); // return empty array
		}

		public static function submitOrder( $obj ) {
			if ( self::countryValidate() ) {

				$bulk_order = array(
					'authentication' => self::$authentication,
					'api'            => self::$integration_id,
					'bulk'           => array()
				);

				$send_point = ''; // EP Buyer Pickup Point
				if ( $obj->order->meta_exists( '_easyparcel_pickup_point_backend' ) && ! empty( $obj->order->get_meta( '_easyparcel_pickup_point_backend' ) ) ) {
					$send_point = $obj->order->get_meta( '_easyparcel_pickup_point_backend' );
				}
				$send_name    = $obj->order->get_shipping_first_name() . ' ' . $obj->order->get_shipping_last_name();
				$send_company = $obj->order->get_shipping_company();
				$send_contact = $obj->order->get_billing_phone();
				if ( version_compare( WC()->version, '5.6', '>=' ) ) {
					### WC 5.6 and above only can use shipping phone ###
					if ( ! empty( $obj->order->get_shipping_phone() ) ) {
						$send_contact = $obj->order->get_shipping_phone();
					}
				}
				$send_addr1   = $obj->order->get_shipping_address_1();
				$send_addr2   = $obj->order->get_shipping_address_2();
				$send_city    = $obj->order->get_shipping_city();
				$send_code    = ! empty( $obj->order->get_shipping_postcode() ) ? $obj->order->get_shipping_postcode() : '';
				$send_state   = ! empty( $obj->order->get_shipping_state() ) ? $obj->order->get_shipping_state() : '';
				$send_country = ! empty( $obj->order->get_shipping_country() ) ? $obj->order->get_shipping_country() : '';

				//add on email
				if ( self::$addon_email_option == 'yes' && strtolower( self::$sender_country ) != 'sg' ) {
					$send_email = $obj->order->get_billing_email();
				} else {
					$send_email = '';
				}

				//add on sms
				if ( self::$addon_sms_option == 'yes' && strtolower( self::$sender_country ) != 'sg' ) {
					$sms = 1;
				} else {
					$sms = 0;
				}

				$f                    = array(
					'authentication' => self::$authentication,
					'api'            => self::$integration_id,

					'pick_point'   => $obj->drop_off_point, # optional
					'pick_name'    => self::$sender_name,
					'pick_company' => self::$sender_company_name, # optional
					'pick_contact' => self::$sender_contact_number,
					'pick_mobile'  => self::$sender_alt_contact_number, # optional
					'pick_unit'    => self::$sender_address_1, ### for sg address only ###
					'pick_addr1'   => self::$sender_address_1,
					'pick_addr2'   => self::$sender_address_2, # optional
					'pick_addr3'   => '', # optional
					'pick_addr4'   => '', # optional
					'pick_city'    => self::$sender_city,
					'pick_code'    => self::$sender_postcode,
					'pick_state'   => self::$sender_state,
					'pick_country' => self::$sender_country,

					'send_point'   => $send_point, # optional
					'send_name'    => $send_name,
					'send_company' => $send_company, # optional
					'send_contact' => $send_contact,
					'send_mobile'  => '', # optional
					'send_unit'    => $send_addr1, ### for sg address only ###
					'send_addr1'   => ( strtolower( self::$sender_country ) == 'sg' ) ? $send_addr2 : $send_addr1,
					'send_addr2'   => $send_addr2, # optional
					'send_addr3'   => '', # optional
					'send_addr4'   => '', # optional
					'send_city'    => $send_city,
					'send_code'    => $send_code, # required
					'send_state'   => $send_state,
					'send_country' => $send_country,

					'weight'       => $obj->weight,
					'width'        => $obj->width,
					'height'       => $obj->height,
					'length'       => $obj->length,
					'content'      => $obj->content,
					'value'        => $obj->item_value,
					'service_id'   => $obj->service_id,
					'collect_date' => $obj->collect_date,
					'sms'          => $sms, # optional
					'send_email'   => $send_email, # optional
					'hs_code'      => '', # optional
					'REQ_ID'       => '', # optional
					'reference'    => '' # optional
				);
				$bulk_order['bulk'][] = $f;

				$data      = (object) array();
				$data->url = self::$submit_bulk_order_api_url;
				$data->pfs = http_build_query( $bulk_order );
				$r         = self::curlPost( $data );
				$json      = ( ! empty( $r['body'] ) ) ? json_decode( $r['body'] ) : '';
				$jsonArray = (array) $json;
				$json_size = sizeof( $jsonArray );
				if ( $json_size > 0 && isset( $json->result[0] ) ) {
					return $json->result[0];
				} else {
					return array();
				}
			}

			// if no support sender country
			return array(); // return empty array
		}

		public static function payOrder( $obj ) {
			if ( self::countryValidate() ) {

				$bulk_order = array(
					'authentication' => self::$authentication,
					'api'            => self::$integration_id,
					'bulk'           => array()
				);

				$f                    = array(
					'authentication' => self::$authentication,
					'api'            => self::$integration_id,
					'order_no'       => $obj->easyparcel_order_number,
				);
				$bulk_order['bulk'][] = $f;
				$data                 = (object) array();
				$data->url            = self::$pay_bulk_order_api_url;
				$data->pfs            = http_build_query( $bulk_order );

				$r    = self::curlPost( $data );
				$json = ( ! empty( $r['body'] ) ) ? json_decode( $r['body'] ) : '';
				if ( ! empty( $json ) ) {
					return $json;
				} else {
					return array();
				}
			}

			// if no support sender country
			return array(); // return empty array
		}

		public static function submitBulkOrder( $orders ) {
			if ( self::countryValidate() ) {

				$bulk_order = array(
					'authentication' => self::$authentication,
					'api'            => self::$integration_id,
					'bulk'           => array()
				);

				foreach ( $orders as $obj ) {
					$send_point = ''; // EP Buyer Pickup Point
					if ( $obj->order->meta_exists( '_easyparcel_pickup_point_backend' ) && ! empty( $obj->order->get_meta( '_easyparcel_pickup_point_backend' ) ) ) {
						$send_point = $obj->order->get_meta( '_easyparcel_pickup_point_backend' );
					}
					$send_name    = $obj->order->get_shipping_first_name() . ' ' . $obj->order->get_shipping_last_name();
					$send_company = $obj->order->get_shipping_company();
					$send_contact = $obj->order->get_billing_phone();
					if ( version_compare( WC()->version, '5.6', '>=' ) ) {
						### WC 5.6 and above only can use shipping phone ###
						if ( ! empty( $obj->order->get_shipping_phone() ) ) {
							$send_contact = $obj->order->get_shipping_phone();
						}
					}
					$send_addr1   = $obj->order->get_shipping_address_1();
					$send_addr2   = $obj->order->get_shipping_address_2();
					$send_city    = $obj->order->get_shipping_city();
					$send_code    = ! empty( $obj->order->get_shipping_postcode() ) ? $obj->order->get_shipping_postcode() : '';
					$send_state   = ! empty( $obj->order->get_shipping_state() ) ? $obj->order->get_shipping_state() : '';
					$send_country = ! empty( $obj->order->get_shipping_country() ) ? $obj->order->get_shipping_country() : '';

					//add on email
					if ( self::$addon_email_option == 'yes' && strtolower( self::$sender_country ) != 'sg' ) {
						$send_email = $obj->order->get_billing_email();
					} else {
						$send_email = '';
					}

					//add on sms
					if ( self::$addon_sms_option == 'yes' && strtolower( self::$sender_country ) != 'sg' ) {
						$sms = 1;
					} else {
						$sms = 0;
					}

					$f = array(
						'pick_point'   => $obj->drop_off_point, # optional
						'pick_name'    => self::$sender_name,
						'pick_company' => self::$sender_company_name, # optional
						'pick_contact' => self::$sender_contact_number,
						'pick_mobile'  => self::$sender_alt_contact_number, # optional
						'pick_unit'    => self::$sender_address_1, ### for sg address only ###
						'pick_addr1'   => self::$sender_address_1,
						'pick_addr2'   => self::$sender_address_2, # optional
						'pick_addr3'   => '', # optional
						'pick_addr4'   => '', # optional
						'pick_city'    => self::$sender_city,
						'pick_code'    => self::$sender_postcode,
						'pick_state'   => self::$sender_state,
						'pick_country' => self::$sender_country,

						'send_point'   => $send_point, # optional
						'send_name'    => $send_name,
						'send_company' => $send_company, # optional
						'send_contact' => $send_contact,
						'send_mobile'  => '', # optional
						'send_unit'    => $send_addr1, ### for sg address only ###
						'send_addr1'   => ( strtolower( self::$sender_country ) == 'sg' ) ? $send_addr2 : $send_addr1,
						'send_addr2'   => $send_addr2, # optional
						'send_addr3'   => '', # optional
						'send_addr4'   => '', # optional
						'send_city'    => $send_city,
						'send_code'    => $send_code, # required
						'send_state'   => $send_state,
						'send_country' => $send_country,

						'weight'       => $obj->weight,
						'width'        => $obj->width,
						'height'       => $obj->height,
						'length'       => $obj->length,
						'content'      => $obj->content,
						'value'        => $obj->item_value,
						'service_id'   => $obj->service_id,
						'collect_date' => $obj->collect_date,
						'sms'          => $sms, # optional
						'send_email'   => $send_email, # optional
						'hs_code'      => '', # optional
						'REQ_ID'       => '', # optional
						'reference'    => '' # optional
					);

					$bulk_order['bulk'][] = $f;

				}

				$data      = (object) array();
				$data->url = self::$submit_bulk_order_api_url;
				$data->pfs = http_build_query( $bulk_order );

				$r    = self::curlPost( $data );
				$json = ( ! empty( $r['body'] ) ) ? json_decode( $r['body'] ) : '';

				if ( ! empty( $json ) ) {
					return $json;
				} else {
					return array();
				}
			}

			// if no support sender country
			return array(); // return empty array
		}

		public static function payBulkOrder( $orders ) {
			if ( self::countryValidate() ) {

				$bulk_order = array(
					'authentication' => self::$authentication,
					'api'            => self::$integration_id,
					'bulk'           => array()
				);

				foreach ( $orders as $order_no ) {
					$f = array(
						'order_no' => $order_no,
					);

					$bulk_order['bulk'][] = $f;
				}

				$data      = (object) array();
				$data->url = self::$pay_bulk_order_api_url;
				$data->pfs = http_build_query( $bulk_order );

				$r    = self::curlPost( $data );
				$json = ( ! empty( $r['body'] ) ) ? json_decode( $r['body'] ) : '';

				if ( ! empty( $json ) ) {
					return $json;
				} else {
					return array();
				}
			}

			// if no support sender country
			return array(); // return empty array
		}
	}
}
