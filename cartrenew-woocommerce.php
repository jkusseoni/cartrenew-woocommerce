<?php
/**
 * Plugin Name: CartRenew for WooCommerce
 * Plugin URI:  https://www.cartrenew.com/en/woocommerce
 * Description: WhatsApp-based cart abandonment recovery for WooCommerce, powered by CartRenew.
 * Version:     0.1.2
 * Author:      CartRenew
 * Author URI:  https://www.cartrenew.com
 * License:     GPL v2 or later
 * Text Domain: cartrenew-for-woocommerce
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'CARTRENEW_WC_VERSION', '0.1.2' );
define( 'CARTRENEW_WC_PLUGIN_FILE', __FILE__ );
define( 'CARTRENEW_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARTRENEW_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Default backend endpoint — can be overridden from Settings > CartRenew.
define( 'CARTRENEW_WC_DEFAULT_API_BASE', 'https://www.cartrenew.com/api/woocommerce' );

/**
 * Bail early with an admin notice if WooCommerce isn't active.
 */
function cartrenew_wc_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>';
	esc_html_e( 'CartRenew for WooCommerce requires WooCommerce to be installed and active.', 'cartrenew-for-woocommerce' );
	echo '</p></div>';
}

/**
 * Load the plugin once WooCommerce is confirmed active.
 */
function cartrenew_wc_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'cartrenew_wc_missing_woocommerce_notice' );
		return;
	}

	require_once CARTRENEW_WC_PLUGIN_DIR . 'includes/class-cr-db.php';
	require_once CARTRENEW_WC_PLUGIN_DIR . 'includes/class-cr-settings.php';
	require_once CARTRENEW_WC_PLUGIN_DIR . 'includes/class-cr-consent.php';
	require_once CARTRENEW_WC_PLUGIN_DIR . 'includes/class-cr-tracker.php';
	require_once CARTRENEW_WC_PLUGIN_DIR . 'includes/class-cr-api.php';
	require_once CARTRENEW_WC_PLUGIN_DIR . 'includes/class-cr-cron.php';

	CartRenew_WC_Settings::init();
	CartRenew_WC_Consent::init();
	CartRenew_WC_Tracker::init();
	CartRenew_WC_Cron::init();
}
add_action( 'plugins_loaded', 'cartrenew_wc_init' );

/**
 * Create DB table + schedule cron on activation.
 */
function cartrenew_wc_activate() {
	require_once CARTRENEW_WC_PLUGIN_DIR . 'includes/class-cr-db.php';
	CartRenew_WC_DB::create_table();

	if ( ! wp_next_scheduled( 'cartrenew_wc_check_abandoned' ) ) {
		wp_schedule_event( time(), 'cartrenew_five_minutes', 'cartrenew_wc_check_abandoned' );
	}
}
register_activation_hook( __FILE__, 'cartrenew_wc_activate' );

/**
 * Clear cron on deactivation. Table is left intact (data isn't destructive on deactivate).
 */
function cartrenew_wc_deactivate() {
	wp_clear_scheduled_hook( 'cartrenew_wc_check_abandoned' );
}
register_deactivation_hook( __FILE__, 'cartrenew_wc_deactivate' );

/**
 * Register a 5-minute cron schedule.
 */
function cartrenew_wc_add_cron_interval( $schedules ) {
	$schedules['cartrenew_five_minutes'] = array(
		'interval' => 300,
		'display'  => __( 'Every 5 minutes (CartRenew)', 'cartrenew-for-woocommerce' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'cartrenew_wc_add_cron_interval' );
