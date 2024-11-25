<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'IMPORT_SHOPIFY_TO_WOOCOMMERCE_ADMIN_Import_By_Id' ) ) {
	class IMPORT_SHOPIFY_TO_WOOCOMMERCE_ADMIN_Import_By_Id {
		protected static $settings;

		public function __construct() {
			self::$settings = VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA::get_instance();
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 18 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 1 );
		}

		/**
		 *
		 */
		public function admin_enqueue_scripts() {
			global $pagenow;
			$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( $_REQUEST['page'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $pagenow === 'admin.php' && $page === 'import-shopify-to-woocommerce-import-by-id' ) {
				wp_dequeue_script( 'select-js' );//Causes select2 error, from ThemeHunk MegaMenu Plus plugin
				wp_dequeue_style( 'eopa-admin-css' );
				global $wp_scripts;
				$scripts = $wp_scripts->registered;
				foreach ( $scripts as $k => $script ) {
					preg_match( '/select2/i', $k, $result );
					if ( count( array_filter( $result ) ) ) {
						unset( $wp_scripts->registered[ $k ] );
						wp_dequeue_script( $script->handle );
					}
					preg_match( '/bootstrap/i', $k, $result );
					if ( count( array_filter( $result ) ) ) {
						unset( $wp_scripts->registered[ $k ] );
						wp_dequeue_script( $script->handle );
					}
				}
				wp_enqueue_script( 'import-shopify-to-woocommerce-semantic-js-accordion', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_JS . 'accordion.min.js', array( 'jquery' ), VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION, true );
				wp_enqueue_style( 'import-shopify-to-woocommerce-semantic-css-accordion', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'accordion.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_style( 'import-shopify-to-woocommerce-semantic-css-button', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'button.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_script( 'import-shopify-to-woocommerce-semantic-js-checkbox', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_JS . 'checkbox.min.js', array( 'jquery' ), VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION, true );
				wp_enqueue_style( 'import-shopify-to-woocommerce-semantic-css-dropdown', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'dropdown.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_script( 'import-shopify-to-woocommerce-dropdown', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_JS . 'dropdown.min.js', array( 'jquery' ), VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION, true );
				wp_enqueue_style( 'import-shopify-to-woocommerce-semantic-css-form', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'form.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_script( 'import-shopify-to-woocommerce-semantic-js-form', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_JS . 'form.min.js', array( 'jquery' ), VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION, true );
				wp_enqueue_style( 'import-shopify-to-woocommerce-semantic-css-input', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'input.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_style( 'import-shopify-to-woocommerce-semantic-icon-css', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'icon.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_style( 'import-shopify-to-woocommerce-semantic-css-label', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'label.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_style( 'import-shopify-to-woocommerce-semantic-css-segment', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'segment.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_style( 'import-shopify-to-woocommerce-semantic-css-transition', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'transition.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_script( 'import-shopify-to-woocommerce-semantic-js-transition', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_JS . 'transition.min.js', array( 'jquery' ), VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION, true );

				wp_enqueue_style( 'import-shopify-to-woocommerce-import-by-id', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'import-by-id.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_script( 'import-shopify-to-woocommerce-import-by-id', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_JS . 'webhooks.js', array( 'jquery' ), VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION, true );
			}
		}

		/**
		 * @param $name
		 * @param bool $set_name
		 *
		 * @return string
		 */
		protected static function set( $name, $set_name = false ) {
			return VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA::set( $name, $set_name );
		}

		public function admin_menu() {
			add_submenu_page(
				'import-shopify-to-woocommerce',
				esc_html__( 'Import by ID', 'import-shopify-to-woocommerce' ),
				esc_html__( 'Import by ID', 'import-shopify-to-woocommerce' ),
				'manage_options',
				'import-shopify-to-woocommerce-import-by-id',
				array( $this, 'page_callback_import_by_id' )
			);
		}

		/**
		 *
		 */
		public function page_callback_import_by_id() {
			?>
            <div class="wrap">
                <h2><?php esc_html_e( 'Import products, orders or customers by ID', 'import-shopify-to-woocommerce' ) ?></h2>
                <div class="vi-ui form">
                    <div class="vi-ui segment">
                        <div class="vi-ui labeled fluid input">
                            <div class="vi-ui left label">
                                <select id="<?php echo esc_attr( self::set( 'import-item-type' ) ) ?>"
                                        class="vi-ui fluid dropdown">
									<?php
									foreach (
										array(
											'products'  => esc_html__( 'Products', 'import-shopify-to-woocommerce' ),
											'orders'    => esc_html__( 'Orders', 'import-shopify-to-woocommerce' ),
											'customers' => esc_html__( 'Customers', 'import-shopify-to-woocommerce' ),
										) as $key => $value
									) {
										?>
                                        <option value="<?php echo esc_attr( $key ) ?>"><?php echo esc_html( $value ) ?></option>
										<?php
									}
									?>
                                </select>
                            </div>
                            <input type="text" id="<?php echo esc_attr( self::set( 'shopify-item-id' ) ) ?>">
                        </div>
                        <p><?php esc_html_e( 'Enter ids of Shopify items separated by "," to import.', 'import-shopify-to-woocommerce' ) ?></p>
                        <p>
							<?php IMPORT_SHOPIFY_TO_WOOCOMMERCE::upgrade_button(); ?>
                        </p>
                    </div>
                </div>
            </div>
			<?php
		}
	}
}
