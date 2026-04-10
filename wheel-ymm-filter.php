<?php
/**
 * Plugin Name: Wheel YMM Filter
 * Description: Filter WooCommerce wheel products by vehicle Year / Make / Model. Uses the Wheel-Size API for the YMM taxonomy and lets admins manually attach fitments to each product or variation.
 * Version:     1.0.0
 * Author:      Collin Johnson
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * Text Domain: wheel-ymm-filter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WYMM_VERSION', '1.0.0' );
define( 'WYMM_FILE', __FILE__ );
define( 'WYMM_DIR', plugin_dir_path( __FILE__ ) );
define( 'WYMM_URL', plugin_dir_url( __FILE__ ) );
define( 'WYMM_TABLE', 'wymm_fitments' );

require_once WYMM_DIR . 'includes/class-wymm-api.php';
require_once WYMM_DIR . 'includes/class-wymm-fitments.php';
require_once WYMM_DIR . 'includes/class-wymm-ajax.php';
require_once WYMM_DIR . 'includes/class-wymm-admin.php';
require_once WYMM_DIR . 'includes/class-wymm-shortcode.php';
require_once WYMM_DIR . 'includes/class-wymm-query.php';

register_activation_hook( __FILE__, 'wymm_activate' );
function wymm_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = $wpdb->prefix . WYMM_TABLE;
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		product_id BIGINT UNSIGNED NOT NULL,
		variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
		make_slug VARCHAR(64) NOT NULL,
		model_slug VARCHAR(64) NOT NULL,
		year SMALLINT UNSIGNED NOT NULL,
		PRIMARY KEY  (id),
		KEY ymm (make_slug, model_slug, year),
		KEY product (product_id),
		KEY variation (variation_id)
	) {$charset};";

	dbDelta( $sql );
}

add_action( 'plugins_loaded', 'wymm_boot' );
function wymm_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Wheel YMM Filter</strong> requires WooCommerce to be active.</p></div>';
		} );
		return;
	}

	WYMM_Ajax::register();
	WYMM_Admin::register();
	WYMM_Shortcode::register();
	WYMM_Query::register();
}
