<?php
/**
 * Plugin Name:       OS Product Gallery
 * Plugin URI:        https://example.com/os-product-gallery
 * Description:       A simple, filterable product gallery with a popup image gallery.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Offshore IT
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       os-product-gallery
 * Domain Path:       /languages
 *
 * @package OS_Product_Gallery
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class OS_Product_Gallery {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * Singleton instance of the plugin.
	 *
	 * @var OS_Product_Gallery
	 */
	protected static $_instance = null;

	/**
	 * Custom post type component.
	 *
	 * @var OS_Product_Gallery_CPT
	 */
	public $cpt;

	/**
	 * Meta boxes component.
	 *
	 * @var OS_Product_Gallery_Meta_Boxes
	 */
	public $meta_boxes;

	/**
	 * Shortcodes component.
	 *
	 * @var OS_Product_Gallery_Shortcodes
	 */
	public $shortcodes;

	/**
	 * Ajax component.
	 *
	 * @var OS_Product_Gallery_Ajax
	 */
	public $ajax;

	/**
	 * Main OS_Product_Gallery Instance.
	 *
	 * Ensures only one instance of OS_Product_Gallery is loaded or can be loaded.
	 *
	 * @return OS_Product_Gallery - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * OS_Product_Gallery Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'os-product-gallery' ), esc_html( $this->version ) );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing is forbidden.', 'os-product-gallery' ), esc_html( $this->version ) );
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		$this->define( 'OSPG_VERSION', $this->version );
		$this->define( 'OSPG_PLUGIN_FILE', __FILE__ );
		$this->define( 'OSPG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		$this->define( 'OSPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		$this->define( 'OSPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Define a constant if it is not already defined.
	 *
	 * @param string $name  Constant name.
	 * @param mixed  $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once OSPG_PLUGIN_DIR . 'includes/class-cpt.php';
		require_once OSPG_PLUGIN_DIR . 'includes/class-meta-boxes.php';
		require_once OSPG_PLUGIN_DIR . 'includes/class-shortcodes.php';
		require_once OSPG_PLUGIN_DIR . 'includes/class-ajax.php';
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		register_activation_hook( OSPG_PLUGIN_FILE, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( OSPG_PLUGIN_FILE, array( __CLASS__, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init_components' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'init', array( $this, 'check_version' ), 5 );
	}

	/**
	 * Instantiate plugin components.
	 */
	public function init_components() {
		$this->cpt        = new OS_Product_Gallery_CPT();
		$this->meta_boxes = new OS_Product_Gallery_Meta_Boxes();
		$this->shortcodes = new OS_Product_Gallery_Shortcodes();
		$this->ajax       = new OS_Product_Gallery_Ajax();

		/**
		 * Fires once all plugin components are loaded.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ospg_loaded' );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'os-product-gallery', false, dirname( OSPG_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Run upgrade routines when the stored version differs from the code version.
	 */
	public function check_version() {
		$installed = get_option( 'ospg_version', '0' );

		if ( version_compare( $installed, OSPG_VERSION, '<' ) ) {
			$this->upgrade( $installed );
		}
	}

	/**
	 * Upgrade routine.
	 *
	 * @param string $from Previously installed version.
	 */
	private function upgrade( $from ) {
		// Example for future releases:
		// if ( version_compare( $from, '1.1.0', '<' ) ) { ... }.

		update_option( 'ospg_version', OSPG_VERSION );
		update_option( 'ospg_flush_rewrite_rules', 'yes' );

		/**
		 * Fires after the plugin has been upgraded.
		 *
		 * @since 1.0.0
		 *
		 * @param string $from Previous version.
		 * @param string $to   Current version.
		 */
		do_action( 'ospg_upgraded', $from, OSPG_VERSION );
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		if ( false === get_option( 'ospg_install_date' ) ) {
			add_option( 'ospg_install_date', time() );
		}

		update_option( 'ospg_version', OSPG_VERSION );
		// Rewrite rules are flushed on the next `init`, after the CPT is registered.
		update_option( 'ospg_flush_rewrite_rules', 'yes' );
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		unregister_post_type( OS_Product_Gallery_CPT::POST_TYPE );
		unregister_taxonomy( OS_Product_Gallery_CPT::TAXONOMY );
		flush_rewrite_rules();
	}
}

/**
 * Returns the main instance of the plugin.
 *
 * @return OS_Product_Gallery
 */
function ospg() {
	return OS_Product_Gallery::instance();
}

ospg();