<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Products and categories are intentionally kept; only plugin options are removed.
 *
 * @package OS_Product_Gallery
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ospg_version' );
delete_option( 'ospg_install_date' );
delete_option( 'ospg_flush_rewrite_rules' );