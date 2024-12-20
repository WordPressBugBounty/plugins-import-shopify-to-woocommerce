<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA {
	private $params;
	private $default;
	private static $prefix;
	protected $my_options;
	protected static $instance = null;
	protected static $allow_html = null;

	/**
	 * VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA constructor.
	 * Init setting
	 */
	public function __construct() {
		self::$prefix = 's2w-';
		global $s2w_settings;
		if ( ! $s2w_settings ) {
			$s2w_settings = get_option( 's2w_params', array() );
		}
		$this->default = array(
			'domain'                          => '',
			'access_token'                    => '',
			'api_key'                         => '',
			'api_secret'                      => '',
			'download_images'                 => '1',
			'disable_background_process'      => '',
			'product_status'                  => 'publish',
			'product_categories'              => array(),
			'number'                          => '',
			'validate'                        => '',
			'request_timeout'                 => '600',
			'products_per_request'            => '5',
			'product_import_sequence'         => 'title asc',
			'cron_update_products'            => 0,
			'cron_update_products_options'    => array( 'inventory' ),
			'cron_update_products_status'     => array( 'publish' ),
			'cron_update_products_categories' => array(),
			'cron_update_products_interval'   => 1,
			'cron_update_products_hour'       => 0,
			'cron_update_products_minute'     => 0,
			'cron_update_products_second'     => 0,

			'cron_update_orders'              => 0,
			'cron_update_orders_options'      => array( 'status' ),
			'cron_update_orders_status'       => array( 'wc-pending', 'wc-on-hold', 'wc-processing' ),
			'cron_update_orders_range'        => 30,
			'cron_update_orders_interval'     => 1,
			'cron_update_orders_hour'         => 0,
			'cron_update_orders_minute'       => 0,
			'cron_update_orders_second'       => 0,
			'webhooks_shared_secret'          => '',
			'webhooks_orders_enable'          => '',
			'webhooks_orders_create_customer' => '',
			'webhooks_orders_options'         => array(
				'order_status',
			),
			'webhooks_order_status_mapping'   => $this->get_params( 'order_status_mapping' ),
			'webhooks_products_enable'        => '',
			'webhooks_products_options'       => array(
				'inventory',
			),
			'webhooks_customers_enable'       => '',
		);

		$this->params = apply_filters( 's2w_params', wp_parse_args( $s2w_settings, $this->default ) );
	}

	public static function get_instance( $new = false ) {
		if ( $new || null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function get_params( $name = "" ) {
		if ( ! $name ) {
			return $this->params;
		} elseif ( isset( $this->params[ $name ] ) ) {
			return apply_filters( 's2w_params' . $name, $this->params[ $name ] );
		} else {
			return false;
		}
	}

	public function get_default( $name = "" ) {
		if ( ! $name ) {
			return $this->default;
		} elseif ( isset( $this->default[ $name ] ) ) {
			return apply_filters( 's2w_params_default' . $name, $this->default[ $name ] );
		} else {
			return false;
		}
	}

	public static function set( $name, $set_name = false ) {
		if ( is_array( $name ) ) {
			return implode( ' ', array_map( array( 'VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA', 'set' ), $name ) );
		} else {
			if ( $set_name ) {
				return esc_attr( str_replace( '-', '_', self::$prefix . $name ) );

			} else {
				return esc_attr( self::$prefix . $name );

			}
		}
	}

	public static function get_option( $option_name, $default = false ) {
		return get_option( $option_name, $default );
	}

	public static function update_option( $option_name, $option_value ) {
		return update_option( $option_name, $option_value );
	}

	public static function delete_option( $option_name ) {
		return delete_option( $option_name );
	}

	/**
	 * @param $files
	 */
	public static function delete_files( $files ) {
		if ( is_array( $files ) ) {
			if ( count( $files ) ) {
				foreach ( $files as $file ) { // iterate files
					if ( is_file( $file ) ) {
						wp_delete_file( $file );//phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
					} // delete file
				}
			}
		} elseif ( is_file( $files ) ) {
			wp_delete_file( $files );//phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		}
	}

	public static function deleteDir( $dirPath ) {
		if ( is_dir( $dirPath ) ) {
			if ( substr( $dirPath, strlen( $dirPath ) - 1, 1 ) != '/' ) {
				$dirPath .= '/';
			}
			$files = glob( $dirPath . '*', GLOB_MARK );
			foreach ( $files as $file ) {
				if ( is_dir( $file ) ) {
					self::deleteDir( $file );
				} else {
					wp_delete_file( $file );//phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				}
			}
			rmdir( $dirPath );//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}
	}

	protected static function create_plugin_cache_folder() {
		if ( ! is_dir( VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CACHE ) ) {
			wp_mkdir_p( VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CACHE );
			file_put_contents( VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CACHE . '.htaccess', //phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				'<IfModule !mod_authz_core.c>
Order deny,allow
Deny from all
</IfModule>
<IfModule mod_authz_core.c>
  <RequireAll>
    Require all denied
  </RequireAll>
</IfModule>
' );//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	public static function create_cache_folder( $path ) {
		self::create_plugin_cache_folder();
		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
		}
	}

	public static function get_cache_path( $domain, $access_token, $api_key, $api_secret ) {
		if ( $access_token ) {
			return VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CACHE . md5( $access_token ) . '_' . md5( $domain ) . '_' . $domain;
		} else {
			return VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CACHE . md5( $api_key ) . '_' . md5( $api_secret ) . '_' . $domain;
		}
	}

	public static function log( $log_file, $logs_content ) {
		$logs_content = PHP_EOL . "[" . date( "Y-m-d H:i:s" ) . "] " . $logs_content;//phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		if ( is_file( $log_file ) ) {
			file_put_contents( $log_file, $logs_content, FILE_APPEND );//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		} else {
			file_put_contents( $log_file, $logs_content );//phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	public static function sku_exists( $sku = '' ) {
		global $wpdb;
		$sku_exists = false;
		if ( $sku ) {
			/*Not sure which method is faster
			$id_from_sku = wc_get_product_id_by_sku( $sku );
			$product     = $id_from_sku ? wc_get_product( $id_from_sku ) : false;
			$sku_exists  = $product && 'importing' !== $product->get_status();
			*/
			$table_posts    = "{$wpdb->prefix}posts";
			$table_postmeta = "{$wpdb->prefix}postmeta";
			$query          = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type in ('product','product_variation') and {$table_posts}.post_status in ('publish','draft','private','pending') and {$table_postmeta}.meta_key = '_sku' and {$table_postmeta}.meta_value = %s";
			$results        = $wpdb->get_var( $wpdb->prepare( $query, $sku ) );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( intval( $results ) > 0 ) {
				$sku_exists = true;
			}
		}

		return $sku_exists;
	}

	/**
	 * @param $shopify_id
	 * @param bool $is_variation
	 * @param bool $count
	 * @param bool $multiple
	 *
	 * @return array|null|object|string
	 */
	public static function product_get_woo_id_by_shopify_id( $shopify_id, $is_variation = false, $count = false, $multiple = false ) {
		global $wpdb;
		if ( $shopify_id ) {
			$table_posts    = "{$wpdb->prefix}posts";
			$table_postmeta = "{$wpdb->prefix}postmeta";
			if ( $is_variation ) {
				$post_type = 'product_variation';
				$meta_key  = '_shopify_variation_id';
			} else {
				$post_type = 'product';
				$meta_key  = '_shopify_product_id';
			}
			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_posts}.post_status != 'trash' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				$results = $wpdb->get_var( $wpdb->prepare( $query, $shopify_id ) );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$query = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_posts}.post_status != 'trash' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				if ( $multiple ) {
					$results = $wpdb->get_results( $wpdb->prepare( $query, $shopify_id ), ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				} else {
					$results = $wpdb->get_var( $wpdb->prepare( $query, $shopify_id ), 1 );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}

			return $results;
		} else {
			return false;
		}
	}

	public static function query_get_id_by_shopify_id( $shopify_id, $type = 'order', $count = false, $multiple = false, $meta_key = '' ) {
		global $wpdb;
		if ( $shopify_id ) {
			$table_posts    = "{$wpdb->prefix}posts";
			$table_postmeta = "{$wpdb->prefix}postmeta";
			switch ( $type ) {
				case 'image':
					$post_type = 'attachment';
					break;
				case 'post':
					$post_type = 'post';
					break;
				case 'coupon':
					$post_type = 'shop_coupon';
					break;
				case 'order':
				default:
					$post_type = 'shop_order';
			}
			if ( ! $meta_key ) {
				$meta_key = "_s2w_shopify_{$type}_id";
			}

			if ( $count ) {
				$query   = "SELECT count(*) from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				$results = $wpdb->get_var( $wpdb->prepare( $query, $shopify_id ) );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$query = "SELECT {$table_postmeta}.* from {$table_postmeta} join {$table_posts} on {$table_postmeta}.post_id={$table_posts}.ID where {$table_posts}.post_type = '{$post_type}' and {$table_postmeta}.meta_key = '{$meta_key}' and {$table_postmeta}.meta_value = %s";
				if ( $multiple ) {
					$results = $wpdb->get_results( $wpdb->prepare( $query, $shopify_id ), ARRAY_A );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				} else {
					$results = $wpdb->get_var( $wpdb->prepare( $query, $shopify_id ), 1 );//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}

			return $results;
		} else {
			return false;
		}
	}

	public static function implode_args( $args ) {
		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) ) {
				$args[ $key ] = implode( ',', $value );
			}
		}

		return $args;
	}

	public static function get_access_scopes( $domain, $access_token, $api_key, $api_secret ) {
		if ( $access_token ) {
			$url     = "https://{$domain}/admin/oauth/access_scopes.json";
			$headers = array( 'X-Shopify-Access-Token' => $access_token );
		} else {
			$url     = "https://{$api_key}:{$api_secret}@{$domain}/admin/oauth/access_scopes.json";
			$headers = array( 'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $api_secret ) );
		}
		$request = wp_remote_get(
			$url, array(
				'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
				'timeout'    => 10,
				'headers'    => $headers,
			)
		);
		$return  = array(
			'status' => 'error',
			'data'   => '',
			'code'   => '',
		);
		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) ) {
				$return['code'] = $request['response']['code'];
			}
			$body = vi_s2w_json_decode( $request['body'] );
			if ( isset( $body['errors'] ) ) {
				$return['data'] = $body['errors'];
			} else {
				$return['status'] = 'success';
				$return['data']   = $body['access_scopes'];
			}
		} else {
			$return['data'] = $request->get_error_message();
			$return['code'] = $request->get_error_code();
		}

		return $return;
	}

	public static function wp_remote_get( $domain, $access_token, $api_key, $api_secret, $type = 'products', $count = false, $args = array(), $timeout = 300, $return_pagination_link = false, $version = VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_REST_ADMIN_VERSION ) {
		$args = self::implode_args( wp_parse_args( $args, array( 'limit' => 250 ) ) );
		if ( $access_token ) {
			$url     = "https://{$domain}/admin";
			$headers = array(
				'X-Shopify-Access-Token' => $access_token,
			);
		} else {
			$url     = "https://{$api_key}:{$api_secret}@{$domain}/admin";
			$headers = array(
				'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $api_secret ),
			);
		}
		if ( $version ) {
			$url .= "/api/{$version}";
		}
		if ( $count ) {
			$url .= "/{$type}/count.json";
		} else {
			$url .= "/{$type}.json";
		}
		$url     = add_query_arg( $args, $url );
		$request = wp_remote_get(
			$url, array(
				'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3163.100 Safari/537.36',
				'timeout'    => $timeout,
				'headers'    => $headers,
			)
		);
		$return  = array(
			'status' => 'error',
			'data'   => '',
			'code'   => '',
		);
		if ( ! is_wp_error( $request ) ) {
			if ( $return_pagination_link ) {
				$return['pagination_link'] = self::get_pagination_link( $request );
			}
			$body = json_decode( $request['body'], true, 512, JSON_BIGINT_AS_STRING );
			if ( isset( $body['errors'] ) ) {
				$return['data'] = $body['errors'];
			} else {
				$return['status'] = 'success';
				if ( $count ) {
					$return['data'] = absint( $body['count'] );
				} else {
					if ( ! empty( $args['ids'] ) && ! is_array( $args['ids'] ) ) {
						$return['data'] = isset( $body[ $type ][0] ) ? $body[ $type ][0] : array();
					} else {
						$return['data'] = $body[ $type ];
					}
				}
			}
		} else {
			$return['data'] = $request->get_error_message();
		}

		return $return;
	}

	/**
	 * @param $request
	 *
	 * @return mixed|string
	 */
	public static function get_pagination_link( $request ) {
		$link      = wp_remote_retrieve_header( $request, 'link' );
		$page_link = array( 'previous' => '', 'next' => '' );
		if ( $link ) {
			$links = explode( ',', $link );
			foreach ( $links as $url ) {
				$params = wp_parse_url( $url );
				parse_str( $params['query'], $query );
				if ( ! empty( $query['page_info'] ) ) {
					$query_params = explode( '>;', $query['page_info'] );
					if ( trim( $query_params[1] ) === 'rel="next"' ) {
						$page_link['next'] = $query_params[0];
					} else {
						$page_link['previous'] = $query_params[0];
					}
				}
			}
		}

		return $page_link;
	}

	public static function download_image( &$shopify_id, $url, $post_parent = 0, $exclude = array(), $post_title = '', $desc = null ) {
		global $wpdb;
		$new_url   = $url;
		$parse_url = wp_parse_url( $new_url );
		$scheme    = empty( $parse_url['scheme'] ) ? 'http' : $parse_url['scheme'];
		$image_id  = "{$parse_url['host']}{$parse_url['path']}";
		$new_url   = "{$scheme}://{$image_id}";
		$reg       = self::get_image_file_extension_reg();
		preg_match( $reg, $new_url, $matches );
		if ( ! is_array( $matches ) || ! count( $matches ) ) {
			preg_match( $reg, $url, $matches );
			if ( is_array( $matches ) && count( $matches ) ) {
				$new_url  .= "?{$matches[0]}";
				$image_id .= "?{$matches[0]}";
			}
		}
		if ( ! $shopify_id ) {
			$shopify_id = $image_id;
		}
		$thumb_id = self::query_get_id_by_shopify_id( $shopify_id, 'image' );
		if ( ! $thumb_id ) {
			$thumb_id = s2w_upload_image( $new_url, $post_parent, $exclude, $post_title, $desc );
		} elseif ( $post_parent ) {
			$table_postmeta = "{$wpdb->prefix}posts";
			$wpdb->query( $wpdb->prepare( "UPDATE {$table_postmeta} set post_parent=%s WHERE ID=%s AND post_parent = 0 LIMIT 1", array( $post_parent, $thumb_id ) ) );//phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return $thumb_id;
	}

	public static function sanitize_taxonomy_name( $name ) {
		return strtolower( urlencode( wc_sanitize_taxonomy_name( $name ) ) );
	}

	public static function wp_kses_post( $content ) {
		if ( self::$allow_html === null ) {
			self::$allow_html = wp_kses_allowed_html( 'post' );
			self::$allow_html = array_merge_recursive( self::$allow_html, array(
					'input'  => array(
						'type'         => 1,
						'id'           => 1,
						'name'         => 1,
						'class'        => 1,
						'placeholder'  => 1,
						'autocomplete' => 1,
						'style'        => 1,
						'value'        => 1,
						'size'         => 1,
						'checked'      => 1,
						'disabled'     => 1,
						'readonly'     => 1,
						'data-*'       => 1,
					),
					'form'   => array(
						'method' => 1,
						'id'     => 1,
						'class'  => 1,
						'action' => 1,
						'data-*' => 1,
					),
					'select' => array(
						'id'       => 1,
						'name'     => 1,
						'class'    => 1,
						'multiple' => 1,
						'data-*'   => 1,
					),
					'option' => array(
						'value'    => 1,
						'selected' => 1,
						'data-*'   => 1,
					),
				)
			);
			foreach ( self::$allow_html as $key => $value ) {
				if ( $key === 'input' ) {
					self::$allow_html[ $key ]['data-*']   = 1;
					self::$allow_html[ $key ]['checked']  = 1;
					self::$allow_html[ $key ]['disabled'] = 1;
					self::$allow_html[ $key ]['readonly'] = 1;
				} elseif ( in_array( $key, array( 'div', 'span', 'a', 'form', 'select', 'option', 'tr', 'td' ) ) ) {
					self::$allow_html[ $key ]['data-*'] = 1;
				}
			}
		}

		return wp_kses( $content, self::$allow_html );
	}

	public static function get_image_file_extension_reg() {
		return '/[^\?]+\.(jpg|JPG|jpeg|JPEG|jpe|JPE|gif|GIF|png|PNG|webp|WEBP|heic|HEIC)/';
	}

	public static function get_functionality() {
		return array(
			'change_settings' => esc_html__( 'Change the plugin\'s main settings and import data via API', 'import-shopify-to-woocommerce' ),
			'import_csv'      => esc_html__( 'Import CSV', 'import-shopify-to-woocommerce' ),
			'cron_products'   => esc_html__( 'Change Cron products sync', 'import-shopify-to-woocommerce' ),
			'cron_orders'     => esc_html__( 'Change Cron orders sync', 'import-shopify-to-woocommerce' ),
			'webhooks'        => esc_html__( 'Change webhooks settings', 'import-shopify-to-woocommerce' ),
			'import_by_id'    => esc_html__( 'Import by ID', 'import-shopify-to-woocommerce' ),
			'failed_images'   => esc_html__( 'Failed Images', 'import-shopify-to-woocommerce' ),
			'access_logs'     => esc_html__( 'Logs', 'import-shopify-to-woocommerce' ),
		);
	}

	public static function get_capabilities( $functionality ) {
		return array(
			'manage_options',
			'manage_woocommerce'
		);
	}
}
