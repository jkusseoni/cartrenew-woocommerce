<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the custom table that stores cart snapshots.
 *
 * Table: {prefix}cartrenew_carts
 * One row per tracked cart (keyed by session/cart hash). Status moves:
 *   tracking -> pending_send -> sent -> recovered (or opted_out)
 */
class CartRenew_WC_DB {

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'cartrenew_carts';
	}

	public static function create_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			cart_key VARCHAR(191) NOT NULL,
			customer_name VARCHAR(191) NULL,
			phone_number VARCHAR(32) NULL,
			consent TINYINT(1) NOT NULL DEFAULT 0,
			cart_contents LONGTEXT NULL,
			cart_total DECIMAL(12,2) NULL,
			checkout_url TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'tracking',
			order_id BIGINT UNSIGNED NULL,
			last_activity DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			sent_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY cart_key (cart_key),
			KEY status (status),
			KEY phone_number (phone_number)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Insert or update the tracking row for a given cart_key.
	 */
	public static function upsert_cart( $cart_key, $data ) {
		global $wpdb;
		$table = self::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table, no core API available
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- transient cart data, caching not beneficial
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status FROM {$wpdb->prefix}cartrenew_carts WHERE cart_key = %s",
				$cart_key
			)
		);

		$now = current_time( 'mysql' );

		if ( $existing ) {
			// Don't overwrite a cart that's already sent/recovered/opted_out back to "tracking".
			$protected_statuses = array( 'sent', 'recovered', 'opted_out' );
			if ( in_array( $existing->status, $protected_statuses, true ) && empty( $data['status'] ) ) {
				unset( $data['status'] );
			}

			$data['last_activity'] = $now;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table, no core API available
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- transient cart data, caching not beneficial
			$wpdb->update( $table, $data, array( 'id' => $existing->id ) );
			return $existing->id;
		}

		$data['cart_key']      = $cart_key;
		$data['last_activity'] = $now;
		$data['created_at']    = $now;
		if ( empty( $data['status'] ) ) {
			$data['status'] = 'tracking';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table, no core API available
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- transient cart data, caching not beneficial
		$wpdb->insert( $table, $data );
		return $wpdb->insert_id;
	}

	public static function mark_status( $cart_key, $status, $extra = array() ) {
		global $wpdb;
		$table = self::table_name();

		$data = array_merge( array( 'status' => $status ), $extra );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table, no core API available
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- transient cart data, caching not beneficial
		$wpdb->update( $table, $data, array( 'cart_key' => $cart_key ) );
	}

	/**
	 * Carts eligible for the abandonment webhook:
	 * has phone + consent, still "tracking", inactive for >= $minutes.
	 */
	public static function get_abandoned_carts( $minutes = 20, $limit = 50 ) {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * 60 ) - ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table, no core API available
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- transient cart data, caching not beneficial
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cartrenew_carts
				 WHERE status = %s
				 AND consent = %d
				 AND phone_number IS NOT NULL AND phone_number != %s
				 AND last_activity <= %s
				 ORDER BY last_activity ASC
				 LIMIT %d",
				'tracking',
				1,
				'',
				$cutoff,
				$limit
			)
		);
	}
}
