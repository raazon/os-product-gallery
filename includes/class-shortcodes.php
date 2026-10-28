<?php

/**
 * Shortcodes.
 *
 * @package OS_Product_Gallery
 */

defined('ABSPATH') || exit;

/**
 * Registers the [os_product_gallery] shortcode and shared rendering helpers.
 */
class OS_Product_Gallery_Shortcodes
{

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		add_shortcode('os_product_gallery', array($this, 'render'));
		add_action('wp_enqueue_scripts', array($this, 'register_assets'));
	}

	/**
	 * Register (not enqueue) front-end assets. They load only when the shortcode runs.
	 */
	public function register_assets()
	{
		wp_register_style('os-product-gallery', OSPG_PLUGIN_URL . 'assets/css/os-product-gallery.css', array(), OSPG_VERSION);
		wp_register_script(
			'os-product-gallery',
			OSPG_PLUGIN_URL . 'assets/js/os-product-gallery.js',
			array('jquery'),
			OSPG_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_localize_script(
			'os-product-gallery',
			'ospgData',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce'   => wp_create_nonce('ospg_nonce'),
				'i18n'    => array(
					'close' => __('Close', 'os-product-gallery'),
					'prev'  => __('Previous image', 'os-product-gallery'),
					'next'  => __('Next image', 'os-product-gallery'),
					'error' => __('Something went wrong. Please try again.', 'os-product-gallery'),
				),
			)
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render($atts)
	{
		$atts = shortcode_atts(
			array(
				'category' => '',
				'per_page' => 4,
				'columns'  => 2,
				'orderby'  => 'date',
				'order'    => 'DESC',
				'filter'   => 'yes',
			),
			$atts,
			'os_product_gallery'
		);

		$per_page   = max(1, absint($atts['per_page']));
		$columns    = max(1, min(6, absint($atts['columns'])));
		$categories = self::parse_slugs($atts['category']);
		$show_filter = 'yes' === $atts['filter'];

		wp_enqueue_style('os-product-gallery');
		wp_enqueue_script('os-product-gallery');

		$query = self::query_products(
			array(
				'categories' => $categories,
				'per_page'   => $per_page,
				'orderby'    => $atts['orderby'],
				'order'      => $atts['order'],
			)
		);

		$terms = array();

		if ($show_filter) {
			$term_args = array(
				'taxonomy'   => OS_Product_Gallery_CPT::TAXONOMY,
				'hide_empty' => true,
			);

			if ($categories) {
				$term_args['slug'] = $categories;
			}

			$found = get_terms($term_args);
			$terms = is_wp_error($found) ? array() : $found;
		}

		ob_start();
?>
		<div class="ospg ospg--cols-<?php echo esc_attr($columns); ?>"
			data-per-page="<?php echo esc_attr($per_page); ?>"
			data-categories="<?php echo esc_attr(implode(',', $categories)); ?>"
			data-orderby="<?php echo esc_attr($atts['orderby']); ?>"
			data-order="<?php echo esc_attr($atts['order']); ?>"
			data-max-pages="<?php echo esc_attr($query->max_num_pages); ?>">

			<?php if ($terms) : ?>
				<ul class="ospg__filter">
					<li>
						<button type="button" class="ospg__filter-btn is-active" data-category="" aria-pressed="true">
							<?php esc_html_e('All', 'os-product-gallery'); ?>
						</button>
					</li>
					<?php foreach ($terms as $term) : ?>
						<li>
							<button type="button" class="ospg__filter-btn" data-category="<?php echo esc_attr($term->slug); ?>" aria-pressed="false">
								<?php echo esc_html($term->name); ?>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="ospg__grid" aria-live="polite">
				<?php echo self::render_items($query); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render_items(). 
				?>
			</div>

			<nav class="ospg__pagination" aria-label="<?php esc_attr_e('Gallery pagination', 'os-product-gallery'); ?>">
				<?php echo self::render_pagination(1, $query->max_num_pages); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside render_pagination(). 
				?>
			</nav>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Build a products query. Shared by the shortcode and Ajax handlers.
	 *
	 * @param array $args Query arguments.
	 * @return WP_Query
	 */
	public static function query_products($args = array())
	{
		$args = wp_parse_args(
			$args,
			array(
				'categories' => array(),
				'category'   => '',
				'paged'      => 1,
				'per_page'   => 4,
				'orderby'    => 'date',
				'order'      => 'DESC',
			)
		);

		$orderby    = in_array($args['orderby'], array('date', 'title', 'menu_order', 'rand'), true) ? $args['orderby'] : 'date';
		$order      = ('ASC' === strtoupper((string) $args['order'])) ? 'ASC' : 'DESC';
		$categories = (array) $args['categories'];
		$category   = (string) $args['category'];
		$slugs      = $categories;

		// The active filter must stay inside the categories allowed by the shortcode.
		if ('' !== $category && (empty($categories) || in_array($category, $categories, true))) {
			$slugs = array($category);
		}

		$query_args = array(
			'post_type'      => OS_Product_Gallery_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => max(1, absint($args['per_page'])),
			'paged'          => max(1, absint($args['paged'])),
			'orderby'        => $orderby,
			'order'          => $order,
		);

		if ($slugs) {
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required for category filtering.
				array(
					'taxonomy' => OS_Product_Gallery_CPT::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $slugs,
				),
			);
		}

		/**
		 * Filters the products WP_Query arguments.
		 *
		 * @since 1.0.0
		 *
		 * @param array $query_args Final query arguments.
		 * @param array $args       Raw arguments passed to the helper.
		 */
		return new WP_Query(apply_filters('ospg_query_args', $query_args, $args));
	}

	/**
	 * Render grid items for a query.
	 *
	 * @param WP_Query $query Products query.
	 * @return string
	 */
	public static function render_items(WP_Query $query)
	{
		if (! $query->have_posts()) {
			return '<p class="ospg__empty">' . esc_html__('No products found.', 'os-product-gallery') . '</p>';
		}

		ob_start();

		while ($query->have_posts()) {
			$query->the_post();

			$product_id = get_the_ID();
			$image_id   = self::get_card_image_id($product_id);
		?>
			<article class="ospg-item">
				<a href="<?php the_permalink(); ?>" class="ospg-item__link" data-product-id="<?php echo esc_attr($product_id); ?>">
					<span class="ospg-item__media">
						<?php if ($image_id) : ?>
							<?php echo wp_get_attachment_image($image_id, 'medium_large', false, array('loading' => 'lazy')); ?>
						<?php else : ?>
							<span class="ospg-item__placeholder" aria-hidden="true"><?php the_title(); ?></span>
						<?php endif; ?>
					</span>
					<span class="ospg-item__title"><?php the_title(); ?></span>
				</a>
			</article>
<?php
		}

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Render numbered pagination links (Prev 1 2 3 … 10 Next).
	 *
	 * Links use a "#page-N" hash; JavaScript reads the number and loads that page via Ajax.
	 *
	 * @param int $current Current page.
	 * @param int $total   Total pages.
	 * @return string
	 */
	public static function render_pagination($current, $total)
	{
		$current = max(1, absint($current));
		$total   = absint($total);

		if ($total < 2) {
			return '';
		}

		$links = paginate_links(
			array(
				'base'      => '#page-%#%',
				'format'    => '',
				'current'   => $current,
				'total'     => $total,
				'type'      => 'array',
				'prev_next' => false,
				'end_size'  => 1,
				'mid_size'  => 2,
			)
		);

		if (empty($links)) {
			return '';
		}

		$prev_label = esc_html__('Prev', 'os-product-gallery');
		$next_label = esc_html__('Next', 'os-product-gallery');

		$prev = ($current > 1)
			? sprintf('<a class="prev page-numbers" href="#page-%d">%s</a>', $current - 1, $prev_label)
			: sprintf('<span class="prev page-numbers is-disabled" aria-disabled="true">%s</span>', $prev_label);

		$next = ($current < $total)
			? sprintf('<a class="next page-numbers" href="#page-%d">%s</a>', $current + 1, $next_label)
			: sprintf('<span class="next page-numbers is-disabled" aria-disabled="true">%s</span>', $next_label);

		return $prev . implode('', $links) . $next;
	}

	/**
	 * Get the attachment ID shown on a grid card.
	 *
	 * Uses the featured image, then falls back to the first gallery image.
	 *
	 * @param int $product_id Product ID.
	 * @return int Attachment ID or 0.
	 */
	public static function get_card_image_id($product_id)
	{
		$id = (int) get_post_thumbnail_id($product_id);

		if (! $id) {
			$gallery = OS_Product_Gallery_Meta_Boxes::get_gallery_ids($product_id);
			$id      = $gallery ? (int) $gallery[0] : 0;
		}

		return $id;
	}

	/**
	 * Turn a comma separated list into an array of sanitized slugs.
	 *
	 * @param string $value Comma separated slugs.
	 * @return string[]
	 */
	public static function parse_slugs($value)
	{
		return array_values(array_filter(array_map('sanitize_title', explode(',', (string) $value))));
	}
}
