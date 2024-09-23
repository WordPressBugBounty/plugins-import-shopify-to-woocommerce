<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IMPORT_SHOPIFY_TO_WOOCOMMERCE_ADMIN_Error_Images {
	protected $settings;

	public function __construct() {
		$this->settings = VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA::get_instance();
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_script' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 11 );
		add_action( 'wp_ajax_s2w_download_error_product_images', array( $this, 'download_error_product_images' ) );
		add_action( 'wp_ajax_s2w_delete_error_product_images', array( $this, 'delete_error_product_images' ) );
		add_action( 'admin_head', array( $this, 'menu_product_count' ), 999 );
		add_filter( 'set-screen-option', array( $this, 'save_screen_options' ), 10, 3 );
	}

	/**
	 *
	 */
	public function menu_product_count() {
		global $submenu;
		if ( isset( $submenu['import-shopify-to-woocommerce'] ) ) {
			// Add count if user has access.
			if ( apply_filters( 's2w_import_shopify_to_woocommerce_error_images_count_in_menu', true ) && current_user_can( 'manage_options' ) ) {
				$product_count = S2W_Error_Images_Table::get_rows( 0, 0, true );
				if ( $product_count ) {
					foreach ( $submenu['import-shopify-to-woocommerce'] as $key => $menu_item ) {
						if ( 0 === strpos( $menu_item[0], _x( 'Failed Images', 'Admin menu name', 'import-shopify-to-woocommerce' ) ) ) {
							$submenu['import-shopify-to-woocommerce'][ $key ][0] .= ' <span class="update-plugins count-' . esc_attr( $product_count ) . '"><span class="' . self::set( 'error-images-count' ) . '">' . number_format_i18n( $product_count ) . '</span></span>'; // WPCS: override ok.
							break;
						}
					}
				}
			}
		}
	}

	private static function set( $name, $set_name = false ) {
		return VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA::set( $name, $set_name );
	}

	public function download_error_product_images() {
		check_ajax_referer( 's2w_action_nonce', '_s2w_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		$id       = isset( $_POST['item_id'] ) ? wp_unslash( sanitize_text_field( $_POST['item_id'] ) ) : '';
		$response = array(
			'status' => 'error',
		);
		if ( $id ) {
			$data = S2W_Error_Images_Table::get_row( $id );
			if ( count( $data ) ) {
				$product_id = $data['product_id'];
				$image_id   = isset( $data['image_id'] ) ? $data['image_id'] : '';
				$post       = get_post( $product_id );
				if ( $post && $post->post_type === 'product' ) {
					$thumb_id = VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA::download_image( $image_id, $data['image_src'], $product_id );
					if ( $thumb_id && ! is_wp_error( $thumb_id ) ) {
						if ( $image_id ) {
							update_post_meta( $thumb_id, '_s2w_shopify_image_id', $image_id );
						}
						if ( $data['set_gallery'] == 2 ) {
							$downloaded_url = wp_get_attachment_url( $thumb_id );
							$description    = str_replace( $data['image_src'], $downloaded_url, html_entity_decode( $post->post_content, ENT_QUOTES | ENT_XML1, 'UTF-8' ) );
							wp_update_post( array( 'ID' => $product_id, 'post_content' => $description ) );
						} else {
							if ( $data['image_alt'] ) {
								update_post_meta( $thumb_id, '_wp_attachment_image_alt', $data['image_alt'] );
							}
							if ( $data['product_ids'] ) {
								$product_ids = explode( ',', $data['product_ids'] );
								foreach ( $product_ids as $v_id ) {
									if ( in_array( get_post_type( $v_id ), array(
										'product',
										'product_variation'
									) ) ) {
										update_post_meta( $v_id, '_thumbnail_id', $thumb_id );
									}
								}
							}

							if ( 1 == $data['set_gallery'] ) {
								$gallery = get_post_meta( $product_id, '_product_image_gallery', true );
								if ( $gallery ) {
									$gallery_array = explode( ',', $gallery );
								} else {
									$gallery_array = array();
								}
								$gallery_array[] = $thumb_id;
								update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_unique( $gallery_array ) ) );
							}
						}

						$response['status'] = 'success';
						S2W_Error_Images_Table::delete( $id );
					} elseif ( is_wp_error( $thumb_id ) ) {
						$response['message'] = $thumb_id->get_error_code() . ' - ' . $thumb_id->get_error_message();
					}
				} else {
					$response['message'] = esc_html__( 'Product not exist', 'import-shopify-to-woocommerce' );
				}
			} else {
				$response['message'] = esc_html__( 'No data', 'import-shopify-to-woocommerce' );
			}
		}
		wp_send_json( $response );
	}

	public function delete_error_product_images() {
		check_ajax_referer( 's2w_action_nonce', '_s2w_nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die();
		}
		$id       = isset( $_POST['item_id'] ) ? wp_slash( $_POST['item_id'] ) : '';
		$response = array(
			'status'  => 'success',
			'success' => array(),
			'error'   => array(),
		);
		if ( $id ) {
			$ids = explode( ',', $id );
			foreach ( $ids as $image_id ) {
				$delete = S2W_Error_Images_Table::delete( $image_id );
				if ( $delete ) {
					$response['success'] [] = $image_id;
				} else {
					$response['error'] [] = $image_id;
				}
			}
		}
		wp_send_json( $response );
	}

	public function save_screen_options( $status, $option, $value ) {
		if ( 's2w_error_images_per_page' === $option ) {
			return $value;
		}

		return $status;
	}

	/**
	 * Add Screen Options
	 */
	public function screen_options_page() {

		$option = 'per_page';

		$args = array(
			'label'   => esc_html__( 'Number of items per page', 'wp-admin' ),
			'default' => 10,
			'option'  => 's2w_error_images_per_page'
		);

		add_screen_option( $option, $args );
	}

	public function admin_menu() {
		$import_list = add_submenu_page( 'import-shopify-to-woocommerce', esc_html__( 'Failed Images', 'import-shopify-to-woocommerce' ), esc_html__( 'Failed Images', 'import-shopify-to-woocommerce' ), 'manage_options', 'import-shopify-to-woocommerce-error-images', array(
			$this,
			'page_callback'
		) );
		add_action( "load-$import_list", array( $this, 'screen_options_page' ) );
	}

	/**
	 *
	 */
	public function page_callback() {
		$user     = get_current_user_id();
		$screen   = get_current_screen();
		$option   = $screen->get_option( 'per_page', 'option' );
		$per_page = get_user_meta( $user, $option, true );

		if ( empty ( $per_page ) || $per_page < 1 ) {
			$per_page = $screen->get_option( 'per_page', 'default' );
		}

		$paged = isset( $_GET['paged'] ) ? intval( wp_unslash( sanitize_text_field( $_GET['paged'] ) ) ) : 1;//phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'All error images', 'import-shopify-to-woocommerce' ) ?></h2>
			<?php
			$keyword = isset( $_GET['s2w_search_product'] ) ? sanitize_text_field( $_GET['s2w_search_product'] ) : '';//phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $keyword ) {
				$args['s'] = $keyword;
			}
			$count   = S2W_Error_Images_Table::get_rows( 0, 0, true );
			$results = S2W_Error_Images_Table::get_rows( $per_page, ( $paged - 1 ) * $per_page, false );
			if ( count( $results ) ) {
				ob_start();
				?>
                <form method="get">
                    <input type="hidden" name="page" value="import-shopify-to-woocommerce-error-images">
                    <div class="tablenav top">
                        <div class="tablenav-pages">
                            <div class="pagination-links">
								<?php
								$total_page = ceil( $count / $per_page );
								/*Previous button*/
								if ( $per_page * $paged > $per_page ) {
									$p_paged = $paged - 1;
								} else {
									$p_paged = 0;
								}
								if ( $p_paged ) {
									$p_url = add_query_arg(
										array(
											'page'               => 'import-shopify-to-woocommerce-error-images',
											'paged'              => $p_paged,
											's2w_search_product' => $keyword,
										), admin_url( 'admin.php' )
									);
									?>
                                    <a class="prev-page button" href="<?php echo esc_url( $p_url ) ?>"><span
                                                class="screen-reader-text"><?php esc_html_e( 'Previous Page', 'import-shopify-to-woocommerce' ) ?></span><span
                                                aria-hidden="true">‹</span></a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
									<?php
								}
								?>
                                <span class="screen-reader-text"><?php esc_html_e( 'Current Page', 'import-shopify-to-woocommerce' ) ?></span>
                                <span id="table-paging" class="paging-input">
                                    <span class="tablenav-paging-text">
                                        <input class="current-page" type="text" name="paged" size="1"
                                               value="<?php echo esc_html( $paged ) ?>"><span
                                                class="tablenav-paging-text"><?php esc_html_e( ' of ', 'import-shopify-to-woocommerce' ) ?>
                                            <span
                                                    class="total-pages"><?php echo esc_html( $total_page ) ?></span>
                                        </span>
                                    </span>
                                </span>
								<?php /*Next button*/
								if ( $per_page * $paged < $count ) {
									$n_paged = $paged + 1;
								} else {
									$n_paged = 0;
								}
								if ( $n_paged ) {
									$n_url = add_query_arg(
										array(
											'page'               => 'import-shopify-to-woocommerce-error-images',
											'paged'              => $n_paged,
											's2w_search_product' => $keyword,
										), admin_url( 'admin.php' )
									); ?>
                                    <a class="next-page button" href="<?php echo esc_url( $n_url ) ?>"><span
                                                class="screen-reader-text"><?php esc_html_e( 'Next Page', 'import-shopify-to-woocommerce' ) ?></span><span
                                                aria-hidden="true">›</span></a>
									<?php
								} else {
									?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
									<?php
								}
								?>
                            </div>
                        </div>
                        <div class="<?php echo esc_attr( self::set( 'button-all-container' ) ) ?>">
                            <span class="vi-ui button positive <?php echo esc_attr( self::set( 'action-download-all' ) ) ?>"
                                  title="<?php esc_attr_e( 'Import all failed images on current page', 'import-shopify-to-woocommerce' ) ?>"><?php esc_html_e( 'Import All', 'import-shopify-to-woocommerce' ) ?></span>
                            <span class="vi-ui button negative <?php echo esc_attr( self::set( 'action-delete-all' ) ) ?>"
                                  title="<?php esc_attr_e( 'Delete all failed images on current page', 'import-shopify-to-woocommerce' ) ?>"><?php esc_html_e( 'Delete All', 'import-shopify-to-woocommerce' ) ?></span>
                        </div>
                    </div>
                </form>
				<?php
				$pagination_html = ob_get_clean();
				echo VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA::wp_kses_post( $pagination_html );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
                <form class="vi-ui form">
                    <table class="vi-ui table">
                        <thead>
                        <tr>
                            <th><?php esc_html_e( 'Index', 'import-shopify-to-woocommerce' ) ?></th>
                            <th><?php esc_html_e( 'Product ID', 'import-shopify-to-woocommerce' ) ?></th>
                            <th><?php esc_html_e( 'Product Title', 'import-shopify-to-woocommerce' ) ?></th>
                            <th><?php esc_html_e( 'Product/Variation IDs', 'import-shopify-to-woocommerce' ) ?></th>
                            <th><?php esc_html_e( 'Image', 'import-shopify-to-woocommerce' ) ?></th>
                            <th><?php esc_html_e( 'Image alt', 'import-shopify-to-woocommerce' ) ?></th>
                            <th><?php esc_html_e( 'Set gallery', 'import-shopify-to-woocommerce' ) ?></th>
                            <th><?php esc_html_e( 'Actions', 'import-shopify-to-woocommerce' ) ?></th>
                        </tr>
                        </thead>
                        <tbody>
						<?php
						$index = ( $paged - 1 ) * $per_page;
						foreach ( $results as $key => $result ) {
							$product = wc_get_product( $result['product_id'] );
							if ( ! $product ) {
								continue;
							}
							?>
                            <tr>
                                <td>
                                    <span class="<?php echo esc_attr( self::set( 'index' ) ) ?>"><?php echo  esc_html( ++ $index ) ?></span>
                                </td>
								<?php
								foreach ( $result as $result_k => $result_v ) {
									if ( in_array( $result_k, array( 'id', 'image_id' ) ) ) {
										continue;
									}
									?>
                                    <td>
										<?php
										if ( $result_k === 'image_src' ) {
											?>
                                            <img width="48" height="48" src="<?php echo esc_attr( $result_v ) ?>">
											<?php
										} else {
											echo esc_html( $result_v );
										}
										?>
                                    </td>
									<?php
									if ( $result_k === 'product_id' ) {
										?>
                                        <td><a target="_blank"
                                               href="<?php echo esc_attr( admin_url( 'post.php?action=edit&post=' . $result['product_id'] ) ) ?>"><?php echo esc_html( $product->get_title() ) ?></a>
                                        </td>
										<?php
									}
								}
								?>
                                <td class="<?php echo esc_attr( self::set( 'actions-container' ) ) ?>">
                                        <span class="vi-ui positive button <?php echo esc_attr( self::set( 'action-download' ) ) ?>"
                                              data-item_id="<?php echo esc_attr( $result['id'] ) ?>"><?php esc_html_e( 'Import', 'import-shopify-to-woocommerce' ) ?></span>
                                    <span class="vi-ui negative button <?php echo esc_attr( self::set( 'action-delete' ) ) ?>"
                                          data-item_id="<?php echo esc_attr( $result['id'] ) ?>"><?php esc_html_e( 'Delete', 'import-shopify-to-woocommerce' ) ?></span>
                                </td>
                            </tr>
							<?php
						}
						?>
                        </tbody>
                    </table>
                </form>
				<?php
				echo VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_DATA::wp_kses_post( $pagination_html );//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				?>
                <div class="vi-ui segment">
                    <p>
						<?php esc_html_e( 'You don\'t have any error images. ', 'import-shopify-to-woocommerce' ) ?>
                    </p>
                </div>
				<?php
			}
			?>
        </div>
		<?php
	}

	public function enqueue_semantic() {
		wp_dequeue_script( 'select-js' );//Causes select2 error, from ThemeHunk MegaMenu Plus plugin
		wp_dequeue_style( 'eopa-admin-css' );
		/*Stylesheet*/
		wp_enqueue_style( 'import-shopify-to-woocommerce-form', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'form.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
		wp_enqueue_style( 'import-shopify-to-woocommerce-table', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'table.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
		wp_enqueue_style( 'import-shopify-to-woocommerce-icon', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'icon.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
		wp_enqueue_style( 'import-shopify-to-woocommerce-segment', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'segment.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
		wp_enqueue_style( 'import-shopify-to-woocommerce-button', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'button.min.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
		wp_enqueue_style( 'select2', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'select2.min.css','',VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
		if ( woocommerce_version_check( '3.0.0' ) ) {
			wp_enqueue_script( 'select2' );
		} else {
			wp_enqueue_script( 'select2-v4', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_JS . 'select2.js', array( 'jquery' ), '4.0.3', true );
		}
	}

	public function admin_enqueue_script( $page ) {
		if ( $page === 'shopify-to-woo_page_import-shopify-to-woocommerce-error-images' ) {
			$this->enqueue_semantic();
			wp_enqueue_style( 'import-shopify-to-woocommerce-error-images', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_CSS . 'error-images.css', '', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION );
			wp_enqueue_script( 'import-shopify-to-woocommerce-error-images', VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_JS . 'error-images.js', array( 'jquery' ), VI_IMPORT_SHOPIFY_TO_WOOCOMMERCE_VERSION, true );
			wp_localize_script( 'import-shopify-to-woocommerce-error-images', 's2w_params_admin_error_images', array(
				'url'                     => admin_url( 'admin-ajax.php' ),
				'i18n_confirm_delete'     => esc_html__( 'Are you sure you want to delete this?', 'import-shopify-to-woocommerce' ),
				'i18n_confirm_delete_all' => esc_html__( 'Are you sure you want to delete all items on this page?', 'import-shopify-to-woocommerce' ),
				'i18n_confirm_empty_list' => esc_html__( 'This will remove all Failed images from database, do you want to continue?', 'import-shopify-to-woocommerce' ),
				'_s2w_nonce'              => wp_create_nonce( 's2w_action_nonce' ),
			) );
		}
	}
}

