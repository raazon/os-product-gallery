<?php
/**
 * Ajax handlers.
 *
 * @package OS_Product_Gallery
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles front-end Ajax requests.
 */
class OS_Product_Gallery_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$actions = array(
			'ospg_filter_products' => 'filter_products',
			'ospg_get_product'     => 'get_product',
			'ospg_get_hover_image' => 'get_hover_image',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Return filtered / paginated product items.
	 */
	public function filter_products() {
		check_ajax_referer( 'ospg_nonce', 'nonce' );

		$query = OS_Product_Gallery_Shortcodes::query_products(
			array(
				'category'   => isset( $_POST['category'] ) ? sanitize_title( wp_unslash( $_POST['category'] ) ) : '',
				'categories' => isset( $_POST['categories'] ) ? OS_Product_Gallery_Shortcodes::parse_slugs( sanitize_text_field( wp_unslash( $_POST['categories'] ) ) ) : array(),
				'paged'      => isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1,
				'per_page'   => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 4,
				'orderby'    => isset( $_POST['orderby'] ) ? sanitize_key( wp_unslash( $_POST['orderby'] ) ) : 'date',
				'order'      => isset( $_POST['order'] ) ? sanitize_key( wp_unslash( $_POST['order'] ) ) : 'DESC',
			)
		);

		wp_send_json_success(
			array(
				'html'      => OS_Product_Gallery_Shortcodes::render_items( $query ),
				'max_pages' => (int) $query->max_num_pages,
				'found'     => (int) $query->found_posts,
			)
		);
	}

	/**
	 * Return the popup data for a single product.
	 */
	public function get_product() {
		check_ajax_referer( 'ospg_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? get_post( $product_id ) : null;

		if (
			! $product
			|| OS_Product_Gallery_CPT::POST_TYPE !== $product->post_type
			|| 'publish' !== $product->post_status
			|| post_password_required( $product )
		) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'os-product-gallery' ) ), 404 );
		}

		$image_ids = OS_Product_Gallery_Meta_Boxes::get_gallery_ids( $product_id );

		if ( empty( $image_ids ) && has_post_thumbnail( $product_id ) ) {
			$image_ids = array( get_post_thumbnail_id( $product_id ) );
		}

		$images = array();

		foreach ( $image_ids as $attachment_id ) {
			$full  = wp_get_attachment_image_src( $attachment_id, 'large' );
			$thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );

			if ( ! $full ) {
				continue;
			}

			$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

			$images[] = array(
				'id'    => (int) $attachment_id,
				'full'  => esc_url_raw( $full[0] ),
				'thumb' => esc_url_raw( $thumb ? $thumb[0] : $full[0] ),
				'alt'   => '' !== $alt ? $alt : get_the_title( $product_id ),
			);
		}

		wp_send_json_success(
			array(
				'title'       => get_the_title( $product_id ),
				'description' => wp_kses_post( wpautop( $product->post_excerpt ) ),
				'images'      => $images,
			)
		);
	}

	/**
	 * Return the second image of a product for the grid hover effect.
	 *
	 * The second image is the first gallery image that is not the one already shown on the card.
	 */
	public function get_hover_image() {
		check_ajax_referer( 'ospg_nonce', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? get_post( $product_id ) : null;

		if (
			! $product
			|| OS_Product_Gallery_CPT::POST_TYPE !== $product->post_type
			|| 'publish' !== $product->post_status
			|| post_password_required( $product )
		) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'os-product-gallery' ) ), 404 );
		}

		$card_id  = OS_Product_Gallery_Shortcodes::get_card_image_id( $product_id );
		$hover_id = 0;

		foreach ( OS_Product_Gallery_Meta_Boxes::get_gallery_ids( $product_id ) as $attachment_id ) {
			if ( (int) $attachment_id !== $card_id ) {
				$hover_id = (int) $attachment_id;
				break;
			}
		}

		$image = $hover_id ? wp_get_attachment_image_src( $hover_id, 'medium_large' ) : false;

		wp_send_json_success(
			array(
				'url' => $image ? esc_url_raw( $image[0] ) : '',
				'alt' => $image ? (string) get_post_meta( $hover_id, '_wp_attachment_image_alt', true ) : '',
			)
		);
	}
}