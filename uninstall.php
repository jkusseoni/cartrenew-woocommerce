<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 * Removes the cart snapshot table (it holds customer phone numbers) and settings.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin-owned table, removed on uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cartrenew_carts" );

delete_option( 'cartrenew_wc_settings' );
delete_option( 'cartrenew_wc_db_version' );
delete_transient( 'cartrenew_wc_activation_redirect' );
wp_clear_scheduled_hook( 'cartrenew_wc_check_abandoned' );
