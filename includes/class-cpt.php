<?php
/**
 * Custom post type and taxonomy.
 *
 * @package OS_Product_Gallery
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Products post type and the Product Categories taxonomy.
 */
class OS_Product_Gallery_CPT {

	const POST_TYPE = 'ospg_product';
	const TAXONOMY  = 'ospg_product_cat';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
	}

	/**
	 * Register the Products post type.
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => _x( 'Products', 'post type general name', 'os-product-gallery' ),
			'singular_name'         => _x( 'Product', 'post type singular name', 'os-product-gallery' ),
			'menu_name'             => _x( 'Products', 'admin menu', 'os-product-gallery' ),
			'name_admin_bar'        => _x( 'Product', 'add new on admin bar', 'os-product-gallery' ),
			'add_new'               => __( 'Add New', 'os-product-gallery' ),
			'add_new_item'          => __( 'Add New Product', 'os-product-gallery' ),
			'new_item'              => __( 'New Product', 'os-product-gallery' ),
			'edit_item'             => __( 'Edit Product', 'os-product-gallery' ),
			'view_item'             => __( 'View Product', 'os-product-gallery' ),
			'all_items'             => __( 'All Products', 'os-product-gallery' ),
			'search_items'          => __( 'Search Products', 'os-product-gallery' ),
			'not_found'             => __( 'No products found.', 'os-product-gallery' ),
			'not_found_in_trash'    => __( 'No products found in Trash.', 'os-product-gallery' ),
			'featured_image'        => __( 'Product Thumbnail', 'os-product-gallery' ),
			'set_featured_image'    => __( 'Set product thumbnail', 'os-product-gallery' ),
			'remove_featured_image' => __( 'Remove product thumbnail', 'os-product-gallery' ),
			'use_featured_image'    => __( 'Use as product thumbnail', 'os-product-gallery' ),
		);

		$args = array(
			'labels'       => $labels,
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-format-gallery',
			'menu_position' => 25,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'has_archive'  => false,
			'rewrite'      => array(
				'slug'       => 'gallery-product',
				'with_front' => false,
			),
		);

		/**
		 * Filters the Products post type arguments.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Post type arguments.
		 */
		register_post_type( self::POST_TYPE, apply_filters( 'ospg_post_type_args', $args ) );
	}

	/**
	 * Register the Product Categories taxonomy.
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => _x( 'Product Categories', 'taxonomy general name', 'os-product-gallery' ),
			'singular_name'     => _x( 'Product Category', 'taxonomy singular name', 'os-product-gallery' ),
			'search_items'      => __( 'Search Product Categories', 'os-product-gallery' ),
			'all_items'         => __( 'All Product Categories', 'os-product-gallery' ),
			'parent_item'       => __( 'Parent Category', 'os-product-gallery' ),
			'parent_item_colon' => __( 'Parent Category:', 'os-product-gallery' ),
			'edit_item'         => __( 'Edit Category', 'os-product-gallery' ),
			'update_item'       => __( 'Update Category', 'os-product-gallery' ),
			'add_new_item'      => __( 'Add New Category', 'os-product-gallery' ),
			'new_item_name'     => __( 'New Category Name', 'os-product-gallery' ),
			'menu_name'         => __( 'Categories', 'os-product-gallery' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => 'gallery-category',
				'with_front' => false,
			),
		);

		/**
		 * Filters the Product Categories taxonomy arguments.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Taxonomy arguments.
		 */
		register_taxonomy( self::TAXONOMY, array( self::POST_TYPE ), apply_filters( 'ospg_taxonomy_args', $args ) );
	}

	/**
	 * Flush rewrite rules once after activation or upgrade.
	 */
	public function maybe_flush_rewrite_rules() {
		if ( 'yes' === get_option( 'ospg_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'ospg_flush_rewrite_rules' );
		}
	}
}