<?php

/**
 * Product meta boxes.
 *
 * @package OS_Product_Gallery
 */

defined('ABSPATH') || exit;

/**
 * Adds the gallery images field to products.
 */
class OS_Product_Gallery_Meta_Boxes
{

	const META_GALLERY = '_ospg_gallery_ids';

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
		add_action('save_post_' . OS_Product_Gallery_CPT::POST_TYPE, array($this, 'save'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	/**
	 * Register meta boxes.
	 */
	public function add_meta_boxes()
	{
		add_meta_box(
			'ospg_product_media',
			__('Product Media', 'os-product-gallery'),
			array($this, 'render'),
			OS_Product_Gallery_CPT::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the media meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render($post)
	{
		wp_nonce_field('ospg_save_meta', 'ospg_meta_nonce');

		$ids   = self::get_gallery_ids($post->ID);
?>
		<div class="ospg-gallery-field">
			<p><strong><?php esc_html_e('Gallery Images', 'os-product-gallery'); ?></strong></p>
			<ul class="ospg-gallery-list">
				<?php foreach ($ids as $attachment_id) : ?>
					<li data-id="<?php echo esc_attr($attachment_id); ?>">
						<?php echo wp_get_attachment_image($attachment_id, 'thumbnail'); ?>
						<button type="button" class="ospg-gallery-remove" aria-label="<?php esc_attr_e('Remove image', 'os-product-gallery'); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<input type="hidden" class="ospg-gallery-ids" name="ospg_gallery_ids" value="<?php echo esc_attr(implode(',', $ids)); ?>" />
			<button type="button" class="button ospg-gallery-add"><?php esc_html_e('Add Images', 'os-product-gallery'); ?></button>
		</div>
<?php
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save($post_id)
	{
		if (! isset($_POST['ospg_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ospg_meta_nonce'])), 'ospg_save_meta')) {
			return;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}

		if (! current_user_can('edit_post', $post_id)) {
			return;
		}

		$ids = isset($_POST['ospg_gallery_ids'])
			? self::sanitize_ids(sanitize_text_field(wp_unslash($_POST['ospg_gallery_ids'])))
			: array();

		if ($ids) {
			update_post_meta($post_id, self::META_GALLERY, implode(',', $ids));
		} else {
			delete_post_meta($post_id, self::META_GALLERY);
		}
	}

	/**
	 * Enqueue admin assets on the product edit screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets($hook)
	{
		if (! in_array($hook, array('post.php', 'post-new.php'), true)) {
			return;
		}

		$screen = get_current_screen();

		if (! $screen || OS_Product_Gallery_CPT::POST_TYPE !== $screen->post_type) {
			return;
		}

		$suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';

		wp_enqueue_media();
		wp_enqueue_style('os-product-gallery-admin', OSPG_PLUGIN_URL . 'assets/css/os-product-gallery-admin.css', array(), OSPG_VERSION);
		wp_enqueue_script(
			'os-product-gallery-admin',
			OSPG_PLUGIN_URL . 'assets/js/os-product-gallery-admin.js',
			array('jquery', 'jquery-ui-sortable'),
			OSPG_VERSION,
			true
		);
		wp_localize_script(
			'os-product-gallery-admin',
			'ospgAdmin',
			array(
				'title'  => __('Select gallery images', 'os-product-gallery'),
				'button' => __('Add to gallery', 'os-product-gallery'),
				'remove' => __('Remove image', 'os-product-gallery'),
			)
		);
	}

	/**
	 * Get gallery attachment IDs for a product.
	 *
	 * @param int $post_id Product ID.
	 * @return int[]
	 */
	public static function get_gallery_ids($post_id)
	{
		return self::sanitize_ids(get_post_meta($post_id, self::META_GALLERY, true));
	}

	/**
	 * Convert a comma separated string into a clean array of IDs.
	 *
	 * @param string $value Comma separated IDs.
	 * @return int[]
	 */
	public static function sanitize_ids($value)
	{
		return array_values(array_unique(array_filter(array_map('absint', explode(',', (string) $value)))));
	}
}
