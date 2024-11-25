<?php

if ( ! defined( 'ABSPATH' ) ) {
exit;
}
/**
 * Sync products manually from admin Products table page
 */
if ( ! class_exists( 'IMPORT_SHOPIFY_TO_WOOCOMMERCE_ADMIN_Update_Products' ) ) {
	class IMPORT_SHOPIFY_TO_WOOCOMMERCE_ADMIN_Update_Products {
		public function __construct() {
			add_filter( 'manage_edit-product_columns', array( $this, 'button_update_from_shopify' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_script' ) );
		}

		public static function set( $name, $set_name = false ) {
			return VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA::set( $name, $set_name );
		}

		public function button_update_from_shopify( $cols ) {
			$cols['s2w_update_from_shopify'] = '<span class="s2w-button ' . self::set( 'shopify-update-product' ) . '">' . esc_html__( 'Shopify sync', 'import-shopify-to-woocommerce' ) . '</span>';

			return $cols;
		}

		public function admin_enqueue_script() {
			global $pagenow;
			$post_type = isset( $_REQUEST['post_type'] ) ? sanitize_text_field( $_REQUEST['post_type'] ) : '';// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $pagenow === 'edit.php' && $post_type === 'product' ) {
				wp_enqueue_style( 's2w-import-shopify-to-woocommerce-update-product', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'update-product.css',array(),VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
				wp_enqueue_script( 's2w-import-shopify-to-woocommerce-update-product', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_JS . 'update-products.js', array( 'jquery' ), VI_S2W_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION ,true);
                add_action( 'admin_footer', array( $this, 'wp_footer' ) );
			}
		}

		public static function get_supported_options() {
			return array(
				'title'                => esc_html__( 'Title', 'import-shopify-to-woocommerce' ),
				'price'                => esc_html__( 'Price', 'import-shopify-to-woocommerce' ),
				'inventory'            => esc_html__( 'Inventory', 'import-shopify-to-woocommerce' ),
				'description'          => esc_html__( 'Description', 'import-shopify-to-woocommerce' ),
				'product_status'       => esc_html__( 'Status', 'import-shopify-to-woocommerce' ),
				'images'               => esc_html__( 'Images', 'import-shopify-to-woocommerce' ),
				'variations'           => esc_html__( 'Variations', 'import-shopify-to-woocommerce' ),
				'variation_attributes' => esc_html__( 'Variation attributes', 'import-shopify-to-woocommerce' ),
				'variation_sku'        => esc_html__( 'Variation SKU', 'import-shopify-to-woocommerce' ),
				'product_url'          => esc_html__( 'Product slug', 'import-shopify-to-woocommerce' ),
				'tags'                 => esc_html__( 'Tags', 'import-shopify-to-woocommerce' ),
				'published_date'       => esc_html__( 'Published date', 'import-shopify-to-woocommerce' ),
				'barcode'              => esc_html__( 'Barcode', 'import-shopify-to-woocommerce' ),
				'metafields'           => esc_html__( 'Metafields', 'import-shopify-to-woocommerce' ),
			);
		}

		public function wp_footer() {
			$all_options    = self::get_supported_options();
			$update_options = array('title', 'price');
			?>
			<div class="<?php echo esc_attr( self::set( array(
				'update-product-options-container',
				'hidden'
			) ) ) ?>">
				<?php wp_nonce_field( 's2w_update_product_options_action_nonce', '_s2w_update_product_options_nonce' ) ?>
				<div class="<?php echo esc_attr( self::set( 'overlay' ) ) ?>"></div>
				<div class="<?php echo esc_attr( self::set( 'update-product-options-content' ) ) ?>">
					<div class="<?php echo esc_attr( self::set( 'update-product-options-content-header' ) ) ?>">
						<h2><?php esc_html_e( 'Sync options', 'import-shopify-to-woocommerce' ) ?></h2>
						<span class="<?php echo esc_attr( self::set( 'update-product-options-close' ) ) ?>"></span>
					</div>
					<div class="<?php echo esc_attr( self::set( 'update-product-options-content-body' ) ) ?>">
						<?php
						foreach ( $all_options as $option_key => $option_value ) {
							?>
							<div class="<?php echo esc_attr( self::set( 'update-product-options-content-body-row' ) ) ?>">
								<div class="<?php echo esc_attr( self::set( 'update-product-options-option-wrap' ) ) ?>">
									<input type="checkbox" value="1"
									       data-product_option="<?php echo esc_attr( $option_key ) ?>"
										<?php if ( in_array( $option_key, $update_options ) ) {
											echo esc_attr( 'checked' );
										} ?>
										   id="<?php echo esc_attr( self::set( 'update-product-options-' . $option_key ) ) ?>"
										   class="<?php echo esc_attr( self::set( 'update-product-options-option' ) ) ?>">
									<label for="<?php echo esc_attr( self::set( 'update-product-options-' . $option_key ) ) ?>"><?php echo esc_html( $option_value ) ?></label>
								</div>
							</div>
							<?php
						}
						$from = $to = array( '' );
						?>
						<table class="<?php echo esc_attr( self::set( 'product-metafields-mapping' ) ) ?> wp-list-table widefat fixed striped">
							<thead>
							<tr>
								<th><?php esc_html_e( 'Shopify metafield key', 'import-shopify-to-woocommerce' ) ?>
									<span class="button-primary <?php echo esc_attr( self::set( array(
										'update-product-options-button-get-metafields',
										'button',
										'hidden'
									) ) ) ?>"
									      title="<?php esc_attr_e( 'Get all Shopify metafield keys of current product', 'import-shopify-to-woocommerce' ) ?>"
									      data-update_product_id=""><?php esc_html_e( 'Get', 'import-shopify-to-woocommerce' ) ?>
                                    </span>
								</th>
								<th><?php esc_html_e( 'Woo product meta key', 'import-shopify-to-woocommerce' ) ?></th>
								<th></th>
							</tr>
							</thead>
							<tbody>
							<?php
							for ( $i = 0; $i < count( $from ); $i ++ ) {
								?>
								<tr>
									<td>
										<input type="text"
										       class="<?php echo esc_attr( self::set( 'update_product_metafields_from' ) ) ?>"
										       name="<?php echo esc_attr( self::set( 'update_product_metafields[from][]', true ) ) ?>"
										       value="<?php echo esc_attr( $from[ $i ] ) ?>">
									</td>
									<td>
										<input type="text"
										       class="<?php echo esc_attr( self::set( 'update_product_metafields_to' ) ) ?>"
										       name="<?php echo esc_attr( self::set( 'update_product_metafields[to][]', true ) ) ?>"
										       value="<?php echo esc_attr( $to[ $i ] ) ?>">
									</td>
									<td>
										<div>
											<i class="dashicons dashicons-admin-page <?php echo esc_attr( self::set( array(
												'product-metafields-button',
												'product-metafields-duplicate'
											) ) ) ?>"></i>
											<i class="dashicons dashicons-trash <?php echo esc_attr( self::set( array(
												'product-metafields-button',
												'product-metafields-remove'
											) ) ) ?>"></i>
										</div>
									</td>
								</tr>
								<?php
							}
							?>
							</tbody>
						</table>
						<?php
						if ( class_exists( 'Viw2s_Pro' ) ) {
							?>
							<div class="<?php echo esc_attr( 's2w-update-product-options-content-body-row s2w-update-compa-w2s' ) ?>">
								<div class="<?php echo esc_attr( 's2w-update-product-options-option-wrap' ) ?>">
									<input type="checkbox" value="1"
									       data-product_option="domain"
									       id="<?php echo esc_attr( 's2w-update-product-options-domain' ); ?>"
									       class="<?php echo esc_attr( 's2w-update-product-options-option' ); ?>">
									<label for="<?php echo esc_attr( 's2w-update-product-options-domain' ); ?>"><?php echo esc_html( 'Sync Domain ' ) ?></label>
									<div class="<?php echo esc_attr( 's2w-option-description' ) ?>"> <?php esc_html_e( 'Enable this option to sync product/order data imported from the previous version 1.2', 'import-shopify-to-woocommerce' ) ?></div>
								</div>
							</div>
							<?php
						}
						?>
					</div>
					<div class="<?php echo esc_attr( self::set( 'update-product-options-content-body-1' ) ) ?>">
						<div class="<?php echo esc_attr( self::set( 'update-product-options-content-body-row' ) ) ?>">
							<input type="checkbox" value="1"
								   id="<?php echo esc_attr( self::set( 'update-product-options-show' ) ) ?>"
								   class="<?php echo esc_attr( self::set( 'update-product-options-show' ) ) ?>">
							<label for="<?php echo esc_attr( self::set( 'update-product-options-show' ) ) ?>"><?php esc_html_e( 'Show these options when clicking on "Sync" button on each product', 'import-shopify-to-woocommerce' ) ?></label>
						</div>
					</div>
					<div class="<?php echo esc_attr( self::set( 'update-product-options-content-footer' ) ) ?>">
                        <?php IMPORT_SHOPIFY_TO_WOOCOMMERCE::upgrade_button(); ?>
						<span class="<?php echo esc_attr( self::set( array(
							'update-product-options-button-cancel',
							'button'
						) ) ) ?>">
                            <?php esc_html_e( 'Cancel', 'import-shopify-to-woocommerce' ) ?>
                        </span>
					</div>
				</div>
				<div class="<?php echo esc_attr( self::set( 'saving-overlay' ) ) ?>"></div>
			</div>
			<?php
		}
	}
}